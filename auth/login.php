<?php
require_once '../config.php';

// Verificar si ya está logueado
if (isLoggedIn()) {
    header('Location: ../dashboard.php');
    exit;
}

$error = '';
$success = '';

// Verificar si llega por timeout de sesión
if (isset($_GET['timeout']) && $_GET['timeout'] == '1') {
    $error = 'Tu sesión ha expirado por inactividad. Por favor, inicia sesión nuevamente.';
}

// Procesar formulario de login
if ($_POST) {
    // Registrar tiempo de inicio para prevenir timing attacks
    $_SESSION['login_start_time'] = microtime(true);
    
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    // Verificar token CSRF
    if (!verifyCSRFToken($csrf_token)) {
        $clientId = $_SERVER['REMOTE_ADDR'] . '_' . $username;
        recordFailedLogin($clientId, $username, 'Invalid CSRF token');
        $error = 'Credenciales inválidas o error de seguridad.';
        preventTimingAttack();
    } else if (empty($username) || empty($password)) {
        $error = 'Por favor, completa todos los campos.';
        preventTimingAttack();
    } else {
        // Verificar rate limiting
        $clientId = $_SERVER['REMOTE_ADDR'] . '_' . $username;
        
        if (!checkLoginAttempts($clientId)) {
            $error = 'Demasiados intentos fallidos. Intenta de nuevo en 15 minutos.';
            preventTimingAttack();
        } else {
            try {
                $pdo = getConnection();
                $stmt = $pdo->prepare("SELECT id, username, password, email, active FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Hash dummy para prevenir ataques de timing
                $dummyHash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
                
                // Siempre ejecutar password_verify para prevenir timing attacks
                $userExists = $user !== false;
                $hashToVerify = $userExists ? $user['password'] : $dummyHash;
                $isValidPassword = password_verify($password, $hashToVerify);
                
                // Solo proceder si el usuario existe Y la contraseña es válida
                if ($userExists && $isValidPassword) {
                    if ($user['active']) {
                        // Login exitoso - limpiar intentos fallidos
                        clearLoginAttempts($clientId);
                        
                        // Login exitoso
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['login_time'] = time();
                        $_SESSION['last_activity'] = time();
                        
                        // Regenerar ID de sesión por seguridad
                        session_regenerate_id(true);
                        
                        // Actualizar último login
                        $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                        $updateStmt->execute([$user['id']]);
                        
                        preventTimingAttack();
                        header('Location: ../dashboard.php');
                        exit;
                    } else {
                        recordFailedLogin($clientId, $username, 'Account disabled');
                        $error = 'Tu cuenta está desactivada. Contacta al administrador.';
                        preventTimingAttack();
                    }
                } else {
                    recordFailedLogin($clientId, $username, 'Invalid credentials');
                    $error = 'Usuario o contraseña incorrectos.';
                    preventTimingAttack();
                }
            } catch (PDOException $e) {
                $error = 'Error de conexión. Inténtalo más tarde.';
                preventTimingAttack();
            }
        }
    }
}

$pageTitle = 'Iniciar Sesión';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <form class="login-form" method="POST" action="">
            <div class="login-header">
                <img src="https://res.cloudinary.com/dpr7agofk/image/upload/v1728755106/logo_azul_wnwres.png" 
                     alt="AP Cuadre Logo" 
                     style="height: 60px; width: auto; margin-bottom: 16px; object-fit: contain;">
        
                <p>Inicia sesión en tu cuenta</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="username" class="form-label">
                    <i class="fas fa-user"></i>
                    Usuario o Email
                </label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    class="form-input" 
                    value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                    required
                    autocomplete="username"
                    placeholder="Ingresa tu usuario o email"
                >
            </div>
            
            <div class="form-group">
                <label for="password" class="form-label">
                    <i class="fas fa-lock"></i>
                    Contraseña
                </label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    class="form-input" 
                    required
                    autocomplete="current-password"
                    placeholder="Ingresa tu contraseña"
                >
            </div>
            
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <button type="submit" class="btn btn-primary" id="loginBtn">
                <i class="fas fa-sign-in-alt"></i>
                Iniciar Sesión
                <div class="btn-loading-overlay" id="loginOverlay">
                    <span class="btn-spinner"></span>
                    Iniciando sesión...
                </div>
            </button>
            
            <div style="text-align: center; margin-top: 20px;">
                <p style="color: var(--text-secondary); font-size: 14px;">
                    ¿No tienes cuenta? 
                    <a href="register.php" style="color: var(--primary-color); text-decoration: none; font-weight: 500;">
                        Regístrate aquí
                    </a>
                </p>
            </div>
        </form>
    </div>
    
    <script>
        // Función para mostrar/ocultar spinner overlay
        function toggleLoginSpinner(show) {
            const btn = document.getElementById('loginBtn');
            
            if (show) {
                btn.classList.add('btn-loading');
                btn.disabled = true;
            } else {
                btn.classList.remove('btn-loading');
                btn.disabled = false;
            }
        }
        
        // Manejar envío del formulario
        document.querySelector('.login-form').addEventListener('submit', function(e) {
            // Validar campos básicos antes de enviar
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            
            if (!username || !password) {
                e.preventDefault();
                return false;
            }
            
            // Mostrar spinner overlay
            toggleLoginSpinner(true);
            
            // Si hay errores PHP, ocultar spinner después de un breve delay
            setTimeout(function() {
                if (document.querySelector('.alert-danger')) {
                    toggleLoginSpinner(false);
                }
            }, 100);
        });
        
        // Ocultar spinner si hay errores al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            if (document.querySelector('.alert-danger') || document.querySelector('.alert-success')) {
                toggleLoginSpinner(false);
            }
        });
    </script>
    <script src="../assets/js/script.js"></script>
</body>
</html>
