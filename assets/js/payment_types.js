// --- Configuración ---
const API_URL = 'api/payment_type/PaymentTypeController.php';
let editingPaymentTypeId = null;
let sortField = 'created_at';
let sortDir = 'desc';

// --- Cargar tipos al iniciar ---
document.addEventListener('DOMContentLoaded', loadPaymentTypes);

// --- Paginación ---
let currentPage = 1;
let pageSize = 10;
let totalPaymentTypesCount = 0;
let bankAccounts = [];

function renderPageSizeSelector() {
    // Puedes implementar si lo usas en otras tablas
}

function loadPaymentTypes(page = 1) {
    currentPage = page;
    setTableLoading(true);
    fetch(`${API_URL}`)
        .then(res => res.json())
        .then(data => {
            const types = data.data || data;
            totalPaymentTypesCount = data.total || types.length;
            bankAccounts = data.bank_accounts || [];
            renderBankAccountsSelect();
            renderPaymentTypesTable(types);
            // renderPagination(); // Si implementas paginación
        })
        .catch(() => {
            document.getElementById('paymentTypesTableBody').innerHTML = '<tr><td colspan="4">Error al cargar tipos</td></tr>';
        })
        .finally(() => setTableLoading(false));
}

function setTableLoading(loading) {
    const tbody = document.getElementById('paymentTypesTableBody');
    if (loading) {
        tbody.innerHTML = `<tr><td colspan="4" style="text-align:center; padding:40px 0;">
            <div class="loading-spinner"></div>
            <span style="display:block; margin-top:8px; color:var(--text-secondary);">Cargando tipos...</span>
        </td></tr>`;
    }
}

function renderPaymentTypesTable(types) {
    const tbody = document.getElementById('paymentTypesTableBody');
    tbody.innerHTML = '';
    if (!types.length) {
        tbody.innerHTML = '<tr><td colspan="4">No hay tipos registrados</td></tr>';
        return;
    }
    types.forEach(type => {
        const acc = bankAccounts.find(a => a.id === type.bank_account_id);
        const accLabel = acc ? `${acc.name} (${acc.bank_name} - ${acc.account_number})` : 'No asignada';
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${type.name}</td>
            <td>${type.description || ''}</td>
            <td>${accLabel}</td>
            <td style="vertical-align: middle; text-align: center;">
                <div style="display: flex; gap: 4px; justify-content: center; align-items: center;">
                    <button type="button" class="btn-icon" onclick="editPaymentType('${type.id}')" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button type="button" class="btn-icon btn-danger" onclick="deletePaymentType('${type.id}')" title="Eliminar">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function renderBankAccountsSelect() {
    const select = document.getElementById('paymentTypeBankAccount');
    if (!select) return;
    select.innerHTML = '<option value="">Seleccione una cuenta...</option>';
    bankAccounts.forEach(acc => {
        select.innerHTML += `<option value="${acc.id}">${acc.name} (${acc.bank_name} - ${acc.account_number})</option>`;
    });
}

function openModal(modalId) {
    document.getElementById(modalId).style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
    document.body.style.overflow = 'auto';
    if (modalId === 'paymentTypeModal') {
        document.getElementById('paymentTypeForm').reset();
        document.getElementById('modalTitle').textContent = 'Nuevo Tipo de Pago';
        editingPaymentTypeId = null;
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
    const icon = document.getElementById('toastIcon');
    const msg = document.getElementById('toastMessage');
    msg.textContent = message;
    icon.innerHTML = type === 'success' ? '<i class="fas fa-check-circle"></i>' : '<i class="fas fa-exclamation-circle"></i>';
    toast.style.background = type === 'success' ? 'var(--primary-color, #2563eb)' : '#e53e3e';
    toast.style.display = 'flex';
    setTimeout(() => { toast.style.display = 'none'; }, 3000);
}

function editPaymentType(id) {
    fetch(`${API_URL}`)
        .then(res => res.json())
        .then(data => {
            const type = (data.data || data).find(x => x.id === id);
            bankAccounts = data.bank_accounts || [];
            renderBankAccountsSelect();
            if (type) {
                document.getElementById('modalTitle').textContent = 'Editar Tipo de Pago';
                document.getElementById('paymentTypeId').value = type.id;
                document.getElementById('paymentTypeName').value = type.name || '';
                document.getElementById('paymentTypeDescription').value = type.description || '';
                document.getElementById('paymentTypeBankAccount').value = type.bank_account_id || '';
                editingPaymentTypeId = type.id;
                openModal('paymentTypeModal');
            }
        });
}

let paymentTypeIdToDelete = null;
function showDeleteModal(id) {
    paymentTypeIdToDelete = id;
    document.getElementById('confirmDeleteModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
document.getElementById('confirmDeleteBtn').onclick = function() {
    if (paymentTypeIdToDelete) {
        deletePaymentTypeConfirmed(paymentTypeIdToDelete);
        paymentTypeIdToDelete = null;
        closeModal('confirmDeleteModal');
    }
};
function deletePaymentType(id) {
    showDeleteModal(id);
}
function deletePaymentTypeConfirmed(id) {
    const csrfToken = document.querySelector('#paymentTypeForm [name="csrf_token"]').value;
    fetch(API_URL, {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${encodeURIComponent(id)}&csrf_token=${encodeURIComponent(csrfToken)}`
    })
    .then(res => res.json())
    .then(result => {
        if (result && result.error) {
            showToast('Ocurrió un error al eliminar el tipo.', 'error');
        } else {
            showToast('Tipo eliminado con éxito.', 'success');
        }
        loadPaymentTypes();
    })
    .catch(() => {
        showToast('Ocurrió un error al eliminar el tipo.', 'error');
    });
}

document.getElementById('paymentTypeForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const data = {
        name: document.getElementById('paymentTypeName').value,
        description: document.getElementById('paymentTypeDescription').value,
        bank_account_id: document.getElementById('paymentTypeBankAccount').value,
        csrf_token: document.querySelector('#paymentTypeForm [name="csrf_token"]').value
    };
    let url = API_URL;
    let method = 'POST';
    let isEdit = false;
    if (editingPaymentTypeId) {
        data.id = editingPaymentTypeId;
        method = 'PUT';
        isEdit = true;
    }
    fetch(url, {
        method: method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(result => {
        closeModal('paymentTypeModal');
        if (result && result.error) {
            showToast('Ocurrió un error al guardar el tipo.', 'error');
        } else {
            showToast(isEdit ? 'Tipo editado con éxito.' : 'Tipo creado con éxito.', 'success');
        }
        loadPaymentTypes();
    })
    .catch(() => {
        showToast('Ocurrió un error al guardar el tipo.', 'error');
    });
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (modal.style.display === 'flex') {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        });
    }
});
