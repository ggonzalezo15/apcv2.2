<?php
/**
 * TEST API Endpoint para obtener estadísticas del sistema de archivos
 * Solo para pruebas - sin verificación de sesión
 */

// Configuración de la base de datos - usando constantes directamente
define('DB_HOST', '168.231.68.229');     
define('DB_PORT', '3306');               
define('DB_NAME', 'cloude_apcuadre');           
define('DB_USER', 'workbench_user');     
define('DB_PASS', 'Mysql2025#');         
define('DB_CHARSET', 'utf8mb4');  

header('Content-Type: application/json');

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

    // Información adicional
    $stats['last_updated'] = date('Y-m-d H:i:s');
    $stats['system_status'] = 'operational';

    echo json_encode($stats);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Error de base de datos',
        'details' => $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Error interno del servidor',
        'details' => $e->getMessage()
    ]);
}
?>
