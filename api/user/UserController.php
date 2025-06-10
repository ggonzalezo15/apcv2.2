<?php
// Configurar manejo de errores
error_reporting(0);
ini_set('display_errors', 0);

// Buffer de salida para capturar cualquier output no deseado
ob_start();

require_once dirname(__DIR__, 2) . '/config.php';

// Limpiar buffer
ob_clean();

header('Content-Type: application/json');

$pdo = getConnection();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'getAllUsers':
        getAllUsers();
        break;
    case 'getUserById':
        getUserById($_GET['id'] ?? '');
        break;
    case 'createUser':
        createUser();
        break;
    case 'updateUser':
        updateUser($_GET['id'] ?? '');
        break;
    case 'deleteUser':
        deleteUser($_GET['id'] ?? '');
        break;
    case 'updatePassword':
        updatePassword($_GET['id'] ?? '');
        break;
    case 'toggleUserStatus':
        toggleUserStatus($_GET['id'] ?? '');
        break;
    case 'updateProfile':
        updateProfile();
        break;
    default:
        echo json_encode(['error' => 'Acción no válida']);
}

function getAllUsers() {
    global $pdo;
    
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 0;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $sort = $_GET['sort'] ?? 'created_at';
    $dir = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
    
    $allowedSort = ['id', 'username', 'email', 'role', 'active', 'created_at', 'last_login'];
    if (!in_array($sort, $allowedSort)) $sort = 'created_at';
    
    $sql = "SELECT id, username, email, role, active, created_at, updated_at, last_login FROM users ORDER BY $sort $dir";
    
    if ($limit > 0) {
        $sql .= " LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        $stmt = $pdo->query($sql);
    }
    
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    
    echo json_encode(['data' => $users, 'total' => (int)$total]);
}

function getUserById($id) {
    global $pdo;
    
    if (empty($id)) {
        echo json_encode(['error' => 'ID de usuario requerido']);
        return;
    }
    
    $stmt = $pdo->prepare("SELECT id, username, email, role, active, created_at, updated_at, last_login FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['error' => 'Usuario no encontrado']);
        return;
    }
    
    echo json_encode($user);
}

function createUser() {
    global $pdo;
    
    try {
        $data = json_decode(file_get_contents("php://input"), true);
        
        // Validaciones
        if (empty($data['username'])) {
            echo json_encode(['error' => 'El nombre de usuario es requerido']);
            return;
        }
        
        if (empty($data['email'])) {
            echo json_encode(['error' => 'El email es requerido']);
            return;
        }
        
        if (empty($data['password'])) {
            echo json_encode(['error' => 'La contraseña es requerida']);
            return;
        }
        
        if (strlen($data['password']) < 6) {
            echo json_encode(['error' => 'La contraseña debe tener al menos 6 caracteres']);
            return;
        }
        
        // Verificar si el username ya existe
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$data['username']]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['error' => 'El nombre de usuario ya existe']);
            return;
        }
        
        // Verificar si el email ya existe
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['error' => 'El email ya está registrado']);
            return;
        }
        
        // Crear usuario
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        $role = $data['role'] ?? 'user';
        $active = isset($data['active']) ? (bool)$data['active'] : true;
        
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([
            $data['username'],
            $data['email'],
            $hashedPassword,
            $role,
            $active
        ]);
        
        $userId = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Usuario creado exitosamente',
            'id' => $userId
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['error' => 'Error al crear usuario: ' . $e->getMessage()]);
    }
}

function updateUser($id) {
    global $pdo;
    
    try {
        if (empty($id)) {
            echo json_encode(['error' => 'ID de usuario requerido']);
            return;
        }
        
        $data = json_decode(file_get_contents("php://input"), true);
        
        // Verificar que el usuario existe
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode(['error' => 'Usuario no encontrado']);
            return;
        }
        
        // Validaciones
        if (empty($data['username'])) {
            echo json_encode(['error' => 'El nombre de usuario es requerido']);
            return;
        }
        
        if (empty($data['email'])) {
            echo json_encode(['error' => 'El email es requerido']);
            return;
        }
        
        // Verificar si el username ya existe en otro usuario
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$data['username'], $id]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['error' => 'El nombre de usuario ya existe']);
            return;
        }
        
        // Verificar si el email ya existe en otro usuario
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$data['email'], $id]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['error' => 'El email ya está registrado']);
            return;
        }
        
        // Actualizar usuario
        $role = $data['role'] ?? $user['role'];
        $active = isset($data['active']) ? (bool)$data['active'] : $user['active'];
        
        $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, role = ?, active = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([
            $data['username'],
            $data['email'],
            $role,
            $active,
            $id
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Usuario actualizado exitosamente'
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['error' => 'Error al actualizar usuario: ' . $e->getMessage()]);
    }
}

function updatePassword($id) {
    global $pdo;
    
    try {
        if (empty($id)) {
            echo json_encode(['error' => 'ID de usuario requerido']);
            return;
        }
        
        $data = json_decode(file_get_contents("php://input"), true);
        
        // Verificar que el usuario existe
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode(['error' => 'Usuario no encontrado']);
            return;
        }
        
        // Validar nueva contraseña
        if (empty($data['new_password'])) {
            echo json_encode(['error' => 'La nueva contraseña es requerida']);
            return;
        }
        
        if (strlen($data['new_password']) < 6) {
            echo json_encode(['error' => 'La contraseña debe tener al menos 6 caracteres']);
            return;
        }
        
        // Si se proporciona contraseña actual, verificarla (para cambio por el mismo usuario)
        if (isset($data['current_password'])) {
            if (!password_verify($data['current_password'], $user['password'])) {
                echo json_encode(['error' => 'La contraseña actual es incorrecta']);
                return;
            }
        }
        
        // Actualizar contraseña
        $hashedPassword = password_hash($data['new_password'], PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$hashedPassword, $id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Contraseña actualizada exitosamente'
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['error' => 'Error al actualizar contraseña: ' . $e->getMessage()]);
    }
}

function toggleUserStatus($id) {
    global $pdo;
    
    try {
        if (empty($id)) {
            echo json_encode(['error' => 'ID de usuario requerido']);
            return;
        }
        
        // Verificar que el usuario existe
        $stmt = $pdo->prepare("SELECT active FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode(['error' => 'Usuario no encontrado']);
            return;
        }
        
        // Cambiar estado
        $newStatus = !$user['active'];
        
        $stmt = $pdo->prepare("UPDATE users SET active = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newStatus, $id]);
        
        $statusText = $newStatus ? 'activado' : 'desactivado';
        
        echo json_encode([
            'success' => true,
            'message' => "Usuario $statusText exitosamente",
            'active' => $newStatus
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['error' => 'Error al cambiar estado: ' . $e->getMessage()]);
    }
}

function updateProfile() {
    global $pdo;
    
    try {
        // Verificar que el usuario esté logueado
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['error' => 'Usuario no autenticado']);
            return;
        }
        
        $userId = $_SESSION['user_id'];
        $data = json_decode(file_get_contents("php://input"), true);
        
        // Validaciones básicas
        if (empty($data['email'])) {
            echo json_encode(['error' => 'El email es requerido']);
            return;
        }
        
        // Verificar si el email ya existe en otro usuario
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$data['email'], $userId]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['error' => 'El email ya está registrado']);
            return;
        }
        
        // Actualizar perfil
        $stmt = $pdo->prepare("UPDATE users SET email = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$data['email'], $userId]);
        
        // Actualizar sesión
        $_SESSION['email'] = $data['email'];
        
        echo json_encode([
            'success' => true,
            'message' => 'Perfil actualizado exitosamente'
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['error' => 'Error al actualizar perfil: ' . $e->getMessage()]);
    }
}

function deleteUser($id) {
    global $pdo;
    
    try {
        if (empty($id)) {
            echo json_encode(['error' => 'ID de usuario requerido']);
            return;
        }
        
        // Verificar que el usuario existe
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode(['error' => 'Usuario no encontrado']);
            return;
        }
        
        // Verificar que no sea el usuario actual
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $id) {
            echo json_encode(['error' => 'No puedes eliminar tu propia cuenta']);
            return;
        }
        
        // Verificar que no sea el último admin
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1");
        $stmt->execute();
        $adminCount = $stmt->fetchColumn();
        
        if ($adminCount <= 1) {
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $userRole = $stmt->fetchColumn();
            
            if ($userRole === 'admin') {
                echo json_encode(['error' => 'No se puede eliminar el último administrador del sistema']);
                return;
            }
        }
        
        // Eliminar usuario (las sesiones se eliminarán automáticamente por CASCADE)
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Usuario eliminado exitosamente'
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['error' => 'Error al eliminar usuario: ' . $e->getMessage()]);
    }
}
?> 
