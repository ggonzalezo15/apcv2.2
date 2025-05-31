<?php
require_once 'config.php';

if (!isLoggedIn() || !checkSessionTimeout()) {
    header('Location: login.php');
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
                <div style="flex: 1;">
                    <input
                        type="text"
                        id="searchInput"
                        class="form-input"
                        placeholder="Buscar cuenta por nombre o banco..."
                        aria-label="Buscar cuenta bancaria"
                        style="max-width: 300px;"
                        autocomplete="off"
                    >
                </div>
                <div style="display: flex; gap: 12px;">
                    <button type="button" class="btn btn-primary" onclick="openModal('transferModal')">
                        <i class="fas fa-exchange-alt"></i>
                        Transferencia
                    </button>
                    <button type="button" class="btn btn-primary" onclick="openModal('bankAccountModal')">
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
                <table class="data-table" id="bankAccountsTable" style="min-width: 900px;">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Banco</th>
                            <th>Número de Cuenta</th>
                            <th>Tipo</th>
                            <th>Saldo</th>
                            <th style="vertical-align: middle; text-align: center;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="bankAccountsTableBody">
                        <!-- Las filas se llenarán dinámicamente con JS -->
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
                
                <div class="form-group">
                    <label class="form-label">Cuenta de Crédito</label>
                    <input type="text" class="form-input" id="creditAccountName" readonly style="background-color: var(--bg-secondary);">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="paymentFromAccount">Pagar desde *</label>
                    <select class="form-input" id="paymentFromAccount" name="paymentFromAccount" required>
                        <option value="">Seleccionar cuenta...</option>
                        <!-- Se llenarán dinámicamente (solo cuentas no crédito) -->
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="paymentAmount">Monto del Pago *</label>
                    <input type="number" step="0.01" class="form-input" id="paymentAmount" name="paymentAmount" required min="0.01">
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
    background-color: rgb(37 99 235 / 0.1);
    color: var(--primary-color);
}

.account-type-credito {
    background-color: rgb(220 38 38 / 0.1);
    color: var(--danger-color);
}

.account-type-ahorro {
    background-color: rgb(5 150 105 / 0.1);
    color: var(--success-color);
}

.account-type-caja_chica {
    background-color: rgb(217 119 6 / 0.1);
    color: var(--warning-color);
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
</style>

<?php include 'includes/footer.php'; ?>

<script src="assets/js/bank_accounts.js"></script>
