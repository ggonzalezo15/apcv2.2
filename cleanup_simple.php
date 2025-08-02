#!/usr/bin/env php
<?php
/**
 * 🖥️ HERRAMIENTA CLI SIMPLIFICADA DE LIMPIEZA
 * 
 * Versión simplificada que funciona sin dependencias externas problemáticas
 */

// Verificar que se ejecuta desde CLI
if (php_sapi_name() !== 'cli') {
    die("❌ Este script solo puede ejecutarse desde línea de comandos\n");
}

require_once 'config_cli.php';

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
    printColor("🧹 HERRAMIENTA SIMPLIFICADA DE LIMPIEZA", Colors::BOLD);
    echo "\n";
    printColor("USO:", Colors::CYAN);
    echo "  php cleanup_simple.php [OPCIONES]\n\n";
    
    printColor("OPCIONES:", Colors::CYAN);
    echo "  --help              Mostrar esta ayuda\n";
    echo "  --analyze           Analizar archivos huérfanos (solo lectura)\n";
    echo "  --cleanup           Ejecutar limpieza de registros BD\n";
    echo "  --stats             Mostrar estadísticas del sistema\n";
    echo "  --dry-run           Simular operaciones sin ejecutar cambios\n";
    echo "  --force             Ejecutar limpieza real\n";
    echo "\n";
    
    printColor("EJEMPLOS:", Colors::CYAN);
    echo "  php cleanup_simple.php --stats\n";
    echo "  php cleanup_simple.php --analyze\n";
    echo "  php cleanup_simple.php --cleanup --dry-run\n";
    echo "  php cleanup_simple.php --cleanup --force\n";
    echo "\n";
}

// ⚙️ PARSEAR ARGUMENTOS
function parseArguments($argv) {
    $options = [
        'help' => false,
        'analyze' => false,
        'cleanup' => false,
        'stats' => false,
        'dry-run' => false,
        'force' => false
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
        } elseif ($arg === '--dry-run') {
            $options['dry-run'] = true;
        } elseif ($arg === '--force') {
            $options['force'] = true;
        } else {
            printColor("❌ Opción desconocida: $arg", Colors::RED);
            exit(1);
        }
    }
    
    return $options;
}

// 📊 FUNCIÓN: Mostrar estadísticas
function showStats($pdo) {
    printColor("📊 ESTADÍSTICAS DEL SISTEMA", Colors::BOLD);
    echo "\n";
    
    try {
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
        
    } catch (Exception $e) {
        printColor("❌ Error obteniendo estadísticas: " . $e->getMessage(), Colors::RED);
    }
}

// 🔍 FUNCIÓN: Analizar archivos huérfanos
function analyzeOrphans($pdo) {
    printColor("🔍 ANÁLISIS DE ARCHIVOS HUÉRFANOS", Colors::BOLD);
    echo "\n";
    
    try {
        // 1. Registros en BD sin gasto asociado
        printColor("1. 🗄️ Registros en BD sin Gasto Asociado", Colors::CYAN);
        
        $stmt = $pdo->query("
            SELECT ea.id, ea.original_filename, ea.expense_id, ea.file_size, ea.created_at
            FROM expense_attachments ea 
            LEFT JOIN expenses e ON ea.expense_id = e.id 
            WHERE e.id IS NULL
            ORDER BY ea.created_at DESC
            LIMIT 50
        ");
        $orphanedRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($orphanedRecords)) {
            printColor("   ✅ No se encontraron registros huérfanos", Colors::GREEN);
        } else {
            printColor("   ⚠️  Encontrados " . count($orphanedRecords) . " registros huérfanos", Colors::YELLOW);
            
            $totalSize = 0;
            foreach ($orphanedRecords as $record) {
                $totalSize += $record['file_size'] ?: 0;
                echo "   • ID: {$record['id']} | {$record['original_filename']} | " . 
                     formatBytes($record['file_size'] ?: 0) . " | {$record['created_at']}\n";
            }
            
            echo "\n   📊 Espacio total de archivos huérfanos: " . formatBytes($totalSize) . "\n";
        }
        echo "\n";
        
        // 2. Archivos locales sin registro en BD
        printColor("2. 🗂️ Archivos Locales sin Registro en BD", Colors::CYAN);
        
        $localOrphans = findLocalOrphans($pdo, 'uploads/expenses/');
        
        if (empty($localOrphans)) {
            printColor("   ✅ No se encontraron archivos locales huérfanos", Colors::GREEN);
        } else {
            printColor("   ⚠️  Encontrados " . count($localOrphans) . " archivos locales huérfanos", Colors::YELLOW);
            
            $totalLocalSize = 0;
            foreach ($localOrphans as $file) {
                $totalLocalSize += $file['size'];
                echo "   • {$file['filename']} | " . formatBytes($file['size']) . " | {$file['date']}\n";
            }
            
            echo "\n   📊 Espacio total de archivos locales huérfanos: " . formatBytes($totalLocalSize) . "\n";
        }
        echo "\n";
        
        // Resumen
        printColor("📊 RESUMEN DEL ANÁLISIS", Colors::BOLD);
        $totalOrphans = count($orphanedRecords) + count($localOrphans);
        $totalRecoverableSpace = $totalSize + ($totalLocalSize ?? 0);
        
        echo "┌─────────────────────────────────────┬─────────────┐\n";
        echo "│ " . str_pad("Total Elementos Huérfanos", 35) . " │ " . str_pad(number_format($totalOrphans), 11) . " │\n";
        echo "│ " . str_pad("Espacio Recuperable", 35) . " │ " . str_pad(formatBytes($totalRecoverableSpace), 11) . " │\n";
        echo "└─────────────────────────────────────┴─────────────┘\n";
        echo "\n";
        
        if ($totalOrphans > 0) {
            printColor("💡 RECOMENDACIÓN:", Colors::YELLOW);
            echo "   php cleanup_simple.php --cleanup --dry-run\n";
        } else {
            printColor("🎉 ¡El sistema está limpio!", Colors::GREEN);
        }
        
    } catch (Exception $e) {
        printColor("❌ Error durante el análisis: " . $e->getMessage(), Colors::RED);
    }
}

// 🧹 FUNCIÓN: Ejecutar limpieza
function performCleanup($pdo, $isDryRun, $isForce) {
    if (!$isDryRun && !$isForce) {
        printColor("❌ ERROR: Limpieza real requiere --force", Colors::RED);
        printColor("💡 Use --dry-run para simular o --force para ejecutar", Colors::YELLOW);
        return;
    }
    
    $mode = $isDryRun ? "SIMULACIÓN" : "LIMPIEZA REAL";
    printColor("🧹 $mode DE ARCHIVOS HUÉRFANOS", Colors::BOLD);
    echo "\n";
    
    if (!$isDryRun) {
        printColor("⚠️  ADVERTENCIA: Esta operación eliminará registros permanentemente", Colors::RED);
        echo "Presiona ENTER para continuar o Ctrl+C para cancelar...";
        fgets(STDIN);
    }
    
    try {
        if (!$isDryRun) {
            $pdo->beginTransaction();
        }
        
        // Obtener registros huérfanos
        $stmt = $pdo->query("
            SELECT ea.id, ea.original_filename, ea.file_path, ea.file_size
            FROM expense_attachments ea 
            LEFT JOIN expenses e ON ea.expense_id = e.id 
            WHERE e.id IS NULL
            LIMIT 100
        ");
        $orphanedRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $cleaned = 0;
        $spaceRecovered = 0;
        $errors = 0;
        
        printColor("🗄️ Procesando registros huérfanos en BD...", Colors::CYAN);
        
        foreach ($orphanedRecords as $record) {
            try {
                if (!$isDryRun) {
                    // Eliminar archivo local si existe
                    if (!empty($record['file_path']) && file_exists($record['file_path'])) {
                        unlink($record['file_path']);
                        $spaceRecovered += $record['file_size'] ?: 0;
                    }
                    
                    // Eliminar registro de BD
                    $deleteStmt = $pdo->prepare("DELETE FROM expense_attachments WHERE id = ?");
                    $deleteStmt->execute([$record['id']]);
                }
                
                $cleaned++;
                $action = $isDryRun ? "Sería eliminado" : "Eliminado";
                echo "   ✅ $action: {$record['original_filename']}\n";
                
            } catch (Exception $e) {
                $errors++;
                echo "   ❌ Error: {$record['original_filename']} - " . $e->getMessage() . "\n";
            }
        }
        
        if (!$isDryRun) {
            $pdo->commit();
        }
        
        // Mostrar resumen
        echo "\n";
        printColor("📊 RESUMEN DE " . ($isDryRun ? "SIMULACIÓN" : "LIMPIEZA"), Colors::BOLD);
        echo "┌─────────────────────────────────────┬─────────────┐\n";
        echo "│ " . str_pad("Registros " . ($isDryRun ? "simulados" : "eliminados"), 35) . " │ " . str_pad(number_format($cleaned), 11) . " │\n";
        echo "│ " . str_pad("Espacio " . ($isDryRun ? "recuperable" : "recuperado"), 35) . " │ " . str_pad(formatBytes($spaceRecovered), 11) . " │\n";
        echo "│ " . str_pad("Errores", 35) . " │ " . str_pad(number_format($errors), 11) . " │\n";
        echo "└─────────────────────────────────────┴─────────────┘\n";
        echo "\n";
        
        if ($isDryRun && $cleaned > 0) {
            printColor("💡 Para ejecutar la limpieza real:", Colors::CYAN);
            echo "   php cleanup_simple.php --cleanup --force\n";
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
function findLocalOrphans($pdo, $uploadDir) {
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
            analyzeOrphans($pdo);
        } elseif ($options['cleanup']) {
            performCleanup($pdo, $options['dry-run'], $options['force']);
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
