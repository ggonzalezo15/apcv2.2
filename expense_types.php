<?php
require_once 'config.php';

if (!isLoggedIn() || !checkSessionTimeout()) {
    header('Location: login.php');
    exit;
}

$pageTitle = 'Tipos de Gastos';
?>
<?php include 'includes/header.php'; ?>

<div class="main-layout">
    <?php include 'includes/sidebar.php'; ?>
    <main class="content">
        <div class="content-header">
            <h1 class="content-title">
                <i class="fas fa-list"></i>
                Tipos de Gastos
            </h1>
            <p class="content-subtitle">Administrar tipos específicos de gastos por categoría</p>
        </div>
        
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: flex-end; align-items: center;">
                <div style="flex: 1;">
                    <input
                        type="text"
                        id="searchInput"
                        class="form-input"
                        placeholder="Buscar tipo de gasto..."
                        aria-label="Buscar tipo de gasto"
                        style="max-width: 300px;"
                        autocomplete="off"
                    >
                </div>
                <div>
                    <button type="button" class="btn btn-primary" onclick="openModal('typeModal')">
                        <i class="fas fa-plus"></i>
                        Nuevo Tipo
                    </button>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-table"></i>
                    Lista de Tipos de Gastos
                </h3>
                <p class="card-subtitle">Total: <span id="totalTypes">0</span> tipos registrados</p>
            </div>
            <div style="overflow-x: auto;">
                <table class="data-table" id="typesTable" style="min-width: 700px;">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Categoría</th>
                            <th style="width: 80px;">Estado</th>
                            <th style="width: 120px; text-align: center;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="typesTableBody">
                        <!-- Las filas se llenarán dinámicamente con JS -->
                    </tbody>
                </table>
                <div id="typesTableFooter" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0 0 0;">
                    <div id="pageSizeSelectorContainer"></div>
                    <div id="typesPagination"></div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modal para crear/editar tipo -->
<div class="modal" id="typeModal">
    <div class="modal-overlay" onclick="closeModal('typeModal')"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Nuevo Tipo de Gasto</h2>
            <button type="button" class="modal-close" onclick="closeModal('typeModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="typeForm">
            <div class="modal-body">
                <input type="hidden" id="typeId" name="typeId" value="">
                
                <div class="form-group">
                    <label class="form-label" for="typeName">Nombre *</label>
                    <input type="text" class="form-input" id="typeName" name="typeName" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="typeDescription">Descripción</label>
                    <textarea class="form-input" id="typeDescription" name="typeDescription" rows="3" placeholder="Descripción del tipo de gasto"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="typeCategory">Categoría *</label>
                    <select class="form-input" id="typeCategory" name="typeCategory" required>
                        <option value="">Seleccionar categoría...</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <input type="checkbox" id="typeIsActive" name="typeIsActive" checked>
                        Tipo activo
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('typeModal')" style="background-color: var(--secondary-color); color: white;">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Guardar Tipo
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
            <p id="confirmDeleteMessage">¿Está seguro de que desea eliminar este tipo de gasto?</p>
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
<script src="assets/js/expense_types.js"></script> 