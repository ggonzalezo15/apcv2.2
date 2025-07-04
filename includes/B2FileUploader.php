<?php
require_once __DIR__ . '/../config.php';

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use Intervention\Image\ImageManager;

class B2FileUploader {
    private $s3Client;
    private $bucket;
    private $imageManager;
    private $config;
    
    public function __construct() {
        try {
            $this->config = getB2Config();
            $this->bucket = $this->config['bucket'];
            $this->imageManager = new ImageManager(['driver' => 'gd']);
            
            // Intentar crear cliente con configuración automática
            $this->initializeClient();
        } catch (Exception $e) {
            error_log("Error in B2FileUploader constructor: " . $e->getMessage());
            error_log("Constructor trace: " . $e->getTraceAsString());
            throw new Exception("Error inicializando B2FileUploader: " . $e->getMessage());
        }
    }
    
    private function initializeClient() {
        try {
            $this->s3Client = new S3Client([
                'version' => 'latest',
                'region' => $this->config['region'],
                'endpoint' => $this->config['endpoint'],
                'credentials' => [
                    'key' => $this->config['credentials']['key'],
                    'secret' => $this->config['credentials']['secret']
                ],
                'use_path_style_endpoint' => true,
                'http' => [
                    'timeout' => 30,
                    'connect_timeout' => 10
                ]
            ]);
            
        } catch (Exception $e) {
            throw new Exception("No se pudo conectar a BackBlaze B2: " . $e->getMessage());
        }
    }
    
    private function extractRegionFromEndpoint($endpoint) {
        if (preg_match('/s3\.([^.]+)\.backblazeb2\.com/', $endpoint, $matches)) {
            return $matches[1];
        }
        return 'us-east-005'; // Fallback por defecto
    }
    
    /**
     * Probar conexión a BackBlaze B2
     * @return array Resultado de la prueba
     */
    public function testConnection() {
        try {
            // Intentar subir un archivo de prueba
            $testKey = 'test_connection_' . time() . '.txt';
            $testContent = 'Test connection - ' . date('Y-m-d H:i:s');
            
            $result = $this->s3Client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $testKey,
                'Body' => $testContent,
                'ContentType' => 'text/plain'
            ]);
            
            // Si llegamos aquí, la conexión fue exitosa
            // Limpiar el archivo de prueba
            $this->s3Client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $testKey
            ]);
            
            return [
                'success' => true,
                'message' => "Conexión exitosa con BackBlaze B2",
                'endpoint' => $this->config['endpoint'],
                'region' => $this->config['region'],
                'bucket' => $this->bucket,
                'bucket_id' => $this->config['bucket_id'] ?? 'N/A'
            ];
            
        } catch (S3Exception $e) {
            return [
                'success' => false,
                'message' => 'Error de conexión a BackBlaze B2',
                'error' => $e->getMessage(),
                'aws_error_code' => $e->getAwsErrorCode(),
                'aws_error_type' => $e->getAwsErrorType()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error general de conexión',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Subir archivo a BackBlaze B2
     * @param array $file Información del archivo ($_FILES)
     * @param string $expenseId ID del gasto
     * @param string $expenseDate Fecha del gasto para crear carpeta por año
     * @return array Resultado del upload
     */
    public function uploadFile($file, $expenseId, $expenseDate) {
        try {
            // Validaciones básicas
            $validation = $this->validateFile($file);
            if (!$validation['valid']) {
                return ['success' => false, 'error' => $validation['error']];
            }
            
            // Obtener año de la fecha del gasto
            $year = date('Y', strtotime($expenseDate));
            
            // Crear carpeta si no existe
            $this->createYearFolder($year);
            
            // Procesar archivo según tipo
            $processedFile = $this->processFile($file, $expenseId);
            if (!$processedFile['success']) {
                return $processedFile;
            }
            
            // Generar nombre único para el archivo
            $fileName = $this->generateUniqueFileName($file['name'], $expenseId);
            
            // Crear key/path en el bucket
            $key = "expenses/{$year}/{$fileName}";
            
            // Subir archivo a B2
            $result = $this->s3Client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
                'Body' => fopen($processedFile['file_path'], 'rb'),
                'ContentType' => $file['type'],
                'Metadata' => [
                    'expense_id' => $expenseId,
                    'original_name' => $file['name'],
                    'upload_date' => date('Y-m-d H:i:s')
                ]
            ]);
            
            // Limpiar archivo temporal procesado
            if (file_exists($processedFile['file_path'])) {
                unlink($processedFile['file_path']);
            }
            
            return [
                'success' => true,
                'file_key' => $key,
                'file_url' => $result['ObjectURL'],
                'file_name' => $fileName,
                'original_name' => $file['name'],
                'file_size' => $processedFile['file_size'],
                'mime_type' => $file['type'],
                'compressed' => $processedFile['compressed'] ?? false
            ];
            
        } catch (S3Exception $e) {
            error_log("Error uploading to B2: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error al subir archivo al storage'];
        } catch (Exception $e) {
            error_log("Error processing file: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error procesando archivo'];
        }
    }
    
    /**
     * Validar archivo antes de procesarlo
     */
    private function validateFile($file) {
        // Verificar errores de upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => 'Error en la subida del archivo'];
        }
        
        // Verificar si las constantes están definidas
        if (!defined('MAX_FILE_SIZE')) {
            error_log("Constante MAX_FILE_SIZE no definida");
            return ['valid' => false, 'error' => 'Configuración de archivos no encontrada'];
        }
        
        if (!defined('ALLOWED_FILE_TYPES')) {
            error_log("Constante ALLOWED_FILE_TYPES no definida");
            return ['valid' => false, 'error' => 'Tipos de archivo permitidos no configurados'];
        }
        
        // Verificar tamaño
        if ($file['size'] > MAX_FILE_SIZE) {
            $maxMB = MAX_FILE_SIZE / (1024 * 1024);
            return ['valid' => false, 'error' => "El archivo excede el tamaño máximo de {$maxMB}MB"];
        }
        
        // Verificar tipo MIME
        if (!in_array($file['type'], ALLOWED_FILE_TYPES)) {
            return ['valid' => false, 'error' => 'Tipo de archivo no permitido'];
        }
        
        // Verificar que el archivo existe
        if (!file_exists($file['tmp_name'])) {
            return ['valid' => false, 'error' => 'Archivo no encontrado'];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Procesar archivo (comprimir imágenes si es necesario)
     */
    private function processFile($file, $expenseId) {
        try {
            $isImage = strpos($file['type'], 'image/') === 0;
            
            if (!$isImage) {
                // Para PDFs, no procesamos, solo retornamos la información
                return [
                    'success' => true,
                    'file_path' => $file['tmp_name'],
                    'file_size' => $file['size'],
                    'compressed' => false
                ];
            }
            
            // Verificar si la compresión está deshabilitada manualmente
            if (file_exists('compression_disabled.flag')) {
                error_log("Image compression manually disabled");
                return [
                    'success' => true,
                    'file_path' => $file['tmp_name'],
                    'file_size' => $file['size'],
                    'compressed' => false,
                    'note' => 'Image compression manually disabled - using original file'
                ];
            }
            
            // Verificar dependencias antes de procesar imagen
            if (!$this->canProcessImages()) {
                error_log("Image processing disabled - dependencies not available");
                return [
                    'success' => true,
                    'file_path' => $file['tmp_name'],
                    'file_size' => $file['size'],
                    'compressed' => false,
                    'note' => 'Image processing disabled - using original file'
                ];
            }
            
            // Comprimir imagen
            $tempDir = sys_get_temp_dir();
            $processedFileName = $expenseId . '_' . uniqid() . '_compressed.' . pathinfo($file['name'], PATHINFO_EXTENSION);
            $processedFilePath = $tempDir . '/' . $processedFileName;
            
            // Cargar imagen con Intervention
            $image = $this->imageManager->make($file['tmp_name']);
            
            // Aplicar compresión y redimensionamiento si es necesario
            $originalSize = $file['size'];
            $maxWidth = 1920;
            $maxHeight = 1080;
            
            // Redimensionar si es muy grande
            if ($image->width() > $maxWidth || $image->height() > $maxHeight) {
                $image->resize($maxWidth, $maxHeight, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });
            }
            
            // Guardar con compresión
            $image->save($processedFilePath, IMAGE_COMPRESSION_QUALITY);
            
            $compressedSize = filesize($processedFilePath);
            
            // Usar archivo comprimido solo si es significativamente más pequeño
            if ($compressedSize < $originalSize * 0.8) {
                return [
                    'success' => true,
                    'file_path' => $processedFilePath,
                    'file_size' => $compressedSize,
                    'compressed' => true,
                    'original_size' => $originalSize,
                    'compression_ratio' => round(($originalSize - $compressedSize) / $originalSize * 100, 2)
                ];
            } else {
                // Si no hay mucha diferencia, usar original
                unlink($processedFilePath);
                return [
                    'success' => true,
                    'file_path' => $file['tmp_name'],
                    'file_size' => $originalSize,
                    'compressed' => false
                ];
            }
            
        } catch (Exception $e) {
            error_log("Error processing image: " . $e->getMessage());
            error_log("Image file: " . $file['name'] . " Type: " . $file['type']);
            
            // Fallback: usar archivo original si la compresión falla
            return [
                'success' => true,
                'file_path' => $file['tmp_name'],
                'file_size' => $file['size'],
                'compressed' => false,
                'note' => 'Image compression failed - using original file',
                'compression_error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Verificar si podemos procesar imágenes
     */
    private function canProcessImages() {
        // Verificar si Intervention Image está disponible
        if (!class_exists('Intervention\Image\ImageManager')) {
            return false;
        }
        
        // Verificar si GD está disponible
        if (!extension_loaded('gd')) {
            return false;
        }
        
        // Verificar si ImageManager está inicializado
        if (!isset($this->imageManager)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Crear carpeta por año en el bucket
     */
    private function createYearFolder($year) {
        try {
            $folderKey = "expenses/{$year}/";
            
            // Verificar si la carpeta ya existe
            $exists = $this->s3Client->doesObjectExist($this->bucket, $folderKey);
            
            if (!$exists) {
                // Crear carpeta vacía
                $this->s3Client->putObject([
                    'Bucket' => $this->bucket,
                    'Key' => $folderKey,
                    'Body' => '',
                    'ContentType' => 'application/x-directory'
                ]);
            }
            
        } catch (Exception $e) {
            error_log("Error creating year folder: " . $e->getMessage());
            // No es crítico si no se puede crear la carpeta
        }
    }
    
    /**
     * Generar nombre único para archivo
     */
    private function generateUniqueFileName($originalName, $expenseId) {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $timestamp = date('YmdHis');
        $uniqueId = uniqid();
        
        return "{$expenseId}_{$timestamp}_{$uniqueId}.{$extension}";
    }
    
    /**
     * Descargar archivo desde B2
     */
    public function downloadFile($fileKey) {
        try {
            $result = $this->s3Client->getObject([
                'Bucket' => $this->bucket,
                'Key' => $fileKey
            ]);
            
            return [
                'success' => true,
                'content' => $result['Body'],
                'content_type' => $result['ContentType'],
                'metadata' => $result['Metadata']
            ];
            
        } catch (S3Exception $e) {
            error_log("Error downloading from B2: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error al descargar archivo'];
        }
    }
    
    /**
     * Eliminar archivo de B2
     */
    public function deleteFile($fileKey) {
        try {
            $this->s3Client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $fileKey
            ]);
            
            return ['success' => true];
            
        } catch (S3Exception $e) {
            error_log("Error deleting from B2: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error al eliminar archivo'];
        }
    }
    
    /**
     * Obtener URL firmada para acceso temporal
     */
    public function getSignedUrl($fileKey, $expiration = '+1 hour') {
        try {
            $cmd = $this->s3Client->getCommand('GetObject', [
                'Bucket' => $this->bucket,
                'Key' => $fileKey
            ]);
            
            $request = $this->s3Client->createPresignedRequest($cmd, $expiration);
            return (string) $request->getUri();
            
        } catch (Exception $e) {
            error_log("Error generating signed URL: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Subir múltiples archivos
     */
    public function uploadMultipleFiles($files, $expenseId, $expenseDate) {
        $results = [];
        $errors = [];
        
        // Verificar límite de archivos
        $fileCount = is_array($files['name']) ? count($files['name']) : 1;
        if ($fileCount > MAX_FILES_PER_EXPENSE) {
            return [
                'success' => false, 
                'error' => 'Máximo ' . MAX_FILES_PER_EXPENSE . ' archivos permitidos'
            ];
        }
        
        // Procesar cada archivo
        for ($i = 0; $i < $fileCount; $i++) {
            $file = [
                'name' => is_array($files['name']) ? $files['name'][$i] : $files['name'],
                'tmp_name' => is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'],
                'size' => is_array($files['size']) ? $files['size'][$i] : $files['size'],
                'type' => is_array($files['type']) ? $files['type'][$i] : $files['type'],
                'error' => is_array($files['error']) ? $files['error'][$i] : $files['error']
            ];
            
            $result = $this->uploadFile($file, $expenseId, $expenseDate);
            
            if ($result['success']) {
                $results[] = $result;
            } else {
                $errors[] = $result['error'];
            }
        }
        
        return [
            'success' => count($results) > 0,
            'uploaded' => $results,
            'errors' => $errors,
            'total_uploaded' => count($results),
            'total_errors' => count($errors)
        ];
    }
    
    /**
     * Verificar si un archivo existe en B2
     */
    public function fileExists($fileKey) {
        try {
            return $this->s3Client->doesObjectExist($this->bucket, $fileKey);
        } catch (Exception $e) {
            error_log("Error checking file existence: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener información de un archivo en B2
     */
    public function getFileInfo($fileKey) {
        try {
            $result = $this->s3Client->headObject([
                'Bucket' => $this->bucket,
                'Key' => $fileKey
            ]);
            
            return [
                'success' => true,
                'size' => $result['ContentLength'],
                'last_modified' => $result['LastModified'],
                'content_type' => $result['ContentType'],
                'etag' => $result['ETag'],
                'metadata' => $result['Metadata'] ?? []
            ];
            
        } catch (Exception $e) {
            error_log("Error getting file info: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al obtener información del archivo'
            ];
        }
    }
    
    /**
     * Verificar si una carpeta existe en B2
     */
    public function folderExists($folderKey) {
        try {
            // Asegurar que termine con /
            if (substr($folderKey, -1) !== '/') {
                $folderKey .= '/';
            }
            
            // Verificar si existe como objeto directorio
            $directExists = $this->s3Client->doesObjectExist($this->bucket, $folderKey);
            
            if ($directExists) {
                return true;
            }
            
            // Verificar si hay objetos que empiecen con el prefijo
            $result = $this->s3Client->listObjects([
                'Bucket' => $this->bucket,
                'Prefix' => $folderKey,
                'MaxKeys' => 1
            ]);
            
            return isset($result['Contents']) && count($result['Contents']) > 0;
            
        } catch (Exception $e) {
            error_log("Error checking folder existence: " . $e->getMessage());
            return false;
        }
    }
}
?> 