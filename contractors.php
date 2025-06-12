<?php
require_once 'config.php';

if (!isLoggedIn() || !checkSessionTimeout()) {
    header('Location: auth/login.php');
    exit;
}

$pageTitle = 'Gestión de Contratistas';
?>
<?php include 'includes/header.php'; ?>

<div class="main-layout">
    <?php include 'includes/sidebar.php'; ?>
    <main class="content">
        <div class="content-header">
            <h1 class="content-title">
                <i class="fas fa-user-tie"></i>
                Gestión de Contratistas
            </h1>
            <p class="content-subtitle">Administración de contratistas externos</p>
        </div>
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; gap: 16px;">
                <div style="display: flex; align-items: center; gap: 16px; flex: 1;">
                    <input
                        type="text"
                        id="searchInput"
                        class="form-input"
                        placeholder="Buscar contratista por nombre..."
                        aria-label="Buscar contratista"
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
                                    Estado del Contratista
                                </label>
                                <select id="statusFilter" class="form-input">
                                    <option value="">Todos los contratistas</option>
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
                <div style="display: flex; gap: 12px;">
                    <button type="button" class="btn btn-primary header-btn" onclick="openModal('paymentModal')">
                        <i class="fas fa-money-bill-wave"></i>
                        Registrar Pago
                    </button>
                    <button type="button" class="btn btn-primary header-btn" onclick="openModal('contractorModal')">
                        <i class="fas fa-plus"></i>
                        Nuevo Contratista
                    </button>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-table"></i>
                    Lista de Contratistas
                </h3>
                <p class="card-subtitle">Total: <span id="totalContractors">0</span> contratistas registrados</p>
            </div>
            <div style="overflow-x: auto;">
                <table class="data-table sortable-table" id="contractorsTable" style="min-width: 700px;">
                    <thead>
                        <tr>
                            <th class="sortable" data-sort="name">
                                Nombre
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th class="sortable" data-sort="email">
                                Email
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th class="sortable" data-sort="phone">
                                Teléfono
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th>Dirección</th>
                            <th class="sortable" data-sort="status">
                                Estado
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th style="vertical-align: middle; text-align: center;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="contractorsTableBody">
                        <!-- Las filas se llenarán dinámicamente con JS -->
                    </tbody>
                </table>
                <div id="contractorsTableFooter" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0 0 0;">
                    <div id="pageSizeSelectorContainer"></div>
                    <div id="contractorsPagination"></div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modal para crear/editar contratista -->
<div class="modal" id="contractorModal">
    <div class="modal-overlay" onclick="closeModal('contractorModal')"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Nuevo Contratista</h2>
            <button type="button" class="modal-close" onclick="closeModal('contractorModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="contractorForm">
            <div class="modal-body">
                <input type="hidden" id="contractorId" name="contractorId" value="">
                <div class="form-group">
                    <label class="form-label" for="contractorName">Nombre *</label>
                    <input type="text" class="form-input" id="contractorName" name="contractorName" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="contractorEmail">Email</label>
                    <input type="email" class="form-input" id="contractorEmail" name="contractorEmail">
                </div>
                <div class="form-group">
                    <label class="form-label" for="contractorPhone">Teléfono</label>
                    <input type="text" class="form-input" id="contractorPhone" name="contractorPhone">
                </div>
                <div class="form-group">
                    <label class="form-label" for="contractorAddress">Dirección</label>
                    <textarea class="form-input" id="contractorAddress" name="contractorAddress" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Estado del Contratista</label>
                    <div style="display: flex; align-items: center; gap: 12px; margin-top: 8px;">
                        <label class="switch">
                            <input type="checkbox" id="contractorStatus" name="contractorStatus" checked>
                            <span class="slider"></span>
                        </label>
                        <span id="contractorStatusLabel" style="font-weight: 500; color: var(--success-color);">Activo</span>
                    </div>
                    <small class="form-text" style="color: var(--text-secondary); font-size: 12px; margin-top: 4px;">
                        <i class="fas fa-info-circle"></i>
                        Los contratistas inactivos no aparecerán en las listas de selección para nuevos ingresos
                    </small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('contractorModal')" style="background-color: var(--secondary-color); color: white;">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Guardar Contratista
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
            <p id="confirmDeleteMessage">¿Está seguro de que desea eliminar este contratista?</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn" onclick="closeModal('confirmDeleteModal')">Cancelar</button>
            <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Eliminar</button>
        </div>
    </div>
</div>

<!-- Modal para registro de pago -->
<div class="modal" id="paymentModal">
    <div class="modal-overlay" onclick="closeModal('paymentModal')"></div>
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h2 id="paymentModalTitle">Registrar Pago a Contratista</h2>
            <button type="button" class="modal-close" onclick="closeModal('paymentModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="paymentForm">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="paymentContractorId">Contratista *</label>
                    <select class="form-input" id="paymentContractorId" name="contractorId" required>
                        <option value="">Seleccionar contratista...</option>
                        <!-- Se llena dinámicamente -->
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="paymentBankAccountId">Cuenta Bancaria *</label>
                    <select class="form-input" id="paymentBankAccountId" name="bankAccountId" required>
                        <option value="">Seleccionar cuenta...</option>
                        <!-- Se llena dinámicamente -->
                    </select>
                    <small class="form-help">Solo se muestran cuentas bancarias (no crédito)</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="paymentAmount">Monto *</label>
                    <input type="number" class="form-input" id="paymentAmount" name="amount" step="0.01" min="0.01" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="paymentDate">Fecha de Pago *</label>
                    <input type="date" class="form-input" id="paymentDate" name="paymentDate" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="paymentReferenceNumber">Número de Referencia</label>
                    <input type="text" class="form-input" id="paymentReferenceNumber" name="referenceNumber" placeholder="Ej: TRF001, CHQ1234">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="paymentNotes">Notas</label>
                    <textarea class="form-input" id="paymentNotes" name="notes" rows="3" placeholder="Notas adicionales sobre el pago..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('paymentModal')" style="background-color: var(--secondary-color); color: white;">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i>
                    Registrar Pago
                </button>
            </div>
        </form>
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
                        ¿Está seguro de que desea cambiar el estado de este contratista?
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

<!-- Toast de notificación -->
<div id="toast" style="position: fixed; bottom: 32px; right: 32px; min-width: 220px; z-index: 9999; display: none; background: var(--primary-color, #2563eb); color: #fff; padding: 16px 24px; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,0.12); font-size: 16px; font-weight: 500; align-items: center; gap: 8px;">
    <span id="toastIcon" style="margin-right: 8px;"></span>
    <span id="toastMessage"></span>
</div>

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

/* Estilos para botones del header - ancho automático */
.header-btn {
    width: auto !important;
    white-space: nowrap;
    min-width: auto;
}

/* Estilo para enlaces de nombre de contratista */
.contractor-name-link {
    color: var(--primary-color);
    text-decoration: none;
    font-weight: 500;
    transition: all 0.2s ease;
}

.contractor-name-link:hover {
    color: var(--primary-dark, #1d4ed8);
    text-decoration: underline;
}

.contractor-name-link:visited {
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

<?php include 'includes/footer.php'; ?>

<script src="assets/js/contractors.js"></script>
