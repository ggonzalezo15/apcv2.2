<?php
require_once 'config.php';

if (!isLoggedIn() || !checkSessionTimeout()) {
    header('Location: auth/login.php');
    exit;
}

$contractorId = $_GET['id'] ?? '';
if (empty($contractorId)) {
    header('Location: contractors.php');
    exit;
}

$pageTitle = 'Detalles del Contratista';
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
                <button type="button" class="btn" onclick="window.location.href='contractors.php'" title="Volver a contratistas">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <div>
                    <h1 class="content-title">
                        <i class="fas fa-user-tie"></i>
                        <span id="contractorNameTitle">Detalles del Contratista</span>
                    </h1>
                    <p class="content-subtitle">Información completa y historial de pagos</p>
                </div>
            </div>
        </div>

        <!-- Información del contratista -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-info-circle"></i>
                    Información del Contratista
                </h3>
            </div>
            <div class="card-body" id="contractorDetails">
                <!-- Se llena dinámicamente -->
            </div>
        </div>

        <!-- Historial de pagos -->
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 16px; flex: 1;">
                    <h3 class="card-title">
                        <i class="fas fa-money-bill-wave"></i>
                        Historial de Pagos
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
                    <button type="button" class="btn btn-primary" onclick="openNewPaymentModal()">
                        <i class="fas fa-plus"></i>
                        Nuevo Pago
                    </button>
                </div>
            </div>
            <div style="overflow-x: auto;">
                <table class="data-table sortable-table" id="paymentsTable" style="min-width: 800px;">
                    <thead>
                        <tr>
                            <th class="sortable" data-sort="payment_date" onclick="sortTable('payment_date')">
                                Fecha de Pago
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th class="sortable" data-sort="amount" onclick="sortTable('amount')">
                                Monto
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th class="sortable" data-sort="account_name" onclick="sortTable('account_name')">
                                Cuenta Bancaria
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th class="sortable" data-sort="reference_number" onclick="sortTable('reference_number')">
                                Referencia
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th>Notas</th>
                            <th style="text-align: center; width: 120px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="paymentsTableBody">
                        <!-- Se llena dinámicamente -->
                    </tbody>
                </table>
                <div id="paymentsTableFooter" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0 0 0;">
                    <div id="pageSizeSelectorContainer"></div>
                    <div id="paymentsPagination"></div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modal para nuevo pago -->
<div class="modal" id="paymentModal">
    <div class="modal-overlay" onclick="closeModal('paymentModal')"></div>
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h2 id="paymentModalTitle">Nuevo Pago a Contratista</h2>
            <button type="button" class="modal-close" onclick="closeModal('paymentModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="paymentForm">
            <div class="modal-body">
                <input type="hidden" id="paymentId" name="paymentId" value="">
                <input type="hidden" id="contractorIdInput" name="contractorId" value="<?php echo htmlspecialchars($contractorId); ?>">
                
                <div class="form-group">
                    <label class="form-label" for="bankAccountId">Cuenta Bancaria *</label>
                    <select class="form-input" id="bankAccountId" name="bankAccountId" required>
                        <option value="">Seleccionar cuenta...</option>
                        <!-- Se llena dinámicamente -->
                    </select>
                    <small class="form-help">Solo se muestran cuentas bancarias (no crédito)</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="amount">Monto *</label>
                    <input type="number" class="form-input" id="amount" name="amount" step="0.01" min="0.01" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="paymentDate">Fecha de Pago *</label>
                    <input type="text" class="form-input" id="paymentDate" name="paymentDate" required placeholder="Seleccionar fecha...">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="referenceNumber">Número de Referencia</label>
                    <input type="text" class="form-input" id="referenceNumber" name="referenceNumber" placeholder="Ej: TRF001, CHQ1234">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="notes">Notas</label>
                    <textarea class="form-input" id="notes" name="notes" rows="3" placeholder="Notas adicionales sobre el pago..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('paymentModal')" style="background-color: var(--secondary-color); color: white;">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i>
                    <span id="paymentSubmitText">Registrar Pago</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de confirmación de eliminación -->
<div class="modal" id="confirmDeletePaymentModal" style="display:none;">
    <div class="modal-overlay" onclick="closeModal('confirmDeletePaymentModal')"></div>
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h2>Confirmar eliminación</h2>
            <button type="button" class="modal-close" onclick="closeModal('confirmDeletePaymentModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <p>¿Está seguro de que desea eliminar este pago?</p>
            <p><strong>Esta acción revertirá la transacción bancaria asociada.</strong></p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn" onclick="closeModal('confirmDeletePaymentModal')">Cancelar</button>
            <button type="button" class="btn btn-danger" id="confirmDeletePaymentBtn">Eliminar</button>
        </div>
    </div>
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

/* Estilos para paginación */
#pageSizeSelectorContainer {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
}

#pageSizeSelectorContainer label {
    color: var(--text-secondary);
    font-weight: 500;
}

#pageSizeSelectorContainer select {
    width: auto;
    min-width: 140px;
    padding: 6px 10px;
    font-size: 14px;
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

/* Responsive para paginación */
@media (max-width: 768px) {
    #paymentsTableFooter {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
    }
    
    #pageSizeSelectorContainer {
        justify-content: center;
    }
    
    #paymentsPagination {
        display: flex;
        justify-content: center;
        flex-wrap: wrap;
    }
}
</style>

<?php include 'includes/footer.php'; ?>

<script>
// Variables globales para el script inline (hacer contractorId explícitamente global)
window.contractorId = '<?php echo htmlspecialchars($contractorId); ?>';
let editingPaymentId = null;
let paymentIdToDelete = null;

// Función básica para cargar datos, será sobrescrita por el JS externo
document.addEventListener('DOMContentLoaded', function() {
    // Solo establecer fecha actual para el picker si el elemento existe
    const paymentDateInput = document.getElementById('paymentDate');
    if (paymentDateInput && paymentDateInput.type !== 'text') {
        paymentDateInput.value = new Date().toISOString().split('T')[0];
    }
});

// Las funciones renderPaymentsTable y updateStatistics están en el archivo externo

// Funciones del modal
function openModal(modalId) {
    document.getElementById(modalId).style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
    document.body.style.overflow = 'auto';
    if (modalId === 'paymentModal') {
        document.getElementById('paymentForm').reset();
        document.getElementById('paymentModalTitle').textContent = 'Nuevo Pago a Contratista';
        document.getElementById('paymentSubmitText').textContent = 'Registrar Pago';
        editingPaymentId = null;
        // Restablecer fecha actual
        document.getElementById('paymentDate').value = new Date().toISOString().split('T')[0];
    }
}

function openNewPaymentModal() {
    openModal('paymentModal');
}

function editContractor() {
    window.location.href = `contractors.php?edit=${contractorId}`;
}

// Las funciones editPayment y deletePayment están en el archivo externo

// Variable para evitar event listeners duplicados
let paymentFormListenerAdded = false;

// Event listeners - estas funciones necesitan acceso a las variables inline  
document.addEventListener('DOMContentLoaded', function() {
    const paymentForm = document.getElementById('paymentForm');
    if (paymentForm && !paymentFormListenerAdded) {
        paymentFormListenerAdded = true;
        paymentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const data = {
                contractor_id: window.contractorId,
                bank_account_id: document.getElementById('bankAccountId').value,
                amount: document.getElementById('amount').value,
                payment_date: document.getElementById('paymentDate').value,
                reference_number: document.getElementById('referenceNumber').value,
                notes: document.getElementById('notes').value,
                created_by: 'current_user'
            };
            
            const API_PAYMENT_URL = 'api/contractor/ContractorPaymentController.php';
            let url = API_PAYMENT_URL;
            let method = 'POST';
            
            // Verificar si estamos editando usando window.editingPaymentId
            if (window.editingPaymentId || editingPaymentId) {
                const id = window.editingPaymentId || editingPaymentId;
                url += `?action=updateContractorPayment&id=${id}`;
                method = 'PUT';
            } else {
                url += '?action=createContractorPayment';
            }
            
            fetch(url, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(res => res.json())
            .then(result => {
                closeModal('paymentModal');
                if (result.success) {
                    showToast(result.message, 'success');
                    // Limpiar variables de edición
                    window.editingPaymentId = null;
                    editingPaymentId = null;
                    // Recargar datos usando las funciones del archivo externo
                    if (window.loadContractorPayments) {
                        window.loadContractorPayments();
                    }
                    if (window.loadBankAccountsForPayment) {
                        window.loadBankAccountsForPayment();
                    }
                } else {
                    showToast(result.error || 'Error al procesar el pago', 'error');
                }
            })
            .catch(err => {
                console.error('Error:', err);
                showToast('Error de conexión', 'error');
            });
        });
    }
    
    // Configurar botón de confirmación de eliminación con timeout para asegurar que se registre
    setTimeout(() => {
        const confirmDeleteBtn = document.getElementById('confirmDeletePaymentBtn');
        if (confirmDeleteBtn && !confirmDeleteBtn.hasAttribute('data-listener-added')) {
            confirmDeleteBtn.setAttribute('data-listener-added', 'true');
            confirmDeleteBtn.onclick = function() {
                const idToDelete = window.paymentIdToDelete || paymentIdToDelete;
                if (idToDelete) {
                    const API_PAYMENT_URL = 'api/contractor/ContractorPaymentController.php';
                    fetch(`${API_PAYMENT_URL}?action=deleteContractorPayment&id=${idToDelete}`, { method: 'DELETE' })
                    .then(res => res.json())
                    .then(result => {
                        closeModal('confirmDeletePaymentModal');
                        if (result.success) {
                            showToast(result.message, 'success');
                            // Recargar datos usando las funciones del archivo externo
                            if (window.loadContractorPayments) {
                                window.loadContractorPayments();
                            }
                            if (window.loadBankAccountsForPayment) {
                                window.loadBankAccountsForPayment();
                            }
                        } else {
                            showToast(result.error || 'Error al eliminar el pago', 'error');
                        }
                        paymentIdToDelete = null;
                        window.paymentIdToDelete = null;
                    })
                    .catch(err => {
                        console.error('Error:', err);
                        showToast('Error de conexión', 'error');
                    });
                }
            };
        }
    }, 100); // Esperar 100ms para asegurar que el DOM esté listo
});

// Las funciones utilitarias están en el archivo externo

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

<script src="assets/js/contractor_details.js"></script> 
