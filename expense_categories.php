<?php
require_once 'config.php';

if (!isLoggedIn() || !checkSessionTimeout()) {
    header('Location: auth/login.php');
    exit;
}

$pageTitle = 'Categorías de Gastos';
?>
<?php include 'includes/header.php'; ?>

<div class="main-layout">
    <?php include 'includes/sidebar.php'; ?>
    <main class="content">
        <div class="content-header">
            <h1 class="content-title">
                <i class="fas fa-tags"></i>
                Categorías de Gastos
            </h1>
            <p class="content-subtitle">Administrar categorías para clasificar los gastos</p>
        </div>
        
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: flex-end; align-items: center;">
                <div style="flex: 1;">
                    <input
                        type="text"
                        id="searchInput"
                        class="form-input"
                        placeholder="Buscar categoría..."
                        aria-label="Buscar categoría"
                        style="max-width: 300px;"
                        autocomplete="off"
                    >
                </div>
                <div>
                    <button type="button" class="btn btn-primary" onclick="openModal('categoryModal')">
                        <i class="fas fa-plus"></i>
                        Nueva Categoría
                    </button>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-table"></i>
                    Lista de Categorías
                </h3>
                <p class="card-subtitle">Total: <span id="totalCategories">0</span> categorías registradas</p>
            </div>
            <div style="overflow-x: auto;">
                <table class="data-table" id="categoriesTable" style="min-width: 600px;">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th style="width: 100px;">Tipos</th>
                            <th style="width: 80px;">Estado</th>
                            <th style="width: 120px; text-align: center;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="categoriesTableBody">
                        <!-- Las filas se llenarán dinámicamente con JS -->
                    </tbody>
                </table>
                <div id="categoriesTableFooter" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0 0 0;">
                    <div id="pageSizeSelectorContainer"></div>
                    <div id="categoriesPagination"></div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modal para crear/editar categoría -->
<div class="modal" id="categoryModal">
    <div class="modal-overlay" onclick="closeModal('categoryModal')"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Nueva Categoría</h2>
            <button type="button" class="modal-close" onclick="closeModal('categoryModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="categoryForm">
            <div class="modal-body">
                <input type="hidden" id="categoryId" name="categoryId" value="">
                
                <div class="form-group">
                    <label class="form-label" for="categoryName">Nombre *</label>
                    <input type="text" class="form-input" id="categoryName" name="categoryName" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="categoryDescription">Descripción</label>
                    <textarea class="form-input" id="categoryDescription" name="categoryDescription" rows="3" placeholder="Descripción de la categoría"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <input type="checkbox" id="categoryIsActive" name="categoryIsActive" checked>
                        Categoría activa
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('categoryModal')" style="background-color: var(--secondary-color); color: white;">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Guardar Categoría
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
            <p id="confirmDeleteMessage">¿Está seguro de que desea eliminar esta categoría?</p>
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
<script src="assets/js/expense_categories.js"></script> 
