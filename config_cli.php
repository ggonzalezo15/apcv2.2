<?php
/**
 * ⚙️ CONFIGURACIÓN ESPECÍFICA PARA CLI
 * 
 * Configuración optimizada para scripts de línea de comandos
 * Sin configuraciones web como sesiones, headers, etc.
 */

// 🗄️ CONFIGURACIÓN DE BASE DE DATOS
define('DB_HOST', '168.231.68.229');
define('DB_PORT', '3306');
define('DB_NAME', 'cloude_apcuadre');
define('DB_USER', 'workbench_user');
define('DB_PASS', 'Mysql2025#');
define('DB_CHARSET', 'utf8mb4');

// 🌍 CONFIGURACIÓN GLOBAL
date_default_timezone_set('America/Mexico_City');

// 📝 CONFIGURACIÓN DE LOGS
error_reporting(E_ALL);

// Solo mostrar errores en CLI si está en modo verbose
if (php_sapi_name() === 'cli') {
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', 'logs/cli_errors.log');
} else {
    ini_set('display_errors', 1);
}

/**
 * 🔗 FUNCIÓN: Obtener conexión a base de datos
 */
function getConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
        } catch (PDOException $e) {
            // En CLI, mostrar error y salir
            if (php_sapi_name() === 'cli') {
                fwrite(STDERR, "❌ Error de conexión a BD: " . $e->getMessage() . "\n");
                exit(1);
            } else {
                throw $e;
            }
        }
    }
    
    return $pdo;
}

/**
 * 🛠️ FUNCIÓN: Generar UUID
 */
function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

/**
 * 🔒 FUNCIÓN: Verificar permisos de administrador (simplificada para CLI)
 */
function checkAdminPermissions() {
    // En CLI, asumir que quien ejecuta tiene permisos
    // En producción, podrías verificar usuario del sistema o archivo de configuración
    return true;
}

/**
 * 📝 FUNCIÓN: Log para CLI
 */
function logCLI($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] [{$level}] {$message}";
    
    // Escribir a archivo de log
    $logDir = 'logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/cli_' . date('Y-m-d') . '.log';
    file_put_contents($logFile, $logEntry . "\n", FILE_APPEND | LOCK_EX);
    
    return $logEntry;
}

/**
 * 🌟 FUNCIÓN: Verificar si estamos en CLI
 */
function isCLI() {
    return php_sapi_name() === 'cli';
}

/**
 * ⚡ FUNCIÓN: Configuración optimizada para CLI
 */
function setupCLIEnvironment() {
    if (!isCLI()) {
        return;
    }
    
    // Configurar límites para scripts de larga duración
    set_time_limit(0); // Sin límite de tiempo
    ini_set('memory_limit', '512M'); // Aumentar memoria disponible
    
    // Configurar output buffering para CLI
    if (ob_get_level()) {
        ob_end_clean();
    }
}

// 🚀 INICIALIZACIÓN AUTOMÁTICA
setupCLIEnvironment();

?>
