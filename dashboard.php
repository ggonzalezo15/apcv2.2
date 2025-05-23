<?php
require_once 'config.php';

// Verificar autenticación
if (!isLoggedIn() || !checkSessionTimeout()) {
    header('Location: login.php');
    exit;
}

$pageTitle = 'Panel Principal';
?>
<?php include 'includes/header.php'; ?>

<div class="main-layout">
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="content">
        <div class="content-header">
            <h1 class="content-title">
                <i class="fas fa-home"></i>
                Bienvenido, <?php echo htmlspecialchars($_SESSION['username']); ?>
            </h1>
            <p class="content-subtitle">Panel de control principal</p>
        </div>
        
        <div class="dashboard-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 24px; margin-bottom: 30px;">
            <!-- Tarjeta de estadísticas -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-chart-line" style="color: var(--success-color);"></i>
                        Estadísticas
                    </h3>
                </div>
                <div style="text-align: center; padding: 20px 0;">
                    <div style="font-size: 32px; font-weight: bold; color: var(--success-color); margin-bottom: 8px;">
                        127
                    </div>
                    <div style="color: var(--text-secondary);">Usuarios Activos</div>
                </div>
            </div>
            
            <!-- Tarjeta de sesión -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-clock" style="color: var(--info-color);"></i>
                        Tu Sesión
                    </h3>
                </div>
                <div style="padding: 10px 0;">
                    <p style="margin-bottom: 8px;">
                        <strong>Inicio:</strong> 
                        <?php echo date('d/m/Y H:i', $_SESSION['login_time'] ?? time()); ?>
                    </p>
                    <p style="margin-bottom: 8px;">
                        <strong>Duración:</strong> 
                        <?php 
                        $duration = time() - ($_SESSION['login_time'] ?? time());
                        $hours = floor($duration / 3600);
                        $minutes = floor(($duration % 3600) / 60);
                        echo sprintf('%02d:%02d', $hours, $minutes);
                        ?>
                    </p>
                    <p>
                        <strong>IP:</strong> 
                        <?php echo htmlspecialchars($_SERVER['REMOTE_ADDR']); ?>
                    </p>
                </div>
            </div>
            
            <!-- Tarjeta de acciones rápidas -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-bolt" style="color: var(--warning-color);"></i>
                        Acciones Rápidas
                    </h3>
                </div>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <a href="profile.php" class="btn" style="background: var(--primary-color); color: white; text-decoration: none; padding: 10px 16px; border-radius: 6px;">
                        <i class="fas fa-user"></i>
                        Ver Perfil
                    </a>
                    <a href="settings.php" class="btn" style="background: var(--secondary-color); color: white; text-decoration: none; padding: 10px 16px; border-radius: 6px;">
                        <i class="fas fa-cog"></i>
                        Configuración
                    </a>
                </div>
            </div>
            
            <!-- Tarjeta de sistema -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-server" style="color: var(--danger-color);"></i>
                        Sistema
                    </h3>
                </div>
                <div style="padding: 10px 0;">
                    <p style="margin-bottom: 8px;">
                        <strong>PHP:</strong> <?php echo PHP_VERSION; ?>
                    </p>
                    <p style="margin-bottom: 8px;">
                        <strong>Servidor:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'No disponible'; ?>
                    </p>
                    <p>
                        <strong>Memoria:</strong> <?php echo ini_get('memory_limit'); ?>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Contenido principal -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-info-circle"></i>
                    Información del Sistema
                </h3>
                <p class="card-subtitle">Detalles sobre el funcionamiento de la aplicación</p>
            </div>
            
            <div style="line-height: 1.8;">
                <h4 style="color: var(--primary-color); margin-bottom: 16px;">Características del Sistema:</h4>
                <ul style="list-style: none; padding: 0;">
                    <li style="margin-bottom: 12px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-circle" style="color: var(--success-color);"></i>
                        <span><strong>Autenticación Segura:</strong> Sistema de login/logout con protección CSRF</span>
                    </li>
                    <li style="margin-bottom: 12px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-circle" style="color: var(--success-color);"></i>
                        <span><strong>Layout Responsivo:</strong> Sidebar colapsable que se expande automáticamente</span>
                    </li>
                    <li style="margin-bottom: 12px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-circle" style="color: var(--success-color);"></i>
                        <span><strong>Gestión de Sesiones:</strong> Control de timeout y seguridad avanzada</span>
                    </li>
                    <li style="margin-bottom: 12px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-circle" style="color: var(--success-color);"></i>
                        <span><strong>Interfaz Moderna:</strong> Diseño limpio y profesional con CSS Grid/Flexbox</span>
                    </li>
                    <li style="margin-bottom: 12px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-circle" style="color: var(--success-color);"></i>
                        <span><strong>Base de Datos PDO:</strong> Conexión segura con prepared statements</span>
                    </li>
                </ul>
                
                <div style="background: var(--bg-primary); padding: 20px; border-radius: 8px; margin-top: 24px;">
                    <h4 style="color: var(--primary-color); margin-bottom: 12px;">
                        <i class="fas fa-lightbulb"></i>
                        Funcionalidades del Sidebar:
                    </h4>
                    <p style="margin-bottom: 10px;">• <strong>Estado por defecto:</strong> Siempre colapsado mostrando solo iconos</p>
                    <p style="margin-bottom: 10px;">• <strong>Expansión automática:</strong> Se expande al pasar el mouse por encima</p>
                    <p style="margin-bottom: 10px;">• <strong>Responsive:</strong> En móviles se convierte en menú lateral deslizable</p>
                    <p>• <strong>Navegación intuitiva:</strong> Enlaces activos resaltados según la página actual</p>
                </div>
            </div>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
</div>
