// --- Configuración ---
const API_URL = 'api/vendor/VendorController.php';
let editingVendorId = null;
let sortField = 'created_at';
let sortDir = 'desc';

// --- Cargar proveedores al iniciar ---
document.addEventListener('DOMContentLoaded', loadVendors);

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

function loadVendors(page = 1) {
    currentPage = page;
    setTableLoading(true);
    fetch(`${API_URL}?action=getAllVendors&limit=${pageSize}&offset=${(page-1)*pageSize}&sort=${sortField}&dir=${sortDir}`)
        .then(res => res.json())
        .then(data => {
            const vendors = data.data || data;
            totalVendorsCount = data.total || vendors.length;
            renderVendorsTable(vendors);
            renderPagination();
        })
            .catch(() => {
        document.getElementById('vendorsTableBody').innerHTML = '<tr><td colspan="5">Error al cargar proveedores</td></tr>';
    })
        .finally(() => setTableLoading(false));
}

function setTableLoading(loading) {
    const tbody = document.getElementById('vendorsTableBody');
    if (loading) {
        tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; padding:40px 0;">
            <div class="loading-spinner"></div>
            <span style="display:block; margin-top:8px; color:var(--text-secondary);">Cargando proveedores...</span>
        </td></tr>`;
    }
}

function renderVendorsTable(vendors) {
    const tbody = document.getElementById('vendorsTableBody');
    tbody.innerHTML = '';
    if (!vendors.length) {
        tbody.innerHTML = '<tr><td colspan="5">No hay proveedores registrados</td></tr>';
        document.getElementById('totalVendors').textContent = '0';
        return;
    }
    document.getElementById('totalVendors').textContent = vendors.length;
    vendors.forEach(vendor => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${vendor.name}</td>
            <td>${vendor.email || ''}</td>
            <td>${vendor.phone || ''}</td>
            <td>${vendor.address || ''}</td>
            <td style="vertical-align: middle; text-align: center;">
                <div style="display: flex; gap: 4px; justify-content: center; align-items: center;">
                    <button type="button" class="btn-icon" onclick="editVendor('${vendor.id}')" title="Editar">
                        <i class="fas fa-edit"></i>
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
    container.innerHTML = '';
    const totalPages = Math.ceil(totalVendorsCount / pageSize);
    if (totalPages <= 1) { container.style.display = 'none'; return; }
    container.style.display = 'flex';
    for (let i = 1; i <= totalPages; i++) {
        const btn = document.createElement('button');
        btn.className = 'btn' + (i === currentPage ? ' btn-primary' : '');
        btn.textContent = i;
        btn.style.minWidth = '36px';
        btn.onclick = () => loadVendors(i);
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
    if (modalId === 'vendorModal') {
        document.getElementById('vendorForm').reset();
        document.getElementById('modalTitle').textContent = 'Nuevo Proveedor';
        editingVendorId = null;
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

function editVendor(id) {
    fetch(`${API_URL}?action=getVendorById&id=${id}`)
        .then(res => res.json())
        .then(vendor => {
            document.getElementById('modalTitle').textContent = 'Editar Proveedor';
            document.getElementById('vendorName').value = vendor.name || '';
            document.getElementById('vendorEmail').value = vendor.email || '';
            document.getElementById('vendorPhone').value = vendor.phone || '';
            document.getElementById('vendorAddress').value = vendor.address || '';
            editingVendorId = vendor.id;
            openModal('vendorModal');
        });
}

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
        address: document.getElementById('vendorAddress').value
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
        closeModal('vendorModal');
        if (result && result.error) {
            showToast('Ocurrió un error al guardar el proveedor.', 'error');
        } else {
            showToast(isEdit ? 'Proveedor editado con éxito.' : 'Proveedor creado con éxito.', 'success');
        }
        loadVendors();
    })
    .catch(() => {
        showToast('Ocurrió un error al guardar el proveedor.', 'error');
    });
});

// --- Filtro de búsqueda local por nombre de proveedor ---
document.getElementById('searchInput').addEventListener('input', function() {
    const search = this.value.trim().toLowerCase();
    const rows = document.querySelectorAll('#vendorsTableBody tr');
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
    document.getElementById('totalVendors').textContent = count;
});

// --- Sort interactivo en la tabla ---
document.addEventListener('DOMContentLoaded', function() {
    const ths = document.querySelectorAll('#vendorsTable thead th');
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
                loadVendors(1);
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


