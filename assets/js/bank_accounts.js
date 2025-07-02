// --- Configuración ---
const API_URL = 'api/bank_account/BankAccountController.php';
let editingBankAccountId = null;
let sortField = 'created_at';
let sortDir = 'desc';

// Variables para filtros de estado
let statusFilter = '';
let tempStatusFilter = '';

// --- Cargar cuentas al iniciar ---
document.addEventListener('DOMContentLoaded', function() {
    loadBankAccounts();
    loadAccountsForTransfers();
    setupStatusFilterEventListeners();
    
    // Configurar ordenamiento después de un delay para asegurar que el DOM esté listo
    setTimeout(() => {
        setupTableSorting();
    }, 100);
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
            loadBankAccounts(1);
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

renderPageSizeSelector();

function loadBankAccounts(page = 1) {
    currentPage = page;
    setTableLoading(true);
    
    let url = `${API_URL}?action=getAllBankAccounts&limit=${pageSize}&offset=${(page-1)*pageSize}&sort=${sortField}&dir=${sortDir}`;
    
    // Agregar filtro de estado si está activo
    if (statusFilter !== '') {
        url += `&status=${statusFilter}`;
    }
    
    fetch(url)
        .then(res => res.json())
        .then(data => {
            const accounts = data.data || data;
            
            // Si no hay filtro de estado, usar el total del backend
            // Si hay filtro, aplicar filtro en frontend y contar
            if (statusFilter === '') {
                // Sin filtros: usar total del backend
                totalBankAccountsCount = data.total || accounts.length;
                renderBankAccountsTable(accounts);
            } else {
                // Con filtros: aplicar filtro en frontend 
                let filteredAccounts = accounts.filter(account => {
                    const isActive = account.active === 1 || account.active === '1' || account.active === true;
                    return statusFilter === '1' ? isActive : !isActive;
                });
                
                totalBankAccountsCount = filteredAccounts.length;
                renderBankAccountsTable(filteredAccounts);
            }
            
            renderPagination();
            updateActiveStatusFiltersDisplay();
            
            // Actualizar iconos de ordenamiento
            updateSortIcons();
        })
        .catch(() => {
            document.getElementById('bankAccountsTableBody').innerHTML = '<tr><td colspan="7">Error al cargar cuentas</td></tr>';
            const footerContainer = document.getElementById('bankAccountsTableFooter');
            if (footerContainer) {
                footerContainer.style.display = 'none';
            }
        })
        .finally(() => setTableLoading(false));
}

function setTableLoading(loading) {
    const tbody = document.getElementById('bankAccountsTableBody');
    if (loading) {
                    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:40px 0;">
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

// Función para formatear el estado de la cuenta
function formatAccountStatus(active) {
    const isActive = active === 1 || active === '1' || active === true;
    if (isActive) {
        return '<span class="status-badge status-active">Activa</span>';
    } else {
        return '<span class="status-badge status-inactive">Inactiva</span>';
    }
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
        tbody.innerHTML = '<tr><td colspan="7">No hay cuentas bancarias registradas</td></tr>';
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
            <td><a href="bank_account_detail.php?id=${account.id}" class="account-name-link">${account.name}</a></td>
            <td>${account.bank_name}</td>
            <td>${account.account_number}</td>
            <td><span class="account-type-badge account-type-${account.account_type}">${formatAccountType(account.account_type)}</span></td>
            <td>${formatBalance(account.balance, account.account_type)}</td>
            <td>${formatAccountStatus(account.active)}</td>
            <td class="acciones">
                <div class="table-actions">
                    <button type="button" class="btn-action" onclick="viewBankAccount('${account.id}')" title="Ver">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button type="button" class="btn-action" onclick="editBankAccount('${account.id}')" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    ${additionalButtons}
                    <button type="button" class="btn-action ${account.active == 1 ? 'btn-warning' : 'btn-success'}" onclick="toggleAccountStatus('${account.id}', ${account.active})" title="${account.active == 1 ? 'Desactivar' : 'Activar'}">
                        <i class="fas fa-${account.active == 1 ? 'ban' : 'check'}"></i>
                    </button>
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
    
    const totalPages = Math.ceil(totalBankAccountsCount / pageSize);
    if (totalPages <= 1) { 
        container.style.display = 'none'; 
        return; 
    }
    
    container.style.display = 'flex';
    let html = '';
    
    // Botón anterior
    if (currentPage > 1) {
        html += `<span class="pagination-number" onclick="loadBankAccounts(${currentPage - 1})">
            <i class="fas fa-chevron-left"></i>
        </span>`;
    } else {
        html += `<span class="pagination-number" style="opacity: 0.5; cursor: not-allowed;">
            <i class="fas fa-chevron-left"></i>
        </span>`;
    }
    
    // Números de página
    const maxVisiblePages = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
    let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
    
    if (endPage - startPage + 1 < maxVisiblePages) {
        startPage = Math.max(1, endPage - maxVisiblePages + 1);
    }
    
    // Primera página si no está visible
    if (startPage > 1) {
        html += `<span class="pagination-number" onclick="loadBankAccounts(1)">1</span>`;
        if (startPage > 2) {
            html += `<span class="pagination-ellipsis">...</span>`;
        }
    }
    
    // Páginas visibles
    for (let i = startPage; i <= endPage; i++) {
        const activeClass = i === currentPage ? 'active' : '';
        html += `<span class="pagination-number ${activeClass}" onclick="loadBankAccounts(${i})">${i}</span>`;
    }
    
    // Última página si no está visible
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            html += `<span class="pagination-ellipsis">...</span>`;
        }
        html += `<span class="pagination-number" onclick="loadBankAccounts(${totalPages})">${totalPages}</span>`;
    }
    
    // Botón siguiente
    if (currentPage < totalPages) {
        html += `<span class="pagination-number" onclick="loadBankAccounts(${currentPage + 1})">
            <i class="fas fa-chevron-right"></i>
        </span>`;
    } else {
        html += `<span class="pagination-number" style="opacity: 0.5; cursor: not-allowed;">
            <i class="fas fa-chevron-right"></i>
        </span>`;
    }
    
    container.innerHTML = html;
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
        // Resetear formulario
        document.getElementById('creditPaymentForm').reset();
        
        // Limpiar estado del modal
        currentCreditBalance = 0;
        updateBalanceDisplay();
        updateMaxAmountIndicator();
        
        // Limpiar feedback
        const feedback = document.getElementById('paymentAmountFeedback');
        if (feedback) {
            feedback.textContent = '';
            feedback.className = 'payment-amount-feedback';
        }
        
        // Rehabilitar botón de envío
        const submitButton = document.querySelector('#creditPaymentForm button[type="submit"]');
        if (submitButton) {
            submitButton.disabled = false;
            submitButton.innerHTML = '<i class="fas fa-credit-card"></i> Realizar Pago';
        }
        
        // Remover event listeners del input de monto para evitar acumulación
        const paymentAmountInput = document.getElementById('paymentAmount');
        if (paymentAmountInput) {
            paymentAmountInput.removeEventListener('input', validatePaymentAmount);
            paymentAmountInput.removeEventListener('change', validatePaymentAmount);
        }
    } else if (modalId === 'confirmStatusChangeModal') {
        accountDataToToggle = null;
    } else if (modalId === 'paymentTypesConflictModal') {
        // Limpiar cualquier dato temporal si es necesario
        console.log('Cerrando modal de conflicto de payment types');
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
    
    // Configurar estado por defecto (activa)
    const accountActiveSwitch = document.getElementById('accountActive');
    const accountStatusLabel = document.getElementById('accountStatusLabel');
    if (accountActiveSwitch && accountStatusLabel) {
        accountActiveSwitch.checked = true;
        accountStatusLabel.textContent = 'Activa';
        accountStatusLabel.style.color = 'var(--success-color)';
        
        // Agregar event listener para el switch
        accountActiveSwitch.removeEventListener('change', handleAccountStatusChange);
        accountActiveSwitch.addEventListener('change', handleAccountStatusChange);
    }
    
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

// Función para manejar el cambio del switch de estado
function handleAccountStatusChange() {
    const accountStatusLabel = document.getElementById('accountStatusLabel');
    if (this.checked) {
        accountStatusLabel.textContent = 'Activa';
        accountStatusLabel.style.color = 'var(--success-color)';
    } else {
        accountStatusLabel.textContent = 'Inactiva';
        accountStatusLabel.style.color = 'var(--danger-color)';
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
            
            // Configurar estado de la cuenta
            const accountActiveSwitch = document.getElementById('accountActive');
            const accountStatusLabel = document.getElementById('accountStatusLabel');
            if (accountActiveSwitch && accountStatusLabel) {
                const isActive = account.active === 1 || account.active === '1' || account.active === true;
                accountActiveSwitch.checked = isActive;
                accountStatusLabel.textContent = isActive ? 'Activa' : 'Inactiva';
                accountStatusLabel.style.color = isActive ? 'var(--success-color)' : 'var(--danger-color)';
                
                // Agregar event listener para el switch
                accountActiveSwitch.removeEventListener('change', handleAccountStatusChange);
                accountActiveSwitch.addEventListener('change', handleAccountStatusChange);
            }
            
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
            // Filtrar cuentas: no crédito Y activas
            window.transferAccounts = accounts.filter(acc => 
                acc.account_type !== 'credito' && (acc.active === 1 || acc.active === '1' || acc.active === true)
            );
            
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
// Variables globales para el modal de pago
let currentCreditBalance = 0;

// Cargar balance actual de la cuenta de crédito
function loadCreditAccountBalance(accountId) {
    return fetch(`${API_URL}?action=getBankAccountById&id=${accountId}`)
        .then(res => res.json())
        .then(data => {
            const account = data.data || data;
            if (account && account.balance !== undefined) {
                // Para cuentas de crédito, el balance negativo significa deuda
                currentCreditBalance = Math.abs(parseFloat(account.balance));
                updateBalanceDisplay();
                updateMaxAmountIndicator();
                return currentCreditBalance;
            }
            return 0;
        })
        .catch(err => {
            console.error('Error loading credit balance:', err);
            currentCreditBalance = 0;
            updateBalanceDisplay();
            return 0;
        });
}

// Actualizar la visualización del balance
function updateBalanceDisplay() {
    const balanceDisplay = document.getElementById('currentBalanceDisplay');
    const hiddenBalance = document.getElementById('currentCreditBalance');
    
    if (balanceDisplay) {
        if (currentCreditBalance > 0) {
            balanceDisplay.textContent = `$${currentCreditBalance.toLocaleString('es-MX', {minimumFractionDigits: 2})}`;
            balanceDisplay.style.color = 'var(--danger-color)';
        } else {
            balanceDisplay.textContent = '$0.00';
            balanceDisplay.style.color = 'var(--success-color)';
        }
    }
    
    if (hiddenBalance) {
        hiddenBalance.value = currentCreditBalance;
    }
}

// Actualizar indicador de monto máximo
function updateMaxAmountIndicator() {
    const indicator = document.getElementById('maxAmountIndicator');
    if (indicator) {
        if (currentCreditBalance > 0) {
            indicator.textContent = `(máx: $${currentCreditBalance.toLocaleString('es-MX', {minimumFractionDigits: 2})})`;
        } else {
            indicator.textContent = '(cuenta saldada)';
        }
    }
}

// Función para quick actions
function setQuickAmount(percentage) {
    const paymentAmountInput = document.getElementById('paymentAmount');
    if (!paymentAmountInput || currentCreditBalance <= 0) return;
    
    let amount = 0;
    switch(percentage) {
        case '25':
            amount = currentCreditBalance * 0.25;
            break;
        case '50':
            amount = currentCreditBalance * 0.50;
            break;
        case '100':
            amount = currentCreditBalance;
            break;
    }
    
    // Redondear a 2 decimales
    amount = Math.round(amount * 100) / 100;
    paymentAmountInput.value = amount.toFixed(2);
    
    // Validar el monto después de establecerlo
    validatePaymentAmount();
}

// Validar monto del pago
function validatePaymentAmount() {
    const paymentAmountInput = document.getElementById('paymentAmount');
    const feedback = document.getElementById('paymentAmountFeedback');
    const submitButton = document.querySelector('#creditPaymentForm button[type="submit"]');
    
    if (!paymentAmountInput || !feedback) return;
    
    const amount = parseFloat(paymentAmountInput.value) || 0;
    
    feedback.className = 'payment-amount-feedback';
    
    if (amount <= 0) {
        feedback.textContent = 'El monto debe ser mayor a $0.00';
        feedback.classList.add('invalid');
        if (submitButton) submitButton.disabled = true;
        return false;
    }
    
    if (currentCreditBalance <= 0) {
        feedback.textContent = 'Esta cuenta no tiene saldo pendiente de pago';
        feedback.classList.add('warning');
        if (submitButton) submitButton.disabled = true;
        return false;
    }
    
    if (amount > currentCreditBalance) {
        feedback.textContent = `No puedes pagar más de $${currentCreditBalance.toLocaleString('es-MX', {minimumFractionDigits: 2})} (sobrepago no permitido)`;
        feedback.classList.add('invalid');
        if (submitButton) submitButton.disabled = true;
        return false;
    }
    
    // Monto válido
    if (amount === currentCreditBalance) {
        feedback.textContent = '✓ Pago total - La cuenta quedará completamente saldada';
        feedback.classList.add('valid');
    } else {
        const remaining = currentCreditBalance - amount;
        feedback.textContent = `✓ Monto válido - Quedarán $${remaining.toLocaleString('es-MX', {minimumFractionDigits: 2})} pendientes`;
        feedback.classList.add('valid');
    }
    
    if (submitButton) submitButton.disabled = false;
    return true;
}

function openCreditPaymentModal(accountId, accountName) {
    document.getElementById('creditAccountId').value = accountId;
    document.getElementById('creditAccountName').value = accountName;
    
    // Cargar balance actual y cuentas para pago
    Promise.all([
        loadCreditAccountBalance(accountId),
        loadAccountsForTransfers()
    ]).then(() => {
        // Configurar event listener para validación en tiempo real
        const paymentAmountInput = document.getElementById('paymentAmount');
        if (paymentAmountInput) {
            paymentAmountInput.addEventListener('input', validatePaymentAmount);
            paymentAmountInput.addEventListener('change', validatePaymentAmount);
        }
        
        openModal('creditPaymentModal');
    });
}

// --- Event Listeners para formularios ---
document.getElementById('bankAccountForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Datos básicos que siempre se pueden editar
    const data = {
        name: document.getElementById('bankAccountName').value,
        bank_name: document.getElementById('bankName').value,
        account_number: document.getElementById('accountNumber').value,
        active: document.getElementById('accountActive').checked ? 1 : 0
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
        if (result && result.error) {
            showToast(result.error, 'error');
        } else {
            closeModal('bankAccountModal');
            showToast(isEdit ? 'Cuenta editada con éxito.' : 'Cuenta creada con éxito.', 'success');
            loadBankAccounts();
        }
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
    
    // Validar antes de enviar
    if (!validatePaymentAmount()) {
        showToast('Por favor, corrige el monto del pago', 'error');
        return;
    }
    
    const creditAccountId = document.getElementById('creditAccountId').value;
    const fromAccount = document.getElementById('paymentFromAccount').value;
    const amount = parseFloat(document.getElementById('paymentAmount').value);
    const description = document.getElementById('paymentDescription').value;
    
    // Validaciones adicionales
    if (!fromAccount) {
        showToast('Selecciona una cuenta de origen', 'error');
        return;
    }
    
    if (amount > currentCreditBalance) {
        showToast('El monto no puede ser mayor al saldo pendiente', 'error');
        return;
    }
    
    // Deshabilitar botón de envío para evitar doble envío
    const submitButton = document.querySelector('#creditPaymentForm button[type="submit"]');
    const originalText = submitButton.innerHTML;
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
    
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
        if (result.success) {
            showToast('Pago realizado con éxito', 'success');
            closeModal('creditPaymentModal');
            loadBankAccounts();
        } else {
            showToast(result.message || 'Error al realizar el pago', 'error');
            // Restaurar botón
            submitButton.disabled = false;
            submitButton.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('Payment error:', error);
        showToast('Error al procesar el pago', 'error');
        // Restaurar botón
        submitButton.disabled = false;
        submitButton.innerHTML = originalText;
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

// Event listener para el botón de confirmación de cambio de estado
document.getElementById('confirmStatusBtn').onclick = function() {
    confirmStatusChange();
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
        const matchesSearch = name.includes(search) || bank.includes(search);
        
        if (matchesSearch) {
            row.style.display = '';
            count++;
        } else {
            row.style.display = 'none';
        }
    });
    document.getElementById('totalBankAccounts').textContent = count;
});

// --- Función para cambiar estado de la cuenta (activar/desactivar) ---
let accountDataToToggle = null;

function toggleAccountStatus(accountId, currentStatus) {
    // Obtener información de la cuenta para mostrar en el modal
    fetch(`${API_URL}?action=getBankAccountById&id=${accountId}`)
        .then(res => res.json())
        .then(account => {
            showStatusChangeModal(accountId, currentStatus, account);
        })
        .catch(() => {
            showToast('Error al obtener información de la cuenta.', 'error');
        });
}

function showStatusChangeModal(accountId, currentStatus, account) {
    const newStatus = currentStatus == 1 ? 0 : 1;
    const isActivating = newStatus == 1;
    
    // Guardar datos para la confirmación
    accountDataToToggle = {
        id: accountId,
        currentStatus: currentStatus,
        newStatus: newStatus,
        account: account
    };
    
    // Configurar contenido del modal
    const title = document.getElementById('confirmStatusTitle');
    const message = document.getElementById('confirmStatusMessage');
    const details = document.getElementById('confirmStatusDetails');
    const btn = document.getElementById('confirmStatusBtn');
    const btnText = document.getElementById('confirmStatusBtnText');
    const btnIcon = document.getElementById('confirmStatusIcon');
    
    title.textContent = isActivating ? 'Activar Cuenta Bancaria' : 'Desactivar Cuenta Bancaria';
    message.textContent = `¿Está seguro de que desea ${isActivating ? 'activar' : 'desactivar'} la cuenta "${account.name}"?`;
    
    if (isActivating) {
        details.innerHTML = '<i class="fas fa-info-circle"></i> La cuenta estará disponible para todas las operaciones y aparecerá en las listas de selección.';
        btn.className = 'btn btn-success';
        btnText.textContent = 'Activar';
        btnIcon.className = 'fas fa-check';
    } else {
        details.innerHTML = '<i class="fas fa-exclamation-triangle"></i> La cuenta no aparecerá en las listas de selección para nuevas transacciones, pero mantendrá su saldo actual.';
        btn.className = 'btn btn-warning';
        btnText.textContent = 'Desactivar';
        btnIcon.className = 'fas fa-ban';
    }
    
    // Mostrar modal
    document.getElementById('confirmStatusChangeModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function confirmStatusChange() {
    if (!accountDataToToggle) return;
    
    const { id, newStatus } = accountDataToToggle;
    
    // Mostrar loading en el botón
    const btn = document.getElementById('confirmStatusBtn');
    const originalContent = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
    btn.disabled = true;
    
    fetch(`${API_URL}?action=toggleAccountStatus&id=${id}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ active: newStatus })
    })
    .then(res => res.json())
    .then(result => {
        // Debug: mostrar la respuesta del backend en consola
        console.log('Respuesta del backend:', result);
        
        if (result && result.error) {
            // Si hay payment types asociados, mostrar modal con detalles
            if (result.details && result.details.payment_types && result.details.payment_types.length > 0) {
                console.log('Mostrando modal de conflicto con payment types:', result.details.payment_types);
                // Guardar la cuenta temporalmente antes de que se limpie
                const accountData = accountDataToToggle.account;
                // Cerrar el modal de confirmación y mostrar el modal de conflicto
                closeModal('confirmStatusChangeModal');
                // Pequeño delay para asegurar que el modal anterior se cierre completamente
                setTimeout(() => {
                    showPaymentTypesConflictModal(result, accountData);
                }, 100);
                // No limpiar accountDataToToggle aquí, se limpia en el finally
            } else {
                // Mostrar el mensaje específico del backend si existe
                closeModal('confirmStatusChangeModal');
                const errorMessage = result.message || result.error || 'Error al cambiar el estado de la cuenta.';
                console.log('Mostrando toast de error:', errorMessage);
                showToast(errorMessage, 'error');
            }
        } else if (result && result.message) {
            // Éxito
            closeModal('confirmStatusChangeModal');
            showToast(`Cuenta ${newStatus == 1 ? 'activada' : 'desactivada'} con éxito.`, 'success');
            loadBankAccounts(currentPage);
        } else {
            // Respuesta inesperada
            closeModal('confirmStatusChangeModal');
            console.error('Respuesta inesperada del backend:', result);
            showToast('Respuesta inesperada del servidor.', 'error');
        }
    })
    .catch(() => {
        closeModal('confirmStatusChangeModal');
        showToast('Error al cambiar el estado de la cuenta.', 'error');
    })
    .finally(() => {
        // Restaurar botón
        btn.innerHTML = originalContent;
        btn.disabled = false;
        accountDataToToggle = null;
    });
}

// --- Función para mostrar modal de conflicto con payment types ---
function showPaymentTypesConflictModal(result, account) {
    console.log('showPaymentTypesConflictModal llamada con:', { result, account });
    
    const conflictAccountName = document.getElementById('conflictAccountName');
    const paymentTypesList = document.getElementById('paymentTypesList');
    const modal = document.getElementById('paymentTypesConflictModal');
    
    if (!conflictAccountName || !paymentTypesList || !modal) {
        console.error('Elementos del modal no encontrados:', {
            conflictAccountName: !!conflictAccountName,
            paymentTypesList: !!paymentTypesList,
            modal: !!modal
        });
        showToast('Error al mostrar el modal de conflicto.', 'error');
        return;
    }
    
    conflictAccountName.textContent = account.name;
    paymentTypesList.innerHTML = '';
    
    result.details.payment_types.forEach((paymentType, index) => {
        const div = document.createElement('div');
        div.style.cssText = 'display: flex; align-items: center; gap: 8px; margin-bottom: 8px; font-size: 14px;';
        if (index === result.details.payment_types.length - 1) {
            div.style.marginBottom = '0';
        }
        div.innerHTML = `
            <i class="fas fa-credit-card" style="color: var(--warning-color); width: 16px;"></i>
            <span style="font-weight: 500;">${paymentType}</span>
        `;
        paymentTypesList.appendChild(div);
    });
    
    console.log('Mostrando modal de conflicto...');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    
    // Debug: verificar si el modal es visible
    setTimeout(() => {
        const modalVisible = window.getComputedStyle(modal).display === 'flex';
        const modalOpacity = window.getComputedStyle(modal).opacity;
        const modalZIndex = window.getComputedStyle(modal).zIndex;
        console.log('Estado del modal después de mostrar:', {
            display: modal.style.display,
            computedDisplay: window.getComputedStyle(modal).display,
            opacity: modalOpacity,
            zIndex: modalZIndex,
            visible: modalVisible
        });
    }, 100);
}

function openPaymentTypesSettings() {
    closeModal('paymentTypesConflictModal');
    // Redirigir a la página de configuración de tipos de pago
    window.location.href = 'settings.php#payment-types';
}

// --- Funciones para filtro de estado ---
function setupStatusFilterEventListeners() {
    // Event listener para el select de estado
    document.getElementById('statusFilter').addEventListener('change', function() {
        tempStatusFilter = this.value;
    });
    
    // Cerrar dropdown al hacer click fuera
    document.addEventListener('click', function(e) {
        const dropdown = document.getElementById('statusFilterDropdown');
        const filterContainer = e.target.closest('.filter-dropdown-container');
        
        if (!filterContainer && dropdown && dropdown.classList.contains('show')) {
            dropdown.classList.remove('show');
            // Revertir cambios temporales si no se aplicaron
            if (tempStatusFilter !== statusFilter) {
                document.getElementById('statusFilter').value = statusFilter;
                tempStatusFilter = statusFilter;
            }
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
    loadBankAccounts(1);
}

function clearStatusFilters() {
    statusFilter = '';
    tempStatusFilter = '';
    document.getElementById('statusFilter').value = '';
    closeStatusFilterDropdown();
    loadBankAccounts(1);
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
        const statusText = statusFilter === '1' ? 'Solo activas' : 'Solo inactivas';
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
