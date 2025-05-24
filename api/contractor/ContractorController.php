<?php
require_once '../../config.php';

header('Content-Type: application/json');

$pdo = getConnection();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'getAllContractors':
        getAllContractors();
        break;
    case 'getContractorById':
        getContractorById($_GET['id'] ?? '');
        break;
    case 'createContractor':
        createContractor();
        break;
    case 'updateContractor':
        updateContractor($_GET['id'] ?? '');
        break;
    case 'deleteContractor':
        deleteContractor($_GET['id'] ?? '');
        break;
    default:
        echo json_encode(['error' => 'Acción no válida']);
}

function getAllContractors() {
    global $pdo;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 0;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $sort = $_GET['sort'] ?? 'created_at';
    $dir = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
    $allowedSort = ['name', 'email', 'phone', 'created_at', 'updated_at', 'id'];
    if (!in_array($sort, $allowedSort)) $sort = 'created_at';
    $sql = "SELECT * FROM contractors ORDER BY $sort $dir, id DESC";
    if ($limit > 0) {
        $sql .= " LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        $stmt = $pdo->query($sql);
    }
    $contractors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total = $pdo->query("SELECT COUNT(*) FROM contractors")->fetchColumn();
    echo json_encode(['data' => $contractors, 'total' => (int)$total]);
}

function getContractorById($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM contractors WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
}

function createContractor() {
    global $pdo;
    $data = json_decode(file_get_contents("php://input"), true);
    $uuid = uniqid('', true);
    $stmt = $pdo->prepare("INSERT INTO contractors (id, name, email, phone, address, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
    $stmt->execute([
        $uuid,
        $data['name'],
        $data['email'] ?? null,
        $data['phone'] ?? null,
        $data['address'] ?? null
    ]);
    echo json_encode(["message" => "Contractor created", "id" => $uuid]);
}

function updateContractor($id) {
    global $pdo;
    $data = json_decode(file_get_contents("php://input"), true);
    $stmt = $pdo->prepare("UPDATE contractors SET name = ?, email = ?, phone = ?, address = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([
        $data['name'],
        $data['email'] ?? null,
        $data['phone'] ?? null,
        $data['address'] ?? null,
        $id
    ]);
    echo json_encode(["message" => "Contractor updated"]);
}

function deleteContractor($id) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM contractors WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(["message" => "Contractor deleted"]);
}
