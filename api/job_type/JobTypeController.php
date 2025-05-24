<?php
require_once '../../config.php';

header('Content-Type: application/json');

$pdo = getConnection();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'getAllJobTypes':
        getAllJobTypes();
        break;
    case 'getJobTypeById':
        getJobTypeById($_GET['id'] ?? '');
        break;
    case 'createJobType':
        createJobType();
        break;
    case 'updateJobType':
        updateJobType($_GET['id'] ?? '');
        break;
    case 'deleteJobType':
        deleteJobType($_GET['id'] ?? '');
        break;
    default:
        echo json_encode(['error' => 'Acción no válida']);
}

function getAllJobTypes() {
    global $pdo;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 0;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $sort = $_GET['sort'] ?? 'created_at';
    $dir = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
    $allowedSort = ['name', 'pay_as_contractor', 'pay_as_sub_contractor', 'created_at', 'updated_at', 'id'];
    if (!in_array($sort, $allowedSort)) $sort = 'created_at';
    $sql = "SELECT * FROM job_types ORDER BY $sort $dir, id DESC";
    if ($limit > 0) {
        $sql .= " LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        $stmt = $pdo->query($sql);
    }
    $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total = $pdo->query("SELECT COUNT(*) FROM job_types")->fetchColumn();
    echo json_encode(['data' => $types, 'total' => (int)$total]);
}

function getJobTypeById($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM job_types WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
}

function createJobType() {
    global $pdo;
    $data = json_decode(file_get_contents("php://input"), true);
    $uuid = uniqid('', true);
    $stmt = $pdo->prepare("INSERT INTO job_types (id, name, pay_as_contractor, pay_as_sub_contractor, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
    $stmt->execute([
        $uuid,
        $data['name'],
        $data['pay_as_contractor'] ?? 0.00,
        $data['pay_as_sub_contractor'] ?? 0.00
    ]);
    echo json_encode(["message" => "Job type created", "id" => $uuid]);
}

function updateJobType($id) {
    global $pdo;
    $data = json_decode(file_get_contents("php://input"), true);
    $stmt = $pdo->prepare("UPDATE job_types SET name = ?, pay_as_contractor = ?, pay_as_sub_contractor = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([
        $data['name'],
        $data['pay_as_contractor'] ?? 0.00,
        $data['pay_as_sub_contractor'] ?? 0.00,
        $id
    ]);
    echo json_encode(["message" => "Job type updated"]);
}

function deleteJobType($id) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM job_types WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(["message" => "Job type deleted"]);
}
