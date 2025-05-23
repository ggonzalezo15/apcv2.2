<?php
require_once 'config.php';

// Verificar autenticación
if (!isLoggedIn() || !checkSessionTimeout()) {
    header('Location: login.php');
    exit;
}

$pageTitle = 'Configuración';
?>

<?php include 'includes/header.php'; ?>

<div class="main-layout">
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="content">
        <div class="content-header">
            <h1 class="content-title">
                <i class="fas fa-cog"></i>
                Configuración
            </h1>
            <p class="content-subtitle">Personaliza tu experiencia en el sistema</p>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px;">
            <!-- Configuración de apariencia -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-palette"></i>
                        Apariencia
                    </h3>
                    <p class="card-subtitle">Personaliza la interfaz del sistema</p>
                </div>
                
                <div style="padding: 10px 0;">
                    <div style="margin-bottom: 20px;">
                        <label class="form-label">Tema:</label>
                        <div style="display: flex; gap: 10px; margin-top: 8px;">
                            <button class="btn" style="background: var(--primary-color); color: white; flex: 1;">
                                <i class="fas fa-sun"></i>
                                Claro
                            </button>
                            <button class="btn" style="background: var(--secondary-color); color: white; flex: 1;">
                                <i class="fas fa-moon"></i>
                                Oscuro
                            </button>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label class="form-label">Sidebar por defecto:</label>
                        <div style="margin-top: 8px;">
                            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                <input type="checkbox" checked disabled>
                                <span>Siempre colapsado (recomendado)</span>
                            </label>
                        </div>
                    </div>
                    
                    <div>
                        <label class="form-label">Animaciones:</label>
                        <div style="margin-top: 8px;">
                            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                <input type="checkbox" checked>
                                <span>Habilitar transiciones suaves</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Configuración de seguridad -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-shield-alt"></i>
                        Seguridad
                    </h3>
                    <p class="card-subtitle">Configura las opciones de seguridad</p>
                </div>
                
                <div style="padding: 10px 0;">
                    <div style="margin-bottom: 20px;">
                        <label class="form-label">Timeout de sesión:</label>
                        <select class="form-input" style="margin-top: 8px;">
                            <option value="3600" selected>1 hora</option>
                            <option value="7200">2 horas</option>
                            <option value="14400">4 horas</option>
                            <option value="28800">8 horas</option>
                        </select>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label class="form-label">Verificación en dos pasos:</label>
                        <div style="margin-top: 8px;">
                            <button class="btn" style="background: var(--success-color); color: white; width: 100%;">
                                <i class="fas fa-mobile-alt"></i>
                                Configurar 2FA
                            </button>
                        </div>
                    </div>
                    
                    <div>
                        <label class="form-label">Sesiones activas:</label>
                        <div style="background: var(--bg-primary); padding: 12px; border-radius: 6px; margin-top: 8px;">
                            <p style="font-size: 13px; margin-bottom: 8px;">
                                <strong>Actual:</strong> <?php echo $_SERVER['REMOTE_ADDR']; ?>
                            </p>
                            <p style="font-size: 13px; color: var(--text-secondary);">
                                Iniciada: <?php echo date('d/m/Y H:i', $_SESSION['login_time'] ?? time()); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Configuración de notificaciones -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-bell"></i>
                        Notificaciones
                    </h3>
                    <p class="card-subtitle">Gestiona cómo recibes las notificaciones</p>
                </div>
                
                <div style="padding: 10px 0;">
                    <div style="margin-bottom: 16px;">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" checked>
                            <span>Notificaciones por email</span>
                        </label>
                    </div>
                    
                    <div style="margin-bottom: 16px;">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" checked>
                            <span>Alertas de seguridad</span>
                        </label>
                    </div>
                    
                    <div style="margin-bottom: 16px;">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox">
                            <span>Actualizaciones del sistema</span>
                        </label>
                    </div>
                    
                    <div>
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox">
                            <span>Newsletter semanal</span>
                        </label>
                    </div>
                </div>
            </div>
            
            <!-- Configuración de privacidad -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-user-secret"></i>
                        Privacidad
                    </h3>
                    <p class="card-subtitle">Controla tu información y privacidad</p>
                </div>
                
                <div style="padding: 10px 0;">
                    <div style="margin-bottom: 16px;">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" checked>
                            <span>Registro de actividad</span>
                        </label>
                    </div>
                    
                    <div style="margin-bottom: 16px;">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox">
                            <span>Permitir analytics</span>
                        </label>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox">
                            <span>Compartir datos de uso</span>
                        </label>
                    </div>
                    
                    <div style="border-top: 1px solid var(--border-color); padding-top: 16px;">
                        <button class="btn" style="background: var(--danger-color); color: white; width: 100%; margin-bottom: 8px;">
                            <i class="fas fa-download"></i>
                            Descargar mis datos
                        </button>
                        <button class="btn" style="background: var(--warning-color); color: white; width: 100%;" onclick="confirmAction('¿Estás seguro de que quieres eliminar tu cuenta? Esta acción no se puede deshacer.', function() { alert('Funcionalidad en desarrollo'); })">
                            <i class="fas fa-user-times"></i>
                            Eliminar cuenta
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Botón de guardar cambios -->
        <div style="margin-top: 30px; text-align: center;">
            <button class="btn btn-primary" style="padding: 12px 40px; font-size: 16px;">
                <i class="fas fa-save"></i>
                Guardar Configuración
            </button>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
</div>
