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

switch ($method) {
    case 'GET':
        // Listar todos con paginación y búsqueda
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 0;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        $search = trim($_GET['search'] ?? '');
        $where = '';
        $params = [];
        if ($search !== '') {
            $where = 'WHERE name LIKE ?';
            $params[] = "%$search%";
        }
        $sql = 'SELECT * FROM payment_types ' . $where . ' ORDER BY name';
        if ($limit > 0) {
            $sql .= ' LIMIT ? OFFSET ?';
            $params[] = $limit;
            $params[] = $offset;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total = 0;
        if ($where) {
            $stmt2 = $pdo->prepare('SELECT COUNT(*) FROM payment_types ' . $where);
            $stmt2->execute(array_slice($params, 0, count($params) - 2));
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
        $data = json_decode(file_get_contents('php://input'), true);
        if (!verifyCSRFToken($data['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['error' => 'CSRF inválido']);
            exit;
        }
        $name = trim($data['name'] ?? '');
        $description = trim($data['description'] ?? '');
        $bank_account_id = $data['bank_account_id'] ?? '';
        if ($name === '' || $bank_account_id === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Nombre y cuenta bancaria requeridos']);
            exit;
        }
        // Validar que la cuenta existe y es tipo checking/business
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bank_accounts WHERE id=? AND account_type != 'credito'");
        $stmt->execute([$bank_account_id]);
        if ($stmt->fetchColumn() == 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Cuenta bancaria inválida']);
            exit;
        }
        $id = bin2hex(random_bytes(16));
        $stmt = $pdo->prepare('INSERT INTO payment_types (id, name, description, bank_account_id) VALUES (?, ?, ?, ?)');
        $stmt->execute([$id, $name, $description, $bank_account_id]);
        echo json_encode(['success' => true, 'id' => $id]);
        break;
    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!verifyCSRFToken($data['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['error' => 'CSRF inválido']);
            exit;
        }
        $id = $data['id'] ?? '';
        $name = trim($data['name'] ?? '');
        $description = trim($data['description'] ?? '');
        $bank_account_id = $data['bank_account_id'] ?? '';
        if ($id === '' || $name === '' || $bank_account_id === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Datos requeridos']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bank_accounts WHERE id=? AND account_type != 'credito'");
        $stmt->execute([$bank_account_id]);
        if ($stmt->fetchColumn() == 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Cuenta bancaria inválida']);
            exit;
        }
        $stmt = $pdo->prepare('UPDATE payment_types SET name=?, description=?, bank_account_id=? WHERE id=?');
        $stmt->execute([$name, $description, $bank_account_id, $id]);
        echo json_encode(['success' => true]);
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
