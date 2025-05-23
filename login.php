<?php
require_once 'config.php';

// Verificar si ya está logueado
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

// Procesar formulario de login
if ($_POST) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    // Verificar token CSRF
    if (!verifyCSRFToken($csrf_token)) {
        $error = 'Token de seguridad inválido.';
    } else if (empty($username) || empty($password)) {
        $error = 'Por favor, completa todos los campos.';
    } else {
        try {
            $pdo = getConnection();
            $stmt = $pdo->prepare("SELECT id, username, password, email, active FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                if ($user['active']) {
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
                    
                    header('Location: dashboard.php');
                    exit;
                } else {
                    $error = 'Tu cuenta está desactivada. Contacta al administrador.';
                }
            } else {
                $error = 'Usuario o contraseña incorrectos.';
            }
        } catch (PDOException $e) {
            $error = 'Error de conexión. Inténtalo más tarde.';
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
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <form class="login-form" method="POST" action="">
            <div class="login-header">
                <i class="fas fa-shield-alt" style="font-size: 48px; color: var(--primary-color); margin-bottom: 16px;"></i>
                <h1>Bienvenido</h1>
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
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-sign-in-alt"></i>
                Iniciar Sesión
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
    
    <script src="assets/js/script.js"></script>
</body>
</html>
