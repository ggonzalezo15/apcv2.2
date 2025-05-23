<?php
require_once 'config.php';

// Verificar autenticación
if (!isLoggedIn() || !checkSessionTimeout()) {
    header('Location: login.php');
    exit;
}

$pageTitle = 'Mi Perfil';
$error = '';
$success = '';

// Obtener datos del usuario
try {
    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        header('Location: logout.php');
        exit;
    }
} catch (PDOException $e) {
    $error = 'Error al cargar los datos del perfil.';
}

// Procesar actualización de perfil
if ($_POST && isset($_POST['update_profile'])) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrf_token)) {
        $error = 'Token de seguridad inválido.';
    } else if (empty($username) || empty($email)) {
        $error = 'Por favor, completa todos los campos.';
    } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Por favor, ingresa un email válido.';
    } else {
        try {
            // Verificar si el username/email ya están en uso por otro usuario
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE (username = ? OR email = ?) AND id != ?");
            $checkStmt->execute([$username, $email, $_SESSION['user_id']]);
            
            if ($checkStmt->fetchColumn() > 0) {
                $error = 'El usuario o email ya están en uso.';
            } else {
                // Actualizar perfil
                $updateStmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, updated_at = NOW() WHERE id = ?");
                if ($updateStmt->execute([$username, $email, $_SESSION['user_id']])) {
                    $_SESSION['username'] = $username;
                    $_SESSION['email'] = $email;
                    $user['username'] = $username;
                    $user['email'] = $email;
                    $success = 'Perfil actualizado correctamente.';
                } else {
                    $error = 'Error al actualizar el perfil.';
                }
            }
        } catch (PDOException $e) {
            $error = 'Error de conexión. Inténtalo más tarde.';
        }
    }
}

// Procesar cambio de contraseña
if ($_POST && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrf_token)) {
        $error = 'Token de seguridad inválido.';
    } else if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'Por favor, completa todos los campos de contraseña.';
    } else if (strlen($new_password) < 6) {
        $error = 'La nueva contraseña debe tener al menos 6 caracteres.';
    } else if ($new_password !== $confirm_password) {
        $error = 'Las contraseñas nuevas no coinciden.';
    } else if (!password_verify($current_password, $user['password'])) {
        $error = 'La contraseña actual es incorrecta.';
    } else {
        try {
            $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
            $updateStmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            
            if ($updateStmt->execute([$hashedPassword, $_SESSION['user_id']])) {
                $user['password'] = $hashedPassword;
                $success = 'Contraseña cambiada correctamente.';
            } else {
                $error = 'Error al cambiar la contraseña.';
            }
        } catch (PDOException $e) {
            $error = 'Error de conexión. Inténtalo más tarde.';
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="main-layout">
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="content">
        <div class="content-header">
            <h1 class="content-title">
                <i class="fas fa-user"></i>
                Mi Perfil
            </h1>
            <p class="content-subtitle">Gestiona tu información personal y configuración de cuenta</p>
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
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
            <!-- Información del perfil -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-user-edit"></i>
                        Información Personal
                    </h3>
                    <p class="card-subtitle">Actualiza tu información básica</p>
                </div>
                
                <form method="POST" action="">
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
                            value="<?php echo htmlspecialchars($user['username']); ?>"
                            required
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
                            value="<?php echo htmlspecialchars($user['email']); ?>"
                            required
                        >
                    </div>
                    
                    <div style="background: var(--bg-primary); padding: 16px; border-radius: 8px; margin-bottom: 20px;">
                        <h4 style="color: var(--text-secondary); font-size: 14px; margin-bottom: 10px;">Información de la cuenta:</h4>
                        <p style="margin-bottom: 6px; font-size: 13px;"><strong>Creado:</strong> <?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></p>
                        <p style="margin-bottom: 6px; font-size: 13px;"><strong>Último acceso:</strong> <?php echo $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Primera vez'; ?></p>
                        <p style="font-size: 13px;"><strong>Estado:</strong> <span style="color: var(--success-color);">Activo</span></p>
                    </div>
                    
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <button type="submit" name="update_profile" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Actualizar Perfil
                    </button>
                </form>
            </div>
            
            <!-- Cambio de contraseña -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-lock"></i>
                        Cambiar Contraseña
                    </h3>
                    <p class="card-subtitle">Actualiza tu contraseña por seguridad</p>
                </div>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="current_password" class="form-label">
                            <i class="fas fa-key"></i>
                            Contraseña Actual
                        </label>
                        <input 
                            type="password" 
                            id="current_password" 
                            name="current_password" 
                            class="form-input" 
                            required
                            placeholder="Ingresa tu contraseña actual"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password" class="form-label">
                            <i class="fas fa-lock"></i>
                            Nueva Contraseña
                        </label>
                        <input 
                            type="password" 
                            id="new_password" 
                            name="new_password" 
                            class="form-input" 
                            required
                            minlength="6"
                            placeholder="Mínimo 6 caracteres"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">
                            <i class="fas fa-lock"></i>
                            Confirmar Nueva Contraseña
                        </label>
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password" 
                            class="form-input" 
                            required
                            minlength="6"
                            placeholder="Repite la nueva contraseña"
                        >
                    </div>
                    
                    <div style="background: var(--bg-primary); padding: 16px; border-radius: 8px; margin-bottom: 20px;">
                        <h4 style="color: var(--text-secondary); font-size: 14px; margin-bottom: 10px;">Consejos de seguridad:</h4>
                        <ul style="font-size: 13px; color: var(--text-secondary); margin: 0; padding-left: 16px;">
                            <li>Usa al menos 6 caracteres</li>
                            <li>Combina letras, números y símbolos</li>
                            <li>No uses información personal</li>
                            <li>Cambia tu contraseña regularmente</li>
                        </ul>
                    </div>
                    
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <button type="submit" name="change_password" class="btn" style="background: var(--warning-color); color: white;">
                        <i class="fas fa-shield-alt"></i>
                        Cambiar Contraseña
                    </button>
                </form>
            </div>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
</div>

<script>
// Validación en tiempo real para confirmar contraseña
document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = this.value;
    
    if (confirmPassword && newPassword !== confirmPassword) {
        this.setCustomValidity('Las contraseñas no coinciden');
    } else {
        this.setCustomValidity('');
    }
});
</script>
