<?php
require_once 'config.php';

if (!isLoggedIn() || !checkSessionTimeout()) {
    header('Location: auth/login.php');
    exit;
}

$vendorId = $_GET['id'] ?? '';
if (empty($vendorId)) {
    header('Location: vendors.php');
    exit;
}

$pageTitle = 'Detalles del Proveedor';
?>
<?php include 'includes/header.php'; ?>

<!-- Flatpickr CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<!-- Flatpickr JS -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script>
// Verificar que flatpickr esté disponible
if (typeof flatpickr === 'undefined') {
    console.error('Flatpickr no está disponible. Recargando la página...');
    if (!sessionStorage.getItem('flatpickrReload')) {
        sessionStorage.setItem('flatpickrReload', 'true');
        window.location.reload();
    } else {
        sessionStorage.removeItem('flatpickrReload');
    }
}
</script>

<div class="main-layout">
    <?php include 'includes/sidebar.php'; ?>
    <main class="content">
        <div class="content-header">
            <div style="display: flex; align-items: center; gap: 16px;">
                <button type="button" class="btn" onclick="window.location.href='vendors.php'" title="Volver a proveedores">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <div>
                    <h1 class="content-title">
                        <i class="fas fa-truck"></i>
                        <span id="vendorNameTitle">Detalles del Proveedor</span>
                    </h1>
                    <p class="content-subtitle">Información completa e historial de gastos</p>
                </div>
            </div>
        </div>

        <!-- Información del proveedor -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-info-circle"></i>
                    Información del Proveedor
                </h3>
            </div>
            <div class="card-body" id="vendorDetails">
                <!-- Se llena dinámicamente -->
            </div>
        </div>

        <!-- Historial de gastos -->
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 16px; flex: 1;">
                    <h3 class="card-title">
                        <i class="fas fa-receipt"></i>
                        Historial de Gastos
                    </h3>
                    <!-- Filtros de fecha -->
                    <div class="filter-dropdown-container">
                        <button type="button" class="btn btn-outline" id="filterDropdownBtn" onclick="toggleFilterDropdown()">
                            <i class="fas fa-filter"></i>
                            Filtrar por fecha
                            <span id="activeFiltersCount" class="filter-count" style="display: none;">0</span>
                        </button>
                        <div class="filter-dropdown" id="filterDropdown">
                            <div class="filter-section">
                                <label class="filter-label">
                                    <i class="fas fa-calendar-alt"></i>
                                    Rango de Fechas
                                </label>
                                <div class="date-range-inputs">
                                    <input type="text" id="dateFromFilter" class="form-input date-picker" placeholder="Fecha desde...">
                                    <input type="text" id="dateToFilter" class="form-input date-picker" placeholder="Fecha hasta...">
                                </div>
                            </div>
                            
                            <div class="filter-actions">
                                <button type="button" class="btn btn-secondary" onclick="clearDateFilters()">
                                    <i class="fas fa-times"></i>
                                    Limpiar
                                </button>
                                <button type="button" class="btn btn-primary" onclick="applyDateFilters()">
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
                    <button type="button" class="btn btn-primary" onclick="window.location.href='expenses.php?vendor=<?php echo htmlspecialchars($vendorId); ?>'">
                        <i class="fas fa-plus"></i>
                        Nuevo Gasto
                    </button>
                </div>
            </div>
            <div style="overflow-x: auto;">
                <table class="data-table sortable-table" id="expensesTable" style="min-width: 900px;">
                    <thead>
                        <tr>
                            <th class="sortable" data-sort="expense_date" onclick="sortTable('expense_date')">
                                Fecha de Gasto
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th class="sortable" data-sort="expense_number" onclick="sortTable('expense_number')">
                                Número
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th class="sortable" data-sort="total_amount" onclick="sortTable('total_amount')">
                                Monto Total
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th class="sortable" data-sort="team_name" onclick="sortTable('team_name')">
                                Equipo
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th class="sortable" data-sort="bank_account_name" onclick="sortTable('bank_account_name')">
                                Cuenta Bancaria
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th>Notas</th>
                            <th style="text-align: center; width: 120px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="expensesTableBody">
                        <!-- Se llena dinámicamente -->
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

<!-- Toast de notificación -->
<div id="toast" style="position: fixed; bottom: 32px; right: 32px; min-width: 220px; z-index: 9999; display: none; background: var(--primary-color, #2563eb); color: #fff; padding: 16px 24px; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,0.12); font-size: 16px; font-weight: 500; align-items: center; gap: 8px;">
    <span id="toastIcon" style="margin-right: 8px;"></span>
    <span id="toastMessage"></span>
</div>

<style>
.badge {
    background: var(--success-color, #059669);
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.loading-spinner {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid rgba(255,255,255,.3);
    border-radius: 50%;
    border-top-color: #fff;
    animation: spin 1s ease-in-out infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Estilos para filtros de fecha */
.filter-dropdown-container {
    position: relative;
    display: inline-block;
}

/* Estilo del botón de filtros */
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

.active-filters-container {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    align-items: center;
}

.filter-tag {
    background: var(--primary-color);
    color: white;
    padding: 4px 8px;
    border-radius: 16px;
    font-size: 12px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
}

.filter-tag .remove-filter {
    background: none;
    border: none;
    color: white;
    cursor: pointer;
    padding: 0;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    transition: background-color 0.2s ease;
}

.filter-tag .remove-filter:hover {
    background: rgba(255, 255, 255, 0.2);
}

/* Flatpickr personalización */
.flatpickr-input {
    cursor: pointer;
}

.flatpickr-calendar {
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    z-index: 1001 !important;
}

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

/* Estilos para sorting */
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

/* Estilos para números de gastos clickeables */
.expense-number-link {
    color: var(--primary-color);
    text-decoration: none;
    transition: all 0.2s ease;
    cursor: pointer;
}

.expense-number-link:hover {
    color: var(--primary-dark, #1d4ed8);
    text-decoration: underline;
}

.expense-number-link:visited {
    color: var(--primary-color);
}

/* Responsive para paginación */
@media (max-width: 768px) {
    #expensesTableFooter {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
    }
    
    #expensesPagination {
        display: flex;
        justify-content: center;
        flex-wrap: wrap;
    }
}
</style>

<?php include 'includes/footer.php'; ?>

<script>
// Variables globales para el script inline
window.vendorId = '<?php echo htmlspecialchars($vendorId); ?>';

// Funciones del modal
function openModal(modalId) {
    document.getElementById(modalId).style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Función auxiliar local para compatibilidad si es necesaria
function formatDateLocal(dateString) {
    if (!dateString) return '-';
    // Evitar problemas de zona horaria tratando la fecha como local
    const dateParts = dateString.split('T')[0].split('-');
    const date = new Date(dateParts[0], dateParts[1] - 1, dateParts[2]);
    return date.toLocaleDateString('es-ES');
}

// Cerrar modales con ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (modal.style.display === 'flex') {
                closeModal(modal.id);
            }
        });
    }
});
</script>

<script src="assets/js/vendor_details.js"></script> 