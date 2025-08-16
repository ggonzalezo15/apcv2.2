<?php
/**
 * Utilidad para generar y gestionar logs de limpieza
 */

class CleanupLogger {
    private $pdo;
    private $userId;
    private $logData;
    
    public function __construct($pdo, $userId) {
        $this->pdo = $pdo;
        $this->userId = $userId;
        $this->logData = [
            'operation_type' => '',
            'files_processed' => 0,
            'files_deleted' => 0,
            'space_freed_mb' => 0.0,
            'execution_time_seconds' => 0.0,
            'status' => 'success',
            'error_message' => null,
            'b2_path' => null,
            'details' => []
        ];
    }
    
    public function startOperation($operationType) {
        $this->logData['operation_type'] = $operationType;
        $this->logData['start_time'] = microtime(true);
        
        // Generar nombre de archivo único
        $timestamp = date('Y-m-d_H-i-s');
        $this->logData['log_filename'] = "cleanup_{$operationType}_{$timestamp}_{$this->userId}.json";
        
        $this->logMessage("🚀 Iniciando operación: " . strtoupper($operationType));
        $this->logMessage("📅 Fecha: " . date('Y-m-d H:i:s'));
        $this->logMessage("👤 Usuario: ID {$this->userId}");
        $this->logMessage("📝 Archivo de log: {$this->logData['log_filename']}");
        $this->logMessage(str_repeat("=", 50));
    }
    
    public function logMessage($message) {
        $this->logData['details'][] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => $message
        ];
    }
    
    public function logFileProcessed($filename, $filesize = 0, $action = 'processed') {
        $this->logData['files_processed']++;
        
        if ($action === 'deleted') {
            $this->logData['files_deleted']++;
            $this->logData['space_freed_mb'] += ($filesize / (1024 * 1024));
        }
        
        $this->logMessage("📄 {$action}: {$filename} (" . $this->formatFileSize($filesize) . ")");
    }
    
    public function logError($error) {
        $this->logData['status'] = 'error';
        $this->logData['error_message'] = $error;
        $this->logMessage("❌ ERROR: " . $error);
    }
    
    public function logWarning($warning) {
        if ($this->logData['status'] !== 'error') {
            $this->logData['status'] = 'partial';
        }
        $this->logMessage("⚠️ WARNING: " . $warning);
    }
    
    public function endOperation() {
        $endTime = microtime(true);
        $this->logData['execution_time_seconds'] = round($endTime - $this->logData['start_time'], 3);
        
        $this->logMessage(str_repeat("=", 50));
        $this->logMessage("✅ Operación completada");
        $this->logMessage("⏱️ Tiempo de ejecución: {$this->logData['execution_time_seconds']} segundos");
        $this->logMessage("📊 Archivos procesados: {$this->logData['files_processed']}");
        $this->logMessage("🗑️ Archivos eliminados: {$this->logData['files_deleted']}");
        $this->logMessage("💾 Espacio liberado: " . round($this->logData['space_freed_mb'], 2) . " MB");
        $this->logMessage("📈 Estado: " . strtoupper($this->logData['status']));
        
        // Guardar log en B2 (simulado por ahora)
        $this->saveLogToB2();
        
        // Guardar registro en base de datos
        $this->saveLogToDatabase();
        
        return $this->logData;
    }
    
    private function saveLogToB2() {
        // TODO: Implementar upload real a BackBlaze B2
        // Por ahora, simulamos la ruta donde estaría el archivo
        $this->logData['b2_path'] = "clean_logs/" . $this->logData['log_filename'];
        $this->logMessage("☁️ Log guardado en B2: " . $this->logData['b2_path']);
        
        // Aquí iría la implementación real de B2:
        /*
        try {
            $b2Service = new B2Service();
            $logContent = json_encode($this->logData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            
            $uploadResult = $b2Service->uploadFile(
                'clean_logs/' . $this->logData['log_filename'],
                $logContent,
                'application/json'
            );
            
            $this->logData['b2_path'] = $uploadResult['file_path'];
        } catch (Exception $e) {
            $this->logWarning("No se pudo subir log a B2: " . $e->getMessage());
        }
        */
    }
    
    private function saveLogToDatabase() {
        try {
            $sql = "
                INSERT INTO cleanup_logs (
                    log_filename, operation_type, files_processed, files_deleted, 
                    space_freed_mb, execution_time_seconds, status, error_message, 
                    b2_path, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $this->logData['log_filename'],
                $this->logData['operation_type'],
                $this->logData['files_processed'],
                $this->logData['files_deleted'],
                $this->logData['space_freed_mb'],
                $this->logData['execution_time_seconds'],
                $this->logData['status'],
                $this->logData['error_message'],
                $this->logData['b2_path'],
                $this->userId
            ]);
            
            $this->logData['db_id'] = $this->pdo->lastInsertId();
            $this->logMessage("💾 Registro guardado en BD con ID: " . $this->logData['db_id']);
            
        } catch (Exception $e) {
            error_log("Error guardando log en BD: " . $e->getMessage());
        }
    }
    
    private function formatFileSize($bytes) {
        if ($bytes == 0) return '0 B';
        
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = floor(log($bytes, 1024));
        
        return round($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
    }
    
    public function getLogData() {
        return $this->logData;
    }
}

// Función helper para crear logs fácilmente
function createCleanupLogger($pdo, $userId) {
    return new CleanupLogger($pdo, $userId);
}
?>
