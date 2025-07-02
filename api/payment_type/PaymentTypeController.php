<?php
// Controlador para payment_types CRUD
require_once '../../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$pdo = getConnection();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Manejar acción específica para toggle de status
if ($method === 'POST' && $action === 'togglePaymentTypeStatus') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!verifyCSRFToken($data['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'CSRF inválido']);
        exit;
    }
    
    $id = $data['id'] ?? '';
    if ($id === '') {
        http_response_code(400);
        echo json_encode(['error' => 'ID requerido']);
        exit;
    }
    
    // Obtener el estado actual
    $stmt = $pdo->prepare('SELECT status FROM payment_types WHERE id = ?');
    $stmt->execute([$id]);
    $currentStatus = $stmt->fetchColumn();
    
    if ($currentStatus === false) {
        http_response_code(404);
        echo json_encode(['error' => 'Tipo de pago no encontrado']);
        exit;
    }
    
    // Cambiar el estado
    $newStatus = ($currentStatus === 'active') ? 'inactive' : 'active';
    $stmt = $pdo->prepare('UPDATE payment_types SET status = ? WHERE id = ?');
    $stmt->execute([$newStatus, $id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Estado actualizado correctamente',
        'new_status' => $newStatus,
        'status_numeric' => ($newStatus === 'active') ? 1 : 0
    ]);
    exit;
}

switch ($method) {
    case 'GET':
        // Listar todos con paginación, búsqueda y filtro de estado
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 0;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        $search = trim($_GET['search'] ?? '');
        $status_filter = $_GET['status'] ?? '';
        
        $where = [];
        $params = [];
        
        if ($search !== '') {
            $where[] = 'name LIKE ?';
            $params[] = "%$search%";
        }
        
        if ($status_filter !== '') {
            $where[] = 'status = ?';
            $params[] = $status_filter;
        }
        
        $whereClause = '';
        if (!empty($where)) {
            $whereClause = 'WHERE ' . implode(' AND ', $where);
        }
        
        $sql = 'SELECT *, (CASE WHEN status = "active" THEN 1 ELSE 0 END) as status_numeric FROM payment_types ' . $whereClause . ' ORDER BY name';
        if ($limit > 0) {
            $sql .= ' LIMIT ? OFFSET ?';
            $params[] = $limit;
            $params[] = $offset;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $total = 0;
        if ($whereClause) {
            $countParams = array_slice($params, 0, count($params) - ($limit > 0 ? 2 : 0));
            $stmt2 = $pdo->prepare('SELECT COUNT(*) FROM payment_types ' . $whereClause);
            $stmt2->execute($countParams);
            $total = (int)$stmt2->fetchColumn();
        } else {
            $total = (int)$pdo->query('SELECT COUNT(*) FROM payment_types')->fetchColumn();
        }
        
        // Listar cuentas bancarias válidas para el select (solo activas)
        $stmt3 = $pdo->query("SELECT id, name, bank_name, account_number, active FROM bank_accounts WHERE account_type != 'credito' ORDER BY name");
        $bankAccounts = $stmt3->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['data' => $data, 'total' => $total, 'bank_accounts' => $bankAccounts]);
        break;
    case 'POST':
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            if (!verifyCSRFToken($data['csrf_token'] ?? '')) {
                throw new Exception('CSRF inválido');
            }
            
            $name = trim($data['name'] ?? '');
            $description = trim($data['description'] ?? '');
            $bank_account_id = $data['bank_account_id'] ?? '';
            $status = isset($data['status']) ? ($data['status'] ? 'active' : 'inactive') : 'active';
            
            if ($name === '' || $bank_account_id === '') {
                throw new Exception('Nombre y cuenta bancaria requeridos');
            }
            
            // Verificar si ya existe un tipo de pago con el mismo nombre
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM payment_types WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))");
            $checkStmt->execute([$name]);
            $count = $checkStmt->fetchColumn();
            
            if ($count > 0) {
                throw new Exception('El nombre de tipo de pago ya existe');
            }
            
            // Validar que la cuenta existe y es tipo checking/business
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM bank_accounts WHERE id=? AND account_type != 'credito'");
            $stmt->execute([$bank_account_id]);
            if ($stmt->fetchColumn() == 0) {
                throw new Exception('Cuenta bancaria inválida');
            }
            
            $id = bin2hex(random_bytes(16));
            $stmt = $pdo->prepare('INSERT INTO payment_types (id, name, description, bank_account_id, status) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$id, $name, $description, $bank_account_id, $status]);
            
            echo json_encode(['success' => true, 'id' => $id, 'message' => 'Tipo de pago creado correctamente']);
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;
    case 'PUT':
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            if (!verifyCSRFToken($data['csrf_token'] ?? '')) {
                throw new Exception('CSRF inválido');
            }
            
            $id = $data['id'] ?? '';
            $name = trim($data['name'] ?? '');
            $description = trim($data['description'] ?? '');
            $bank_account_id = $data['bank_account_id'] ?? '';
            $status = isset($data['status']) ? ($data['status'] ? 'active' : 'inactive') : 'active';
            
            if ($id === '' || $name === '' || $bank_account_id === '') {
                throw new Exception('Datos requeridos');
            }
            
            // Verificar si ya existe otro tipo de pago con el mismo nombre (excluyendo el actual)
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM payment_types WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) AND id != ?");
            $checkStmt->execute([$name, $id]);
            $count = $checkStmt->fetchColumn();
            
            if ($count > 0) {
                throw new Exception('El nombre de tipo de pago ya existe');
            }
            
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM bank_accounts WHERE id=? AND account_type != 'credito'");
            $stmt->execute([$bank_account_id]);
            if ($stmt->fetchColumn() == 0) {
                throw new Exception('Cuenta bancaria inválida');
            }
            
            $stmt = $pdo->prepare('UPDATE payment_types SET name=?, description=?, bank_account_id=?, status=? WHERE id=?');
            $stmt->execute([$name, $description, $bank_account_id, $status, $id]);
            
            echo json_encode(['success' => true, 'message' => 'Tipo de pago actualizado correctamente']);
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;
    case 'DELETE':
        parse_str(file_get_contents('php://input'), $data);
        if (!verifyCSRFToken($data['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['error' => 'CSRF inválido']);
            exit;
        }
        $id = $data['id'] ?? '';
        if ($id === '') {
            http_response_code(400);
            echo json_encode(['error' => 'ID requerido']);
            exit;
        }
        $stmt = $pdo->prepare('DELETE FROM payment_types WHERE id=?');
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido']);
}
