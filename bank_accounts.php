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
            <div class="card-header" style="display: flex; justify-content: flex-end; align-items: center;">
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
                <div>
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
                        <option value="checking">Cheques</option>
                        <option value="savings">Ahorros</option>
                        <option value="business">Empresarial</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="balance">Saldo Inicial</label>
                    <input type="number" step="0.01" class="form-input" id="balance" name="balance" value="0.00">
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

<?php include 'includes/footer.php'; ?>

<script src="assets/js/bank_accounts.js"></script>
