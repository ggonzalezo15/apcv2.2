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
                    c.*,
                    COUNT(et.id) as types_count,
                    CASE 
                        WHEN c.status = 'active' THEN 1 
                        ELSE 0 
                    END as status_numeric
                FROM expense_categories c
                LEFT JOIN expense_types et ON c.id = et.category_id
            ";
            
            // Agregar filtro de estado si es necesario
            $params = [];
            if ($statusFilter === 'active') {
                $sql .= " WHERE c.status = 'active'";
            } elseif ($statusFilter === 'inactive') {
                $sql .= " WHERE c.status = 'inactive'";
            }
            
            $sql .= " GROUP BY c.id ORDER BY c.created_at DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            ob_clean();
            echo json_encode([
                'success' => true,
                'data' => $categories
            ]);
            break;

        case 'get':
            $id = $_GET['id'] ?? '';
            if (!$id) {
                throw new Exception('ID es requerido');
            }
            
            $stmt = $pdo->prepare("SELECT * FROM expense_categories WHERE id = ?");
            $stmt->execute([$id]);
            $category = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$category) {
                throw new Exception('Categoría no encontrada');
            }
            
            ob_clean();
            echo json_encode([
                'success' => true,
                'data' => $category
            ]);
            break;

        case 'create':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método no permitido');
            }
            
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $status = isset($_POST['status']) ? ($_POST['status'] === '1' || $_POST['status'] === 'active' ? 'active' : 'inactive') : 'active';
            
            if (empty($name)) {
                throw new Exception('El nombre es obligatorio');
            }
            
            // Verificar si ya existe una categoría con ese nombre
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM expense_categories WHERE name = ?");
            $stmt->execute([$name]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Ya existe una categoría con ese nombre');
            }
            
            // Generar ID único
            $id = uniqid('cat_');
            
            // Insertar categoría
            $stmt = $pdo->prepare("
                INSERT INTO expense_categories (id, name, description, status, created_at, updated_at) 
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$id, $name, $description, $status]);
            
            ob_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Categoría creada exitosamente',
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
            $status = isset($_POST['status']) ? ($_POST['status'] === '1' || $_POST['status'] === 'active' ? 'active' : 'inactive') : 'active';
            
            if (empty($name)) {
                throw new Exception('El nombre es obligatorio');
            }
            
            // Verificar si ya existe otra categoría con ese nombre
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM expense_categories WHERE name = ? AND id != ?");
            $stmt->execute([$name, $id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Ya existe otra categoría con ese nombre');
            }
            
            // Actualizar categoría
            $stmt = $pdo->prepare("
                UPDATE expense_categories 
                SET name = ?, description = ?, status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $result = $stmt->execute([$name, $description, $status, $id]);
            
            if (!$result || $stmt->rowCount() === 0) {
                throw new Exception('No se pudo actualizar la categoría');
            }
            
            ob_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Categoría actualizada exitosamente'
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
            
            // Verificar si la categoría tiene tipos de gastos asociados
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM expense_types WHERE category_id = ?");
            $stmt->execute([$id]);
            $typesCount = $stmt->fetchColumn();
            
            if ($typesCount > 0) {
                throw new Exception("No se puede eliminar la categoría porque tiene $typesCount tipo(s) de gasto asociado(s)");
            }
            
            // Eliminar categoría
            $stmt = $pdo->prepare("DELETE FROM expense_categories WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            if (!$result || $stmt->rowCount() === 0) {
                throw new Exception('No se pudo eliminar la categoría');
            }
            
            ob_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Categoría eliminada exitosamente'
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
            $stmt = $pdo->prepare("SELECT status FROM expense_categories WHERE id = ?");
            $stmt->execute([$id]);
            $currentStatus = $stmt->fetchColumn();
            
            if ($currentStatus === false) {
                throw new Exception('Categoría no encontrada');
            }
            
            // Cambiar estado
            $newStatus = $currentStatus === 'active' ? 'inactive' : 'active';
            
            $stmt = $pdo->prepare("UPDATE expense_categories SET status = ?, updated_at = NOW() WHERE id = ?");
            $result = $stmt->execute([$newStatus, $id]);
            
            if (!$result) {
                throw new Exception('No se pudo cambiar el estado de la categoría');
            }
            
            $statusText = $newStatus === 'active' ? 'activada' : 'desactivada';
            
            ob_clean();
            echo json_encode([
                'success' => true,
                'message' => "Categoría {$statusText} exitosamente",
                'new_status' => $newStatus
            ]);
            break;

        default:
            throw new Exception('Acción no válida');
    }

} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

ob_end_flush();
?> 
