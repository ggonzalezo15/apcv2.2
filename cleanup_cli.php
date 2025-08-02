#!/usr/bin/env php
<?php
/**
 * 🖥️ HERRAMIENTA CLI DE LIMPIEZA DE ARCHIVOS HUÉRFANOS
 * 
 * Herramienta de línea de comandos para administradores
 * Permite análisis y limpieza detallados desde terminal
 * 
 * Uso:
 * php cleanup_cli.php --help
 * php cleanup_cli.php --analyze
 * php cleanup_cli.php --cleanup --dry-run
 * php cleanup_cli.php --cleanup --force
 * php cleanup_cli.php --stats
 */

// Verificar que se ejecuta desde CLI
if (php_sapi_name() !== 'cli') {
    die("❌ Este script solo puede ejecutarse desde línea de comandos\n");
}

require_once 'config_cli.php';
require_once 'includes/B2FileUploader.php';

// 🎨 COLORES PARA TERMINAL
class Colors {
    const RESET = "\033[0m";
    const RED = "\033[0;31m";
    const GREEN = "\033[0;32m";
    const YELLOW = "\033[0;33m";
    const BLUE = "\033[0;34m";
    const PURPLE = "\033[0;35m";
    const CYAN = "\033[0;36m";
    const WHITE = "\033[1;37m";
    const BOLD = "\033[1m";
}

// 📝 FUNCIÓN: Imprimir con color
function printColor($text, $color = Colors::WHITE) {
    echo $color . $text . Colors::RESET . "\n";
}

// 📋 FUNCIÓN: Mostrar ayuda
function showHelp() {
    printColor("🧹 HERRAMIENTA DE LIMPIEZA DE ARCHIVOS HUÉRFANOS", Colors::BOLD);
    echo "\n";
    printColor("USO:", Colors::CYAN);
    echo "  php cleanup_cli.php [OPCIONES]\n\n";
    
    printColor("OPCIONES:", Colors::CYAN);
    echo "  --help              Mostrar esta ayuda\n";
    echo "  --analyze           Analizar archivos huérfanos (solo lectura)\n";
    echo "  --cleanup           Ejecutar limpieza\n";
    echo "  --stats             Mostrar estadísticas del sistema\n";
    echo "  --verify-b2         Verificar consistencia con BackBlaze B2\n";
    echo "\n";
    
    printColor("MODIFICADORES:", Colors::CYAN);
    echo "  --dry-run           Simular operaciones sin ejecutar cambios\n";
    echo "  --force             Ejecutar limpieza real (usar con precaución)\n";
    echo "  --max-files=N       Máximo número de archivos a procesar (default: 100)\n";
    echo "  --min-age=N         Edad mínima en días para considerar archivos (default: 7)\n";
    echo "  --verbose           Mostrar información detallada\n";
    echo "\n";
    
    printColor("EJEMPLOS:", Colors::CYAN);
    echo "  php cleanup_cli.php --analyze\n";
    echo "  php cleanup_cli.php --cleanup --dry-run\n";
    echo "  php cleanup_cli.php --cleanup --force --max-files=50\n";
    echo "  php cleanup_cli.php --verify-b2 --verbose\n";
    echo "\n";
}

// ⚙️ PARSEAR ARGUMENTOS
function parseArguments($argv) {
    $options = [
        'help' => false,
        'analyze' => false,
        'cleanup' => false,
        'stats' => false,
        'verify-b2' => false,
        'dry-run' => false,
        'force' => false,
        'verbose' => false,
        'max-files' => 100,
        'min-age' => 7
    ];
    
    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];
        
        if ($arg === '--help') {
            $options['help'] = true;
        } elseif ($arg === '--analyze') {
            $options['analyze'] = true;
        } elseif ($arg === '--cleanup') {
            $options['cleanup'] = true;
        } elseif ($arg === '--stats') {
            $options['stats'] = true;
        } elseif ($arg === '--verify-b2') {
            $options['verify-b2'] = true;
        } elseif ($arg === '--dry-run') {
            $options['dry-run'] = true;
        } elseif ($arg === '--force') {
            $options['force'] = true;
        } elseif ($arg === '--verbose') {
            $options['verbose'] = true;
        } elseif (strpos($arg, '--max-files=') === 0) {
            $options['max-files'] = (int)substr($arg, 12);
        } elseif (strpos($arg, '--min-age=') === 0) {
            $options['min-age'] = (int)substr($arg, 10);
        } else {
            printColor("❌ Opción desconocida: $arg", Colors::RED);
            printColor("Use --help para ver las opciones disponibles", Colors::YELLOW);
            exit(1);
        }
    }
    
    return $options;
}

// 📊 FUNCIÓN: Mostrar estadísticas
function showStats($pdo) {
    printColor("📊 ESTADÍSTICAS DEL SISTEMA", Colors::BOLD);
    echo "\n";
    
    // Total de attachments
    $stmt = $pdo->query("SELECT COUNT(*) FROM expense_attachments");
    $totalAttachments = $stmt->fetchColumn();
    
    // Attachments con file_key (B2)
    $stmt = $pdo->query("SELECT COUNT(*) FROM expense_attachments WHERE file_key IS NOT NULL AND file_key != ''");
    $b2Attachments = $stmt->fetchColumn();
    
    // Attachments locales
    $stmt = $pdo->query("SELECT COUNT(*) FROM expense_attachments WHERE (file_key IS NULL OR file_key = '') AND file_path IS NOT NULL");
    $localAttachments = $stmt->fetchColumn();
    
    // Total de gastos
    $stmt = $pdo->query("SELECT COUNT(*) FROM expenses");
    $totalExpenses = $stmt->fetchColumn();
    
    // Registros huérfanos
    $stmt = $pdo->query("
        SELECT COUNT(*) 
        FROM expense_attachments ea 
        LEFT JOIN expenses e ON ea.expense_id = e.id 
        WHERE e.id IS NULL
    ");
    $orphanedRecords = $stmt->fetchColumn();
    
    // Tamaño total estimado
    $stmt = $pdo->query("SELECT SUM(file_size) FROM expense_attachments WHERE file_size IS NOT NULL");
    $totalSize = $stmt->fetchColumn() ?: 0;
    
    echo "┌─────────────────────────────────────┬─────────────┐\n";
    echo "│ " . str_pad("Total Attachments en BD", 35) . " │ " . str_pad(number_format($totalAttachments), 11) . " │\n";
    echo "│ " . str_pad("Archivos en BackBlaze B2", 35) . " │ " . str_pad(number_format($b2Attachments), 11) . " │\n";
    echo "│ " . str_pad("Archivos Locales", 35) . " │ " . str_pad(number_format($localAttachments), 11) . " │\n";
    echo "│ " . str_pad("Total Gastos", 35) . " │ " . str_pad(number_format($totalExpenses), 11) . " │\n";
    echo "│ " . str_pad("Registros Huérfanos", 35) . " │ " . str_pad(number_format($orphanedRecords), 11) . " │\n";
    echo "│ " . str_pad("Tamaño Total Estimado", 35) . " │ " . str_pad(formatBytes($totalSize), 11) . " │\n";
    echo "└─────────────────────────────────────┴─────────────┘\n";
    echo "\n";
    
    if ($orphanedRecords > 0) {
        printColor("⚠️  Se encontraron $orphanedRecords registros huérfanos", Colors::YELLOW);
        printColor("💡 Ejecuta --analyze para más detalles", Colors::CYAN);
    } else {
        printColor("✅ No se encontraron registros huérfanos", Colors::GREEN);
    }
}

// 🔍 FUNCIÓN: Analizar archivos huérfanos
function analyzeOrphans($pdo, $options) {
    printColor("🔍 ANÁLISIS DE ARCHIVOS HUÉRFANOS", Colors::BOLD);
    echo "\n";
    
    $totalOrphans = 0;
    $estimatedSpace = 0;
    
    // 1. Registros en BD sin gasto asociado
    printColor("1. 🗄️ Registros en BD sin Gasto Asociado", Colors::CYAN);
    
    $ageCondition = $options['min-age'] > 0 ? 
        "AND ea.created_at < DATE_SUB(NOW(), INTERVAL {$options['min-age']} DAY)" : "";
    
    $stmt = $pdo->query("
        SELECT ea.*, e.id as expense_exists 
        FROM expense_attachments ea 
        LEFT JOIN expenses e ON ea.expense_id = e.id 
        WHERE e.id IS NULL 
        $ageCondition
        LIMIT {$options['max-files']}
    ");
    $orphanedRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($orphanedRecords)) {
        printColor("   ✅ No se encontraron registros huérfanos", Colors::GREEN);
    } else {
        printColor("   ⚠️  Encontrados " . count($orphanedRecords) . " registros huérfanos", Colors::YELLOW);
        $totalOrphans += count($orphanedRecords);
        
        if ($options['verbose']) {
            foreach ($orphanedRecords as $record) {
                echo "   • ID: {$record['id']} | Archivo: {$record['original_filename']} | Gasto: {$record['expense_id']}\n";
            }
        }
    }
    echo "\n";
    
    // 2. Archivos locales sin registro en BD
    printColor("2. 🗂️ Archivos Locales sin Registro en BD", Colors::CYAN);
    
    $uploadDir = 'uploads/expenses/';
    $localOrphans = findLocalOrphans($pdo, $uploadDir, $options['min-age']);
    
    if (empty($localOrphans)) {
        printColor("   ✅ No se encontraron archivos locales huérfanos", Colors::GREEN);
    } else {
        $count = min(count($localOrphans), $options['max-files']);
        printColor("   ⚠️  Encontrados $count archivos locales huérfanos", Colors::YELLOW);
        $totalOrphans += $count;
        
        if ($options['verbose']) {
            foreach (array_slice($localOrphans, 0, $options['max-files']) as $file) {
                $estimatedSpace += $file['size'];
                echo "   • {$file['filename']} | " . formatBytes($file['size']) . " | {$file['date']}\n";
            }
        }
    }
    echo "\n";
    
    // 3. Resumen
    printColor("📊 RESUMEN DEL ANÁLISIS", Colors::BOLD);
    echo "┌─────────────────────────────────────┬─────────────┐\n";
    echo "│ " . str_pad("Total Elementos Huérfanos", 35) . " │ " . str_pad(number_format($totalOrphans), 11) . " │\n";
    echo "│ " . str_pad("Espacio Recuperable Estimado", 35) . " │ " . str_pad(formatBytes($estimatedSpace), 11) . " │\n";
    echo "└─────────────────────────────────────┴─────────────┘\n";
    echo "\n";
    
    if ($totalOrphans > 0) {
        printColor("💡 RECOMENDACIÓN:", Colors::YELLOW);
        echo "   Ejecutar: php cleanup_cli.php --cleanup --dry-run\n";
        echo "   Para simular la limpieza antes de ejecutarla\n";
    } else {
        printColor("🎉 ¡El sistema está limpio!", Colors::GREEN);
    }
}

// 🧹 FUNCIÓN: Ejecutar limpieza
function performCleanup($pdo, $options) {
    $isDryRun = $options['dry-run'];
    $isForce = $options['force'];
    
    if (!$isDryRun && !$isForce) {
        printColor("❌ ERROR: Limpieza real requiere --force", Colors::RED);
        printColor("💡 Use --dry-run para simular o --force para ejecutar", Colors::YELLOW);
        return;
    }
    
    $mode = $isDryRun ? "SIMULACIÓN" : "LIMPIEZA REAL";
    printColor("🧹 $mode DE ARCHIVOS HUÉRFANOS", Colors::BOLD);
    echo "\n";
    
    if (!$isDryRun) {
        printColor("⚠️  ADVERTENCIA: Esta operación eliminará archivos permanentemente", Colors::RED);
        echo "Presiona ENTER para continuar o Ctrl+C para cancelar...";
        fgets(STDIN);
    }
    
    $stats = [
        'db_records_cleaned' => 0,
        'local_files_cleaned' => 0,
        'space_recovered' => 0,
        'errors' => 0
    ];
    
    try {
        if (!$isDryRun) {
            $pdo->beginTransaction();
        }
        
        // Limpiar registros de BD
        printColor("🗄️ Procesando registros huérfanos en BD...", Colors::CYAN);
        
        $ageCondition = $options['min-age'] > 0 ? 
            "AND ea.created_at < DATE_SUB(NOW(), INTERVAL {$options['min-age']} DAY)" : "";
        
        $stmt = $pdo->query("
            SELECT ea.* 
            FROM expense_attachments ea 
            LEFT JOIN expenses e ON ea.expense_id = e.id 
            WHERE e.id IS NULL 
            $ageCondition
            LIMIT {$options['max-files']}
        ");
        $orphanedRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($orphanedRecords as $record) {
            try {
                if (!$isDryRun) {
                    // Eliminar archivo asociado
                    if (!empty($record['file_key'])) {
                        $uploader = new B2FileUploader();
                        $result = $uploader->deleteFile($record['file_key']);
                        if (!$result['success'] && $options['verbose']) {
                            printColor("   ⚠️  Error eliminando de B2: {$record['file_key']}", Colors::YELLOW);
                        }
                    } elseif (!empty($record['file_path']) && file_exists($record['file_path'])) {
                        $fileSize = filesize($record['file_path']);
                        unlink($record['file_path']);
                        $stats['space_recovered'] += $fileSize;
                    }
                    
                    // Eliminar registro de BD
                    $deleteStmt = $pdo->prepare("DELETE FROM expense_attachments WHERE id = ?");
                    $deleteStmt->execute([$record['id']]);
                }
                
                $stats['db_records_cleaned']++;
                if ($options['verbose']) {
                    $action = $isDryRun ? "Sería eliminado" : "Eliminado";
                    echo "   ✅ $action: {$record['original_filename']}\n";
                }
                
            } catch (Exception $e) {
                $stats['errors']++;
                if ($options['verbose']) {
                    printColor("   ❌ Error: {$record['original_filename']} - " . $e->getMessage(), Colors::RED);
                }
            }
        }
        
        // Limpiar archivos locales
        printColor("🗂️ Procesando archivos locales huérfanos...", Colors::CYAN);
        
        $uploadDir = 'uploads/expenses/';
        $localOrphans = findLocalOrphans($pdo, $uploadDir, $options['min-age']);
        
        foreach (array_slice($localOrphans, 0, $options['max-files']) as $file) {
            try {
                if (!$isDryRun) {
                    unlink($file['full_path']);
                    $stats['space_recovered'] += $file['size'];
                }
                
                $stats['local_files_cleaned']++;
                if ($options['verbose']) {
                    $action = $isDryRun ? "Sería eliminado" : "Eliminado";
                    echo "   ✅ $action: {$file['filename']} (" . formatBytes($file['size']) . ")\n";
                }
                
            } catch (Exception $e) {
                $stats['errors']++;
                if ($options['verbose']) {
                    printColor("   ❌ Error: {$file['filename']} - " . $e->getMessage(), Colors::RED);
                }
            }
        }
        
        if (!$isDryRun) {
            $pdo->commit();
        }
        
        // Mostrar resumen
        echo "\n";
        printColor("📊 RESUMEN DE " . ($isDryRun ? "SIMULACIÓN" : "LIMPIEZA"), Colors::BOLD);
        echo "┌─────────────────────────────────────┬─────────────┐\n";
        echo "│ " . str_pad("Registros BD " . ($isDryRun ? "simulados" : "eliminados"), 35) . " │ " . str_pad(number_format($stats['db_records_cleaned']), 11) . " │\n";
        echo "│ " . str_pad("Archivos locales " . ($isDryRun ? "simulados" : "eliminados"), 35) . " │ " . str_pad(number_format($stats['local_files_cleaned']), 11) . " │\n";
        echo "│ " . str_pad("Espacio " . ($isDryRun ? "recuperable" : "recuperado"), 35) . " │ " . str_pad(formatBytes($stats['space_recovered']), 11) . " │\n";
        echo "│ " . str_pad("Errores", 35) . " │ " . str_pad(number_format($stats['errors']), 11) . " │\n";
        echo "└─────────────────────────────────────┴─────────────┘\n";
        echo "\n";
        
        if ($isDryRun && ($stats['db_records_cleaned'] > 0 || $stats['local_files_cleaned'] > 0)) {
            printColor("💡 Para ejecutar la limpieza real:", Colors::CYAN);
            echo "   php cleanup_cli.php --cleanup --force\n";
        } elseif (!$isDryRun) {
            printColor("🎉 Limpieza completada exitosamente", Colors::GREEN);
        }
        
    } catch (Exception $e) {
        if (!$isDryRun) {
            $pdo->rollBack();
        }
        printColor("💥 Error durante la limpieza: " . $e->getMessage(), Colors::RED);
    }
}

// 🔍 FUNCIÓN: Encontrar archivos locales huérfanos
function findLocalOrphans($pdo, $uploadDir, $minAgeDays) {
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
        
        if (filemtime($fullPath) > $minTimestamp) continue;
        
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

// 🛠️ FUNCIÓN: Formatear bytes
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

// 🚀 FUNCIÓN PRINCIPAL
function main($argv) {
    $options = parseArguments($argv);
    
    if ($options['help'] || count($argv) === 1) {
        showHelp();
        return;
    }
    
    try {
        $pdo = getConnection();
        
        if ($options['stats']) {
            showStats($pdo);
        } elseif ($options['analyze']) {
            analyzeOrphans($pdo, $options);
        } elseif ($options['cleanup']) {
            performCleanup($pdo, $options);
        } elseif ($options['verify-b2']) {
            printColor("☁️ Verificación de B2 - En desarrollo", Colors::YELLOW);
        } else {
            printColor("❌ Debe especificar una acción", Colors::RED);
            printColor("Use --help para ver las opciones disponibles", Colors::YELLOW);
        }
        
    } catch (Exception $e) {
        printColor("💥 Error: " . $e->getMessage(), Colors::RED);
        exit(1);
    }
}

// 🎯 EJECUTAR
main($argv);

?>
