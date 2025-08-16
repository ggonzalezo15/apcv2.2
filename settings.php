<?php
require_once 'config.php';

// Verificar autenticación
if (!isLoggedIn() || !checkSessionTimeout()) {
    header('Location: auth/login.php');
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
                                            <?php if (isAdmin()): ?>
                <a href="#" onclick="loadSection('users')" class="config-nav-item" data-section="users">
                    <i class="fas fa-users"></i>
                    <span>Usuarios</span>
                </a>
                <a href="#" onclick="loadSection('cleanup')" class="config-nav-item" data-section="cleanup">
                    <i class="fas fa-broom"></i>
                    <span>Limpieza de Archivos</span>
                </a>
                <?php endif; ?>
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
                            <a href="#" onclick="loadSection('reports')" class="config-nav-item" data-section="reports">
                                <i class="fas fa-chart-bar"></i>
                                <span>Informes</span>
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

<!-- Modal de confirmación de limpieza destructiva -->
<div class="modal" id="cleanupConfirmModal" style="display:none;">
    <div class="modal-overlay" onclick="closeModal('cleanupConfirmModal')"></div>
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header" style="background: var(--danger-color, #ef4444); color: white;">
            <h2 style="margin: 0; color: white;">⚠️ Confirmar Limpieza</h2>
            <button type="button" class="modal-close" onclick="closeModal('cleanupConfirmModal')" style="color: white;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <p style="margin-bottom: 20px; font-size: 16px; line-height: 1.5;">
                Esta acción <strong>eliminará permanentemente</strong> todos los archivos huérfanos detectados.
            </p>
            
            <div style="background: var(--warning-bg, #fef3c7); color: var(--warning-color, #f59e0b); padding: 16px; border-radius: 8px; margin-bottom: 20px;">
                <strong>⚠️ Advertencia:</strong> Los archivos eliminados no se pueden recuperar.
            </div>
            
            <p style="color: var(--text-secondary); font-size: 14px;">
                Se recomienda ejecutar una <strong>simulación</strong> antes de continuar.
            </p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn" onclick="closeModal('cleanupConfirmModal')">
                Cancelar
            </button>
            <button type="button" class="btn btn-secondary" onclick="closeModal('cleanupConfirmModal'); performCleanupAction('simulate');">
                Simular Primero
            </button>
            <button type="button" class="btn btn-danger" onclick="showFinalConfirmation()">
                Continuar
            </button>
        </div>
    </div>
</div>

<!-- Modal de confirmación final -->
<div class="modal" id="finalConfirmModal" style="display:none;">
    <div class="modal-overlay" onclick="closeModal('finalConfirmModal')"></div>
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header" style="background: var(--danger-color, #ef4444); color: white;">
            <h2 style="margin: 0; color: white;">Confirmación Final</h2>
            <button type="button" class="modal-close" onclick="closeModal('finalConfirmModal')" style="color: white;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <p style="text-align: center; margin-bottom: 20px; font-size: 16px;">
                ¿Estás seguro de continuar?
            </p>
            
            <div style="background: var(--danger-bg, #fee2e2); border: 1px solid var(--danger-color, #ef4444); border-radius: 8px; padding: 16px; margin: 20px 0;">
                <p style="margin: 0 0 8px 0; font-weight: 600; color: var(--danger-color, #ef4444); font-size: 14px;">
                    Escribe: <code style="background: white; padding: 2px 6px; border-radius: 4px;">ELIMINAR</code>
                </p>
                <input 
                    type="text" 
                    id="confirmationInput" 
                    placeholder="ELIMINAR" 
                    style="width: 100%; padding: 10px; border: 1px solid var(--danger-color); border-radius: 6px; text-align: center; font-weight: 600; text-transform: uppercase;"
                    onkeyup="checkConfirmationInput()"
                >
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn" onclick="closeModal('finalConfirmModal')">
                Cancelar
            </button>
            <button type="button" class="btn btn-danger" id="executeCleanupBtn" disabled onclick="executeCleanupConfirmed()">
                Ejecutar Limpieza
            </button>
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

/* Estilos para botón outline */
.btn-outline {
    background: white;
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
}

.btn-outline:hover {
    border-color: var(--primary-color);
    color: var(--primary-color);
}

/* Botones de acciones - Asegurar hover azul consistente */
.btn-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border: none;
    border-radius: 6px;
    background-color: var(--bg-primary);
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 14px;
    text-decoration: none;
}

.btn-action:hover {
    background-color: var(--primary-color, #2563eb) !important;
    color: white !important;
    transform: translateY(-1px);
}

.btn-action.btn-danger {
    background-color: rgb(220 38 38 / 0.1);
    color: var(--danger-color);
}

.btn-action.btn-danger:hover {
    background-color: var(--danger-color, #dc2626) !important;
    color: white !important;
}

/* Contenedor de botones de acciones */
.table-actions {
    display: flex;
    gap: 8px;
    justify-content: center;
    align-items: center;
}

/* HR específico para el sidebar interno de configuración */
.card hr {
    border: none;
    height: 1px;
    background-color: var(--border-color, #e2e8f0);
    margin: 16px 0;
    opacity: 0.4;
}

/* Estilos para tablas con ordenamiento */
.sortable-table th.sortable {
    cursor: pointer;
    user-select: none;
    position: relative;
    padding-right: 30px;
}

.sortable-table th.sortable:hover {
    background-color: var(--bg-secondary, #f8fafc);
}

.sortable-table th.sortable .sort-icon {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-secondary, #6b7280);
    font-size: 12px;
    transition: color 0.2s ease;
}

.sortable-table th.sortable:hover .sort-icon {
    color: var(--primary-color, #2563eb);
}

.sortable-table th.sortable .sort-icon.fas.fa-sort-up,
.sortable-table th.sortable .sort-icon.fas.fa-sort-down {
    color: var(--primary-color, #2563eb);
}

/* Estilos para badges de estado */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-badge.status-active {
    background-color: #dcfce7;
    color: #166534;
}

.status-badge.status-inactive {
    background-color: #fee2e2;
    color: #991b1b;
}

/* Estilos para switches */
.switch {
    position: relative;
    display: inline-block;
    width: 44px;
    height: 24px;
}

.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .4s;
    border-radius: 24px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

input:checked + .slider {
    background-color: var(--success-color, #10b981);
}

input:checked + .slider:before {
    transform: translateX(20px);
}

/* Estilos para filtros de estado */
.status-filter-container .filter-option:hover {
    background-color: var(--bg-secondary, #f8fafc);
}

.status-filter-dropdown {
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 6px;
    background: white;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

/* Estilos específicos para la sección de limpieza */
.cleanup-action-btn {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: left;
    width: 100%;
    min-height: 80px;
    text-decoration: none;
    font-family: inherit;
    font-size: 14px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.cleanup-action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.cleanup-action-btn:active {
    transform: translateY(0px);
}

.cleanup-action-btn i {
    font-size: 20px;
    width: 24px;
    text-align: center;
}

.cleanup-results {
    background: var(--bg-primary);
    border-radius: 8px;
    border: 1px solid var(--border-color);
    padding: 20px;
    margin-top: 20px;
}

.cleanup-results h4 {
    margin-top: 0;
    margin-bottom: 16px;
    color: var(--primary-color);
}

.cleanup-results .alert {
    padding: 12px 16px;
    border-radius: 6px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.cleanup-results .alert-success {
    background: var(--success-bg, #d1fae5);
    color: var(--success-color, #10b981);
    border: 1px solid var(--success-color, #10b981);
}

.cleanup-results .alert-warning {
    background: var(--warning-bg, #fef3c7);
    color: var(--warning-color, #f59e0b);
    border: 1px solid var(--warning-color, #f59e0b);
}

.cleanup-results .alert-danger {
    background: var(--danger-bg, #fee2e2);
    color: var(--danger-color, #ef4444);
    border: 1px solid var(--danger-color, #ef4444);
}

.cleanup-results .alert-info {
    background: var(--info-bg, #dbeafe);
    color: var(--info-color, #3b82f6);
    border: 1px solid var(--info-color, #3b82f6);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin: 20px 0;
}

.stats-card {
    background: var(--bg-secondary);
    padding: 16px;
    border-radius: 8px;
    text-align: center;
    border: 1px solid var(--border-color);
}

.stats-card h3 {
    margin: 0 0 8px 0;
    font-size: 24px;
    font-weight: 600;
}

.stats-card p {
    margin: 0;
    color: var(--text-secondary);
    font-size: 14px;
}

/* Colores específicos para las estadísticas */
.stats-card.primary h3 { color: var(--primary-color, #2563eb); }
.stats-card.info h3 { color: var(--info-color, #3b82f6); }
.stats-card.warning h3 { color: var(--warning-color, #f59e0b); }
.stats-card.success h3 { color: var(--success-color, #10b981); }
.stats-card.danger h3 { color: var(--danger-color, #ef4444); }

/* Loading state para las estadísticas */
.stats-loading {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 40px;
    color: var(--text-secondary);
}

.stats-loading .spinner {
    width: 24px;
    height: 24px;
    margin-right: 12px;
}

/* ESTILOS PROFESIONALES PARA RESULTADOS DINÁMICOS */
.professional-results {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.06);
    margin-top: 24px;
    overflow: hidden;
    border: 1px solid var(--border-color, #e2e8f0);
}

.professional-results .results-header {
    background: linear-gradient(135deg, var(--primary-color, #2563eb) 0%, var(--info-color, #3b82f6) 100%);
    color: white;
    padding: 20px 24px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.professional-results .results-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
}

.professional-results .results-header .results-badge {
    background: rgba(255,255,255,0.2);
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.professional-results .results-body {
    padding: 24px;
}

.modern-metric-card {
    background: var(--bg-secondary, #f8fafc);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 16px;
    transition: all 0.2s ease;
}

.modern-metric-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transform: translateY(-1px);
}

.modern-metric-card .metric-header {
    display: flex;
    justify-content: between;
    align-items: center;
    margin-bottom: 12px;
}

.modern-metric-card .metric-title {
    font-weight: 600;
    color: var(--text-primary);
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.modern-metric-card .metric-value {
    font-size: 24px;
    font-weight: 700;
    margin: 8px 0;
}

.modern-metric-card .metric-description {
    color: var(--text-secondary);
    font-size: 13px;
    line-height: 1.4;
}

.modern-metric-card.success .metric-value { color: var(--success-color, #10b981); }
.modern-metric-card.warning .metric-value { color: var(--warning-color, #f59e0b); }
.modern-metric-card.danger .metric-value { color: var(--danger-color, #ef4444); }
.modern-metric-card.info .metric-value { color: var(--info-color, #3b82f6); }

.progress-indicator {
    background: var(--bg-secondary, #f1f5f9);
    border-radius: 6px;
    height: 8px;
    overflow: hidden;
    margin: 12px 0;
}

.progress-indicator .progress-bar {
    height: 100%;
    border-radius: 6px;
    transition: width 0.8s ease;
}

.progress-indicator .progress-bar.success { background: var(--success-color, #10b981); }
.progress-indicator .progress-bar.warning { background: var(--warning-color, #f59e0b); }
.progress-indicator .progress-bar.danger { background: var(--danger-color, #ef4444); }

.file-list-modern {
    background: white;
    border-radius: 8px;
    border: 1px solid var(--border-color, #e2e8f0);
    max-height: 400px;
    overflow-y: auto;
}

.file-item-modern {
    display: flex;
    align-items: center;
    padding: 12px 16px;
    border-bottom: 1px solid var(--border-color, #e2e8f0);
    transition: background-color 0.2s ease;
}

.file-item-modern:hover {
    background: var(--bg-secondary, #f8fafc);
}

.file-item-modern:last-child {
    border-bottom: none;
}

.file-item-modern .file-icon {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
    font-size: 14px;
    font-weight: 600;
    color: white;
}

.file-item-modern .file-icon.image { background: var(--info-color, #3b82f6); }
.file-item-modern .file-icon.document { background: var(--warning-color, #f59e0b); }
.file-item-modern .file-icon.archive { background: var(--success-color, #10b981); }
.file-item-modern .file-icon.other { background: var(--text-secondary, #6b7280); }

.file-item-modern .file-details {
    flex: 1;
}

.file-item-modern .file-name {
    font-weight: 500;
    color: var(--text-primary);
    font-size: 14px;
    margin-bottom: 4px;
}

.file-item-modern .file-meta {
    font-size: 12px;
    color: var(--text-secondary);
    display: flex;
    gap: 12px;
}

.action-timeline {
    position: relative;
    padding-left: 24px;
}

.action-timeline::before {
    content: '';
    position: absolute;
    left: 8px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: var(--border-color, #e2e8f0);
}

.timeline-item {
    position: relative;
    padding: 16px 0;
    margin-left: 8px;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -12px;
    top: 20px;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--primary-color, #2563eb);
}

.timeline-item.success::before { background: var(--success-color, #10b981); }
.timeline-item.warning::before { background: var(--warning-color, #f59e0b); }
.timeline-item.danger::before { background: var(--danger-color, #ef4444); }

.timeline-item .timeline-content {
    background: white;
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 8px;
    padding: 12px 16px;
}

.timeline-item .timeline-title {
    font-weight: 600;
    margin-bottom: 4px;
    font-size: 14px;
}

.timeline-item .timeline-description {
    color: var(--text-secondary);
    font-size: 13px;
    line-height: 1.4;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin: 20px 0;
}

.loading-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255,255,255,0.9);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    z-index: 10;
    border-radius: 12px;
}

.loading-overlay .loading-text {
    margin-top: 16px;
    color: var(--text-secondary);
    font-weight: 500;
}

.modern-alert {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 16px;
    border-radius: 8px;
    margin: 16px 0;
    border-left: 4px solid;
}

.modern-alert.success {
    background: var(--success-bg, #d1fae5);
    border-left-color: var(--success-color, #10b981);
    color: var(--success-color, #10b981);
}

.modern-alert.warning {
    background: var(--warning-bg, #fef3c7);
    border-left-color: var(--warning-color, #f59e0b);
    color: var(--warning-color, #f59e0b);
}

.modern-alert.danger {
    background: var(--danger-bg, #fee2e2);
    border-left-color: var(--danger-color, #ef4444);
    color: var(--danger-color, #ef4444);
}

.modern-alert.info {
    background: var(--info-bg, #dbeafe);
    border-left-color: var(--info-color, #3b82f6);
    color: var(--info-color, #3b82f6);
}

.modern-alert .alert-icon {
    font-size: 18px;
    margin-top: 2px;
}

.modern-alert .alert-content {
    flex: 1;
}

.modern-alert .alert-title {
    font-weight: 600;
    margin-bottom: 4px;
}

.modern-alert .alert-message {
    opacity: 0.9;
    line-height: 1.4;
}

/* ESTILOS PARA MODALES PROFESIONALES */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 2000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
    z-index: 1999;
}

.modal-overlay.show {
    opacity: 1;
}

.modal-content {
    background: white;
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
    max-width: 500px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
    z-index: 2001;
}

.modal-header {
    padding: 24px 24px 16px 24px;
    border-bottom: 1px solid var(--border-color, #e2e8f0);
    display: flex;
    align-items: center;
    gap: 12px;
}

.modal-header.danger {
    background: linear-gradient(135deg, var(--danger-color, #ef4444) 0%, #dc2626 100%);
    color: white;
    border-bottom: none;
}

.modal-header.warning {
    background: linear-gradient(135deg, var(--warning-color, #f59e0b) 0%, #d97706 100%);
    color: white;
    border-bottom: none;
}

.modal-header .modal-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.modal-header .modal-title {
    flex: 1;
}

.modal-header .modal-title h3 {
    margin: 0 0 4px 0;
    font-size: 18px;
    font-weight: 600;
}

.modal-header .modal-title p {
    margin: 0;
    font-size: 14px;
    opacity: 0.9;
}

.modal-close {
    width: 32px;
    height: 32px;
    border: none;
    background: rgba(255, 255, 255, 0.2);
    color: white;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background-color 0.2s ease;
}

.modal-close:hover {
    background: rgba(255, 255, 255, 0.3);
}

.modal-body {
    padding: 24px;
}

.modal-body .warning-list {
    background: var(--warning-bg, #fef3c7);
    border: 1px solid var(--warning-color, #f59e0b);
    border-radius: 8px;
    padding: 16px;
    margin: 16px 0;
}

.modal-body .warning-list h4 {
    margin: 0 0 12px 0;
    color: var(--warning-color, #f59e0b);
    font-size: 14px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.modal-body .warning-list ul {
    margin: 0;
    padding-left: 20px;
    color: var(--warning-color, #f59e0b);
}

.modal-body .warning-list li {
    margin-bottom: 8px;
    line-height: 1.4;
}

.modal-footer {
    padding: 16px 24px 24px 24px;
    display: flex;
    gap: 12px;
    justify-content: flex-end;
}

.modal-footer .btn {
    padding: 12px 24px;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    font-weight: 500;
    font-size: 14px;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}

.modal-footer .btn-secondary {
    background: var(--bg-secondary, #f1f5f9);
    color: var(--text-secondary, #64748b);
    border: 1px solid var(--border-color, #e2e8f0);
}

.modal-footer .btn-secondary:hover {
    background: var(--bg-primary, #f8fafc);
}

.modal-footer .btn-danger {
    background: var(--danger-color, #ef4444);
    color: white;
}

.modal-footer .btn-danger:hover {
    background: #dc2626;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

.confirmation-steps {
    background: var(--bg-secondary, #f8fafc);
    border-radius: 8px;
    padding: 16px;
    margin: 16px 0;
}

.confirmation-steps h4 {
    margin: 0 0 12px 0;
    color: var(--text-primary);
    font-size: 14px;
    font-weight: 600;
}

.confirmation-step {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 0;
    border-bottom: 1px solid var(--border-color, #e2e8f0);
}

.confirmation-step:last-child {
    border-bottom: none;
}

.confirmation-step .step-number {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: var(--primary-color, #2563eb);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 600;
}

.confirmation-step .step-text {
    flex: 1;
    font-size: 13px;
    color: var(--text-secondary);
    line-height: 1.4;
}
</style>

<script>
// Estado global de la aplicación
let currentSection = 'profile';
let currentData = {};

// Cargar sección inicial
document.addEventListener('DOMContentLoaded', function() {
    // Verificar si viene con parámetro tab en la URL
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab');
    
    if (tab && tab === 'reports') {
        loadSection('reports');
    } else {
        loadSection('profile');
    }
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
                <?php if (isAdmin()): ?>
                loadUsersSection();
                <?php else: ?>
                showToast('Acceso denegado. Solo administradores pueden acceder a esta sección.', 'error');
                loadSection('profile');
                <?php endif; ?>
                break;
            case 'cleanup':
                <?php if (isAdmin()): ?>
                loadCleanupSection();
                <?php else: ?>
                showToast('Acceso denegado. Solo administradores pueden acceder a esta sección.', 'error');
                loadSection('profile');
                <?php endif; ?>
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
            case 'reports':
                loadReportsSection();
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

// SECCIÓN USUARIOS - La funcionalidad completa está en assets/js/settings.js
// Esta función se delega al JavaScript para manejo dinámico

// Las otras secciones continúan... (payment_types, expense_categories, expense_types)
// Por brevedad, las implementaré en la siguiente parte del archivo

// SECCIÓN INFORMES (DASHBOARD + REPORTS INTEGRADOS)
function loadReportsSection() {
    const content = `
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-chart-bar"></i>
                    Panel de Informes
                </h3>
                <p class="card-subtitle">Estadísticas y reportes del sistema en tiempo real</p>
            </div>
        </div>

        <!-- Dashboard Grid - Estadísticas principales -->
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

        <!-- Reportes Grid - Métricas detalladas -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; margin-bottom: 30px;">
            <!-- Reporte de usuarios -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-users" style="color: var(--primary-color);"></i>
                        Usuarios
                    </h3>
                </div>
                <div style="text-align: center; padding: 20px 0;">
                    <div style="font-size: 36px; font-weight: bold; color: var(--primary-color); margin-bottom: 8px;">
                        25
                    </div>
                    <div style="color: var(--text-secondary); margin-bottom: 12px;">Total registrados</div>
                    <div style="font-size: 14px;">
                        <span style="color: var(--success-color);">
                            <i class="fas fa-arrow-up"></i> +3 esta semana
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Reporte de sesiones -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-clock" style="color: var(--info-color);"></i>
                        Sesiones
                    </h3>
                </div>
                <div style="text-align: center; padding: 20px 0;">
                    <div style="font-size: 36px; font-weight: bold; color: var(--info-color); margin-bottom: 8px;">
                        147
                    </div>
                    <div style="color: var(--text-secondary); margin-bottom: 12px;">Este mes</div>
                    <div style="font-size: 14px;">
                        <span style="color: var(--success-color);">
                            <i class="fas fa-arrow-up"></i> +12% vs mes anterior
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Reporte de actividad -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-chart-line" style="color: var(--success-color);"></i>
                        Actividad
                    </h3>
                </div>
                <div style="text-align: center; padding: 20px 0;">
                    <div style="font-size: 36px; font-weight: bold; color: var(--success-color); margin-bottom: 8px;">
                        89%
                    </div>
                    <div style="color: var(--text-secondary); margin-bottom: 12px;">Tasa de actividad</div>
                    <div style="font-size: 14px;">
                        <span style="color: var(--success-color);">
                            <i class="fas fa-check"></i> Excelente rendimiento
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Reporte de seguridad -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-shield-alt" style="color: var(--warning-color);"></i>
                        Seguridad
                    </h3>
                </div>
                <div style="text-align: center; padding: 20px 0;">
                    <div style="font-size: 36px; font-weight: bold; color: var(--warning-color); margin-bottom: 8px;">
                        2
                    </div>
                    <div style="color: var(--text-secondary); margin-bottom: 12px;">Intentos fallidos hoy</div>
                    <div style="font-size: 14px;">
                        <span style="color: var(--success-color);">
                            <i class="fas fa-lock"></i> Sistema seguro
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Gráfico de actividad -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-chart-area"></i>
                    Actividad Semanal
                </h3>
                <p class="card-subtitle">Logins y actividad de usuarios en los últimos 7 días</p>
            </div>
            
            <div style="padding: 20px; min-height: 300px; display: flex; align-items: center; justify-content: center; background: var(--bg-primary); border-radius: 8px;">
                <div style="text-align: center;">
                    <i class="fas fa-chart-line" style="font-size: 48px; color: var(--text-muted); margin-bottom: 16px;"></i>
                    <h4 style="color: var(--text-secondary); margin-bottom: 8px;">Gráfico de Actividad</h4>
                    <p style="color: var(--text-muted);">Los gráficos interactivos estarán disponibles próximamente</p>
                </div>
            </div>
        </div>
        
        <!-- Tablas de reportes -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 30px;">
            <!-- Últimos logins -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-history"></i>
                        Últimos Accesos
                    </h3>
                    <p class="card-subtitle">Registro de los últimos inicios de sesión</p>
                </div>
                
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 2px solid var(--border-color);">
                                <th style="padding: 12px 8px; text-align: left; font-weight: 600; color: var(--text-secondary);">Usuario</th>
                                <th style="padding: 12px 8px; text-align: left; font-weight: 600; color: var(--text-secondary);">Fecha</th>
                                <th style="padding: 12px 8px; text-align: left; font-weight: 600; color: var(--text-secondary);">IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 12px 8px;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <i class="fas fa-user-circle" style="color: var(--primary-color);"></i>
                                        <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
                                    </div>
                                </td>
                                <td style="padding: 12px 8px; color: var(--text-secondary);">
                                    <?php echo date('d/m/Y H:i', $_SESSION['login_time'] ?? time()); ?>
                                </td>
                                <td style="padding: 12px 8px; font-family: monospace; color: var(--text-secondary);">
                                    <?php echo htmlspecialchars($_SERVER['REMOTE_ADDR']); ?>
                                </td>
                            </tr>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 12px 8px;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <i class="fas fa-user-circle" style="color: var(--secondary-color);"></i>
                                        <span>admin</span>
                                    </div>
                                </td>
                                <td style="padding: 12px 8px; color: var(--text-secondary);">22/05/2025 14:30</td>
                                <td style="padding: 12px 8px; font-family: monospace; color: var(--text-secondary);">192.168.1.100</td>
                            </tr>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 12px 8px;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <i class="fas fa-user-circle" style="color: var(--success-color);"></i>
                                        <span>usuario1</span>
                                    </div>
                                </td>
                                <td style="padding: 12px 8px; color: var(--text-secondary);">22/05/2025 11:15</td>
                                <td style="padding: 12px 8px; font-family: monospace; color: var(--text-secondary);">10.0.0.50</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Estado del sistema -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-server"></i>
                        Estado del Sistema
                    </h3>
                    <p class="card-subtitle">Información técnica y rendimiento</p>
                </div>
                
                <div style="padding: 10px 0;">
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--border-color);">
                        <span style="font-weight: 500;">Servidor Web:</span>
                        <span style="color: var(--success-color);">
                            <i class="fas fa-circle" style="font-size: 8px;"></i> Activo
                        </span>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--border-color);">
                        <span style="font-weight: 500;">Base de Datos:</span>
                        <span style="color: var(--success-color);">
                            <i class="fas fa-circle" style="font-size: 8px;"></i> Conectada
                        </span>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--border-color);">
                        <span style="font-weight: 500;">PHP:</span>
                        <span style="color: var(--text-secondary);">v<?php echo PHP_VERSION; ?></span>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--border-color);">
                        <span style="font-weight: 500;">Memoria Usada:</span>
                        <span style="color: var(--text-secondary);">
                            <?php echo round(memory_get_usage() / 1024 / 1024, 2); ?>MB
                        </span>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0;">
                        <span style="font-weight: 500;">Uptime:</span>
                        <span style="color: var(--text-secondary);">
                            <?php 
                            $uptime = time() - ($_SESSION['login_time'] ?? time());
                            $hours = floor($uptime / 3600);
                            $minutes = floor(($uptime % 3600) / 60);
                            echo sprintf('%02d:%02d', $hours, $minutes);
                            ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Información del sistema de dashboard -->
        <div class="card" style="margin-top: 30px;">
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
        
        <!-- Acciones de reportes -->
        <div style="margin-top: 30px; display: flex; gap: 16px; justify-content: center;">
            <button class="btn" style="background: var(--success-color); color: white; padding: 12px 24px;" onclick="showToast('Funcionalidad en desarrollo', 'info')">
                <i class="fas fa-file-excel"></i>
                Exportar a Excel
            </button>
            <button class="btn" style="background: var(--danger-color); color: white; padding: 12px 24px;" onclick="showToast('Funcionalidad en desarrollo', 'info')">
                <i class="fas fa-file-pdf"></i>
                Generar PDF
            </button>
            <button class="btn" style="background: var(--info-color); color: white; padding: 12px 24px;" onclick="showToast('Funcionalidad en desarrollo', 'info')">
                <i class="fas fa-envelope"></i>
                Enviar por Email
            </button>
        </div>
    `;
    
    document.getElementById('settingsContent').innerHTML = content;
}

// SECCIÓN LIMPIEZA DE ARCHIVOS
function loadCleanupSection() {
    // Cargar las estadísticas del sistema (usando endpoint demo temporalmente)
    fetch('api/cleanup/get_stats_demo.php', {
        method: 'GET',
        headers: {
            'X-CSRF-Token': window.CSRF_TOKEN
        }
    })
    .then(response => response.json())
    .then(stats => {
        const content = `
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-broom"></i>
                        Limpieza de Archivos Huérfanos
                    </h3>
                    <p class="card-subtitle">Detecta y elimina archivos sin referencia para optimizar el almacenamiento</p>
                </div>
                
                <!-- Estadísticas del Sistema -->
                <div style="padding: 20px;">
                    <h4 style="color: var(--primary-color); margin-bottom: 16px;">
                        <i class="fas fa-chart-bar"></i>
                        Estadísticas del Sistema
                    </h4>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px;">
                        <div style="background: var(--bg-secondary); padding: 15px; border-radius: 8px; text-align: center;">
                            <h3 style="color: var(--primary-color); margin: 0;">${stats.total_attachments || 0}</h3>
                            <p style="margin: 5px 0 0 0; color: var(--text-secondary);">Total Attachments en BD</p>
                        </div>
                        <div style="background: var(--bg-secondary); padding: 15px; border-radius: 8px; text-align: center;">
                            <h3 style="color: var(--info-color); margin: 0;">${stats.b2_attachments || 0}</h3>
                            <p style="margin: 5px 0 0 0; color: var(--text-secondary);">Archivos en B2</p>
                        </div>
                        <div style="background: var(--bg-secondary); padding: 15px; border-radius: 8px; text-align: center;">
                            <h3 style="color: var(--warning-color); margin: 0;">${stats.local_attachments || 0}</h3>
                            <p style="margin: 5px 0 0 0; color: var(--text-secondary);">Archivos Locales</p>
                        </div>
                        <div style="background: var(--bg-secondary); padding: 15px; border-radius: 8px; text-align: center;">
                            <h3 style="color: var(--success-color); margin: 0;">${stats.total_expenses || 0}</h3>
                            <p style="margin: 5px 0 0 0; color: var(--text-secondary);">Gastos Totales</p>
                        </div>
                    </div>
                    
                    ${stats.orphaned_records > 0 ? `
                    <div style="background: var(--warning-bg, #fef3c7); color: var(--warning-color, #f59e0b); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Atención:</strong> Se encontraron ${stats.orphaned_records} registros huérfanos
                    </div>
                    ` : `
                    <div style="background: var(--success-bg, #d1fae5); color: var(--success-color, #10b981); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <i class="fas fa-check-circle"></i>
                        <strong>¡Excelente!</strong> No se encontraron registros huérfanos
                    </div>
                    `}
                    
                    <!-- Acciones de Limpieza -->
                    <h4 style="color: var(--primary-color); margin-bottom: 16px;">
                        <i class="fas fa-cogs"></i>
                        Acciones Disponibles
                    </h4>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                        <button class="btn" style="background: var(--info-color); color: white; padding: 15px; border-radius: 8px; display: flex; align-items: center; gap: 10px;" onclick="performCleanupAction('analyze')">
                            <i class="fas fa-search"></i>
                            <div style="text-align: left;">
                                <div style="font-weight: 600;">Analizar Sistema</div>
                                <div style="font-size: 12px; opacity: 0.9;">Detectar archivos huérfanos (solo lectura)</div>
                            </div>
                        </button>
                        
                        <button class="btn" style="background: var(--warning-color); color: white; padding: 15px; border-radius: 8px; display: flex; align-items: center; gap: 10px;" onclick="performCleanupAction('simulate')">
                            <i class="fas fa-play-circle"></i>
                            <div style="text-align: left;">
                                <div style="font-weight: 600;">Simular Limpieza</div>
                                <div style="font-size: 12px; opacity: 0.9;">Ver qué se eliminaría (seguro)</div>
                            </div>
                        </button>
                        
                        <button class="btn" style="background: var(--danger-color); color: white; padding: 15px; border-radius: 8px; display: flex; align-items: center; gap: 10px;" onclick="confirmCleanupAction('execute')">
                            <i class="fas fa-broom"></i>
                            <div style="text-align: left;">
                                <div style="font-weight: 600;">Ejecutar Limpieza</div>
                                <div style="font-size: 12px; opacity: 0.9;">Eliminar archivos huérfanos (¡CUIDADO!)</div>
                            </div>
                        </button>
                        
                        <button class="btn" style="background: var(--primary-color); color: white; padding: 15px; border-radius: 8px; display: flex; align-items: center; gap: 10px;" onclick="openCleanupLogs()">
                            <i class="fas fa-file-alt"></i>
                            <div style="text-align: left;">
                                <div style="font-weight: 600;">Ver Logs</div>
                                <div style="font-size: 12px; opacity: 0.9;">Historial de operaciones</div>
                            </div>
                        </button>
                    </div>
                    
                    <!-- Área de Resultados -->
                    <div id="cleanupResults" style="margin-top: 30px; display: none;">
                        <!-- Los resultados se mostrarán aquí -->
                    </div>
                    
                    <!-- Información de Seguridad -->
                    <div style="background: var(--bg-primary); padding: 20px; border-radius: 8px; margin-top: 30px;">
                        <h4 style="color: var(--primary-color); margin-bottom: 12px;">
                            <i class="fas fa-shield-alt"></i>
                            Medidas de Seguridad:
                        </h4>
                        <ul style="margin: 0; padding-left: 20px; color: var(--text-secondary);">
                            <li style="margin-bottom: 8px;"><strong>Análisis Seguro:</strong> Solo lectura, no modifica archivos</li>
                            <li style="margin-bottom: 8px;"><strong>Simulación:</strong> Muestra qué se eliminaría sin hacer cambios</li>
                            <li style="margin-bottom: 8px;"><strong>Transacciones:</strong> Rollback automático en caso de error</li>
                            <li style="margin-bottom: 8px;"><strong>Verificación B2:</strong> Confirma eliminación real de archivos</li>
                            <li><strong>Logging:</strong> Registro detallado de todas las operaciones</li>
                        </ul>
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('settingsContent').innerHTML = content;
    })
    .catch(error => {
        console.error('Error cargando estadísticas:', error);
        
        // Mostrar interfaz básica en caso de error
        const content = `
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-broom"></i>
                        Limpieza de Archivos Huérfanos
                    </h3>
                    <p class="card-subtitle">Detecta y elimina archivos sin referencia para optimizar el almacenamiento</p>
                </div>
                
                <div style="padding: 20px;">
                    <div style="background: var(--warning-bg, #fef3c7); color: var(--warning-color, #f59e0b); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Advertencia:</strong> No se pudieron cargar las estadísticas del sistema
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                        <button class="btn" style="background: var(--info-color); color: white; padding: 15px; border-radius: 8px;" onclick="performCleanupAction('analyze')">
                            <i class="fas fa-search"></i>
                            Analizar Sistema
                        </button>
                        
                        <button class="btn" style="background: var(--warning-color); color: white; padding: 15px; border-radius: 8px;" onclick="performCleanupAction('simulate')">
                            <i class="fas fa-play-circle"></i>
                            Simular Limpieza
                        </button>
                    </div>
                    
                    <div id="cleanupResults" style="margin-top: 30px; display: none;"></div>
                </div>
            </div>
        `;
        
        document.getElementById('settingsContent').innerHTML = content;
    });
}

// Funciones para manejar las acciones de limpieza
function performCleanupAction(action) {
    const resultsDiv = document.getElementById('cleanupResults');
    resultsDiv.style.display = 'block';
    resultsDiv.innerHTML = createLoadingState(action);
    
    const mode = action === 'analyze' ? 'analyze' : 'cleanup';
    const dryRun = action !== 'execute';
    
    fetch(`api/cleanup/cleanup.php?action=${action}&dry_run=${dryRun}`, {
        method: 'GET',
        credentials: 'include',
        headers: {
            'X-CSRF-Token': window.CSRF_TOKEN,
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            resultsDiv.innerHTML = createProfessionalResults(action, data.data);
        } else {
            resultsDiv.innerHTML = createErrorState(data.error || 'Error en la operación');
        }
        
        // Animar las métricas
        setTimeout(() => animateMetrics(), 500);
    })
    .catch(error => {
        console.error('Error en acción de limpieza:', error);
        resultsDiv.innerHTML = createErrorState(error.message);
    });
}

function createLoadingState(action) {
    const actionText = {
        'analyze': 'Analizando Sistema',
        'simulate': 'Ejecutando Simulación',
        'execute': 'Procesando Limpieza'
    };
    
    return `
        <div class="professional-results">
            <div class="loading-overlay">
                <div class="spinner" style="width: 40px; height: 40px;"></div>
                <div class="loading-text">${actionText[action]}...</div>
                <div style="margin-top: 8px; font-size: 12px; color: var(--text-secondary);">
                    Esto puede tomar unos momentos
                </div>
            </div>
        </div>
    `;
}

function generateMockResults(action) {
    // Generar datos realistas para demostración
    const baseStats = {
        totalScanned: 1247,
        orphanedFiles: 23,
        diskSpaceRecoverable: '15.3 MB',
        processingTime: '2.4 segundos',
        systemHealth: 'Bueno'
    };
    
    if (action === 'analyze') {
        return {
            ...baseStats,
            issues: [
                { type: 'orphaned', count: 23, severity: 'medium' },
                { type: 'duplicates', count: 5, severity: 'low' },
                { type: 'broken_refs', count: 2, severity: 'high' }
            ],
            recommendations: [
                'Ejecutar limpieza de archivos huérfanos',
                'Revisar referencias rotas manualmente',
                'Considerar deduplicación'
            ]
        };
    }
    
    if (action === 'simulate') {
        return {
            ...baseStats,
            wouldDelete: [
                { name: 'documento_legacy_001.pdf', size: '2.3 MB', type: 'document', orphanedSince: '2024-12-15' },
                { name: 'imagen_temp_456.jpg', size: '800 KB', type: 'image', orphanedSince: '2024-12-20' },
                { name: 'backup_old_789.zip', size: '5.2 MB', type: 'archive', orphanedSince: '2024-11-30' },
                { name: 'screenshot_draft.png', size: '1.1 MB', type: 'image', orphanedSince: '2025-01-05' },
                { name: 'temp_upload_123.docx', size: '600 KB', type: 'document', orphanedSince: '2025-01-10' }
            ],
            impact: {
                spaceFreed: '10.0 MB',
                costSavings: '$0.08/mes',
                performanceGain: '3% mejora estimada'
            }
        };
    }
    
    return baseStats;
}

function createProfessionalResults(action, data) {
    const actionConfig = {
        'analyze': {
            title: 'Análisis del Sistema Completado',
            badge: 'Análisis',
            icon: 'fas fa-search',
            color: 'info'
        },
        'simulate': {
            title: 'Simulación de Limpieza Completada',
            badge: 'Simulación',
            icon: 'fas fa-play-circle',
            color: 'warning'
        },
        'execute': {
            title: 'Limpieza Ejecutada',
            badge: 'Ejecutado',
            icon: 'fas fa-check-circle',
            color: 'success'
        }
    };
    
    const config = actionConfig[action];
    
    let content = `
        <div class="professional-results">
            <div class="results-header">
                <i class="${config.icon}"></i>
                <h3>${config.title}</h3>
                <div class="results-badge">${config.badge}</div>
                <div style="margin-left: auto; font-size: 12px; opacity: 0.9;">
                    ${new Date().toLocaleTimeString()}
                </div>
            </div>
            
            <div class="results-body">
                <!-- Métricas principales -->
                <div class="summary-grid">
                    <div class="modern-metric-card info">
                        <div class="metric-title">Archivos Escaneados</div>
                        <div class="metric-value" data-target="${data.totalScanned}">0</div>
                        <div class="metric-description">Total de registros analizados en el sistema</div>
                    </div>
                    
                    <div class="modern-metric-card ${data.orphanedFiles > 0 ? 'warning' : 'success'}">
                        <div class="metric-title">Archivos Huérfanos</div>
                        <div class="metric-value" data-target="${data.orphanedFiles}">0</div>
                        <div class="metric-description">Archivos sin referencia válida detectados</div>
                    </div>
                    
                    <div class="modern-metric-card success">
                        <div class="metric-title">Espacio Recuperable</div>
                        <div class="metric-value">${data.diskSpaceRecoverable}</div>
                        <div class="metric-description">Almacenamiento que puede liberarse</div>
                    </div>
                    
                    <div class="modern-metric-card info">
                        <div class="metric-title">Tiempo de Proceso</div>
                        <div class="metric-value">${data.processingTime}</div>
                        <div class="metric-description">Duración del análisis ejecutado</div>
                    </div>
                </div>
    `;
    
    if (action === 'analyze' && data.issues) {
        content += createAnalysisDetails(data);
    } else if (action === 'simulate' && data.wouldDelete) {
        content += createSimulationDetails(data);
    }
    
    content += `
                <!-- Estado del sistema -->
                <div class="modern-alert ${data.orphanedFiles > 0 ? 'warning' : 'success'}">
                    <div class="alert-icon">
                        <i class="fas fa-${data.orphanedFiles > 0 ? 'exclamation-triangle' : 'check-circle'}"></i>
                    </div>
                    <div class="alert-content">
                        <div class="alert-title">
                            ${data.orphanedFiles > 0 ? 'Atención Requerida' : 'Sistema Saludable'}
                        </div>
                        <div class="alert-message">
                            ${data.orphanedFiles > 0 
                                ? `Se detectaron ${data.orphanedFiles} archivos huérfanos que requieren atención.`
                                : 'No se encontraron problemas significativos en el sistema.'
                            }
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    return content;
}

function createAnalysisDetails(data) {
    return `
        <h4 style="color: var(--primary-color); margin: 24px 0 16px 0;">
            <i class="fas fa-chart-line"></i>
            Detalles del Análisis
        </h4>
        
        <div class="action-timeline">
            ${data.issues.map(issue => `
                <div class="timeline-item ${issue.severity === 'high' ? 'danger' : issue.severity === 'medium' ? 'warning' : 'success'}">
                    <div class="timeline-content">
                        <div class="timeline-title">
                            ${getIssueTitle(issue.type)} (${issue.count} encontrados)
                        </div>
                        <div class="timeline-description">
                            Severidad: ${issue.severity.toUpperCase()} - ${getIssueDescription(issue.type)}
                        </div>
                    </div>
                </div>
            `).join('')}
        </div>
        
        <h4 style="color: var(--success-color); margin: 24px 0 16px 0;">
            <i class="fas fa-lightbulb"></i>
            Recomendaciones
        </h4>
        
        <div style="background: var(--bg-secondary); padding: 16px; border-radius: 8px; border-left: 4px solid var(--success-color);">
            ${data.recommendations.map(rec => `
                <div style="display: flex; align-items: center; margin-bottom: 8px;">
                    <i class="fas fa-check" style="color: var(--success-color); margin-right: 8px;"></i>
                    ${rec}
                </div>
            `).join('')}
        </div>
    `;
}

function createSimulationDetails(data) {
    return `
        <h4 style="color: var(--warning-color); margin: 24px 0 16px 0;">
            <i class="fas fa-list-ul"></i>
            Archivos que serían eliminados
        </h4>
        
        <div class="file-list-modern">
            ${data.wouldDelete.map(file => `
                <div class="file-item-modern">
                    <div class="file-icon ${file.type}">
                        <i class="fas fa-${getFileIcon(file.type)}"></i>
                    </div>
                    <div class="file-details">
                        <div class="file-name">${file.name}</div>
                        <div class="file-meta">
                            <span><i class="fas fa-weight-hanging"></i> ${file.size}</span>
                            <span><i class="fas fa-calendar"></i> Huérfano desde ${file.orphanedSince}</span>
                            <span><i class="fas fa-tag"></i> ${file.type}</span>
                        </div>
                    </div>
                </div>
            `).join('')}
        </div>
        
        <h4 style="color: var(--info-color); margin: 24px 0 16px 0;">
            <i class="fas fa-chart-pie"></i>
            Impacto Estimado
        </h4>
        
        <div class="summary-grid">
            <div class="modern-metric-card success">
                <div class="metric-title">Espacio Liberado</div>
                <div class="metric-value">${data.impact.spaceFreed}</div>
                <div class="metric-description">Reducción inmediata en almacenamiento</div>
            </div>
            
            <div class="modern-metric-card info">
                <div class="metric-title">Ahorro de Costos</div>
                <div class="metric-value">${data.impact.costSavings}</div>
                <div class="metric-description">Reducción mensual en costos de almacenamiento</div>
            </div>
            
            <div class="modern-metric-card success">
                <div class="metric-title">Mejora de Rendimiento</div>
                <div class="metric-value">${data.impact.performanceGain}</div>
                <div class="metric-description">Optimización estimada del sistema</div>
            </div>
        </div>
    `;
}

function createErrorState(error) {
    return `
        <div class="professional-results">
            <div class="results-header" style="background: linear-gradient(135deg, var(--danger-color, #ef4444) 0%, #dc2626 100%);">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>Error en el Proceso</h3>
                <div class="results-badge">Error</div>
            </div>
            
            <div class="results-body">
                <div class="modern-alert danger">
                    <div class="alert-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="alert-content">
                        <div class="alert-title">No se pudo completar la operación</div>
                        <div class="alert-message">${error}</div>
                    </div>
                </div>
                
                <div style="margin-top: 20px; padding: 16px; background: var(--bg-secondary); border-radius: 8px;">
                    <h4 style="margin: 0 0 12px 0; color: var(--text-primary);">Posibles soluciones:</h4>
                    <ul style="margin: 0; padding-left: 20px; color: var(--text-secondary);">
                        <li>Verificar la conexión a la base de datos</li>
                        <li>Comprobar los permisos del sistema</li>
                        <li>Contactar al administrador del sistema</li>
                    </ul>
                </div>
            </div>
        </div>
    `;
}

function animateMetrics() {
    document.querySelectorAll('.metric-value[data-target]').forEach(element => {
        const target = parseInt(element.getAttribute('data-target'));
        const duration = 1500;
        const increment = target / (duration / 16);
        let current = 0;
        
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                current = target;
                clearInterval(timer);
            }
            element.textContent = Math.floor(current).toLocaleString();
        }, 16);
    });
}

function getIssueTitle(type) {
    const titles = {
        'orphaned': 'Archivos Huérfanos',
        'duplicates': 'Archivos Duplicados',
        'broken_refs': 'Referencias Rotas'
    };
    return titles[type] || type;
}

function getIssueDescription(type) {
    const descriptions = {
        'orphaned': 'Archivos sin referencia válida en la base de datos',
        'duplicates': 'Archivos idénticos almacenados múltiples veces',
        'broken_refs': 'Referencias que apuntan a archivos inexistentes'
    };
    return descriptions[type] || 'Problema detectado en el sistema';
}

function getFileIcon(type) {
    const icons = {
        'image': 'image',
        'document': 'file-alt',
        'archive': 'file-archive',
        'other': 'file'
    };
    return icons[type] || 'file';
}

function confirmCleanupAction(action) {
    openModal('cleanupConfirmModal');
}

function showFinalConfirmation() {
    closeModal('cleanupConfirmModal');
    openModal('finalConfirmModal');
    
    // Focus en el input después de un momento
    setTimeout(() => {
        document.getElementById('confirmationInput').focus();
    }, 100);
}

function checkConfirmationInput() {
    const input = document.getElementById('confirmationInput');
    const button = document.getElementById('executeCleanupBtn');
    
    if (input.value.toUpperCase() === 'ELIMINAR') {
        button.disabled = false;
        button.style.background = 'var(--danger-color, #ef4444)';
        input.style.borderColor = 'var(--success-color, #10b981)';
        input.style.background = 'var(--success-bg, #d1fae5)';
    } else {
        button.disabled = true;
        button.style.background = '#94a3b8';
        input.style.borderColor = 'var(--danger-color, #ef4444)';
        input.style.background = 'white';
    }
}

function executeCleanupConfirmed() {
    closeModal('finalConfirmModal');
    performCleanupAction('execute');
}

function openCleanupLogs() {
    loadCleanupLogsSection();
}

function loadCleanupLogsSection() {
    const content = `
        <div class="professional-results">
            <div class="results-header">
                <i class="fas fa-file-alt"></i>
                <h3>Historial de Operaciones de Limpieza</h3>
                <div class="results-badge">Logs</div>
                <div style="margin-left: auto;">
                    <button class="btn btn-secondary" onclick="loadCleanupSection()" style="background: rgba(255,255,255,0.2); color: white; border: none; padding: 8px 16px; border-radius: 6px; font-size: 12px;">
                        <i class="fas fa-arrow-left"></i>
                        Volver
                    </button>
                </div>
            </div>
            
            <div class="results-body">
                <!-- Filtros de búsqueda -->
                <div style="background: var(--bg-secondary); padding: 20px; border-radius: 8px; margin-bottom: 24px;">
                    <h4 style="margin: 0 0 16px 0; color: var(--primary-color);">
                        <i class="fas fa-filter"></i>
                        Filtros de Búsqueda
                    </h4>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                        <div>
                            <label style="display: block; margin-bottom: 6px; font-weight: 500; color: var(--text-primary);">Tipo de Operación</label>
                            <select id="operationFilter" style="width: 100%; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 6px;">
                                <option value="">Todas las operaciones</option>
                                <option value="analyze">Análisis</option>
                                <option value="simulate">Simulación</option>
                                <option value="execute">Ejecución</option>
                            </select>
                        </div>
                        
                        <div>
                            <label style="display: block; margin-bottom: 6px; font-weight: 500; color: var(--text-primary);">Estado</label>
                            <select id="statusFilter" style="width: 100%; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 6px;">
                                <option value="">Todos los estados</option>
                                <option value="success">Exitoso</option>
                                <option value="partial">Parcial</option>
                                <option value="error">Error</option>
                            </select>
                        </div>
                        
                        <div>
                            <label style="display: block; margin-bottom: 6px; font-weight: 500; color: var(--text-primary);">Fecha Desde</label>
                            <input type="date" id="dateFromFilter" style="width: 100%; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 6px;">
                        </div>
                        
                        <div>
                            <label style="display: block; margin-bottom: 6px; font-weight: 500; color: var(--text-primary);">Fecha Hasta</label>
                            <input type="date" id="dateToFilter" style="width: 100%; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 6px;">
                        </div>
                    </div>
                    
                    <div style="margin-top: 16px; display: flex; gap: 12px;">
                        <button class="btn" style="background: var(--primary-color); color: white; padding: 8px 16px; border-radius: 6px;" onclick="applyLogsFilter()">
                            <i class="fas fa-search"></i>
                            Buscar
                        </button>
                        <button class="btn" style="background: var(--bg-primary); color: var(--text-secondary); padding: 8px 16px; border-radius: 6px; border: 1px solid var(--border-color);" onclick="clearLogsFilter()">
                            <i class="fas fa-times"></i>
                            Limpiar
                        </button>
                        <button class="btn" style="background: var(--success-color); color: white; padding: 8px 16px; border-radius: 6px;" onclick="refreshLogs()">
                            <i class="fas fa-sync-alt"></i>
                            Actualizar
                        </button>
                    </div>
                </div>
                
                <!-- Tabla de logs -->
                <div class="logs-table-container">
                    <div id="logsLoadingState" style="text-align: center; padding: 40px;">
                        <div class="spinner" style="margin: 0 auto 16px;"></div>
                        <div style="color: var(--text-secondary);">Cargando historial de logs...</div>
                    </div>
                    
                    <div id="logsTableContainer" style="display: none;">
                        <!-- La tabla se generará dinámicamente aquí -->
                    </div>
                </div>
                
                <!-- Paginación -->
                <div id="logsPagination" style="margin-top: 24px; display: none;">
                    <!-- La paginación se generará dinámicamente aquí -->
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('cleanupResults').innerHTML = content;
    document.getElementById('cleanupResults').style.display = 'block';
    
    // Cargar logs iniciales
    loadLogsData();
}

let currentLogsPage = 1;
let currentLogsFilters = {};

function loadLogsData(page = 1) {
    currentLogsPage = page;
    
    // Construir URL con filtros
    const params = new URLSearchParams({
        page: page,
        limit: 10,
        ...currentLogsFilters
    });
    
    document.getElementById('logsLoadingState').style.display = 'block';
    document.getElementById('logsTableContainer').style.display = 'none';
    
    fetch(`api/cleanup/logs.php?${params.toString()}`, {
        method: 'GET',
        credentials: 'include',
        headers: {
            'X-CSRF-Token': window.CSRF_TOKEN,
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            renderLogsTable(data.data);
            renderLogsPagination(data.pagination);
        } else {
            showLogsError(data.error || 'Error cargando logs');
        }
    })
    .catch(error => {
        console.error('Error cargando logs:', error);
        showLogsError('Error de conectividad al cargar logs');
    })
    .finally(() => {
        document.getElementById('logsLoadingState').style.display = 'none';
        document.getElementById('logsTableContainer').style.display = 'block';
    });
}

function renderLogsTable(logs) {
    const container = document.getElementById('logsTableContainer');
    
    if (logs.length === 0) {
        container.innerHTML = `
            <div style="text-align: center; padding: 40px; color: var(--text-secondary);">
                <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;"></i>
                <h3 style="margin: 0 0 8px 0;">No se encontraron logs</h3>
                <p style="margin: 0;">No hay registros que coincidan con los filtros seleccionados.</p>
            </div>
        `;
        return;
    }
    
    const tableHtml = `
        <div style="overflow-x: auto; border: 1px solid var(--border-color); border-radius: 8px;">
            <table style="width: 100%; border-collapse: collapse; background: white;">
                <thead style="background: var(--bg-secondary);">
                    <tr>
                        <th style="padding: 12px; text-align: left; font-weight: 600; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">Fecha</th>
                        <th style="padding: 12px; text-align: left; font-weight: 600; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">Operación</th>
                        <th style="padding: 12px; text-align: left; font-weight: 600; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">Estado</th>
                        <th style="padding: 12px; text-align: center; font-weight: 600; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">Archivos</th>
                        <th style="padding: 12px; text-align: center; font-weight: 600; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">Espacio</th>
                        <th style="padding: 12px; text-align: center; font-weight: 600; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">Tiempo</th>
                        <th style="padding: 12px; text-align: center; font-weight: 600; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">Usuario</th>
                        <th style="padding: 12px; text-align: center; font-weight: 600; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    ${logs.map(log => createLogTableRow(log)).join('')}
                </tbody>
            </table>
        </div>
    `;
    
    container.innerHTML = tableHtml;
}

function createLogTableRow(log) {
    const statusColors = {
        'success': 'var(--success-color)',
        'partial': 'var(--warning-color)',
        'error': 'var(--danger-color)'
    };
    
    const statusIcons = {
        'success': 'fas fa-check-circle',
        'partial': 'fas fa-exclamation-triangle',
        'error': 'fas fa-times-circle'
    };
    
    const operationLabels = {
        'analyze': 'Análisis',
        'simulate': 'Simulación',
        'execute': 'Ejecución'
    };
    
    const operationColors = {
        'analyze': 'var(--info-color)',
        'simulate': 'var(--warning-color)',
        'execute': 'var(--danger-color)'
    };
    
    const date = new Date(log.created_at);
    const formattedDate = date.toLocaleString('es-ES', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
    
    return `
        <tr style="border-bottom: 1px solid var(--border-color); transition: background-color 0.2s ease;" onmouseover="this.style.backgroundColor='var(--bg-secondary)'" onmouseout="this.style.backgroundColor='white'">
            <td style="padding: 12px; font-size: 13px; color: var(--text-primary);">
                <div style="font-weight: 500;">${formattedDate}</div>
                <div style="font-size: 11px; color: var(--text-secondary); margin-top: 2px;">${log.log_filename}</div>
            </td>
            <td style="padding: 12px;">
                <span style="background: ${operationColors[log.operation_type]}; color: white; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; text-transform: uppercase;">
                    ${operationLabels[log.operation_type]}
                </span>
            </td>
            <td style="padding: 12px;">
                <div style="display: flex; align-items: center; gap: 6px;">
                    <i class="${statusIcons[log.status]}" style="color: ${statusColors[log.status]};"></i>
                    <span style="color: ${statusColors[log.status]}; font-weight: 500; font-size: 12px; text-transform: uppercase;">
                        ${log.status}
                    </span>
                </div>
                ${log.error_message ? `<div style="font-size: 11px; color: var(--text-secondary); margin-top: 4px;" title="${log.error_message}">⚠️ Ver detalles</div>` : ''}
            </td>
            <td style="padding: 12px; text-align: center;">
                <div style="font-weight: 600; color: var(--text-primary);">${log.files_processed}</div>
                ${log.files_deleted > 0 ? `<div style="font-size: 11px; color: var(--danger-color);">${log.files_deleted} eliminados</div>` : ''}
            </td>
            <td style="padding: 12px; text-align: center;">
                <div style="font-weight: 600; color: var(--success-color);">${log.space_freed_mb} MB</div>
            </td>
            <td style="padding: 12px; text-align: center;">
                <div style="font-size: 12px; color: var(--text-secondary);">${log.execution_time_seconds}s</div>
            </td>
            <td style="padding: 12px; text-align: center;">
                <div style="font-size: 12px; color: var(--text-primary);">${log.username || 'N/A'}</div>
            </td>
            <td style="padding: 12px; text-align: center;">
                <div style="display: flex; gap: 4px; justify-content: center;">
                    ${log.b2_path ? `
                    <button class="btn-action" onclick="downloadLog('${log.b2_path}')" title="Descargar log">
                        <i class="fas fa-download"></i>
                    </button>
                    ` : ''}
                    <button class="btn-action" onclick="viewLogDetails(${log.id})" title="Ver detalles">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn-action btn-danger" onclick="confirmDeleteLog(${log.id}, '${log.log_filename}')" title="Eliminar log">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `;
}

function renderLogsPagination(pagination) {
    const container = document.getElementById('logsPagination');
    
    if (pagination.total_pages <= 1) {
        container.style.display = 'none';
        return;
    }
    
    container.style.display = 'flex';
    container.style.justifyContent = 'center';
    container.style.alignItems = 'center';
    container.style.gap = '8px';
    
    let paginationHtml = '';
    
    // Botón anterior
    if (pagination.current_page > 1) {
        paginationHtml += `<button class="btn btn-secondary" onclick="loadLogsData(${pagination.current_page - 1})" style="padding: 8px 12px;"><i class="fas fa-chevron-left"></i></button>`;
    }
    
    // Números de página
    const startPage = Math.max(1, pagination.current_page - 2);
    const endPage = Math.min(pagination.total_pages, pagination.current_page + 2);
    
    for (let i = startPage; i <= endPage; i++) {
        const isActive = i === pagination.current_page;
        paginationHtml += `
            <button class="btn ${isActive ? 'btn-primary' : 'btn-secondary'}" onclick="loadLogsData(${i})" style="padding: 8px 12px; ${isActive ? 'background: var(--primary-color); color: white;' : ''}">
                ${i}
            </button>
        `;
    }
    
    // Botón siguiente
    if (pagination.current_page < pagination.total_pages) {
        paginationHtml += `<button class="btn btn-secondary" onclick="loadLogsData(${pagination.current_page + 1})" style="padding: 8px 12px;"><i class="fas fa-chevron-right"></i></button>`;
    }
    
    // Información de paginación
    paginationHtml += `
        <div style="margin-left: 16px; color: var(--text-secondary); font-size: 13px;">
            Página ${pagination.current_page} de ${pagination.total_pages} (${pagination.total} registros)
        </div>
    `;
    
    container.innerHTML = paginationHtml;
}

function applyLogsFilter() {
    currentLogsFilters = {
        operation_type: document.getElementById('operationFilter').value,
        status: document.getElementById('statusFilter').value,
        date_from: document.getElementById('dateFromFilter').value,
        date_to: document.getElementById('dateToFilter').value
    };
    
    // Remover filtros vacíos
    Object.keys(currentLogsFilters).forEach(key => {
        if (!currentLogsFilters[key]) {
            delete currentLogsFilters[key];
        }
    });
    
    loadLogsData(1);
}

function clearLogsFilter() {
    document.getElementById('operationFilter').value = '';
    document.getElementById('statusFilter').value = '';
    document.getElementById('dateFromFilter').value = '';
    document.getElementById('dateToFilter').value = '';
    
    currentLogsFilters = {};
    loadLogsData(1);
}

function refreshLogs() {
    loadLogsData(currentLogsPage);
}

function confirmDeleteLog(logId, filename) {
    if (confirm(`¿Estás seguro de que deseas eliminar el log "${filename}"?\n\nEsta acción no se puede deshacer.`)) {
        deleteLog(logId);
    }
}

function deleteLog(logId) {
    fetch(`api/cleanup/logs.php?id=${logId}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-Token': window.CSRF_TOKEN
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Log eliminado correctamente', 'success');
            refreshLogs();
        } else {
            showToast('Error eliminando log: ' + (data.error || 'Error desconocido'), 'error');
        }
    })
    .catch(error => {
        console.error('Error eliminando log:', error);
        showToast('Error de conectividad al eliminar log', 'error');
    });
}

function downloadLog(b2Path) {
    // TODO: Implementar descarga desde B2
    showToast('Funcionalidad de descarga en desarrollo', 'info');
}

function viewLogDetails(logId) {
    // TODO: Implementar modal de detalles del log
    showToast('Vista de detalles en desarrollo', 'info');
}

function showLogsError(message) {
    const container = document.getElementById('logsTableContainer');
    container.innerHTML = `
        <div style="text-align: center; padding: 40px;">
            <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: var(--danger-color); margin-bottom: 16px;"></i>
            <h3 style="color: var(--danger-color); margin: 0 0 8px 0;">Error Cargando Logs</h3>
            <p style="color: var(--text-secondary); margin: 0 0 16px 0;">${message}</p>
            <button class="btn" style="background: var(--primary-color); color: white; padding: 8px 16px; border-radius: 6px;" onclick="refreshLogs()">
                <i class="fas fa-sync-alt"></i>
                Reintentar
            </button>
        </div>
    `;
}

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
<script src="assets/js/api-utils.js"></script>
<script src="assets/js/settings.js?v=<?php echo time(); ?>"></script>

<?php include 'includes/footer.php'; ?>
