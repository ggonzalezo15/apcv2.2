<?php
require_once 'config.php';

if (!isLoggedIn() || !checkSessionTimeout()) {
    header('Location: auth/login.php');
    exit;
}

$pageTitle = 'Cuentas Bancarias';
?>
<?php include 'includes/header.php'; ?>

<div class="main-layout">
    <?php include 'includes/sidebar.php'; ?>
    <main class="content">
        <div class="content-header">
            <h1 class="content-title">
                <i class="fas fa-university"></i>
                Cuentas Bancarias
            </h1>
            <p class="content-subtitle">Gestión de cuentas bancarias de la empresa</p>
        </div>
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <div style="flex: 1; display: flex; align-items: center; gap: 12px;">
                    <input
                        type="text"
                        id="searchInput"
                        class="form-input"
                        placeholder="Buscar cuenta por nombre o banco..."
                        aria-label="Buscar cuenta bancaria"
                        style="max-width: 300px;"
                        autocomplete="off"
                    >
                    
                    <!-- Dropdown de filtros de estado -->
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
                                    Estado de la Cuenta
                                </label>
                                <select id="statusFilter" class="form-input">
                                    <option value="">Todas las cuentas</option>
                                    <option value="1">Solo activas</option>
                                    <option value="0">Solo inactivas</option>
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
                    <div id="activeStatusFiltersContainer" class="active-filters-container" style="display: none;">
                        <!-- Se llenarán dinámicamente -->
                    </div>
                </div>
                <div style="display: flex; gap: 12px;">
                    <button type="button" class="btn btn-primary header-btn" onclick="openModal('transferModal')">
                        <i class="fas fa-exchange-alt"></i>
                        Transferencia
                    </button>
                    <button type="button" class="btn btn-primary header-btn" onclick="openModal('bankAccountModal')">
                        <i class="fas fa-plus"></i>
                        Nueva Cuenta
                    </button>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-table"></i>
                    Lista de Cuentas Bancarias
                </h3>
                <p class="card-subtitle">Total: <span id="totalBankAccounts">0</span> cuentas registradas</p>
            </div>
            <div style="overflow-x: auto;">
                <table class="data-table sortable-table" id="bankAccountsTable" style="min-width: 900px;">
                    <thead>
                        <tr>
                            <th class="sortable" data-sort="name">
                                Nombre
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th class="sortable" data-sort="bank_name">
                                Banco
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th class="sortable" data-sort="account_number">
                                Número de Cuenta
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th class="sortable" data-sort="account_type">
                                Tipo
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th class="sortable" data-sort="balance">
                                Saldo
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th>Estado</th>
                            <th style="vertical-align: middle; text-align: center;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="bankAccountsTableBody">
                        <!-- Las filas se llenarán dinámicamente con JS (ahora con columna de estado) -->
                    </tbody>
                </table>
                <div id="bankAccountsTableFooter" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0 0 0;">
                    <div id="pageSizeSelectorContainer"></div>
                    <div id="bankAccountsPagination"></div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modal para crear/editar cuenta bancaria -->
<div class="modal" id="bankAccountModal">
    <div class="modal-overlay" onclick="closeModal('bankAccountModal')"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Nueva Cuenta Bancaria</h2>
            <button type="button" class="modal-close" onclick="closeModal('bankAccountModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="bankAccountForm">
            <div class="modal-body">
                <input type="hidden" id="bankAccountId" name="bankAccountId" value="">
                <div class="form-group">
                    <label class="form-label" for="bankAccountName">Nombre *</label>
                    <input type="text" class="form-input" id="bankAccountName" name="bankAccountName" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="bankName">Banco *</label>
                    <input type="text" class="form-input" id="bankName" name="bankName" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="accountNumber">Número de Cuenta *</label>
                    <input type="text" class="form-input" id="accountNumber" name="accountNumber" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="accountType">Tipo de Cuenta *</label>
                    <select class="form-input" id="accountType" name="accountType" required>
                        <option value="">Seleccionar tipo...</option>
                        <option value="cheque">Cheque</option>
                        <option value="credito">Crédito</option>
                        <option value="ahorro">Ahorro</option>
                        <option value="caja_chica">Caja Chica</option>
                    </select>
                    <!-- Campo de solo lectura para edición -->
                    <input type="text" class="form-input" id="accountTypeReadonly" readonly style="background-color: var(--bg-secondary); display: none;">
                    <small class="form-text" id="accountTypeEditNote" style="color: var(--text-secondary); font-size: 12px; margin-top: 4px; display: none;">
                        <i class="fas fa-lock"></i>
                        El tipo de cuenta no puede ser modificado después de la creación
                    </small>
                </div>
                <div class="form-group">
                    <label class="form-label" for="balance">Saldo Inicial</label>
                    <input type="number" step="0.01" class="form-input credit-balance-input" id="balance" name="balance" value="0.00">
                    <!-- Campo de solo lectura para edición -->
                    <input type="text" class="form-input" id="balanceReadonly" readonly style="background-color: var(--bg-secondary); display: none;">
                    <small class="form-text" id="balanceCreateNote" style="color: var(--text-secondary); font-size: 12px; margin-top: 4px;">
                        <i class="fas fa-info-circle"></i>
                        <span id="balanceHelpText">Para cuentas de crédito, ingrese el límite disponible como positivo</span>
                    </small>
                    <small class="form-text" id="balanceEditNote" style="color: var(--text-secondary); font-size: 12px; margin-top: 4px; display: none;">
                        <i class="fas fa-lock"></i>
                        El balance solo puede ser modificado a través de transacciones
                    </small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Estado de la Cuenta</label>
                    <div style="display: flex; align-items: center; gap: 12px; margin-top: 8px;">
                        <label class="switch">
                            <input type="checkbox" id="accountActive" name="accountActive" checked>
                            <span class="slider"></span>
                        </label>
                        <span id="accountStatusLabel" style="font-weight: 500; color: var(--success-color);">Activa</span>
                    </div>
                    <small class="form-text" style="color: var(--text-secondary); font-size: 12px; margin-top: 4px;">
                        <i class="fas fa-info-circle"></i>
                        Las cuentas inactivas no aparecerán en las listas de selección para transacciones
                    </small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('bankAccountModal')" style="background-color: var(--secondary-color); color: white;">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Guardar Cuenta
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal para transferencias -->
<div class="modal" id="transferModal">
    <div class="modal-overlay" onclick="closeModal('transferModal')"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h2>Transferencia entre Cuentas</h2>
            <button type="button" class="modal-close" onclick="closeModal('transferModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="transferForm">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="fromAccount">Cuenta Origen *</label>
                    <select class="form-input" id="fromAccount" name="fromAccount" required>
                        <option value="">Seleccionar cuenta origen...</option>
                        <!-- Se llenarán dinámicamente (solo cuentas no crédito) -->
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="toAccount">Cuenta Destino *</label>
                    <select class="form-input" id="toAccount" name="toAccount" required>
                        <option value="">Seleccionar cuenta destino...</option>
                        <!-- Se llenarán dinámicamente (solo cuentas no crédito) -->
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="transferAmount">Monto a Transferir *</label>
                    <input type="number" step="0.01" class="form-input" id="transferAmount" name="transferAmount" required min="0.01">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="transferDescription">Descripción</label>
                    <textarea class="form-input" id="transferDescription" name="transferDescription" rows="3" placeholder="Concepto de la transferencia"></textarea>
                </div>
                
                <div style="background: var(--bg-primary); padding: 12px; border-radius: 6px; border-left: 4px solid var(--info-color);">
                    <p style="font-size: 13px; margin: 0; color: var(--text-secondary);">
                        <i class="fas fa-info-circle"></i>
                        <strong>Nota:</strong> No se pueden realizar transferencias desde o hacia cuentas de crédito
                    </p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('transferModal')" style="background-color: var(--secondary-color); color: white;">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-exchange-alt"></i>
                    Realizar Transferencia
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal para pago de crédito -->
<div class="modal" id="creditPaymentModal">
    <div class="modal-overlay" onclick="closeModal('creditPaymentModal')"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h2>Pago a Tarjeta de Crédito</h2>
            <button type="button" class="modal-close" onclick="closeModal('creditPaymentModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="creditPaymentForm">
            <div class="modal-body">
                <input type="hidden" id="creditAccountId" name="creditAccountId">
                <input type="hidden" id="currentCreditBalance" name="currentCreditBalance">
                
                <!-- Sección de Balance Actual -->
                <div class="credit-balance-section">
                    <div class="balance-info">
                        <div class="balance-label">
                            <i class="fas fa-credit-card"></i>
                            Saldo Pendiente de Pago
                        </div>
                        <div class="balance-amount" id="currentBalanceDisplay">$0.00</div>
                        <div class="balance-note">Monto máximo que se puede pagar</div>
                    </div>
                    
                    <!-- Quick Actions para pagos comunes -->
                    <div class="quick-actions">
                        <div class="quick-actions-label">Pagos Rápidos:</div>
                        <div class="quick-actions-buttons">
                            <button type="button" class="quick-amount-btn" onclick="setQuickAmount('25')">25%</button>
                            <button type="button" class="quick-amount-btn" onclick="setQuickAmount('50')">50%</button>
                            <button type="button" class="quick-amount-btn" onclick="setQuickAmount('100')">Total</button>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Cuenta de Crédito</label>
                    <input type="text" class="form-input" id="creditAccountName" readonly style="background-color: var(--bg-primary);">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="paymentFromAccount">Pagar desde *</label>
                    <select class="form-input" id="paymentFromAccount" name="paymentFromAccount" required>
                        <option value="">Seleccionar cuenta...</option>
                        <!-- Se llenarán dinámicamente (solo cuentas no crédito) -->
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="paymentAmount">
                        Monto del Pago *
                        <span class="max-amount-indicator" id="maxAmountIndicator"></span>
                    </label>
                    <input type="number" step="0.01" class="form-input" id="paymentAmount" name="paymentAmount" required min="0.01">
                    <div class="payment-amount-feedback" id="paymentAmountFeedback"></div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="paymentDescription">Descripción</label>
                    <textarea class="form-input" id="paymentDescription" name="paymentDescription" rows="3" placeholder="Concepto del pago"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('creditPaymentModal')" style="background-color: var(--secondary-color); color: white;">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-credit-card"></i>
                    Realizar Pago
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
            <p id="confirmDeleteMessage">¿Está seguro de que desea eliminar esta cuenta bancaria?</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn" onclick="closeModal('confirmDeleteModal')">Cancelar</button>
            <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Eliminar</button>
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
                        ¿Está seguro de que desea cambiar el estado de esta cuenta bancaria?
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

<!-- Modal de conflicto con payment types -->
<div class="modal" id="paymentTypesConflictModal" style="display:none;">
    <div class="modal-overlay" onclick="closePaymentTypesConflictModal()"></div>
    <div class="modal-content" style="max-width: 520px;">
        <div class="modal-header">
            <h2>No se puede desactivar la cuenta</h2>
            <button type="button" class="modal-close" onclick="closePaymentTypesConflictModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div style="display: flex; align-items: flex-start; gap: 16px; margin-bottom: 20px;">
                <div style="font-size: 48px; color: var(--danger-color); flex-shrink: 0;">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div>
                    <p style="margin: 0 0 12px 0; font-size: 16px; line-height: 1.4;">
                        <strong id="conflictAccountName"></strong> no puede ser desactivada porque tiene tipos de pago asociados que la están utilizando.
                    </p>
                    <p style="margin: 0 0 16px 0; font-size: 14px; color: var(--text-secondary);">
                        Tipos de pago que usan esta cuenta:
                    </p>
                    <div id="paymentTypesList" style="background: var(--bg-secondary); padding: 12px; border-radius: 6px; border-left: 4px solid var(--warning-color);">
                        <!-- Lista de payment types se llenará dinámicamente -->
                    </div>
                </div>
            </div>
            
            <div style="background: var(--bg-primary); padding: 16px; border-radius: 8px; border-left: 4px solid var(--info-color);">
                <h4 style="margin: 0 0 8px 0; color: var(--info-color); font-size: 14px;">
                    <i class="fas fa-lightbulb"></i> ¿Qué puedes hacer?
                </h4>
                <ul style="margin: 0; padding-left: 20px; font-size: 13px; color: var(--text-secondary);">
                    <li>Cambiar los tipos de pago para que usen otra cuenta bancaria activa</li>
                    <li>O eliminar los tipos de pago si ya no los necesitas</li>
                    <li>Luego podrás desactivar esta cuenta sin problemas</li>
                </ul>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn" onclick="closePaymentTypesConflictModal()" style="background-color: var(--secondary-color); color: white;">
                Entendido
            </button>
            <button type="button" class="btn btn-primary" onclick="openPaymentTypesSettings()">
                <i class="fas fa-cog"></i>
                Ir a Tipos de Pago
            </button>
        </div>
    </div>
</div>

<!-- Toast de notificación -->
<div id="toast" style="position: fixed; bottom: 32px; right: 32px; min-width: 220px; z-index: 9999; display: none; background: var(--primary-color, #2563eb); color: #fff; padding: 16px 24px; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,0.12); font-size: 16px; font-weight: 500; align-items: center; gap: 8px;">
    <span id="toastIcon" style="margin-right: 8px;"></span>
    <span id="toastMessage"></span>
</div>

<!-- Estilos específicos para cuentas bancarias -->
<style>
/* Estilos para balances de crédito en rojo */
.credit-balance {
    color: var(--danger-color) !important;
    font-weight: 600;
}

.credit-balance::before {
    content: "- $";
}

.positive-balance {
    color: var(--success-color);
    font-weight: 600;
}

.positive-balance::before {
    content: "$";
}

.zero-balance {
    color: var(--text-secondary);
}

.zero-balance::before {
    content: "$";
}

/* Badge para tipos de cuenta */
.account-type-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
    text-transform: uppercase;
}

.account-type-cheque {
    background-color: rgb(99 102 241 / 0.1);
    color: rgb(99 102 241);
}

.account-type-credito {
    background-color: rgb(6 182 212 / 0.1);
    color: rgb(6 182 212);
}

.account-type-ahorro {
    background-color: rgb(5 150 105 / 0.1);
    color: var(--success-color);
}

.account-type-caja_chica {
    background-color: rgb(59 130 246 / 0.1);
    color: rgb(59 130 246);
}

/* Estilos para el manejo de cuentas de crédito */
.credit-balance-input {
    transition: all 0.3s ease;
}

.credit-balance-input.converting {
    border-color: var(--warning-color, #f59e0b) !important;
    background-color: rgba(245, 158, 11, 0.1) !important;
    box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.2);
}

#balanceHelpText {
    transition: color 0.3s ease;
}

/* Switch toggle para estado activo/inactivo */
.switch {
    position: relative;
    display: inline-block;
    width: 48px;
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
    background-color: var(--secondary-color, #6b7280);
    transition: 0.3s;
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
    transition: 0.3s;
    border-radius: 50%;
}

input:checked + .slider {
    background-color: var(--success-color, #10b981);
}

input:checked + .slider:before {
    transform: translateX(24px);
}

/* Badges de estado */
.status-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
    text-transform: uppercase;
}

.status-active {
    background-color: rgb(5 150 105 / 0.1);
    color: var(--success-color);
}

.status-inactive {
    background-color: rgb(220 38 38 / 0.1);
    color: var(--danger-color);
}

/* Dropdown de filtros de estado */
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
    min-width: 300px;
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

.filter-actions {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid var(--border-color);
}

.active-filters-container {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
}

.active-filter-btn {
    background: var(--primary-color);
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    display: flex;
    align-items: center;
    gap: 4px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.active-filter-btn:hover {
    background: var(--primary-dark, #1d4ed8);
}

.active-filter-btn i {
    font-size: 10px;
}

/* Estilos de paginación para cuentas bancarias */
#bankAccountsPagination {
    display: flex;
    gap: 8px;
    align-items: center;
}

.pagination-number {
    min-width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--border-color);
    background: var(--bg-primary);
    color: var(--text-primary);
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.2s ease;
    text-decoration: none;
    padding: 0 8px;
}

.pagination-number:hover {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

.pagination-number.active {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

.pagination-number:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    background: var(--bg-secondary);
}

.pagination-ellipsis {
    padding: 0 8px;
    color: var(--text-secondary);
    font-size: 14px;
}

/* Estilo para enlaces de nombre de cuenta - consistente con incomes y expenses */
.account-name-link {
    color: var(--primary-color, #2563eb);
    cursor: pointer;
    font-weight: 600;
    text-decoration: none;
    border-bottom: 1px solid transparent;
    transition: all 0.2s ease;
    padding: 2px 4px;
    border-radius: 4px;
}

.account-name-link:hover {
    color: var(--primary-dark, #1d4ed8);
    background-color: rgba(37, 99, 235, 0.1);
    border-bottom-color: var(--primary-color, #2563eb);
    text-decoration: none;
}

.account-name-link:active {
    transform: translateY(1px);
    background-color: rgba(37, 99, 235, 0.2);
}

.account-name-link:visited {
    color: var(--primary-color);
}

/* Estilos para botones del header - ancho automático */
.header-btn {
    width: auto !important;
    white-space: nowrap;
    min-width: auto;
}

/* Estilos para el modal de pago a crédito */
.credit-balance-section {
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 24px;
}

.balance-info {
    text-align: center;
    margin-bottom: 20px;
}

.balance-label {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: 8px;
}

.balance-amount {
    font-size: 32px;
    font-weight: 700;
    color: var(--danger-color);
    margin-bottom: 4px;
}

.balance-note {
    font-size: 12px;
    color: var(--text-secondary);
    font-style: italic;
}

.quick-actions {
    border-top: 1px solid #e2e8f0;
    padding-top: 16px;
}

.quick-actions-label {
    font-size: 13px;
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: 12px;
    text-align: center;
}

.quick-actions-buttons {
    display: flex;
    gap: 8px;
    justify-content: center;
}

.quick-amount-btn {
    padding: 8px 16px;
    border: 1px solid var(--primary-color);
    background: white;
    color: var(--primary-color);
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    min-width: 60px;
}

.quick-amount-btn:hover {
    background: var(--primary-color);
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(37, 99, 235, 0.3);
}

.max-amount-indicator {
    font-size: 11px;
    color: var(--text-secondary);
    font-weight: normal;
    margin-left: 8px;
}

.payment-amount-feedback {
    margin-top: 4px;
    font-size: 12px;
    min-height: 16px;
}

.payment-amount-feedback.valid {
    color: var(--success-color);
}

.payment-amount-feedback.invalid {
    color: var(--danger-color);
}

.payment-amount-feedback.warning {
    color: #f59e0b;
}

/* Responsive para paginación */
@media (max-width: 768px) {
    #bankAccountsTableFooter {
        flex-direction: column;
        align-items: center;
        gap: 12px;
    }
    
    #bankAccountsPagination {
        flex-wrap: wrap;
        justify-content: center;
        gap: 4px;
    }
    
    .pagination-number {
        min-width: 28px;
        height: 28px;
        font-size: 12px;
        padding: 0 6px;
    }
    
    #pageSizeSelectorContainer {
        text-align: center;
    }
    
    #pageSizeSelectorContainer select {
        min-width: 100px;
        font-size: 12px;
    }
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
</style>

<?php include 'includes/footer.php'; ?>

<script src="assets/js/bank_accounts.js"></script>
