<?php
/**
 * 🤖 TAREA AUTOMÁTICA DE LIMPIEZA - CRON JOB
 * 
 * Script para ejecutar automáticamente desde cron
 * Detecta y limpia archivos huérfanos de forma segura
 * 
 * Uso desde cron:
 * 0 2 * * 0 /usr/bin/php /path/to/automated_cleanup.php
 * (Ejecutar cada domingo a las 2:00 AM)
 */

require_once 'config_cli.php';
require_once 'includes/B2FileUploader.php';

// 📝 CONFIGURACIÓN DE LOG
$logFile = 'logs/cleanup_' . date('Y-m-d') . '.log';
$logDir = dirname($logFile);

if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

function writeLog($message, $level = 'INFO') {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] [{$level}] {$message}\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    
    // También mostrar en consola si se ejecuta desde CLI
    if (php_sapi_name() === 'cli') {
        echo $logEntry;
    }
}

// ⚙️ CONFIGURACIÓN
$CONFIG = [
    'max_execution_time' => 300, // 5 minutos máximo
    'max_files_per_run' => 100,  // Máximo 100 archivos por ejecución
    'min_age_days' => 7,         // Solo eliminar archivos de más de 7 días
    'dry_run' => false,          // Cambiar a true para solo simular
    'email_report' => true,      // Enviar reporte por email
    'admin_email' => 'admin@tudominio.com'
];

// ⏰ CONTROL DE TIEMPO
set_time_limit($CONFIG['max_execution_time']);
$startTime = microtime(true);

writeLog("🤖 Iniciando limpieza automática de archivos huérfanos");
writeLog("⚙️ Configuración: max_files={$CONFIG['max_files_per_run']}, min_age={$CONFIG['min_age_days']}d, dry_run=" . ($CONFIG['dry_run'] ? 'true' : 'false'));

try {
    $pdo = getConnection();
    $cleanupStats = [
        'db_records_found' => 0,
        'db_records_cleaned' => 0,
        'local_files_found' => 0,
        'local_files_cleaned' => 0,
        'b2_files_checked' => 0,
        'b2_files_cleaned' => 0,
        'errors' => [],
        'space_recovered' => 0
    ];
    
    // 🗄️ PASO 1: Limpiar registros de BD huérfanos
    writeLog("🗄️ PASO 1: Buscando registros huérfanos en BD...");
    
    $stmt = $pdo->query("
        SELECT ea.* 
        FROM expense_attachments ea 
        LEFT JOIN expenses e ON ea.expense_id = e.id 
        WHERE e.id IS NULL 
        AND ea.created_at < DATE_SUB(NOW(), INTERVAL {$CONFIG['min_age_days']} DAY)
        LIMIT {$CONFIG['max_files_per_run']}
    ");
    $orphanedRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $cleanupStats['db_records_found'] = count($orphanedRecords);
    writeLog("📊 Encontrados {$cleanupStats['db_records_found']} registros huérfanos en BD");
    
    foreach ($orphanedRecords as $record) {
        try {
            if (!$CONFIG['dry_run']) {
                // Eliminar archivo asociado
                if (!empty($record['file_key'])) {
                    $uploader = new B2FileUploader();
                    $result = $uploader->deleteFile($record['file_key']);
                    if ($result['success']) {
                        writeLog("✅ Eliminado de B2: {$record['file_key']}");
                    } else {
                        writeLog("⚠️ No se pudo eliminar de B2: {$record['file_key']}", 'WARNING');
                    }
                } elseif (!empty($record['file_path']) && file_exists($record['file_path'])) {
                    $fileSize = filesize($record['file_path']);
                    unlink($record['file_path']);
                    $cleanupStats['space_recovered'] += $fileSize;
                    writeLog("✅ Eliminado archivo local: {$record['file_path']} (" . formatBytes($fileSize) . ")");
                }
                
                // Eliminar registro de BD
                $deleteStmt = $pdo->prepare("DELETE FROM expense_attachments WHERE id = ?");
                $deleteStmt->execute([$record['id']]);
            }
            
            $cleanupStats['db_records_cleaned']++;
            writeLog(($CONFIG['dry_run'] ? "🔍 Sería eliminado" : "✅ Eliminado") . " registro: {$record['original_filename']} (ID: {$record['id']})");
            
        } catch (Exception $e) {
            $error = "Error procesando registro {$record['id']}: " . $e->getMessage();
            writeLog($error, 'ERROR');
            $cleanupStats['errors'][] = $error;
        }
    }
    
    // 🗂️ PASO 2: Limpiar archivos locales huérfanos
    writeLog("🗂️ PASO 2: Buscando archivos locales huérfanos...");
    
    $uploadDir = 'uploads/expenses/';
    $localOrphans = findLocalOrphanedFiles($pdo, $uploadDir, $CONFIG['min_age_days']);
    
    $cleanupStats['local_files_found'] = count($localOrphans);
    writeLog("📊 Encontrados {$cleanupStats['local_files_found']} archivos locales huérfanos");
    
    $processedFiles = 0;
    foreach ($localOrphans as $file) {
        if ($processedFiles >= $CONFIG['max_files_per_run']) {
            writeLog("⏸️ Alcanzado límite de archivos por ejecución ({$CONFIG['max_files_per_run']})");
            break;
        }
        
        try {
            if (!$CONFIG['dry_run']) {
                unlink($file['full_path']);
                $cleanupStats['space_recovered'] += $file['size'];
            }
            
            $cleanupStats['local_files_cleaned']++;
            writeLog(($CONFIG['dry_run'] ? "🔍 Sería eliminado" : "✅ Eliminado") . " archivo local: {$file['filename']} (" . formatBytes($file['size']) . ")");
            $processedFiles++;
            
        } catch (Exception $e) {
            $error = "Error eliminando archivo {$file['filename']}: " . $e->getMessage();
            writeLog($error, 'ERROR');
            $cleanupStats['errors'][] = $error;
        }
    }
    
    // ☁️ PASO 3: Verificación muestral de archivos B2
    writeLog("☁️ PASO 3: Verificación muestral de archivos B2...");
    
    $stmt = $pdo->query("
        SELECT id, file_key, original_filename 
        FROM expense_attachments 
        WHERE file_key IS NOT NULL AND file_key != ''
        AND updated_at < DATE_SUB(NOW(), INTERVAL {$CONFIG['min_age_days']} DAY)
        ORDER BY RAND() 
        LIMIT 20
    ");
    $b2FilesToCheck = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $cleanupStats['b2_files_checked'] = count($b2FilesToCheck);
    
    if (!empty($b2FilesToCheck)) {
        $uploader = new B2FileUploader();
        
        foreach ($b2FilesToCheck as $dbFile) {
            try {
                $fileInfo = $uploader->getFileInfo($dbFile['file_key']);
                
                if (!$fileInfo || !$fileInfo['success']) {
                    writeLog("⚠️ Archivo en BD pero no en B2: {$dbFile['file_key']}", 'WARNING');
                    
                    if (!$CONFIG['dry_run']) {
                        // Eliminar registro huérfano de BD
                        $deleteStmt = $pdo->prepare("DELETE FROM expense_attachments WHERE id = ?");
                        $deleteStmt->execute([$dbFile['id']]);
                        $cleanupStats['b2_files_cleaned']++;
                        writeLog("✅ Eliminado registro huérfano de BD: {$dbFile['original_filename']}");
                    }
                }
                
            } catch (Exception $e) {
                if (strpos(strtolower($e->getMessage()), 'not found') !== false) {
                    writeLog("⚠️ Archivo no encontrado en B2: {$dbFile['file_key']}", 'WARNING');
                    
                    if (!$CONFIG['dry_run']) {
                        $deleteStmt = $pdo->prepare("DELETE FROM expense_attachments WHERE id = ?");
                        $deleteStmt->execute([$dbFile['id']]);
                        $cleanupStats['b2_files_cleaned']++;
                        writeLog("✅ Eliminado registro huérfano de BD: {$dbFile['original_filename']}");
                    }
                }
            }
        }
    }
    
    $executionTime = round(microtime(true) - $startTime, 2);
    
    // 📊 REPORTE FINAL
    writeLog("📊 REPORTE FINAL DE LIMPIEZA");
    writeLog("⏰ Tiempo de ejecución: {$executionTime}s");
    writeLog("🗄️ Registros BD encontrados/limpiados: {$cleanupStats['db_records_found']}/{$cleanupStats['db_records_cleaned']}");
    writeLog("🗂️ Archivos locales encontrados/limpiados: {$cleanupStats['local_files_found']}/{$cleanupStats['local_files_cleaned']}");
    writeLog("☁️ Archivos B2 verificados/limpiados: {$cleanupStats['b2_files_checked']}/{$cleanupStats['b2_files_cleaned']}");
    writeLog("💾 Espacio recuperado: " . formatBytes($cleanupStats['space_recovered']));
    writeLog("❌ Errores: " . count($cleanupStats['errors']));
    
    if (!empty($cleanupStats['errors'])) {
        writeLog("📝 Detalle de errores:");
        foreach ($cleanupStats['errors'] as $error) {
            writeLog("  • {$error}", 'ERROR');
        }
    }
    
    // 📧 ENVIAR REPORTE POR EMAIL (opcional)
    if ($CONFIG['email_report'] && !empty($CONFIG['admin_email'])) {
        sendCleanupReport($cleanupStats, $executionTime, $CONFIG['admin_email']);
    }
    
    writeLog("🎉 Limpieza automática completada exitosamente");
    
} catch (Exception $e) {
    writeLog("💥 Error crítico en limpieza automática: " . $e->getMessage(), 'ERROR');
    
    // Enviar alerta por email en caso de error crítico
    if ($CONFIG['email_report'] && !empty($CONFIG['admin_email'])) {
        $subject = "🚨 Error en Limpieza Automática - " . date('Y-m-d H:i:s');
        $message = "Error crítico durante la limpieza automática:\n\n" . $e->getMessage() . "\n\nRevisa los logs para más detalles.";
        mail($CONFIG['admin_email'], $subject, $message);
    }
}

// 🔍 FUNCIÓN: Encontrar archivos locales huérfanos con filtro de edad
function findLocalOrphanedFiles($pdo, $uploadDir, $minAgeDays) {
    $orphans = [];
    
    if (!is_dir($uploadDir)) {
        return $orphans;
    }
    
    $minTimestamp = time() - ($minAgeDays * 24 * 60 * 60);
    $files = scandir($uploadDir);
    
    foreach ($files as $filename) {
        if ($filename === '.' || $filename === '..') continue;
        
        $fullPath = $uploadDir . $filename;
        if (!is_file($fullPath)) continue;
        
        // Verificar edad del archivo
        if (filemtime($fullPath) > $minTimestamp) continue;
        
        // Verificar si el archivo tiene referencia en BD
        $relativePath = 'uploads/expenses/' . $filename;
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM expense_attachments WHERE file_path = ? OR filename = ?");
        $stmt->execute([$relativePath, $filename]);
        $count = $stmt->fetchColumn();
        
        if ($count == 0) {
            $orphans[] = [
                'filename' => $filename,
                'full_path' => $fullPath,
                'size' => filesize($fullPath),
                'date' => date('Y-m-d H:i:s', filemtime($fullPath))
            ];
        }
    }
    
    return $orphans;
}

// 📧 FUNCIÓN: Enviar reporte por email
function sendCleanupReport($stats, $executionTime, $adminEmail) {
    $subject = "📊 Reporte de Limpieza Automática - " . date('Y-m-d H:i:s');
    
    $message = "
🤖 REPORTE DE LIMPIEZA AUTOMÁTICA
======================================

⏰ Tiempo de ejecución: {$executionTime}s
📅 Fecha: " . date('Y-m-d H:i:s') . "

📊 ESTADÍSTICAS:
• Registros BD encontrados/limpiados: {$stats['db_records_found']}/{$stats['db_records_cleaned']}
• Archivos locales encontrados/limpiados: {$stats['local_files_found']}/{$stats['local_files_cleaned']}
• Archivos B2 verificados/limpiados: {$stats['b2_files_checked']}/{$stats['b2_files_cleaned']}
• Espacio recuperado: " . formatBytes($stats['space_recovered']) . "
• Errores: " . count($stats['errors']) . "

";

    if (!empty($stats['errors'])) {
        $message .= "❌ ERRORES ENCONTRADOS:\n";
        foreach ($stats['errors'] as $error) {
            $message .= "• {$error}\n";
        }
        $message .= "\n";
    }
    
    $message .= "
🔗 Para más detalles, revisa los logs del sistema.

--
Sistema de Limpieza Automática
";
    
    mail($adminEmail, $subject, $message);
}

// 🛠️ FUNCIÓN: Formatear bytes
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

?>
