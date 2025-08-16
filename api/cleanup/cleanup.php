<?php
/**
 * API Endpoint para operaciones de limpieza
 * Devuelve datos JSON para consumo por JavaScript
 */

require_once '../../config.php';
require_once '../../includes/B2FileUploader.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

// Iniciar sesión si no está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Usuario no autenticado']);
    exit;
}

try {
    $pdo = getConnection();
    
    // Verificar rol de administrador
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userRole = $stmt->fetchColumn();
    
    if ($userRole !== 'admin' && $userRole !== 'administrator') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acceso denegado - Solo administradores']);
        exit;
    }
    
    $action = $_GET['action'] ?? 'analyze';
    $dryRun = ($_GET['dry_run'] ?? 'true') === 'true';
    
    $startTime = microtime(true);
    
    // Obtener estadísticas del sistema
    $stats = [
        'totalScanned' => 0,
        'orphanedFiles' => 0,
        'diskSpaceRecoverable' => '0 MB',
        'processingTime' => '0 segundos',
        'systemHealth' => 'Desconocido'
    ];
    
    // Contar archivos en la base de datos
    $stmt = $pdo->query("SELECT COUNT(*) FROM expense_attachments");
    $totalAttachments = $stmt->fetchColumn();
    $stats['totalScanned'] = $totalAttachments;
    
    // Buscar archivos huérfanos (registros sin archivo físico)
    $orphanedRecords = [];
    $stmt = $pdo->query("
        SELECT id, expense_id, filename, original_filename, file_path, file_size, created_at 
        FROM expense_attachments 
        ORDER BY created_at DESC
    ");
    
    $attachments = $stmt->fetchAll();
    $totalSize = 0;
    
    foreach ($attachments as $attachment) {
        // Verificar si el archivo existe físicamente
        $filePath = $attachment['file_path'];
        
        // Para archivos locales
        if (strpos($filePath, 'uploads/') === 0 && !file_exists($filePath)) {
            $orphanedRecords[] = $attachment;
            $totalSize += (int)$attachment['file_size'];
        }
        // Para archivos B2 (verificación simplificada por ahora)
        else if (strpos($filePath, 'https://') === 0) {
            // TODO: Implementar verificación real de B2
            // Por ahora asumimos que existen
        }
    }
    
    $stats['orphanedFiles'] = count($orphanedRecords);
    $stats['diskSpaceRecoverable'] = round($totalSize / (1024 * 1024), 2) . ' MB';
    
    $endTime = microtime(true);
    $stats['processingTime'] = round($endTime - $startTime, 2) . ' segundos';
    
    // Determinar salud del sistema
    if ($stats['orphanedFiles'] === 0) {
        $stats['systemHealth'] = 'Excelente';
    } else if ($stats['orphanedFiles'] < 5) {
        $stats['systemHealth'] = 'Bueno';
    } else if ($stats['orphanedFiles'] < 20) {
        $stats['systemHealth'] = 'Regular';
    } else {
        $stats['systemHealth'] = 'Requiere atención';
    }
    
    $response = [
        'success' => true,
        'action' => $action,
        'dry_run' => $dryRun,
        'data' => $stats
    ];
    
    // Para simulación, agregar detalles de archivos
    if ($action === 'simulate' && !empty($orphanedRecords)) {
        $response['data']['wouldDelete'] = array_slice(array_map(function($record) {
            return [
                'name' => $record['original_filename'] ?: $record['filename'],
                'size' => round((int)$record['file_size'] / 1024, 1) . ' KB',
                'type' => getFileType($record['original_filename'] ?: $record['filename']),
                'orphanedSince' => $record['created_at']
            ];
        }, $orphanedRecords), 0, 10); // Limitar a 10 archivos
        
        $response['data']['impact'] = [
            'spaceFreed' => $stats['diskSpaceRecoverable'],
            'costSavings' => '$' . round(($totalSize / (1024 * 1024)) * 0.008, 2) . '/mes',
            'performanceGain' => count($orphanedRecords) > 10 ? '3-5% mejora estimada' : '1-2% mejora estimada'
        ];
    }
    
    // Para ejecución real (no dry run)
    if ($action === 'execute' && !$dryRun) {
        $deletedCount = 0;
        
        foreach ($orphanedRecords as $record) {
            try {
                // Eliminar archivo físico si existe
                if (file_exists($record['file_path'])) {
                    unlink($record['file_path']);
                }
                
                // Eliminar registro de la base de datos
                $deleteStmt = $pdo->prepare("DELETE FROM expense_attachments WHERE id = ?");
                $deleteStmt->execute([$record['id']]);
                
                $deletedCount++;
            } catch (Exception $e) {
                error_log("Error eliminando archivo {$record['original_filename']}: " . $e->getMessage());
            }
        }
        
        $response['data']['filesDeleted'] = $deletedCount;
        $response['message'] = "Limpieza completada: {$deletedCount} archivos eliminados";
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    error_log("Error en cleanup API: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor: ' . $e->getMessage()
    ]);
}

function getFileType($filename) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
        return 'image';
    } else if (in_array($extension, ['pdf', 'doc', 'docx', 'txt'])) {
        return 'document';
    } else if (in_array($extension, ['zip', 'rar', '7z', 'tar'])) {
        return 'archive';
    } else {
        return 'other';
    }
}
?>
