<?php
/**
 * Script para generar datos de ejemplo para logs de limpieza
 * Solo para demostración y testing
 */

// Configuración de la base de datos
define('DB_HOST', '168.231.68.229');     
define('DB_PORT', '3306');               
define('DB_NAME', 'cloude_apcuadre');           
define('DB_USER', 'workbench_user');     
define('DB_PASS', 'Mysql2025#');         
define('DB_CHARSET', 'utf8mb4');  

try {
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
            created_by INT NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_created_at (created_at),
            INDEX idx_operation_type (operation_type),
            INDEX idx_created_by (created_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($createTableSQL);

    // Datos de ejemplo
    $sampleLogs = [
        [
            'log_filename' => 'cleanup_analyze_2025-08-02_14-30-15_1.json',
            'operation_type' => 'analyze',
            'files_processed' => 1247,
            'files_deleted' => 0,
            'space_freed_mb' => 0.00,
            'execution_time_seconds' => 2.341,
            'status' => 'success',
            'error_message' => null,
            'b2_path' => 'clean_logs/cleanup_analyze_2025-08-02_14-30-15_1.json',
            'created_by' => 1,
            'created_at' => '2025-08-02 14:30:18'
        ],
        [
            'log_filename' => 'cleanup_simulate_2025-08-02_14-35-22_1.json',
            'operation_type' => 'simulate',
            'files_processed' => 1247,
            'files_deleted' => 0,
            'space_freed_mb' => 15.30,
            'execution_time_seconds' => 1.892,
            'status' => 'success',
            'error_message' => null,
            'b2_path' => 'clean_logs/cleanup_simulate_2025-08-02_14-35-22_1.json',
            'created_by' => 1,
            'created_at' => '2025-08-02 14:35:24'
        ],
        [
            'log_filename' => 'cleanup_execute_2025-08-02_14-40-10_1.json',
            'operation_type' => 'execute',
            'files_processed' => 23,
            'files_deleted' => 23,
            'space_freed_mb' => 15.30,
            'execution_time_seconds' => 4.567,
            'status' => 'success',
            'error_message' => null,
            'b2_path' => 'clean_logs/cleanup_execute_2025-08-02_14-40-10_1.json',
            'created_by' => 1,
            'created_at' => '2025-08-02 14:40:15'
        ],
        [
            'log_filename' => 'cleanup_analyze_2025-08-01_09-15-30_1.json',
            'operation_type' => 'analyze',
            'files_processed' => 1156,
            'files_deleted' => 0,
            'space_freed_mb' => 0.00,
            'execution_time_seconds' => 2.123,
            'status' => 'success',
            'error_message' => null,
            'b2_path' => 'clean_logs/cleanup_analyze_2025-08-01_09-15-30_1.json',
            'created_by' => 1,
            'created_at' => '2025-08-01 09:15:32'
        ],
        [
            'log_filename' => 'cleanup_execute_2025-07-30_16-22-45_1.json',
            'operation_type' => 'execute',
            'files_processed' => 45,
            'files_deleted' => 42,
            'space_freed_mb' => 28.75,
            'execution_time_seconds' => 6.891,
            'status' => 'partial',
            'error_message' => 'Warning: 3 archivos no pudieron ser eliminados de B2',
            'b2_path' => 'clean_logs/cleanup_execute_2025-07-30_16-22-45_1.json',
            'created_by' => 1,
            'created_at' => '2025-07-30 16:22:52'
        ],
        [
            'log_filename' => 'cleanup_analyze_2025-07-28_11-45-12_1.json',
            'operation_type' => 'analyze',
            'files_processed' => 892,
            'files_deleted' => 0,
            'space_freed_mb' => 0.00,
            'execution_time_seconds' => 8.234,
            'status' => 'error',
            'error_message' => 'Error de conexión con BackBlaze B2 durante el análisis',
            'b2_path' => null,
            'created_by' => 1,
            'created_at' => '2025-07-28 11:45:20'
        ]
    ];

    // Insertar datos de ejemplo
    $insertSQL = "
        INSERT INTO cleanup_logs (
            log_filename, operation_type, files_processed, files_deleted, 
            space_freed_mb, execution_time_seconds, status, error_message, 
            b2_path, created_by, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";

    $stmt = $pdo->prepare($insertSQL);

    foreach ($sampleLogs as $log) {
        $stmt->execute([
            $log['log_filename'],
            $log['operation_type'],
            $log['files_processed'],
            $log['files_deleted'],
            $log['space_freed_mb'],
            $log['execution_time_seconds'],
            $log['status'],
            $log['error_message'],
            $log['b2_path'],
            $log['created_by'],
            $log['created_at']
        ]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Datos de ejemplo creados correctamente',
        'records_inserted' => count($sampleLogs)
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Error de base de datos',
        'details' => $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Error general',
        'details' => $e->getMessage()
    ]);
}
?>
