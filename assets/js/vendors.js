// --- Configuración ---
const API_URL = 'api/vendor/VendorController.php';
let editingVendorId = null;
let sortField = 'created_at';
let sortDir = 'desc';

// Variables para filtros de estado
let statusFilter = '';
let tempStatusFilter = '';

// --- Cargar proveedores al iniciar ---
document.addEventListener('DOMContentLoaded', function() {
    loadVendors();
    setupStatusFilterEventListeners();
    
    // Configurar ordenamiento después de un delay para asegurar que el DOM esté listo
    setTimeout(() => {
        setupTableSorting();
    }, 100);
});

// --- Paginación ---
let currentPage = 1;
let pageSize = 10;
let totalVendorsCount = 0;

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
        loadVendors(1);
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
            loadVendors(1);
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

function loadVendors(page = 1) {
    currentPage = page;
    setTableLoading(true);
    
    let url = `${API_URL}?action=getAllVendors&limit=${pageSize}&offset=${(page-1)*pageSize}&sort=${sortField}&dir=${sortDir}`;
    
    // Agregar filtro de estado si está activo
    if (statusFilter !== '') {
        url += `&status=${statusFilter}`;
    }
    
    fetch(url)
        .then(res => res.json())
        .then(data => {
            const vendors = data.data || data;
            totalVendorsCount = data.total || vendors.length;
            document.getElementById('totalVendors').textContent = totalVendorsCount;
            
            renderVendorsTable(vendors);
            renderPagination();
            updateActiveStatusFiltersDisplay();
            
            // Actualizar iconos de ordenamiento
            updateSortIcons();
        })
        .catch(() => {
            document.getElementById('vendorsTableBody').innerHTML = '<tr><td colspan="6">Error al cargar proveedores</td></tr>';
            const footerContainer = document.getElementById('vendorsTableFooter');
            if (footerContainer) {
                footerContainer.style.display = 'none';
            }
        })
        .finally(() => setTableLoading(false));
}

function setTableLoading(loading) {
    const tbody = document.getElementById('vendorsTableBody');
    if (loading) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:40px 0;">
            <div class="loading-spinner"></div>
            <span style="display:block; margin-top:8px; color:var(--text-secondary);">Cargando proveedores...</span>
        </td></tr>`;
    }
}

// Función para formatear el estado del proveedor
function formatVendorStatus(status) {
    const isActive = status === 1 || status === '1' || status === true;
    if (isActive) {
        return '<span class="status-badge status-active">Activo</span>';
    } else {
        return '<span class="status-badge status-inactive">Inactivo</span>';
    }
}

function renderVendorsTable(vendors) {
    const tbody = document.getElementById('vendorsTableBody');
    tbody.innerHTML = '';
    if (!vendors.length) {
        tbody.innerHTML = '<tr><td colspan="6">No hay proveedores registrados</td></tr>';
        return;
    }
    
    vendors.forEach(vendor => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <a href="vendor_details.php?id=${vendor.id}" class="vendor-name-link" title="Ver detalles del proveedor">
                    ${vendor.name}
                </a>
            </td>
            <td>${vendor.email || '-'}</td>
            <td>${vendor.phone || '-'}</td>
            <td>${vendor.address || '-'}</td>
            <td>${formatVendorStatus(vendor.status)}</td>
            <td style="text-align: center;">
                <div style="display: flex; gap: 4px; justify-content: center; align-items: center;">
                    <button type="button" class="btn-icon" onclick="viewVendor('${vendor.id}')" title="Ver detalles">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button type="button" class="btn-icon" onclick="editVendor('${vendor.id}')" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button type="button" class="btn-icon ${vendor.status == 1 ? 'btn-warning' : 'btn-success'}" onclick="toggleVendorStatus('${vendor.id}', ${vendor.status})" title="${vendor.status == 1 ? 'Desactivar' : 'Activar'}">
                        <i class="fas fa-${vendor.status == 1 ? 'ban' : 'check'}"></i>
                    </button>
                    <button type="button" class="btn-icon btn-danger" onclick="deleteVendor('${vendor.id}')" title="Eliminar">
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
    const container = document.getElementById('vendorsPagination');
    if (!container) return;
    
    const totalPages = Math.ceil(totalVendorsCount / pageSize);
    if (totalPages <= 1) {
        container.innerHTML = '';
        return;
    }
    
    let paginationHTML = '';
    
    // Botón anterior
    if (currentPage > 1) {
        paginationHTML += `<button class="pagination-btn" onclick="loadVendors(${currentPage - 1})">
            <i class="fas fa-chevron-left"></i>
        </button>`;
    }
    
    // Números de página
    const startPage = Math.max(1, currentPage - 2);
    const endPage = Math.min(totalPages, currentPage + 2);
    
    if (startPage > 1) {
        paginationHTML += `<button class="pagination-btn" onclick="loadVendors(1)">1</button>`;
        if (startPage > 2) {
            paginationHTML += `<span class="pagination-ellipsis">...</span>`;
        }
    }
    
    for (let i = startPage; i <= endPage; i++) {
        paginationHTML += `<button class="pagination-btn ${i === currentPage ? 'active' : ''}" onclick="loadVendors(${i})">${i}</button>`;
    }
    
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            paginationHTML += `<span class="pagination-ellipsis">...</span>`;
        }
        paginationHTML += `<button class="pagination-btn" onclick="loadVendors(${totalPages})">${totalPages}</button>`;
    }
    
    // Botón siguiente
    if (currentPage < totalPages) {
        paginationHTML += `<button class="pagination-btn" onclick="loadVendors(${currentPage + 1})">
            <i class="fas fa-chevron-right"></i>
        </button>`;
    }
    
    container.innerHTML = paginationHTML;
}

function openModal(modalId) {
    if (modalId === 'vendorModal') {
        setModalToCreateMode();
    }
    document.getElementById(modalId).style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
    document.body.style.overflow = 'auto';
    if (modalId === 'vendorModal') {
        document.getElementById('vendorForm').reset();
        document.getElementById('modalTitle').textContent = 'Nuevo Proveedor';
        editingVendorId = null;
    }
}

// Función para configurar el modal en modo creación
function setModalToCreateMode() {
    // Configurar estado por defecto (activo)
    const vendorStatusSwitch = document.getElementById('vendorStatus');
    const vendorStatusLabel = document.getElementById('vendorStatusLabel');
    if (vendorStatusSwitch && vendorStatusLabel) {
        vendorStatusSwitch.checked = true;
        vendorStatusLabel.textContent = 'Activo';
        vendorStatusLabel.style.color = 'var(--success-color)';
        
        // Agregar event listener para el switch
        vendorStatusSwitch.removeEventListener('change', handleVendorStatusChange);
        vendorStatusSwitch.addEventListener('change', handleVendorStatusChange);
    }
}

// Función para manejar el cambio del switch de estado
function handleVendorStatusChange() {
    const vendorStatusLabel = document.getElementById('vendorStatusLabel');
    if (this.checked) {
        vendorStatusLabel.textContent = 'Activo';
        vendorStatusLabel.style.color = 'var(--success-color)';
    } else {
        vendorStatusLabel.textContent = 'Inactivo';
        vendorStatusLabel.style.color = 'var(--danger-color)';
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
    const toastMessage = document.getElementById('toastMessage');
    const toastIcon = document.getElementById('toastIcon');
    
    toastMessage.textContent = message;
    
    // Configurar icono y color según el tipo
    if (type === 'success') {
        toastIcon.innerHTML = '<i class="fas fa-check-circle"></i>';
        toast.style.background = 'var(--success-color, #10b981)';
    } else if (type === 'error') {
        toastIcon.innerHTML = '<i class="fas fa-exclamation-circle"></i>';
        toast.style.background = 'var(--danger-color, #ef4444)';
    } else if (type === 'warning') {
        toastIcon.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
        toast.style.background = 'var(--warning-color, #f59e0b)';
    }
    
    toast.style.display = 'flex';
    
    setTimeout(() => {
        toast.style.display = 'none';
    }, 3000);
}

function viewVendor(id) {
    window.location.href = `vendor_details.php?id=${id}`;
}

function editVendor(id) {
    editingVendorId = id;
    fetch(`${API_URL}?action=getVendorById&id=${id}`)
        .then(res => res.json())
        .then(vendor => {
            document.getElementById('modalTitle').textContent = 'Editar Proveedor';
            document.getElementById('vendorName').value = vendor.name || '';
            document.getElementById('vendorEmail').value = vendor.email || '';
            document.getElementById('vendorPhone').value = vendor.phone || '';
            document.getElementById('vendorAddress').value = vendor.address || '';
            
            // Configurar estado del proveedor
            const vendorStatusSwitch = document.getElementById('vendorStatus');
            const vendorStatusLabel = document.getElementById('vendorStatusLabel');
            if (vendorStatusSwitch && vendorStatusLabel) {
                const isActive = vendor.status === 1 || vendor.status === '1' || vendor.status === true;
                vendorStatusSwitch.checked = isActive;
                vendorStatusLabel.textContent = isActive ? 'Activo' : 'Inactivo';
                vendorStatusLabel.style.color = isActive ? 'var(--success-color)' : 'var(--danger-color)';
                
                // Agregar event listener para el switch
                vendorStatusSwitch.removeEventListener('change', handleVendorStatusChange);
                vendorStatusSwitch.addEventListener('change', handleVendorStatusChange);
            }
            
            openModal('vendorModal');
        });
}

// --- Función para cambiar estado del proveedor (activar/desactivar) ---
let vendorDataToToggle = null;

function toggleVendorStatus(vendorId, currentStatus) {
    // Obtener información del proveedor para mostrar en el modal
    fetch(`${API_URL}?action=getVendorById&id=${vendorId}`)
        .then(res => res.json())
        .then(vendor => {
            showStatusChangeModal(vendorId, currentStatus, vendor);
        })
        .catch(() => {
            showToast('Error al obtener información del proveedor.', 'error');
        });
}

function showStatusChangeModal(vendorId, currentStatus, vendor) {
    const newStatus = currentStatus == 1 ? 0 : 1;
    const isActivating = newStatus == 1;
    
    // Guardar datos para la confirmación
    vendorDataToToggle = {
        id: vendorId,
        currentStatus: currentStatus,
        newStatus: newStatus,
        vendor: vendor
    };
    
    // Configurar contenido del modal
    const title = document.getElementById('confirmStatusTitle');
    const message = document.getElementById('confirmStatusMessage');
    const details = document.getElementById('confirmStatusDetails');
    const btn = document.getElementById('confirmStatusBtn');
    const btnText = document.getElementById('confirmStatusBtnText');
    const btnIcon = document.getElementById('confirmStatusIcon');
    
    title.textContent = isActivating ? 'Activar Proveedor' : 'Desactivar Proveedor';
    message.textContent = `¿Está seguro de que desea ${isActivating ? 'activar' : 'desactivar'} el proveedor "${vendor.name}"?`;
    
    if (isActivating) {
        details.innerHTML = '<i class="fas fa-info-circle"></i> El proveedor estará disponible para todas las operaciones y aparecerá en las listas de selección.';
        btn.className = 'btn btn-success';
        btnText.textContent = 'Activar';
        btnIcon.className = 'fas fa-check';
    } else {
        details.innerHTML = '<i class="fas fa-exclamation-triangle"></i> El proveedor no aparecerá en las listas de selección para nuevos gastos.';
        btn.className = 'btn btn-warning';
        btnText.textContent = 'Desactivar';
        btnIcon.className = 'fas fa-ban';
    }
    
    // Mostrar modal
    document.getElementById('confirmStatusChangeModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function confirmStatusChange() {
    if (!vendorDataToToggle) return;
    
    const { id, newStatus } = vendorDataToToggle;
    
    // Mostrar loading en el botón
    const btn = document.getElementById('confirmStatusBtn');
    const originalContent = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
    btn.disabled = true;
    
    fetch(`${API_URL}?action=toggleVendorStatus&id=${id}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ status: newStatus })
    })
    .then(res => res.json())
    .then(result => {
        if (result && result.error) {
            closeModal('confirmStatusChangeModal');
            const errorMessage = result.message || result.error || 'Error al cambiar el estado del proveedor.';
            showToast(errorMessage, 'error');
        } else if (result && result.message) {
            // Éxito
            closeModal('confirmStatusChangeModal');
            showToast(`Proveedor ${newStatus == 1 ? 'activado' : 'desactivado'} con éxito.`, 'success');
            loadVendors(currentPage);
        } else {
            // Respuesta inesperada
            closeModal('confirmStatusChangeModal');
            showToast('Respuesta inesperada del servidor.', 'error');
        }
    })
    .catch(() => {
        closeModal('confirmStatusChangeModal');
        showToast('Error al cambiar el estado del proveedor.', 'error');
    })
    .finally(() => {
        // Restaurar botón
        btn.innerHTML = originalContent;
        btn.disabled = false;
        vendorDataToToggle = null;
    });
}

// Event listener para el botón de confirmación de cambio de estado
document.getElementById('confirmStatusBtn').onclick = function() {
    confirmStatusChange();
};

let vendorIdToDelete = null;
function showDeleteModal(id) {
    vendorIdToDelete = id;
    document.getElementById('confirmDeleteModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
document.getElementById('confirmDeleteBtn').onclick = function() {
    if (vendorIdToDelete) {
        deleteVendorConfirmed(vendorIdToDelete);
        vendorIdToDelete = null;
        closeModal('confirmDeleteModal');
    }
};
function deleteVendor(id) {
    showDeleteModal(id);
}
function deleteVendorConfirmed(id) {
    fetch(`${API_URL}?action=deleteVendor&id=${id}`, { method: 'DELETE' })
        .then(res => res.json())
        .then(result => {
            if (result && result.error) {
                showNotification('No se puede eliminar el proveedor.\n\nDetalle: ' + result.error, 'Error al eliminar proveedor');
                showToast('No se pudo eliminar el proveedor.', 'error');
                return;
            }
            showToast('Proveedor eliminado con éxito.', 'success');
            setTimeout(() => {
                fetch(`${API_URL}?action=getAllVendors&limit=${pageSize}&offset=${(currentPage-1)*pageSize}`)
                    .then(res => res.json())
                    .then(data => {
                        const vendors = data.data || data;
                        if (vendors.length === 0 && currentPage > 1) {
                            loadVendors(currentPage - 1);
                        } else {
                            loadVendors(currentPage);
                        }
                    });
            }, 200);
        })
        .catch(() => {
            showToast('Ocurrió un error al eliminar el proveedor.', 'error');
        });
}

document.getElementById('vendorForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const data = {
        name: document.getElementById('vendorName').value,
        email: document.getElementById('vendorEmail').value,
        phone: document.getElementById('vendorPhone').value,
        address: document.getElementById('vendorAddress').value,
        status: document.getElementById('vendorStatus').checked ? 1 : 0
    };
    let url = API_URL;
    let method = 'POST';
    let isEdit = false;
    if (editingVendorId) {
        url += `?action=updateVendor&id=${editingVendorId}`;
        method = 'PUT';
        isEdit = true;
    } else {
        url += '?action=createVendor';
    }
    fetch(url, {
        method: method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(result => {
        if (result && result.error) {
            showToast(result.error, 'error');
        } else {
            closeModal('vendorModal');
            showToast(isEdit ? 'Proveedor editado con éxito.' : 'Proveedor creado con éxito.', 'success');
            loadVendors();
        }
    })
    .catch(() => {
        showToast('Ocurrió un error al conectar con el servidor.', 'error');
    });
});

// --- Funciones para filtros de estado ---
function setupStatusFilterEventListeners() {
    // Cerrar dropdown al hacer clic fuera
    document.addEventListener('click', function(event) {
        const dropdown = document.getElementById('statusFilterDropdown');
        const button = document.getElementById('statusFilterDropdownBtn');
        
        if (dropdown && !dropdown.contains(event.target) && !button.contains(event.target)) {
            dropdown.classList.remove('show');
        }
    });
}

function toggleStatusFilterDropdown() {
    const dropdown = document.getElementById('statusFilterDropdown');
    dropdown.classList.toggle('show');
    
    if (dropdown.classList.contains('show')) {
        // Sincronizar valor temporal con el actual
        tempStatusFilter = statusFilter;
        document.getElementById('statusFilter').value = statusFilter;
    }
}

function applyStatusFilters() {
    statusFilter = tempStatusFilter;
    closeStatusFilterDropdown();
    loadVendors(1);
}

function clearStatusFilters() {
    statusFilter = '';
    tempStatusFilter = '';
    document.getElementById('statusFilter').value = '';
    closeStatusFilterDropdown();
    loadVendors(1);
}

function closeStatusFilterDropdown() {
    document.getElementById('statusFilterDropdown').classList.remove('show');
}

function updateActiveStatusFiltersDisplay() {
    const container = document.getElementById('activeStatusFiltersContainer');
    const countElement = document.getElementById('activeStatusFiltersCount');
    
    if (!container || !countElement) return;
    
    container.innerHTML = '';
    let filterCount = 0;
    
    if (statusFilter !== '') {
        filterCount++;
        const statusText = statusFilter === '1' ? 'Solo activos' : 'Solo inactivos';
        const filterBtn = document.createElement('div');
        filterBtn.className = 'active-filter-btn';
        filterBtn.innerHTML = `
            <span>Estado: ${statusText}</span>
            <i class="fas fa-times" onclick="removeStatusFilter()"></i>
        `;
        container.appendChild(filterBtn);
    }
    
    if (filterCount > 0) {
        container.style.display = 'flex';
        countElement.style.display = 'flex';
        countElement.textContent = filterCount;
    } else {
        container.style.display = 'none';
        countElement.style.display = 'none';
    }
}

function removeStatusFilter() {
    clearStatusFilters();
}

// Event listener para el filtro de estado
document.getElementById('statusFilter').addEventListener('change', function() {
    tempStatusFilter = this.value;
});


