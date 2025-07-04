<?php
require_once '../../config.php';
require_once '../../includes/B2FileUploader.php';

// Verificar autenticación
checkAPIAuthentication();

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'uploadFiles':
        uploadFiles();
        break;
    case 'uploadSingleFile':
        uploadSingleFile();
        break;
    case 'downloadFile':
        downloadFile();
        break;
    case 'deleteFile':
        deleteFile();
        break;
    case 'getSignedUrl':
        getSignedUrl();
        break;
    case 'testConnection':
        testConnection();
        break;
    case 'getExpenseOptions':
        getExpenseOptions();
        break;
    case 'getAttachments':
        getAttachments();
        break;
    default:
        echo json_encode(['error' => 'Acción no válida']);
}

/**
 * Subir múltiples archivos a BackBlaze B2
 */
function uploadFiles() {
    try {
        // Validar parámetros requeridos
        if (!isset($_POST['expense_id']) || !isset($_POST['expense_date'])) {
            echo json_encode(['success' => false, 'error' => 'Parámetros requeridos: expense_id, expense_date']);
            return;
        }
        
        if (!isset($_FILES['files']) || empty($_FILES['files']['name'])) {
            echo json_encode(['success' => false, 'error' => 'No se proporcionaron archivos']);
            return;
        }
        
        $expenseId = $_POST['expense_id'];
        $expenseDate = $_POST['expense_date'];
        
        // Crear instancia del uploader
        $uploader = new B2FileUploader();
        
        // Subir archivos
        $result = $uploader->uploadMultipleFiles($_FILES['files'], $expenseId, $expenseDate);
        
        if ($result['success']) {
            // Guardar información en base de datos
            $savedFiles = saveFilesToDatabase($result['uploaded'], $expenseId);
            
            echo json_encode([
                'success' => true,
                'message' => "{$result['total_uploaded']} archivo(s) subido(s) exitosamente",
                'files' => $savedFiles,
                'total_uploaded' => $result['total_uploaded'],
                'total_errors' => $result['total_errors'],
                'errors' => $result['errors']
            ]);
        } else {
            echo json_encode($result);
        }
        
    } catch (Exception $e) {
        error_log("Error uploading files: " . $e->getMessage());
        error_log("Error trace: " . $e->getTraceAsString());
        echo json_encode([
            'success' => false, 
            'error' => 'Error interno del servidor',
            'debug_message' => $e->getMessage(),
            'debug_file' => $e->getFile(),
            'debug_line' => $e->getLine()
        ]);
    }
}

/**
 * Subir un solo archivo a BackBlaze B2
 */
function uploadSingleFile() {
    try {
        // Validar parámetros requeridos
        if (!isset($_POST['expense_id']) || !isset($_POST['expense_date'])) {
            echo json_encode(['success' => false, 'error' => 'Parámetros requeridos: expense_id, expense_date']);
            return;
        }
        
        if (!isset($_FILES['file']) || empty($_FILES['file']['name'])) {
            echo json_encode(['success' => false, 'error' => 'No se proporcionó archivo']);
            return;
        }
        
        $expenseId = $_POST['expense_id'];
        $expenseDate = $_POST['expense_date'];
        
        // Crear instancia del uploader
        $uploader = new B2FileUploader();
        
        // Subir archivo
        $result = $uploader->uploadFile($_FILES['file'], $expenseId, $expenseDate);
        
        if ($result['success']) {
            // Guardar información en base de datos
            $savedFile = saveFileToDatabase($result, $expenseId);
            
            echo json_encode([
                'success' => true,
                'message' => 'Archivo subido exitosamente',
                'file' => $savedFile
            ]);
        } else {
            echo json_encode($result);
        }
        
    } catch (Exception $e) {
        error_log("Error uploading file: " . $e->getMessage());
        error_log("Error trace: " . $e->getTraceAsString());
        echo json_encode([
            'success' => false, 
            'error' => 'Error interno del servidor',
            'debug_message' => $e->getMessage(),
            'debug_file' => $e->getFile(),
            'debug_line' => $e->getLine()
        ]);
    }
}

/**
 * Descargar archivo desde BackBlaze B2
 */
function downloadFile() {
    try {
        $fileId = $_GET['file_id'] ?? '';
        
        if (empty($fileId)) {
            echo json_encode(['success' => false, 'error' => 'ID de archivo requerido']);
            return;
        }
        
        // Obtener información del archivo desde base de datos
        $fileInfo = getFileInfo($fileId);
        
        if (!$fileInfo) {
            echo json_encode(['success' => false, 'error' => 'Archivo no encontrado']);
            return;
        }
        
        // Crear instancia del uploader
        $uploader = new B2FileUploader();
        
        // Descargar archivo
        $result = $uploader->downloadFile($fileInfo['file_key']);
        
        if ($result['success']) {
            // Enviar archivo al navegador
            header('Content-Type: ' . $result['content_type']);
            header('Content-Disposition: attachment; filename="' . $fileInfo['original_filename'] . '"');
            header('Content-Length: ' . strlen($result['content']));
            
            echo $result['content'];
        } else {
            echo json_encode($result);
        }
        
    } catch (Exception $e) {
        error_log("Error downloading file: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Error interno del servidor']);
    }
}

/**
 * Eliminar archivo de BackBlaze B2
 */
function deleteFile() {
    try {
        // Obtener datos del POST (puede ser JSON o form-data)
        $input = json_decode(file_get_contents('php://input'), true);
        
        $fileId = $input['file_id'] ?? $_POST['file_id'] ?? '';
        $fileKey = $input['file_key'] ?? $_POST['file_key'] ?? '';
        $attachmentId = $input['attachment_id'] ?? $_POST['attachment_id'] ?? '';
        
        // Priorizar attachment_id si está presente
        if (!empty($attachmentId)) {
            $targetId = $attachmentId;
        } elseif (!empty($fileId)) {
            $targetId = $fileId;
        } else {
            echo json_encode(['success' => false, 'error' => 'ID de archivo requerido']);
            return;
        }
        
        // Obtener información del archivo desde base de datos
        $fileInfo = getFileInfo($targetId);
        
        if (!$fileInfo) {
            echo json_encode(['success' => false, 'error' => 'Archivo no encontrado']);
            return;
        }
        
        // Usar file_key proporcionado o el de la base de datos
        $targetKey = !empty($fileKey) ? $fileKey : $fileInfo['file_key'];
        
        // Crear instancia del uploader
        $uploader = new B2FileUploader();
        
        // Eliminar archivo de B2
        $result = $uploader->deleteFile($targetKey);
        
        if ($result['success']) {
            // Eliminar registro de base de datos
            deleteFileFromDatabase($targetId);
            
            echo json_encode([
                'success' => true,
                'message' => 'Archivo eliminado exitosamente'
            ]);
        } else {
            echo json_encode($result);
        }
        
    } catch (Exception $e) {
        error_log("Error deleting file: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Error interno del servidor']);
    }
}

/**
 * Obtener URL firmada para acceso temporal
 */
function getSignedUrl() {
    try {
        $fileId = $_GET['file_id'] ?? '';
        $fileKey = $_GET['file_key'] ?? '';
        $expiration = $_GET['expiration'] ?? '+1 hour';
        
        // Priorizar file_key si está presente
        if (!empty($fileKey)) {
            $targetKey = $fileKey;
        } elseif (!empty($fileId)) {
            // Obtener información del archivo desde base de datos
            $fileInfo = getFileInfo($fileId);
            
            if (!$fileInfo) {
                echo json_encode(['success' => false, 'error' => 'Archivo no encontrado']);
                return;
            }
            
            $targetKey = $fileInfo['file_key'];
        } else {
            echo json_encode(['success' => false, 'error' => 'ID de archivo o file_key requerido']);
            return;
        }
        
        // Crear instancia del uploader
        $uploader = new B2FileUploader();
        
        // Generar URL firmada
        $signedUrl = $uploader->getSignedUrl($targetKey, $expiration);
        
        if ($signedUrl) {
            echo json_encode([
                'success' => true,
                'signed_url' => $signedUrl,
                'expires' => $expiration,
                'file_key' => $targetKey
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Error generando URL']);
        }
        
    } catch (Exception $e) {
        error_log("Error generating signed URL: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Error interno del servidor']);
    }
}

/**
 * Probar conexión con BackBlaze B2
 */
function testConnection() {
    try {
        $uploader = new B2FileUploader();
        $result = $uploader->testConnection();
        
        if ($result['success']) {
            echo json_encode([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'endpoint' => $result['endpoint'],
                    'region' => $result['region'], 
                    'bucket' => $result['bucket'],
                    'bucket_id' => $result['bucket_id'],
                    'status' => 'Connected',
                    'test_upload' => 'OK'
                ]
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => $result['message'],
                'error' => $result['error'] ?? 'Unknown error',
                'aws_error_code' => $result['aws_error_code'] ?? null,
                'aws_error_type' => $result['aws_error_type'] ?? null
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Error testing B2 connection: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => 'Error inicializando BackBlaze B2: ' . $e->getMessage()
        ]);
    }
}

/**
 * Guardar información de archivos en base de datos
 */
function saveFilesToDatabase($files, $expenseId) {
    $pdo = getConnection();
    $savedFiles = [];
    
    try {
        foreach ($files as $file) {
            $attachmentId = generateUUID();
            
            $stmt = $pdo->prepare("
                INSERT INTO expense_attachments (
                    id, expense_id, filename, original_filename, file_path, 
                    file_size, mime_type, file_key, compressed, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $attachmentId,
                $expenseId,
                $file['file_name'],
                $file['original_name'],
                $file['file_url'], // Guardar URL completa en file_path
                $file['file_size'],
                $file['mime_type'],
                $file['file_key'], // Clave para acceso directo en B2
                $file['compressed'] ? 1 : 0
            ]);
            
            $savedFiles[] = [
                'id' => $attachmentId,
                'filename' => $file['file_name'],
                'original_filename' => $file['original_name'],
                'file_size' => $file['file_size'],
                'mime_type' => $file['mime_type'],
                'compressed' => $file['compressed'],
                'file_key' => $file['file_key']
            ];
        }
        
        return $savedFiles;
        
    } catch (Exception $e) {
        error_log("Error saving files to database: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Guardar un archivo en base de datos
 */
function saveFileToDatabase($file, $expenseId) {
    $pdo = getConnection();
    
    try {
        $attachmentId = generateUUID();
        
        $stmt = $pdo->prepare("
            INSERT INTO expense_attachments (
                id, expense_id, filename, original_filename, file_path, 
                file_size, mime_type, file_key, compressed, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $attachmentId,
            $expenseId,
            $file['file_name'],
            $file['original_name'],
            $file['file_url'], // Guardar URL completa en file_path
            $file['file_size'],
            $file['mime_type'],
            $file['file_key'], // Clave para acceso directo en B2
            $file['compressed'] ? 1 : 0
        ]);
        
        return [
            'id' => $attachmentId,
            'filename' => $file['file_name'],
            'original_filename' => $file['original_name'],
            'file_size' => $file['file_size'],
            'mime_type' => $file['mime_type'],
            'compressed' => $file['compressed'],
            'file_key' => $file['file_key']
        ];
        
    } catch (Exception $e) {
        error_log("Error saving file to database: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Obtener información de archivo desde base de datos
 */
function getFileInfo($fileId) {
    $pdo = getConnection();
    
    try {
        $stmt = $pdo->prepare("
            SELECT id, filename, original_filename, file_path, file_size, 
                   mime_type, file_key, compressed, created_at
            FROM expense_attachments 
            WHERE id = ?
        ");
        $stmt->execute([$fileId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Error getting file info: " . $e->getMessage());
        return null;
    }
}

/**
 * Eliminar archivo de base de datos
 */
function deleteFileFromDatabase($fileId) {
    $pdo = getConnection();
    
    try {
        $stmt = $pdo->prepare("DELETE FROM expense_attachments WHERE id = ?");
        $stmt->execute([$fileId]);
        
    } catch (Exception $e) {
        error_log("Error deleting file from database: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Generar UUID para archivos
 */
function generateUUID() {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

/**
 * Obtener opciones de expenses para el dropdown
 */
function getExpenseOptions() {
    try {
        $pdo = getConnection();
        
        // Obtener expenses que tengan attachments o que sean expenses de prueba
        $stmt = $pdo->prepare("
            SELECT DISTINCT e.id, e.description, e.expense_date, e.amount,
                   COUNT(ea.id) as attachment_count
            FROM expenses e
            LEFT JOIN expense_attachments ea ON e.id = ea.expense_id
            WHERE e.id LIKE 'test-expense-%' OR ea.id IS NOT NULL
            GROUP BY e.id, e.description, e.expense_date, e.amount
            ORDER BY e.expense_date DESC, e.created_at DESC
            LIMIT 50
        ");
        
        $stmt->execute();
        $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Formatear los datos para el dropdown
        $formattedExpenses = [];
        foreach ($expenses as $expense) {
            $formattedExpenses[] = [
                'id' => $expense['id'],
                'description' => $expense['description'] ?: 'Sin descripción',
                'expense_date' => $expense['expense_date'],
                'amount' => $expense['amount'],
                'attachment_count' => (int)$expense['attachment_count']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'expenses' => $formattedExpenses,
            'total' => count($formattedExpenses)
        ]);
        
    } catch (Exception $e) {
        error_log("Error getting expense options: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'error' => 'Error obteniendo opciones de expenses'
        ]);
    }
}

/**
 * Obtener attachments de un expense específico
 */
function getAttachments() {
    try {
        $expenseId = $_GET['expense_id'] ?? '';
        
        if (empty($expenseId)) {
            echo json_encode(['success' => false, 'error' => 'ID de expense requerido']);
            return;
        }
        
        $pdo = getConnection();
        
        $stmt = $pdo->prepare("
            SELECT id, filename, original_filename, file_path, file_size, 
                   mime_type, file_key, compressed, created_at
            FROM expense_attachments 
            WHERE expense_id = ?
            ORDER BY created_at DESC
        ");
        
        $stmt->execute([$expenseId]);
        $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Formatear los datos para el frontend
        $formattedAttachments = [];
        foreach ($attachments as $attachment) {
            $formattedAttachments[] = [
                'id' => $attachment['id'],
                'filename' => $attachment['filename'],
                'original_name' => $attachment['original_filename'],
                'file_size' => (int)$attachment['file_size'],
                'mime_type' => $attachment['mime_type'],
                'file_key' => $attachment['file_key'],
                'compressed' => (bool)$attachment['compressed'],
                'created_at' => $attachment['created_at']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'attachments' => $formattedAttachments,
            'expense_id' => $expenseId,
            'total' => count($formattedAttachments)
        ]);
        
    } catch (Exception $e) {
        error_log("Error getting attachments: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'error' => 'Error obteniendo attachments'
        ]);
    }
}
?> 