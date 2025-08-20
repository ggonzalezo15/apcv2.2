<?php
require_once '../../config.php';

// Verificar autenticación
checkAPIAuthentication();

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
    case 'toggleContractorStatus':
        toggleContractorStatus($_GET['id'] ?? '');
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
    $allowedSort = ['name', 'email', 'phone', 'status', 'created_at', 'updated_at', 'id', 'type'];
    if (!in_array($sort, $allowedSort)) $sort = 'created_at';
    
    // Filtro de estado
    $statusFilter = $_GET['status'] ?? '';
    $typeFilter = $_GET['type'] ?? '';
    $whereClause = '';
    $params = [];
    
    if ($statusFilter !== '') {
        $statusValue = $statusFilter == '1' ? 'active' : 'inactive';
        $whereClause = " WHERE status = ?";
        $params[] = $statusValue;
    }
    if ($typeFilter !== '') {
        $whereClause .= ($whereClause ? ' AND' : ' WHERE') . " type = ?";
        $params[] = $typeFilter;
    }
    $sql = "SELECT * FROM contractors{$whereClause} ORDER BY $sort $dir, id DESC";
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
    $contractors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Convertir ENUM a numérico para compatibilidad con frontend
    foreach ($contractors as &$contractor) {
        $contractor['status_numeric'] = $contractor['status'] === 'active' ? 1 : 0;
    }
    $totalSql = "SELECT COUNT(*) FROM contractors{$whereClause}";
    $totalStmt = $pdo->prepare($totalSql);
    $totalStmt->execute($params);
    $total = $totalStmt->fetchColumn();
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
    
    try {
        // Validar que el nombre no esté vacío
        if (empty(trim($data['name']))) {
            echo json_encode(['error' => 'El nombre del contratista es obligatorio']);
            return;
        }
        
        // Verificar si ya existe un contratista con el mismo nombre
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM contractors WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))");
        $checkStmt->execute([$data['name']]);
        $count = $checkStmt->fetchColumn();
        
        if ($count > 0) {
            echo json_encode(['error' => 'El nombre de contratista ya existe']);
            return;
        }
        
        $uuid = uniqid('', true);
        
        // Convertir status numérico a ENUM
        $status = 'active'; // Por defecto activo
        if (isset($data['status'])) {
            $status = $data['status'] == 1 || $data['status'] === 'active' ? 'active' : 'inactive';
        }
        
        $stmt = $pdo->prepare("INSERT INTO contractors (id, name, email, phone, address, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([
            $uuid,
            $data['name'],
            $data['email'] ?? null,
            $data['phone'] ?? null,
            $data['address'] ?? null,
            $status
        ]);
        echo json_encode(["message" => "Contractor created", "id" => $uuid]);
        
    } catch (Exception $e) {
        echo json_encode(['error' => 'Error al crear el contratista: ' . $e->getMessage()]);
    }
}

function updateContractor($id) {
    global $pdo;
    $data = json_decode(file_get_contents("php://input"), true);
    
    try {
        // Validar que el nombre no esté vacío
        if (empty(trim($data['name']))) {
            echo json_encode(['error' => 'El nombre del contratista es obligatorio']);
            return;
        }
        
        // Verificar si ya existe otro contratista con el mismo nombre (excluyendo el actual)
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM contractors WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) AND id != ?");
        $checkStmt->execute([$data['name'], $id]);
        $count = $checkStmt->fetchColumn();
        
        if ($count > 0) {
            echo json_encode(['error' => 'El nombre de contratista ya existe']);
            return;
        }
        
        // Convertir status numérico a ENUM
        $status = 'active'; // Por defecto activo
        if (isset($data['status'])) {
            $status = $data['status'] == 1 || $data['status'] === 'active' ? 'active' : 'inactive';
        }
        
        $type = isset($data['type']) && in_array($data['type'], ['Tecnico','Administrativo']) ? $data['type'] : 'Tecnico';
        $stmt = $pdo->prepare("UPDATE contractors SET name = ?, email = ?, phone = ?, address = ?, status = ?, type = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([
            $data['name'],
            $data['email'] ?? null,
            $data['phone'] ?? null,
            $data['address'] ?? null,
            $status,
            $type,
            $id
        ]);
        echo json_encode(["message" => "Contractor updated"]);
        
    } catch (Exception $e) {
        echo json_encode(['error' => 'Error al actualizar el contratista: ' . $e->getMessage()]);
    }
}

function deleteContractor($id) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM contractors WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(["message" => "Contractor deleted"]);
}

function toggleContractorStatus($id) {
    global $pdo;
    
    try {
        // Obtener el estado actual
        $stmt = $pdo->prepare("SELECT status FROM contractors WHERE id = ?");
        $stmt->execute([$id]);
        $contractor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$contractor) {
            echo json_encode(['success' => false, 'error' => 'Contratista no encontrado']);
            return;
        }
        
        // Cambiar el estado
        $newStatus = $contractor['status'] === 'active' ? 'inactive' : 'active';
        
        $stmt = $pdo->prepare("UPDATE contractors SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newStatus, $id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Estado del contratista actualizado',
            'new_status' => $newStatus,
            'new_status_numeric' => $newStatus === 'active' ? 1 : 0
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
