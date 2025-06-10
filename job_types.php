<?php
require_once 'config.php';

if (!isLoggedIn() || !checkSessionTimeout()) {
    header('Location: auth/login.php');
    exit;
}

$pageTitle = 'Tipos de Trabajo';
?>
<?php include 'includes/header.php'; ?>

<div class="main-layout">
    <?php include 'includes/sidebar.php'; ?>
    <main class="content">
        <div class="content-header">
            <h1 class="content-title">
                <i class="fas fa-briefcase"></i>
                Tipos de Trabajo
            </h1>
            <p class="content-subtitle">Catálogo de tipos de trabajo</p>
        </div>
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: flex-end; align-items: center;">
                <div style="flex: 1;">
                    <input
                        type="text"
                        id="searchInput"
                        class="form-input"
                        placeholder="Buscar tipo de trabajo..."
                        aria-label="Buscar tipo de trabajo"
                        style="max-width: 300px;"
                        autocomplete="off"
                    >
                </div>
                <div>
                    <button type="button" class="btn btn-primary" onclick="openModal('jobTypeModal')">
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
                    Lista de Tipos de Trabajo
                </h3>
                <p class="card-subtitle">Total: <span id="totalJobTypes">0</span> tipos registrados</p>
            </div>
            <div style="overflow-x: auto;">
                <table class="data-table" id="jobTypesTable" style="min-width: 700px;">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Paga como Contratista</th>
                            <th>Paga como Subcontratista</th>
                            <th style="vertical-align: middle; text-align: center;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="jobTypesTableBody">
                        <!-- Las filas se llenarán dinámicamente con JS -->
                    </tbody>
                </table>
                <div id="jobTypesTableFooter" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0 0 0;">
                    <div id="pageSizeSelectorContainer"></div>
                    <div id="jobTypesPagination"></div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modal para crear/editar tipo de trabajo -->
<div class="modal" id="jobTypeModal">
    <div class="modal-overlay" onclick="closeModal('jobTypeModal')"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Nuevo Tipo de Trabajo</h2>
            <button type="button" class="modal-close" onclick="closeModal('jobTypeModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="jobTypeForm">
            <div class="modal-body">
                <input type="hidden" id="jobTypeId" name="jobTypeId" value="">
                <div class="form-group">
                    <label class="form-label" for="jobTypeName">Nombre *</label>
                    <input type="text" class="form-input" id="jobTypeName" name="jobTypeName" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="payAsContractor">Paga como Contratista</label>
                    <input type="number" step="0.01" class="form-input" id="payAsContractor" name="payAsContractor" value="0.00">
                </div>
                <div class="form-group">
                    <label class="form-label" for="payAsSubContractor">Paga como Subcontratista</label>
                    <input type="number" step="0.01" class="form-input" id="payAsSubContractor" name="payAsSubContractor" value="0.00">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('jobTypeModal')" style="background-color: var(--secondary-color); color: white;">
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
            <p id="confirmDeleteMessage">¿Está seguro de que desea eliminar este tipo de trabajo?</p>
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
<script src="assets/js/job_types.js"></script>
