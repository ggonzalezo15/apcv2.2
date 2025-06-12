<?php
require_once '../../config.php';

header('Content-Type: application/json');

$pdo = getConnection();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'getAllIncomes':
        getAllIncomes();
        break;
    case 'getIncomeById':
        getIncomeById($_GET['id'] ?? '');
        break;
    case 'getIncomeWithDetails':
        getIncomeWithDetails($_GET['id'] ?? '');
        break;
    case 'getIncomePayments':
        getIncomePayments($_GET['income_id'] ?? '');
        break;
    case 'getIncomeLines':
        getIncomeLines($_GET['income_id'] ?? '');
        break;
    case 'getTeams':
        getTeams();
        break;
    case 'getJobTypes':
        getJobTypes();
        break;
    case 'createTestIncome':
        createTestIncome();
        break;
    default:
        echo json_encode(['error' => 'Acción no válida']);
}

function getAllIncomes() {
    global $pdo;
    try {
        $stmt = $pdo->query("
            SELECT 
                i.*,
                t.name as team_name,
                jt.name as job_type_name,
                COUNT(DISTINCT ip.id) as payments_count,
                COUNT(DISTINCT il.id) as lines_count,
                COALESCE(SUM(ip.amount), 0) as total_paid
            FROM incomes i
            LEFT JOIN teams t ON i.team_id = t.id
            LEFT JOIN job_types jt ON i.job_type_id = jt.id
            LEFT JOIN income_payments ip ON i.id = ip.income_id
            LEFT JOIN income_lines il ON i.id = il.income_id
            GROUP BY i.id
            ORDER BY i.created_at DESC
        ");
        
        $incomes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calcular progreso de pagos
        foreach ($incomes as &$income) {
            $income['payment_progress'] = $income['total_amount'] > 0 
                ? round(($income['total_paid'] / $income['total_amount']) * 100, 2)
                : 0;
            $income['remaining_amount'] = $income['total_amount'] - $income['total_paid'];
        }
        
        echo json_encode([
            'success' => true,
            'data' => $incomes,
            'total' => count($incomes)
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getIncomeById($id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT 
                i.*,
                t.name as team_name,
                jt.name as job_type_name
            FROM incomes i
            LEFT JOIN teams t ON i.team_id = t.id
            LEFT JOIN job_types jt ON i.job_type_id = jt.id
            WHERE i.id = ?
        ");
        $stmt->execute([$id]);
        $income = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($income) {
            echo json_encode(['success' => true, 'data' => $income]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Ingreso no encontrado']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getIncomeWithDetails($id) {
    global $pdo;
    try {
        // Obtener información principal
        $stmt = $pdo->prepare("
            SELECT 
                i.*,
                t.name as team_name,
                jt.name as job_type_name
            FROM incomes i
            LEFT JOIN teams t ON i.team_id = t.id
            LEFT JOIN job_types jt ON i.job_type_id = jt.id
            WHERE i.id = ?
        ");
        $stmt->execute([$id]);
        $income = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$income) {
            echo json_encode(['success' => false, 'error' => 'Ingreso no encontrado']);
            return;
        }
        
        // Obtener pagos
        $stmt = $pdo->prepare("
            SELECT 
                ip.*,
                pt.name as payment_type_name,
                ba.name as bank_account_name,
                ba.bank_name
            FROM income_payments ip
            LEFT JOIN payment_types pt ON ip.payment_type_id = pt.id
            LEFT JOIN bank_accounts ba ON ip.bank_account_id = ba.id
            WHERE ip.income_id = ?
            ORDER BY ip.payment_date DESC
        ");
        $stmt->execute([$id]);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener líneas
        $stmt = $pdo->prepare("
            SELECT * FROM income_lines 
            WHERE income_id = ? 
            ORDER BY created_at ASC
        ");
        $stmt->execute([$id]);
        $lines = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calcular estadísticas
        $total_paid = array_sum(array_column($payments, 'amount'));
        $payment_progress = $income['total_amount'] > 0 
            ? round(($total_paid / $income['total_amount']) * 100, 2)
            : 0;
        
        echo json_encode([
            'success' => true,
            'data' => [
                'income' => $income,
                'payments' => $payments,
                'lines' => $lines,
                'statistics' => [
                    'total_paid' => $total_paid,
                    'remaining_amount' => $income['total_amount'] - $total_paid,
                    'payment_progress' => $payment_progress,
                    'payments_count' => count($payments),
                    'lines_count' => count($lines)
                ]
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getIncomePayments($incomeId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT 
                ip.*,
                pt.name as payment_type_name,
                ba.name as bank_account_name,
                ba.bank_name
            FROM income_payments ip
            LEFT JOIN payment_types pt ON ip.payment_type_id = pt.id
            LEFT JOIN bank_accounts ba ON ip.bank_account_id = ba.id
            WHERE ip.income_id = ?
            ORDER BY ip.payment_date DESC
        ");
        $stmt->execute([$incomeId]);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $payments]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getIncomeLines($incomeId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM income_lines 
            WHERE income_id = ? 
            ORDER BY created_at ASC
        ");
        $stmt->execute([$incomeId]);
        $lines = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $lines]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getTeams() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT id, name FROM teams WHERE status = 'active' ORDER BY name");
        $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $teams]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getJobTypes() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT id, name FROM job_types WHERE status = 'active' ORDER BY name");
        $jobTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $jobTypes]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function createTestIncome() {
    global $pdo;
    try {
        // Generar datos de prueba
        $testData = [
            'id' => generateUUID(),
            'team_id' => '550e8400-e29b-41d4-a716-446655440001', // Usar el que existe
            'job_type_id' => '550e8400-e29b-41d4-a716-446655440031', // Usar el que existe
            'name' => 'Proyecto de Prueba API - ' . date('Y-m-d H:i:s'),
            'description' => 'Proyecto creado desde la API para pruebas de funcionalidad',
            'total_amount' => 50000.00,
            'status' => 'pending',
            'start_date' => date('Y-m-d'),
            'end_date' => date('Y-m-d', strtotime('+3 months'))
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO incomes (id, team_id, job_type_id, name, description, total_amount, status, start_date, end_date, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $stmt->execute([
            $testData['id'],
            $testData['team_id'],
            $testData['job_type_id'],
            $testData['name'],
            $testData['description'],
            $testData['total_amount'],
            $testData['status'],
            $testData['start_date'],
            $testData['end_date']
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Proyecto de prueba creado exitosamente',
            'data' => $testData
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}
?> 
