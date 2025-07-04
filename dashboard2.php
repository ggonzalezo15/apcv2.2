<?php
require_once 'config.php';
require_once 'audit_system.php';

// Verificar autenticación
if (!isLoggedIn() || !checkSessionTimeout()) {
    header('Location: auth/login.php');
    exit;
}

$pageTitle = 'Dashboard Profesional';

// Establecer conexión a la base de datos
try {
    $pdo = getConnection();
} catch (Exception $e) {
    die("Error de conexión a la base de datos: " . $e->getMessage());
}

// Lógica de fechas (igual que el dashboard original)
$isAjaxUpdate = isset($_GET['ajax_update']) && $_GET['ajax_update'] === '1';

if ($isAjaxUpdate) {
    $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('monday this week'));
    $endDate = $_GET['end_date'] ?? date('Y-m-d', strtotime('sunday this week'));
} else {
    $startDate = date('Y-m-d', strtotime('monday this week'));
    $endDate = date('Y-m-d', strtotime('sunday this week'));
}

// Validar fechas
if (!$startDate || !$endDate) {
    $startDate = date('Y-m-d', strtotime('monday this week'));
    $endDate = date('Y-m-d', strtotime('sunday this week'));
}

// Función para obtener resumen financiero
function getFinancialSummary($pdo, $startDate, $endDate) {
    // Obtener total de ingresos
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
    
    // Obtener total de gastos
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
    
    $balance = $totalIncome - $totalExpenses;
    
    return [
        'income' => $totalIncome,
        'expenses' => $totalExpenses,
        'balance' => $balance
    ];
}

// Función para obtener totales por métodos de pago (NUEVA FUNCIONALIDAD)
function getPaymentMethodTotals($pdo, $startDate, $endDate) {
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
        error_log("Error obteniendo totales por método de pago: " . $e->getMessage());
        return [];
    }
}

// Función para obtener balances de cuentas bancarias
function getBankAccountBalances($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT name, bank_name, account_number, balance, account_type 
            FROM bank_accounts 
            ORDER BY balance DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

// Función para obtener actividad reciente
function getRecentActivity($pdo, $limit = 5) {
    try {
        if (class_exists('AuditSystem')) {
            $audit = new AuditSystem();
            $auditActivities = $audit->getRecentActivities($limit);
            
            if (!empty($auditActivities)) {
                $formattedActivities = [];
                foreach ($auditActivities as $activity) {
                    $formatted = [
                        'type' => $activity['entity_type'],
                        'date' => $activity['created_at'],
                        'description' => $activity['description'],
                        'amount' => null,
                        'account_name' => $activity['username'] ?? 'Sistema',
                        'user' => $activity['username'],
                        'action_icon' => $activity['action_icon'],
                        'entity_name' => $activity['entity_name']
                    ];
                    
                    if ($activity['entity_type'] == 'income' && !empty($activity['entity_id'])) {
                        try {
                            $stmt = $pdo->prepare("
                                SELECT i.id, i.invoice_number, i.total_amount,
                                       t.name as team_name
                                FROM incomes i
                                LEFT JOIN teams t ON i.team_id = t.id
                                WHERE i.id = ? LIMIT 1
                            ");
                            $stmt->execute([$activity['entity_id']]);
                            $incomeData = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($incomeData) {
                                $formatted['amount'] = $incomeData['total_amount'];
                                $formatted['team_name'] = $incomeData['team_name'] ?? '';
                            }
                        } catch (Exception $e) {
                            // Continuar sin información adicional
                        }
                    }
                    
                    $formattedActivities[] = $formatted;
                }
                
                return $formattedActivities;
            }
        }
    } catch (Exception $e) {
        error_log("Error obteniendo actividades: " . $e->getMessage());
    }
    
    return [];
}

// Función para obtener ingresos pendientes
function getPendingIncomes($pdo, $limit = 5) {
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
        error_log("Error obteniendo ingresos pendientes: " . $e->getMessage());
        return [];
    }
}

// Función para obtener datos diarios
function getDailyData($pdo, $startDate, $endDate) {
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
        // Continuar sin gastos
    }
    
    return array_values($dates);
}

// Obtener todos los datos necesarios
try {
    $financialSummary = getFinancialSummary($pdo, $startDate, $endDate);
    $paymentMethodTotals = getPaymentMethodTotals($pdo, $startDate, $endDate);
    $bankAccounts = getBankAccountBalances($pdo);
    $recentActivity = getRecentActivity($pdo);
    $pendingIncomes = getPendingIncomes($pdo, 5);
    $dailyData = getDailyData($pdo, $startDate, $endDate);
    
    // Determinar período activo
    $today = new DateTime();
    $currentWeekStart = new DateTime('monday this week');
    $currentWeekEnd = clone $currentWeekStart;
    $currentWeekEnd->modify('+6 days');
    
    $isCurrentWeek = ($startDate == $currentWeekStart->format('Y-m-d') && $endDate == $currentWeekEnd->format('Y-m-d'));
    $isPreviousWeek = false;
    $isCurrentMonth = false;
    $isPreviousMonth = false;
    
    // Lógica para otros períodos...
    
} catch (Exception $e) {
    $financialSummary = ['income' => 0, 'expenses' => 0, 'balance' => 0];
    $paymentMethodTotals = [];
    $bankAccounts = [];
    $recentActivity = [];
    $pendingIncomes = [];
    $dailyData = [];
    $isCurrentWeek = false;
    $isPreviousWeek = false;
    $isCurrentMonth = false;
    $isPreviousMonth = false;
    error_log("Error en dashboard2: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Profesional - APV</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
</head>
<body class="modern-dashboard">
    <div class="app-container">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-chart-line"></i>
                    <span>APV</span>
                </div>
                <button class="sidebar-toggle" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
            
            <div class="sidebar-menu">
                <div class="menu-section">
                    <h6>Principal</h6>
                    <a href="dashboard2.php" class="menu-item active">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="dashboard.php" class="menu-item">
                        <i class="fas fa-chart-bar"></i>
                        <span>Dashboard Clásico</span>
                    </a>
                </div>
                
                <div class="menu-section">
                    <h6>Gestión</h6>
                    <a href="incomes.php" class="menu-item">
                        <i class="fas fa-arrow-up"></i>
                        <span>Ingresos</span>
                    </a>
                    <a href="expenses.php" class="menu-item">
                        <i class="fas fa-arrow-down"></i>
                        <span>Gastos</span>
                    </a>
                    <a href="contractors.php" class="menu-item">
                        <i class="fas fa-users"></i>
                        <span>Contratistas</span>
                    </a>
                    <a href="vendors.php" class="menu-item">
                        <i class="fas fa-store"></i>
                        <span>Proveedores</span>
                    </a>
                </div>
                
                <div class="menu-section">
                    <h6>Configuración</h6>
                    <a href="bank_accounts.php" class="menu-item">
                        <i class="fas fa-university"></i>
                        <span>Cuentas Bancarias</span>
                    </a>
                    <a href="teams.php" class="menu-item">
                        <i class="fas fa-users-cog"></i>
                        <span>Equipos</span>
                    </a>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Bar -->
            <header class="top-bar">
                <div class="top-bar-left">
                    <h1>Dashboard Profesional</h1>
                    <p>Análisis financiero en tiempo real</p>
                </div>
                <div class="top-bar-right">
                    <div class="period-selector">
                        <div class="date-range">
                            <input type="date" id="startDate" value="<?php echo $startDate; ?>">
                            <span>—</span>
                            <input type="date" id="endDate" value="<?php echo $endDate; ?>">
                            <button onclick="updatePeriod()" class="btn-update">
                                <i class="fas fa-sync"></i>
                            </button>
                        </div>
                        <div class="period-tabs">
                            <button onclick="setWeekPeriod('current')" class="tab <?php echo $isCurrentWeek ? 'active' : ''; ?>">Esta Semana</button>
                            <button onclick="setWeekPeriod('previous')" class="tab <?php echo $isPreviousWeek ? 'active' : ''; ?>">Anterior</button>
                            <button onclick="setMonthPeriod('current')" class="tab <?php echo $isCurrentMonth ? 'active' : ''; ?>">Este Mes</button>
                            <button onclick="setMonthPeriod('previous')" class="tab <?php echo $isPreviousMonth ? 'active' : ''; ?>">Mes Ant.</button>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card income animate__animated animate__fadeInUp">
                        <div class="stat-icon">
                            <i class="fas fa-arrow-up"></i>
                        </div>
                        <div class="stat-content">
                            <span class="stat-label">Ingresos</span>
                            <span class="stat-value">$<?php echo number_format($financialSummary['income'], 2); ?></span>
                        </div>
                        <div class="stat-trend">
                            <i class="fas fa-arrow-up"></i>
                            <span>+12.5%</span>
                        </div>
                    </div>

                    <div class="stat-card expenses animate__animated animate__fadeInUp" style="animation-delay: 0.1s;">
                        <div class="stat-icon">
                            <i class="fas fa-arrow-down"></i>
                        </div>
                        <div class="stat-content">
                            <span class="stat-label">Gastos</span>
                            <span class="stat-value">$<?php echo number_format($financialSummary['expenses'], 2); ?></span>
                        </div>
                        <div class="stat-trend">
                            <i class="fas fa-arrow-down"></i>
                            <span>-5.2%</span>
                        </div>
                    </div>

                    <div class="stat-card balance <?php echo $financialSummary['balance'] >= 0 ? 'positive' : 'negative'; ?> animate__animated animate__fadeInUp" style="animation-delay: 0.2s;">
                        <div class="stat-icon">
                            <i class="fas fa-balance-scale"></i>
                        </div>
                        <div class="stat-content">
                            <span class="stat-label">Balance</span>
                            <span class="stat-value">$<?php echo number_format($financialSummary['balance'], 2); ?></span>
                        </div>
                        <div class="stat-trend">
                            <i class="fas fa-<?php echo $financialSummary['balance'] >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                            <span><?php echo $financialSummary['balance'] >= 0 ? '+' : ''; ?>8.3%</span>
                        </div>
                    </div>
                </div>

                <!-- Main Grid -->
                <div class="content-grid">
                    <!-- Chart Section -->
                    <div class="chart-container animate__animated animate__fadeInUp" style="animation-delay: 0.3s;">
                        <div class="chart-header">
                            <h3>Análisis de Flujo de Caja</h3>
                            <div class="chart-controls">
                                <button class="chart-btn active" data-type="bar">
                                    <i class="fas fa-chart-bar"></i>
                                </button>
                                <button class="chart-btn" data-type="line">
                                    <i class="fas fa-chart-line"></i>
                                </button>
                            </div>
                        </div>
                        <div class="chart-content">
                            <canvas id="incomeExpenseChart"></canvas>
                        </div>
                    </div>

                    <!-- Payment Methods -->
                    <div class="payment-methods-container animate__animated animate__fadeInUp" style="animation-delay: 0.4s;">
                        <div class="section-header">
                            <h3>Métodos de Pago</h3>
                            <span class="badge"><?php echo count($paymentMethodTotals); ?> métodos</span>
                        </div>
                        <div class="payment-methods-grid">
                            <?php if (!empty($paymentMethodTotals)): ?>
                                <?php foreach ($paymentMethodTotals as $index => $method): ?>
                                    <div class="payment-method-card" style="animation-delay: <?php echo 0.1 * $index; ?>s;">
                                        <div class="method-icon">
                                            <i class="fas fa-credit-card"></i>
                                        </div>
                                        <div class="method-details">
                                            <h4><?php echo htmlspecialchars($method['payment_method']); ?></h4>
                                            <p><?php echo $method['payment_count']; ?> transacciones</p>
                                        </div>
                                        <div class="method-amount">
                                            $<?php echo number_format($method['total_amount'], 2); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-credit-card"></i>
                                    <p>No hay métodos de pago registrados</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Pending Incomes -->
                    <div class="pending-container animate__animated animate__fadeInUp" style="animation-delay: 0.5s;">
                        <div class="section-header">
                            <h3>Ingresos Pendientes</h3>
                            <span class="badge urgent"><?php echo count($pendingIncomes); ?> pendientes</span>
                        </div>
                        <div class="pending-list">
                            <?php if (!empty($pendingIncomes)): ?>
                                <?php foreach ($pendingIncomes as $index => $income): ?>
                                    <div class="pending-item" style="animation-delay: <?php echo 0.1 * $index; ?>s;">
                                        <div class="pending-info">
                                            <h4><?php echo htmlspecialchars($income['invoice_number'] ?: 'Sin número'); ?></h4>
                                            <p><?php echo date('d M Y', strtotime($income['date'])); ?></p>
                                            <div class="progress-bar">
                                                <?php 
                                                $progress = $income['total_income'] > 0 ? ($income['total_paid'] / $income['total_income']) * 100 : 0;
                                                ?>
                                                <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                                            </div>
                                        </div>
                                        <div class="pending-amount">
                                            <span class="amount">$<?php echo number_format($income['pending_amount'], 2); ?></span>
                                            <span class="label">Pendiente</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-check-circle"></i>
                                    <p>Todos los ingresos están al día</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="activity-container animate__animated animate__fadeInUp" style="animation-delay: 0.6s;">
                        <div class="section-header">
                            <h3>Actividad Reciente</h3>
                            <button class="view-all-btn">Ver todo</button>
                        </div>
                        <div class="activity-timeline">
                            <?php if (!empty($recentActivity)): ?>
                                <?php foreach ($recentActivity as $index => $activity): ?>
                                    <div class="activity-item" style="animation-delay: <?php echo 0.1 * $index; ?>s;">
                                        <div class="activity-icon">
                                            <?php echo $activity['action_icon'] ?? '<i class="fas fa-circle"></i>'; ?>
                                        </div>
                                        <div class="activity-content">
                                            <p><?php echo htmlspecialchars($activity['description']); ?></p>
                                            <span class="activity-time"><?php echo date('d M, H:i', strtotime($activity['date'])); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-history"></i>
                                    <p>No hay actividad reciente</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Bank Accounts -->
                    <div class="accounts-container animate__animated animate__fadeInUp" style="animation-delay: 0.7s;">
                        <div class="section-header">
                            <h3>Cuentas Bancarias</h3>
                            <button class="add-account-btn">
                                <i class="fas fa-plus"></i>
                                Nueva Cuenta
                            </button>
                        </div>
                        <div class="accounts-grid">
                            <?php if (!empty($bankAccounts)): ?>
                                <?php foreach ($bankAccounts as $index => $account): ?>
                                    <div class="account-card" style="animation-delay: <?php echo 0.1 * $index; ?>s;">
                                        <div class="account-header">
                                            <div class="account-icon">
                                                <i class="fas fa-university"></i>
                                            </div>
                                            <div class="account-info">
                                                <h4><?php echo htmlspecialchars($account['name']); ?></h4>
                                                <p><?php echo htmlspecialchars($account['bank_name']); ?></p>
                                            </div>
                                        </div>
                                        <div class="account-balance">
                                            <span class="balance-amount <?php echo $account['balance'] >= 0 ? 'positive' : 'negative'; ?>">
                                                $<?php echo number_format($account['balance'], 2); ?>
                                            </span>
                                            <span class="account-number">****<?php echo substr($account['account_number'], -4); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-university"></i>
                                    <p>No hay cuentas bancarias configuradas</p>
                                    <button class="btn-primary">Agregar Cuenta</button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner">
            <div class="spinner"></div>
            <p>Actualizando datos...</p>
        </div>
    </div>
</body>
</html>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Variables globales
let incomeExpenseChart;

// Inicializar gráfico
document.addEventListener('DOMContentLoaded', function() {
    initializeChart();
});

function initializeChart() {
    const ctx = document.getElementById('incomeExpenseChart').getContext('2d');
    incomeExpenseChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_map(function($d) {
                return date('d/m', strtotime($d['date']));
            }, $dailyData)); ?>,
            datasets: [{
                label: 'Ingresos',
                data: <?php echo json_encode(array_map(function($d) {
                    return floatval($d['income']);
                }, $dailyData)); ?>,
                backgroundColor: 'rgba(16, 185, 129, 0.8)',
                borderColor: 'rgb(16, 185, 129)',
                borderWidth: 2
            }, {
                label: 'Gastos',
                data: <?php echo json_encode(array_map(function($d) {
                    return floatval($d['expenses']);
                }, $dailyData)); ?>,
                backgroundColor: 'rgba(239, 68, 68, 0.8)',
                borderColor: 'rgb(239, 68, 68)',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '$' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
}

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
    
    updateDashboardData(startDate, endDate);
}

function setWeekPeriod(type) {
    const dates = getDateRangeForPeriod(type === 'current' ? 'current_week' : 'previous_week');
    document.getElementById('startDate').value = dates.startDate;
    document.getElementById('endDate').value = dates.endDate;
    updateDashboardData(dates.startDate, dates.endDate);
}

function setMonthPeriod(type) {
    const dates = getDateRangeForPeriod(type === 'current' ? 'current_month' : 'previous_month');
    document.getElementById('startDate').value = dates.startDate;
    document.getElementById('endDate').value = dates.endDate;
    updateDashboardData(dates.startDate, dates.endDate);
}

function getDateRangeForPeriod(type) {
    const now = new Date();
    const result = { startDate: null, endDate: null };
    
    switch (type) {
        case 'current_week':
            const day = now.getDay();
            const diff = now.getDate() - day + (day === 0 ? -6 : 1);
            result.startDate = new Date(now.getFullYear(), now.getMonth(), diff);
            result.endDate = new Date(result.startDate);
            result.endDate.setDate(result.startDate.getDate() + 6);
            break;
        case 'previous_week':
            const dayPrev = now.getDay();
            const diffPrev = now.getDate() - dayPrev + (dayPrev === 0 ? -6 : 1) - 7;
            result.startDate = new Date(now.getFullYear(), now.getMonth(), diffPrev);
            result.endDate = new Date(result.startDate);
            result.endDate.setDate(result.startDate.getDate() + 6);
            break;
        case 'current_month':
            result.startDate = new Date(now.getFullYear(), now.getMonth(), 1);
            result.endDate = new Date(now.getFullYear(), now.getMonth() + 1, 0);
            break;
        case 'previous_month':
            result.startDate = new Date(now.getFullYear(), now.getMonth() - 1, 1);
            result.endDate = new Date(now.getFullYear(), now.getMonth(), 0);
            break;
    }
    
    return {
        startDate: result.startDate.toISOString().split('T')[0],
        endDate: result.endDate.toISOString().split('T')[0]
    };
}

function updateDashboardData(startDate, endDate) {
    showLoadingIndicator();
    
    fetch(`dashboard2_ajax.php?start_date=${startDate}&end_date=${endDate}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateFinancialSummary(data.data.financial_summary);
                updatePaymentMethods(data.data.payment_methods);
                updateChart(data.data.daily_data);
                updatePendingIncomes(data.data.pending_incomes);
                updateActivePeriodButtons(data.data.active_period);
            } else {
                alert('Error al actualizar los datos: ' + (data.error || 'Error desconocido'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error de conexión');
        })
        .finally(() => {
            hideLoadingIndicator();
        });
}

function showLoadingIndicator() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.style.display = 'flex';
        overlay.classList.add('animate__animated', 'animate__fadeIn');
    }
    
    const btn = document.querySelector('.btn-update');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    }
}

function hideLoadingIndicator() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.style.display = 'none';
        overlay.classList.remove('animate__animated', 'animate__fadeIn');
    }
    
    const btn = document.querySelector('.btn-update');
    if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-sync"></i>';
    }
}

function updateFinancialSummary(data) {
    document.querySelector('.income-card .amount').textContent = '$' + Number(data.income).toLocaleString('es-ES', {minimumFractionDigits: 2});
    document.querySelector('.expenses-card .amount').textContent = '$' + Number(data.expenses).toLocaleString('es-ES', {minimumFractionDigits: 2});
    
    const balanceEl = document.querySelector('.balance-card .amount');
    balanceEl.textContent = '$' + Number(data.balance).toLocaleString('es-ES', {minimumFractionDigits: 2});
    
    const balanceCard = document.querySelector('.balance-card');
    balanceCard.className = balanceCard.className.replace(/(positive|negative)/, '');
    balanceCard.classList.add(data.balance >= 0 ? 'positive' : 'negative');
}

function updatePaymentMethods(methods) {
    const container = document.querySelector('.payment-methods-list');
    if (!container) return;
    
    if (methods.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-credit-card"></i>
                <p>No hay métodos de pago en este período</p>
            </div>
        `;
        return;
    }
    
    let html = '';
    methods.forEach(method => {
        html += `
            <div class="payment-method-item">
                <div class="method-info">
                    <span class="method-name">${method.payment_method}</span>
                    <span class="method-count">${method.payment_count} pagos</span>
                </div>
                <div class="method-amount">
                    $${Number(method.total_amount).toLocaleString('es-ES', {minimumFractionDigits: 2})}
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

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

function updatePendingIncomes(pendingIncomes) {
    const container = document.querySelector('.pending-list');
    if (!container) return;
    
    if (pendingIncomes.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <p>No hay ingresos pendientes</p>
            </div>
        `;
        return;
    }
    
    let html = '';
    pendingIncomes.forEach(income => {
        html += `
            <div class="pending-item">
                <div class="pending-info">
                    <h4>${income.invoice_number || 'Sin número'}</h4>
                    <p>${new Date(income.date).toLocaleDateString('es-ES')}</p>
                </div>
                <div class="pending-amount">
                    $${Number(income.pending_amount).toLocaleString('es-ES', {minimumFractionDigits: 2})}
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function updateActivePeriodButtons(activePeriod) {
    // Actualizar tabs activos
    const tabs = document.querySelectorAll('.period-tabs .tab');
    tabs.forEach(tab => {
        tab.classList.remove('active');
    });
    
    if (activePeriod.isCurrentWeek) {
        tabs[0].classList.add('active');
    } else if (activePeriod.isPreviousWeek) {
        tabs[1].classList.add('active');
    } else if (activePeriod.isCurrentMonth) {
        tabs[2].classList.add('active');
    } else if (activePeriod.isPreviousMonth) {
        tabs[3].classList.add('active');
    }
}

// Función para toggle del sidebar en móvil
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    sidebar.classList.toggle('open');
}

// Función para cambiar tipo de gráfico
function changeChartType(type) {
    const buttons = document.querySelectorAll('.chart-btn');
    buttons.forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    if (incomeExpenseChart) {
        incomeExpenseChart.config.type = type;
        incomeExpenseChart.update();
    }
}

// Event listeners para controles del gráfico
document.addEventListener('DOMContentLoaded', function() {
    // Chart type controls
    document.querySelectorAll('.chart-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const type = this.dataset.type;
            changeChartType(type);
        });
    });
    
    // Cerrar sidebar al hacer click fuera en móvil
    document.addEventListener('click', function(e) {
        const sidebar = document.querySelector('.sidebar');
        const toggle = document.querySelector('.sidebar-toggle');
        
        if (window.innerWidth <= 768 && 
            !sidebar.contains(e.target) && 
            !toggle.contains(e.target) && 
            sidebar.classList.contains('open')) {
            sidebar.classList.remove('open');
        }
    });
});
</script>

<style>
/* Modern Dashboard Styles */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #1a202c;
    line-height: 1.6;
    overflow-x: hidden;
}

.app-container {
    display: flex;
    min-height: 100vh;
    position: relative;
}

/* Modern Sidebar */
.sidebar {
    width: 280px;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-right: 1px solid rgba(255, 255, 255, 0.2);
    padding: 2rem 0;
    position: fixed;
    height: 100vh;
    z-index: 1000;
    transition: transform 0.3s ease;
}

.sidebar-header {
    padding: 0 2rem 2rem 2rem;
    border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.logo {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1.5rem;
    font-weight: 700;
    color: #4c51bf;
}

.logo i {
    font-size: 2rem;
}

.sidebar-toggle {
    display: none;
    background: none;
    border: none;
    font-size: 1.25rem;
    cursor: pointer;
    color: #6b7280;
}

.sidebar-menu {
    padding: 2rem 0;
}

.menu-section {
    margin-bottom: 2rem;
}

.menu-section h6 {
    color: #9ca3af;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    margin-bottom: 1rem;
    padding: 0 2rem;
}

.menu-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.875rem 2rem;
    color: #6b7280;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.2s ease;
    position: relative;
}

.menu-item:hover {
    background: rgba(99, 102, 241, 0.1);
    color: #4c51bf;
}

.menu-item.active {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.menu-item.active::before {
    content: '';
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 4px;
    height: 80%;
    background: white;
    border-radius: 0 2px 2px 0;
}

.menu-item i {
    font-size: 1.125rem;
    width: 20px;
    text-align: center;
}

/* Main Content */
.main-content {
    flex: 1;
    margin-left: 280px;
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(20px);
    min-height: 100vh;
}

/* Modern Top Bar */
.top-bar {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    padding: 1.5rem 2rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
}

.top-bar-left h1 {
    font-size: 1.875rem;
    font-weight: 700;
    color: #1a202c;
    margin-bottom: 0.25rem;
}

.top-bar-left p {
    color: #6b7280;
    font-size: 0.875rem;
}

.period-selector {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.date-range {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(255, 255, 255, 0.8);
    padding: 0.5rem 1rem;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.date-range input {
    border: none;
    background: none;
    font-size: 0.875rem;
    color: #374151;
    font-weight: 500;
}

.date-range input:focus {
    outline: none;
}

.btn-update {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    padding: 0.5rem;
    border-radius: 8px;
    cursor: pointer;
    transition: transform 0.2s ease;
}

.btn-update:hover {
    transform: scale(1.05);
}

.period-tabs {
    display: flex;
    background: rgba(255, 255, 255, 0.8);
    border-radius: 12px;
    padding: 0.25rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.tab {
    padding: 0.5rem 1rem;
    border: none;
    background: none;
    color: #6b7280;
    font-weight: 500;
    font-size: 0.875rem;
    cursor: pointer;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.tab:hover {
    background: rgba(99, 102, 241, 0.1);
    color: #4c51bf;
}

.tab.active {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

/* Dashboard Content */
.dashboard-content {
    padding: 2rem;
}

/* Modern Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    gap: 1.5rem;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(135deg, #667eea, #764ba2);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    position: relative;
}

.stat-card.income .stat-icon {
    background: linear-gradient(135deg, #10b981, #059669);
}

.stat-card.expenses .stat-icon {
    background: linear-gradient(135deg, #ef4444, #dc2626);
}

.stat-card.balance.positive .stat-icon {
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
}

.stat-card.balance.negative .stat-icon {
    background: linear-gradient(135deg, #f59e0b, #d97706);
}

.stat-content {
    flex: 1;
}

.stat-label {
    display: block;
    font-size: 0.875rem;
    color: #6b7280;
    font-weight: 500;
    margin-bottom: 0.5rem;
}

.stat-value {
    display: block;
    font-size: 2rem;
    font-weight: 700;
    color: #1a202c;
}

.stat-trend {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.875rem;
    font-weight: 600;
    color: #10b981;
}

.stat-trend.negative {
    color: #ef4444;
}

/* Content Grid */
.content-grid {
    display: grid;
    grid-template-columns: repeat(12, 1fr);
    gap: 2rem;
}

/* Chart Container */
.chart-container {
    grid-column: 1 / -1;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.chart-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 2rem;
}

.chart-header h3 {
    font-size: 1.25rem;
    font-weight: 600;
    color: #1a202c;
}

.chart-controls {
    display: flex;
    gap: 0.5rem;
}

.chart-btn {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    border: none;
    background: rgba(107, 114, 128, 0.1);
    color: #6b7280;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.chart-btn:hover,
.chart-btn.active {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.chart-content {
    height: 400px;
    position: relative;
}

/* Modern Cards */
.payment-methods-container,
.pending-container,
.activity-container,
.accounts-container {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    grid-column: span 6;
}

.payment-methods-container {
    grid-column: span 6;
}

.pending-container {
    grid-column: span 6;
}

.activity-container {
    grid-column: span 6;
}

.accounts-container {
    grid-column: span 6;
}

.section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
}

.section-header h3 {
    font-size: 1.125rem;
    font-weight: 600;
    color: #1a202c;
}

.badge {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.badge.urgent {
    background: linear-gradient(135deg, #f59e0b, #d97706);
}

.view-all-btn,
.add-account-btn {
    background: none;
    border: none;
    color: #6b7280;
    font-size: 0.875rem;
    cursor: pointer;
    font-weight: 500;
    transition: color 0.2s ease;
}

.view-all-btn:hover,
.add-account-btn:hover {
    color: #4c51bf;
}

.add-account-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Payment Methods Grid */
.payment-methods-grid {
    display: grid;
    gap: 1rem;
}

.payment-method-card {
    background: rgba(255, 255, 255, 0.7);
    border-radius: 12px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: all 0.2s ease;
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.payment-method-card:hover {
    background: rgba(255, 255, 255, 0.9);
    transform: translateY(-2px);
}

.method-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.method-details {
    flex: 1;
}

.method-details h4 {
    font-size: 1rem;
    font-weight: 600;
    color: #1a202c;
    margin-bottom: 0.25rem;
}

.method-details p {
    font-size: 0.875rem;
    color: #6b7280;
}

.method-amount {
    font-size: 1.125rem;
    font-weight: 700;
    color: #10b981;
}

/* Pending Items */
.pending-list {
    display: grid;
    gap: 1rem;
}

.pending-item {
    background: rgba(255, 255, 255, 0.7);
    border-radius: 12px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: all 0.2s ease;
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.pending-item:hover {
    background: rgba(255, 255, 255, 0.9);
    transform: translateY(-2px);
}

.pending-info {
    flex: 1;
}

.pending-info h4 {
    font-size: 1rem;
    font-weight: 600;
    color: #1a202c;
    margin-bottom: 0.25rem;
}

.pending-info p {
    font-size: 0.875rem;
    color: #6b7280;
    margin-bottom: 0.75rem;
}

.progress-bar {
    width: 100%;
    height: 4px;
    background: rgba(0, 0, 0, 0.1);
    border-radius: 2px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(135deg, #10b981, #059669);
    transition: width 0.3s ease;
}

.pending-amount {
    text-align: right;
}

.pending-amount .amount {
    display: block;
    font-size: 1.125rem;
    font-weight: 700;
    color: #f59e0b;
    margin-bottom: 0.25rem;
}

.pending-amount .label {
    font-size: 0.75rem;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

/* Activity Timeline */
.activity-timeline {
    display: grid;
    gap: 1rem;
}

.activity-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.5);
    transition: all 0.2s ease;
}

.activity-item:hover {
    background: rgba(255, 255, 255, 0.8);
    transform: translateY(-2px);
}

.activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
}

.activity-content {
    flex: 1;
}

.activity-content p {
    font-size: 0.875rem;
    font-weight: 500;
    color: #1a202c;
    margin-bottom: 0.25rem;
}

.activity-time {
    font-size: 0.75rem;
    color: #6b7280;
}

/* Accounts Grid */
.accounts-grid {
    display: grid;
    gap: 1rem;
}

.account-card {
    background: rgba(255, 255, 255, 0.7);
    border-radius: 12px;
    padding: 1.5rem;
    transition: all 0.2s ease;
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.account-card:hover {
    background: rgba(255, 255, 255, 0.9);
    transform: translateY(-2px);
}

.account-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.account-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.account-info h4 {
    font-size: 1rem;
    font-weight: 600;
    color: #1a202c;
    margin-bottom: 0.25rem;
}

.account-info p {
    font-size: 0.875rem;
    color: #6b7280;
}

.account-balance {
    text-align: right;
}

.balance-amount {
    display: block;
    font-size: 1.25rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
}

.balance-amount.positive {
    color: #10b981;
}

.balance-amount.negative {
    color: #ef4444;
}

.account-number {
    font-size: 0.75rem;
    color: #6b7280;
    font-family: monospace;
}

/* Empty States */
.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    color: #6b7280;
}

.empty-state i {
    font-size: 3rem;
    opacity: 0.5;
    margin-bottom: 1rem;
}

.empty-state p {
    font-size: 1rem;
    margin-bottom: 1rem;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    font-weight: 500;
    cursor: pointer;
    transition: transform 0.2s ease;
}

.btn-primary:hover {
    transform: translateY(-2px);
}

/* Loading Overlay */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(10px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

.loading-spinner {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 20px;
    padding: 2rem;
    text-align: center;
}

.spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #667eea;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 1rem auto;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Responsive Design */
@media (max-width: 1024px) {
    .content-grid {
        grid-template-columns: 1fr;
    }
    
    .payment-methods-container,
    .pending-container,
    .activity-container,
    .accounts-container {
        grid-column: span 1;
    }
}

@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        width: 100%;
    }
    
    .sidebar.open {
        transform: translateX(0);
    }
    
    .main-content {
        margin-left: 0;
        width: 100%;
    }
    
    .sidebar-toggle {
        display: block;
    }
    
    .top-bar {
        flex-direction: column;
        align-items: stretch;
    }
    
    .period-selector {
        flex-direction: column;
        gap: 1rem;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .period-tabs {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.25rem;
    }
}

@media (max-width: 480px) {
    .dashboard-content {
        padding: 1rem;
    }
    
    .stat-card {
        padding: 1.5rem;
    }
    
    .stat-value {
        font-size: 1.5rem;
    }
    
    .chart-container,
    .payment-methods-container,
    .pending-container,
    .activity-container,
    .accounts-container {
        padding: 1.5rem;
    }
}
</style> 