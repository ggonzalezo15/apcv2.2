<?php
/**
 * Script para crear logs de prueba
 */

// Configuración de base de datos directa para evitar conflictos
$host = '168.231.68.229';
$dbname = 'cloude_apcuadre';
$username = 'workbench_user';
$password = 'Mysql2025#';

try {
    // Crear conexión PDO
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    echo "✅ Conexión a base de datos exitosa\n";

    // Verificar si la tabla cleanup_logs existe
    $checkTable = $pdo->query("SHOW TABLES LIKE 'cleanup_logs'")->fetch();
    
    if (!$checkTable) {
        echo "📋 Creando tabla cleanup_logs...\n";
        $createTable = "
            CREATE TABLE cleanup_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                log_filename VARCHAR(255) NOT NULL,
                file_path VARCHAR(500) NOT NULL,
                file_size BIGINT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_by_user_id INT,
                operation_type VARCHAR(100) DEFAULT 'cleanup',
                summary TEXT,
                INDEX idx_created_at (created_at),
                INDEX idx_user_id (created_by_user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        $pdo->exec($createTable);
        echo "✅ Tabla cleanup_logs creada\n";
    } else {
        echo "✅ Tabla cleanup_logs ya existe\n";
    }

    // Insertar un log de prueba
    $logFilename = 'test_cleanup_' . date('Y-m-d_H-i-s') . '.json';
    
    $insertLog = $pdo->prepare("
        INSERT INTO cleanup_logs (
            log_filename, 
            operation_type, 
            files_processed, 
            files_deleted, 
            space_freed_mb, 
            execution_time_seconds, 
            status, 
            b2_path, 
            created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $insertLog->execute([
        $logFilename,
        'execute',
        150,  // files_processed
        8,    // files_deleted
        23.5, // space_freed_mb
        3.2,  // execution_time_seconds
        'success',
        'clean_logs/' . $logFilename,
        1     // created_by (admin user)
    ]);

    $logId = $pdo->lastInsertId();
    
    echo "✅ Log de prueba creado exitosamente\n";
    echo "📋 ID: $logId\n";
    echo "📁 Archivo: $logFilename\n";

    // Crear algunos logs adicionales
    for ($i = 1; $i <= 4; $i++) {
        $logFilename = 'cleanup_' . date('Y-m-d_H-i-s') . "_$i.json";
        
        $operationTypes = ['analyze', 'simulate', 'execute'];
        $statuses = ['success', 'success', 'success', 'partial']; // Mostly success
        
        $filesProcessed = rand(50, 300);
        $filesDeleted = rand(1, intval($filesProcessed * 0.2)); // Max 20% deleted
        $spaceFreed = rand(5, 100) + (rand(0, 99) / 100); // Random decimal
        $executionTime = rand(1, 10) + (rand(0, 999) / 1000); // Random decimal
        
        $insertLog->execute([
            $logFilename,
            $operationTypes[array_rand($operationTypes)],
            $filesProcessed,
            $filesDeleted,
            $spaceFreed,
            $executionTime,
            $statuses[array_rand($statuses)],
            'clean_logs/' . $logFilename,
            1
        ]);

        echo "✅ Log adicional $i creado: $logFilename (Procesados: $filesProcessed, Eliminados: $filesDeleted)\n";
    }

    echo "\n🎉 ¡Logs de prueba creados exitosamente!\n";
    echo "📋 Ahora puedes probar el botón 'Ver Logs' en el panel de administración.\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
