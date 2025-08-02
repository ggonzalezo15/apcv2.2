<?php
require_once 'config.php';
require_once 'audit_system.php';

// Verificar autenticación
if (!isLoggedIn() || !checkSessionTimeout()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

// Establecer cabeceras JSON
header('Content-Type: application/json');

// Obtener rango de fechas desde la petición
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('monday this week'));
$endDate = $_GET['end_date'] ?? date('Y-m-d', strtotime('sunday this week'));

// Depuración AJAX
error_log("DASHBOARD_AJAX - Fechas recibidas: $startDate a $endDate");
error_log("DASHBOARD_AJAX - Parámetros GET: " . json_encode($_GET));

// Validar fechas
if (!$startDate || !$endDate) {
    $startDate = date('Y-m-d', strtotime('monday this week'));
    $endDate = date('Y-m-d', strtotime('sunday this week'));
}

try {
    $pdo = getConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de conexión a la base de datos']);
    exit;
}

// Función unificada para obtener datos financieros y de métodos de pago (AJAX)
function getUnifiedFinancialDataAjax($pdo, $startDate, $endDate) {
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
        
        error_log("Consulta unificada AJAX - Ingresos: $totalIncome, Fees: $totalFees, Equipos: " . count($teamData));
        
    } catch (Exception $e) {
        error_log("Error en consulta unificada AJAX: " . $e->getMessage());
    }
    
    // Consulta separada para gastos
    try {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(e.total_amount), 0) as total_expenses
            FROM expenses e 
            WHERE e.expense_date BETWEEN ? AND ?
        ");
        $stmt->execute([$startDate, $endDate]);
        $result['financial_summary']['expenses'] = $stmt->fetchColumn() ?: 0;
        
    } catch (Exception $e) {
        error_log("Error en consulta de gastos AJAX: " . $e->getMessage());
    }
    
    // Calcular balance
    $result['financial_summary']['balance'] = 
        $result['financial_summary']['income'] - 
        $result['financial_summary']['expenses'] - 
        $result['financial_summary']['fees'];
    
    return $result;
}

// Funciones wrapper para mantener compatibilidad
function getFinancialSummaryAjax($pdo, $startDate, $endDate) {
    static $cachedData = null;
    static $cachedDates = null;
    
    // Usar caché si las fechas son las mismas
    if ($cachedData === null || $cachedDates !== [$startDate, $endDate]) {
        $cachedData = getUnifiedFinancialDataAjax($pdo, $startDate, $endDate);
        $cachedDates = [$startDate, $endDate];
    }
    
    return $cachedData['financial_summary'];
}

function getTeamPaymentMethodsAjax($pdo, $startDate, $endDate) {
    static $cachedData = null;
    static $cachedDates = null;
    
    // Usar caché si las fechas son las mismas
    if ($cachedData === null || $cachedDates !== [$startDate, $endDate]) {
        $cachedData = getUnifiedFinancialDataAjax($pdo, $startDate, $endDate);
        $cachedDates = [$startDate, $endDate];
    }
    
    error_log("getTeamPaymentMethodsAjax - Consulta con fechas: $startDate a $endDate");
    error_log("getTeamPaymentMethodsAjax - Registros encontrados: " . count($cachedData['team_payment_methods']));
    
    return $cachedData['team_payment_methods'];
}

function getIncomeContractorsAjax($pdo, $incomeId) {
    try {
        $stmt = $pdo->prepare("SELECT contractor_ids FROM incomes WHERE id = ?");
        $stmt->execute([$incomeId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['contractor_ids']) {
            $contractorIds = json_decode($result['contractor_ids'], true);
            if (is_array($contractorIds) && count($contractorIds) > 0) {
                $placeholders = str_repeat('?,', count($contractorIds) - 1) . '?';
                $stmt = $pdo->prepare("SELECT name FROM contractors WHERE id IN ($placeholders)");
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

// Función eliminada - la actividad reciente ya no se actualiza vía AJAX
// Se mantiene estática desde la carga inicial de la página

function getPendingIncomesAjax($pdo, $limit = 5) {
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
            WHERE 
                (i.total_income - COALESCE(SUM(ip.amount), 0)) > 0
            GROUP BY 
                i.id
            ORDER BY 
                i.date DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error obteniendo ingresos pendientes AJAX: " . $e->getMessage());
        return [];
    }
}

try {
    // Obtener datos que SÍ deben cambiar con el rango de fechas
    $financialSummary = getFinancialSummaryAjax($pdo, $startDate, $endDate);
    // NO incluir recentActivity - debe mantenerse estática
    $pendingIncomes = getPendingIncomesAjax($pdo, 5);
    $teamPaymentMethods = getTeamPaymentMethodsAjax($pdo, $startDate, $endDate);
    
    // Determinar período activo
    $currentWeekStart = new DateTime('monday this week');
    $currentWeekStart->setTime(0, 0, 0);
    $currentWeekEnd = clone $currentWeekStart;
    $currentWeekEnd->modify('+6 days');
    
    $previousWeekStart = clone $currentWeekStart;
    $previousWeekStart->modify('-7 days');
    $previousWeekEnd = clone $currentWeekEnd;
    $previousWeekEnd->modify('-7 days');
    
    $currentMonthStart = new DateTime('first day of this month');
    $currentMonthStart->setTime(0, 0, 0);
    $currentMonthEnd = new DateTime('last day of this month');
    
    $previousMonthStart = new DateTime('first day of last month');
    $previousMonthStart->setTime(0, 0, 0);
    $previousMonthEnd = new DateTime('last day of last month');
    
    $activePeriod = [
        'isCurrentWeek' => ($startDate == $currentWeekStart->format('Y-m-d') && $endDate == $currentWeekEnd->format('Y-m-d')),
        'isPreviousWeek' => ($startDate == $previousWeekStart->format('Y-m-d') && $endDate == $previousWeekEnd->format('Y-m-d')),
        'isCurrentMonth' => ($startDate == $currentMonthStart->format('Y-m-d') && $endDate == $currentMonthEnd->format('Y-m-d')),
        'isPreviousMonth' => ($startDate == $previousMonthStart->format('Y-m-d') && $endDate == $previousMonthEnd->format('Y-m-d'))
    ];
    
    // Respuesta JSON (SIN recent_activity - se mantiene estática)
    echo json_encode([
        'success' => true,
        'data' => [
            'financial_summary' => $financialSummary,
            'pending_incomes' => $pendingIncomes,
            'team_payment_methods' => $teamPaymentMethods,
            'active_period' => $activePeriod,
            'date_range' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error en dashboard AJAX: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor'
    ]);
}
?> 