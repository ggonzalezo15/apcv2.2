<?php
require_once 'config.php';

// Verificar autenticación
if (!isLoggedIn() || !checkSessionTimeout()) {
    header('Location: login.php');
    exit;
}

// Generar token CSRF para las operaciones AJAX
$csrfToken = generateCSRFToken();

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
        
        <div style="display: flex; gap: 24px; min-height: 600px;">
            <!-- Sidebar interno de configuración (ESTÁTICO) -->
            <div style="width: 280px; flex-shrink: 0;">
                <div class="card" style="height: fit-content;">
                    <div style="padding: 20px 0;">
                        <nav style="display: flex; flex-direction: column;">
                            <a href="#" onclick="loadSection('profile')" class="config-nav-item active" data-section="profile">
                                <i class="fas fa-user"></i>
                                <span>Perfil</span>
                            </a>
                            <a href="#" onclick="loadSection('users')" class="config-nav-item" data-section="users">
                                <i class="fas fa-users"></i>
                                <span>Usuarios</span>
                            </a>
                            <a href="#" onclick="loadSection('payment_types')" class="config-nav-item" data-section="payment_types">
                                <i class="fas fa-credit-card"></i>
                                <span>Tipos de pagos</span>
                            </a>
                            <a href="#" onclick="loadSection('expense_categories')" class="config-nav-item" data-section="expense_categories">
                                <i class="fas fa-tags"></i>
                                <span>Categorías de gastos</span>
                            </a>
                            <a href="#" onclick="loadSection('expense_types')" class="config-nav-item" data-section="expense_types">
                                <i class="fas fa-list"></i>
                                <span>Tipos de gastos</span>
                            </a>
                            <a href="#" onclick="loadSection('job_types')" class="config-nav-item" data-section="job_types">
                                <i class="fas fa-briefcase"></i>
                                <span>Tipos de trabajos</span>
                            </a>
                        </nav>
                    </div>
                </div>
            </div>
            
            <!-- Contenido dinámico -->
            <div style="flex: 1;" id="settingsContent">
                <!-- El contenido se carga dinámicamente aquí -->
            </div>
        </div>
    </main>
</div>

<!-- MODALES UNIFICADOS -->
<!-- Modal genérico para formularios -->
<div class="modal" id="formModal">
    <div class="modal-overlay" onclick="closeModal('formModal')"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="formModalTitle">Formulario</h2>
            <button type="button" class="modal-close" onclick="closeModal('formModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="formModalBody">
            <!-- Contenido del formulario se carga dinámicamente -->
        </div>
    </div>
</div>

<!-- Modal de confirmación de eliminación -->
<div class="modal" id="confirmDeleteModal" style="display:none;">
    <div class="modal-overlay" onclick="closeModal('confirmDeleteModal')"></div>
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h2>Confirmar eliminación</h2>
            <button type="button" class="modal-close" onclick="closeModal('confirmDeleteModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <p id="confirmDeleteMessage">¿Está seguro de que desea eliminar este elemento?</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn" onclick="closeModal('confirmDeleteModal')">Cancelar</button>
            <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Eliminar</button>
        </div>
    </div>
</div>

<!-- Toast de notificación -->
<div id="toast" style="position: fixed; bottom: 32px; right: 32px; min-width: 220px; z-index: 9999; display: none; background: var(--primary-color, #2563eb); color: #fff; padding: 16px 24px; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,0.12); font-size: 16px; font-weight: 500; align-items: center; gap: 8px;">
    <span id="toastIcon" style="margin-right: 8px;"></span>
    <span id="toastMessage"></span>
</div>

<!-- Estilos para el sidebar de configuración -->
<style>
.config-nav-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 20px;
    color: var(--text-secondary);
    text-decoration: none;
    border-left: 3px solid transparent;
    transition: all 0.2s ease;
    font-weight: 500;
    cursor: pointer;
}

.config-nav-item:hover {
    color: var(--primary-color);
    background-color: var(--bg-secondary);
}

.config-nav-item.active {
    color: var(--primary-color);
    background-color: var(--bg-primary);
    border-left-color: var(--primary-color);
}

.config-nav-item i {
    width: 20px;
    text-align: center;
}

.form-group {
    margin-bottom: 16px;
}

.form-label {
    display: block;
    margin-bottom: 6px;
    font-weight: 500;
    color: var(--text-primary);
}

.form-input {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.2s ease;
}

.form-input:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.form-input:readonly {
    background-color: var(--bg-secondary);
    color: var(--text-secondary);
}

.loading-spinner {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 40px;
}

.spinner {
    width: 40px;
    height: 40px;
    border: 4px solid var(--bg-secondary);
    border-top: 4px solid var(--primary-color);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Estilos para botones más compactos */
.btn {
    padding: 12px 24px;
    font-size: 14px;
    white-space: nowrap;
    min-width: auto;
    width: auto;
}

.btn-primary {
    max-width: fit-content;
}

.btn i {
    margin-right: 6px;
}

/* HR específico para el sidebar interno de configuración */
.card hr {
    border: none;
    height: 1px;
    background-color: var(--border-color, #e2e8f0);
    margin: 16px 0;
    opacity: 0.4;
}
</style>

<script>
// Estado global de la aplicación
let currentSection = 'profile';
let currentData = {};

// Cargar sección inicial
document.addEventListener('DOMContentLoaded', function() {
    loadSection('profile');
});

// Función principal para cargar secciones
function loadSection(section) {
    // Mostrar loading
    showLoading();
    
    // Actualizar navegación
    updateNavigation(section);
    
    // Cargar contenido según la sección
    currentSection = section;
    
    setTimeout(() => {
        switch(section) {
            case 'profile':
                loadProfileSection();
                break;
            case 'users':
                loadUsersSection();
                break;
            case 'payment_types':
                loadPaymentTypesSection();
                break;
            case 'expense_categories':
                loadExpenseCategoriesSection();
                break;
            case 'expense_types':
                loadExpenseTypesSection();
                break;
            case 'job_types':
                loadJobTypesSection();
                break;
        }
    }, 200);
}

function showLoading() {
    document.getElementById('settingsContent').innerHTML = `
        <div class="loading-spinner">
            <div class="spinner"></div>
        </div>
    `;
}

function updateNavigation(section) {
    // Remover clase active de todos los enlaces
    document.querySelectorAll('.config-nav-item').forEach(item => {
        item.classList.remove('active');
    });
    
    // Agregar clase active al enlace seleccionado
    document.querySelector(`[data-section="${section}"]`).classList.add('active');
}

// SECCIÓN PERFIL
function loadProfileSection() {
    const content = `
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-user"></i>
                    Mi Perfil
                </h3>
                <p class="card-subtitle">Gestiona tu información personal y configuración de cuenta</p>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px; padding: 20px 0;">
                <!-- Información Personal -->
                <div>
                    <h4 style="margin-bottom: 20px; color: var(--text-primary); font-weight: 600;">
                        <i class="fas fa-id-card"></i>
                        Información Personal
                    </h4>
                    <p style="margin-bottom: 16px; color: var(--text-secondary); font-size: 14px;">
                        Actualiza tu información básica
                    </p>
                    
                    <form id="profileForm">
                        <div class="form-group">
                            <label class="form-label" for="username">Usuario</label>
                            <input type="text" class="form-input" id="username" name="username" value="<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="email">Email</label>
                            <input type="email" class="form-input" id="email" name="email" value="<?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>">
                        </div>
                        
                        <div style="margin-top: 20px;">
                            <p style="font-size: 14px; color: var(--text-secondary); margin-bottom: 8px;">Información de la cuenta:</p>
                            <div style="background: var(--bg-secondary); padding: 12px; border-radius: 6px; font-size: 13px;">
                                <p><strong>Creado:</strong> <?php echo date('d/m/Y H:i'); ?></p>
                                <p><strong>Último acceso:</strong> <?php echo date('d/m/Y H:i'); ?></p>
                                <p><strong>Estado:</strong> <span style="color: var(--success-color);">Activo</span></p>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="margin-top: 20px;">
                            <i class="fas fa-save"></i>
                            Actualizar Perfil
                        </button>
                    </form>
                </div>
                
                <!-- Cambiar Contraseña -->
                <div>
                    <h4 style="margin-bottom: 20px; color: var(--text-primary); font-weight: 600;">
                        <i class="fas fa-lock"></i>
                        Cambiar Contraseña
                    </h4>
                    <p style="margin-bottom: 16px; color: var(--text-secondary); font-size: 14px;">
                        Actualiza tu contraseña por seguridad
                    </p>
                    
                    <form id="passwordForm">
                        <div class="form-group">
                            <label class="form-label" for="currentPassword">Contraseña Actual</label>
                            <input type="password" class="form-input" id="currentPassword" name="currentPassword" placeholder="Ingresa tu contraseña actual">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="newPassword">Nueva Contraseña</label>
                            <input type="password" class="form-input" id="newPassword" name="newPassword" placeholder="Mínimo 6 caracteres">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="confirmPassword">Confirmar Nueva Contraseña</label>
                            <input type="password" class="form-input" id="confirmPassword" name="confirmPassword" placeholder="Repite la nueva contraseña">
                        </div>
                        
                        <div style="margin-top: 20px; padding: 12px; background: var(--bg-warning); border-radius: 6px; border-left: 4px solid var(--warning-color);">
                            <p style="font-size: 13px; margin: 0;"><strong>Consejos de seguridad:</strong></p>
                            <ul style="font-size: 12px; margin: 8px 0 0 16px; color: var(--text-secondary);">
                                <li>Usa al menos 6 caracteres</li>
                                <li>Combina letras, números y símbolos</li>
                                <li>Evita información personal</li>
                                <li>Cambia tu contraseña regularmente</li>
                            </ul>
                        </div>
                        
                        <button type="submit" class="btn" style="background-color: var(--warning-color); color: white; margin-top: 20px;">
                            <i class="fas fa-key"></i>
                            Cambiar Contraseña
                        </button>
                    </form>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('settingsContent').innerHTML = content;
    
    // Agregar event listeners para los formularios
    document.getElementById('profileForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const email = document.getElementById('email').value;
        
        fetch('api/user/UserController.php?action=updateProfile', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: email })
        })
        .then(res => res.json())
        .then(result => {
            if (result.error) {
                showToast(result.error, 'error');
                return;
            }
            showToast(result.message, 'success');
        })
        .catch(err => {
            console.error('Error updating profile:', err);
            showToast('Error al actualizar perfil', 'error');
        });
    });

    document.getElementById('passwordForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const currentPassword = document.getElementById('currentPassword').value;
        const newPassword = document.getElementById('newPassword').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        
        if (newPassword !== confirmPassword) {
            showToast('Las contraseñas no coinciden', 'error');
            return;
        }
        
        if (newPassword.length < 6) {
            showToast('La contraseña debe tener al menos 6 caracteres', 'error');
            return;
        }
        
        // Usar la API para cambiar contraseña (necesitamos el user_id de la sesión)
        fetch('api/user/UserController.php?action=updatePassword&id=<?php echo $_SESSION['user_id'] ?? '0'; ?>', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                current_password: currentPassword,
                new_password: newPassword 
            })
        })
        .then(res => res.json())
        .then(result => {
            if (result.error) {
                showToast(result.error, 'error');
                return;
            }
            showToast(result.message, 'success');
            this.reset();
        })
        .catch(err => {
            console.error('Error updating password:', err);
            showToast('Error al cambiar contraseña', 'error');
        });
    });
}

// SECCIÓN USUARIOS
function loadUsersSection() {
    const content = `
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-users"></i>
                    Gestión de Usuarios
                </h3>
                <p class="card-subtitle">Administrar usuarios del sistema</p>
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
    `;
    
    document.getElementById('settingsContent').innerHTML = content;
}

// Las otras secciones continúan... (payment_types, expense_categories, expense_types)
// Por brevedad, las implementaré en la siguiente parte del archivo

// FUNCIONES AUXILIARES
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

function openModal(modalId) {
    document.getElementById(modalId).style.display = 'flex';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}
</script>

<script>
// Token CSRF para operaciones AJAX
window.CSRF_TOKEN = '<?php echo htmlspecialchars($csrfToken); ?>';
</script>

<!-- Incluir el archivo JavaScript con las funciones adicionales -->
<script src="assets/js/settings.js"></script>

<?php include 'includes/footer.php'; ?>
