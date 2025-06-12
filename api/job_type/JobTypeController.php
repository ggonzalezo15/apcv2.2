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
    case 'toggleStatus':
        toggleJobTypeStatus($_GET['id'] ?? '');
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
    $statusFilter = $_GET['status'] ?? 'all';
    
    $allowedSort = ['name', 'pay_as_contractor', 'pay_as_sub_contractor', 'created_at', 'updated_at', 'id', 'status'];
    if (!in_array($sort, $allowedSort)) $sort = 'created_at';
    
    // Construir consulta base con conversión de status
    $sql = "SELECT *, 
            CASE 
                WHEN status = 'active' THEN 1 
                ELSE 0 
            END as status_numeric 
            FROM job_types";
    
    // Agregar filtro de estado si es necesario
    $params = [];
    if ($statusFilter === 'active') {
        $sql .= " WHERE status = 'active'";
    } elseif ($statusFilter === 'inactive') {
        $sql .= " WHERE status = 'inactive'";
    }
    
    $sql .= " ORDER BY $sort $dir, id DESC";
    
    if ($limit > 0) {
        $sql .= " LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    }
    
    $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Contar total con el mismo filtro
    $countSql = "SELECT COUNT(*) FROM job_types";
    if ($statusFilter === 'active') {
        $countSql .= " WHERE status = 'active'";
    } elseif ($statusFilter === 'inactive') {
        $countSql .= " WHERE status = 'inactive'";
    }
    
    $total = $pdo->query($countSql)->fetchColumn();
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
    $status = isset($data['status']) ? ($data['status'] === '1' || $data['status'] === 'active' ? 'active' : 'inactive') : 'active';
    
    $stmt = $pdo->prepare("INSERT INTO job_types (id, name, pay_as_contractor, pay_as_sub_contractor, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
    $stmt->execute([
        $uuid,
        $data['name'],
        $data['pay_as_contractor'] ?? 0.00,
        $data['pay_as_sub_contractor'] ?? 0.00,
        $status
    ]);
    echo json_encode(["message" => "Job type created", "id" => $uuid]);
}

function updateJobType($id) {
    global $pdo;
    $data = json_decode(file_get_contents("php://input"), true);
    $status = isset($data['status']) ? ($data['status'] === '1' || $data['status'] === 'active' ? 'active' : 'inactive') : 'active';
    
    $stmt = $pdo->prepare("UPDATE job_types SET name = ?, pay_as_contractor = ?, pay_as_sub_contractor = ?, status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([
        $data['name'],
        $data['pay_as_contractor'] ?? 0.00,
        $data['pay_as_sub_contractor'] ?? 0.00,
        $status,
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

function toggleJobTypeStatus($id) {
    global $pdo;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['error' => 'Método no permitido']);
        return;
    }
    
    if (!$id) {
        echo json_encode(['error' => 'ID es requerido']);
        return;
    }
    
    try {
        // Obtener estado actual
        $stmt = $pdo->prepare("SELECT status FROM job_types WHERE id = ?");
        $stmt->execute([$id]);
        $currentStatus = $stmt->fetchColumn();
        
        if ($currentStatus === false) {
            echo json_encode(['error' => 'Tipo de trabajo no encontrado']);
            return;
        }
        
        // Cambiar estado
        $newStatus = $currentStatus === 'active' ? 'inactive' : 'active';
        
        $stmt = $pdo->prepare("UPDATE job_types SET status = ?, updated_at = NOW() WHERE id = ?");
        $result = $stmt->execute([$newStatus, $id]);
        
        if (!$result) {
            echo json_encode(['error' => 'No se pudo cambiar el estado del tipo de trabajo']);
            return;
        }
        
        $statusText = $newStatus === 'active' ? 'activado' : 'desactivado';
        
        echo json_encode([
            'success' => true,
            'message' => "Tipo de trabajo {$statusText} exitosamente",
            'new_status' => $newStatus
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}
