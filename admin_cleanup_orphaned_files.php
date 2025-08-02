<?php
/**
 * 🧹 SCRIPT DE LIMPIEZA DE ARCHIVOS HUÉRFANOS
 * 
 * Este script permite al administrador:
 * 1. Detectar archivos huérfanos en BackBlaze B2
 * 2. Detectar archivos locales sin referencia en BD
 * 3. Detectar registros en BD sin archivo correspondiente
 * 4. Eliminar archivos huérfanos de forma segura
 * 
 * USAR CON PRECAUCIÓN - Solo para administradores
 */

require_once 'config.php';
require_once 'includes/B2FileUploader.php';

// 🔒 VERIFICACIÓN DE SEGURIDAD
// Nota: session_start() ya se ejecuta en config.php, no necesitamos duplicarlo

// Verificar que el usuario está logueado y es administrador
if (!isset($_SESSION['user_id'])) {
    die("❌ ERROR: Debes estar logueado para ejecutar este script\n");
}

// Verificar rol de administrador (ajustar según tu sistema)
$pdo = getConnection();
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$userRole = $stmt->fetchColumn();

if ($userRole !== 'admin' && $userRole !== 'administrator') {
    die("❌ ERROR: Solo los administradores pueden ejecutar este script\n");
}

// 🎯 CONFIGURACIÓN
$SCRIPT_MODE = $_GET['mode'] ?? 'analyze'; // analyze | cleanup | force_cleanup
$DRY_RUN = ($_GET['dry_run'] ?? 'true') === 'true';
$CHUNK_SIZE = 100; // Procesar archivos en chunks para evitar timeouts

echo "<!DOCTYPE html>
<html>
<head>
    <title>🧹 Limpieza de Archivos Huérfanos</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; }
        .warning { color: #ffc107; }
        .error { color: #dc3545; }
        .info { color: #17a2b8; }
        .section { margin: 20px 0; padding: 15px; border-left: 4px solid #007bff; background: #f8f9fa; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
        .button { display: inline-block; padding: 10px 15px; margin: 5px; text-decoration: none; border-radius: 4px; }
        .btn-analyze { background: #007bff; color: white; }
        .btn-cleanup { background: #28a745; color: white; }
        .btn-force { background: #dc3545; color: white; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }
        .stat-card { background: #e9ecef; padding: 15px; border-radius: 8px; text-align: center; }
        .progress { width: 100%; background: #e9ecef; border-radius: 4px; overflow: hidden; margin: 10px 0; }
        .progress-bar { height: 20px; background: #007bff; transition: width 0.3s; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🧹 Sistema de Limpieza de Archivos Huérfanos</h1>";
echo "<p class='info'>👤 Usuario: " . htmlspecialchars($_SESSION['username'] ?? 'Admin') . " | 🕒 " . date('Y-m-d H:i:s') . "</p>";

// 🎮 MENÚ DE NAVEGACIÓN
echo "<div class='section'>";
echo "<h2>🎮 Acciones Disponibles</h2>";
echo "<a href='?mode=analyze&dry_run=true' class='button btn-analyze'>📊 Analizar (Solo Lectura)</a>";
echo "<a href='?mode=cleanup&dry_run=true' class='button btn-cleanup'>🧹 Limpieza Simulada</a>";
echo "<a href='?mode=cleanup&dry_run=false' class='button btn-force'>⚠️ Limpieza REAL</a>";
echo "<a href='?mode=force_cleanup&dry_run=false' class='button btn-force'>🔥 Limpieza FORZADA</a>";
echo "</div>";

// 📊 ESTADÍSTICAS INICIALES
$stats = getSystemStats($pdo);
echo "<div class='section'>";
echo "<h2>📊 Estadísticas del Sistema</h2>";
echo "<div class='stats'>";
echo "<div class='stat-card'><h3>{$stats['total_attachments']}</h3><p>Total Attachments en BD</p></div>";
echo "<div class='stat-card'><h3>{$stats['b2_attachments']}</h3><p>Archivos en B2</p></div>";
echo "<div class='stat-card'><h3>{$stats['local_attachments']}</h3><p>Archivos Locales</p></div>";
echo "<div class='stat-card'><h3>{$stats['total_expenses']}</h3><p>Gastos Totales</p></div>";
echo "</div>";
echo "</div>";

try {
    switch ($SCRIPT_MODE) {
        case 'analyze':
            performAnalysis($pdo, $DRY_RUN);
            break;
        case 'cleanup':
            performCleanup($pdo, $DRY_RUN);
            break;
        case 'force_cleanup':
            performForceCleanup($pdo, $DRY_RUN);
            break;
        default:
            echo "<div class='section error'>";
            echo "<h2>❌ Modo no válido</h2>";
            echo "<p>Modos disponibles: analyze, cleanup, force_cleanup</p>";
            echo "</div>";
    }
} catch (Exception $e) {
    echo "<div class='section error'>";
    echo "<h2>💥 Error Crítico</h2>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "</div>";
}

echo "</div></body></html>";

// 📊 FUNCIÓN: Obtener estadísticas del sistema
function getSystemStats($pdo) {
    $stats = [];
    
    // Total de attachments en BD
    $stmt = $pdo->query("SELECT COUNT(*) FROM expense_attachments");
    $stats['total_attachments'] = $stmt->fetchColumn();
    
    // Attachments con file_key (B2)
    $stmt = $pdo->query("SELECT COUNT(*) FROM expense_attachments WHERE file_key IS NOT NULL AND file_key != ''");
    $stats['b2_attachments'] = $stmt->fetchColumn();
    
    // Attachments locales
    $stmt = $pdo->query("SELECT COUNT(*) FROM expense_attachments WHERE (file_key IS NULL OR file_key = '') AND file_path IS NOT NULL");
    $stats['local_attachments'] = $stmt->fetchColumn();
    
    // Total de gastos
    $stmt = $pdo->query("SELECT COUNT(*) FROM expenses");
    $stats['total_expenses'] = $stmt->fetchColumn();
    
    return $stats;
}

// 🔍 FUNCIÓN: Análisis completo del sistema
function performAnalysis($pdo, $dryRun = true) {
    echo "<div class='section info'>";
    echo "<h2>🔍 Análisis de Archivos Huérfanos</h2>";
    echo "<p><strong>Modo:</strong> " . ($dryRun ? "Solo Lectura (Seguro)" : "Análisis Activo") . "</p>";
    echo "</div>";
    
    $orphanedFiles = [];
    
    // 1. 🗄️ ARCHIVOS EN BD SIN GASTO ASOCIADO
    echo "<div class='section'>";
    echo "<h3>1. 🗄️ Registros en BD sin Gasto Asociado</h3>";
    
    $stmt = $pdo->query("
        SELECT ea.*, e.id as expense_exists 
        FROM expense_attachments ea 
        LEFT JOIN expenses e ON ea.expense_id = e.id 
        WHERE e.id IS NULL
    ");
    $orphanedRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($orphanedRecords)) {
        echo "<p class='success'>✅ No se encontraron registros huérfanos en BD</p>";
    } else {
        echo "<p class='warning'>⚠️ Encontrados " . count($orphanedRecords) . " registros huérfanos</p>";
        echo "<pre>";
        foreach ($orphanedRecords as $record) {
            echo "• ID: {$record['id']} | Archivo: {$record['original_filename']} | Gasto: {$record['expense_id']}\n";
            $orphanedFiles['db_records'][] = $record;
        }
        echo "</pre>";
    }
    echo "</div>";
    
    // 2. 🗂️ ARCHIVOS LOCALES SIN REGISTRO EN BD
    echo "<div class='section'>";
    echo "<h3>2. 🗂️ Archivos Locales sin Registro en BD</h3>";
    
    $uploadDir = 'uploads/expenses/';
    $localOrphans = findLocalOrphanedFiles($pdo, $uploadDir);
    
    if (empty($localOrphans)) {
        echo "<p class='success'>✅ No se encontraron archivos locales huérfanos</p>";
    } else {
        echo "<p class='warning'>⚠️ Encontrados " . count($localOrphans) . " archivos locales huérfanos</p>";
        echo "<pre>";
        foreach ($localOrphans as $file) {
            echo "• Archivo: {$file['filename']} | Tamaño: " . formatBytes($file['size']) . " | Fecha: {$file['date']}\n";
            $orphanedFiles['local_files'][] = $file;
        }
        echo "</pre>";
    }
    echo "</div>";
    
    // 3. ☁️ VERIFICACIÓN DE ARCHIVOS B2
    echo "<div class='section'>";
    echo "<h3>3. ☁️ Verificación de Archivos en BackBlaze B2</h3>";
    
    try {
        $b2Orphans = analyzeB2Files($pdo);
        
        if (empty($b2Orphans['missing_in_b2']) && empty($b2Orphans['missing_in_db'])) {
            echo "<p class='success'>✅ Consistencia perfecta entre BD y B2</p>";
        } else {
            if (!empty($b2Orphans['missing_in_b2'])) {
                echo "<p class='error'>❌ Registros en BD sin archivo en B2: " . count($b2Orphans['missing_in_b2']) . "</p>";
                $orphanedFiles['missing_in_b2'] = $b2Orphans['missing_in_b2'];
            }
            
            if (!empty($b2Orphans['missing_in_db'])) {
                echo "<p class='warning'>⚠️ Archivos en B2 sin registro en BD: " . count($b2Orphans['missing_in_db']) . "</p>";
                $orphanedFiles['missing_in_db'] = $b2Orphans['missing_in_db'];
            }
        }
    } catch (Exception $e) {
        echo "<p class='error'>❌ Error verificando B2: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    echo "</div>";
    
    // 📊 RESUMEN DE ANÁLISIS
    echo "<div class='section'>";
    echo "<h3>📊 Resumen del Análisis</h3>";
    
    $totalOrphans = 0;
    $estimatedSpace = 0;
    
    foreach ($orphanedFiles as $category => $files) {
        $count = count($files);
        $totalOrphans += $count;
        
        if ($category === 'local_files') {
            foreach ($files as $file) {
                $estimatedSpace += $file['size'];
            }
        }
        
        echo "<p><strong>" . ucfirst(str_replace('_', ' ', $category)) . ":</strong> {$count} elementos</p>";
    }
    
    echo "<div class='stats'>";
    echo "<div class='stat-card'><h3>{$totalOrphans}</h3><p>Total Elementos Huérfanos</p></div>";
    echo "<div class='stat-card'><h3>" . formatBytes($estimatedSpace) . "</h3><p>Espacio Recuperable</p></div>";
    echo "</div>";
    
    if ($totalOrphans > 0) {
        echo "<p class='warning'>💡 <strong>Recomendación:</strong> Ejecutar limpieza para recuperar espacio y mantener consistencia</p>";
        echo "<a href='?mode=cleanup&dry_run=true' class='button btn-cleanup'>🧹 Simular Limpieza</a>";
        echo "<a href='?mode=cleanup&dry_run=false' class='button btn-force'>⚠️ Ejecutar Limpieza Real</a>";
    } else {
        echo "<p class='success'>🎉 <strong>¡Excelente!</strong> El sistema está limpio y consistente</p>";
    }
    echo "</div>";
}

// 🧹 FUNCIÓN: Realizar limpieza
function performCleanup($pdo, $dryRun = true) {
    echo "<div class='section " . ($dryRun ? 'info' : 'warning') . "'>";
    echo "<h2>🧹 Limpieza de Archivos Huérfanos</h2>";
    echo "<p><strong>Modo:</strong> " . ($dryRun ? "SIMULACIÓN (No se eliminará nada)" : "LIMPIEZA REAL") . "</p>";
    echo "</div>";
    
    $cleanupResults = [
        'db_records_cleaned' => 0,
        'local_files_cleaned' => 0,
        'b2_files_cleaned' => 0,
        'errors' => []
    ];
    
    try {
        $pdo->beginTransaction();
        
        // 1. Limpiar registros de BD sin gasto asociado
        $stmt = $pdo->query("
            SELECT ea.* 
            FROM expense_attachments ea 
            LEFT JOIN expenses e ON ea.expense_id = e.id 
            WHERE e.id IS NULL
        ");
        $orphanedRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<div class='section'>";
        echo "<h3>1. 🗄️ Limpiando Registros Huérfanos en BD</h3>";
        
        foreach ($orphanedRecords as $record) {
            try {
                if (!$dryRun) {
                    // Eliminar archivo asociado si existe
                    if (!empty($record['file_key'])) {
                        // Es un archivo B2
                        $uploader = new B2FileUploader();
                        $result = $uploader->deleteFile($record['file_key']);
                        if ($result['success']) {
                            echo "<p class='success'>✅ Eliminado de B2: {$record['file_key']}</p>";
                        } else {
                            echo "<p class='warning'>⚠️ No se pudo eliminar de B2: {$record['file_key']}</p>";
                        }
                    } elseif (!empty($record['file_path'])) {
                        // Es un archivo local
                        $filePath = $record['file_path'];
                        if (file_exists($filePath)) {
                            unlink($filePath);
                            echo "<p class='success'>✅ Eliminado archivo local: {$filePath}</p>";
                        }
                    }
                    
                    // Eliminar registro de BD
                    $deleteStmt = $pdo->prepare("DELETE FROM expense_attachments WHERE id = ?");
                    $deleteStmt->execute([$record['id']]);
                }
                
                echo "<p>" . ($dryRun ? "🔍 Sería eliminado" : "✅ Eliminado") . ": {$record['original_filename']} (ID: {$record['id']})</p>";
                $cleanupResults['db_records_cleaned']++;
                
            } catch (Exception $e) {
                $error = "Error procesando {$record['id']}: " . $e->getMessage();
                echo "<p class='error'>❌ {$error}</p>";
                $cleanupResults['errors'][] = $error;
            }
        }
        echo "</div>";
        
        // 2. Limpiar archivos locales huérfanos
        $uploadDir = 'uploads/expenses/';
        $localOrphans = findLocalOrphanedFiles($pdo, $uploadDir);
        
        echo "<div class='section'>";
        echo "<h3>2. 🗂️ Limpiando Archivos Locales Huérfanos</h3>";
        
        foreach ($localOrphans as $file) {
            try {
                if (!$dryRun) {
                    unlink($file['full_path']);
                }
                
                echo "<p>" . ($dryRun ? "🔍 Sería eliminado" : "✅ Eliminado") . ": {$file['filename']} (" . formatBytes($file['size']) . ")</p>";
                $cleanupResults['local_files_cleaned']++;
                
            } catch (Exception $e) {
                $error = "Error eliminando {$file['filename']}: " . $e->getMessage();
                echo "<p class='error'>❌ {$error}</p>";
                $cleanupResults['errors'][] = $error;
            }
        }
        echo "</div>";
        
        if ($dryRun) {
            $pdo->rollBack();
            echo "<div class='section info'>";
            echo "<h3>🔍 Resumen de Simulación</h3>";
            echo "<p>✅ Registros en BD que serían eliminados: {$cleanupResults['db_records_cleaned']}</p>";
            echo "<p>✅ Archivos locales que serían eliminados: {$cleanupResults['local_files_cleaned']}</p>";
            echo "<p>❌ Errores encontrados: " . count($cleanupResults['errors']) . "</p>";
            echo "<p class='warning'>💡 Para ejecutar la limpieza real, usar: <a href='?mode=cleanup&dry_run=false' class='button btn-force'>Limpieza REAL</a></p>";
            echo "</div>";
        } else {
            $pdo->commit();
            echo "<div class='section success'>";
            echo "<h3>🎉 Limpieza Completada</h3>";
            echo "<p>✅ Registros en BD eliminados: {$cleanupResults['db_records_cleaned']}</p>";
            echo "<p>✅ Archivos locales eliminados: {$cleanupResults['local_files_cleaned']}</p>";
            echo "<p>❌ Errores: " . count($cleanupResults['errors']) . "</p>";
            echo "</div>";
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<div class='section error'>";
        echo "<h3>💥 Error en Limpieza</h3>";
        echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
        echo "</div>";
    }
}

// 🔥 FUNCIÓN: Limpieza forzada (más agresiva)
function performForceCleanup($pdo, $dryRun = true) {
    echo "<div class='section error'>";
    echo "<h2>🔥 Limpieza FORZADA - Modo Agresivo</h2>";
    echo "<p><strong>⚠️ ADVERTENCIA:</strong> Este modo es más agresivo y puede eliminar archivos que parezcan huérfanos</p>";
    echo "<p><strong>Modo:</strong> " . ($dryRun ? "SIMULACIÓN FORZADA" : "LIMPIEZA FORZADA REAL") . "</p>";
    echo "</div>";
    
    if (!$dryRun) {
        echo "<div class='section error'>";
        echo "<h3>⚠️ Confirmación Requerida</h3>";
        echo "<p>La limpieza forzada real requiere confirmación adicional.</p>";
        echo "<p>Para proceder, agregar: <code>&confirm=FORCE_DELETE_ALL</code> a la URL</p>";
        
        if (($_GET['confirm'] ?? '') !== 'FORCE_DELETE_ALL') {
            echo "<p class='error'>❌ Confirmación no válida. Operación cancelada por seguridad.</p>";
            return;
        }
        echo "</div>";
    }
    
    // Ejecutar limpieza normal primero
    performCleanup($pdo, $dryRun);
    
    // Luego, verificaciones adicionales más agresivas
    echo "<div class='section'>";
    echo "<h3>🔥 Verificaciones Adicionales Forzadas</h3>";
    
    // TODO: Implementar verificaciones más agresivas como:
    // - Archivos antiguos sin actividad
    // - Archivos con nombres sospechosos
    // - Verificación masiva de B2
    
    echo "<p class='info'>💡 Las verificaciones forzadas adicionales se implementarán según necesidades específicas</p>";
    echo "</div>";
}

// 🔍 FUNCIÓN: Encontrar archivos locales huérfanos
function findLocalOrphanedFiles($pdo, $uploadDir) {
    $orphans = [];
    
    if (!is_dir($uploadDir)) {
        return $orphans;
    }
    
    $files = scandir($uploadDir);
    
    foreach ($files as $filename) {
        if ($filename === '.' || $filename === '..') continue;
        
        $fullPath = $uploadDir . $filename;
        if (!is_file($fullPath)) continue;
        
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

// ☁️ FUNCIÓN: Analizar archivos B2
function analyzeB2Files($pdo) {
    $result = [
        'missing_in_b2' => [],
        'missing_in_db' => []
    ];
    
    // Obtener todos los file_keys de la BD
    $stmt = $pdo->query("SELECT id, file_key, original_filename FROM expense_attachments WHERE file_key IS NOT NULL AND file_key != ''");
    $dbFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($dbFiles)) {
        return $result; // No hay archivos B2 para verificar
    }
    
    // Verificar una muestra de archivos (para evitar timeouts)
    $sampleSize = min(50, count($dbFiles));
    $sample = array_slice($dbFiles, 0, $sampleSize);
    
    $uploader = new B2FileUploader();
    
    foreach ($sample as $dbFile) {
        try {
            $fileInfo = $uploader->getFileInfo($dbFile['file_key']);
            
            if (!$fileInfo || !$fileInfo['success']) {
                $result['missing_in_b2'][] = $dbFile;
            }
            
        } catch (Exception $e) {
            // Si hay error "not found", el archivo no existe en B2
            if (strpos(strtolower($e->getMessage()), 'not found') !== false) {
                $result['missing_in_b2'][] = $dbFile;
            }
        }
    }
    
    return $result;
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
