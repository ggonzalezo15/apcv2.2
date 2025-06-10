<?php
require_once '../config.php';

// Verificar si ya está logueado
if (isLoggedIn()) {
    header('Location: ../dashboard.php');
    exit;
}

$error = '';
$success = '';

// Procesar formulario de registro
if ($_POST) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    // Verificar token CSRF
    if (!verifyCSRFToken($csrf_token)) {
        $error = 'Token de seguridad inválido.';
    } else if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Por favor, completa todos los campos.';
    } else if (strlen($username) < 3) {
        $error = 'El usuario debe tener al menos 3 caracteres.';
    } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Por favor, ingresa un email válido.';
    } else if (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } else if ($password !== $confirm_password) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        try {
            $pdo = getConnection();
            
            // Verificar si el usuario o email ya existen
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            
            if ($stmt->fetchColumn() > 0) {
                $error = 'El usuario o email ya están registrados.';
            } else {
                // Crear usuario
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $insertStmt = $pdo->prepare("
                    INSERT INTO users (username, email, password, created_at, active) 
                    VALUES (?, ?, ?, NOW(), 1)
                ");
                
                if ($insertStmt->execute([$username, $email, $hashedPassword])) {
                    $success = 'Cuenta creada exitosamente. Ya puedes iniciar sesión.';
                    // Limpiar campos
                    $_POST = [];
                } else {
                    $error = 'Error al crear la cuenta. Inténtalo de nuevo.';
                }
            }
        } catch (PDOException $e) {
            $error = 'Error de conexión. Inténtalo más tarde.';
        }
    }
}

$pageTitle = 'Crear Cuenta';
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
                <i class="fas fa-user-plus" style="font-size: 48px; color: var(--primary-color); margin-bottom: 16px;"></i>
                <h1>Crear Cuenta</h1>
                <p>Regístrate para acceder al sistema</p>
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
                    Usuario
                </label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    class="form-input" 
                    value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                    required
                    autocomplete="username"
                    placeholder="Elige un nombre de usuario"
                    minlength="3"
                >
            </div>
            
            <div class="form-group">
                <label for="email" class="form-label">
                    <i class="fas fa-envelope"></i>
                    Email
                </label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    class="form-input" 
                    value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                    required
                    autocomplete="email"
                    placeholder="tu@email.com"
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
                    autocomplete="new-password"
                    placeholder="Mínimo 6 caracteres"
                    minlength="6"
                >
            </div>
            
            <div class="form-group">
                <label for="confirm_password" class="form-label">
                    <i class="fas fa-lock"></i>
                    Confirmar Contraseña
                </label>
                <input 
                    type="password" 
                    id="confirm_password" 
                    name="confirm_password" 
                    class="form-input" 
                    required
                    autocomplete="new-password"
                    placeholder="Repite tu contraseña"
                    minlength="6"
                >
            </div>
            
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-user-plus"></i>
                Crear Cuenta
            </button>
            
            <div style="text-align: center; margin-top: 20px;">
                <p style="color: var(--text-secondary); font-size: 14px;">
                    ¿Ya tienes cuenta? 
                    <a href="login.php" style="color: var(--primary-color); text-decoration: none; font-weight: 500;">
                        Inicia sesión aquí
                    </a>
                </p>
            </div>
        </form>
    </div>
    
    <script src="../assets/js/script.js"></script>
    
    <script>
    // Validación en tiempo real para confirmar contraseña
    document.getElementById('confirm_password').addEventListener('input', function() {
        const password = document.getElementById('password').value;
        const confirmPassword = this.value;
        
        if (confirmPassword && password !== confirmPassword) {
            this.setCustomValidity('Las contraseñas no coinciden');
        } else {
            this.setCustomValidity('');
        }
    });
    </script>
</body>
</html>
