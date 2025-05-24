<?php
require_once '../../config.php';

header('Content-Type: application/json');

$pdo = getConnection();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'getAllVendors':
        getAllVendors();
        break;
    case 'getVendorById':
        getVendorById($_GET['id'] ?? '');
        break;
    case 'createVendor':
        createVendor();
        break;
    case 'updateVendor':
        updateVendor($_GET['id'] ?? '');
        break;
    case 'deleteVendor':
        deleteVendor($_GET['id'] ?? '');
        break;
    default:
        echo json_encode(['error' => 'Acción no válida']);
}

function getAllVendors() {
    global $pdo;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 0;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $sort = $_GET['sort'] ?? 'created_at';
    $dir = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
    $allowedSort = ['name', 'email', 'phone', 'tax_id', 'created_at', 'updated_at', 'id'];
    if (!in_array($sort, $allowedSort)) $sort = 'created_at';
    $sql = "SELECT * FROM vendors ORDER BY $sort $dir, id DESC";
    if ($limit > 0) {
        $sql .= " LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        $stmt = $pdo->query($sql);
    }
    $vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total = $pdo->query("SELECT COUNT(*) FROM vendors")->fetchColumn();
    echo json_encode(['data' => $vendors, 'total' => (int)$total]);
}

function getVendorById($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM vendors WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
}

function createVendor() {
    global $pdo;
    $data = json_decode(file_get_contents("php://input"), true);
    $uuid = uniqid('', true);
    $stmt = $pdo->prepare("INSERT INTO vendors (id, name, email, phone, address, tax_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
    $stmt->execute([
        $uuid,
        $data['name'],
        $data['email'] ?? null,
        $data['phone'] ?? null,
        $data['address'] ?? null,
        $data['tax_id'] ?? null
    ]);
    echo json_encode(["message" => "Vendor created", "id" => $uuid]);
}

function updateVendor($id) {
    global $pdo;
    $data = json_decode(file_get_contents("php://input"), true);
    $stmt = $pdo->prepare("UPDATE vendors SET name = ?, email = ?, phone = ?, address = ?, tax_id = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([
        $data['name'],
        $data['email'] ?? null,
        $data['phone'] ?? null,
        $data['address'] ?? null,
        $data['tax_id'] ?? null,
        $id
    ]);
    echo json_encode(["message" => "Vendor updated"]);
}

function deleteVendor($id) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM vendors WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(["message" => "Vendor deleted"]);
}
