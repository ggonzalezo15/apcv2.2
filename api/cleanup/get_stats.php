<?php
/**
 * API Endpoint para obtener estadísticas del sistema de archivos
 * Endpoint: api/cleanup/get_stats.php
 * Método: GET
 * Requiere: Sesión activa de administrador
 */

// Configuración de la base de datos - usando constantes directamente
define('DB_HOST', '168.231.68.229');     
define('DB_PORT', '3306');               
define('DB_NAME', 'cloude_apcuadre');           
define('DB_USER', 'workbench_user');     
define('DB_PASS', 'Mysql2025#');         
define('DB_CHARSET', 'utf8mb4');         

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Iniciar sesión
session_start();

// Verificar autenticación y rol de administrador
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit;
}

try {
    // Conectar a la base de datos
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
    ]);

    // Estadísticas básicas
    $stats = [];

    // Total de attachments en la base de datos
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM expense_attachments");
    $result = $stmt->fetch();
    $stats['total_attachments'] = (int)$result['count'];

    // Total de gastos
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM expenses");
    $result = $stmt->fetch();
    $stats['total_expenses'] = (int)$result['count'];

    // Contar archivos en BackBlaze B2 (mediante registros en BD)
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM expense_attachments 
        WHERE file_path LIKE 'https://%' OR file_path LIKE 'http://%'
    ");
    $result = $stmt->fetch();
    $stats['b2_attachments'] = (int)$result['count'];

    // Contar archivos locales
    $stats['local_attachments'] = $stats['total_attachments'] - $stats['b2_attachments'];

    // Buscar registros huérfanos (attachments sin expense válido)
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM expense_attachments ea
        LEFT JOIN expenses e ON ea.expense_id = e.id
        WHERE e.id IS NULL
    ");
    $result = $stmt->fetch();
    $stats['orphaned_records'] = (int)$result['count'];

    // Información adicional para debugging
    $stats['last_updated'] = date('Y-m-d H:i:s');
    $stats['system_status'] = 'operational';

    // Si hay registros huérfanos, obtener información adicional
    if ($stats['orphaned_records'] > 0) {
        $stmt = $pdo->query("
            SELECT 
                ea.id,
                ea.expense_id,
                ea.file_name,
                ea.file_path,
                ea.uploaded_at
            FROM expense_attachments ea
            LEFT JOIN expenses e ON ea.expense_id = e.id
            WHERE e.id IS NULL
            ORDER BY ea.uploaded_at DESC
            LIMIT 10
        ");
        $orphanedSamples = $stmt->fetchAll();
        $stats['orphaned_samples'] = $orphanedSamples;
    }

    // Estadísticas de almacenamiento (si es posible calcular)
    try {
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as file_count,
                SUM(CASE WHEN file_size IS NOT NULL THEN file_size ELSE 0 END) as total_size
            FROM expense_attachments
        ");
        $storageStats = $stmt->fetch();
        $stats['file_count'] = (int)$storageStats['file_count'];
        $stats['total_size_bytes'] = (int)$storageStats['total_size'];
        $stats['total_size_mb'] = round($stats['total_size_bytes'] / (1024 * 1024), 2);
    } catch (Exception $e) {
        // Si no hay columna file_size, no es crítico
        $stats['total_size_bytes'] = 0;
        $stats['total_size_mb'] = 0;
    }

    // Respuesta exitosa
    echo json_encode($stats);

} catch (PDOException $e) {
    error_log("Error en get_stats.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Error de base de datos',
        'details' => 'No se pudieron obtener las estadísticas'
    ]);
} catch (Exception $e) {
    error_log("Error general en get_stats.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Error interno del servidor',
        'details' => 'Error inesperado al procesar la solicitud'
    ]);
}
?>
