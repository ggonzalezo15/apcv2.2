// --- Configuración ---
const API_URL = 'api/bank_account/BankAccountController.php';
let editingBankAccountId = null;
let sortField = 'created_at';
let sortDir = 'desc';

// --- Cargar cuentas al iniciar ---
document.addEventListener('DOMContentLoaded', function() {
    loadBankAccounts();
    loadAccountsForTransfers();
});

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

// Función para formatear el tipo de cuenta
function formatAccountType(type) {
    const types = {
        'cheque': 'Cheque',
        'credito': 'Crédito', 
        'ahorro': 'Ahorro',
        'caja_chica': 'Caja Chica'
    };
    return types[type] || type;
}

// Función para formatear el balance según el tipo de cuenta
function formatBalance(balance, accountType) {
    const amount = parseFloat(balance);
    const formattedAmount = Math.abs(amount).toLocaleString('es-MX', {minimumFractionDigits:2});
    
    if (accountType === 'credito') {
        // Para crédito, mostrar en rojo si hay balance usado (negativo)
        if (amount < 0) {
            return `<span class="credit-balance">${formattedAmount}</span>`;
        } else {
            return `<span class="positive-balance">${formattedAmount}</span>`;
        }
    } else {
        // Para otras cuentas, normal
        if (amount > 0) {
            return `<span class="positive-balance">${formattedAmount}</span>`;
        } else if (amount === 0) {
            return `<span class="zero-balance">${formattedAmount}</span>`;
        } else {
            return `<span class="credit-balance">${formattedAmount}</span>`;
        }
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
        
        // Determinar botones adicionales según el tipo de cuenta
        let additionalButtons = '';
        if (account.account_type === 'credito') {
            additionalButtons = `
                <button type="button" class="btn-action btn-success" onclick="openCreditPaymentModal('${account.id}', '${account.name}')" title="Hacer Pago">
                    <i class="fas fa-credit-card"></i>
                </button>`;
        }
        
        tr.innerHTML = `
            <td>${account.name}</td>
            <td>${account.bank_name}</td>
            <td>${account.account_number}</td>
            <td><span class="account-type-badge account-type-${account.account_type}">${formatAccountType(account.account_type)}</span></td>
            <td>${formatBalance(account.balance, account.account_type)}</td>
            <td class="acciones">
                <div class="table-actions">
                    <button type="button" class="btn-action" onclick="viewBankAccount('${account.id}')" title="Ver">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button type="button" class="btn-action" onclick="editBankAccount('${account.id}')" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    ${additionalButtons}
                    <button type="button" class="btn-action btn-danger" onclick="deleteBankAccount('${account.id}')" title="Eliminar">
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
    
    // Cargar cuentas para transferencias si es necesario
    if (modalId === 'transferModal') {
        loadAccountsForTransfers();
    }
    
    // Si se abre el modal de cuenta bancaria y no está editando, configurar modo creación
    if (modalId === 'bankAccountModal' && !editingBankAccountId) {
        // Pequeño delay para asegurar que el DOM esté completamente renderizado
        setTimeout(() => {
            setModalToCreateMode();
        }, 10);
    }
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
    document.body.style.overflow = 'auto';
    if (modalId === 'bankAccountModal') {
        document.getElementById('bankAccountForm').reset();
        document.getElementById('modalTitle').textContent = 'Nueva Cuenta Bancaria';
        editingBankAccountId = null;
        
        // Restablecer campos a modo creación
        setModalToCreateMode();
    } else if (modalId === 'transferModal') {
        document.getElementById('transferForm').reset();
    } else if (modalId === 'creditPaymentModal') {
        document.getElementById('creditPaymentForm').reset();
    }
}

// Función para configurar el modal en modo creación
function setModalToCreateMode() {
    // Mostrar campos editables
    document.getElementById('accountType').style.display = 'block';
    document.getElementById('balance').style.display = 'block';
    document.getElementById('balanceCreateNote').style.display = 'block';
    
    // Ocultar campos de solo lectura y notas de edición
    document.getElementById('accountTypeReadonly').style.display = 'none';
    document.getElementById('balanceReadonly').style.display = 'none';
    document.getElementById('accountTypeEditNote').style.display = 'none';
    document.getElementById('balanceEditNote').style.display = 'none';
    
    // Configurar event listeners para manejo de cuentas de crédito
    setupCreditAccountHandling();
}

// Función para manejar el comportamiento especial de cuentas de crédito
function setupCreditAccountHandling() {
    const accountTypeSelect = document.getElementById('accountType');
    const balanceInput = document.getElementById('balance');
    const balanceHelpText = document.getElementById('balanceHelpText');
    
    if (!accountTypeSelect || !balanceInput || !balanceHelpText) return;
    
    // Remover event listeners previos
    accountTypeSelect.removeEventListener('change', handleAccountTypeChange);
    balanceInput.removeEventListener('blur', handleBalanceBlur);
    
    // Agregar nuevos event listeners
    accountTypeSelect.addEventListener('change', handleAccountTypeChange);
    balanceInput.addEventListener('blur', handleBalanceBlur);
    
    // Configurar estado inicial
    handleAccountTypeChange.call(accountTypeSelect);
}

// Función para manejar el cambio de tipo de cuenta
function handleAccountTypeChange() {
    const accountType = this.value;
    const balanceInput = document.getElementById('balance');
    const balanceHelpText = document.getElementById('balanceHelpText');
    
    if (accountType === 'credito') {
        balanceHelpText.innerHTML = '⚠️ Para cuentas de crédito con deuda pendiente, ingrese el monto de la deuda (se convertirá automáticamente a negativo)';
        balanceHelpText.style.color = 'var(--warning-color, #f59e0b)';
        balanceInput.placeholder = 'Ej: 5000 (se guardará como -5000)';
    } else {
        balanceHelpText.innerHTML = 'Ingrese el saldo inicial de la cuenta';
        balanceHelpText.style.color = 'var(--text-secondary)';
        balanceInput.placeholder = '0.00';
    }
}

// Función para manejar el blur del campo balance (cuando pierde el foco)
function handleBalanceBlur() {
    const accountType = document.getElementById('accountType').value;
    const balanceInput = this;
    
    if (accountType === 'credito' && balanceInput.value) {
        let value = parseFloat(balanceInput.value);
        
        // Si es un número válido y positivo, convertir a negativo para cuentas de crédito
        if (!isNaN(value) && value > 0) {
            // Aplicar clase de conversión para feedback visual
            balanceInput.classList.add('converting');
            
            // Convertir a negativo
            balanceInput.value = (-value).toFixed(2);
            
            // Remover clase después de la animación
            setTimeout(() => {
                balanceInput.classList.remove('converting');
            }, 1000);
        }
    }
}

// Función para configurar el modal en modo edición
function setModalToEditMode(account) {
    // Ocultar campos editables
    document.getElementById('accountType').style.display = 'none';
    document.getElementById('balance').style.display = 'none';
    document.getElementById('balanceCreateNote').style.display = 'none';
    
    // Mostrar campos de solo lectura
    document.getElementById('accountTypeReadonly').style.display = 'block';
    document.getElementById('balanceReadonly').style.display = 'block';
    document.getElementById('accountTypeEditNote').style.display = 'block';
    document.getElementById('balanceEditNote').style.display = 'block';
    
    // Llenar campos de solo lectura con valores formateados
    const typeNames = {
        'cheque': 'Cheque',
        'credito': 'Crédito', 
        'ahorro': 'Ahorro',
        'caja_chica': 'Caja Chica'
    };
    
    document.getElementById('accountTypeReadonly').value = typeNames[account.account_type] || account.account_type;
    
    const balance = parseFloat(account.balance);
    const formattedBalance = '$' + balance.toLocaleString('es-MX', {minimumFractionDigits: 2});
    document.getElementById('balanceReadonly').value = formattedBalance;
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
            
            // Solo llenar los campos que NO se van a editar (para referencia en el formulario)
            document.getElementById('accountType').value = account.account_type || '';
            document.getElementById('balance').value = account.balance || '0.00';
            
            editingBankAccountId = account.id;
            
            // Configurar modal en modo edición
            setModalToEditMode(account);
            
            openModal('bankAccountModal');
        });
}

// --- Funciones para transferencias ---
function loadAccountsForTransfers() {
    fetch(`${API_URL}?action=getAllBankAccounts`)
        .then(res => res.json())
        .then(data => {
            const accounts = data.data || data;
            window.transferAccounts = accounts.filter(acc => acc.account_type !== 'credito'); // Guardar globalmente para filtros
            
            // Llenar select de cuenta origen
            const fromSelect = document.getElementById('fromAccount');
            const toSelect = document.getElementById('toAccount');
            const paymentFromSelect = document.getElementById('paymentFromAccount');
            
            if (fromSelect) {
                // Remover event listeners previos para evitar duplicados
                const newFromSelect = fromSelect.cloneNode(true);
                fromSelect.parentNode.replaceChild(newFromSelect, fromSelect);
                
                populateFromAccountSelect();
                // Agregar event listener para filtrar cuenta destino
                document.getElementById('fromAccount').addEventListener('change', function() {
                    populateToAccountSelect(this.value);
                });
            }
            
            if (toSelect) {
                // Remover event listeners previos para evitar duplicados
                const newToSelect = toSelect.cloneNode(true);
                toSelect.parentNode.replaceChild(newToSelect, toSelect);
                
                populateToAccountSelect();
                // Agregar event listener para filtrar cuenta origen
                document.getElementById('toAccount').addEventListener('change', function() {
                    populateFromAccountSelect(this.value);
                });
            }
            
            if (paymentFromSelect) {
                paymentFromSelect.innerHTML = '<option value="">Seleccionar cuenta...</option>';
                window.transferAccounts.forEach(account => {
                    paymentFromSelect.innerHTML += `<option value="${account.id}">${account.name} - ${account.bank_name} ($${parseFloat(account.balance).toLocaleString('es-MX', {minimumFractionDigits:2})})</option>`;
                });
            }
        })
        .catch(err => {
            console.error('Error loading accounts:', err);
        });
}

// Función para llenar el select de cuenta origen excluyendo la cuenta destino seleccionada
function populateFromAccountSelect(excludeAccountId = null) {
    const fromSelect = document.getElementById('fromAccount');
    if (!fromSelect || !window.transferAccounts) return;
    
    const currentValue = fromSelect.value;
    fromSelect.innerHTML = '<option value="">Seleccionar cuenta origen...</option>';
    
    window.transferAccounts.forEach(account => {
        if (account.id !== excludeAccountId) {
            const selected = account.id === currentValue ? 'selected' : '';
            fromSelect.innerHTML += `<option value="${account.id}" ${selected}>${account.name} - ${account.bank_name} ($${parseFloat(account.balance).toLocaleString('es-MX', {minimumFractionDigits:2})})</option>`;
        }
    });
}

// Función para llenar el select de cuenta destino excluyendo la cuenta origen seleccionada
function populateToAccountSelect(excludeAccountId = null) {
    const toSelect = document.getElementById('toAccount');
    if (!toSelect || !window.transferAccounts) return;
    
    const currentValue = toSelect.value;
    toSelect.innerHTML = '<option value="">Seleccionar cuenta destino...</option>';
    
    window.transferAccounts.forEach(account => {
        if (account.id !== excludeAccountId) {
            const selected = account.id === currentValue ? 'selected' : '';
            toSelect.innerHTML += `<option value="${account.id}" ${selected}>${account.name} - ${account.bank_name}</option>`;
        }
    });
}

// --- Funciones para pago de crédito ---
function openCreditPaymentModal(accountId, accountName) {
    document.getElementById('creditAccountId').value = accountId;
    document.getElementById('creditAccountName').value = accountName;
    loadAccountsForTransfers(); // Cargar cuentas disponibles para pago
    openModal('creditPaymentModal');
}

// --- Event Listeners para formularios ---
document.getElementById('bankAccountForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Datos básicos que siempre se pueden editar
    const data = {
        name: document.getElementById('bankAccountName').value,
        bank_name: document.getElementById('bankName').value,
        account_number: document.getElementById('accountNumber').value
    };
    
    // Solo incluir tipo y balance si NO estamos editando
    if (!editingBankAccountId) {
        data.account_type = document.getElementById('accountType').value;
        data.balance = document.getElementById('balance').value;
    } else {
        // En modo edición, mantener los valores originales (necesarios para el backend)
        data.account_type = document.getElementById('accountType').value;
        data.balance = document.getElementById('balance').value;
    }
    
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

// Formulario de transferencias
document.getElementById('transferForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const fromAccount = document.getElementById('fromAccount').value;
    const toAccount = document.getElementById('toAccount').value;
    const amount = document.getElementById('transferAmount').value;
    const description = document.getElementById('transferDescription').value;
    
    if (fromAccount === toAccount) {
        showToast('No puedes transferir a la misma cuenta', 'error');
        return;
    }
    
    const transferData = {
        from_account_id: fromAccount,
        to_account_id: toAccount,
        amount: amount,
        description: description
    };
    
    fetch(`${API_URL}?action=transfer`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(transferData)
    })
    .then(res => res.json())
    .then(result => {
        closeModal('transferModal');
        if (result.success) {
            showToast('Transferencia realizada con éxito', 'success');
            loadBankAccounts();
        } else {
            showToast(result.message || 'Error al realizar la transferencia', 'error');
        }
    })
    .catch(() => {
        showToast('Error al procesar la transferencia', 'error');
    });
});

// Formulario de pago de crédito
document.getElementById('creditPaymentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const creditAccountId = document.getElementById('creditAccountId').value;
    const fromAccount = document.getElementById('paymentFromAccount').value;
    const amount = document.getElementById('paymentAmount').value;
    const description = document.getElementById('paymentDescription').value;
    
    const paymentData = {
        credit_account_id: creditAccountId,
        from_account_id: fromAccount,
        amount: amount,
        description: description
    };
    
    fetch(`${API_URL}?action=creditPayment`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(paymentData)
    })
    .then(res => res.json())
    .then(result => {
        closeModal('creditPaymentModal');
        if (result.success) {
            showToast('Pago realizado con éxito', 'success');
            loadBankAccounts();
        } else {
            showToast(result.message || 'Error al realizar el pago', 'error');
        }
    })
    .catch(() => {
        showToast('Error al procesar el pago', 'error');
    });
});

// --- Eliminación de cuentas ---
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

// --- Cerrar modales con ESC ---
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
