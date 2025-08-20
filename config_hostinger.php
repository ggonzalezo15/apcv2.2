<?php
// ============================================================
// CONFIGURACIÓN PARA HOSTINGER
// Base de datos: u496363305_apc2
// ============================================================

// Configuración de la base de datos HOSTINGER
define('DB_HOST', 'localhost');              // Hostinger usa localhost
define('DB_PORT', '3306');                   // Puerto estándar MySQL
define('DB_NAME', 'u496363305_apc2');        // Tu base de datos en Hostinger
define('DB_USER', 'u496363305_apc2');        // Usuario (normalmente igual al nombre de BD)
define('DB_PASS', 'TU_CONTRASEÑA_AQUI');     // ⚠️ CAMBIAR por tu contraseña real
define('DB_CHARSET', 'utf8mb4');             // Charset

// Configuración de la aplicación
define('BASE_URL', 'https://tu-dominio.com/'); // ⚠️ CAMBIAR por tu dominio real
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

// Configuración de BackBlaze B2 (opcional - configurar si usas almacenamiento en la nube)
define('B2_KEY_ID', '');                    // Configurar si usas BackBlaze
define('B2_APPLICATION_KEY', '');           // Configurar si usas BackBlaze
define('B2_BUCKET_NAME', '');               // Configurar si usas BackBlaze
define('B2_BUCKET_ID', '');                 // Configurar si usas BackBlaze
define('B2_REGION', 'us-east-005');         // Región por defecto
define('B2_ENDPOINT', 'https://s3.us-east-005.backblazeb2.com');

// Configuración de archivos
define('ALLOWED_FILE_TYPES', ['image/jpeg', 'image/png', 'application/pdf']);
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2MB
define('MAX_FILES_PER_EXPENSE', 4);
define('IMAGE_COMPRESSION_QUALITY', 75);

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
}

// Establecer encabezados de seguridad HTTP automáticamente
setSecurityHeaders();

// ============================================================
// INSTRUCCIONES DE CONFIGURACIÓN
// ============================================================
/*
PASOS PARA CONFIGURAR EN HOSTINGER:

1. CREDENCIALES DE BASE DE DATOS:
   - Obtén las credenciales reales de tu panel de Hostinger
   - Actualiza DB_USER y DB_PASS con los valores correctos
   - Normalmente DB_USER es igual a DB_NAME en Hostinger

2. DOMINIO:
   - Cambia BASE_URL por tu dominio real
   - Ejemplo: 'https://tudominio.com/' o 'https://tudominio.hostinger.site/'

3. SUBIR ARCHIVOS:
   - Sube todos los archivos PHP a tu hosting
   - Asegúrate de que este archivo se llame config.php
   - O renombra tu config.php actual y usa este

4. PROBAR CONEXIÓN:
   - Accede a tu sitio
   - Ve a /auth/login.php
   - Usa: admin / password
   - Cambia la contraseña inmediatamente

5. CONFIGURACIÓN ADICIONAL:
   - Si usas BackBlaze B2, configura las credenciales
   - Ajusta los límites de archivos según tus necesidades
   - Configura equipos, tipos de gastos, etc.
*/
?>