<?php
require_once '../../config.php';

// Verificar autenticación
checkAPIAuthentication();

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
    case 'toggleVendorStatus':
        toggleVendorStatus($_GET['id'] ?? '');
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
    $allowedSort = ['name', 'email', 'phone', 'created_at', 'updated_at', 'id', 'status'];
    if (!in_array($sort, $allowedSort)) $sort = 'created_at';
    
    $sql = "SELECT * FROM vendors";
    
    // Agregar filtro de estado si se especifica
    $statusFilter = $_GET['status'] ?? '';
    if ($statusFilter !== '') {
        $statusValue = $statusFilter === '1' ? 'active' : 'inactive';
        $sql .= " WHERE status = '$statusValue'";
    }
    
    $sql .= " ORDER BY $sort $dir, id DESC";
    
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
    
    // Convertir status ENUM a formato numérico para compatibilidad con frontend
    foreach ($vendors as &$vendor) {
        $vendor['status'] = $vendor['status'] === 'active' ? 1 : 0;
    }
    
    $total = $pdo->query("SELECT COUNT(*) FROM vendors")->fetchColumn();
    echo json_encode(['data' => $vendors, 'total' => (int)$total]);
}

function getVendorById($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM vendors WHERE id = ?");
    $stmt->execute([$id]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($vendor) {
        // Convertir status ENUM a formato numérico
        $vendor['status'] = $vendor['status'] === 'active' ? 1 : 0;
    }
    
    echo json_encode($vendor);
}

function createVendor() {
    global $pdo;
    $data = json_decode(file_get_contents("php://input"), true);
    
    try {
        // Validar que el nombre no esté vacío
        if (empty(trim($data['name']))) {
            echo json_encode(['error' => 'El nombre del proveedor es obligatorio']);
            return;
        }
        
        // Verificar si ya existe un proveedor con el mismo nombre
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM vendors WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))");
        $checkStmt->execute([$data['name']]);
        $count = $checkStmt->fetchColumn();
        
        if ($count > 0) {
            echo json_encode(['error' => 'El nombre de proveedor ya existe']);
            return;
        }
        
        $uuid = uniqid('', true);
        
        // Convertir status numérico a ENUM
        $status = isset($data['status']) && $data['status'] == 0 ? 'inactive' : 'active';
        
        $stmt = $pdo->prepare("INSERT INTO vendors (id, name, email, phone, address, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([
            $uuid,
            $data['name'],
            $data['email'] ?? null,
            $data['phone'] ?? null,
            $data['address'] ?? null,
            $status
        ]);
        echo json_encode(["message" => "Vendor created", "id" => $uuid]);
        
    } catch (Exception $e) {
        echo json_encode(['error' => 'Error al crear el proveedor: ' . $e->getMessage()]);
    }
}

function updateVendor($id) {
    global $pdo;
    $data = json_decode(file_get_contents("php://input"), true);
    
    try {
        // Validar que el nombre no esté vacío
        if (empty(trim($data['name']))) {
            echo json_encode(['error' => 'El nombre del proveedor es obligatorio']);
            return;
        }
        
        // Verificar si ya existe otro proveedor con el mismo nombre (excluyendo el actual)
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM vendors WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) AND id != ?");
        $checkStmt->execute([$data['name'], $id]);
        $count = $checkStmt->fetchColumn();
        
        if ($count > 0) {
            echo json_encode(['error' => 'El nombre de proveedor ya existe']);
            return;
        }
        
        // Convertir status numérico a ENUM
        $status = isset($data['status']) && $data['status'] == 0 ? 'inactive' : 'active';
        
        $stmt = $pdo->prepare("UPDATE vendors SET name = ?, email = ?, phone = ?, address = ?, status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([
            $data['name'],
            $data['email'] ?? null,
            $data['phone'] ?? null,
            $data['address'] ?? null,
            $status,
            $id
        ]);
        echo json_encode(["message" => "Vendor updated"]);
        
    } catch (Exception $e) {
        echo json_encode(['error' => 'Error al actualizar el proveedor: ' . $e->getMessage()]);
    }
}

function toggleVendorStatus($id) {
    global $pdo;
    
    try {
        $data = json_decode(file_get_contents("php://input"), true);
        
        // Convertir status numérico a ENUM
        $status = isset($data['status']) && $data['status'] == 1 ? 'active' : 'inactive';
        
        // Actualizar el status del proveedor
        $stmt = $pdo->prepare("UPDATE vendors SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $id]);
        
        echo json_encode(["message" => "Vendor status updated"]);
        
    } catch (Exception $e) {
        echo json_encode(["error" => "Error interno del servidor", "message" => $e->getMessage()]);
    }
}

function deleteVendor($id) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM vendors WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(["message" => "Vendor deleted"]);
}
