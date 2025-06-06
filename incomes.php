<?php
require_once 'config.php';

if (!isLoggedIn() || !checkSessionTimeout()) {
    header('Location: login.php');
    exit;
}

$pageTitle = 'Gestión de Ingresos';
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
                <i class="fas fa-chart-line"></i>
                Gestión de Ingresos
            </h1>
            <p class="content-subtitle">Administración de ingresos y facturación</p>
        </div>
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap;">
                <div style="flex: 1; display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                    <input
                        type="text"
                        id="searchInput"
                        class="form-input"
                        placeholder="Buscar ingreso..."
                        aria-label="Buscar ingreso"
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
                                    Estado
                                </label>
                                <select id="statusFilter" class="form-input">
                                    <option value="">Todos los estados</option>
                                    <option value="pending">Pendiente</option>
                                    <option value="paid">Pagado</option>
                                    <option value="overpaid">Sobrepago</option>
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
                    <button type="button" class="btn btn-primary" onclick="resetIncomeForm(); openModal('incomeModal')">
                        <i class="fas fa-plus"></i>
                        Nuevo Ingreso
                    </button>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-table"></i>
                    Lista de Ingresos
                </h3>
                <p class="card-subtitle">Total: <span id="totalIncomes">0</span> ingresos registrados</p>
            </div>
            <div style="overflow-x: auto;">
                <table class="data-table sortable-table" id="incomesTable" style="min-width: 1200px;">
                    <thead>
                        <tr>
                            <th class="sortable" data-sort="invoice_number">
                                Nº Factura
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th class="sortable" data-sort="date">
                                Fecha
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th class="sortable" data-sort="team_name">
                                Equipo
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th class="sortable" data-sort="contractors">
                                Contratistas
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th class="sortable" data-sort="balance" style="text-align: center;">
                                Balance
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th class="sortable" data-sort="total_fees" style="text-align: center;">
                                Fee
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th class="sortable" data-sort="status" style="text-align: center;">
                                Status
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th style="vertical-align: middle; text-align: center; ">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="incomesTableBody">
                        <!-- Las filas se llenarán dinámicamente con JS -->
                    </tbody>
                </table>
                <div id="incomesTableFooter" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0 0 0;">
                    <div id="pageSizeSelectorContainer"></div>
                    <div id="incomesPagination"></div>
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

<!-- Modal para crear/editar ingreso -->
<div class="modal" id="incomeModal">
    <div class="modal-overlay" onclick="closeModal('incomeModal')"></div>
    <div class="modal-content" style="max-width: 900px; max-height: 90vh; overflow-y: auto;">
        <div class="modal-header">
            <h2 id="modalTitle">Nuevo Ingreso</h2>
            <button type="button" class="modal-close" onclick="closeModal('incomeModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="incomeForm">
            <div class="modal-body">
                <input type="hidden" id="incomeId" name="incomeId" value="">
                
                <!-- Información general -->
                <div class="form-section">
                    <h3 class="form-section-title">
                        <i class="fas fa-info-circle"></i>
                        Información General
                    </h3>
                    <div class="form-row-three">
                        <div class="form-group">
                            <label class="form-label" for="invoiceNumber">Número de Factura *</label>
                            <input type="text" class="form-input" id="invoiceNumber" required placeholder="Ingrese el número de factura...">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="incomeDate">Fecha *</label>
                            <input type="text" class="form-input" id="incomeDate" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Equipo *</label>
                            <select id="team" class="form-input" required>
                                <option value="">Seleccionar equipo...</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Contratistas -->
                <div class="form-section">
                    <h3 class="form-section-title">
                        <i class="fas fa-users"></i>
                        Contratistas
                    </h3>
                    <div class="form-group">
                        <div class="multi-select-container" id="contractorsContainer">
                            <div class="multi-select-input" id="contractorsInput">
                                <div class="selected-items" id="selectedContractors">
                                    <!-- Items seleccionados aparecerán aquí -->
                                </div>
                                <input type="text" class="filter-input" id="contractorFilter" placeholder="Buscar contratistas..." autocomplete="off">
                                <i class="fas fa-chevron-down select-arrow"></i>
                            </div>
                            <div class="multi-select-dropdown" id="contractorsDropdown">
                                <div class="dropdown-options" id="contractorOptions">
                                    <!-- Opciones aparecerán aquí -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Líneas de ingreso -->
                <div class="modal-section">
                    <div class="section-header">
                        <div class="section-title">
                            <i class="fas fa-list"></i>
                            <h3>Líneas de Ingreso</h3>
                        </div>
                        <button type="button" onclick="addIncomeLine()" class="btn-add-line">
                            <i class="fas fa-plus"></i> Agregar Línea
                        </button>
                    </div>
                    
                    <div class="income-lines-wrapper">
                        <div class="income-lines-header">
                            <div class="header-cell type-header">Tipo de Trabajo *</div>
                            <div class="header-cell units-header">Unidades</div>
                            <div class="header-cell price-header">Precio Unit.</div>
                            <div class="header-cell total-header">Total</div>
                            <div class="header-cell actions-header">Acción</div>
                        </div>
                        
                        <div id="income-lines" class="income-lines-container">
                            <!-- Las líneas se agregan dinámicamente -->
                        </div>
                        
                        <div class="income-lines-footer">
                            <div class="total-section">
                                <div class="total-label">Total Ingresos:</div>
                                <div id="totalIncomeAmount" class="total-amount">$0.00</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pagos -->
                <div class="modal-section">
                    <div class="section-header">
                        <div class="section-title">
                            <i class="fas fa-credit-card"></i>
                            <h3>Pagos</h3>
                        </div>
                        <button type="button" onclick="addPaymentLine()" class="btn-add-line">
                            <i class="fas fa-plus"></i> Agregar Pago
                        </button>
                    </div>
                    
                    <div class="payments-wrapper">
                        <div class="payments-header">
                            <div class="header-cell type-header">Tipo de Pago *</div>
                            <div class="header-cell amount-header">Monto</div>
                            <div class="header-cell fee-header">Fee</div>
                            <div class="header-cell net-header">Neto</div>
                            <div class="header-cell actions-header">Acción</div>
                        </div>
                        
                        <div id="payments-list" class="payments-container">
                            <!-- Los pagos se agregan dinámicamente -->
                        </div>
                        
                        <div class="payments-footer">
                            <div class="total-section">
                                <div class="total-label">Total Pagos:</div>
                                <div id="totalPaymentsAmount" class="total-amount">$0.00</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Balance -->
                <div class="balance-section">
                    <h3>Balance: <span id="balanceAmount">$0.00</span></h3>
                </div>

                <!-- Notas -->
                <div class="form-section">
                    <h3 class="form-section-title">
                        <i class="fas fa-sticky-note"></i>
                        Notas
                    </h3>
                    <div class="form-group">
                        <label class="form-label">Notas generales</label>
                        <textarea id="generalNote" class="form-input" rows="3" placeholder="Notas generales..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('incomeModal')">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Guardar
                </button>
            </div>
        </form>
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
            <p id="confirmDeleteMessage">¿Está seguro de que desea eliminar este ingreso?</p>
            <input type="hidden" id="deleteIncomeId">
        </div>
        <div class="modal-footer">
            <button type="button" onclick="closeModal('deleteModal')" class="btn btn-secondary">Cancelar</button>
            <button type="button" onclick="deleteIncomeConfirmed()" class="btn btn-danger">Eliminar</button>
        </div>
    </div>
</div>

<script src="assets/js/incomes-simple.js"></script>

<style>
/* Estilos para el modal - usando estilos globales consistentes */
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

.form-row-three {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 16px;
}

.form-help-text {
    display: block;
    margin-top: 6px;
    font-size: 12px;
    color: var(--text-secondary);
    font-style: italic;
}

/* Multi-select personalizado */
.multi-select-container {
    position: relative;
    width: 100%;
}

.multi-select-input {
    display: flex;
    align-items: center;
    min-height: 42px;
    padding: 8px 40px 8px 12px;
    border: 2px solid var(--border-color);
    border-radius: 8px;
    background: var(--bg-secondary);
    cursor: pointer;
    transition: all 0.2s ease;
    flex-wrap: wrap;
    gap: 6px;
}

.multi-select-input:not(.has-selections) {
    justify-content: center;
}

.multi-select-input:hover {
    border-color: var(--primary-color);
}

.multi-select-input.active {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.selected-items {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    flex: 1;
}

/* Cuando no hay selecciones, ocultar el contenedor de items */
.multi-select-input:not(.has-selections) .selected-items {
    display: none;
}

.selected-item {
    display: flex;
    align-items: center;
    background: var(--primary-color);
    color: white;
    padding: 4px 8px;
    border-radius: 16px;
    font-size: 12px;
    font-weight: 500;
    gap: 6px;
}

.selected-item .remove-btn {
    background: none;
    border: none;
    color: white;
    cursor: pointer;
    padding: 0;
    width: 16px;
    height: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    font-size: 10px;
    transition: background 0.2s ease;
}

.selected-item .remove-btn:hover {
    background: rgba(255, 255, 255, 0.2);
}

.filter-input {
    border: none;
    outline: none;
    background: transparent;
    flex: 1;
    min-width: 120px;
    font-size: 14px;
    color: var(--text-primary);
}

.filter-input::placeholder {
    color: var(--text-secondary);
}

/* Centrar placeholder cuando no hay selecciones */
.multi-select-input:not(.has-selections) .filter-input {
    text-align: center;
    width: 100%;
}

.multi-select-input:not(.has-selections) .filter-input::placeholder {
    text-align: center;
}

/* Alinear a la izquierda cuando hay selecciones o está enfocado */
.multi-select-input.has-selections .filter-input,
.filter-input:focus {
    text-align: left;
}

/* Mostrar el contenedor de items cuando el input está enfocado */
.multi-select-input:focus-within .selected-items {
    display: flex !important;
}

.select-arrow {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-secondary);
    transition: transform 0.2s ease;
    pointer-events: none;
}

.multi-select-input.active .select-arrow {
    transform: translateY(-50%) rotate(180deg);
}

.multi-select-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    z-index: 1000;
    max-height: 200px;
    overflow-y: auto;
    margin-top: 4px;
    display: none;
}

.multi-select-dropdown.show {
    display: block;
}

.dropdown-options {
    padding: 8px 0;
}

.dropdown-option {
    display: flex;
    align-items: center;
    padding: 10px 16px;
    cursor: pointer;
    transition: background 0.2s ease;
    font-size: 14px;
    color: var(--text-primary);
}

.dropdown-option:hover {
    background: var(--bg-primary);
}

.dropdown-option.selected {
    background: var(--primary-color);
    color: white;
}

.dropdown-option.hidden {
    display: none;
}

.no-results {
    padding: 16px;
    text-align: center;
    color: var(--text-secondary);
    font-style: italic;
}

.modal-section {
    margin-bottom: 24px;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 20px;
}

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

.total-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 16px;
    padding: 16px;
    background: var(--bg-primary);
    border-radius: 6px;
    border: 1px solid var(--border-color);
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

.balance-section {
    margin: 24px 0;
    padding: 20px;
    background: var(--bg-primary);
    border: 2px solid var(--success-color);
    border-radius: 8px;
    text-align: center;
}

.balance-section h3 {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
    color: var(--text-primary);
}

.balance-section #balanceAmount {
    color: var(--success-color);
    font-weight: 700;
}

/* Estilos para líneas de ingreso */
.income-lines-wrapper, .payments-wrapper {
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    overflow: hidden;
}

.income-lines-header, .payments-header {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr 80px;
    gap: 12px;
    background: var(--bg-primary);
    padding: 16px;
    border-bottom: 1px solid var(--border-color);
    font-weight: 600;
    font-size: 14px;
    color: var(--text-secondary);
}

.payments-header {
    grid-template-columns: 2fr 1fr 1fr 1fr 80px;
}

.header-cell {
    display: flex;
    align-items: center;
}

.income-lines-container, .payments-container {
    max-height: 400px;
    overflow-y: auto;
}

.income-line, .payment-line {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr 80px;
    gap: 12px;
    align-items: center;
    padding: 16px;
    border-bottom: 1px solid var(--border-color);
    background: var(--bg-secondary);
    transition: all 0.2s ease;
}

.payment-line {
    grid-template-columns: 2fr 1fr 1fr 1fr 80px;
}

.income-line:hover, .payment-line:hover {
    background: var(--bg-primary);
}

.income-line:last-child, .payment-line:last-child {
    border-bottom: none;
}

.income-line .form-input, .payment-line .form-input {
    margin: 0;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    padding: 8px 12px;
    font-size: 14px;
    transition: all 0.2s ease;
}

.income-line .form-input:focus, .payment-line .form-input:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.1);
}

.income-line .line-job-type, .payment-line .payment-type {
    font-weight: 500;
}

.income-line .line-total, .payment-line .payment-net {
    text-align: right;
    background: var(--bg-primary);
    font-weight: 600;
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

.income-lines-footer, .payments-footer {
    background: var(--bg-primary);
    padding: 16px;
    border-top: 1px solid var(--border-color);
}

@media (max-width: 768px) {
    .form-row, .form-row-three {
        grid-template-columns: 1fr;
    }
    
    .section-header {
        flex-direction: column;
        gap: 12px;
        align-items: stretch;
    }
    
    .btn-add-line {
        width: 100%;
        justify-content: center;
    }
    
    .total-section {
        flex-direction: column;
        gap: 8px;
        text-align: center;
    }
    
    .income-lines-header, .payments-header {
        display: none;
    }
    
    .income-line, .payment-line {
        grid-template-columns: 1fr;
        gap: 8px;
    }
    
    .income-line, .payment-line {
        background: var(--bg-secondary);
        border-radius: 6px;
        margin-bottom: 12px;
        padding: 16px;
        border: 1px solid var(--border-color);
    }
    
    .income-line .form-input, .payment-line .form-input {
        width: 100%;
        margin-bottom: 8px;
    }
    
    .btn-remove-line {
        width: 100%;
        height: 40px;
        border-radius: 6px;
    }
}

/* Estilos para el sistema de filtros avanzado */
.filter-dropdown-container {
    position: relative;
}

.filter-count {
    background: var(--primary-color, #2563eb);
    color: white;
    border-radius: 50%;
    padding: 2px 6px;
    font-size: 11px;
    font-weight: 600;
    margin-left: 8px;
    min-width: 18px;
    height: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: pulse 0.5s ease-in-out;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.2); }
    100% { transform: scale(1); }
}

.filter-dropdown {
    display: none;
    position: absolute;
    top: 100%;
    left: 0;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
    z-index: 1000;
    min-width: 360px;
    padding: 24px;
    margin-top: 8px;
    overflow: visible;
}

.filter-dropdown.show {
    display: block;
}

.filter-section {
    margin-bottom: 20px;
}

.filter-section:last-of-type {
    margin-bottom: 24px;
}

.filter-label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #374151;
    font-size: 14px;
}

.date-range-inputs {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    overflow: visible;
}

.filter-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    border-top: 1px solid #f3f4f6;
    padding-top: 20px;
}

.filter-actions button {
    min-width: 100px;
}

/* Filtros activos */
.active-filters-container {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
}

.active-filter-btn {
    background: var(--primary-color, #2563eb);
    color: white;
    border: 1px solid var(--primary-color, #2563eb);
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    animation: slideIn 0.3s ease;
}

.active-filter-btn:hover {
    background: var(--primary-dark, #1d4ed8);
    border-color: var(--primary-dark, #1d4ed8);
    transform: translateY(-1px);
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
    padding: 0;
}

.active-filter-btn .remove-filter:hover {
    background: rgba(255, 255, 255, 0.5);
    transform: scale(1.1);
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(-10px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

/* Mejorar el botón de filtros */
#filterDropdownBtn {
    position: relative;
    transition: all 0.2s ease;
}

#filterDropdownBtn.active {
    background: var(--primary-color, #2563eb);
    color: white;
    border-color: var(--primary-color, #2563eb);
}

#filterDropdownBtn .fa-chevron-down {
    transition: transform 0.2s ease;
}

/* Flatpickr personalización */
.flatpickr-input {
    cursor: pointer !important;
    background: white !important;
}

.flatpickr-calendar {
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    z-index: 9999 !important;
}

.filter-dropdown .date-picker {
    background: white !important;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    padding: 8px 12px;
    width: 100%;
    font-size: 14px;
    cursor: pointer !important;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
    pointer-events: auto !important;
}

.filter-dropdown .date-picker:focus {
    outline: none;
    border-color: var(--primary-color, #2563eb);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.filter-dropdown .date-picker:hover {
    border-color: var(--primary-color, #2563eb);
}

/* Distribución optimizada del espacio horizontal */
#incomesTable {
    table-layout: fixed;
    width: 100%;
    border-collapse: collapse;
}

#incomesTable th, #incomesTable td {
    overflow: hidden;
    text-overflow: ellipsis;
    padding: 12px 12px;
    vertical-align: middle;
}

/* Distribución equilibrada sin espacios excesivos */
#incomesTable th:nth-child(1), #incomesTable td:nth-child(1) { width: 10%; }  /* Nº Factura */
#incomesTable th:nth-child(2), #incomesTable td:nth-child(2) { width: 10%; }  /* Fecha */
#incomesTable th:nth-child(3), #incomesTable td:nth-child(3) { width: 12%; }  /* Equipo */
#incomesTable th:nth-child(4), #incomesTable td:nth-child(4) { width: 10%; }  /* Contratistas */
#incomesTable th:nth-child(5), #incomesTable td:nth-child(5) { width: 10%; }  /* Balance */
#incomesTable th:nth-child(6), #incomesTable td:nth-child(6) { width: 10%; }  /* Fee */
#incomesTable th:nth-child(7), #incomesTable td:nth-child(7) { width: 10%; }  /* Status */
#incomesTable th:nth-child(8), #incomesTable td:nth-child(8) { width: 12%; }  /* Acciones */

#incomesTable td:nth-child(4) {
    white-space: nowrap;
    max-width: 0; /* Forzar el ancho fijo */
}

/* Badges para status */
.badge {
    display: inline-block;
    padding: 4px 8px;
    font-size: 12px;
    font-weight: 600;
    text-align: center;
    white-space: nowrap;
    border-radius: 12px;
    min-width: 60px;
}

.badge-success {
    background-color: #10b981;
    color: white;
}

.badge-danger {
    background-color: #ef4444;
    color: white;
}

.badge-warning {
    background-color: #f59e0b;
    color: white;
}

.badge-secondary {
    background-color: #6b7280;
    color: white;
}

/* Paginación */
.pagination-btn {
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

.pagination-btn:hover {
    border-color: var(--primary-color);
    color: var(--primary-color);
}

.pagination-btn.active {
    background: var(--primary-color);
    border-color: var(--primary-color);
    color: white;
}

.pagination-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.pagination-ellipsis {
    padding: 8px 4px;
    color: var(--text-secondary);
    font-size: 14px;
}

/* Estilos para ordenamiento de tabla */
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

/* Responsive */
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
    
    .filter-actions {
        flex-direction: column;
    }
    
    .filter-actions button {
        width: 100%;
    }
    
    .active-filters-container {
        width: 100%;
        justify-content: flex-start;
    }
}
</style>

<!-- Estilos para Toast notifications -->
<style>
.toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 10000;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.toast {
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-width: 300px;
    max-width: 400px;
    padding: 12px 16px;
    border-radius: 8px;
    color: white;
    font-weight: 500;
    opacity: 0;
    transform: translateX(100%);
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    animation: slideInToast 0.3s ease forwards;
}

@keyframes slideInToast {
    from {
        opacity: 0;
        transform: translateX(100%);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.toast-content {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
}

.toast-content i {
    font-size: 16px;
}

.toast-message {
    font-size: 14px;
    line-height: 1.4;
}

.toast-close {
    background: none;
    border: none;
    color: white;
    cursor: pointer;
    padding: 4px;
    margin-left: 12px;
    border-radius: 4px;
    opacity: 0.8;
    transition: all 0.2s ease;
}

.toast-close:hover {
    opacity: 1;
    background: rgba(255, 255, 255, 0.1);
}

.toast-success {
    background: linear-gradient(135deg, #10b981, #059669);
}

.toast-error {
    background: linear-gradient(135deg, #ef4444, #dc2626);
}

.toast-info {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
}

.toast-warning {
    background: linear-gradient(135deg, #f59e0b, #d97706);
}

/* Responsive para móviles */
@media (max-width: 768px) {
    .toast-container {
        left: 10px;
        right: 10px;
        top: 10px;
    }
    
    .toast {
        min-width: auto;
        max-width: none;
    }
}
</style>

<?php include 'includes/footer.php'; ?> 