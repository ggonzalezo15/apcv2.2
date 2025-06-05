<?php
require_once 'config.php';

if (!isLoggedIn() || !checkSessionTimeout()) {
    header('Location: login.php');
    exit;
}

$pageTitle = 'Gestión de Proveedores';
?>
<?php include 'includes/header.php'; ?>

<div class="main-layout">
    <?php include 'includes/sidebar.php'; ?>
    <main class="content">
        <div class="content-header">
            <h1 class="content-title">
                <i class="fas fa-truck"></i>
                Gestión de Proveedores
            </h1>
            <p class="content-subtitle">Administración de proveedores externos</p>
        </div>
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: flex-end; align-items: center;">
                <div style="flex: 1;">
                    <input
                        type="text"
                        id="searchInput"
                        class="form-input"
                        placeholder="Buscar proveedor por nombre..."
                        aria-label="Buscar proveedor"
                        style="max-width: 300px;"
                        autocomplete="off"
                    >
                </div>
                <div>
                    <button type="button" class="btn btn-primary" onclick="openModal('vendorModal')">
                        <i class="fas fa-plus"></i>
                        Nuevo Proveedor
                    </button>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-table"></i>
                    Lista de Proveedores
                </h3>
                <p class="card-subtitle">Total: <span id="totalVendors">0</span> proveedores registrados</p>
            </div>
            <div style="overflow-x: auto;">
                <table class="data-table" id="vendorsTable" style="min-width: 700px;">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Teléfono</th>
                            <th>Dirección</th>
                            <th style="vertical-align: middle; text-align: center;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="vendorsTableBody">
                        <!-- Las filas se llenarán dinámicamente con JS -->
                    </tbody>
                </table>
                <div id="vendorsTableFooter" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0 0 0;">
                    <div id="pageSizeSelectorContainer"></div>
                    <div id="vendorsPagination"></div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modal para crear/editar proveedor -->
<div class="modal" id="vendorModal">
    <div class="modal-overlay" onclick="closeModal('vendorModal')"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Nuevo Proveedor</h2>
            <button type="button" class="modal-close" onclick="closeModal('vendorModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="vendorForm">
            <div class="modal-body">
                <input type="hidden" id="vendorId" name="vendorId" value="">
                <div class="form-group">
                    <label class="form-label" for="vendorName">Nombre *</label>
                    <input type="text" class="form-input" id="vendorName" name="vendorName" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="vendorEmail">Email</label>
                    <input type="email" class="form-input" id="vendorEmail" name="vendorEmail">
                </div>
                <div class="form-group">
                    <label class="form-label" for="vendorPhone">Teléfono</label>
                    <input type="text" class="form-input" id="vendorPhone" name="vendorPhone">
                </div>
                <div class="form-group">
                    <label class="form-label" for="vendorAddress">Dirección</label>
                    <textarea class="form-input" id="vendorAddress" name="vendorAddress" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('vendorModal')" style="background-color: var(--secondary-color); color: white;">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Guardar Proveedor
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
            <p id="confirmDeleteMessage">¿Está seguro de que desea eliminar este proveedor?</p>
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

<script src="assets/js/vendors.js"></script>
