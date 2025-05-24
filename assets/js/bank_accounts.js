// --- Configuración ---
const API_URL = 'api/bank_account/BankAccountController.php';
let editingBankAccountId = null;
let sortField = 'created_at';
let sortDir = 'desc';

// --- Cargar cuentas al iniciar ---
document.addEventListener('DOMContentLoaded', loadBankAccounts);

// --- Paginación ---
let currentPage = 1;
let pageSize = 10;
let totalBankAccountsCount = 0;

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
        loadBankAccounts(1);
    };
    container.appendChild(label);
    container.appendChild(selector);
}

renderPageSizeSelector();

function loadBankAccounts(page = 1) {
    currentPage = page;
    setTableLoading(true);
    fetch(`${API_URL}?action=getAllBankAccounts&limit=${pageSize}&offset=${(page-1)*pageSize}&sort=${sortField}&dir=${sortDir}`)
        .then(res => res.json())
        .then(data => {
            const accounts = data.data || data;
            totalBankAccountsCount = data.total || accounts.length;
            renderBankAccountsTable(accounts);
            renderPagination();
        })
        .catch(() => {
            document.getElementById('bankAccountsTableBody').innerHTML = '<tr><td colspan="6">Error al cargar cuentas</td></tr>';
        })
        .finally(() => setTableLoading(false));
}

function setTableLoading(loading) {
    const tbody = document.getElementById('bankAccountsTableBody');
    if (loading) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:40px 0;">
            <div class="loading-spinner"></div>
            <span style="display:block; margin-top:8px; color:var(--text-secondary);">Cargando cuentas...</span>
        </td></tr>`;
    }
}

function renderBankAccountsTable(accounts) {
    const tbody = document.getElementById('bankAccountsTableBody');
    tbody.innerHTML = '';
    if (!accounts.length) {
        tbody.innerHTML = '<tr><td colspan="6">No hay cuentas bancarias registradas</td></tr>';
        document.getElementById('totalBankAccounts').textContent = '0';
        return;
    }
    document.getElementById('totalBankAccounts').textContent = accounts.length;
    accounts.forEach(account => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${account.name}</td>
            <td>${account.bank_name}</td>
            <td>${account.account_number}</td>
            <td>${account.account_type === 'checking' ? 'Cheques' : account.account_type === 'savings' ? 'Ahorros' : 'Empresarial'}</td>
            <td>$${parseFloat(account.balance).toLocaleString('es-MX', {minimumFractionDigits:2})}</td>
            <td style="vertical-align: middle; text-align: center;">
                <div style="display: flex; gap: 4px; justify-content: center; align-items: center;">
                    <button type="button" class="btn-icon" onclick="viewBankAccount('${account.id}')" title="Ver"><i class="fas fa-eye"></i></button>
                    <button type="button" class="btn-icon" onclick="editBankAccount('${account.id}')" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button type="button" class="btn-icon btn-danger" onclick="deleteBankAccount('${account.id}')" title="Eliminar">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });
    renderPageSizeSelector();
}

function viewBankAccount(id) {
    window.location.href = `bank_account_detail.php?id=${encodeURIComponent(id)}`;
}

function renderPagination() {
    const container = document.getElementById('bankAccountsPagination');
    if (!container) return;
    container.innerHTML = '';
    const totalPages = Math.ceil(totalBankAccountsCount / pageSize);
    if (totalPages <= 1) { container.style.display = 'none'; return; }
    container.style.display = 'flex';
    for (let i = 1; i <= totalPages; i++) {
        const btn = document.createElement('button');
        btn.className = 'btn' + (i === currentPage ? ' btn-primary' : '');
        btn.textContent = i;
        btn.style.minWidth = '36px';
        btn.onclick = () => loadBankAccounts(i);
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
    if (modalId === 'bankAccountModal') {
        document.getElementById('bankAccountForm').reset();
        document.getElementById('modalTitle').textContent = 'Nueva Cuenta Bancaria';
        editingBankAccountId = null;
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

function editBankAccount(id) {
    fetch(`${API_URL}?action=getBankAccountById&id=${id}`)
        .then(res => res.json())
        .then(account => {
            document.getElementById('modalTitle').textContent = 'Editar Cuenta Bancaria';
            document.getElementById('bankAccountName').value = account.name || '';
            document.getElementById('bankName').value = account.bank_name || '';
            document.getElementById('accountNumber').value = account.account_number || '';
            document.getElementById('accountType').value = account.account_type || 'checking';
            document.getElementById('balance').value = account.balance || '0.00';
            editingBankAccountId = account.id;
            openModal('bankAccountModal');
        });
}

let bankAccountIdToDelete = null;
function showDeleteModal(id) {
    bankAccountIdToDelete = id;
    document.getElementById('confirmDeleteModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
document.getElementById('confirmDeleteBtn').onclick = function() {
    if (bankAccountIdToDelete) {
        deleteBankAccountConfirmed(bankAccountIdToDelete);
        bankAccountIdToDelete = null;
        closeModal('confirmDeleteModal');
    }
};
function deleteBankAccount(id) {
    showDeleteModal(id);
}
function deleteBankAccountConfirmed(id) {
    fetch(`${API_URL}?action=deleteBankAccount&id=${id}`, { method: 'DELETE' })
        .then(res => res.json())
        .then(result => {
            if (result && result.error) {
                showNotification('No se puede eliminar la cuenta bancaria.\n\nDetalle: ' + result.error, 'Error al eliminar cuenta');
                showToast('No se pudo eliminar la cuenta.', 'error');
                return;
            }
            showToast('Cuenta bancaria eliminada con éxito.', 'success');
            setTimeout(() => {
                fetch(`${API_URL}?action=getAllBankAccounts&limit=${pageSize}&offset=${(currentPage-1)*pageSize}`)
                    .then(res => res.json())
                    .then(data => {
                        const accounts = data.data || data;
                        if (accounts.length === 0 && currentPage > 1) {
                            loadBankAccounts(currentPage - 1);
                        } else {
                            loadBankAccounts(currentPage);
                        }
                    });
            }, 200);
        })
        .catch(() => {
            showToast('Ocurrió un error al eliminar la cuenta.', 'error');
        });
}

document.getElementById('bankAccountForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const data = {
        name: document.getElementById('bankAccountName').value,
        bank_name: document.getElementById('bankName').value,
        account_number: document.getElementById('accountNumber').value,
        account_type: document.getElementById('accountType').value,
        balance: document.getElementById('balance').value
    };
    let url = API_URL;
    let method = 'POST';
    let isEdit = false;
    if (editingBankAccountId) {
        url += `?action=updateBankAccount&id=${editingBankAccountId}`;
        method = 'PUT';
        isEdit = true;
    } else {
        url += '?action=createBankAccount';
    }
    fetch(url, {
        method: method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(result => {
        closeModal('bankAccountModal');
        if (result && result.error) {
            showToast('Ocurrió un error al guardar la cuenta.', 'error');
        } else {
            showToast(isEdit ? 'Cuenta editada con éxito.' : 'Cuenta creada con éxito.', 'success');
        }
        loadBankAccounts();
    })
    .catch(() => {
        showToast('Ocurrió un error al guardar la cuenta.', 'error');
    });
});

// --- Filtro de búsqueda local por nombre o banco ---
document.getElementById('searchInput').addEventListener('input', function() {
    const search = this.value.trim().toLowerCase();
    const rows = document.querySelectorAll('#bankAccountsTableBody tr');
    let count = 0;
    rows.forEach(row => {
        const name = row.children[0]?.textContent.toLowerCase() || '';
        const bank = row.children[1]?.textContent.toLowerCase() || '';
        if (name.includes(search) || bank.includes(search)) {
            row.style.display = '';
            count++;
        } else {
            row.style.display = 'none';
        }
    });
    document.getElementById('totalBankAccounts').textContent = count;
});

// --- Sort interactivo en la tabla ---
document.addEventListener('DOMContentLoaded', function() {
    const ths = document.querySelectorAll('#bankAccountsTable thead th');
    ths.forEach((th, idx) => {
        if (idx < 5) { // Solo para columnas principales
            th.style.cursor = 'pointer';
            th.addEventListener('click', function() {
                const fields = ['name', 'bank_name', 'account_number', 'account_type', 'balance'];
                const field = fields[idx];
                if (sortField === field) {
                    sortDir = sortDir === 'asc' ? 'desc' : 'asc';
                } else {
                    sortField = field;
                    sortDir = 'asc';
                }
                loadBankAccounts(1);
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
