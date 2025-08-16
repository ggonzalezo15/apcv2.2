<?php
/**
 * API Endpoint para manejar logs de limpieza
 * Endpoint: api/cleanup/logs.php
 * Métodos: GET (listar), DELETE (eliminar log específico)
 */

// Incluir configuración del sistema
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/B2FileUploader.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, DELETE');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

// Función para verificar si es administrador
function isAdmin() {
    return isset($_SESSION['user_id']) && 
           isset($_SESSION['role']) && 
           $_SESSION['role'] === 'admin';
}

// Verificar autenticación básica
if (!isAdmin()) {
    http_response_code(401);
    echo json_encode([
        'error' => 'Acceso no autorizado',
        'message' => 'Se requieren permisos de administrador para acceder a los logs',
        'debug' => [
            'logged_in' => isset($_SESSION['user_id']),
            'user_role' => $_SESSION['role'] ?? 'no_role'
        ]
    ]);
    exit;
}

try {
    // Conectar a la base de datos usando config.php
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
    ]);

    // Crear tabla de logs si no existe
    $createTableSQL = "
        CREATE TABLE IF NOT EXISTS cleanup_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            log_filename VARCHAR(255) NOT NULL,
            operation_type ENUM('analyze', 'simulate', 'execute') NOT NULL,
            files_processed INT DEFAULT 0,
            files_deleted INT DEFAULT 0,
            space_freed_mb DECIMAL(10,2) DEFAULT 0.00,
            execution_time_seconds DECIMAL(8,3) DEFAULT 0.000,
            status ENUM('success', 'error', 'partial') DEFAULT 'success',
            error_message TEXT NULL,
            b2_path VARCHAR(500) NULL,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_created_at (created_at),
            INDEX idx_operation_type (operation_type),
            INDEX idx_created_by (created_by),
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($createTableSQL);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Listar logs
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $limit = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : 20;
        $offset = ($page - 1) * $limit;
        
        // Filtros opcionales
        $whereConditions = [];
        $params = [];
        
        if (isset($_GET['operation_type']) && in_array($_GET['operation_type'], ['analyze', 'simulate', 'execute'])) {
            $whereConditions[] = "cl.operation_type = ?";
            $params[] = $_GET['operation_type'];
        }
        
        if (isset($_GET['status']) && in_array($_GET['status'], ['success', 'error', 'partial'])) {
            $whereConditions[] = "cl.status = ?";
            $params[] = $_GET['status'];
        }
        
        if (isset($_GET['date_from'])) {
            $whereConditions[] = "DATE(cl.created_at) >= ?";
            $params[] = $_GET['date_from'];
        }
        
        if (isset($_GET['date_to'])) {
            $whereConditions[] = "DATE(cl.created_at) <= ?";
            $params[] = $_GET['date_to'];
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        // Contar total de registros
        $countSQL = "
            SELECT COUNT(*) as total
            FROM cleanup_logs cl
            LEFT JOIN users u ON cl.created_by = u.id
            $whereClause
        ";
        
        $countStmt = $pdo->prepare($countSQL);
        $countStmt->execute($params);
        $totalRecords = $countStmt->fetch()['total'];
        
        // Obtener logs con paginación
        $logsSQL = "
            SELECT 
                cl.*,
                u.username,
                u.email
            FROM cleanup_logs cl
            LEFT JOIN users u ON cl.created_by = u.id
            $whereClause
            ORDER BY cl.created_at DESC
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $logsStmt = $pdo->prepare($logsSQL);
        $logsStmt->execute($params);
        $logs = $logsStmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'data' => $logs,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $totalRecords,
                'total_pages' => ceil($totalRecords / $limit)
            ]
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // Eliminar log específico
        $logId = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if ($logId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'ID de log inválido']);
            exit;
        }
        
        // Obtener información del log antes de eliminarlo
        $logStmt = $pdo->prepare("SELECT * FROM cleanup_logs WHERE id = ?");
        $logStmt->execute([$logId]);
        $log = $logStmt->fetch();
        
        if (!$log) {
            http_response_code(404);
            echo json_encode(['error' => 'Log no encontrado']);
            exit;
        }
        
        // TODO: Aquí se podría agregar la eliminación del archivo de B2
        // if ($log['b2_path']) {
        //     // Eliminar archivo de B2
        // }
        
        // Eliminar registro de la base de datos
        $deleteStmt = $pdo->prepare("DELETE FROM cleanup_logs WHERE id = ?");
        $deleteStmt->execute([$logId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Log eliminado correctamente',
            'deleted_log' => $log
        ]);
        
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido']);
    }

} catch (PDOException $e) {
    error_log("Error en logs.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Error de base de datos',
        'details' => 'No se pudo procesar la solicitud'
    ]);
} catch (Exception $e) {
    error_log("Error general en logs.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Error interno del servidor',
        'details' => 'Error inesperado al procesar la solicitud'
    ]);
}
?>
