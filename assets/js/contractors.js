// --- Configuración ---
const API_URL = 'api/contractor/ContractorController.php';
const API_PAYMENT_URL = 'api/contractor/ContractorPaymentController.php';
let editingContractorId = null;
let sortField = 'created_at';
let sortDir = 'desc';

// --- Variables para filtros ---
let activeStatusFilters = {
    status: ''
};

// --- Cargar contratistas al iniciar ---
document.addEventListener('DOMContentLoaded', function() {
    loadContractors();
    loadContractorsForPayment();
    loadBankAccountsForPayment();
    // Establecer fecha actual por defecto
    document.getElementById('paymentDate').value = new Date().toISOString().split('T')[0];
    
    // Configurar ordenamiento después de un delay para asegurar que el DOM esté listo
    setTimeout(() => {
        setupTableSorting();
        setupStatusSwitch();
        setupSearchInput();
    }, 100);
});

// --- Paginación ---
let currentPage = 1;
let pageSize = 10;
let totalContractorsCount = 0;

// Selector de líneas por página
function renderPageSizeSelector() {
    let container = document.getElementById('pageSizeSelectorContainer');
    if (!container) return;
    container.innerHTML = '';
    const label = document.createElement('label');
    label.textContent = 'Mostrar:';
    label.style = 'margin-right: 4px; font-weight: 500; color: var(--text-secondary);';
    const selector = document.createElement('select');
    selector.id = 'pageSizeSelector';
    selector.className = 'form-input';
    selector.style = 'width: auto; display: inline-block;';
    [5, 10, 20, 50, 100].forEach(size => {
        const opt = document.createElement('option');
        opt.value = size;
        opt.textContent = `${size} por página`;
        selector.appendChild(opt);
    });
    selector.value = pageSize;
    selector.onchange = function() {
        pageSize = parseInt(this.value);
        loadContractors(1);
    };
    container.appendChild(label);
    container.appendChild(selector);
}

renderPageSizeSelector();

// --- Configurar ordenamiento de tabla ---
function setupTableSorting() {
    const sortableElements = document.querySelectorAll('.sortable');
    
    sortableElements.forEach(th => {
        th.addEventListener('click', function() {
            const field = this.dataset.sort;
            
            if (sortField === field) {
                sortDir = sortDir === 'asc' ? 'desc' : 'asc';
            } else {
                sortField = field;
                sortDir = 'asc';
            }
            
            updateSortIcons();
            loadContractors(1);
        });
    });
}

function updateSortIcons() {
    document.querySelectorAll('.sortable').forEach(th => {
        th.classList.remove('sort-asc', 'sort-desc');
        if (th.dataset.sort === sortField) {
            th.classList.add(`sort-${sortDir}`);
        }
    });
}

function loadContractors(page = 1) {
    currentPage = page;
    setTableLoading(true);
    
    // Construir URL con filtros
    let url = `${API_URL}?action=getAllContractors&limit=${pageSize}&offset=${(page-1)*pageSize}&sort=${sortField}&dir=${sortDir}`;
    
    // Agregar filtros de estado
    if (activeStatusFilters.status !== '') {
        url += `&status=${activeStatusFilters.status}`;
    }
    
    // Agregar filtro de búsqueda
    const searchTerm = document.getElementById('searchInput')?.value?.trim();
    if (searchTerm) {
        url += `&search=${encodeURIComponent(searchTerm)}`;
    }
    
    fetch(url)
        .then(res => res.json())
        .then(data => {
            const contractors = data.data || data;
            totalContractorsCount = data.total || contractors.length;
            renderContractorsTable(contractors);
            renderPagination();
            
            // Actualizar iconos de ordenamiento
            updateSortIcons();
        })
        .catch(() => {
            document.getElementById('contractorsTableBody').innerHTML = '<tr><td colspan="6">Error al cargar contratistas</td></tr>';
        })
        .finally(() => setTableLoading(false));
}

function setTableLoading(loading) {
    const tbody = document.getElementById('contractorsTableBody');
    if (loading) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:40px 0;">
            <div class="loading-spinner"></div>
            <span style="display:block; margin-top:8px; color:var(--text-secondary);">Cargando contratistas...</span>
        </td></tr>`;
    }
}

function renderContractorsTable(contractors) {
    const tbody = document.getElementById('contractorsTableBody');
    tbody.innerHTML = '';
    if (!contractors.length) {
        tbody.innerHTML = '<tr><td colspan="6">No hay contratistas registrados</td></tr>';
        document.getElementById('totalContractors').textContent = '0';
        return;
    }
    document.getElementById('totalContractors').textContent = totalContractorsCount;
    contractors.forEach(contractor => {
        const tr = document.createElement('tr');
        
        // Determinar estado y badge
        const isActive = contractor.status === 'active' || contractor.status_numeric === 1;
        const statusBadge = isActive 
            ? '<span class="status-badge status-active">Activo</span>'
            : '<span class="status-badge status-inactive">Inactivo</span>';
        
        tr.innerHTML = `
            <td>
                <a href="contractor_details.php?id=${contractor.id}" class="contractor-name-link" title="Ver detalles del contratista">
                    ${contractor.name}
                </a>
            </td>
            <td>${contractor.email || ''}</td>
            <td>${contractor.phone || ''}</td>
            <td>${contractor.address || ''}</td>
            <td>${statusBadge}</td>
            <td style="vertical-align: middle; text-align: center;">
                <div style="display: flex; gap: 4px; justify-content: center; align-items: center;">
                    <button type="button" class="btn-icon" onclick="toggleContractorStatus('${contractor.id}', ${isActive ? 0 : 1}, '${contractor.name}')" title="${isActive ? 'Desactivar' : 'Activar'} contratista">
                        <i class="fas fa-toggle-${isActive ? 'on' : 'off'}" style="color: ${isActive ? 'var(--success-color)' : 'var(--text-secondary)'}"></i>
                    </button>
                    <button type="button" class="btn-icon" onclick="viewContractor('${contractor.id}')" title="Ver detalles">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button type="button" class="btn-icon" onclick="editContractor('${contractor.id}')" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button type="button" class="btn-icon btn-danger" onclick="deleteContractor('${contractor.id}')" title="Eliminar">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });
    renderPageSizeSelector();
}

function renderPagination() {
    const container = document.getElementById('contractorsPagination');
    if (!container) return;
    container.innerHTML = '';
    const totalPages = Math.ceil(totalContractorsCount / pageSize);
    if (totalPages <= 1) { container.style.display = 'none'; return; }
    container.style.display = 'flex';
    for (let i = 1; i <= totalPages; i++) {
        const btn = document.createElement('button');
        btn.className = 'btn' + (i === currentPage ? ' btn-primary' : '');
        btn.textContent = i;
        btn.style.minWidth = '36px';
        btn.onclick = () => loadContractors(i);
        container.appendChild(btn);
    }
}

function openModal(modalId) {
    document.getElementById(modalId).style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
    document.body.style.overflow = 'auto';
    if (modalId === 'contractorModal') {
        document.getElementById('contractorForm').reset();
        document.getElementById('modalTitle').textContent = 'Nuevo Contratista';
        editingContractorId = null;
    } else if (modalId === 'paymentModal') {
        document.getElementById('paymentForm').reset();
        // Restablecer fecha actual
        document.getElementById('paymentDate').value = new Date().toISOString().split('T')[0];
    }
}

function showNotification(message, title = 'Notificación') {
    document.getElementById('notificationTitle').textContent = title;
    document.getElementById('notificationMessage').textContent = message;
    document.getElementById('notificationModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    const toastIcon = document.getElementById('toastIcon');
    const toastMessage = document.getElementById('toastMessage');
    toastMessage.textContent = message;
    if (type === 'success') {
        toast.style.background = 'var(--success-color, #059669)';
        toastIcon.innerHTML = '<i class="fas fa-check-circle"></i>';
    } else if (type === 'error') {
        toast.style.background = 'var(--danger-color, #dc2626)';
        toastIcon.innerHTML = '<i class="fas fa-times-circle"></i>';
    } else {
        toast.style.background = 'var(--primary-color, #2563eb)';
        toastIcon.innerHTML = '<i class="fas fa-info-circle"></i>';
    }
    toast.style.display = 'flex';
    setTimeout(() => { toast.style.display = 'none'; }, 3200);
}

function viewContractor(id) {
    window.location.href = `contractor_details.php?id=${id}`;
}

function editContractor(id) {
    fetch(`${API_URL}?action=getContractorById&id=${id}`)
        .then(res => res.json())
        .then(contractor => {
            document.getElementById('modalTitle').textContent = 'Editar Contratista';
            document.getElementById('contractorName').value = contractor.name || '';
            document.getElementById('contractorEmail').value = contractor.email || '';
            document.getElementById('contractorPhone').value = contractor.phone || '';
            document.getElementById('contractorAddress').value = contractor.address || '';
            
            // Configurar estado
            const isActive = contractor.status === 'active' || contractor.status_numeric === 1;
            const statusCheckbox = document.getElementById('contractorStatus');
            const statusLabel = document.getElementById('contractorStatusLabel');
            
            statusCheckbox.checked = isActive;
            statusLabel.textContent = isActive ? 'Activo' : 'Inactivo';
            statusLabel.style.color = isActive ? 'var(--success-color)' : 'var(--text-secondary)';
            
            editingContractorId = contractor.id;
            openModal('contractorModal');
        });
}

let contractorIdToDelete = null;
function showDeleteModal(id) {
    contractorIdToDelete = id;
    document.getElementById('confirmDeleteModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
document.getElementById('confirmDeleteBtn').onclick = function() {
    if (contractorIdToDelete) {
        deleteContractorConfirmed(contractorIdToDelete);
        contractorIdToDelete = null;
        closeModal('confirmDeleteModal');
    }
};
function deleteContractor(id) {
    showDeleteModal(id);
}
function deleteContractorConfirmed(id) {
    fetch(`${API_URL}?action=deleteContractor&id=${id}`, { method: 'DELETE' })
        .then(res => res.json())
        .then(result => {
            if (result && result.error) {
                showNotification('No se puede eliminar el contratista.\n\nDetalle: ' + result.error, 'Error al eliminar contratista');
                showToast('No se pudo eliminar el contratista.', 'error');
                return;
            }
            showToast('Contratista eliminado con éxito.', 'success');
            setTimeout(() => {
                fetch(`${API_URL}?action=getAllContractors&limit=${pageSize}&offset=${(currentPage-1)*pageSize}`)
                    .then(res => res.json())
                    .then(data => {
                        const contractors = data.data || data;
                        if (contractors.length === 0 && currentPage > 1) {
                            loadContractors(currentPage - 1);
                        } else {
                            loadContractors(currentPage);
                        }
                    });
            }, 200);
        })
        .catch(() => {
            showToast('Ocurrió un error al eliminar el contratista.', 'error');
        });
}

document.getElementById('contractorForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const data = {
        name: document.getElementById('contractorName').value,
        email: document.getElementById('contractorEmail').value,
        phone: document.getElementById('contractorPhone').value,
        address: document.getElementById('contractorAddress').value,
        status: document.getElementById('contractorStatus').checked ? 1 : 0
    };
    let url = API_URL;
    let method = 'POST';
    let isEdit = false;
    if (editingContractorId) {
        url += `?action=updateContractor&id=${editingContractorId}`;
        method = 'PUT';
        isEdit = true;
    } else {
        url += '?action=createContractor';
    }
    fetch(url, {
        method: method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(result => {
        closeModal('contractorModal');
        if (result && result.error) {
            showToast('Ocurrió un error al guardar el contratista.', 'error');
        } else {
            showToast(isEdit ? 'Contratista editado con éxito.' : 'Contratista creado con éxito.', 'success');
        }
        loadContractors();
    })
    .catch(() => {
        showToast('Ocurrió un error al guardar el contratista.', 'error');
    });
});

// --- Filtro de búsqueda local por nombre de contratista ---
document.getElementById('searchInput').addEventListener('input', function() {
    const search = this.value.trim().toLowerCase();
    const rows = document.querySelectorAll('#contractorsTableBody tr');
    let count = 0;
    rows.forEach(row => {
        const name = row.children[0]?.textContent.toLowerCase() || '';
        if (name.includes(search)) {
            row.style.display = '';
            count++;
        } else {
            row.style.display = 'none';
        }
    });
    document.getElementById('totalContractors').textContent = count;
});

// --- Sort interactivo en la tabla ---
document.addEventListener('DOMContentLoaded', function() {
    const ths = document.querySelectorAll('#contractorsTable thead th');
    ths.forEach((th, idx) => {
        if (idx < 4) { // Solo para columnas principales
            th.style.cursor = 'pointer';
            th.addEventListener('click', function() {
                const fields = ['name', 'email', 'phone', 'address'];
                const field = fields[idx];
                if (sortField === field) {
                    sortDir = sortDir === 'asc' ? 'desc' : 'asc';
                } else {
                    sortField = field;
                    sortDir = 'asc';
                }
                loadContractors(1);
            });
        }
    });
});

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

// --- Funciones para manejo de pagos ---

function loadContractorsForPayment() {
    fetch(`${API_URL}?action=getAllContractors&status=1`)
        .then(res => res.json())
        .then(data => {
            const contractors = data.data || data;
            const select = document.getElementById('paymentContractorId');
            select.innerHTML = '<option value="">Seleccionar contratista...</option>';
            contractors.forEach(contractor => {
                const option = document.createElement('option');
                option.value = contractor.id;
                option.textContent = contractor.name;
                select.appendChild(option);
            });
        })
        .catch(err => {
            console.error('Error cargando contratistas para pago:', err);
        });
}

function loadBankAccountsForPayment() {
    fetch(`${API_PAYMENT_URL}?action=getBankAccountsForPayments`)
        .then(res => res.json())
        .then(accounts => {
            const select = document.getElementById('paymentBankAccountId');
            select.innerHTML = '<option value="">Seleccionar cuenta...</option>';
            // Solo mostrar cuentas activas
            const activeAccounts = accounts.filter(account => account.active === 1 || account.active === '1' || account.active === true);
            activeAccounts.forEach(account => {
                const option = document.createElement('option');
                option.value = account.id;
                option.textContent = `${account.name} (${account.bank_name}) - $${parseFloat(account.balance).toFixed(2)}`;
                select.appendChild(option);
            });
        })
        .catch(err => {
            console.error('Error cargando cuentas bancarias:', err);
        });
}

// Event listener para el formulario de pagos
document.getElementById('paymentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const data = {
        contractor_id: document.getElementById('paymentContractorId').value,
        bank_account_id: document.getElementById('paymentBankAccountId').value,
        amount: document.getElementById('paymentAmount').value,
        payment_date: document.getElementById('paymentDate').value,
        reference_number: document.getElementById('paymentReferenceNumber').value,
        notes: document.getElementById('paymentNotes').value,
        created_by: 'current_user' // Aquí podrías obtener el usuario actual
    };
    
    fetch(`${API_PAYMENT_URL}?action=createContractorPayment`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(result => {
        closeModal('paymentModal');
        if (result.success) {
            showToast(result.message, 'success');
            // Actualizar balances si es necesario
            loadBankAccountsForPayment();
        } else {
            showToast(result.error || 'Error al registrar el pago', 'error');
        }
    })
    .catch(err => {
        console.error('Error:', err);
        showToast('Error de conexión', 'error');
    });
});

// ============================================================================
// FUNCIONES DE GESTIÓN DE ESTADO
// ============================================================================

function toggleContractorStatus(contractorId, newStatus, contractorName) {
    const action = newStatus === 1 ? 'activar' : 'desactivar';
    const actionCapitalized = newStatus === 1 ? 'Activar' : 'Desactivar';
    
    // Configurar modal de confirmación
    document.getElementById('confirmStatusMessage').textContent = 
        `¿Está seguro de que desea ${action} el contratista "${contractorName}"?`;
    
    const details = newStatus === 1 
        ? 'El contratista aparecerá en las listas de selección para nuevos ingresos.'
        : 'El contratista no aparecerá en las listas de selección para nuevos ingresos.';
    
    document.getElementById('confirmStatusDetails').textContent = details;
    
    // Configurar botón de confirmación
    const confirmBtn = document.getElementById('confirmStatusBtn');
    const confirmIcon = document.getElementById('confirmStatusIcon');
    const confirmText = document.getElementById('confirmStatusBtnText');
    
    confirmBtn.className = newStatus === 1 ? 'btn btn-success' : 'btn btn-warning';
    confirmIcon.className = newStatus === 1 ? 'fas fa-check' : 'fas fa-pause';
    confirmText.textContent = actionCapitalized;
    
    // Configurar evento del botón
    confirmBtn.onclick = () => confirmToggleContractorStatus(contractorId, contractorName);
    
    // Mostrar modal
    openModal('confirmStatusChangeModal');
}

function confirmToggleContractorStatus(contractorId, contractorName) {
    fetch(`${API_URL}?action=toggleContractorStatus&id=${contractorId}`, {
        method: 'POST'
    })
    .then(res => res.json())
    .then(result => {
        closeModal('confirmStatusChangeModal');
        
        if (result.success) {
            const statusText = result.new_status === 'active' ? 'activado' : 'desactivado';
            showToast(`Contratista "${contractorName}" ${statusText} exitosamente.`, 'success');
            loadContractors(currentPage);
        } else {
            showToast(result.error || 'Error al cambiar el estado del contratista.', 'error');
        }
    })
    .catch(err => {
        console.error('Error:', err);
        closeModal('confirmStatusChangeModal');
        showToast('Error de conexión al cambiar el estado.', 'error');
    });
}

// ============================================================================
// FUNCIONES DE FILTROS
// ============================================================================

function toggleStatusFilterDropdown() {
    const dropdown = document.getElementById('statusFilterDropdown');
    dropdown.classList.toggle('show');
    
    // Cerrar otros dropdowns si están abiertos
    document.addEventListener('click', function closeDropdown(e) {
        if (!e.target.closest('.filter-dropdown-container')) {
            dropdown.classList.remove('show');
            document.removeEventListener('click', closeDropdown);
        }
    });
}

function applyStatusFilters() {
    const statusValue = document.getElementById('statusFilter').value;
    
    activeStatusFilters.status = statusValue;
    
    // Actualizar UI de filtros activos
    updateActiveFiltersDisplay();
    
    // Cerrar dropdown
    document.getElementById('statusFilterDropdown').classList.remove('show');
    
    // Recargar datos
    loadContractors(1);
}

function clearStatusFilters() {
    // Limpiar filtros
    activeStatusFilters.status = '';
    
    // Resetear controles
    document.getElementById('statusFilter').value = '';
    
    // Actualizar UI
    updateActiveFiltersDisplay();
    
    // Cerrar dropdown
    document.getElementById('statusFilterDropdown').classList.remove('show');
    
    // Recargar datos
    loadContractors(1);
}

function updateActiveFiltersDisplay() {
    const container = document.getElementById('activeStatusFiltersContainer');
    const countElement = document.getElementById('activeStatusFiltersCount');
    
    container.innerHTML = '';
    let activeCount = 0;
    
    // Filtro de estado
    if (activeStatusFilters.status !== '') {
        activeCount++;
        const statusText = activeStatusFilters.status === '1' ? 'Solo activos' : 'Solo inactivos';
        const filterBtn = document.createElement('div');
        filterBtn.className = 'active-filter-btn';
        filterBtn.innerHTML = `
            ${statusText}
            <i class="fas fa-times" onclick="removeStatusFilter()"></i>
        `;
        container.appendChild(filterBtn);
    }
    
    // Mostrar/ocultar contenedor y contador
    if (activeCount > 0) {
        container.style.display = 'flex';
        countElement.style.display = 'flex';
        countElement.textContent = activeCount;
    } else {
        container.style.display = 'none';
        countElement.style.display = 'none';
    }
}

function removeStatusFilter() {
    activeStatusFilters.status = '';
    document.getElementById('statusFilter').value = '';
    updateActiveFiltersDisplay();
    loadContractors(1);
}

// ============================================================================
// CONFIGURACIÓN DE COMPONENTES
// ============================================================================

function setupStatusSwitch() {
    const statusCheckbox = document.getElementById('contractorStatus');
    const statusLabel = document.getElementById('contractorStatusLabel');
    
    if (statusCheckbox && statusLabel) {
        statusCheckbox.addEventListener('change', function() {
            const isActive = this.checked;
            statusLabel.textContent = isActive ? 'Activo' : 'Inactivo';
            statusLabel.style.color = isActive ? 'var(--success-color)' : 'var(--text-secondary)';
        });
    }
}

function setupSearchInput() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                loadContractors(1);
            }, 300); // Debounce de 300ms
        });
    }
}
