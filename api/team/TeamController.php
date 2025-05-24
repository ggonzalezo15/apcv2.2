<?php
require_once '../../config.php';

header('Content-Type: application/json');

// Conexión PDO
$pdo = getConnection();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'getAllTeams':
        getAllTeams();
        break;
    case 'getTeamById':
        getTeamById($_GET['id'] ?? '');
        break;
    case 'createTeam':
        createTeam();
        break;
    case 'updateTeam':
        updateTeam($_GET['id'] ?? '');
        break;
    case 'deleteTeam':
        deleteTeam($_GET['id'] ?? '');
        break;
    default:
        echo json_encode(['error' => 'Acción no válida']);
}

function getAllTeams() {
    global $pdo;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 0;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $sort = $_GET['sort'] ?? 'created_at';
    $dir = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
    $allowedSort = ['name', 'description', 'created_at', 'updated_at', 'id'];
    if (!in_array($sort, $allowedSort)) $sort = 'created_at';
    $sql = "SELECT * FROM teams ORDER BY $sort $dir, id DESC";
    if ($limit > 0) {
        $sql .= " LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        $stmt = $pdo->query($sql);
    }
    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Obtener el total de equipos para la paginación
    $total = $pdo->query("SELECT COUNT(*) FROM teams")->fetchColumn();
    echo json_encode(['data' => $teams, 'total' => (int)$total]);
}

function getTeamById($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM teams WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
}

function createTeam() {
    global $pdo;
    $data = json_decode(file_get_contents("php://input"), true);
    $uuid = uniqid('', true);
    $stmt = $pdo->prepare("INSERT INTO teams (id, name, description, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
    $stmt->execute([$uuid, $data['name'], $data['description'] ?? '']);
    echo json_encode(["message" => "Team created", "id" => $uuid]);
}

function updateTeam($id) {
    global $pdo;
    $data = json_decode(file_get_contents("php://input"), true);
    $stmt = $pdo->prepare("UPDATE teams SET name = ?, description = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$data['name'], $data['description'] ?? '', $id]);
    echo json_encode(["message" => "Team updated"]);
}

function deleteTeam($id) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM teams WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(["message" => "Team deleted"]);
}
