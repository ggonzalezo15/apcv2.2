<?php
require_once 'config.php';

// Verificar autenticación
if (!isLoggedIn() || !checkSessionTimeout()) {
    header('Location: auth/login.php');
    exit;
}

$pageTitle = 'Gestión de Equipos';
?>
<?php include 'includes/header.php'; ?>

<div class="main-layout">
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="content">
        <div class="content-header">
            <h1 class="content-title">
                <i class="fas fa-users"></i>
                Gestión de Equipos
            </h1>
            <p class="content-subtitle">Administración de equipos de trabajo</p>
        </div>
        
        <!-- Botón para agregar nuevo equipo -->
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; gap: 16px;">
                <div style="display: flex; align-items: center; gap: 16px; flex: 1;">
                    <input
                        type="text"
                        id="searchInput"
                        class="form-input"
                        placeholder="Buscar equipo por nombre..."
                        aria-label="Buscar equipo"
                        style="max-width: 300px;"
                        autocomplete="off"
                    >
                    
                    <!-- Filtro de estado -->
                    <div class="filter-dropdown-container">
                        <button type="button" class="btn btn-outline" id="statusFilterDropdownBtn" onclick="toggleStatusFilterDropdown()">
                            <i class="fas fa-filter"></i>
                            Estado
                            <span id="activeStatusFiltersCount" class="filter-count" style="display: none;">1</span>
                        </button>
                        <div class="filter-dropdown" id="statusFilterDropdown">
                            <div class="filter-section">
                                <label class="filter-label">
                                    <i class="fas fa-toggle-on"></i>
                                    Estado del Equipo
                                </label>
                                <select id="statusFilter" class="form-input">
                                    <option value="">Todos los equipos</option>
                                    <option value="1">Solo activos</option>
                                    <option value="0">Solo inactivos</option>
                                </select>
                            </div>
                            
                            <div class="filter-actions">
                                <button type="button" class="btn btn-secondary" onclick="clearStatusFilters()">
                                    <i class="fas fa-times"></i>
                                    Limpiar
                                </button>
                                <button type="button" class="btn btn-primary" onclick="applyStatusFilters()">
                                    <i class="fas fa-search"></i>
                                    Aplicar
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Filtros activos -->
                    <div id="activeStatusFiltersContainer" style="display: none; gap: 8px;"></div>
                </div>
                <div>
                    <button type="button" class="btn btn-primary" onclick="openModal('teamModal')">
                        <i class="fas fa-plus"></i>
                        Nuevo Equipo
                    </button>
                </div>
            </div>
        </div>
        <!-- Tabla de equipos -->
        <div class="card">
            <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-table"></i>
                Lista de Equipos
            </h3>
            <p class="card-subtitle">Total: <span id="totalTeams">5</span> equipos registrados</p>
            </div>
            
            <div style="overflow-x: auto;">
            <table class="data-table sortable-table" id="teamsTable" style="min-width: 500px;">
                <thead>
                <tr>
                    <th class="sortable" data-sort="name">
                        Nombre del Equipo
                        <i class="fas fa-sort sort-icon"></i>
                    </th>
                    <th class="sortable" data-sort="description">
                        Descripción
                        <i class="fas fa-sort sort-icon"></i>
                    </th>
                    <th class="sortable" data-sort="status">
                        Estado
                        <i class="fas fa-sort sort-icon"></i>
                    </th>
                    <th style="vertical-align: middle; text-align: center;">Acciones</th>
                </tr>
                </thead>
                <tbody id="teamsTableBody">
                <!-- Las filas se llenarán dinámicamente con JS -->
                </tbody>
            </table>
            <!-- Pie de tabla para selector y paginación -->
            <div id="teamsTableFooter" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0 0 0;">
                <div id="pageSizeSelectorContainer"></div>
                <div id="teamsPagination"></div>
            </div>
            </div>
        </div>
    </main>
</div>

<!-- Modal para crear/editar equipo -->
<div class="modal" id="teamModal">
    <div class="modal-overlay" onclick="closeModal('teamModal')"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Nuevo Equipo</h2>
            <button type="button" class="modal-close" onclick="closeModal('teamModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="teamForm">
            <div class="modal-body">
                <input type="hidden" id="teamId" name="teamId" value="">
                <div class="form-group">
                    <label class="form-label" for="teamName">Nombre del Equipo *</label>
                    <input type="text" class="form-input" id="teamName" name="teamName" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="teamDescription">Descripción</label>
                    <textarea class="form-input" id="teamDescription" name="teamDescription" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Estado del Equipo</label>
                    <div style="display: flex; align-items: center; gap: 12px; margin-top: 8px;">
                        <label class="switch">
                            <input type="checkbox" id="teamStatus" name="teamStatus" checked>
                            <span class="slider"></span>
                        </label>
                        <span id="teamStatusLabel" style="font-weight: 500; color: var(--success-color);">Activo</span>
                    </div>
                    <small class="form-text" style="color: var(--text-secondary); font-size: 12px; margin-top: 4px;">
                        <i class="fas fa-info-circle"></i>
                        Los equipos inactivos no aparecerán en las listas de selección para nuevos gastos e ingresos
                    </small>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('teamModal')" style="background-color: var(--secondary-color); color: white;">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Guardar Equipo
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de notificación -->
<div class="modal" id="notificationModal" style="display:none;">
    <div class="modal-overlay" onclick="closeModal('notificationModal')"></div>
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h2 id="notificationTitle">Notificación</h2>
            <button type="button" class="modal-close" onclick="closeModal('notificationModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <p id="notificationMessage"></p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary" onclick="closeModal('notificationModal')">Aceptar</button>
        </div>
    </div>
</div>

<!-- Modal de confirmación de cambio de estado -->
<div class="modal" id="confirmStatusChangeModal" style="display:none;">
    <div class="modal-overlay" onclick="closeModal('confirmStatusChangeModal')"></div>
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header">
            <h2 id="confirmStatusTitle">Confirmar cambio de estado</h2>
            <button type="button" class="modal-close" onclick="closeModal('confirmStatusChangeModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 16px;">
                <div style="font-size: 48px; color: var(--warning-color);">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div>
                    <p id="confirmStatusMessage" style="margin: 0; font-size: 16px; line-height: 1.4;">
                        ¿Está seguro de que desea cambiar el estado de este equipo?
                    </p>
                    <p id="confirmStatusDetails" style="margin: 8px 0 0 0; font-size: 14px; color: var(--text-secondary);">
                        
                    </p>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn" onclick="closeModal('confirmStatusChangeModal')" style="background-color: var(--secondary-color); color: white;">
                Cancelar
            </button>
            <button type="button" class="btn" id="confirmStatusBtn" style="min-width: 100px;">
                <i id="confirmStatusIcon" class="fas fa-check"></i>
                <span id="confirmStatusBtnText">Activar</span>
            </button>
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
            <p id="confirmDeleteMessage">¿Está seguro de que desea eliminar este equipo?</p>
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

<?php include 'includes/footer.php'; ?>

<script src="assets/js/teams.js"></script>

<!-- Estilos para tabla ordenable -->
<style>
/* Tabla ordenable */
.sortable-table th.sortable {
    cursor: pointer;
    user-select: none;
    position: relative;
    transition: background-color 0.2s ease;
}

.sortable-table th.sortable:hover {
    background-color: #f8fafc;
}

.sort-icon {
    margin-left: 8px;
    font-size: 12px;
    color: var(--text-secondary);
    transition: color 0.2s ease;
}

.sortable-table th.sortable.sort-asc .sort-icon:before {
    content: "\f0de"; /* fa-sort-up */
    color: var(--primary-color);
}

.sortable-table th.sortable.sort-desc .sort-icon:before {
    content: "\f0dd"; /* fa-sort-down */
    color: var(--primary-color);
}

/* Estilos para el switch de estado */
.switch {
    position: relative;
    display: inline-block;
    width: 50px;
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
    height: 16px;
    width: 16px;
    left: 4px;
    bottom: 4px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

input:checked + .slider {
    background-color: var(--primary-color);
}

input:focus + .slider {
    box-shadow: 0 0 1px var(--primary-color);
}

input:checked + .slider:before {
    transform: translateX(26px);
}

/* Estilos para badges de estado */
.status-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-active {
    background-color: #dcfce7;
    color: #166534;
}

.status-inactive {
    background-color: #fef2f2;
    color: #dc2626;
}

/* Estilos para filtros */
.filter-dropdown-container {
    position: relative;
    display: inline-block;
}

.filter-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    background: white;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    padding: 16px;
    min-width: 250px;
    z-index: 1000;
    display: none;
}

.filter-dropdown.show {
    display: block;
}

.filter-section {
    margin-bottom: 16px;
}

.filter-label {
    display: block;
    font-weight: 500;
    margin-bottom: 8px;
    color: var(--text-color);
}

.filter-actions {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
}

.filter-count {
    background: var(--primary-color);
    color: white;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    font-size: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-left: 4px;
}

.active-filter-btn {
    background: var(--primary-color);
    color: white;
    padding: 4px 8px;
    border-radius: 16px;
    font-size: 12px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.active-filter-btn i {
    cursor: pointer;
    opacity: 0.8;
}

.active-filter-btn i:hover {
    opacity: 1;
}
</style>
