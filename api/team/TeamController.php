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
    case 'toggleTeamStatus':
        toggleTeamStatus($_GET['id'] ?? '');
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
    $allowedSort = ['name', 'description', 'status', 'created_at', 'updated_at', 'id'];
    if (!in_array($sort, $allowedSort)) $sort = 'created_at';
    
    // Filtro de estado
    $statusFilter = $_GET['status'] ?? '';
    $whereClause = '';
    $params = [];
    
    if ($statusFilter !== '') {
        $statusValue = $statusFilter == '1' ? 'active' : 'inactive';
        $whereClause = " WHERE status = ?";
        $params[] = $statusValue;
    }
    
    $sql = "SELECT * FROM teams{$whereClause} ORDER BY $sort $dir, id DESC";
    if ($limit > 0) {
        $sql .= " LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $index => $param) {
            $stmt->bindValue($index + 1, $param);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }
    
    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convertir ENUM a numérico para compatibilidad con frontend
    foreach ($teams as &$team) {
        $team['status_numeric'] = $team['status'] === 'active' ? 1 : 0;
    }
    
    $totalSql = "SELECT COUNT(*) FROM teams{$whereClause}";
    $totalStmt = $pdo->prepare($totalSql);
    $totalStmt->execute($params);
    $total = $totalStmt->fetchColumn();
    
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
    
    // Convertir status numérico a ENUM
    $status = 'active'; // Por defecto activo
    if (isset($data['status'])) {
        $status = $data['status'] == 1 || $data['status'] === 'active' ? 'active' : 'inactive';
    }
    
    $stmt = $pdo->prepare("INSERT INTO teams (id, name, description, status, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
    $stmt->execute([$uuid, $data['name'], $data['description'] ?? '', $status]);
    echo json_encode(["message" => "Team created", "id" => $uuid]);
}

function updateTeam($id) {
    global $pdo;
    $data = json_decode(file_get_contents("php://input"), true);
    
    // Convertir status numérico a ENUM
    $status = 'active'; // Por defecto activo
    if (isset($data['status'])) {
        $status = $data['status'] == 1 || $data['status'] === 'active' ? 'active' : 'inactive';
    }
    
    $stmt = $pdo->prepare("UPDATE teams SET name = ?, description = ?, status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$data['name'], $data['description'] ?? '', $status, $id]);
    echo json_encode(["message" => "Team updated"]);
}

function deleteTeam($id) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM teams WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(["message" => "Team deleted"]);
}

function toggleTeamStatus($id) {
    global $pdo;
    
    try {
        // Obtener el estado actual
        $stmt = $pdo->prepare("SELECT status FROM teams WHERE id = ?");
        $stmt->execute([$id]);
        $team = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$team) {
            echo json_encode(['success' => false, 'error' => 'Equipo no encontrado']);
            return;
        }
        
        // Cambiar el estado
        $newStatus = $team['status'] === 'active' ? 'inactive' : 'active';
        
        $stmt = $pdo->prepare("UPDATE teams SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newStatus, $id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Estado del equipo actualizado',
            'new_status' => $newStatus,
            'new_status_numeric' => $newStatus === 'active' ? 1 : 0
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
