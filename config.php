<?php
// Configuración de la base de datos
define('DB_HOST', '168.231.68.229');     // IP del servidor
define('DB_PORT', '3306');               // Puerto MySQL
define('DB_NAME', 'cloude_apcuadre');           // Nombre de la base de datos
define('DB_USER', 'workbench_user');     // Usuario MySQL
define('DB_PASS', 'Mysql2025#');         // Contraseña MySQL
define('DB_CHARSET', 'utf8mb4');         // Charset

// Configuración de la aplicación
define('BASE_URL', 'http://localhost/cloude/');
define('SESSION_TIMEOUT', 3600); // 1 hora

// Configuración segura de cookies de sesión
$isHTTPS = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;

// Configurar nombre de sesión personalizado
session_name('APCSESSID');

// Configurar parámetros de cookies de sesión
session_set_cookie_params([
    'secure' => $isHTTPS,        // Solo por HTTPS en producción
    'httponly' => true,          // Inaccesible desde JS
    'samesite' => 'Strict',      // Previene CSRF
    'lifetime' => SESSION_TIMEOUT // Timeout de sesión
]);

// Configuraciones adicionales de seguridad
ini_set('session.cookie_httponly', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_strict_mode', '1');

// Iniciar sesión
session_start();

// Regenerar ID de sesión si es una nueva sesión
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

// Autoloader de Composer para librerías externas
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Función para conectar a la base de datos
function getConnection() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch(PDOException $e) {
        die("Error de conexión: " . $e->getMessage());
    }
}

// Función para verificar si el usuario está logueado
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Función para verificar el timeout de sesión
function checkSessionTimeout() {
    // Si no hay última actividad, establecer ahora
    if (!isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    // Verificar si la sesión ha expirado
    if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
        // Registrar cierre de sesión por timeout
        if (function_exists('logActivity') && isLoggedIn()) {
            logActivity('LOGOUT', 'session', $_SESSION['user_id'] ?? null, 'Sesión cerrada por timeout automático');
        }
        
        // Destruir sesión
        session_unset();
        session_destroy();
        
        // Iniciar nueva sesión limpia
        session_start();
        session_regenerate_id(true);
        
        return false;
    }
    
    // Actualizar última actividad
    $_SESSION['last_activity'] = time();
    return true;
}

// Función para generar token CSRF
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Función para verificar token CSRF
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Función para limpiar datos de entrada
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Función para redirigir
function redirect($url) {
    header("Location: " . $url);
    exit();
}

// Función para mostrar mensajes flash
function setFlashMessage($message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        return ['message' => $message, 'type' => $type];
    }
    return null;
}

// Función para prevenir ataques de timing en login
function preventTimingAttack($minDelay = 100000) {
    // Añadir un delay mínimo consistente (en microsegundos)
    // 100000 microsegundos = 100 milisegundos
    $startTime = $_SESSION['login_start_time'] ?? microtime(true);
    $elapsed = (microtime(true) - $startTime) * 1000000; // convertir a microsegundos
    
    if ($elapsed < $minDelay) {
        usleep($minDelay - $elapsed);
    }
}

// Función para rate limiting de intentos de login
function checkLoginAttempts($identifier, $maxAttempts = 5, $timeWindow = 900) {
    // $timeWindow = 900 segundos = 15 minutos
    $key = 'login_attempts_' . md5($identifier);
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'first_attempt' => time()];
    }
    
    $attempts = $_SESSION[$key];
    
    // Resetear contador si ha pasado el tiempo límite
    if (time() - $attempts['first_attempt'] > $timeWindow) {
        $_SESSION[$key] = ['count' => 0, 'first_attempt' => time()];
        return true;
    }
    
    // Verificar si ha excedido el límite
    if ($attempts['count'] >= $maxAttempts) {
        return false;
    }
    
    return true;
}

// Función para registrar intento de login fallido
function recordFailedLogin($identifier, $username = null, $reason = 'Invalid credentials') {
    $key = 'login_attempts_' . md5($identifier);
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'first_attempt' => time()];
    }
    
    $_SESSION[$key]['count']++;
    
    // Registrar en base de datos para análisis de seguridad
    logFailedLoginAttempt($identifier, $username, $reason);
}

// Función para registrar intento fallido en base de datos
function logFailedLoginAttempt($identifier, $username = null, $reason = 'Invalid credentials') {
    try {
        $pdo = getConnection();
        
        // Crear tabla si no existe
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS failed_login_attempts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL,
                username VARCHAR(255),
                user_agent TEXT,
                reason VARCHAR(255),
                identifier_hash VARCHAR(64),
                attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                session_id VARCHAR(128),
                INDEX idx_ip_time (ip_address, attempt_time),
                INDEX idx_username_time (username, attempt_time),
                INDEX idx_attempt_time (attempt_time)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Obtener información de la request
        $ipAddress = getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $sessionId = session_id();
        
        // Insertar registro
        $stmt = $pdo->prepare("
            INSERT INTO failed_login_attempts 
            (ip_address, username, user_agent, reason, identifier_hash, session_id, attempt_time)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $ipAddress,
            $username,
            $userAgent,
            $reason,
            md5($identifier),
            $sessionId
        ]);
        
        // También registrar en audit log si está disponible
        if (function_exists('logActivity')) {
            logActivity('FAILED_LOGIN', 'authentication', null, 
                "Intento de login fallido - IP: $ipAddress - Usuario: " . ($username ?? 'Unknown') . " - Razón: $reason"
            );
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error registrando intento fallido: " . $e->getMessage());
        return false;
    }
}

// Función para obtener IP del cliente (mejorada)
function getClientIP() {
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

// Función para obtener estadísticas de intentos fallidos
function getFailedLoginStats($timeWindow = 3600) {
    try {
        $pdo = getConnection();
        
        // Estadísticas de las últimas horas
        $stmt = $pdo->prepare("
            SELECT 
                reason,
                COUNT(*) as count_by_reason
            FROM failed_login_attempts 
            WHERE attempt_time >= DATE_SUB(NOW(), INTERVAL ? SECOND)
            GROUP BY reason
            ORDER BY count_by_reason DESC
        ");
        $stmt->execute([$timeWindow]);
        $reasonStats = $stmt->fetchAll();
        
        // IPs con más intentos fallidos
        $stmt = $pdo->prepare("
            SELECT 
                ip_address,
                COUNT(*) as attempts,
                MAX(attempt_time) as last_attempt,
                GROUP_CONCAT(DISTINCT username) as usernames_tried
            FROM failed_login_attempts 
            WHERE attempt_time >= DATE_SUB(NOW(), INTERVAL ? SECOND)
            GROUP BY ip_address
            HAVING attempts >= 3
            ORDER BY attempts DESC
            LIMIT 10
        ");
        $stmt->execute([$timeWindow]);
        $suspiciousIPs = $stmt->fetchAll();
        
        // Total general
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total,
                COUNT(DISTINCT ip_address) as unique_ips,
                COUNT(DISTINCT username) as unique_usernames
            FROM failed_login_attempts 
            WHERE attempt_time >= DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$timeWindow]);
        $totals = $stmt->fetch();
        
        return [
            'totals' => $totals,
            'by_reason' => $reasonStats,
            'suspicious_ips' => $suspiciousIPs,
            'time_window_hours' => $timeWindow / 3600
        ];
        
    } catch (Exception $e) {
        error_log("Error obteniendo estadísticas de login: " . $e->getMessage());
        return null;
    }
}

// Función para detectar posibles ataques
function detectPotentialAttacks() {
    try {
        $pdo = getConnection();
        
        $alerts = [];
        
        // Detectar múltiples intentos desde la misma IP en los últimos 15 minutos
        $stmt = $pdo->prepare("
            SELECT ip_address, COUNT(*) as attempts, MAX(attempt_time) as last_attempt
            FROM failed_login_attempts 
            WHERE attempt_time >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
            GROUP BY ip_address
            HAVING attempts >= 10
        ");
        $stmt->execute();
        $bruteForceIPs = $stmt->fetchAll();
        
        foreach ($bruteForceIPs as $ip) {
            $alerts[] = [
                'type' => 'BRUTE_FORCE',
                'severity' => 'HIGH',
                'message' => "Posible ataque de fuerza bruta desde IP {$ip['ip_address']} - {$ip['attempts']} intentos en 15 minutos",
                'data' => $ip
            ];
        }
        
        // Detectar intentos de múltiples usuarios desde la misma IP
        $stmt = $pdo->prepare("
            SELECT ip_address, COUNT(DISTINCT username) as users_tried, COUNT(*) as total_attempts
            FROM failed_login_attempts 
            WHERE attempt_time >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
            AND username IS NOT NULL
            GROUP BY ip_address
            HAVING users_tried >= 5
        ");
        $stmt->execute();
        $userEnumIPs = $stmt->fetchAll();
        
        foreach ($userEnumIPs as $ip) {
            $alerts[] = [
                'type' => 'USER_ENUMERATION',
                'severity' => 'MEDIUM',
                'message' => "Posible enumeración de usuarios desde IP {$ip['ip_address']} - {$ip['users_tried']} usuarios diferentes probados",
                'data' => $ip
            ];
        }
        
        return $alerts;
        
    } catch (Exception $e) {
        error_log("Error detectando ataques: " . $e->getMessage());
        return [];
    }
}

// Función para establecer encabezados de seguridad HTTP
function setSecurityHeaders() {
    // Content Security Policy - Más permisivo para AJAX interno y scripts externos
    header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; img-src 'self' data: https:; font-src 'self' https://cdnjs.cloudflare.com; connect-src 'self' data: https://cdn.jsdelivr.net;");
    
    // Prevenir MIME type sniffing
    header("X-Content-Type-Options: nosniff");
    
    // Prevenir que la página sea embebida en frames (protección contra clickjacking)
    header("X-Frame-Options: DENY");
    
    // Habilitar protección XSS del navegador
    header("X-XSS-Protection: 1; mode=block");
    
    // Strict Transport Security (solo para HTTPS)
    $isHTTPS = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
    if ($isHTTPS) {
        header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
    }
    
    // Referrer Policy - Controlar qué información se envía en el header Referer
    header("Referrer-Policy: strict-origin-when-cross-origin");
    
    // Permissions Policy - Controlar qué características del navegador pueden usarse
    header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
    
    // Prevenir que los navegadores abran archivos descargados automáticamente
    header("X-Download-Options: noopen");
    
    // Prevenir que IE ejecute downloads en el contexto del sitio
    header("X-Permitted-Cross-Domain-Policies: none");
}

// Función para limpiar intentos de login tras éxito
function clearLoginAttempts($identifier) {
    $key = 'login_attempts_' . md5($identifier);
    unset($_SESSION[$key]);
}

// Función para verificar autenticación en APIs (sin timeout estricto)
function checkAPIAuthentication() {
    // Verificar si hay sesión básica
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'No autorizado']);
        exit;
    }
    
    // Verificación de timeout más permisiva para APIs
    if (isset($_SESSION['last_activity'])) {
        $timeSinceActivity = time() - $_SESSION['last_activity'];
        if ($timeSinceActivity > (SESSION_TIMEOUT + 300)) { // 5 minutos extra de gracia
            http_response_code(401);
            echo json_encode(['error' => 'Sesión expirada']);
            exit;
        }
        // Actualizar última actividad solo si no ha expirado
        $_SESSION['last_activity'] = time();
    }
    
    return true;
}

// Función para verificar si el usuario es administrador
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Función para verificar acceso de administrador
function requireAdmin() {
    if (!isLoggedIn()) {
        header('Location: auth/login.php');
        exit;
    }
    
    if (!isAdmin()) {
        http_response_code(403);
        die('Acceso denegado. Solo administradores pueden acceder a esta página.');
    }
    
    return true;
}

// Función para verificar configuración de seguridad de sesiones
function getSessionSecurityStatus() {
    $cookieParams = session_get_cookie_params();
    $isHTTPS = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
    
    return [
        'session_name' => session_name(),
        'secure' => $cookieParams['secure'],
        'httponly' => $cookieParams['httponly'],
        'samesite' => $cookieParams['samesite'],
        'lifetime' => $cookieParams['lifetime'],
        'is_https' => $isHTTPS,
        'session_id' => session_id(),
        'security_level' => $cookieParams['secure'] && $cookieParams['httponly'] ? 'HIGH' : 'MEDIUM'
    ];
}

// Función para obtener información del timeout de sesión
function getSessionTimeoutInfo() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $lastActivity = $_SESSION['last_activity'] ?? null;
    $loginTime = $_SESSION['login_time'] ?? null;
    $currentTime = time();
    
    if (!$lastActivity) {
        return null;
    }
    
    $timeSinceActivity = $currentTime - $lastActivity;
    $timeUntilExpiry = SESSION_TIMEOUT - $timeSinceActivity;
    $sessionDuration = $loginTime ? $currentTime - $loginTime : 0;
    
    return [
        'last_activity' => $lastActivity,
        'current_time' => $currentTime,
        'time_since_activity' => $timeSinceActivity,
        'time_until_expiry' => $timeUntilExpiry,
        'session_duration' => $sessionDuration,
        'timeout_limit' => SESSION_TIMEOUT,
        'is_expired' => $timeUntilExpiry <= 0,
        'expiry_percentage' => min(100, ($timeSinceActivity / SESSION_TIMEOUT) * 100)
    ];
}

// Configuración de BackBlaze B2
define('B2_KEY_ID', '0058ab3df0e6ae30000000009');
define('B2_APPLICATION_KEY', 'K005gUIaeFIdQOggIlVrHubsVM/rqsA');
define('B2_BUCKET_NAME', 'apcuadrev2'); // Nombre exacto del bucket
define('B2_BUCKET_ID', '488a4b93edafd05e967a0e13'); // ID específico del bucket
define('B2_REGION', 'us-east-005'); // Región específica de tu cuenta
define('B2_ENDPOINT', 'https://s3.us-east-005.backblazeb2.com'); // Endpoint específico

// Configuración de archivos
define('ALLOWED_FILE_TYPES', ['image/jpeg', 'image/png', 'application/pdf']);
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2MB
define('MAX_FILES_PER_EXPENSE', 4);
define('IMAGE_COMPRESSION_QUALITY', 75);

// Función para obtener configuración de BackBlaze B2
function getB2Config() {
    return [
        'version' => 'latest',
        'region' => B2_REGION,
        'endpoint' => B2_ENDPOINT,
        'credentials' => [
            'key' => B2_KEY_ID,
            'secret' => B2_APPLICATION_KEY
        ],
        'bucket' => B2_BUCKET_NAME,
        'bucket_id' => B2_BUCKET_ID
    ];
}

// Establecer encabezados de seguridad HTTP automáticamente
setSecurityHeaders();
?>
