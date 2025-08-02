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

// Manejo inteligente de fechas: usar parámetros si están presentes, sino usar semana actual
$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;

// Solo usar semana actual si no hay parámetros específicos
if (!$startDate || !$endDate) {
    $startDate = date('Y-m-d', strtotime('monday this week'));
    $endDate = date('Y-m-d', strtotime('sunday this week'));
}

// Validar fechas
if (!$startDate || !$endDate) {
    $startDate = date('Y-m-d', strtotime('monday this week'));
    $endDate = date('Y-m-d', strtotime('sunday this week'));
}

// Depuración: registrar las fechas que se están usando
error_log("DASHBOARD - Fechas utilizadas: $startDate a $endDate");
error_log("DASHBOARD - Parámetros GET: " . json_encode($_GET));

// Función unificada para obtener datos financieros y de métodos de pago
function getUnifiedFinancialData($pdo, $startDate, $endDate) {
    $result = [
        'financial_summary' => ['income' => 0, 'expenses' => 0, 'fees' => 0, 'balance' => 0],
        'team_payment_methods' => []
    ];
    
    try {
        // Consulta unificada para ingresos, fees y métodos de pago por equipo
        $stmt = $pdo->prepare("
            SELECT 
                -- Datos para resumen financiero
                SUM(ip.amount) as total_income,
                SUM(ip.fee) as total_fees,
                
                -- Datos para métodos de pago por equipo
                t.id as team_id,
                t.name as team_name,
                pt.id as payment_type_id,
                pt.name as payment_method,
                COUNT(ip.id) as payment_count,
                SUM(ip.amount) as team_method_amount,
                SUM(ip.fee) as team_method_fee
            FROM income_payments ip
            INNER JOIN incomes i ON ip.income_id = i.id
            INNER JOIN teams t ON i.team_id = t.id
            INNER JOIN payment_types pt ON ip.payment_type_id = pt.id
            WHERE i.income_date BETWEEN ? AND ?
                AND pt.status = 'active'
            GROUP BY t.id, t.name, pt.id, pt.name
            ORDER BY t.name ASC, pt.name ASC
        ");
        $stmt->execute([$startDate, $endDate]);
        $incomeResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calcular totales para resumen financiero
        $totalIncome = 0;
        $totalFees = 0;
        $teamData = [];
        
        foreach ($incomeResults as $row) {
            // Acumular totales para resumen financiero
            $totalIncome += $row['team_method_amount'];
            $totalFees += $row['team_method_fee'];
            
            // Organizar datos por equipo para métodos de pago
            $teamName = $row['team_name'];
            if (!isset($teamData[$teamName])) {
                $teamData[$teamName] = [
                    'team_name' => $teamName,
                    'payment_methods' => [],
                    'total_amount' => 0,
                    'total_payments' => 0,
                    'total_fee' => 0
                ];
            }
            
            $paymentMethod = $row['payment_method'] ?: 'Sin especificar';
            $teamData[$teamName]['payment_methods'][$paymentMethod] = [
                'count' => $row['payment_count'],
                'amount' => $row['team_method_amount'],
                'fee' => $row['team_method_fee']
            ];
            $teamData[$teamName]['total_amount'] += $row['team_method_amount'];
            $teamData[$teamName]['total_payments'] += $row['payment_count'];
            $teamData[$teamName]['total_fee'] += $row['team_method_fee'];
        }
        
        $result['financial_summary']['income'] = $totalIncome;
        $result['financial_summary']['fees'] = $totalFees;
        $result['team_payment_methods'] = array_values($teamData);
        
        error_log("Consulta unificada - Ingresos: $totalIncome, Fees: $totalFees, Equipos: " . count($teamData));
        
    } catch (Exception $e) {
        error_log("Error en consulta unificada de ingresos: " . $e->getMessage());
    }
    
    // Consulta separada para gastos (no se puede unificar porque es tabla diferente)
    try {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(e.total_amount), 0) as total_expenses
            FROM expenses e 
            WHERE e.expense_date BETWEEN ? AND ?
        ");
        $stmt->execute([$startDate, $endDate]);
        $result['financial_summary']['expenses'] = $stmt->fetchColumn() ?: 0;
        
    } catch (Exception $e) {
        error_log("Error en consulta de gastos: " . $e->getMessage());
    }
    
    // Calcular balance
    $result['financial_summary']['balance'] = 
        $result['financial_summary']['income'] - 
        $result['financial_summary']['expenses'] - 
        $result['financial_summary']['fees'];
    
    return $result;
}

// Funciones wrapper para mantener compatibilidad
function getFinancialSummary($pdo, $startDate, $endDate) {
    static $cachedData = null;
    static $cachedDates = null;
    
    // Usar caché si las fechas son las mismas
    if ($cachedData === null || $cachedDates !== [$startDate, $endDate]) {
        $cachedData = getUnifiedFinancialData($pdo, $startDate, $endDate);
        $cachedDates = [$startDate, $endDate];
    }
    
    return $cachedData['financial_summary'];
}

function getTeamPaymentMethods($pdo, $startDate, $endDate) {
    static $cachedData = null;
    static $cachedDates = null;
    
    // Usar caché si las fechas son las mismas
    if ($cachedData === null || $cachedDates !== [$startDate, $endDate]) {
        $cachedData = getUnifiedFinancialData($pdo, $startDate, $endDate);
        $cachedDates = [$startDate, $endDate];
    }
    
    error_log("getTeamPaymentMethods - Consulta con fechas: $startDate a $endDate");
    error_log("getTeamPaymentMethods - Registros encontrados: " . count($cachedData['team_payment_methods']));
    if (!empty($cachedData['team_payment_methods'])) {
        error_log("getTeamPaymentMethods - Primer registro: " . json_encode($cachedData['team_payment_methods'][0]));
    }
    
    return $cachedData['team_payment_methods'];
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
        error_log("Error obteniendo contratistas para ingreso $incomeId: " . $e->getMessage());
        return '';
    }
}

function getRecentActivity($pdo, $limit = 5) {
    try {
        // Intentar obtener actividades del sistema de auditoría primero
        if (class_exists('AuditSystem')) {
            $audit = new AuditSystem();
            $auditActivities = $audit->getRecentActivities($limit * 2); // Obtener más para evaluar variedad
            
            // Verificar si hay suficiente variedad de actividades en auditoría
            $hasExpenses = false;
            $hasIncomes = false;
            foreach ($auditActivities as $activity) {
                if ($activity['entity_type'] == 'expense') $hasExpenses = true;
                if ($activity['entity_type'] == 'income') $hasIncomes = true;
            }
            
            // Solo usar auditoría si tenemos buena variedad Y suficientes actividades
            if (!empty($auditActivities) && count($auditActivities) >= $limit && $hasExpenses && $hasIncomes) {
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
                
                // Si encontramos actividades de auditoría variadas, las devolvemos
                if (!empty($formattedActivities)) {
                    return array_slice($formattedActivities, 0, $limit);
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
    
    // Obtener últimos ingresos (primero income_payments, luego incomes si no hay pagos)
    try {
        // Intentar obtener income_payments primero
        $stmt = $pdo->prepare("
            SELECT 'income' as type, 
                   COALESCE(ip.created_at, i.created_at) as date, 
                   CONCAT('Pago recibido $', ROUND(ip.amount, 2), ' - Factura: ', COALESCE(i.invoice_number, 'Sin número'), 
                          CASE WHEN t.name IS NOT NULL THEN CONCAT(' (', t.name, ')') ELSE '' END) as description,
                   ip.amount, 
                   COALESCE(ba.name, 'Sin cuenta') as account_name,
                   COALESCE(t.name, 'Sin equipo') as team_name,
                   '' as contractors,
                   'Sistema' as user,
                   '' as action_icon,
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
            LIMIT " . (int)($limit * 2) . "
        ");
        $stmt->execute();
        $incomes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Si no hay income_payments, obtener ingresos directamente de la tabla incomes
        if (empty($incomes)) {
            $stmt = $pdo->prepare("
                SELECT 'income' as type, 
                       i.created_at as date, 
                       CONCAT('Ingreso $', ROUND(i.total_amount, 2), ' - Factura: ', COALESCE(i.invoice_number, 'Sin número'), 
                              CASE WHEN t.name IS NOT NULL THEN CONCAT(' (', t.name, ')') ELSE '' END) as description,
                       i.total_amount as amount, 
                       COALESCE(t.name, 'Sin equipo') as account_name,
                       COALESCE(t.name, 'Sin equipo') as team_name,
                       '' as contractors,
                       'Sistema' as user,
                       '' as action_icon,
                       'Ingreso' as entity_name,
                       i.id as income_id,
                       i.invoice_number,
                       'incomes' as source_table
                FROM incomes i
                LEFT JOIN teams t ON i.team_id = t.id
                WHERE i.created_at IS NOT NULL
                ORDER BY i.created_at DESC
                LIMIT " . (int)($limit * 2) . "
            ");
            $stmt->execute();
            $incomes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // Obtener contratistas para cada ingreso
        foreach ($incomes as &$income) {
            $income['contractors'] = getIncomeContractors($pdo, $income['income_id']);
        }
        
        $activities = array_merge($activities, $incomes);
    } catch (Exception $e) {
        error_log("Error obteniendo ingresos para actividad reciente: " . $e->getMessage());
    }
    
    // Solo mostrar income_payments (pagos recibidos) para evitar duplicados con ingresos creados
    
    // Obtener últimos gastos (SIN filtro de fechas)
    try {
        $stmt = $pdo->prepare("
            SELECT 'expense' as type, 
                   e.created_at as date,
                   CONCAT(COALESCE(e.expense_number, 'Sin número'), ' $', ROUND(e.total_amount, 2), 
                          CASE WHEN v.name IS NOT NULL THEN CONCAT(' ', v.name) ELSE '' END) as description,
                   e.total_amount as amount, 
                   COALESCE(ba.name, 'Gasto directo') as account_name,
                   COALESCE(t.name, 'Sin equipo') as team_name,
                   COALESCE(v.name, 'Sin proveedor') as vendor_name,
                   'Sistema' as user,
                   '' as action_icon,
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
            LIMIT " . (int)($limit * 3) . "
        ");
        $stmt->execute(); // Obtener más registros para asegurar variedad
        $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $activities = array_merge($activities, $expenses);
    } catch (Exception $e) {
        error_log("Error obteniendo gastos para actividad reciente: " . $e->getMessage());
    }
    
    // NO incluir transacciones bancarias para evitar duplicados
    // Solo mostramos las actividades principales de ingresos y gastos
    
    // Ordenar por fecha y limitar a las más recientes
    usort($activities, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
    
    return array_slice($activities, 0, $limit);
}

function getPendingIncomes($pdo, $limit = 5) {
    try {
        // Usar la estructura correcta de la tabla incomes
        $limit = (int)$limit;
        $stmt = $pdo->prepare("
            SELECT 
                i.id, 
                i.invoice_number, 
                i.income_date as date, 
                i.total_amount as total_income, 
                COALESCE(SUM(ip.amount), 0) as total_paid,
                (i.total_amount - COALESCE(SUM(ip.amount), 0)) as pending_amount,
                t.name as team_name,
                i.status
            FROM 
                incomes i
            LEFT JOIN 
                income_payments ip ON i.id = ip.income_id
            LEFT JOIN 
                teams t ON i.team_id = t.id
            WHERE 
                i.status = 'pending'
            GROUP BY 
                i.id, i.invoice_number, i.income_date, i.total_amount, t.name, i.status
            HAVING 
                pending_amount > 0
            ORDER BY 
                i.income_date DESC
            LIMIT " . $limit . "
        ");
        $stmt->execute();
        $pendingIncomes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener contratistas para cada ingreso pendiente
        foreach ($pendingIncomes as &$income) {
            $income['contractors'] = getIncomeContractors($pdo, $income['id']);
        }
        
        return $pendingIncomes;
        
    } catch (Exception $e) {
        error_log("Error obteniendo ingresos pendientes: " . $e->getMessage());
        
        // Intento alternativo más simple si la consulta anterior falla
        try {
            $limit = (int)$limit;
            $stmt = $pdo->prepare("
                SELECT 
                    i.id, 
                    i.invoice_number, 
                    i.income_date as date, 
                    i.total_amount as total_income,
                    0 as total_paid,
                    i.total_amount as pending_amount,
                    t.name as team_name,
                    '' as contractors,
                    i.status
                FROM 
                    incomes i
                LEFT JOIN 
                    teams t ON i.team_id = t.id
                WHERE 
                    i.total_amount > 0 
                    AND (i.status = 'pending' OR i.status IS NULL)
                ORDER BY 
                    i.income_date DESC
                LIMIT " . $limit . "
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e2) {
            error_log("Error en consulta alternativa de ingresos pendientes: " . $e2->getMessage());
            return [];
        }
    }
}

// Obtener datos para el dashboard
try {
    $financialSummary = getFinancialSummary($pdo, $startDate, $endDate);
    $bankAccounts = getBankAccountBalances($pdo);
    $recentActivity = getRecentActivity($pdo);
    $pendingIncomes = getPendingIncomes($pdo, 5);
    $teamPaymentMethods = getTeamPaymentMethods($pdo, $startDate, $endDate);
    
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
    $teamPaymentMethods = [];
    $isCurrentWeek = false;
    $isPreviousWeek = false;
    $isCurrentMonth = false;
    $isPreviousMonth = false;
    error_log("Error en dashboard: " . $e->getMessage());
}
?>

<?php include 'includes/header.php'; ?>

<!-- Spinner Overlay para carga inicial -->
<div id="dashboardLoadingOverlay" style="
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.9);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    backdrop-filter: blur(2px);
">
    <div style="text-align: center;">
        <div style="
            width: 50px;
            height: 50px;
            border: 4px solid #e5e7eb;
            border-top: 4px solid var(--primary-color, #2563eb);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 16px;
        "></div>
        <p style="color: var(--text-secondary); font-size: 14px; margin: 0;">
            Cargando dashboard...
        </p>
    </div>
</div>

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
                    
                    <!-- Selector de período rápido -->
                    <div class="dropdown" style="position: relative;">
                        <button class="btn-outline dropdown-toggle" type="button" id="periodSelector" onclick="togglePeriodDropdown()" style="min-width: 140px; display: flex; align-items: center; justify-content: space-between; background: white; border: 1px solid var(--border-color); color: var(--text-primary); padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 14px; transition: all 0.2s ease;" onmouseover="this.style.borderColor='var(--primary-color)'; this.style.color='var(--primary-color)'" onmouseout="this.style.borderColor='var(--border-color)'; this.style.color='var(--text-primary)'">
                            <span id="periodSelectorText">
                                <?php 
                                if ($isCurrentWeek) echo 'Esta Semana';
                                elseif ($isPreviousWeek) echo 'Semana Anterior';
                                elseif ($isCurrentMonth) echo 'Este Mes';
                                elseif ($isPreviousMonth) echo 'Mes Anterior';
                                else echo 'Período Personalizado';
                                ?>
                            </span>
                            <i class="fas fa-chevron-down" style="margin-left: 8px; font-size: 12px; transition: transform 0.2s ease;"></i>
                        </button>
                        <div class="dropdown-menu" id="periodDropdown" style="
                            position: absolute;
                            top: 100%;
                            right: 0;
                            background: white;
                            border: 1px solid var(--border-color);
                            border-radius: 6px;
                            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                            min-width: 180px;
                            z-index: 1000;
                            display: none;
                            margin-top: 4px;
                        ">
                            <a class="dropdown-item" href="#" onclick="selectPeriod('current_week', 'Esta Semana')" style="display: block; padding: 10px 16px; text-decoration: none; color: var(--text-primary); border-bottom: 1px solid var(--border-light); transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='rgba(37, 99, 235, 0.1)'; this.style.color='var(--primary-color)'" onmouseout="this.style.backgroundColor='transparent'; this.style.color='var(--text-primary)'">
                                <i class="fas fa-calendar-week" style="margin-right: 8px; color: var(--primary-color);"></i>
                                Esta Semana
                            </a>
                            <a class="dropdown-item" href="#" onclick="selectPeriod('previous_week', 'Semana Anterior')" style="display: block; padding: 10px 16px; text-decoration: none; color: var(--text-primary); border-bottom: 1px solid var(--border-light); transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='rgba(37, 99, 235, 0.1)'; this.style.color='var(--primary-color)'" onmouseout="this.style.backgroundColor='transparent'; this.style.color='var(--text-primary)'">
                                <i class="fas fa-calendar-week" style="margin-right: 8px; color: var(--text-secondary);"></i>
                                Semana Anterior
                            </a>
                            <a class="dropdown-item" href="#" onclick="selectPeriod('current_month', 'Este Mes')" style="display: block; padding: 10px 16px; text-decoration: none; color: var(--text-primary); border-bottom: 1px solid var(--border-light); transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='rgba(37, 99, 235, 0.1)'; this.style.color='var(--primary-color)'" onmouseout="this.style.backgroundColor='transparent'; this.style.color='var(--text-primary)'">
                                <i class="fas fa-calendar-alt" style="margin-right: 8px; color: var(--primary-color);"></i>
                                Este Mes
                            </a>
                            <a class="dropdown-item" href="#" onclick="selectPeriod('previous_month', 'Mes Anterior')" style="display: block; padding: 10px 16px; text-decoration: none; color: var(--text-primary); transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='rgba(37, 99, 235, 0.1)'; this.style.color='var(--primary-color)'" onmouseout="this.style.backgroundColor='transparent'; this.style.color='var(--text-primary)'">
                                <i class="fas fa-calendar-alt" style="margin-right: 8px; color: var(--text-secondary);"></i>
                                Mes Anterior
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Resumen Financiero -->
        <div class="financial-summary" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px;">
            <!-- Card Ingresos -->
            <div class="card" style="background: white; border: 1px solid var(--border-color); border-radius: 8px; padding: 20px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05); transition: transform 0.2s ease, box-shadow 0.2s ease;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 4px rgba(0,0,0,0.05)'">
                <div style="display: flex; align-items: center; justify-content: center; width: 48px; height: 48px; background: rgba(34, 197, 94, 0.1); border-radius: 12px; margin: 0 auto 12px;">
                    <i class="fas fa-arrow-up" style="color: var(--success-color); font-size: 20px;"></i>
                </div>
                <div style="font-size: 13px; font-weight: 500; color: var(--text-secondary); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;">
                    Ingresos
                </div>
                <div style="font-size: 24px; font-weight: bold; color: var(--success-color); line-height: 1;">
                    $<?php echo number_format($financialSummary['income'], 2); ?>
                </div>
            </div>
            
            <!-- Card Gastos -->
            <div class="card" style="background: white; border: 1px solid var(--border-color); border-radius: 8px; padding: 20px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05); transition: transform 0.2s ease, box-shadow 0.2s ease;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 4px rgba(0,0,0,0.05)'">
                <div style="display: flex; align-items: center; justify-content: center; width: 48px; height: 48px; background: rgba(239, 68, 68, 0.1); border-radius: 12px; margin: 0 auto 12px;">
                    <i class="fas fa-arrow-down" style="color: var(--danger-color); font-size: 20px;"></i>
                </div>
                <div style="font-size: 13px; font-weight: 500; color: var(--text-secondary); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;">
                    Gastos
                </div>
                <div style="font-size: 24px; font-weight: bold; color: var(--danger-color); line-height: 1;">
                    $<?php echo number_format($financialSummary['expenses'], 2); ?>
                </div>
            </div>
            
            <!-- Card Fees -->
            <div class="card" style="background: white; border: 1px solid var(--border-color); border-radius: 8px; padding: 20px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05); transition: transform 0.2s ease, box-shadow 0.2s ease;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 4px rgba(0,0,0,0.05)'">
                <div style="display: flex; align-items: center; justify-content: center; width: 48px; height: 48px; background: rgba(245, 158, 11, 0.1); border-radius: 12px; margin: 0 auto 12px;">
                    <i class="fas fa-percentage" style="color: var(--warning-color); font-size: 20px;"></i>
                </div>
                <div style="font-size: 13px; font-weight: 500; color: var(--text-secondary); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;">
                    Fees
                </div>
                <div style="font-size: 24px; font-weight: bold; color: var(--warning-color); line-height: 1;">
                    $<?php echo number_format($financialSummary['fees'], 2); ?>
                </div>
            </div>
            
            <!-- Card Balance -->
            <div class="card" style="background: white; border: 1px solid var(--border-color); border-radius: 8px; padding: 20px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05); transition: transform 0.2s ease, box-shadow 0.2s ease;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 4px rgba(0,0,0,0.05)'">
                <div style="display: flex; align-items: center; justify-content: center; width: 48px; height: 48px; background: rgba(<?php echo $financialSummary['balance'] >= 0 ? '34, 197, 94' : '239, 68, 68'; ?>, 0.1); border-radius: 12px; margin: 0 auto 12px;">
                    <i class="fas fa-balance-scale" style="color: <?php echo $financialSummary['balance'] >= 0 ? 'var(--success-color)' : 'var(--danger-color)'; ?>; font-size: 20px;"></i>
                </div>
                <div style="font-size: 13px; font-weight: 500; color: var(--text-secondary); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;">
                    Balance
                </div>
                <div style="font-size: 24px; font-weight: bold; color: <?php echo $financialSummary['balance'] >= 0 ? 'var(--success-color)' : 'var(--danger-color)'; ?>; line-height: 1;">
                    $<?php echo number_format($financialSummary['balance'], 2); ?>
                </div>
            </div>
        </div>

        <!-- Nueva fila de 3 cards -->
        <div class="dashboard-cards" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 24px; margin-bottom: 30px;">
            <!-- Balances de Cuentas (movido desde abajo) -->
            <div class="card dashboard-card" style="grid-column: 1;">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-university"></i>
                        Balances de Cuentas
                    </h3>
                </div>
                
                <div style="padding: 10px 0 0 0; height: 100%; display: flex; flex-direction: column;">
                    <div style="flex: 1; overflow-y: auto; max-height: 400px;">
                        <?php foreach ($bankAccounts as $account): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 16px; border-bottom: 1px solid var(--border-color);">
                            <div style="flex: 1; min-width: 0;">
                                <div style="font-weight: 500; margin-bottom: 3px; font-size: 14px;"><?php echo htmlspecialchars($account['name']); ?></div>
                                <div style="display: flex; align-items: center; gap: 8px; font-size: 10px; color: var(--text-secondary);">
                                    <span><?php echo htmlspecialchars($account['bank_name']); ?></span>
                                    <span style="color: var(--text-muted);">•</span>
                                    <span style="color: var(--text-muted);"><?php echo ucfirst($account['account_type']); ?></span>
                                </div>
                            </div>
                            <div style="text-align: right; margin-left: 8px;">
                                <div style="font-size: 16px; font-weight: bold; color: <?php echo $account['balance'] >= 0 ? 'var(--success-color)' : 'var(--danger-color)'; ?>;">
                                    $<?php echo number_format($account['balance'], 2); ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($bankAccounts)): ?>
                        <div style="text-align: center; padding: 40px 20px; color: var(--text-secondary);">
                            <i class="fas fa-university" style="font-size: 32px; margin-bottom: 12px;"></i>
                            <p>No hay cuentas bancarias</p>
                            <a href="bank_accounts.php" class="btn btn-primary" style="margin-top: 12px; font-size: 12px; padding: 6px 12px;">
                                <i class="fas fa-plus"></i>
                                Agregar
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
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
                    <?php 
                    // Filtrar explícitamente solo los ingresos pendientes
                    $pendingOnly = array_filter($pendingIncomes, function($income) {
                        return isset($income['pending_amount']) && $income['pending_amount'] > 0;
                    });
                    $limitedPendingIncomes = array_slice($pendingOnly, 0, 5);
                    foreach ($limitedPendingIncomes as $income): 
                        $isLastItem = ($limitedPendingIncomes[count($limitedPendingIncomes)-1] === $income);
                    ?>
                        <div style="<?php echo ($limitedPendingIncomes[count($limitedPendingIncomes)-1] === $income) ? 'border-bottom: none;' : 'border-bottom: 1px solid var(--border-color);'; ?> transition: background-color 0.2s;">
                            <a href="incomes.php?id=<?php echo urlencode($income['id']); ?>&view=1" 
                               class="activity-link"
                               style="display: flex; align-items: flex-start; gap: 12px; padding: 12px 16px; text-decoration: none; color: inherit; cursor: pointer;"
                               onmouseover="this.style.backgroundColor='var(--hover-color, #f8f9fa)'"
                               onmouseout="this.style.backgroundColor='transparent'">
                                
                                <!-- Contenido -->
                                <div style="flex: 1; min-width: 0;">
                                    <div style="font-weight: 500; font-size: 14px; margin-bottom: 4px; line-height: 1.4;">
                                        Factura <?php echo htmlspecialchars($income['invoice_number'] ?: 'Sin número'); ?> - $<?php echo number_format($income['pending_amount'], 2); ?> pendiente
                                    </div>
                                    
                                    <div style="display: flex; flex-wrap: wrap; font-size: 11px; color: var(--text-muted);">
                                        <span style="display: flex; align-items: center; gap: 2px; margin-right: 12px;">
                                            <i class="fas fa-calendar-alt" style="font-size: 10px;"></i>
                                            <?php echo date('d/m/Y', strtotime($income['date'])); ?>
                                        </span>
                                        <?php if (!empty($income['team_name'])): ?>
                                        <span style="display: flex; align-items: center; gap: 2px;">
                                            <i class="fas fa-users" style="font-size: 10px;"></i>
                                            <?php echo htmlspecialchars($income['team_name']); ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($limitedPendingIncomes)): ?>
                        <div style="text-align: center; padding: 40px 20px; color: var(--text-secondary);">
                            <i class="fas fa-check-circle" style="font-size: 32px; margin-bottom: 12px; color: var(--success-color);"></i>
                            <p>No hay ingresos pendientes</p>
                            <small style="color: var(--text-muted); display: block; margin-top: 8px;">
                                <?php if (empty($pendingIncomes)): ?>
                                    Total de ingresos obtenidos: 0
                                <?php else: ?>
                                    Filtrados: <?php echo count($pendingOnly); ?> de <?php echo count($pendingIncomes); ?>
                                <?php endif; ?>
                            </small>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (count($pendingOnly) > 5): ?>
                        <div style="text-align: center; margin-top: 10px;">
                            <a href="incomes.php?status=pending" class="btn btn-outline-secondary" style="width: 100%; max-width: 200px; display: inline-block;">
                                Ver más
                            </a>
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
                                $link = "incomes.php?id=" . urlencode($activity['income_id']) . "&view=1";
                                $linkClass = 'activity-link';
                            } elseif (isset($activity['expense_id']) && $activity['expense_id']) {
                                $link = "expenses.php?id=" . urlencode($activity['expense_id']) . "&view=1";
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
                                <!-- Contenido de la actividad -->
                                <div style="flex: 1; min-width: 0;">
                                    <div style="font-weight: 500; font-size: 14px; margin-bottom: 4px; line-height: 1.4;">
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

        <!-- Métodos de Pago por Equipo -->
        <div class="dashboard-content" style="display: grid; grid-template-columns: 1fr; gap: 24px; margin-top: 30px;">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-credit-card"></i>
                        Métodos de Pago por Equipo
                    </h3>
                    <p style="font-size: 12px; color: var(--text-secondary); margin: 0; margin-top: 4px;">
                        Periodo: <?php echo date('d/m/Y', strtotime($startDate)); ?> - <?php echo date('d/m/Y', strtotime($endDate)); ?>
                    </p>
                </div>
                
                <div style="padding: 20px;">
                    <?php if (!empty($teamPaymentMethods)): ?>
                        <div class="team-payment-table" style="overflow-x: auto;">
                            <!-- Crear tabla horizontal de equipos vs métodos de pago -->
                            <?php
                            // Obtener todos los métodos de pago únicos
                            $allPaymentMethods = [];
                            foreach ($teamPaymentMethods as $team) {
                                foreach ($team['payment_methods'] as $method => $data) {
                                    if (!in_array($method, $allPaymentMethods)) {
                                        $allPaymentMethods[] = $method;
                                    }
                                }
                            }
                            sort($allPaymentMethods);
                            ?>
                            
                            <table style="width: 100%; border-collapse: collapse; font-size: 13px; border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                <thead>
                                    <tr style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
                                        <th style="padding: 16px 12px; text-align: left; font-weight: 600; color: var(--text-primary); border-right: 1px solid var(--border-color);">
                                            <i class="fas fa-users" style="color: var(--primary-color); margin-right: 6px;"></i>
                                            Equipo
                                        </th>
                                        <?php foreach ($allPaymentMethods as $method): ?>
                                            <th style="padding: 16px 8px; text-align: center; font-weight: 600; color: var(--text-primary); border-right: 1px solid var(--border-color); min-width: 100px;">
                                                <div style="display: flex; flex-direction: column; align-items: center; gap: 4px;">
                                                    <i class="fas fa-<?php echo $method === 'Efectivo' ? 'money-bill-wave' : ($method === 'Transferencia Bancaria' ? 'university' : ($method === 'ATH M' ? 'mobile-alt' : ($method === 'ATH B' ? 'credit-card' : 'credit-card'))); ?>" 
                                                       style="color: var(--primary-color); font-size: 12px;"></i>
                                                    <span style="font-size: 11px; font-weight: 600;"><?php echo htmlspecialchars($method); ?></span>
                                                </div>
                                            </th>
                                        <?php endforeach; ?>
                                        <th style="padding: 16px 8px; text-align: center; font-weight: 600; color: var(--warning-color); border-right: 1px solid var(--border-color); min-width: 90px; background: rgba(245, 158, 11, 0.1);">
                                            <div style="display: flex; flex-direction: column; align-items: center; gap: 4px;">
                                                <i class="fas fa-percentage" style="color: var(--warning-color); font-size: 12px;"></i>
                                                <span style="font-size: 11px; font-weight: 600;">Fee</span>
                                            </div>
                                        </th>
                                        <th style="padding: 16px 8px; text-align: center; font-weight: 600; color: var(--success-color); border-right: 1px solid var(--border-color); min-width: 100px; background: rgba(34, 197, 94, 0.1);">
                                            <div style="display: flex; flex-direction: column; align-items: center; gap: 4px;">
                                                <i class="fas fa-plus-circle" style="color: var(--success-color); font-size: 12px;"></i>
                                                <span style="font-size: 11px; font-weight: 600;">Total Bruto</span>
                                            </div>
                                        </th>
                                        <th style="padding: 16px 8px; text-align: center; font-weight: 600; color: var(--info-color); border-right: 1px solid var(--border-color); min-width: 100px; background: rgba(59, 130, 246, 0.1);">
                                            <div style="display: flex; flex-direction: column; align-items: center; gap: 4px;">
                                                <i class="fas fa-minus-circle" style="color: var(--info-color); font-size: 12px;"></i>
                                                <span style="font-size: 11px; font-weight: 600;">Total Neto</span>
                                            </div>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($teamPaymentMethods as $team): ?>
                                        <tr style="border-bottom: 1px solid var(--border-color); transition: all 0.2s ease;" 
                                            onmouseover="this.style.backgroundColor='#f8f9fa'" 
                                            onmouseout="this.style.backgroundColor='transparent'">
                                            
                                            <!-- Nombre del equipo -->
                                            <td style="padding: 14px 12px; font-weight: 500; border-right: 1px solid var(--border-color); background: rgba(37, 99, 235, 0.02);">
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <div style="width: 6px; height: 6px; background: var(--primary-color); border-radius: 50%;"></div>
                                                    <span style="color: var(--text-primary); font-size: 13px;"><?php echo htmlspecialchars($team['team_name']); ?></span>
                                                </div>
                                            </td>
                                            
                                            <!-- Métodos de pago -->
                                            <?php foreach ($allPaymentMethods as $method): ?>
                                                <td style="padding: 10px 8px; text-align: center; border-right: 1px solid var(--border-color);">
                                                    <?php if (isset($team['payment_methods'][$method])): ?>
                                                        <span style="font-size: 13px; font-weight: 600; color: var(--success-color);">
                                                            $<?php echo number_format($team['payment_methods'][$method]['amount'], 0); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span style="color: var(--text-muted); font-size: 12px;">—</span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                            
                                            <!-- Columna de Fee (real de la BD) -->
                                            <td style="padding: 10px 8px; text-align: center; border-right: 1px solid var(--border-color); background: rgba(245, 158, 11, 0.05);">
                                                <?php 
                                                $teamFee = $team['total_fee'] ?? 0;
                                                ?>
                                                <?php if ($teamFee > 0): ?>
                                                    <span style="font-size: 13px; font-weight: 600; color: var(--warning-color);">
                                                        $<?php echo number_format($teamFee, 2); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color: var(--text-muted); font-size: 12px;">—</span>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <!-- Total Bruto del equipo -->
                                            <td style="padding: 10px 8px; text-align: center; border-right: 1px solid var(--border-color); background: rgba(34, 197, 94, 0.05);">
                                                <?php 
                                                // El total bruto es la suma de todos los métodos de pago por equipo
                                                $teamTotalBruto = $team['total_amount'];
                                                ?>
                                                <span style="font-size: 13px; font-weight: 600; color: var(--success-color);">
                                                    $<?php echo number_format($teamTotalBruto, 2); ?>
                                                </span>
                                            </td>
                                            
                                            <!-- Total Neto del equipo (Total Bruto - Fee) -->
                                            <td style="padding: 10px 8px; text-align: center; border-right: 1px solid var(--border-color); background: rgba(59, 130, 246, 0.05);">
                                                <?php 
                                                $teamTotalNeto = $teamTotalBruto - $teamFee;
                                                ?>
                                                <span style="font-size: 13px; font-weight: 600; color: var(--info-color);">
                                                    $<?php echo number_format($teamTotalNeto, 2); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    
                                    <!-- Fila de totales -->
                                    <tr style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-top: 2px solid var(--border-color); font-weight: bold;">
                                        <td style="padding: 16px 12px; font-weight: bold; color: var(--text-primary); border-right: 1px solid var(--border-color);">
                                            <i class="fas fa-sigma" style="margin-right: 6px; color: var(--primary-color);"></i>
                                            TOTALES
                                        </td>
                                        <?php foreach ($allPaymentMethods as $method): ?>
                                            <?php
                                            $methodTotal = 0;
                                            $methodCount = 0;
                                            foreach ($teamPaymentMethods as $team) {
                                                if (isset($team['payment_methods'][$method])) {
                                                    $methodTotal += $team['payment_methods'][$method]['amount'];
                                                    $methodCount += $team['payment_methods'][$method]['count'];
                                                }
                                            }
                                            ?>
                                            <td style="padding: 10px 8px; text-align: center; border-right: 1px solid var(--border-color);">
                                                <?php if ($methodTotal > 0): ?>
                                                    <span style="font-size: 13px; font-weight: bold; color: var(--success-color);">
                                                        $<?php echo number_format($methodTotal, 0); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color: var(--text-muted); font-size: 12px;">—</span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                        
                                        <!-- Total de Fees -->
                                        <td style="padding: 10px 8px; text-align: center; border-right: 1px solid var(--border-color); background: rgba(245, 158, 11, 0.1);">
                                            <?php 
                                            $totalFee = array_sum(array_column($teamPaymentMethods, 'total_fee'));
                                            ?>
                                            <?php if ($totalFee > 0): ?>
                                                <span style="font-size: 13px; font-weight: bold; color: var(--warning-color);">
                                                    $<?php echo number_format($totalFee, 2); ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted); font-size: 12px;">—</span>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <!-- Total Bruto General -->
                                        <td style="padding: 10px 8px; text-align: center; border-right: 1px solid var(--border-color); background: rgba(34, 197, 94, 0.1);">
                                            <?php 
                                            // El total bruto general es la suma de todos los total_amount de todos los equipos
                                            $totalGeneralBruto = array_sum(array_column($teamPaymentMethods, 'total_amount'));
                                            ?>
                                            <span style="font-size: 13px; font-weight: bold; color: var(--success-color);">
                                                $<?php echo number_format($totalGeneralBruto, 2); ?>
                                            </span>
                                        </td>
                                        
                                        <!-- Total Neto General -->
                                        <td style="padding: 10px 8px; text-align: center; border-right: 1px solid var(--border-color); background: rgba(59, 130, 246, 0.1);">
                                            <?php 
                                            $totalGeneralNeto = $totalGeneralBruto - $totalFee;
                                            ?>
                                            <span style="font-size: 13px; font-weight: bold; color: var(--info-color);">
                                                $<?php echo number_format($totalGeneralNeto, 2); ?>
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                    <?php else: ?>
                        <div style="text-align: center; padding: 60px 20px; color: var(--text-secondary);">
                            <i class="fas fa-chart-bar" style="font-size: 48px; margin-bottom: 16px; color: var(--text-muted);"></i>
                            <h4 style="margin: 0 0 8px 0; color: var(--text-primary);">No hay datos disponibles</h4>
                            <p style="margin: 0; font-size: 14px;">No se encontraron pagos de equipos en el rango de fechas seleccionado.</p>
                            <p style="margin: 8px 0 0 0; font-size: 12px; color: var(--text-muted);">
                                Periodo: <?php echo date('d/m/Y', strtotime($startDate)); ?> - <?php echo date('d/m/Y', strtotime($endDate)); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </main>
    
    <?php include 'includes/footer.php'; ?>
</div>

<!-- Cargar Flatpickr -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>

<script>
// Verificar que flatpickr esté disponible antes de usarlo
if (typeof flatpickr !== 'undefined') {
    // Configuración para el date picker de fecha de inicio
    const startDateConfig = {
        dateFormat: 'Y-m-d',
        allowInput: true,
        onChange: function(selectedDates, dateStr, instance) {
            // Solo actualizar el mínimo del date picker de fecha final
            // NO actualizar el dashboard automáticamente
            if (dateStr && window.endDatePicker) {
                window.endDatePicker.set('minDate', dateStr);
                
                // Si la fecha final es anterior a la nueva fecha de inicio, limpiarla
                const endDate = document.getElementById('endDate').value;
                if (endDate && endDate < dateStr) {
                    window.endDatePicker.clear();
                }
            }
        }
    };
    
    // Configuración para el date picker de fecha final
    const endDateConfig = {
        dateFormat: 'Y-m-d',
        allowInput: true
        // NO incluir onChange para evitar actualización automática
    };
    
    // Agregar localización española si está disponible
    if (flatpickr.l10ns && flatpickr.l10ns.es) {
        startDateConfig.locale = 'es';
        endDateConfig.locale = 'es';
    }
    
    // Inicializar los date pickers por separado
    window.startDatePicker = flatpickr('#startDate', startDateConfig);
    window.endDatePicker = flatpickr('#endDate', endDateConfig);
    
    // Configurar fecha mínima inicial si ya hay una fecha de inicio seleccionada
    const initialStartDate = document.getElementById('startDate').value;
    if (initialStartDate && window.endDatePicker) {
        window.endDatePicker.set('minDate', initialStartDate);
    }
} else {
    console.error('Flatpickr no está disponible en dashboard');
}

// Variables globales

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
    
    console.log(`Actualizando manualmente con botón: ${startDate} - ${endDate}`);
    
    // Usar AJAX en lugar de recargar la página
    updateDashboardData(startDate, endDate);
}

// Función para formatear fecha desde string sin problemas de timezone
function formatDateFromString(dateString) {
    if (!dateString) return '';
    
    // Parsear la fecha sin crear un objeto Date que pueda tener problemas de timezone
    const parts = dateString.split('-');
    if (parts.length !== 3) return dateString;
    
    const year = parseInt(parts[0]);
    const month = parseInt(parts[1]);
    const day = parseInt(parts[2]);
    
    // Formatear como DD/MM/YYYY
    return `${day.toString().padStart(2, '0')}/${month.toString().padStart(2, '0')}/${year}`;
}

// Función para calcular fechas de manera exacta
function getDateRangeForPeriod(type) {
    const now = new Date();
    const result = { startDate: null, endDate: null };
    
    switch (type) {
        case 'current_week':
            // Calcular fechas para la semana actual (lunes a domingo)
            const day = now.getDay(); // 0 es domingo, 1 es lunes, etc.
            const daysFromMonday = (day === 0) ? 6 : day - 1; // Convertir domingo (0) a 6, y el resto restar 1
            
            result.startDate = new Date(now);
            result.startDate.setDate(now.getDate() - daysFromMonday);
            result.startDate.setHours(0, 0, 0, 0);
            
            result.endDate = new Date(result.startDate);
            result.endDate.setDate(result.startDate.getDate() + 6);
            result.endDate.setHours(23, 59, 59, 999);
            break;
            
        case 'previous_week':
            // Calcular fechas para la semana anterior
            const dayPrev = now.getDay();
            const daysFromMondayPrev = (dayPrev === 0) ? 6 : dayPrev - 1;
            
            result.startDate = new Date(now);
            result.startDate.setDate(now.getDate() - daysFromMondayPrev - 7); // 7 días antes del lunes de esta semana
            result.startDate.setHours(0, 0, 0, 0);
            
            result.endDate = new Date(result.startDate);
            result.endDate.setDate(result.startDate.getDate() + 6);
            result.endDate.setHours(23, 59, 59, 999);
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

// Función para alternar dropdown de período
function togglePeriodDropdown() {
    const dropdown = document.getElementById('periodDropdown');
    const chevron = document.querySelector('#periodSelector i.fa-chevron-down');
    
    if (dropdown.style.display === 'none' || dropdown.style.display === '') {
        dropdown.style.display = 'block';
        chevron.style.transform = 'rotate(180deg)';
        
        // Cerrar dropdown al hacer clic fuera
        setTimeout(() => {
            document.addEventListener('click', closeDropdownOutside);
        }, 10);
    } else {
        dropdown.style.display = 'none';
        chevron.style.transform = 'rotate(0deg)';
        document.removeEventListener('click', closeDropdownOutside);
    }
}

// Función para cerrar dropdown al hacer clic fuera
function closeDropdownOutside(event) {
    const dropdown = document.getElementById('periodDropdown');
    const button = document.getElementById('periodSelector');
    
    if (!dropdown.contains(event.target) && !button.contains(event.target)) {
        dropdown.style.display = 'none';
        document.querySelector('#periodSelector i.fa-chevron-down').style.transform = 'rotate(0deg)';
        document.removeEventListener('click', closeDropdownOutside);
    }
}

// Función para seleccionar período del dropdown
function selectPeriod(type, displayText) {
    const dropdown = document.getElementById('periodDropdown');
    const chevron = document.querySelector('#periodSelector i.fa-chevron-down');
    const selectorText = document.getElementById('periodSelectorText');
    
    // Cerrar dropdown
    dropdown.style.display = 'none';
    chevron.style.transform = 'rotate(0deg)';
    document.removeEventListener('click', closeDropdownOutside);
    
    // Actualizar texto del selector
    selectorText.textContent = displayText;
    
    // Aplicar el período correspondiente
    const dates = getDateRangeForPeriod(type);
    
    console.log(`Seleccionando ${displayText}: del ${dates.startDate} al ${dates.endDate}`);
    
    // Actualizar los campos de fecha
    document.getElementById('startDate').value = dates.startDate;
    document.getElementById('endDate').value = dates.endDate;
    
    // Actualizar las instancias de flatpickr si existen
    if (window.startDatePicker) {
        window.startDatePicker.setDate(dates.startDate, false);
    }
    if (window.endDatePicker) {
        window.endDatePicker.set('minDate', dates.startDate);
        window.endDatePicker.setDate(dates.endDate, false);
    }
    
    // Usar AJAX inmediatamente
    updateDashboardData(dates.startDate, dates.endDate);
}

function setWeekPeriod(type) {
    // Función mantenida para compatibilidad, pero ahora usa selectPeriod
    const periodType = type === 'current' ? 'current_week' : 'previous_week';
    const displayText = type === 'current' ? 'Esta Semana' : 'Semana Anterior';
    selectPeriod(periodType, displayText);
}

function setMonthPeriod(type) {
    // Función mantenida para compatibilidad, pero ahora usa selectPeriod
    const periodType = type === 'current' ? 'current_month' : 'previous_month';
    const displayText = type === 'current' ? 'Este Mes' : 'Mes Anterior';
    selectPeriod(periodType, displayText);
}

// Función AJAX para actualizar datos del dashboard
function updateDashboardData(startDate, endDate) {
    // Mostrar indicador de carga
    const loadingStartTime = Date.now();
    showLoadingIndicator();
    
    // Actualizar texto del selector de período
    updatePeriodSelectorText(startDate, endDate);
    
    // Realizar petición AJAX
    fetch(`dashboard_ajax.php?start_date=${startDate}&end_date=${endDate}`)
        .then(response => response.json())
        .then(data => {
            console.log('Datos recibidos del AJAX:', data);
            if (data.success) {
                // Actualizar solo las secciones que deben cambiar con el rango de fechas
                updateFinancialSummary(data.data.financial_summary);
                updatePendingIncomes(data.data.pending_incomes);
                console.log('Actualizando métodos de pago con:', data.data.team_payment_methods);
                updateTeamPaymentMethods(data.data.team_payment_methods, data.data.date_range);
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

// Función para actualizar el texto del selector de período
function updatePeriodSelectorText(startDate, endDate) {
    const selectorText = document.getElementById('periodSelectorText');
    if (!selectorText) return;
    
    // Obtener las fechas de cada período para comparar
    const currentWeek = getDateRangeForPeriod('current_week');
    const previousWeek = getDateRangeForPeriod('previous_week');
    const currentMonth = getDateRangeForPeriod('current_month');
    const previousMonth = getDateRangeForPeriod('previous_month');
    
    // Determinar qué período coincide
    if (startDate === currentWeek.startDate && endDate === currentWeek.endDate) {
        selectorText.textContent = 'Esta Semana';
    } else if (startDate === previousWeek.startDate && endDate === previousWeek.endDate) {
        selectorText.textContent = 'Semana Anterior';
    } else if (startDate === currentMonth.startDate && endDate === currentMonth.endDate) {
        selectorText.textContent = 'Este Mes';
    } else if (startDate === previousMonth.startDate && endDate === previousMonth.endDate) {
        selectorText.textContent = 'Mes Anterior';
    } else {
        selectorText.textContent = 'Período Personalizado';
    }
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
    // Selector más específico para las nuevas cards del resumen financiero
    const incomeEl = document.querySelector('.financial-summary .card:nth-child(1) div:last-child');
    const expensesEl = document.querySelector('.financial-summary .card:nth-child(2) div:last-child');
    const feesEl = document.querySelector('.financial-summary .card:nth-child(3) div:last-child');
    const balanceEl = document.querySelector('.financial-summary .card:nth-child(4) div:last-child');
    const balanceIcon = document.querySelector('.financial-summary .card:nth-child(4) i');
    const balanceIconBg = document.querySelector('.financial-summary .card:nth-child(4) div:first-child');
    
    if (incomeEl) incomeEl.textContent = '$' + Number(data.income).toLocaleString('es-ES', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    if (expensesEl) expensesEl.textContent = '$' + Number(data.expenses).toLocaleString('es-ES', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    if (feesEl) feesEl.textContent = '$' + Number(data.fees).toLocaleString('es-ES', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    if (balanceEl) {
        balanceEl.textContent = '$' + Number(data.balance).toLocaleString('es-ES', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        balanceEl.style.color = data.balance >= 0 ? 'var(--success-color)' : 'var(--danger-color)';
        
        // Actualizar también el ícono y el fondo según el balance
        if (balanceIcon) {
            balanceIcon.style.color = data.balance >= 0 ? 'var(--success-color)' : 'var(--danger-color)';
        }
        if (balanceIconBg) {
            balanceIconBg.style.background = data.balance >= 0 ? 'rgba(34, 197, 94, 0.1)' : 'rgba(239, 68, 68, 0.1)';
        }
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

// Actualizar tabla de métodos de pago por equipo
function updateTeamPaymentMethods(teamPaymentMethods, dateRange) {
    // Buscar el contenedor específico de la tabla usando un selector más específico
    const cardSelector = '.card .card-header h3:contains("Métodos de Pago por Equipo")';
    let container = document.querySelector('.team-payment-table');
    
    // Si no existe, buscar por el título de la card y obtener el contenedor de contenido
    if (!container) {
        // Buscar el card que contiene "Métodos de Pago por Equipo"
        const cardHeaders = document.querySelectorAll('.card-header h3');
        let targetCard = null;
        
        for (let header of cardHeaders) {
            if (header.textContent.includes('Métodos de Pago por Equipo')) {
                targetCard = header.closest('.card');
                break;
            }
        }
        
        if (targetCard) {
            const cardContent = targetCard.querySelector('.card-header + div');
            if (cardContent) {
                // Recrear la estructura básica si no existe
                cardContent.innerHTML = '<div class="team-payment-table" style="overflow-x: auto;"></div>';
                container = cardContent.querySelector('.team-payment-table');
            }
        }
    }
    
    if (!container) {
        console.error('No se pudo encontrar o crear el contenedor de la tabla de métodos de pago');
        return;
    }
    
    console.log('Contenedor encontrado, actualizando tabla...');
    
    // Actualizar el subtítulo con el nuevo rango de fechas
    const subtitle = document.querySelector('.card-header p');
    if (subtitle && dateRange) {
        // Formatear fechas sin problemas de timezone
        const startDate = formatDateFromString(dateRange.start_date);
        const endDate = formatDateFromString(dateRange.end_date);
        subtitle.textContent = `Periodo: ${startDate} - ${endDate}`;
    }
    
    if (teamPaymentMethods.length === 0) {
        container.innerHTML = `
            <div style="text-align: center; padding: 60px 20px; color: var(--text-secondary);">
                <i class="fas fa-chart-bar" style="font-size: 48px; margin-bottom: 16px; color: var(--text-muted);"></i>
                <h4 style="margin: 0 0 8px 0; color: var(--text-primary);">No hay datos disponibles</h4>
                <p style="margin: 0; font-size: 14px;">No se encontraron pagos de equipos en el rango de fechas seleccionado.</p>
                <p style="margin: 8px 0 0 0; font-size: 12px; color: var(--text-muted);">
                    Periodo: ${dateRange ? formatDateFromString(dateRange.start_date) + ' - ' + formatDateFromString(dateRange.end_date) : ''}
                </p>
            </div>
        `;
        return;
    }
    
    // Obtener todos los métodos de pago únicos
    const allPaymentMethods = [];
    teamPaymentMethods.forEach(team => {
        Object.keys(team.payment_methods).forEach(method => {
            if (!allPaymentMethods.includes(method)) {
                allPaymentMethods.push(method);
            }
        });
    });
    allPaymentMethods.sort();
    
    // Generar tabla HTML horizontal - solo la tabla, sin el div wrapper
    let tableHTML = `
            <table style="width: 100%; border-collapse: collapse; font-size: 13px; border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                <thead>
                    <tr style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
                        <th style="padding: 16px 12px; text-align: left; font-weight: 600; color: var(--text-primary); border-right: 1px solid var(--border-color);">
                            <i class="fas fa-users" style="color: var(--primary-color); margin-right: 6px;"></i>
                            Equipo
                        </th>
    `;
    
    // Encabezados de métodos de pago
    allPaymentMethods.forEach(method => {
        const icon = method === 'Efectivo' ? 'money-bill-wave' : 
                    method === 'Transferencia Bancaria' ? 'university' : 
                    method === 'ATH M' ? 'mobile-alt' : 
                    method === 'ATH B' ? 'credit-card' : 'credit-card';
        
        tableHTML += `
            <th style="padding: 16px 8px; text-align: center; font-weight: 600; color: var(--text-primary); border-right: 1px solid var(--border-color); min-width: 100px;">
                <div style="display: flex; flex-direction: column; align-items: center; gap: 4px;">
                    <i class="fas fa-${icon}" style="color: var(--primary-color); font-size: 12px;"></i>
                    <span style="font-size: 11px; font-weight: 600;">${method}</span>
                </div>
            </th>
        `;
    });
    
    // Encabezado de Fee
    tableHTML += `
        <th style="padding: 16px 8px; text-align: center; font-weight: 600; color: var(--warning-color); border-right: 1px solid var(--border-color); min-width: 90px; background: rgba(245, 158, 11, 0.1);">
            <div style="display: flex; flex-direction: column; align-items: center; gap: 4px;">
                <i class="fas fa-percentage" style="color: var(--warning-color); font-size: 12px;"></i>
                <span style="font-size: 11px; font-weight: 600;">Fee</span>
            </div>
        </th>
        <th style="padding: 16px 8px; text-align: center; font-weight: 600; color: var(--success-color); border-right: 1px solid var(--border-color); min-width: 100px; background: rgba(34, 197, 94, 0.1);">
            <div style="display: flex; flex-direction: column; align-items: center; gap: 4px;">
                <i class="fas fa-plus-circle" style="color: var(--success-color); font-size: 12px;"></i>
                <span style="font-size: 11px; font-weight: 600;">Total Bruto</span>
            </div>
        </th>
        <th style="padding: 16px 8px; text-align: center; font-weight: 600; color: var(--info-color); border-right: 1px solid var(--border-color); min-width: 100px; background: rgba(59, 130, 246, 0.1);">
            <div style="display: flex; flex-direction: column; align-items: center; gap: 4px;">
                <i class="fas fa-minus-circle" style="color: var(--info-color); font-size: 12px;"></i>
                <span style="font-size: 11px; font-weight: 600;">Total Neto</span>
            </div>
        </th>
    </tr>
    </thead>
    <tbody>
    `;
    
    // Filas de equipos
    teamPaymentMethods.forEach(team => {
        tableHTML += `
            <tr style="border-bottom: 1px solid var(--border-color); transition: all 0.2s ease;" 
                onmouseover="this.style.backgroundColor='#f8f9fa'" 
                onmouseout="this.style.backgroundColor='transparent'">
                
                <!-- Nombre del equipo -->
                <td style="padding: 14px 12px; font-weight: 500; border-right: 1px solid var(--border-color); background: rgba(37, 99, 235, 0.02);">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <div style="width: 6px; height: 6px; background: var(--primary-color); border-radius: 50%;"></div>
                        <span style="color: var(--text-primary); font-size: 13px;">${team.team_name}</span>
                    </div>
                </td>
        `;
        
        // Columnas de métodos de pago
        allPaymentMethods.forEach(method => {
            if (team.payment_methods[method]) {
                const data = team.payment_methods[method];
                tableHTML += `
                    <td style="padding: 10px 8px; text-align: center; border-right: 1px solid var(--border-color);">
                        <span style="font-size: 13px; font-weight: 600; color: var(--success-color);">
                            $${Number(data.amount).toLocaleString('es-ES', {minimumFractionDigits: 0, maximumFractionDigits: 0})}
                        </span>
                    </td>
                `;
            } else {
                tableHTML += `
                    <td style="padding: 10px 8px; text-align: center; border-right: 1px solid var(--border-color);">
                        <span style="color: var(--text-muted); font-size: 12px;">—</span>
                    </td>
                `;
            }
        });
        
        // Columna de Fee (real de la BD)
        const teamFee = team.total_fee || 0;
        tableHTML += `
            <td style="padding: 10px 8px; text-align: center; border-right: 1px solid var(--border-color); background: rgba(245, 158, 11, 0.05);">
                ${teamFee > 0 ? `
                    <span style="font-size: 13px; font-weight: 600; color: var(--warning-color);">
                        $${Number(teamFee).toLocaleString('es-ES', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                    </span>
                ` : `
                    <span style="color: var(--text-muted); font-size: 12px;">—</span>
                `}
            </td>
        `;
        
        // Columna de Total Bruto
        const teamTotalBruto = team.total_amount || 0;
        tableHTML += `
            <td style="padding: 10px 8px; text-align: center; border-right: 1px solid var(--border-color); background: rgba(34, 197, 94, 0.05);">
                <span style="font-size: 13px; font-weight: 600; color: var(--success-color);">
                    $${Number(teamTotalBruto).toLocaleString('es-ES', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                </span>
            </td>
        `;
        
        // Columna de Total Neto
        const teamTotalNeto = teamTotalBruto - teamFee;
        tableHTML += `
            <td style="padding: 10px 8px; text-align: center; border-right: 1px solid var(--border-color); background: rgba(59, 130, 246, 0.05);">
                <span style="font-size: 13px; font-weight: 600; color: var(--info-color);">
                    $${Number(teamTotalNeto).toLocaleString('es-ES', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                </span>
            </td>
        `;
        
        tableHTML += `
        </tr>
        `;
    });
    
    // Fila de totales
    tableHTML += `
        <tr style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-top: 2px solid var(--border-color); font-weight: bold;">
            <td style="padding: 16px 12px; font-weight: bold; color: var(--text-primary); border-right: 1px solid var(--border-color);">
                <i class="fas fa-sigma" style="margin-right: 6px; color: var(--primary-color);"></i>
                TOTALES
            </td>
    `;
    
    // Totales por método de pago
    allPaymentMethods.forEach(method => {
        let methodTotal = 0;
        let methodCount = 0;
        teamPaymentMethods.forEach(team => {
            if (team.payment_methods[method]) {
                methodTotal += team.payment_methods[method].amount;
                methodCount += team.payment_methods[method].count;
            }
        });
        
        if (methodTotal > 0) {
            tableHTML += `
                <td style="padding: 10px 8px; text-align: center; border-right: 1px solid var(--border-color);">
                    <span style="font-size: 13px; font-weight: bold; color: var(--success-color);">
                        $${Number(methodTotal).toLocaleString('es-ES', {minimumFractionDigits: 0, maximumFractionDigits: 0})}
                    </span>
                </td>
            `;
        } else {
            tableHTML += `
                <td style="padding: 10px 8px; text-align: center; border-right: 1px solid var(--border-color);">
                    <span style="color: var(--text-muted); font-size: 12px;">—</span>
                </td>
            `;
        }
    });
    
    // Total de fees y gran total
    const totalAmount = teamPaymentMethods.reduce((sum, team) => sum + team.total_amount, 0);
    const totalFee = teamPaymentMethods.reduce((sum, team) => sum + (team.total_fee || 0), 0);
    const totalPayments = teamPaymentMethods.reduce((sum, team) => sum + team.total_payments, 0);
    
    tableHTML += `
        <!-- Total de Fees -->
        <td style="padding: 10px 8px; text-align: center; border-right: 1px solid var(--border-color); background: rgba(245, 158, 11, 0.1);">
            ${totalFee > 0 ? `
                <span style="font-size: 13px; font-weight: bold; color: var(--warning-color);">
                    $${Number(totalFee).toLocaleString('es-ES', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                </span>
            ` : `
                <span style="color: var(--text-muted); font-size: 12px;">—</span>
            `}
        </td>
        
        <!-- Total Bruto General -->
        <td style="padding: 10px 8px; text-align: center; border-right: 1px solid var(--border-color); background: rgba(34, 197, 94, 0.1);">
            <span style="font-size: 13px; font-weight: bold; color: var(--success-color);">
                $${Number(totalAmount).toLocaleString('es-ES', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
            </span>
        </td>
        
        <!-- Total Neto General -->
        <td style="padding: 10px 8px; text-align: center; border-right: 1px solid var(--border-color); background: rgba(59, 130, 246, 0.1);">
            <span style="font-size: 13px; font-weight: bold; color: var(--info-color);">
                $${Number(totalAmount - totalFee).toLocaleString('es-ES', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
            </span>
        </td>
    </tr>
    </tbody>
    </table>
    `;
    
    // Reemplazar SOLO el contenido del contenedor .team-payment-table
    container.innerHTML = tableHTML;
    
    console.log('Tabla de métodos de pago actualizada exitosamente');
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

// Actualizar estado del período activo (simplificado para dropdown)
function updateActivePeriodButtons(activePeriod) {
    // Ya no necesitamos actualizar botones individuales, 
    // el estado se maneja automáticamente en el dropdown
    // Esta función se mantiene para compatibilidad con el AJAX
    console.log('Período activo detectado:', activePeriod);
}

// Función para ocultar el overlay de carga
function hideDashboardLoadingOverlay() {
    const overlay = document.getElementById('dashboardLoadingOverlay');
    const mainLayout = document.querySelector('.main-layout');
    
    if (overlay && mainLayout) {
        // Mostrar el contenido principal
        mainLayout.classList.add('loaded');
        
        // Ocultar el overlay con animación
        overlay.style.opacity = '0';
        overlay.style.transition = 'opacity 0.4s ease';
        setTimeout(() => {
            overlay.style.display = 'none';
        }, 400);
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

    // Verificar visualmente los botones
    console.log("Estado actual de los botones:");
    console.log("Esta Semana:", document.getElementById('btnCurrentWeek').classList.contains('btn-secondary'));
    console.log("Semana Anterior:", document.getElementById('btnPreviousWeek').classList.contains('btn-secondary'));
    console.log("Este Mes:", document.getElementById('btnCurrentMonth').classList.contains('btn-secondary'));
    console.log("Mes Anterior:", document.getElementById('btnPreviousMonth').classList.contains('btn-secondary'));
});

// Ocultar overlay cuando todo esté completamente cargado (incluyendo gráficos)
window.addEventListener('load', function() {
    // Esperar un poco más para asegurar que Chart.js termine de renderizar
    setTimeout(() => {
        hideDashboardLoadingOverlay();
    }, 800);
});

// Fallback: ocultar overlay después de un tiempo máximo para evitar que se quede colgado
setTimeout(() => {
    hideDashboardLoadingOverlay();
}, 5000); // 5 segundos máximo
</script>

<style>
/* Estilos para la tabla de métodos de pago por equipo */
.team-payment-table table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.team-payment-table th {
    padding: 12px;
    text-align: left;
    font-weight: 600;
    color: var(--text-primary);
    background-color: var(--background-secondary, #f8f9fa);
    border-bottom: 2px solid var(--border-color);
}

.team-payment-table td {
    padding: 12px;
    border-bottom: 1px solid var(--border-color);
    transition: background-color 0.2s;
}

.team-payment-table tr:hover {
    background-color: var(--hover-color, #f8f9fa);
}

@media (max-width: 768px) {
    .team-payment-table {
        font-size: 12px;
    }
    
    .team-payment-table th,
    .team-payment-table td {
        padding: 8px;
    }
}

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
    
    /* Responsive para cards del resumen financiero */
    .financial-summary {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 12px !important;
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
    
    /* Cards del resumen financiero en móviles pequeños */
    .financial-summary {
        grid-template-columns: 1fr !important;
        gap: 12px !important;
    }
}

.btn-group .btn {
    min-width: 120px;
}

/* Estilos para indicador de carga discreto - solo en botón */

/* Animaciones deshabilitadas para actualizaciones AJAX 
   Solo se permiten en carga inicial de página */

/* Las teclas de acceso rápido siguen funcionando pero sin indicadores visuales */

/* Animación del spinner de carga */
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Optimizaciones para el overlay de carga */
#dashboardLoadingOverlay {
    user-select: none;
    pointer-events: all;
}

/* Asegurar que el contenido principal esté oculto inicialmente */
.main-layout {
    opacity: 0;
    transition: opacity 0.3s ease;
}

.main-layout.loaded {
    opacity: 1;
}
</style>
