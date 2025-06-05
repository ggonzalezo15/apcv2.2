<?php
require_once 'config.php';

if (!isLoggedIn() || !checkSessionTimeout()) {
    header('Location: login.php');
    exit;
}

$pageTitle = 'Gestión de Gastos';
?>
<?php include 'includes/header.php'; ?>

<!-- Flatpickr CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<!-- Flatpickr JS -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>

<div class="main-layout">
    <?php include 'includes/sidebar.php'; ?>
    <main class="content">
        <div class="content-header">
            <h1 class="content-title">
                <i class="fas fa-receipt"></i>
                Gestión de Gastos
            </h1>
            <p class="content-subtitle">Administración de gastos y transacciones</p>
        </div>
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap;">
                <div style="flex: 1; display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                    <input
                        type="text"
                        id="searchInput"
                        class="form-input"
                        placeholder="Buscar gasto..."
                        aria-label="Buscar gasto"
                        style="max-width: 300px; min-width: 200px;"
                        autocomplete="off"
                    >
                    
                    <!-- Dropdown de filtros avanzados -->
                    <div class="filter-dropdown-container">
                        <button type="button" class="btn btn-outline" id="filterDropdownBtn" onclick="toggleFilterDropdown()">
                            <i class="fas fa-filter"></i>
                            Filtros
                            <span id="activeFiltersCount" class="filter-count" style="display: none;">0</span>
                        </button>
                        <div class="filter-dropdown" id="filterDropdown">
                            <div class="filter-section">
                                <label class="filter-label">
                                    Equipo
                                </label>
                                <select id="teamFilter" class="form-input">
                                    <option value="">Todos los equipos</option>
                                </select>
                            </div>
                            
                            <div class="filter-section">
                                <label class="filter-label">
                                    Proveedor
                                </label>
                                <select id="vendorFilter" class="form-input">
                                    <option value="">Todos los proveedores</option>
                                </select>
                            </div>
                            
                            <div class="filter-section">
                                <label class="filter-label">
                                    Rango de Fechas
                                </label>
                                <div class="date-range-inputs">
                                    <input type="text" id="dateFromFilter" class="form-input date-picker" placeholder="Fecha desde...">
                                    <input type="text" id="dateToFilter" class="form-input date-picker" placeholder="Fecha hasta...">
                                </div>
                            </div>
                            
                            <div class="filter-actions">
                                <button type="button" class="btn btn-secondary" onclick="clearAllFilters()">
                                    <i class="fas fa-times"></i>
                                    Limpiar
                                </button>
                                <button type="button" class="btn btn-primary" onclick="applyFilters()">
                                    <i class="fas fa-search"></i>
                                    Aplicar
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Filtros activos -->
                    <div id="activeFiltersContainer" class="active-filters-container" style="display: none;">
                        <!-- Se llenarán dinámicamente -->
                    </div>
                </div>
                
                <div>
                    <button type="button" class="btn btn-primary" onclick="resetExpenseForm(); openModal('expenseModal')">
                        <i class="fas fa-plus"></i>
                        Nuevo Gasto
                    </button>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-table"></i>
                    Lista de Gastos
                </h3>
                <p class="card-subtitle">Total: <span id="totalExpenses">0</span> gastos registrados</p>
            </div>
            <div style="overflow-x: auto;">
                <table class="data-table sortable-table" id="expensesTable" style="min-width: 1400px;">
                    <thead>
                        <tr>
                            <th class="sortable" data-sort="expense_date">
                                Fecha
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th class="sortable" data-sort="team_name">
                                Equipo
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th class="sortable" data-sort="vendor_name">
                                Proveedor
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th>Cuenta Bancaria</th>
                            <th class="sortable" data-sort="total_amount">
                                Monto Total
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th>Adjuntos</th>
                            <th>Notas</th>
                            <th style="vertical-align: middle; text-align: center; width: 120px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="expensesTableBody">
                        <!-- Las filas se llenarán dinámicamente con JS -->
                    </tbody>
                </table>
                <div id="expensesTableFooter" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0 0 0;">
                    <div id="pageSizeSelectorContainer"></div>
                    <div id="expensesPagination"></div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modal para crear/editar gasto -->
<div class="modal" id="expenseModal">
    <div class="modal-overlay" onclick="closeModal('expenseModal')"></div>
    <div class="modal-content" style="max-width: 900px; max-height: 90vh; overflow-y: auto;">
        <div class="modal-header">
            <h2 id="modalTitle">Nuevo Gasto</h2>
            <button type="button" class="modal-close" onclick="closeModal('expenseModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="expenseForm" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" id="expenseId" name="expenseId" value="">
                
                <!-- Sección de información general -->
                <div class="form-section">
                    <h3 class="form-section-title">
                        <i class="fas fa-info-circle"></i>
                        Información General
                    </h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="expenseDate">Fecha *</label>
                            <input type="text" class="form-input" id="expenseDate" name="expenseDate" placeholder="Seleccionar fecha..." required readonly onchange="validateMainField(this, 'date')" onblur="validateMainField(this, 'date')">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Equipo *</label>
                            <select id="team" class="form-input" required onchange="validateMainField(this, 'team')" onblur="validateMainField(this, 'team')">
                                <option value="">Seleccionar equipo...</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Proveedor *</label>
                            <select id="vendor" class="form-input" required onchange="validateMainField(this, 'vendor')" onblur="validateMainField(this, 'vendor')">
                                <option value="">Seleccionar proveedor...</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Cuenta Bancaria *</label>
                            <select id="bankAccount" class="form-input" required onchange="validateMainField(this, 'bankAccount')" onblur="validateMainField(this, 'bankAccount')">
                                <option value="">Seleccionar cuenta...</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Sección de líneas de gastos mejorada -->
                <div class="modal-section">
                    <div class="section-header">
                        <div class="section-title">
                            <i class="fas fa-list"></i>
                            <h3>Líneas de Gastos</h3>
                        </div>
                        <button type="button" onclick="addExpenseLine()" class="btn-add-line">
                            <i class="fas fa-plus"></i> Agregar Línea
                        </button>
                    </div>
                    
                    <div class="expense-lines-wrapper">
                        <div class="expense-lines-header">
                            <div class="header-cell description-header">Descripción *</div>
                            <div class="header-cell type-header">Tipo de Gasto *</div>
                            <div class="header-cell amount-header">Importe *</div>
                            <div class="header-cell deducible-header">Deducible</div>
                            <div class="header-cell actions-header">Acción</div>
                        </div>
                        
                        <div id="expense-lines" class="expense-lines-container">
                            <!-- Las líneas se agregan dinámicamente -->
                        </div>
                        
                        <div class="expense-lines-footer">
                            <div class="total-section">
                                <div class="total-label">Total del Gasto:</div>
                                <div id="totalAmount" class="total-amount">$0.00</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sección de archivos adjuntos -->
                <div class="form-section">
                    <h3 class="form-section-title">
                        <i class="fas fa-paperclip"></i>
                        Archivos Adjuntos
                    </h3>
                    <div class="form-group">
                        <label class="form-label">Archivos adjuntos (máx. 4 archivos, 2MB cada uno)</label>
                        <div class="file-upload-area" id="fileUploadArea">
                            <div class="file-upload-content">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p>Arrastra archivos aquí o <span class="file-upload-link">selecciona archivos</span></p>
                                <small>Solo se permiten archivos JPG, PNG y PDF</small>
                            </div>
                            <input type="file" class="form-input" id="attachments" multiple accept=".jpg,.jpeg,.png,.pdf" style="display: none;">
                        </div>
                        <div id="attachments-preview" class="attachments-preview"></div>
                    </div>
                </div>

                <!-- Sección de notas -->
                <div class="form-section">
                    <h3 class="form-section-title">
                        <i class="fas fa-sticky-note"></i>
                        Notas
                    </h3>
                    <div class="form-group">
                        <label class="form-label">Notas</label>
                        <textarea id="notes" class="form-input" rows="3" placeholder="Descripción adicional del gasto..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('expenseModal')" style="background-color: var(--secondary-color); color: white;">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Guardar Gasto
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
<div class="modal" id="deleteModal" style="display:none;">
    <div class="modal-overlay" onclick="closeModal('deleteModal')"></div>
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h2>Confirmar eliminación</h2>
            <button type="button" class="modal-close" onclick="closeModal('deleteModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <p id="confirmDeleteMessage">¿Está seguro de que desea eliminar este gasto?</p>
            <input type="hidden" id="deleteExpenseId">
        </div>
        <div class="modal-footer">
            <button type="button" onclick="closeModal('deleteModal')" class="btn btn-secondary">Cancelar</button>
            <button type="button" onclick="deleteExpenseConfirmed()" class="btn btn-danger">Eliminar</button>
        </div>
    </div>
</div>

<!-- Modal de vista de gasto (estilo factura) -->
<div class="modal" id="viewExpenseModal" style="display:none;">
    <div class="modal-overlay" onclick="closeModal('viewExpenseModal')"></div>
    <div class="modal-content" style="max-width: 800px; max-height: 90vh; overflow-y: auto;">
        <div class="expense-invoice">
            <!-- Header del recibo -->
            <div class="invoice-header">
                <div class="invoice-title">
                    <h1><i class="fas fa-receipt"></i> Detalle de Gasto</h1>
                    <div class="expense-number" id="viewExpenseNumber">EXP000001</div>
                </div>
                <button type="button" class="modal-close" onclick="closeModal('viewExpenseModal')" title="Cerrar">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Información general -->
            <div class="invoice-info">
                <div class="info-section">
                    <h3><i class="fas fa-info-circle"></i> Información General</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Fecha:</label>
                            <span id="viewExpenseDate">-</span>
                        </div>
                        <div class="info-item">
                            <label>Equipo:</label>
                            <span id="viewTeamName">-</span>
                        </div>
                        <div class="info-item">
                            <label>Proveedor:</label>
                            <span id="viewVendorName">-</span>
                        </div>
                        <div class="info-item">
                            <label>Cuenta Bancaria:</label>
                            <span id="viewBankAccount">-</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Líneas de gasto -->
            <div class="invoice-lines">
                <h3><i class="fas fa-list"></i> Líneas de Gasto</h3>
                <div class="lines-table">
                    <div class="lines-header">
                        <div>Descripción</div>
                        <div>Tipo</div>
                        <div>Importe</div>
                        <div>Deducible</div>
                    </div>
                    <div id="viewExpenseLines" class="lines-body">
                        <!-- Se llenarán dinámicamente -->
                    </div>
                </div>
            </div>

            <!-- Total -->
            <div class="invoice-total">
                <div class="total-line">
                    <span class="total-label">TOTAL DEL GASTO:</span>
                    <span class="total-amount" id="viewTotalAmount">$0.00</span>
                </div>
            </div>

            <!-- Archivos adjuntos -->
            <div class="invoice-attachments" id="viewAttachmentsSection" style="display:none;">
                <h3><i class="fas fa-paperclip"></i> Archivos Adjuntos</h3>
                <div id="viewAttachments" class="attachments-list">
                    <!-- Se llenarán dinámicamente -->
                </div>
            </div>

            <!-- Notas -->
            <div class="invoice-notes" id="viewNotesSection" style="display:none;">
                <h3><i class="fas fa-sticky-note"></i> Notas</h3>
                <div class="notes-content" id="viewNotes">
                    <!-- Se llenarán dinámicamente -->
                </div>
            </div>

            <!-- Footer con acciones -->
            <div class="invoice-footer">
                <button type="button" class="btn" onclick="closeModal('viewExpenseModal')" style="background-color: var(--secondary-color); color: white;">
                    <i class="fas fa-arrow-left"></i> Cerrar
                </button>
                <div class="footer-actions">
                    <button type="button" class="btn btn-primary" onclick="editExpenseFromView()" id="editFromViewBtn">
                        <i class="fas fa-edit"></i> Editar
                    </button>
                    <button type="button" class="btn" onclick="printExpense()" style="background-color: var(--info-color); color: white;">
                        <i class="fas fa-print"></i> Imprimir
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toast de notificación -->
<div id="toast" style="position: fixed; bottom: 32px; right: 32px; min-width: 220px; z-index: 9999; display: none; background: var(--primary-color, #2563eb); color: #fff; padding: 16px 24px; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,0.12); font-size: 16px; font-weight: 500; align-items: center; gap: 8px;">
    <span id="toastIcon" style="margin-right: 8px;"></span>
    <span id="toastMessage"></span>
</div>

<?php include 'includes/footer.php'; ?>

<script src="assets/js/expenses.js"></script>

<style>
.form-section {
    margin-bottom: 24px;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 20px;
}

.form-section-title {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
    font-size: 16px;
    font-weight: 600;
    color: var(--text-primary);
    border-bottom: 1px solid var(--border-color);
    padding-bottom: 8px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.expense-line {
    display: grid;
    grid-template-columns: 1.5fr 1fr 1fr auto;
    gap: 12px;
    align-items: end;
    padding: 12px;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    margin-bottom: 12px;
    background-color: var(--background-secondary);
}

.expense-line:last-child {
    margin-bottom: 0;
}

.btn-remove-line {
    background-color: var(--danger-color);
    color: white;
    border: none;
    padding: 8px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    height: fit-content;
}

.btn-remove-line:hover {
    background-color: #dc2626;
}

.attachment-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    margin-bottom: 8px;
    background-color: var(--background-secondary);
}

.attachment-item .btn-remove {
    background-color: var(--danger-color);
    color: white;
    border: none;
    padding: 4px 8px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .expense-line {
        grid-template-columns: 1fr;
        gap: 8px;
    }
}

.expense-line-grid {
    display: grid;
    grid-template-columns: 1.5fr 100px 120px 40px;
    gap: 12px;
    align-items: center;
}

.expense-line .form-input {
    margin: 0;
}

.btn-remove-line {
    background: #dc3545;
    color: white;
    border: none;
    border-radius: 4px;
    width: 32px;
    height: 32px;
    cursor: pointer;
    font-size: 12px;
    transition: background-color 0.2s;
}

.btn-remove-line:hover {
    background: #c82333;
}

.btn-add-line {
    background: var(--primary-color);
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: background-color 0.2s;
}

.btn-add-line:hover {
    background: color-mix(in srgb, var(--primary-color) 90%, black);
}

.total-section {
    margin-top: 16px;
    padding: 12px 16px;
    background: var(--bg-primary);
    border-radius: 6px;
    text-align: right;
    font-size: 16px;
    font-weight: 600;
}

.total-amount {
    color: var(--primary-color);
    font-size: 18px;
}

.attachments-preview {
    margin-top: 8px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.attachment-preview {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 12px;
    background: var(--bg-primary);
    border-radius: 4px;
    font-size: 14px;
    border: 1px solid var(--border-color);
}

.attachment-preview button {
    background: var(--danger-color);
    color: white;
    border: none;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    cursor: pointer;
    font-size: 14px;
    font-weight: bold;
}

.attachment-preview button:hover {
    background: #dc2626;
}

/* Toast notifications */
.toast {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 12px 20px;
    border-radius: 6px;
    color: white;
    font-weight: 500;
    opacity: 0;
    transform: translateX(100%);
    transition: all 0.3s ease;
    z-index: 10000;
}

.toast.show {
    opacity: 1;
    transform: translateX(0);
}

.toast-success {
    background: #10b981;
}

.toast-error {
    background: #ef4444;
}

.toast-info {
    background: #3b82f6;
}

/* Sección de líneas de gastos mejorada */
.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border-color);
}

.section-title {
    display: flex;
    align-items: center;
    gap: 10px;
}

.section-title h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: var(--text-primary);
}

.section-title i {
    color: var(--primary-color);
    font-size: 20px;
}

.btn-add-line {
    background: var(--primary-color);
    color: white;
    border: none;
    padding: 10px 16px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
}

.btn-add-line:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
}

.expense-lines-wrapper {
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    overflow: hidden;
}

.expense-lines-header {
    display: grid;
    grid-template-columns: 2fr 180px 130px 100px 60px;
    gap: 12px;
    background: var(--bg-primary);
    padding: 16px;
    border-bottom: 1px solid var(--border-color);
    font-weight: 600;
    font-size: 14px;
    color: var(--text-secondary);
}

.header-cell {
    display: flex;
    align-items: center;
}

.expense-lines-container {
    max-height: 400px;
    overflow-y: auto;
}

.expense-line {
    display: grid;
    grid-template-columns: 2fr 180px 130px 100px 60px;
    gap: 12px;
    align-items: center;
    padding: 16px;
    border-bottom: 1px solid var(--border-color);
    background: var(--bg-secondary);
    transition: all 0.2s ease;
}

.expense-line:hover {
    background: var(--bg-primary);
}

.expense-line:last-child {
    border-bottom: none;
}

.expense-line .form-input {
    margin: 0;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    padding: 8px 12px;
    font-size: 14px;
    transition: all 0.2s ease;
}

.expense-line .form-input:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.1);
}

.expense-line .line-description {
    font-weight: 500;
}

.expense-line .line-amount {
    text-align: right;
}

.btn-remove-line {
    background: var(--danger-color);
    color: white;
    border: none;
    border-radius: 6px;
    width: 36px;
    height: 36px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-remove-line:hover {
    background: #dc2626;
    transform: scale(1.05);
}

.expense-lines-footer {
    background: var(--bg-primary);
    padding: 16px;
    border-top: 1px solid var(--border-color);
}

.total-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.total-label {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-primary);
}

.total-amount {
    font-size: 20px;
    font-weight: 700;
    color: var(--primary-color);
}

/* Responsive design */
@media (max-width: 768px) {
    .expense-lines-header,
    .expense-line {
        grid-template-columns: 1fr;
        gap: 8px;
    }
    
    .expense-lines-header {
        display: none;
    }
    
    .expense-line {
        background: var(--bg-secondary);
        border-radius: 6px;
        margin-bottom: 12px;
        padding: 16px;
        border: 1px solid var(--border-color);
    }
    
    .expense-line .form-input {
        width: 100%;
        margin-bottom: 8px;
    }
    
    .btn-remove-line {
        width: 100%;
        height: 40px;
        border-radius: 6px;
    }
    
    .total-section {
        flex-direction: column;
        gap: 8px;
        text-align: center;
    }
    
    .total-amount {
        font-size: 24px;
    }
}

/* Estados de validación */
.expense-line .form-input.error {
    border-color: var(--danger-color);
    background-color: #fef2f2;
}

.expense-line .form-input.error:focus {
    box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.1);
}

.expense-line .form-input.success {
    border-color: var(--success-color);
    background-color: #f0fdf4;
}

/* Scrollbar personalizado */
.expense-lines-container::-webkit-scrollbar {
    width: 6px;
}

.expense-lines-container::-webkit-scrollbar-track {
    background: var(--bg-primary);
}

.expense-lines-container::-webkit-scrollbar-thumb {
    background: var(--border-color);
    border-radius: 3px;
}

.expense-lines-container::-webkit-scrollbar-thumb:hover {
    background: var(--text-secondary);
}

/* Switch para deducible */
.deducible-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
}

.deducible-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.switch-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: var(--border-color);
    transition: .3s;
    border-radius: 24px;
}

.switch-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .3s;
    border-radius: 50%;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
}

.deducible-switch input:checked + .switch-slider {
    background-color: var(--primary-color);
}

.deducible-switch input:checked + .switch-slider:before {
    transform: translateX(26px);
}

.switch-slider:after {
    content: 'NO';
    position: absolute;
    left: 8px;
    top: 3px;
    font-size: 10px;
    font-weight: bold;
    color: var(--text-secondary);
    transition: .3s;
}

.deducible-switch input:checked + .switch-slider:after {
    content: 'SÍ';
    left: 6px;
    color: white;
}

/* Estilos para el modal de vista (estilo factura) */
.expense-invoice {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

.invoice-header {
    background: var(--bg-secondary);
    color: var(--text-primary);
    padding: 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--border-color);
}

.invoice-title h1 {
    margin: 0;
    font-size: 24px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 12px;
}

.expense-number {
    background: var(--bg-primary);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    margin-top: 8px;
    display: inline-block;
}

.invoice-header .modal-close {
    background: var(--bg-primary);
    color: var(--text-secondary);
    border: 1px solid var(--border-color);
    width: 40px;
    height: 40px;
    font-size: 16px;
}

.invoice-header .modal-close:hover {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

.invoice-info {
    padding: 24px;
    border-bottom: 1px solid #e2e8f0;
}

.info-section h3 {
    margin: 0 0 16px 0;
    color: var(--text-primary);
    font-size: 18px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

.info-section h3 i {
    color: var(--primary-color);
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.info-item label {
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    color: var(--text-secondary);
    letter-spacing: 0.5px;
}

.info-item span {
    font-size: 16px;
    font-weight: 500;
    color: var(--text-primary);
}

.invoice-lines {
    padding: 24px;
    border-bottom: 1px solid #e2e8f0;
}

.invoice-lines h3 {
    margin: 0 0 16px 0;
    color: var(--text-primary);
    font-size: 18px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

.invoice-lines h3 i {
    color: var(--primary-color);
}

.lines-table {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    overflow: hidden;
}

.lines-header {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 100px;
    gap: 16px;
    background: #f1f5f9;
    padding: 16px;
    font-weight: 600;
    font-size: 14px;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.lines-body {
    display: flex;
    flex-direction: column;
}

.line-item {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 100px;
    gap: 16px;
    padding: 16px;
    border-bottom: 1px solid #e2e8f0;
    background: white;
}

.line-item:last-child {
    border-bottom: none;
}

.line-item:hover {
    background: #f8fafc;
}

.line-description {
    font-weight: 500;
    color: var(--text-primary);
}

.line-type {
    color: var(--text-secondary);
    font-size: 14px;
}

.line-amount {
    font-weight: 600;
    color: var(--primary-color);
    text-align: right;
}

.line-deducible {
    display: flex;
    justify-content: center;
    align-items: center;
}

.deducible-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.deducible-badge.yes {
    background: #dcfce7;
    color: #166534;
}

.deducible-badge.no {
    background: #fef2f2;
    color: #991b1b;
}

.invoice-total {
    padding: 24px;
    background: var(--bg-secondary);
    border-top: 1px solid var(--border-color);
    border-bottom: 1px solid var(--border-color);
}

.total-line {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.total-label {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-primary);
}

.total-amount {
    font-size: 28px;
    font-weight: 700;
    color: var(--primary-color);
}

.invoice-attachments, .invoice-notes {
    padding: 24px;
    border-bottom: 1px solid #e2e8f0;
}

.invoice-attachments h3, .invoice-notes h3 {
    margin: 0 0 16px 0;
    color: var(--text-primary);
    font-size: 18px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

.invoice-attachments h3 i, .invoice-notes h3 i {
    color: var(--primary-color);
}

.attachments-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 12px;
}

.attachment-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.attachment-item:hover {
    background: #f1f5f9;
    border-color: var(--primary-color);
}

.attachment-item i {
    color: var(--primary-color);
    font-size: 18px;
}

.attachment-info {
    flex: 1;
}

.attachment-name {
    font-weight: 500;
    color: var(--text-primary);
    font-size: 14px;
    margin-bottom: 2px;
}

.attachment-size {
    font-size: 12px;
    color: var(--text-secondary);
}

.notes-content {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 16px;
    color: var(--text-primary);
    line-height: 1.6;
    font-size: 15px;
}

.invoice-footer {
    padding: 24px;
    background: #f8fafc;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px solid #e2e8f0;
}

.footer-actions {
    display: flex;
    gap: 12px;
}

/* Responsive para el modal de vista */
@media (max-width: 768px) {
    .invoice-header {
        flex-direction: column;
        text-align: center;
        gap: 16px;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .lines-header,
    .line-item {
        grid-template-columns: 1fr;
        gap: 8px;
    }
    
    .lines-header {
        display: none;
    }
    
    .line-item {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
    }
    
    .line-amount {
        text-align: left;
        font-size: 18px;
    }
    
    .total-line {
        flex-direction: column;
        gap: 8px;
        text-align: center;
    }
    
    .total-amount {
        font-size: 28px;
    }
    
    .invoice-footer {
        flex-direction: column;
        gap: 16px;
    }
    
    .footer-actions {
        width: 100%;
        justify-content: center;
    }
}

/* Zona de drag and drop para archivos */
.file-upload-area {
    border: 2px dashed var(--border-color);
    border-radius: 8px;
    padding: 40px 20px;
    text-align: center;
    background: var(--bg-primary);
    transition: all 0.3s ease;
    cursor: pointer;
    position: relative;
}

.file-upload-area:hover {
    border-color: var(--primary-color);
    background: #f8fafc;
}

.file-upload-area.drag-over {
    border-color: var(--primary-color);
    background: #eff6ff;
    border-style: solid;
}

.file-upload-content {
    pointer-events: none;
}

.file-upload-content i {
    font-size: 48px;
    color: var(--text-secondary);
    margin-bottom: 16px;
    display: block;
}

.file-upload-content p {
    margin: 0 0 8px 0;
    color: var(--text-primary);
    font-size: 16px;
}

.file-upload-link {
    color: var(--primary-color);
    text-decoration: underline;
    cursor: pointer;
}

.file-upload-content small {
    color: var(--text-secondary);
    font-size: 14px;
}

/* Estilos para vista previa de archivos adjuntos */
.attachments-preview {
    margin-top: 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.existing-attachments {
    margin-bottom: 16px;
}

.new-attachments {
    margin-bottom: 16px;
}

.existing-title, .new-files-title {
    margin: 0 0 12px 0;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
    padding: 8px 12px;
    background: var(--bg-primary);
    border-radius: 6px 6px 0 0;
    border: 1px solid var(--border-color);
    border-bottom: none;
}

.attachment-item-preview {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px;
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    transition: all 0.2s ease;
    margin-bottom: 8px;
}

.attachment-item-preview:last-child {
    margin-bottom: 0;
}

.attachment-item-preview.existing {
    background: #fef3c7;
    border-color: #fbbf24;
}

.attachment-item-preview.new {
    background: #eff6ff;
    border-color: #3b82f6;
}

.attachment-item-preview:hover {
    border-color: var(--primary-color);
}

.attachment-item-preview.existing:hover {
    border-color: #f59e0b;
}

.attachment-item-preview.new:hover {
    border-color: #1d4ed8;
}

.attachment-preview-content {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
}

.attachment-preview-content i {
    color: var(--primary-color);
    font-size: 20px;
    width: 24px;
    text-align: center;
}

.attachment-info {
    flex: 1;
}

.attachment-name {
    font-weight: 500;
    color: var(--text-primary);
    font-size: 14px;
    margin-bottom: 2px;
    word-break: break-word;
}

.attachment-size {
    font-size: 12px;
    color: var(--text-secondary);
}

.btn-remove-attachment {
    background: var(--danger-color);
    color: white;
    border: none;
    border-radius: 6px;
    width: 32px;
    height: 32px;
    cursor: pointer;
    font-size: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.btn-remove-attachment:hover {
    background: #dc2626;
    transform: scale(1.05);
}

/* Dropdown para archivos adjuntos en tabla */
.attachment-badge-container {
    position: relative;
    display: inline-block;
    cursor: pointer;
}

.attachment-badge {
    transition: all 0.2s ease;
}

.attachment-badge-container:hover .attachment-badge {
    background-color: var(--primary-dark) !important;
    transform: scale(1.05);
}

.attachments-dropdown {
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%);
    background: white;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    min-width: 250px;
    max-width: 300px;
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transform: translateX(-50%) translateY(-10px);
    transition: all 0.2s ease;
}

.attachments-dropdown.show {
    opacity: 1;
    visibility: visible;
    transform: translateX(-50%) translateY(0);
}

.dropdown-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    border-bottom: 1px solid #f0f0f0;
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.dropdown-item:hover {
    background-color: #f8fafc;
}

.dropdown-item:last-child {
    border-bottom: none;
}

.dropdown-item i {
    color: var(--primary-color);
    font-size: 16px;
    width: 20px;
    text-align: center;
}

.file-info {
    flex: 1;
    min-width: 0;
}

.file-name {
    font-weight: 500;
    color: var(--text-primary);
    font-size: 14px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.file-size {
    font-size: 12px;
    color: var(--text-secondary);
    margin-top: 2px;
}

.dropdown-loading,
.dropdown-empty,
.dropdown-error {
    padding: 12px 16px;
    text-align: center;
    color: var(--text-secondary);
    font-size: 14px;
}

.dropdown-error {
    color: var(--danger-color);
}

/* Archivos clickeables en modal de vista */
.attachment-item.clickable {
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
}

.attachment-item.clickable:hover {
    background: #f1f5f9 !important;
    border-color: var(--primary-color) !important;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.attachment-item.clickable .open-icon {
    color: var(--primary-color);
    font-size: 14px;
    opacity: 0;
    transition: opacity 0.2s ease;
}

.attachment-item.clickable:hover .open-icon {
    opacity: 1;
}

/* Responsive para dropdowns */
@media (max-width: 768px) {
    .attachments-dropdown {
        left: 0;
        right: 0;
        transform: none;
        min-width: auto;
        max-width: none;
        margin: 0 10px;
    }
    
    .attachments-dropdown.show {
        transform: none;
    }
}

/* Dropdown de filtros avanzados */
.filter-dropdown-container {
    position: relative;
    display: inline-block;
}

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

.filter-count {
    background: var(--primary-color);
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    font-size: 12px;
    font-weight: bold;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-left: 4px;
}

.filter-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    background: white;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    min-width: 350px;
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.2s ease;
    padding: 20px;
    margin-top: 8px;
}

.filter-dropdown.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.filter-section {
    margin-bottom: 20px;
}

.filter-section:last-of-type {
    margin-bottom: 0;
}

.filter-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 8px;
    font-size: 14px;
}

.filter-label i {
    color: var(--primary-color);
    width: 16px;
}

.date-range-inputs {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}

.filter-actions {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid var(--border-color);
}

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

/* Header de tarjeta mejorado */
.card-subtitle-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 8px;
}

.page-size-selector {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
}

.page-size-selector label {
    color: var(--text-secondary);
    font-weight: 500;
}

.page-size-selector select {
    width: auto;
    min-width: 140px;
    padding: 6px 10px;
    font-size: 14px;
}

/* Información de tabla */
.table-info {
    color: var(--text-secondary);
    font-size: 14px;
}

/* Paginación mejorada */
.btn-pagination {
    background: white;
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    padding: 8px 12px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    margin: 0 2px;
    transition: all 0.2s ease;
    min-width: 40px;
    text-align: center;
}

.btn-pagination:hover {
    border-color: var(--primary-color);
    color: var(--primary-color);
}

.btn-pagination.active {
    background: var(--primary-color);
    border-color: var(--primary-color);
    color: white;
}

.btn-pagination:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Flatpickr personalización */
.flatpickr-input {
    cursor: pointer;
}

.flatpickr-calendar {
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    z-index: 1001 !important; /* Asegurar que esté por encima del dropdown */
}

/* Evitar que el dropdown se cierre al interactuar con flatpickr */
.filter-dropdown .flatpickr-calendar {
    position: fixed !important;
}

.filter-dropdown .date-picker {
    background: white;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    padding: 8px 12px;
    width: 100%;
    font-size: 14px;
    cursor: pointer;
}

.filter-dropdown .date-picker:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.1);
}

/* Responsive para filtros */
@media (max-width: 768px) {
    .filter-dropdown {
        left: 0;
        right: 0;
        min-width: auto;
        max-width: none;
        margin: 0 10px;
    }
    
    .date-range-inputs {
        grid-template-columns: 1fr;
    }
    
    .card-subtitle-container {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
    
    .filter-actions {
        flex-direction: column;
    }
    
    .filter-actions button {
        width: 100%;
    }
}

/* Estilos para validación de campos */
.form-input.error {
    border-color: var(--danger-color) !important;
    background-color: #fef2f2;
    box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.1) !important;
}

.form-input.success {
    border-color: #10b981;
    background-color: #f0fdf4;
    box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.1);
}

.field-error-message {
    color: var(--danger-color);
    font-size: 12px;
    font-weight: 500;
    margin-top: 4px;
    display: flex;
    align-items: center;
    gap: 4px;
    animation: slideIn 0.3s ease;
}

.field-error-message:before {
    content: "⚠";
    font-size: 14px;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Validación específica para líneas de gasto */
.expense-line .form-input.error {
    border-color: var(--danger-color);
    background-color: #fef2f2;
}

.expense-line .form-input.success {
    border-color: #10b981;
    background-color: #f0fdf4;
}

/* Indicadores de campos obligatorios */
.form-label::after {
    content: "";
}

.form-label[for="expenseDate"]::after,
.form-label:has(+ select#team)::after,
.form-label:has(+ select#vendor)::after,
.form-label:has(+ select#bankAccount)::after {
    content: " *";
    color: var(--danger-color);
    font-weight: bold;
}

/* Mejorar apariencia de campos requeridos */
.form-input:required {
    border-left: 3px solid #e5e7eb;
}

.form-input:required:focus {
    border-left-color: var(--primary-color);
}

.form-input.error:required {
    border-left-color: var(--danger-color);
}

.form-input.success:required {
    border-left-color: #10b981;
}

/* Filtros activos */
.active-filters-container {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
}

.active-filter-btn {
    background: var(--primary-color);
    color: white;
    border: 1px solid var(--primary-color);
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.active-filter-btn:hover {
    background: var(--primary-dark);
    border-color: var(--primary-dark);
}

.active-filter-btn .remove-filter {
    background: rgba(255, 255, 255, 0.3);
    color: white;
    border: none;
    border-radius: 50%;
    width: 16px;
    height: 16px;
    cursor: pointer;
    font-size: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s ease;
}

.active-filter-btn .remove-filter:hover {
    background: rgba(255, 255, 255, 0.5);
}
</style> 