<?php
require_once 'config.php';
if (!isLoggedIn()) {
    header('Location: auth/login.php');
    exit;
}
checkSessionTimeout();
$pageTitle = 'Tipos de Pago';
?>
<?php include 'includes/header.php'; ?>
<div class="main-layout">
    <?php include 'includes/sidebar.php'; ?>
    <main class="content">
        <div class="content-header">
            <h1 class="content-title">
                <i class="fas fa-credit-card"></i>
                Tipos de Pago
            </h1>
            <p class="content-subtitle">Catálogo de tipos de pago</p>
        </div>
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: flex-end; align-items: center;">
                <div style="flex: 1;">
                    <input type="text" id="searchInput" class="form-input" placeholder="Buscar tipo de pago..." aria-label="Buscar tipo de pago" style="max-width: 300px;" autocomplete="off">
                </div>
                <div>
                    <button type="button" class="btn btn-primary" onclick="openModal('paymentTypeModal')">
                        <i class="fas fa-plus"></i>
                        Nuevo Tipo de Pago
                    </button>
                </div>
            </div>
            <div class="card-body" style="overflow-x: auto;">
                <table class="data-table" id="paymentTypesTable" style="min-width: 700px;">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Cuenta Bancaria Asociada</th>
                            <th style="vertical-align: middle; text-align: center;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="paymentTypesTableBody">
                        <!-- Las filas se llenarán dinámicamente con JS -->
                    </tbody>
                </table>
                <div id="paymentTypesTableFooter" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0 0 0;">
                    <div id="pageSizeSelectorContainer"></div>
                    <div id="paymentTypesPagination"></div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modal para crear/editar tipo de pago -->
<div class="modal" id="paymentTypeModal" style="display:none;">
    <div class="modal-overlay" onclick="closeModal('paymentTypeModal')"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Nuevo Tipo de Pago</h2>
            <button type="button" class="modal-close" onclick="closeModal('paymentTypeModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="paymentTypeForm" autocomplete="off">
            <div class="modal-body">
                <input type="hidden" name="id" id="paymentTypeId">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                <div class="form-group">
                    <label class="form-label" for="paymentTypeName">Nombre *</label>
                    <input type="text" class="form-input" name="name" id="paymentTypeName" required maxlength="255">
                </div>
                <div class="form-group">
                    <label class="form-label" for="paymentTypeDescription">Descripción</label>
                    <textarea class="form-input" name="description" id="paymentTypeDescription" maxlength="500"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label" for="paymentTypeBankAccount">Cuenta bancaria asociada *</label>
                    <select class="form-input" name="bank_account_id" id="paymentTypeBankAccount" required>
                        <option value="">Seleccione una cuenta...</option>
                        <!-- Opciones dinámicas con JS -->
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('paymentTypeModal')" style="background-color: var(--secondary-color); color: white;">Cancelar</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Guardar Tipo de Pago
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
            <p id="confirmDeleteMessage">¿Está seguro de que desea eliminar este tipo de pago?</p>
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
<script src="assets/js/payment_types.js"></script>
