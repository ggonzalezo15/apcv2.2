<?php
/**
 * Sistema de Auditoría y Tracking de Actividades
 * Registra todas las acciones importantes del sistema
 */

require_once 'config.php';

class AuditSystem {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getConnection();
        $this->initializeAuditTables();
    }
    
    /**
     * Inicializar tablas de auditoría si no existen
     */
    private function initializeAuditTables() {
        try {
            // Crear tabla de activity_logs si no existe
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS activity_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT,
                    action VARCHAR(100) NOT NULL,
                    entity_type VARCHAR(50) NOT NULL,
                    entity_id VARCHAR(36),
                    description TEXT,
                    old_values JSON,
                    new_values JSON,
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                    INDEX idx_user_id (user_id),
                    INDEX idx_action (action),
                    INDEX idx_entity (entity_type, entity_id),
                    INDEX idx_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
            // Agregar campos de auditoría a tablas principales si no existen
            $this->addAuditColumns();
            
        } catch (Exception $e) {
            error_log("Error inicializando sistema de auditoría: " . $e->getMessage());
        }
    }
    
    /**
     * Agregar columnas de auditoría a tablas existentes
     */
    private function addAuditColumns() {
        $tables = ['incomes', 'expenses', 'income_payments', 'bank_accounts', 'teams', 'contractors'];
        
        foreach ($tables as $table) {
            try {
                // Verificar si la tabla existe
                $stmt = $this->pdo->prepare("SHOW TABLES LIKE ?");
                $stmt->execute([$table]);
                if ($stmt->rowCount() == 0) continue;
                
                // Agregar created_by si no existe
                $this->pdo->exec("
                    ALTER TABLE `{$table}` 
                    ADD COLUMN IF NOT EXISTS `created_by` INT NULL,
                    ADD COLUMN IF NOT EXISTS `updated_by` INT NULL,
                    ADD CONSTRAINT IF NOT EXISTS `fk_{$table}_created_by` 
                        FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
                    ADD CONSTRAINT IF NOT EXISTS `fk_{$table}_updated_by` 
                        FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
                ");
                
            } catch (Exception $e) {
                // Ignorar errores si las columnas ya existen
                error_log("Info: Columnas de auditoría para {$table} ya pueden existir");
            }
        }
    }
    
    /**
     * Registrar una actividad en el log
     */
    public function log($action, $entityType, $entityId = null, $description = null, $oldValues = null, $newValues = null) {
        try {
            $userId = $this->getCurrentUserId();
            $ipAddress = $this->getClientIP();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            
            $stmt = $this->pdo->prepare("
                INSERT INTO activity_logs 
                (user_id, action, entity_type, entity_id, description, old_values, new_values, ip_address, user_agent, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $userId,
                $action,
                $entityType,
                $entityId,
                $description,
                $oldValues ? json_encode($oldValues) : null,
                $newValues ? json_encode($newValues) : null,
                $ipAddress,
                $userAgent
            ]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Error registrando actividad: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener actividades recientes para el dashboard
     */
    public function getRecentActivities($limit = 10) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    al.*,
                    COALESCE(u.username, 'Sistema') as username,
                    COALESCE(u.email, '') as email,
                    CASE 
                        WHEN al.action = 'create' THEN '🆕'
                        WHEN al.action = 'update' THEN '✏️'
                        WHEN al.action = 'delete' THEN '🗑️'
                        WHEN al.action = 'payment' THEN '💰'
                        ELSE '📝'
                    END as action_icon,
                    CASE 
                        WHEN al.entity_type = 'income' THEN 'Ingreso'
                        WHEN al.entity_type = 'expense' THEN 'Gasto'
                        WHEN al.entity_type = 'payment' THEN 'Pago'
                        WHEN al.entity_type = 'bank_account' THEN 'Cuenta Bancaria'
                        WHEN al.entity_type = 'team' THEN 'Equipo'
                        WHEN al.entity_type = 'contractor' THEN 'Contratista'
                        ELSE al.entity_type
                    END as entity_name
                FROM activity_logs al
                LEFT JOIN users u ON al.user_id = u.id
                ORDER BY al.created_at DESC
                LIMIT " . intval($limit) . "
            ");
            
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error obteniendo actividades recientes: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener estadísticas de actividad
     */
    public function getActivityStats($days = 7) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    DATE(created_at) as date,
                    action,
                    entity_type,
                    COUNT(*) as count
                FROM activity_logs 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(created_at), action, entity_type
                ORDER BY date DESC
            ");
            
            $stmt->execute([$days]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error obteniendo estadísticas de actividad: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Registrar creación de ingreso
     */
    public function logIncomeCreated($incomeId, $data) {
        $description = "Nuevo ingreso creado";
        if (isset($data['invoice_number'])) {
            $description .= " - Factura: " . $data['invoice_number'];
        }
        if (isset($data['total_amount'])) {
            $description .= " - Monto: $" . number_format($data['total_amount'], 2);
        }
        
        return $this->log('create', 'income', $incomeId, $description, null, $data);
    }
    
    /**
     * Registrar actualización de ingreso
     */
    public function logIncomeUpdated($incomeId, $oldData, $newData) {
        $description = "Ingreso actualizado";
        if (isset($newData['invoice_number'])) {
            $description .= " - Factura: " . $newData['invoice_number'];
        }
        
        return $this->log('update', 'income', $incomeId, $description, $oldData, $newData);
    }
    
    /**
     * Registrar eliminación de ingreso
     */
    public function logIncomeDeleted($incomeId, $data) {
        $description = "Ingreso eliminado";
        if (isset($data['invoice_number'])) {
            $description .= " - Factura: " . $data['invoice_number'];
        }
        
        return $this->log('delete', 'income', $incomeId, $description, $data, null);
    }
    
    /**
     * Registrar pago de ingreso
     */
    public function logIncomePayment($paymentId, $incomeId, $amount, $paymentType) {
        $description = "Pago recibido - Monto: $" . number_format($amount, 2);
        if ($paymentType) {
            $description .= " - Tipo: " . $paymentType;
        }
        
        return $this->log('payment', 'income', $incomeId, $description, null, [
            'payment_id' => $paymentId,
            'amount' => $amount,
            'payment_type' => $paymentType
        ]);
    }
    
    /**
     * Registrar creación de gasto
     */
    public function logExpenseCreated($expenseId, $data) {
        $description = "Nuevo gasto creado";
        if (isset($data['name'])) {
            $description .= " - " . $data['name'];
        }
        if (isset($data['total_amount'])) {
            $description .= " - Monto: $" . number_format($data['total_amount'], 2);
        }
        
        return $this->log('create', 'expense', $expenseId, $description, null, $data);
    }
    
    /**
     * Obtener ID del usuario actual de la sesión
     */
    private function getCurrentUserId() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        return $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Obtener IP del cliente
     */
    private function getClientIP() {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}

// Función helper global para facilitar el uso
function logActivity($action, $entityType, $entityId = null, $description = null, $oldValues = null, $newValues = null) {
    try {
        $audit = new AuditSystem();
        return $audit->log($action, $entityType, $entityId, $description, $oldValues, $newValues);
    } catch (Exception $e) {
        error_log("Error en logActivity: " . $e->getMessage());
        return false;
    }
}

// Función para obtener actividades recientes (para el dashboard)
function getRecentActivities($limit = 10) {
    try {
        $audit = new AuditSystem();
        return $audit->getRecentActivities($limit);
    } catch (Exception $e) {
        error_log("Error en getRecentActivities: " . $e->getMessage());
        return [];
    }
} 