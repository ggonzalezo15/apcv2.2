<?php
// Iniciar output buffering y configurar manejo de errores
ob_start();
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

require_once '../../config.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    $pdo = getConnection();
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'list':
            // Obtener filtro de estado
            $statusFilter = $_GET['status'] ?? 'all';
            
            // Construir consulta base
            $sql = "
                SELECT 
                    et.*,
                    ec.name as category_name,
                    CASE 
                        WHEN et.status = 'active' THEN 1 
                        ELSE 0 
                    END as status_numeric
                FROM expense_types et
                LEFT JOIN expense_categories ec ON et.category_id = ec.id
            ";
            
            // Agregar filtro de estado si es necesario
            $params = [];
            if ($statusFilter === 'active') {
                $sql .= " WHERE et.status = 'active'";
            } elseif ($statusFilter === 'inactive') {
                $sql .= " WHERE et.status = 'inactive'";
            }
            
            $sql .= " ORDER BY et.created_at DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            ob_clean();
            echo json_encode([
                'success' => true,
                'data' => $types
            ]);
            break;

        case 'get':
            $id = $_GET['id'] ?? '';
            if (!$id) {
                throw new Exception('ID es requerido');
            }
            
            $stmt = $pdo->prepare("
                SELECT et.*, ec.name as category_name
                FROM expense_types et
                LEFT JOIN expense_categories ec ON et.category_id = ec.id
                WHERE et.id = ?
            ");
            $stmt->execute([$id]);
            $type = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$type) {
                throw new Exception('Tipo de gasto no encontrado');
            }
            
            ob_clean();
            echo json_encode([
                'success' => true,
                'data' => $type
            ]);
            break;

        case 'create':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método no permitido');
            }
            
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $category_id = trim($_POST['category_id'] ?? '');
            $status = isset($_POST['status']) ? ($_POST['status'] === '1' || $_POST['status'] === 'active' ? 'active' : 'inactive') : 'active';
            
            if (empty($name)) {
                throw new Exception('El nombre es obligatorio');
            }
            
            if (empty($category_id)) {
                throw new Exception('La categoría es obligatoria');
            }
            
            // Verificar que la categoría existe
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM expense_categories WHERE id = ?");
            $stmt->execute([$category_id]);
            if ($stmt->fetchColumn() == 0) {
                throw new Exception('La categoría seleccionada no existe');
            }
            
            // Verificar si ya existe un tipo con ese nombre (case-insensitive y trimmed)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM expense_types WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))");
            $stmt->execute([$name]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('El nombre de tipo de gasto ya existe');
            }
            
            // Generar ID único
            $id = uniqid('type_');
            
            // Insertar tipo
            $stmt = $pdo->prepare("
                INSERT INTO expense_types (id, name, description, category_id, status, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$id, $name, $description, $category_id, $status]);
            
            ob_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Tipo de gasto creado exitosamente',
                'data' => ['id' => $id]
            ]);
            break;

        case 'update':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método no permitido');
            }
            
            $id = $_GET['id'] ?? '';
            if (!$id) {
                throw new Exception('ID es requerido');
            }
            
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $category_id = trim($_POST['category_id'] ?? '');
            $status = isset($_POST['status']) ? ($_POST['status'] === '1' || $_POST['status'] === 'active' ? 'active' : 'inactive') : 'active';
            
            if (empty($name)) {
                throw new Exception('El nombre es obligatorio');
            }
            
            if (empty($category_id)) {
                throw new Exception('La categoría es obligatoria');
            }
            
            // Verificar que la categoría existe
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM expense_categories WHERE id = ?");
            $stmt->execute([$category_id]);
            if ($stmt->fetchColumn() == 0) {
                throw new Exception('La categoría seleccionada no existe');
            }
            
            // Verificar si ya existe otro tipo con ese nombre (case-insensitive y trimmed)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM expense_types WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) AND id != ?");
            $stmt->execute([$name, $id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('El nombre de tipo de gasto ya existe');
            }
            
            // Actualizar tipo
            $stmt = $pdo->prepare("
                UPDATE expense_types 
                SET name = ?, description = ?, category_id = ?, status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $result = $stmt->execute([$name, $description, $category_id, $status, $id]);
            
            if (!$result || $stmt->rowCount() === 0) {
                throw new Exception('No se pudo actualizar el tipo de gasto');
            }
            
            ob_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Tipo de gasto actualizado exitosamente'
            ]);
            break;

        case 'delete':
            if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
                throw new Exception('Método no permitido');
            }
            
            $id = $_GET['id'] ?? '';
            if (!$id) {
                throw new Exception('ID es requerido');
            }
            
            // Eliminar tipo
            $stmt = $pdo->prepare("DELETE FROM expense_types WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            if (!$result || $stmt->rowCount() === 0) {
                throw new Exception('No se pudo eliminar el tipo de gasto');
            }
            
            ob_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Tipo de gasto eliminado exitosamente'
            ]);
            break;

        case 'toggleStatus':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método no permitido');
            }
            
            $id = $_GET['id'] ?? '';
            if (!$id) {
                throw new Exception('ID es requerido');
            }
            
            // Obtener estado actual
            $stmt = $pdo->prepare("SELECT status FROM expense_types WHERE id = ?");
            $stmt->execute([$id]);
            $currentStatus = $stmt->fetchColumn();
            
            if ($currentStatus === false) {
                throw new Exception('Tipo de gasto no encontrado');
            }
            
            // Cambiar estado
            $newStatus = $currentStatus === 'active' ? 'inactive' : 'active';
            
            $stmt = $pdo->prepare("UPDATE expense_types SET status = ?, updated_at = NOW() WHERE id = ?");
            $result = $stmt->execute([$newStatus, $id]);
            
            if (!$result) {
                throw new Exception('No se pudo cambiar el estado del tipo de gasto');
            }
            
            $statusText = $newStatus === 'active' ? 'activado' : 'desactivado';
            
            ob_clean();
            echo json_encode([
                'success' => true,
                'message' => "Tipo de gasto {$statusText} exitosamente",
                'new_status' => $newStatus
            ]);
            break;

        case 'categories':
            // Obtener categorías activas para select
            $stmt = $pdo->prepare("SELECT id, name FROM expense_categories WHERE status = 'active' ORDER BY name ASC");
            $stmt->execute();
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            ob_clean();
            echo json_encode([
                'success' => true,
                'data' => $categories
            ]);
            break;

        default:
            throw new Exception('Acción no válida');
    }

} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}

ob_end_flush();
?> 
