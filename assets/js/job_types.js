// --- Configuración ---
const API_URL = 'api/job_type/JobTypeController.php';
let editingJobTypeId = null;
let sortField = 'created_at';
let sortDir = 'desc';

// --- Cargar tipos al iniciar ---
document.addEventListener('DOMContentLoaded', loadJobTypes);

// --- Paginación ---
let currentPage = 1;
let pageSize = 10;
let totalJobTypesCount = 0;

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
        loadJobTypes(1);
    };
    container.appendChild(label);
    container.appendChild(selector);
}

renderPageSizeSelector();

function loadJobTypes(page = 1) {
    currentPage = page;
    setTableLoading(true);
    fetch(`${API_URL}?action=getAllJobTypes&limit=${pageSize}&offset=${(page-1)*pageSize}&sort=${sortField}&dir=${sortDir}`)
        .then(res => res.json())
        .then(data => {
            const types = data.data || data;
            totalJobTypesCount = data.total || types.length;
            renderJobTypesTable(types);
            renderPagination();
        })
        .catch(() => {
            document.getElementById('jobTypesTableBody').innerHTML = '<tr><td colspan="3">Error al cargar tipos</td></tr>';
        })
        .finally(() => setTableLoading(false));
}

function setTableLoading(loading) {
    const tbody = document.getElementById('jobTypesTableBody');
    if (loading) {
        tbody.innerHTML = `<tr><td colspan="3" style="text-align:center; padding:40px 0;">
            <div class="loading-spinner"></div>
            <span style="display:block; margin-top:8px; color:var(--text-secondary);">Cargando tipos...</span>
        </td></tr>`;
    }
}

function renderJobTypesTable(types) {
    const tbody = document.getElementById('jobTypesTableBody');
    tbody.innerHTML = '';
    if (!types.length) {
        tbody.innerHTML = '<tr><td colspan="4">No hay tipos registrados</td></tr>';
        document.getElementById('totalJobTypes').textContent = '0';
        return;
    }
    document.getElementById('totalJobTypes').textContent = types.length;
    types.forEach(type => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${type.name}</td>
            <td>$${parseFloat(type.pay_as_contractor).toLocaleString('es-MX', {minimumFractionDigits:2})}</td>
            <td>$${parseFloat(type.pay_as_sub_contractor).toLocaleString('es-MX', {minimumFractionDigits:2})}</td>
            <td style="vertical-align: middle; text-align: center;">
                <div style="display: flex; gap: 4px; justify-content: center; align-items: center;">
                    <button type="button" class="btn-icon" onclick="editJobType('${type.id}')" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button type="button" class="btn-icon btn-danger" onclick="deleteJobType('${type.id}')" title="Eliminar">
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
    const container = document.getElementById('jobTypesPagination');
    if (!container) return;
    container.innerHTML = '';
    const totalPages = Math.ceil(totalJobTypesCount / pageSize);
    if (totalPages <= 1) { container.style.display = 'none'; return; }
    container.style.display = 'flex';
    for (let i = 1; i <= totalPages; i++) {
        const btn = document.createElement('button');
        btn.className = 'btn' + (i === currentPage ? ' btn-primary' : '');
        btn.textContent = i;
        btn.style.minWidth = '36px';
        btn.onclick = () => loadJobTypes(i);
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
    if (modalId === 'jobTypeModal') {
        document.getElementById('jobTypeForm').reset();
        document.getElementById('modalTitle').textContent = 'Nuevo Tipo de Trabajo';
        editingJobTypeId = null;
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

function editJobType(id) {
    fetch(`${API_URL}?action=getJobTypeById&id=${id}`)
        .then(res => res.json())
        .then(type => {
            document.getElementById('modalTitle').textContent = 'Editar Tipo de Trabajo';
            document.getElementById('jobTypeName').value = type.name || '';
            document.getElementById('payAsContractor').value = type.pay_as_contractor || '0.00';
            document.getElementById('payAsSubContractor').value = type.pay_as_sub_contractor || '0.00';
            editingJobTypeId = type.id;
            openModal('jobTypeModal');
        });
}

let jobTypeIdToDelete = null;
function showDeleteModal(id) {
    jobTypeIdToDelete = id;
    document.getElementById('confirmDeleteModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
document.getElementById('confirmDeleteBtn').onclick = function() {
    if (jobTypeIdToDelete) {
        deleteJobTypeConfirmed(jobTypeIdToDelete);
        jobTypeIdToDelete = null;
        closeModal('confirmDeleteModal');
    }
};
function deleteJobType(id) {
    showDeleteModal(id);
}
function deleteJobTypeConfirmed(id) {
    fetch(`${API_URL}?action=deleteJobType&id=${id}`, { method: 'DELETE' })
        .then(res => res.json())
        .then(result => {
            if (result && result.error) {
                showNotification('No se puede eliminar el tipo de trabajo.\n\nDetalle: ' + result.error, 'Error al eliminar tipo');
                showToast('No se pudo eliminar el tipo.', 'error');
                return;
            }
            showToast('Tipo de trabajo eliminado con éxito.', 'success');
            setTimeout(() => {
                fetch(`${API_URL}?action=getAllJobTypes&limit=${pageSize}&offset=${(currentPage-1)*pageSize}`)
                    .then(res => res.json())
                    .then(data => {
                        const types = data.data || data;
                        if (types.length === 0 && currentPage > 1) {
                            loadJobTypes(currentPage - 1);
                        } else {
                            loadJobTypes(currentPage);
                        }
                    });
            }, 200);
        })
        .catch(() => {
            showToast('Ocurrió un error al eliminar el tipo.', 'error');
        });
}

document.getElementById('jobTypeForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const data = {
        name: document.getElementById('jobTypeName').value,
        pay_as_contractor: document.getElementById('payAsContractor').value,
        pay_as_sub_contractor: document.getElementById('payAsSubContractor').value
    };
    let url = API_URL;
    let method = 'POST';
    let isEdit = false;
    if (editingJobTypeId) {
        url += `?action=updateJobType&id=${editingJobTypeId}`;
        method = 'PUT';
        isEdit = true;
    } else {
        url += '?action=createJobType';
    }
    fetch(url, {
        method: method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(result => {
        closeModal('jobTypeModal');
        if (result && result.error) {
            showToast('Ocurrió un error al guardar el tipo.', 'error');
        } else {
            showToast(isEdit ? 'Tipo editado con éxito.' : 'Tipo creado con éxito.', 'success');
        }
        loadJobTypes();
    })
    .catch(() => {
        showToast('Ocurrió un error al guardar el tipo.', 'error');
    });
});

// --- Filtro de búsqueda local por nombre ---
document.getElementById('searchInput').addEventListener('input', function() {
    const search = this.value.trim().toLowerCase();
    const rows = document.querySelectorAll('#jobTypesTableBody tr');
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
    document.getElementById('totalJobTypes').textContent = count;
});

// --- Sort interactivo en la tabla ---
document.addEventListener('DOMContentLoaded', function() {
    const ths = document.querySelectorAll('#jobTypesTable thead th');
    ths.forEach((th, idx) => {
        if (idx < 2) { // Solo para columnas principales
            th.style.cursor = 'pointer';
            th.addEventListener('click', function() {
                const fields = ['name', 'description'];
                const field = fields[idx];
                if (sortField === field) {
                    sortDir = sortDir === 'asc' ? 'desc' : 'asc';
                } else {
                    sortField = field;
                    sortDir = 'asc';
                }
                loadJobTypes(1);
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
