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

// Funciones AJAX para el dashboard profesional
function getFinancialSummaryAjax($pdo, $startDate, $endDate) {
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
        error_log("Error en consulta de ingresos AJAX: " . $e->getMessage());
    }
    
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
        error_log("Error en consulta de gastos AJAX: " . $e->getMessage());
    }
    
    $balance = $totalIncome - $totalExpenses;
    
    return [
        'income' => $totalIncome,
        'expenses' => $totalExpenses,
        'balance' => $balance
    ];
}

// NUEVA FUNCIONALIDAD: Obtener totales por métodos de pago AJAX
function getPaymentMethodTotalsAjax($pdo, $startDate, $endDate) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                pt.name as payment_method,
                pt.id as payment_type_id,
                COALESCE(SUM(ip.amount), 0) as total_amount,
                COUNT(ip.id) as payment_count
            FROM payment_types pt
            LEFT JOIN income_payments ip ON pt.id = ip.payment_type_id 
                AND DATE(ip.created_at) BETWEEN ? AND ?
            GROUP BY pt.id, pt.name
            ORDER BY total_amount DESC
        ");
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error obteniendo totales por método de pago AJAX: " . $e->getMessage());
        return [];
    }
}

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
            FROM incomes i
            LEFT JOIN income_payments ip ON i.id = ip.income_id
            LEFT JOIN teams t ON i.team_id = t.id
            WHERE (i.total_income - COALESCE(SUM(ip.amount), 0)) > 0
            GROUP BY i.id
            ORDER BY i.date DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error obteniendo ingresos pendientes AJAX: " . $e->getMessage());
        return [];
    }
}

function getDailyDataAjax($pdo, $startDate, $endDate) {
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
        error_log("Error obteniendo ingresos diarios AJAX: " . $e->getMessage());
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
        error_log("Error obteniendo gastos diarios AJAX: " . $e->getMessage());
    }
    
    return array_values($dates);
}

try {
    // Obtener datos que deben cambiar con el rango de fechas
    $financialSummary = getFinancialSummaryAjax($pdo, $startDate, $endDate);
    $paymentMethods = getPaymentMethodTotalsAjax($pdo, $startDate, $endDate); // NUEVA FUNCIONALIDAD
    $pendingIncomes = getPendingIncomesAjax($pdo, 5);
    $dailyData = getDailyDataAjax($pdo, $startDate, $endDate);
    
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
    
    // Respuesta JSON con la nueva funcionalidad de métodos de pago
    echo json_encode([
        'success' => true,
        'data' => [
            'financial_summary' => $financialSummary,
            'payment_methods' => $paymentMethods, // NUEVA FUNCIONALIDAD
            'pending_incomes' => $pendingIncomes,
            'daily_data' => $dailyData,
            'active_period' => $activePeriod,
            'date_range' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error en dashboard2 AJAX: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor'
    ]);
}
?> 