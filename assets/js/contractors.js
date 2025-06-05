// --- Configuración ---
const API_URL = 'api/contractor/ContractorController.php';
const API_PAYMENT_URL = 'api/contractor/ContractorPaymentController.php';
let editingContractorId = null;
let sortField = 'created_at';
let sortDir = 'desc';

// --- Cargar contratistas al iniciar ---
document.addEventListener('DOMContentLoaded', function() {
    loadContractors();
    loadContractorsForPayment();
    loadBankAccountsForPayment();
    // Establecer fecha actual por defecto
    document.getElementById('paymentDate').value = new Date().toISOString().split('T')[0];
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

function loadContractors(page = 1) {
    currentPage = page;
    setTableLoading(true);
    fetch(`${API_URL}?action=getAllContractors&limit=${pageSize}&offset=${(page-1)*pageSize}&sort=${sortField}&dir=${sortDir}`)
        .then(res => res.json())
        .then(data => {
            const contractors = data.data || data;
            totalContractorsCount = data.total || contractors.length;
            renderContractorsTable(contractors);
            renderPagination();
        })
        .catch(() => {
            document.getElementById('contractorsTableBody').innerHTML = '<tr><td colspan="5">Error al cargar contratistas</td></tr>';
        })
        .finally(() => setTableLoading(false));
}

function setTableLoading(loading) {
    const tbody = document.getElementById('contractorsTableBody');
    if (loading) {
        tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; padding:40px 0;">
            <div class="loading-spinner"></div>
            <span style="display:block; margin-top:8px; color:var(--text-secondary);">Cargando contratistas...</span>
        </td></tr>`;
    }
}

function renderContractorsTable(contractors) {
    const tbody = document.getElementById('contractorsTableBody');
    tbody.innerHTML = '';
    if (!contractors.length) {
        tbody.innerHTML = '<tr><td colspan="5">No hay contratistas registrados</td></tr>';
        document.getElementById('totalContractors').textContent = '0';
        return;
    }
    document.getElementById('totalContractors').textContent = contractors.length;
    contractors.forEach(contractor => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${contractor.name}</td>
            <td>${contractor.email || ''}</td>
            <td>${contractor.phone || ''}</td>
            <td>${contractor.address || ''}</td>
            <td style="vertical-align: middle; text-align: center;">
                <div style="display: flex; gap: 4px; justify-content: center; align-items: center;">
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
        address: document.getElementById('contractorAddress').value
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
    fetch(`${API_URL}?action=getAllContractors`)
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
            accounts.forEach(account => {
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
