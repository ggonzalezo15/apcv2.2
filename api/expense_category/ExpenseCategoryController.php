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
            // Obtener todas las categorías con conteo de tipos
            $stmt = $pdo->prepare("
                SELECT 
                    c.*,
                    COUNT(et.id) as types_count
                FROM expense_categories c
                LEFT JOIN expense_types et ON c.id = et.category_id
                GROUP BY c.id
                ORDER BY c.created_at DESC
            ");
            $stmt->execute();
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
            $is_active = isset($_POST['is_active']) ? (bool)$_POST['is_active'] : true;
            
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
                INSERT INTO expense_categories (id, name, description, is_active, created_at, updated_at) 
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$id, $name, $description, $is_active]);
            
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
            $is_active = isset($_POST['is_active']) ? (bool)$_POST['is_active'] : true;
            
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
                SET name = ?, description = ?, is_active = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $result = $stmt->execute([$name, $description, $is_active, $id]);
            
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
