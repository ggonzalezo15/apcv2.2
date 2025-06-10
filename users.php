<?php
require_once 'config.php';

if (!isLoggedIn() || !checkSessionTimeout()) {
    header('Location: auth/login.php');
    exit;
}

$pageTitle = 'Usuarios';
?>
<?php include 'includes/header.php'; ?>

<div class="main-layout">
    <?php include 'includes/sidebar.php'; ?>
    <main class="content">
        <div class="content-header">
            <h1 class="content-title">
                <i class="fas fa-users"></i>
                Gestión de Usuarios
            </h1>
            <p class="content-subtitle">Administrar usuarios del sistema</p>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-table"></i>
                    Lista de Usuarios
                </h3>
                <p class="card-subtitle">Usuarios registrados en el sistema</p>
            </div>
            <div style="overflow-x: auto;">
                <table class="data-table" style="min-width: 700px;">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Email</th>
                            <th>Estado</th>
                            <th>Último Acceso</th>
                            <th style="width: 120px; text-align: center;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div style="width: 32px; height: 32px; background: var(--primary-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                                        <?php echo strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: 500;"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Usuario'); ?></div>
                                        <div style="font-size: 12px; color: var(--text-secondary);">Administrador</div>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($_SESSION['email'] ?? 'usuario@ejemplo.com'); ?></td>
                            <td>
                                <span style="color: var(--success-color); font-weight: 500;">
                                    <i class="fas fa-circle" style="font-size: 8px; margin-right: 6px;"></i>
                                    Activo
                                </span>
                            </td>
                            <td><?php echo date('d/m/Y H:i'); ?></td>
                            <td style="text-align: center;">
                                <div style="display: flex; gap: 8px; justify-content: center;">
                                    <button type="button" class="btn-action" title="Editar" onclick="showToast('Funcionalidad en desarrollo', 'info')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn-action btn-danger" title="Eliminar" onclick="showToast('No se puede eliminar el usuario actual', 'warning')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Información del sistema -->
        <div class="card" style="margin-top: 24px;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-info-circle"></i>
                    Información del Sistema
                </h3>
            </div>
            <div style="padding: 20px 0;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                    <div style="background: var(--bg-secondary); padding: 16px; border-radius: 8px;">
                        <h4 style="margin: 0 0 8px 0; color: var(--primary-color);">Usuarios Totales</h4>
                        <p style="font-size: 24px; font-weight: bold; margin: 0;">1</p>
                        <p style="font-size: 12px; color: var(--text-secondary); margin: 4px 0 0 0;">Usuario activo</p>
                    </div>
                    <div style="background: var(--bg-secondary); padding: 16px; border-radius: 8px;">
                        <h4 style="margin: 0 0 8px 0; color: var(--success-color);">Sesiones Activas</h4>
                        <p style="font-size: 24px; font-weight: bold; margin: 0;">1</p>
                        <p style="font-size: 12px; color: var(--text-secondary); margin: 4px 0 0 0;">Sesión actual</p>
                    </div>
                    <div style="background: var(--bg-secondary); padding: 16px; border-radius: 8px;">
                        <h4 style="margin: 0 0 8px 0; color: var(--warning-color);">Último Backup</h4>
                        <p style="font-size: 18px; font-weight: bold; margin: 0;">No disponible</p>
                        <p style="font-size: 12px; color: var(--text-secondary); margin: 4px 0 0 0;">Configurar backup automático</p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Toast de notificación -->
<div id="toast" style="position: fixed; bottom: 32px; right: 32px; min-width: 220px; z-index: 9999; display: none; background: var(--primary-color, #2563eb); color: #fff; padding: 16px 24px; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,0.12); font-size: 16px; font-weight: 500; align-items: center; gap: 8px;">
    <span id="toastIcon" style="margin-right: 8px;"></span>
    <span id="toastMessage"></span>
</div>

<script>
function showToast(message, type = 'info') {
    const toast = document.getElementById('toast');
    const toastMessage = document.getElementById('toastMessage');
    const toastIcon = document.getElementById('toastIcon');
    
    if (!toast || !toastMessage || !toastIcon) return;
    
    let icon = 'fas fa-info-circle';
    let backgroundColor = '#2563eb';
    
    switch (type) {
        case 'success':
            icon = 'fas fa-check-circle';
            backgroundColor = '#10b981';
            break;
        case 'error':
            icon = 'fas fa-exclamation-circle';
            backgroundColor = '#ef4444';
            break;
        case 'warning':
            icon = 'fas fa-exclamation-triangle';
            backgroundColor = '#f59e0b';
            break;
    }
    
    toastIcon.className = icon;
    toastMessage.textContent = message;
    toast.style.backgroundColor = backgroundColor;
    toast.style.display = 'flex';
    
    setTimeout(() => {
        toast.style.display = 'none';
    }, 3000);
}
</script>

<?php include 'includes/footer.php'; ?> 
