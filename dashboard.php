<?php
require_once 'config.php';
require_once 'audit_system.php';

// Verificar autenticación
if (!isLoggedIn() || !checkSessionTimeout()) {
    header('Location: auth/login.php');
    exit;
}

$pageTitle = 'Dashboard';

// Establecer conexión a la base de datos
try {
    $pdo = getConnection();
} catch (Exception $e) {
    die("Error de conexión a la base de datos: " . $e->getMessage());
}

// SIEMPRE usar la semana en curso por defecto (ignorar parámetros URL en carga inicial)
// Solo usar parámetros URL si vienen de una actualización AJAX
$isAjaxUpdate = isset($_GET['ajax_update']) && $_GET['ajax_update'] === '1';

if ($isAjaxUpdate) {
    // Solo durante actualizaciones AJAX, usar los parámetros de la URL
    $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('monday this week'));
    $endDate = $_GET['end_date'] ?? date('Y-m-d', strtotime('sunday this week'));
} else {
    // En navegación normal o refresh, SIEMPRE cargar semana actual
    $startDate = date('Y-m-d', strtotime('monday this week'));
    $endDate = date('Y-m-d', strtotime('sunday this week'));
}

// Validar fechas
if (!$startDate || !$endDate) {
    $startDate = date('Y-m-d', strtotime('monday this week'));
    $endDate = date('Y-m-d', strtotime('sunday this week'));
}

// Funciones para obtener datos del dashboard
function getFinancialSummary($pdo, $startDate, $endDate) {
    
    // Obtener total de ingresos en el rango de fechas
    $totalIncome = 0;
    try {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(ip.amount), 0) as total_income
            FROM income_payments ip 
            WHERE DATE(ip.created_at) BETWEEN ? AND ?
        ");
        $stmt->execute([$startDate, $endDate]);
        $totalIncome = $stmt->fetchColumn() ?: 0;
    } catch (Exception $e) {
        error_log("Error en consulta de ingresos: " . $e->getMessage());
    }
    
    // Obtener total de gastos en el rango de fechas
    $totalExpenses = 0;
    try {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(e.total_amount), 0) as total_expenses
            FROM expenses e 
            WHERE e.expense_date BETWEEN ? AND ?
        ");
        $stmt->execute([$startDate, $endDate]);
        $totalExpenses = $stmt->fetchColumn() ?: 0;
    } catch (Exception $e) {
        error_log("Error en consulta de gastos: " . $e->getMessage());
    }
    
    // Calcular balance
    $balance = $totalIncome - $totalExpenses;
    
    return [
        'income' => $totalIncome,
        'expenses' => $totalExpenses,
        'balance' => $balance
    ];
}

function getBankAccountBalances($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT name, bank_name, account_number, balance, account_type 
            FROM bank_accounts 
            ORDER BY balance DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Tabla bank_accounts puede no existir
        return [];
    }
}

// Función auxiliar para obtener contratistas de un ingreso
function getIncomeContractors($pdo, $incomeId) {
    try {
        $stmt = $pdo->prepare("
            SELECT contractor_ids FROM incomes WHERE id = ?
        ");
        $stmt->execute([$incomeId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['contractor_ids']) {
            $contractorIds = json_decode($result['contractor_ids'], true);
            if (is_array($contractorIds) && count($contractorIds) > 0) {
                $placeholders = str_repeat('?,', count($contractorIds) - 1) . '?';
                $stmt = $pdo->prepare("
                    SELECT name FROM contractors WHERE id IN ($placeholders)
                ");
                $stmt->execute($contractorIds);
                $contractors = $stmt->fetchAll(PDO::FETCH_COLUMN);
                return implode(', ', $contractors);
            }
        }
        return '';
    } catch (Exception $e) {
        return '';
    }
}

function getRecentActivity($pdo, $limit = 5) {
    try {
        // Intentar obtener actividades del sistema de auditoría primero
        if (class_exists('AuditSystem')) {
            $audit = new AuditSystem();
            $auditActivities = $audit->getRecentActivities($limit);
            
            if (!empty($auditActivities)) {
                // Formatear las actividades del sistema de auditoría con información adicional
                $formattedActivities = [];
                foreach ($auditActivities as $activity) {
                    $formatted = [
                        'type' => $activity['entity_type'],
                        'date' => $activity['created_at'],
                        'description' => $activity['description'], // Usar descripción directa del audit
                        'amount' => null,
                        'account_name' => $activity['username'] ?? 'Sistema',
                        'user' => $activity['username'],
                        'action_icon' => $activity['action_icon'],
                        'entity_name' => $activity['entity_name'],
                        'team_name' => '',
                        'contractors' => '',
                        'vendor_name' => ''
                    ];
                    
                    // Obtener información adicional basada en el tipo de entidad
                    if ($activity['entity_type'] == 'income' && !empty($activity['entity_id'])) {
                        try {
                            $stmt = $pdo->prepare("
                                SELECT i.id, i.invoice_number, i.total_amount,
                                       t.name as team_name,
                                       ba.name as account_name
                                FROM incomes i
                                LEFT JOIN teams t ON i.team_id = t.id
                                LEFT JOIN income_payments ip ON ip.income_id = i.id
                                LEFT JOIN bank_accounts ba ON ip.bank_account_id = ba.id
                                WHERE i.id = ?
                                LIMIT 1
                            ");
                            $stmt->execute([$activity['entity_id']]);
                            $incomeData = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($incomeData) {
                                $formatted['income_id'] = $incomeData['id'];
                                $formatted['team_name'] = $incomeData['team_name'] ?? '';
                                $formatted['account_name'] = $incomeData['account_name'] ?? 'Sistema';
                                $formatted['amount'] = $incomeData['total_amount'];
                                $formatted['contractors'] = getIncomeContractors($pdo, $incomeData['id']);
                            }
                        } catch (Exception $e) {
                            // Continuar sin información adicional
                        }
                    } elseif ($activity['entity_type'] == 'expense' && !empty($activity['entity_id'])) {
                        try {
                            $stmt = $pdo->prepare("
                                SELECT e.id, e.expense_number, e.total_amount,
                                       t.name as team_name,
                                       v.name as vendor_name,
                                       ba.name as account_name
                                FROM expenses e
                                LEFT JOIN teams t ON e.team_id = t.id
                                LEFT JOIN vendors v ON e.vendor_id = v.id
                                LEFT JOIN bank_accounts ba ON e.bank_account_id = ba.id
                                WHERE e.id = ?
                                LIMIT 1
                            ");
                            $stmt->execute([$activity['entity_id']]);
                            $expenseData = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($expenseData) {
                                $formatted['expense_id'] = $expenseData['id'];
                                $formatted['team_name'] = $expenseData['team_name'] ?? '';
                                $formatted['vendor_name'] = $expenseData['vendor_name'] ?? '';
                                $formatted['account_name'] = $expenseData['account_name'] ?? 'Sistema';
                                $formatted['amount'] = $expenseData['total_amount'];
                            }
                        } catch (Exception $e) {
                            // Continuar sin información adicional
                        }
                    }
                    
                    $formattedActivities[] = $formatted;
                }
                
                // Si encontramos actividades de auditoría mejoradas, las devolvemos
                if (!empty($formattedActivities)) {
                    return $formattedActivities;
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error obteniendo actividades de auditoría: " . $e->getMessage());
    }
    
    // Fallback: usar el método anterior si no hay actividades de auditoría
    // IMPORTANTE: Estas consultas NO deben estar filtradas por fechas
    // ya que queremos las actividades más recientes del sistema
    $activities = [];
    
    // Obtener últimos ingresos (SIN filtro de fechas)
    try {
        $stmt = $pdo->prepare("
            SELECT 'income' as type, 
                   COALESCE(ip.created_at, i.created_at) as date, 
                   CONCAT('💰 Pago recibido $', ROUND(ip.amount, 2), ' - Factura: ', COALESCE(i.invoice_number, 'Sin número'), 
                          CASE WHEN t.name IS NOT NULL THEN CONCAT(' (', t.name, ')') ELSE '' END) as description,
                   ip.amount, 
                   COALESCE(ba.name, 'Sin cuenta') as account_name,
                   COALESCE(t.name, 'Sin equipo') as team_name,
                   '' as contractors,
                   'Sistema' as user,
                   '💰' as action_icon,
                   'Ingreso' as entity_name,
                   i.id as income_id,
                   i.invoice_number,
                   'income_payments' as source_table
            FROM income_payments ip
            LEFT JOIN incomes i ON ip.income_id = i.id
            LEFT JOIN bank_accounts ba ON ip.bank_account_id = ba.id
            LEFT JOIN teams t ON i.team_id = t.id
            WHERE ip.created_at IS NOT NULL
            ORDER BY ip.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit * 2]); // Obtener más registros para asegurar variedad
        $incomes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener contratistas para cada ingreso
        foreach ($incomes as &$income) {
            $income['contractors'] = getIncomeContractors($pdo, $income['income_id']);
        }
        
        $activities = array_merge($activities, $incomes);
    } catch (Exception $e) {
        error_log("Error obteniendo ingresos para actividad reciente: " . $e->getMessage());
    }
    
    // Obtener últimos ingresos creados (tabla incomes)
    try {
        $stmt = $pdo->prepare("
            SELECT 'income_created' as type, 
                   i.created_at as date,
                   CONCAT('🆕 Ingreso creado $', ROUND(i.total_amount, 2), ' - Factura: ', COALESCE(i.invoice_number, 'Sin número'),
                          CASE WHEN t.name IS NOT NULL THEN CONCAT(' (', t.name, ')') ELSE '' END) as description,
                   i.total_amount as amount, 
                   COALESCE(t.name, 'Sin equipo') as account_name,
                   COALESCE(t.name, 'Sin equipo') as team_name,
                   '' as contractors,
                   'Sistema' as user,
                   '🆕' as action_icon,
                   'Ingreso' as entity_name,
                   i.id as income_id,
                   i.invoice_number,
                   'incomes' as source_table
            FROM incomes i
            LEFT JOIN teams t ON i.team_id = t.id
            WHERE i.created_at IS NOT NULL
            ORDER BY i.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $incomesCreated = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener contratistas para cada ingreso creado
        foreach ($incomesCreated as &$incomeCreated) {
            $incomeCreated['contractors'] = getIncomeContractors($pdo, $incomeCreated['income_id']);
        }
        
        $activities = array_merge($activities, $incomesCreated);
    } catch (Exception $e) {
        error_log("Error obteniendo ingresos creados para actividad reciente: " . $e->getMessage());
    }
    
    // Obtener últimos gastos (SIN filtro de fechas)
    try {
        $stmt = $pdo->prepare("
            SELECT 'expense' as type, 
                   e.created_at as date,
                   CONCAT('💸 Gasto $', ROUND(e.total_amount, 2), ' - ', COALESCE(e.expense_number, 'Gasto sin número'),
                          CASE WHEN t.name IS NOT NULL THEN CONCAT(' (', t.name, ')') ELSE '' END,
                          CASE WHEN v.name IS NOT NULL THEN CONCAT(' - ', v.name) ELSE '' END) as description,
                   e.total_amount as amount, 
                   COALESCE(ba.name, 'Gasto directo') as account_name,
                   COALESCE(t.name, 'Sin equipo') as team_name,
                   COALESCE(v.name, 'Sin proveedor') as vendor_name,
                   'Sistema' as user,
                   '💸' as action_icon,
                   'Gasto' as entity_name,
                   e.id as expense_id,
                   e.expense_number,
                   'expenses' as source_table
            FROM expenses e
            LEFT JOIN bank_accounts ba ON e.bank_account_id = ba.id
            LEFT JOIN teams t ON e.team_id = t.id
            LEFT JOIN vendors v ON e.vendor_id = v.id
            WHERE e.created_at IS NOT NULL
            ORDER BY e.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit * 2]); // Obtener más registros para asegurar variedad
        $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $activities = array_merge($activities, $expenses);
    } catch (Exception $e) {
        error_log("Error obteniendo gastos para actividad reciente: " . $e->getMessage());
    }
    
    // Obtener últimas transacciones (SIN filtro de fechas)
    try {
        $stmt = $pdo->prepare("
            SELECT 'transaction' as type,
                   t.created_at as date,
                   CONCAT('🏦 Transacción: ', 
                          CASE 
                              WHEN t.type = 'payment_income' THEN 'Ingreso'
                              WHEN t.type = 'payment_fee' THEN 'Comisión'
                              ELSE t.type
                          END, 
                          ' - ', COALESCE(ba.name, 'Cuenta')) as description,
                   ABS(t.amount) as amount,
                   COALESCE(ba.name, 'Sin cuenta') as account_name,
                   'Sistema' as user,
                   '🏦' as action_icon,
                   'Transacción' as entity_name
            FROM transactions t
            LEFT JOIN bank_accounts ba ON t.bank_account_id = ba.id
            WHERE t.created_at IS NOT NULL
            ORDER BY t.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $activities = array_merge($activities, $transactions);
    } catch (Exception $e) {
        error_log("Error obteniendo transacciones para actividad reciente: " . $e->getMessage());
    }
    
    // Ordenar por fecha y limitar a las más recientes
    usort($activities, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
    
    return array_slice($activities, 0, $limit);
}

function getPendingIncomes($pdo, $limit = 5) {
    $pendingIncomes = [];
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                i.id, 
                i.invoice_number, 
                i.date, 
                i.total_income, 
                COALESCE(SUM(ip.amount), 0) as total_paid,
                (i.total_income - COALESCE(SUM(ip.amount), 0)) as pending_amount,
                t.name as team_name,
                GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') as contractors
            FROM 
                incomes i
            LEFT JOIN 
                income_payments ip ON i.id = ip.income_id
            LEFT JOIN 
                teams t ON i.team_id = t.id
            LEFT JOIN 
                income_contractors ic ON i.id = ic.income_id
            LEFT JOIN 
                contractors c ON ic.contractor_id = c.id
            WHERE 
                (i.total_income - COALESCE(SUM(ip.amount), 0)) > 0
            GROUP BY 
                i.id
            ORDER BY 
                i.date DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $pendingIncomes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error obteniendo ingresos pendientes: " . $e->getMessage());
        
        // Intento alternativo si la consulta anterior falla
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    i.id, 
                    i.invoice_number, 
                    i.date, 
                    i.total_income,
                    COALESCE(SUM(ip.amount), 0) as total_paid,
                    (i.total_income - COALESCE(SUM(ip.amount), 0)) as pending_amount,
                    t.name as team_name
                FROM 
                    incomes i
                LEFT JOIN 
                    income_payments ip ON i.id = ip.income_id
                LEFT JOIN 
                    teams t ON i.team_id = t.id
                GROUP BY 
                    i.id
                HAVING 
                    pending_amount > 0
                ORDER BY 
                    i.date DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $pendingIncomes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e2) {
            error_log("Error en consulta alternativa de ingresos pendientes: " . $e2->getMessage());
        }
    }
    
    return $pendingIncomes;
}

function getDailyData($pdo, $startDate, $endDate) {
    
    // Crear array de fechas en el rango
    $dates = [];
    $current = new DateTime($startDate);
    $end = new DateTime($endDate);
    
    while ($current <= $end) {
        $dates[$current->format('Y-m-d')] = [
            'date' => $current->format('Y-m-d'),
            'income' => 0,
            'expenses' => 0
        ];
        $current->add(new DateInterval('P1D'));
    }
    
    // Obtener ingresos por día
    try {
        $stmt = $pdo->prepare("
            SELECT DATE(ip.created_at) as payment_date, SUM(amount) as total
            FROM income_payments ip
            WHERE DATE(ip.created_at) BETWEEN ? AND ?
            GROUP BY DATE(ip.created_at)
        ");
        $stmt->execute([$startDate, $endDate]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (isset($dates[$row['payment_date']])) {
                $dates[$row['payment_date']]['income'] = $row['total'];
            }
        }
    } catch (Exception $e) {
        error_log("Error obteniendo ingresos diarios: " . $e->getMessage());
    }
    
    // Obtener gastos por día
    try {
        $stmt = $pdo->prepare("
            SELECT expense_date, SUM(total_amount) as total
            FROM expenses 
            WHERE expense_date BETWEEN ? AND ?
            GROUP BY expense_date
        ");
        $stmt->execute([$startDate, $endDate]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (isset($dates[$row['expense_date']])) {
                $dates[$row['expense_date']]['expenses'] = $row['total'];
            }
        }
    } catch (Exception $e) {
        // Tabla expenses puede no existir
    }
    
    return array_values($dates);
}

// Obtener datos para el dashboard
try {
    $financialSummary = getFinancialSummary($pdo, $startDate, $endDate);
    $bankAccounts = getBankAccountBalances($pdo);
    $recentActivity = getRecentActivity($pdo);
    $pendingIncomes = getPendingIncomes($pdo, 5);
    $dailyData = getDailyData($pdo, $startDate, $endDate);
    
    // Determinar qué botón de período está activo
    $today = new DateTime();
    $startDateObj = new DateTime($startDate);
    $endDateObj = new DateTime($endDate);
    
    // Por defecto, ninguno está activo
    $isCurrentWeek = false;
    $isPreviousWeek = false;
    $isCurrentMonth = false;
    $isPreviousMonth = false;
    
    // Método más simple y directo para determinar el período activo
    // Lunes de la semana actual
    $currentWeekStart = new DateTime('monday this week');
    $currentWeekStart->setTime(0, 0, 0);
    // Domingo de la semana actual
    $currentWeekEnd = clone $currentWeekStart;
    $currentWeekEnd->modify('+6 days');
    $currentWeekEnd->setTime(23, 59, 59);

    // Verificar si es la semana actual (comparando las fechas como strings)
    if ($startDate == $currentWeekStart->format('Y-m-d') && 
        $endDate == $currentWeekEnd->format('Y-m-d')) {
        $isCurrentWeek = true;
    }

    // Lunes de la semana anterior
    $previousWeekStart = clone $currentWeekStart;
    $previousWeekStart->modify('-7 days');
    // Domingo de la semana anterior
    $previousWeekEnd = clone $currentWeekEnd;
    $previousWeekEnd->modify('-7 days');

    // Verificar si es la semana anterior (comparando las fechas como strings)
    if ($startDate == $previousWeekStart->format('Y-m-d') && 
        $endDate == $previousWeekEnd->format('Y-m-d')) {
        $isPreviousWeek = true;
    }

    // Primer día del mes actual
    $currentMonthStart = new DateTime('first day of this month');
    $currentMonthStart->setTime(0, 0, 0);
    // Último día del mes actual
    $currentMonthEnd = new DateTime('last day of this month');
    $currentMonthEnd->setTime(23, 59, 59);

    // Verificar si es el mes actual (comparando las fechas como strings)
    if ($startDate == $currentMonthStart->format('Y-m-d') && 
        $endDate == $currentMonthEnd->format('Y-m-d')) {
        $isCurrentMonth = true;
    }

    // Primer día del mes anterior
    $previousMonthStart = new DateTime('first day of last month');
    $previousMonthStart->setTime(0, 0, 0);
    // Último día del mes anterior
    $previousMonthEnd = new DateTime('last day of last month');
    $previousMonthEnd->setTime(23, 59, 59);

    // Verificar si es el mes anterior (comparando las fechas como strings)
    if ($startDate == $previousMonthStart->format('Y-m-d') && 
        $endDate == $previousMonthEnd->format('Y-m-d')) {
        $isPreviousMonth = true;
    }

    // Añadir depuración
    error_log("DEBUG - Fechas actuales: $startDate a $endDate");
    error_log("DEBUG - Semana actual: " . $currentWeekStart->format('Y-m-d') . " a " . $currentWeekEnd->format('Y-m-d') . " - Activo: " . ($isCurrentWeek ? 'Sí' : 'No'));
    error_log("DEBUG - Semana anterior: " . $previousWeekStart->format('Y-m-d') . " a " . $previousWeekEnd->format('Y-m-d') . " - Activo: " . ($isPreviousWeek ? 'Sí' : 'No'));
    error_log("DEBUG - Mes actual: " . $currentMonthStart->format('Y-m-d') . " a " . $currentMonthEnd->format('Y-m-d') . " - Activo: " . ($isCurrentMonth ? 'Sí' : 'No'));
    error_log("DEBUG - Mes anterior: " . $previousMonthStart->format('Y-m-d') . " a " . $previousMonthEnd->format('Y-m-d') . " - Activo: " . ($isPreviousMonth ? 'Sí' : 'No'));

} catch (Exception $e) {
    // En caso de error, inicializar con valores por defecto
    $financialSummary = ['income' => 0, 'expenses' => 0, 'balance' => 0];
    $bankAccounts = [];
    $recentActivity = [];
    $pendingIncomes = [];
    $dailyData = [];
    $isCurrentWeek = false;
    $isPreviousWeek = false;
    $isCurrentMonth = false;
    $isPreviousMonth = false;
    error_log("Error en dashboard: " . $e->getMessage());
}
?>

<?php include 'includes/header.php'; ?>

<div class="main-layout">
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="content">
        <div class="content-header">
            <h1 class="content-title">
                <i class="fas fa-chart-line"></i>
                Dashboard Financiero
            </h1>
            <p class="content-subtitle">Resumen de ingresos y gastos del periodo seleccionado</p>
        </div>
        
        <!-- Navegación de Fechas -->
        <div class="card" style="margin-bottom: 24px;">
            <div class="dashboard-nav" style="padding: 20px; display: flex; flex-direction: column; gap: 15px;">
                <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 12px;">
                    <label style="font-weight: 500; color: var(--text-primary);">
                        <i class="fas fa-calendar-alt" style="margin-right: 6px; color: var(--primary-color);"></i>
                        Periodo:
                    </label>
                    <div style="position: relative;">
                        <input type="text" id="startDate" value="<?php echo $startDate; ?>" class="form-input flatpickr-date" placeholder="Fecha inicio" style="width: auto; min-width: 150px; padding-right: 30px;">
                        <i class="fas fa-calendar" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: var(--text-secondary); pointer-events: none;"></i>
                    </div>
                    <span style="color: var(--text-secondary);">hasta</span>
                    <div style="position: relative;">
                        <input type="text" id="endDate" value="<?php echo $endDate; ?>" class="form-input flatpickr-date" placeholder="Fecha fin" style="width: auto; min-width: 150px; padding-right: 30px;">
                        <i class="fas fa-calendar" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: var(--text-secondary); pointer-events: none;"></i>
                    </div>
                    <button onclick="updatePeriod()" class="btn btn-primary" style="min-width: 120px; width: auto;">
                        <i class="fas fa-refresh"></i>
                        Actualizar
                    </button>
                </div>
                
                <!-- Botones de período -->
                <div class="btn-group" style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <button id="btnCurrentWeek" onclick="setWeekPeriod('current')" class="btn <?php echo $isCurrentWeek ? 'btn-secondary' : 'btn-outline-secondary'; ?>">Esta Semana</button>
                    <button id="btnPreviousWeek" onclick="setWeekPeriod('previous')" class="btn <?php echo $isPreviousWeek ? 'btn-secondary' : 'btn-outline-secondary'; ?>">Semana Anterior</button>
                    <button id="btnCurrentMonth" onclick="setMonthPeriod('current')" class="btn <?php echo $isCurrentMonth ? 'btn-secondary' : 'btn-outline-secondary'; ?>">Este Mes</button>
                    <button id="btnPreviousMonth" onclick="setMonthPeriod('previous')" class="btn <?php echo $isPreviousMonth ? 'btn-secondary' : 'btn-outline-secondary'; ?>">Mes Anterior</button>
                </div>
            </div>
            
            <!-- Resumen Financiero -->
            <div class="financial-summary" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1px; border-top: 1px solid var(--border-color); background-color: var(--border-color);">
                <!-- Ingresos -->
                <div style="background-color: var(--background-primary); padding: 15px; text-align: center;">
                    <div style="font-size: 14px; font-weight: 500; color: var(--text-secondary); margin-bottom: 6px;">
                        <i class="fas fa-arrow-up" style="color: var(--success-color); margin-right: 5px;"></i>
                        Ingresos
                    </div>
                    <div style="font-size: 20px; font-weight: bold; color: var(--success-color);">
                        $<?php echo number_format($financialSummary['income'], 2); ?>
                    </div>
                </div>
                
                <!-- Gastos -->
                <div style="background-color: var(--background-primary); padding: 15px; text-align: center;">
                    <div style="font-size: 14px; font-weight: 500; color: var(--text-secondary); margin-bottom: 6px;">
                        <i class="fas fa-arrow-down" style="color: var(--danger-color); margin-right: 5px;"></i>
                        Gastos
                    </div>
                    <div style="font-size: 20px; font-weight: bold; color: var(--danger-color);">
                        $<?php echo number_format($financialSummary['expenses'], 2); ?>
                    </div>
                </div>
                
                <!-- Balance -->
                <div style="background-color: var(--background-primary); padding: 15px; text-align: center;">
                    <div style="font-size: 14px; font-weight: 500; color: var(--text-secondary); margin-bottom: 6px;">
                        <i class="fas fa-balance-scale" style="color: <?php echo $financialSummary['balance'] >= 0 ? 'var(--success-color)' : 'var(--danger-color)'; ?>; margin-right: 5px;"></i>
                        Balance
                    </div>
                    <div style="font-size: 20px; font-weight: bold; color: <?php echo $financialSummary['balance'] >= 0 ? 'var(--success-color)' : 'var(--danger-color)'; ?>;">
                        $<?php echo number_format($financialSummary['balance'], 2); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Nueva fila de 3 cards -->
        <div class="dashboard-cards" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 24px; margin-bottom: 30px;">
            <!-- Gráfico de Ingresos vs Gastos -->
            <div class="card dashboard-card" style="grid-column: 1;">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-chart-bar"></i>
                        Ingresos vs Gastos
                    </h3>
                </div>
                
                <div style="padding: 20px; height: calc(100% - 56px); display: flex; flex-direction: column;">
                    <canvas id="incomeExpenseChart" style="width: 100%; max-height: 300px;"></canvas>
                </div>
            </div>

            <!-- Ingresos Pendientes -->
            <div class="card dashboard-card" style="grid-column: 2;">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-hourglass-half" style="color: var(--warning-color);"></i>
                        Ingresos Pendientes
                    </h3>
                </div>
                
                <div style="padding: 10px 0 0 0; height: 100%; display: flex; flex-direction: column;">
                    <div style="flex: 1;">
                    <?php if (!empty($pendingIncomes)): ?>
                        <?php 
                        // Mostrar los 5 ingresos pendientes más recientes
                        $limitedPendingIncomes = array_slice($pendingIncomes, 0, 5);
                        foreach ($limitedPendingIncomes as $income): 
                            $isLastItem = ($limitedPendingIncomes[count($limitedPendingIncomes)-1] === $income);
                        ?>
                            <a href="incomes.php?action=edit&id=<?php echo urlencode($income['id']); ?>" class="activity-link" style="display: block; padding: 12px 16px; <?php echo $isLastItem ? '' : 'border-bottom: 1px solid var(--border-color);'; ?> text-decoration: none; color: inherit; transition: background-color 0.2s;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                    <div style="font-weight: 500;">
                                        <?php echo htmlspecialchars($income['invoice_number'] ?: 'Factura sin número'); ?>
                                    </div>
                                    <div style="font-weight: 700; color: var(--warning-color);">
                                        $<?php echo number_format($income['pending_amount'], 2); ?>
                                    </div>
                                </div>
                                
                                <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 4px; font-size: 12px; color: var(--text-secondary);">
                                    <span style="display: flex; align-items: center; gap: 4px;">
                                        <i class="fas fa-calendar-alt" style="font-size: 10px;"></i>
                                        <?php echo date('d/m/Y', strtotime($income['date'])); ?>
                                    </span>
                                    
                                    <?php if (!empty($income['team_name'])): ?>
                                    <span style="display: flex; align-items: center; gap: 4px;">
                                        <i class="fas fa-users" style="font-size: 10px;"></i>
                                        <?php echo htmlspecialchars($income['team_name']); ?>
                                    </span>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($income['contractors'])): ?>
                                    <span style="display: flex; align-items: center; gap: 4px;">
                                        <i class="fas fa-user-tie" style="font-size: 10px;"></i>
                                        <?php echo htmlspecialchars($income['contractors']); ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div style="position: relative; height: 4px; background-color: var(--background-secondary); border-radius: 2px; margin-top: 8px;">
                                    <?php 
                                    $percent = 0;
                                    if ($income['total_income'] > 0) {
                                        $percent = 100 * ($income['total_paid'] / $income['total_income']);
                                    }
                                    ?>
                                    <div style="position: absolute; top: 0; left: 0; height: 100%; width: <?php echo $percent; ?>%; background-color: var(--success-color); border-radius: 2px;"></div>
                                </div>
                                <div style="display: flex; justify-content: space-between; font-size: 10px; color: var(--text-muted); margin-top: 4px;">
                                    <span>Pagado: $<?php echo number_format($income['total_paid'], 2); ?></span>
                                    <span>Total: $<?php echo number_format($income['total_income'], 2); ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px 20px; color: var(--text-secondary);">
                            <i class="fas fa-check-circle" style="font-size: 32px; margin-bottom: 12px; color: var(--success-color);"></i>
                            <p>No hay ingresos pendientes</p>
                        </div>
                    <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Actividad Reciente -->
            <div class="card dashboard-card" style="grid-column: 3;">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-history"></i>
                        Actividad Reciente
                    </h3>
                </div>
                
                <div style="padding: 10px 0 0 0; height: 100%; display: flex; flex-direction: column;">
                    <div style="flex: 1;">
                        <?php 
                        // Mostrar las 5 últimas actividades
                        $limitedActivity = array_slice($recentActivity, 0, 5);
                        foreach ($limitedActivity as $activity): 
                            // Determinar el enlace y el tipo de actividad
                            $link = '#';
                            $linkClass = '';
                            if (isset($activity['income_id']) && $activity['income_id']) {
                                $link = "incomes.php?action=edit&id=" . urlencode($activity['income_id']);
                                $linkClass = 'activity-link';
                            } elseif (isset($activity['expense_id']) && $activity['expense_id']) {
                                $link = "expenses.php?action=edit&id=" . urlencode($activity['expense_id']);
                                $linkClass = 'activity-link';
                            }
                        ?>
                        <div style="<?php echo ($limitedActivity[count($limitedActivity)-1] === $activity) ? 'border-bottom: none;' : 'border-bottom: 1px solid var(--border-color);'; ?> transition: background-color 0.2s;">
                            <<?php echo $link != '#' ? 'a' : 'div'; ?> 
                            href="<?php echo $link; ?>" 
                            class="<?php echo $linkClass; ?>"
                            style="display: flex; align-items: flex-start; gap: 12px; padding: 12px 16px; text-decoration: none; color: inherit; 
                                <?php echo $link != '#' ? 'cursor: pointer;' : ''; ?>"
                            <?php if ($link != '#'): ?>
                            onmouseover="this.style.backgroundColor='var(--hover-color, #f8f9fa)'"
                            onmouseout="this.style.backgroundColor='transparent'"
                            <?php endif; ?>
                            >
                                <!-- Icono de acción -->
                                <div style="width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; 
                                            background: <?php echo (strpos($activity['type'], 'income') !== false) ? 'var(--success-color)' : 'var(--danger-color)'; ?>; font-size: 16px;">
                                    <?php if (isset($activity['action_icon'])): ?>
                                        <?php echo $activity['action_icon']; ?>
                                    <?php else: ?>
                                        <i class="fas fa-<?php echo (strpos($activity['type'], 'income') !== false) ? 'arrow-up' : 'arrow-down'; ?>" style="color: white; font-size: 12px;"></i>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Contenido de la actividad -->
                                <div style="flex: 1; min-width: 0;">
                                    <div style="font-weight: 500; font-size: 14px; margin-bottom: 6px; line-height: 1.4;">
                                        <?php echo htmlspecialchars($activity['description']); ?>
                                    </div>
                                    
                                    <div style="display: flex; flex-wrap: wrap; font-size: 11px; color: var(--text-muted);">
                                        <span style="display: flex; align-items: center; gap: 2px; margin-right: 8px;">
                                            <i class="fas fa-clock" style="font-size: 10px;"></i>
                                            <?php echo date('d/m/Y H:i', strtotime($activity['date'])); ?>
                                        </span>
                                    </div>
                                </div>
                            </<?php echo $link != '#' ? 'a' : 'div'; ?>>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($recentActivity)): ?>
                        <div style="text-align: center; padding: 40px 20px; color: var(--text-secondary);">
                            <i class="fas fa-info-circle" style="font-size: 32px; margin-bottom: 12px;"></i>
                            <p>No hay actividad reciente</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Grid de Cuentas Bancarias -->
        <div class="dashboard-content" style="display: grid; grid-template-columns: 1fr; gap: 24px; margin-top: 30px;">
            <!-- Balances de Cuentas -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-university"></i>
                        Balances de Cuentas
                    </h3>
                </div>
                
                <div style="padding: 10px 0;">
                    <?php foreach ($bankAccounts as $account): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid var(--border-color);">
                        <div>
                            <div style="font-weight: 500; margin-bottom: 4px;"><?php echo htmlspecialchars($account['name']); ?></div>
                            <div style="font-size: 12px; color: var(--text-secondary);">
                                <?php echo htmlspecialchars($account['bank_name']); ?> - <?php echo htmlspecialchars($account['account_number']); ?>
                            </div>
                            <div style="font-size: 11px; color: var(--text-muted);">
                                <?php echo ucfirst($account['account_type']); ?>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-size: 18px; font-weight: bold; color: <?php echo $account['balance'] >= 0 ? 'var(--success-color)' : 'var(--danger-color)'; ?>;">
                                $<?php echo number_format($account['balance'], 2); ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($bankAccounts)): ?>
                    <div style="text-align: center; padding: 40px; color: var(--text-secondary);">
                        <i class="fas fa-university" style="font-size: 32px; margin-bottom: 12px;"></i>
                        <p>No hay cuentas bancarias registradas</p>
                        <a href="bank_accounts.php" class="btn btn-primary" style="margin-top: 12px;">
                            <i class="fas fa-plus"></i>
                            Agregar Cuenta
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
</div>

<!-- Cargar Chart.js desde CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

<!-- Cargar Flatpickr -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>

<script>
// Verificar que flatpickr esté disponible antes de usarlo
if (typeof flatpickr !== 'undefined') {
    // Inicializar Flatpickr para los selectores de fecha
    flatpickr('.flatpickr-date', {
        dateFormat: 'Y-m-d',
        allowInput: true
    });
} else {
    console.error('Flatpickr no está disponible en dashboard');
}

// Variables globales
let incomeExpenseChart;

// Gráfico de Ingresos vs Gastos
const ctx = document.getElementById('incomeExpenseChart').getContext('2d');
incomeExpenseChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_map(function($d) {
            // Formatear fecha para mejor visualización
            return date('d/m', strtotime($d['date']));
        }, $dailyData)); ?>,
        datasets: [{
            label: 'Ingresos',
            data: <?php echo json_encode(array_map(function($d) {
                return floatval($d['income']);
            }, $dailyData)); ?>,
            backgroundColor: 'rgba(16, 185, 129, 0.8)',
            borderColor: 'rgb(16, 185, 129)',
            borderWidth: 1
        }, {
            label: 'Gastos',
            data: <?php echo json_encode(array_map(function($d) {
                return floatval($d['expenses']);
            }, $dailyData)); ?>,
            backgroundColor: 'rgba(239, 68, 68, 0.8)',
            borderColor: 'rgb(239, 68, 68)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '$' + value.toLocaleString();
                    }
                }
            },
            x: {
                categoryPercentage: 0.8,
                barPercentage: 0.9
            }
        },
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': $' + context.parsed.y.toLocaleString();
                    }
                }
            },
            legend: {
                position: 'top',
                labels: {
                    boxWidth: 12,
                    padding: 10,
                    font: {
                        size: 11
                    }
                }
            }
        },
        interaction: {
            intersect: false,
            mode: 'index'
        }
    }
});

// Funciones para navegación de fechas
function updatePeriod() {
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    
    if (!startDate || !endDate) {
        alert('Por favor selecciona ambas fechas');
        return;
    }
    
    if (new Date(startDate) > new Date(endDate)) {
        alert('La fecha de inicio no puede ser mayor que la fecha de fin');
        return;
    }
    
    // Usar AJAX en lugar de recargar la página
    updateDashboardData(startDate, endDate);
}

// Función para calcular fechas de manera exacta
function getDateRangeForPeriod(type) {
    const now = new Date();
    const result = { startDate: null, endDate: null };
    
    switch (type) {
        case 'current_week':
            // Calcular fechas para la semana actual (lunes a domingo)
            const day = now.getDay(); // 0 es domingo, 1 es lunes, etc.
            const diff = now.getDate() - day + (day === 0 ? -6 : 1); // Ajuste para que lunes sea el primer día
            
            result.startDate = new Date(now.getFullYear(), now.getMonth(), diff);
            result.endDate = new Date(result.startDate);
            result.endDate.setDate(result.startDate.getDate() + 6);
            break;
            
        case 'previous_week':
            // Calcular fechas para la semana anterior
            const dayPrev = now.getDay();
            const diffPrev = now.getDate() - dayPrev + (dayPrev === 0 ? -6 : 1) - 7; // Lunes de la semana anterior
            
            result.startDate = new Date(now.getFullYear(), now.getMonth(), diffPrev);
            result.endDate = new Date(result.startDate);
            result.endDate.setDate(result.startDate.getDate() + 6);
            break;
            
        case 'current_month':
            // Calcular fechas para el mes actual
            result.startDate = new Date(now.getFullYear(), now.getMonth(), 1);
            result.endDate = new Date(now.getFullYear(), now.getMonth() + 1, 0);
            break;
            
        case 'previous_month':
            // Calcular fechas para el mes anterior
            result.startDate = new Date(now.getFullYear(), now.getMonth() - 1, 1);
            result.endDate = new Date(now.getFullYear(), now.getMonth(), 0);
            break;
    }
    
    // Formatear fechas como strings YYYY-MM-DD
    return {
        startDate: result.startDate.toISOString().split('T')[0],
        endDate: result.endDate.toISOString().split('T')[0]
    };
}

function setWeekPeriod(type) {
    const dates = getDateRangeForPeriod(type === 'current' ? 'current_week' : 'previous_week');
    
    console.log(`Estableciendo período de semana ${type}: del ${dates.startDate} al ${dates.endDate}`);
    
    // Actualizar los campos de fecha
    document.getElementById('startDate').value = dates.startDate;
    document.getElementById('endDate').value = dates.endDate;
    
    // Usar AJAX en lugar de recargar la página
    updateDashboardData(dates.startDate, dates.endDate);
}

function setMonthPeriod(type) {
    const dates = getDateRangeForPeriod(type === 'current' ? 'current_month' : 'previous_month');
    
    console.log(`Estableciendo período de mes ${type}: del ${dates.startDate} al ${dates.endDate}`);
    
    // Actualizar los campos de fecha
    document.getElementById('startDate').value = dates.startDate;
    document.getElementById('endDate').value = dates.endDate;
    
    // Usar AJAX en lugar de recargar la página
    updateDashboardData(dates.startDate, dates.endDate);
}

// Función AJAX para actualizar datos del dashboard
function updateDashboardData(startDate, endDate) {
    // Mostrar indicador de carga
    const loadingStartTime = Date.now();
    showLoadingIndicator();
    
    // Realizar petición AJAX
    fetch(`dashboard_ajax.php?start_date=${startDate}&end_date=${endDate}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Actualizar solo las secciones que deben cambiar con el rango de fechas
                updateFinancialSummary(data.data.financial_summary);
                updateChart(data.data.daily_data);
                updatePendingIncomes(data.data.pending_incomes);
                // NO actualizar recentActivity - se mantiene estática
                updateActivePeriodButtons(data.data.active_period);
                
                // NO actualizar URL - mantener limpia para que siempre cargue semana actual
                // La URL se mantiene sin parámetros de fecha para navegación futura
                
                console.log('Dashboard actualizado exitosamente');
            } else {
                console.error('Error en la respuesta:', data.error);
                alert('Error al actualizar los datos: ' + (data.error || 'Error desconocido'));
            }
        })
        .catch(error => {
            console.error('Error en la petición AJAX:', error);
            alert('Error de conexión al actualizar los datos');
        })
        .finally(() => {
            // Asegurar un tiempo mínimo de carga para evitar el "salto" visual
            const loadingDuration = Date.now() - loadingStartTime;
            const minLoadingTime = 300; // 300ms mínimo
            
            if (loadingDuration < minLoadingTime) {
                setTimeout(() => {
                    hideLoadingIndicator();
                }, minLoadingTime - loadingDuration);
            } else {
                hideLoadingIndicator();
            }
        });
}

// Mostrar indicador de carga discreto
function showLoadingIndicator() {
    // Mostrar spinner en el botón de actualizar
    const updateButton = document.querySelector('button[onclick="updatePeriod()"]');
    if (updateButton) {
        const icon = updateButton.querySelector('i');
        if (icon) {
            icon.className = 'fas fa-spinner fa-spin';
        }
        updateButton.disabled = true;
        updateButton.style.opacity = '0.7';
    }
}

// Ocultar indicador de carga discreto
function hideLoadingIndicator() {
    // Restaurar botón de actualizar
    const updateButton = document.querySelector('button[onclick="updatePeriod()"]');
    if (updateButton) {
        const icon = updateButton.querySelector('i');
        if (icon) {
            icon.className = 'fas fa-refresh';
        }
        updateButton.disabled = false;
        updateButton.style.opacity = '1';
    }
}

// Actualizar resumen financiero
function updateFinancialSummary(data) {
    const incomeEl = document.querySelector('.financial-summary div:nth-child(1) div:nth-child(2)');
    const expensesEl = document.querySelector('.financial-summary div:nth-child(2) div:nth-child(2)');
    const balanceEl = document.querySelector('.financial-summary div:nth-child(3) div:nth-child(2)');
    
    if (incomeEl) incomeEl.textContent = '$' + Number(data.income).toLocaleString('es-ES', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    if (expensesEl) expensesEl.textContent = '$' + Number(data.expenses).toLocaleString('es-ES', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    if (balanceEl) {
        balanceEl.textContent = '$' + Number(data.balance).toLocaleString('es-ES', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        balanceEl.style.color = data.balance >= 0 ? 'var(--success-color)' : 'var(--danger-color)';
    }
}

// Actualizar gráfico
function updateChart(dailyData) {
    if (incomeExpenseChart) {
        const labels = dailyData.map(d => {
            const date = new Date(d.date);
            return String(date.getDate()).padStart(2, '0') + '/' + String(date.getMonth() + 1).padStart(2, '0');
        });
        
        incomeExpenseChart.data.labels = labels;
        incomeExpenseChart.data.datasets[0].data = dailyData.map(d => parseFloat(d.income));
        incomeExpenseChart.data.datasets[1].data = dailyData.map(d => parseFloat(d.expenses));
        incomeExpenseChart.update();
    }
}

// Actualizar ingresos pendientes
function updatePendingIncomes(pendingIncomes) {
    const container = document.querySelector('.card[style*="grid-column: 2"] div[style*="flex: 1"]');
    if (!container) return;
    
    if (pendingIncomes.length === 0) {
        container.innerHTML = `
            <div style="text-align: center; padding: 40px 20px; color: var(--text-secondary);">
                <i class="fas fa-check-circle" style="font-size: 32px; margin-bottom: 12px; color: var(--success-color);"></i>
                <p>No hay ingresos pendientes</p>
            </div>
        `;
        return;
    }
    
    let html = '';
    pendingIncomes.forEach((income, index) => {
        const isLastItem = (index === pendingIncomes.length - 1);
        const percent = income.total_income > 0 ? (100 * (income.total_paid / income.total_income)) : 0;
        
        html += `
            <a href="incomes.php?action=edit&id=${income.id}" class="activity-link" style="display: block; padding: 12px 16px; ${isLastItem ? '' : 'border-bottom: 1px solid var(--border-color);'} text-decoration: none; color: inherit; transition: background-color 0.2s;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                    <div style="font-weight: 500;">
                        ${income.invoice_number || 'Factura sin número'}
                    </div>
                    <div style="font-weight: 700; color: var(--warning-color);">
                        $${Number(income.pending_amount).toLocaleString('es-ES', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                    </div>
                </div>
                
                <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 4px; font-size: 12px; color: var(--text-secondary);">
                    <span style="display: flex; align-items: center; gap: 4px;">
                        <i class="fas fa-calendar-alt" style="font-size: 10px;"></i>
                        ${new Date(income.date).toLocaleDateString('es-ES')}
                    </span>
                    ${income.team_name ? `
                    <span style="display: flex; align-items: center; gap: 4px;">
                        <i class="fas fa-users" style="font-size: 10px;"></i>
                        ${income.team_name}
                    </span>
                    ` : ''}
                </div>
                
                <div style="position: relative; height: 4px; background-color: var(--background-secondary); border-radius: 2px; margin-top: 8px;">
                    <div style="position: absolute; top: 0; left: 0; height: 100%; width: ${percent}%; background-color: var(--success-color); border-radius: 2px;"></div>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 10px; color: var(--text-muted); margin-top: 4px;">
                    <span>Pagado: $${Number(income.total_paid).toLocaleString('es-ES', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                    <span>Total: $${Number(income.total_income).toLocaleString('es-ES', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                </div>
            </a>
        `;
    });
    
    container.innerHTML = html;
}

// Actualizar actividad reciente (SOLO para carga inicial - NO se usa en AJAX)
function updateRecentActivity(activities) {
    const container = document.querySelector('.card[style*="grid-column: 3"] div[style*="flex: 1"]');
    if (!container) return;
    
    if (activities.length === 0) {
        container.innerHTML = `
            <div style="text-align: center; padding: 40px 20px; color: var(--text-secondary);">
                <i class="fas fa-info-circle" style="font-size: 32px; margin-bottom: 12px;"></i>
                <p>No hay actividad reciente</p>
            </div>
        `;
        return;
    }
    
    let html = '';
    activities.forEach((activity, index) => {
        const isLastItem = (index === activities.length - 1);
        const link = activity.income_id ? `incomes.php?action=edit&id=${activity.income_id}` : 
                    (activity.expense_id ? `expenses.php?action=edit&id=${activity.expense_id}` : '#');
        const hasLink = link !== '#';
        
        html += `
            <div style="${isLastItem ? 'border-bottom: none;' : 'border-bottom: 1px solid var(--border-color);'} transition: background-color 0.2s;">
                <${hasLink ? 'a' : 'div'} 
                ${hasLink ? `href="${link}" class="activity-link"` : ''}
                style="display: flex; align-items: flex-start; gap: 12px; padding: 12px 16px; text-decoration: none; color: inherit; ${hasLink ? 'cursor: pointer;' : ''}"
                ${hasLink ? `onmouseover="this.style.backgroundColor='var(--hover-color, #f8f9fa)'" onmouseout="this.style.backgroundColor='transparent'"` : ''}
                >
                    <div style="width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; 
                                background: ${activity.type.includes('income') ? 'var(--success-color)' : 'var(--danger-color)'}; font-size: 16px;">
                        ${activity.action_icon || '<i class="fas fa-' + (activity.type.includes('income') ? 'arrow-up' : 'arrow-down') + '" style="color: white; font-size: 12px;"></i>'}
                    </div>
                    
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-weight: 500; font-size: 14px; margin-bottom: 6px; line-height: 1.4;">
                            ${activity.description}
                        </div>
                        
                        <div style="display: flex; flex-wrap: wrap; font-size: 11px; color: var(--text-muted);">
                            <span style="display: flex; align-items: center; gap: 2px; margin-right: 8px;">
                                <i class="fas fa-clock" style="font-size: 10px;"></i>
                                ${new Date(activity.date).toLocaleDateString('es-ES')} ${new Date(activity.date).toLocaleTimeString('es-ES', {hour: '2-digit', minute: '2-digit'})}
                            </span>
                        </div>
                    </div>
                </${hasLink ? 'a' : 'div'}>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

// Actualizar botones de período activo
function updateActivePeriodButtons(activePeriod) {
    const buttons = {
        'btnCurrentWeek': activePeriod.isCurrentWeek,
        'btnPreviousWeek': activePeriod.isPreviousWeek,
        'btnCurrentMonth': activePeriod.isCurrentMonth,
        'btnPreviousMonth': activePeriod.isPreviousMonth
    };
    
    Object.keys(buttons).forEach(buttonId => {
        const button = document.getElementById(buttonId);
        if (button) {
            if (buttons[buttonId]) {
                button.className = 'btn btn-secondary';
            } else {
                button.className = 'btn btn-outline-secondary';
            }
        }
    });
}


// Actualizar altura del gráfico basado en el contenedor
function adjustChartHeight() {
    const container = document.querySelector('.card[style*="grid-column: 1"]');
    const chartCanvas = document.getElementById('incomeExpenseChart');
    
    // En modo móvil (responsive), usar altura fija
    if (window.innerWidth <= 1200) {
        if (chartCanvas) {
            chartCanvas.style.height = '240px';
            incomeExpenseChart.resize();
        }
        return;
    }
    
    // En pantallas grandes, ajustar altura de forma más compacta
    if (container && chartCanvas) {
        const activityCard = document.querySelector('.card[style*="grid-column: 3"]');
        const pendingCard = document.querySelector('.card[style*="grid-column: 2"]');
        
        if (activityCard && pendingCard) {
            // Calcular altura promedio entre ambas cards, pero con un máximo
            const activityHeight = Math.min(activityCard.offsetHeight, 300);
            const pendingHeight = Math.min(pendingCard.offsetHeight, 300);
            const avgHeight = Math.min(Math.max(activityHeight, pendingHeight), 300);
            
            const cardHeaderHeight = container.querySelector('.card-header').offsetHeight;
            const chartPadding = 40; // 20px arriba y abajo
            
            chartCanvas.style.height = Math.min(avgHeight - cardHeaderHeight - chartPadding, 260) + 'px';
        } else {
            chartCanvas.style.height = '240px';
        }
        incomeExpenseChart.resize();
    }
}

// Llamar al ajuste después de que la página se cargue completamente
document.addEventListener('DOMContentLoaded', function() {
    // Código existente de eventos para fechas
    document.getElementById('startDate').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') updatePeriod();
    });

    document.getElementById('endDate').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') updatePeriod();
    });
    
    // Agregar soporte para teclas de acceso rápido
    document.addEventListener('keydown', function(e) {
        // Solo activar si no estamos escribiendo en un campo de entrada
        if (document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
            switch(e.key) {
                case '1':
                    e.preventDefault();
                    setWeekPeriod('current');
                    break;
                case '2':
                    e.preventDefault();
                    setWeekPeriod('previous');
                    break;
                case '3':
                    e.preventDefault();
                    setMonthPeriod('current');
                    break;
                case '4':
                    e.preventDefault();
                    setMonthPeriod('previous');
                    break;
                case 'r':
                case 'R':
                    e.preventDefault();
                    updatePeriod();
                    break;
            }
        }
    });
    
    // Ajustar altura del gráfico
    setTimeout(adjustChartHeight, 500);
    
    // Ajustar altura al cambiar el tamaño de la ventana
    window.addEventListener('resize', function() {
        setTimeout(adjustChartHeight, 300);
    });

    // Verificar visualmente los botones
    console.log("Estado actual de los botones:");
    console.log("Esta Semana:", document.getElementById('btnCurrentWeek').classList.contains('btn-secondary'));
    console.log("Semana Anterior:", document.getElementById('btnPreviousWeek').classList.contains('btn-secondary'));
    console.log("Este Mes:", document.getElementById('btnCurrentMonth').classList.contains('btn-secondary'));
    console.log("Mes Anterior:", document.getElementById('btnPreviousMonth').classList.contains('btn-secondary'));
});
</script>

<style>
/* Variables CSS adicionales para el dashboard */
:root {
    --primary-hover: #1d4ed8;
    --secondary-hover: #64748b;
    --hover-color: #f8f9fa;
}

/* Estilos para actividades recientes */
.activity-link {
    transition: all 0.2s ease;
    border-radius: 8px;
}

.activity-link:hover {
    background-color: var(--hover-color) !important;
    transform: translateX(2px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    border-radius: 8px;
}

.form-input {
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.2s ease;
    background: white;
}

.form-input:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    transition: all 0.2s ease;
    height: 38px;
}

.btn-primary {
    background: var(--primary-color, #2563eb);
    color: white;
}

.btn-primary:hover {
    background: var(--primary-hover, #1d4ed8);
}

.btn-secondary {
    background: var(--secondary-color, #6b7280);
    color: white;
}

.btn-secondary:hover {
    background: var(--secondary-hover, #4b5563);
}

.btn-outline-secondary {
    background: transparent;
    color: var(--secondary-color, #6b7280);
    border: 1px solid var(--secondary-color, #6b7280);
}

.btn-outline-secondary:hover {
    background: rgba(107, 114, 128, 0.1);
}

#incomeExpenseChart {
    max-height: 400px;
}

/* Estilos para Flatpickr */
.flatpickr-date {
    background: white !important;
    cursor: pointer;
}

.flatpickr-date:focus {
    border-color: var(--primary-color, #2563eb) !important;
}

.flatpickr-calendar {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    border: 1px solid var(--border-color, #e5e7eb);
    border-radius: 8px;
}

.flatpickr-day.selected {
    background: var(--primary-color, #2563eb);
    border-color: var(--primary-color, #2563eb);
}

.flatpickr-day:hover {
    background: var(--bg-primary, #f8fafc);
}

/* Responsive para dispositivos móviles */
@media (max-width: 768px) {
    .content-header h1 {
        font-size: 1.5rem;
    }
    
    .dashboard-nav {
        flex-direction: column;
        gap: 12px;
    }
    
    .dashboard-nav > div {
        flex-direction: column;
        align-items: stretch;
    }
    
    .btn-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
}

@media (max-width: 640px) {
    .dashboard-cards {
        grid-template-columns: 1fr !important;
    }
    
    .dashboard-content {
        grid-template-columns: 1fr !important;
    }
}

/* Estilos responsivos */
@media (max-width: 1200px) {
    .dashboard-cards {
        grid-template-columns: 1fr !important;
        gap: 20px !important;
    }
    
    .dashboard-cards .card {
        grid-column: 1 !important;
    }
}

@media (max-width: 768px) {
    .dashboard-cards {
        grid-template-columns: 1fr !important;
    }
    
    .dashboard-cards .card {
        grid-column: 1 !important;
    }
    
    .financial-summary {
        grid-template-columns: 1fr !important;
    }
    
    .dashboard-nav {
        flex-direction: column;
        align-items: stretch !important;
    }
    
    .dashboard-nav > div {
        width: 100%;
    }
    
    .dashboard-nav > div:first-child {
        flex-direction: column;
        align-items: stretch;
    }
    
    .dashboard-nav > div:first-child > div {
        margin-bottom: 10px;
    }
    
    .dashboard-nav button[onclick="updatePeriod()"] {
        margin-left: 0 !important;
        width: 100%;
        margin-top: 10px;
    }
    
    .btn-group {
        justify-content: space-between;
    }
    
    .btn-group .btn {
        flex: 1;
        min-width: calc(50% - 4px);
        margin-bottom: 8px;
    }
}

@media (max-width: 640px) {
    .dashboard-cards {
        grid-template-columns: 1fr !important;
    }
    
    .dashboard-content {
        grid-template-columns: 1fr !important;
    }
}

.btn-group .btn {
    min-width: 120px;
}

/* Estilos para indicador de carga discreto - solo en botón */

/* Animaciones deshabilitadas para actualizaciones AJAX 
   Solo se permiten en carga inicial de página */

/* Las teclas de acceso rápido siguen funcionando pero sin indicadores visuales */
</style>
