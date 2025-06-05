// ============================================================================
// VARIABLES GLOBALES
// ============================================================================

let incomesData = [];
let teamsData = [];
let contractorsData = [];
let jobTypesData = [];
let paymentTypesData = [];
let currentPage = 1;
let totalPages = 1;
let isEditing = false;
let currentIncomeId = null;

// ============================================================================
// INICIALIZACIÓN
// ============================================================================

document.addEventListener('DOMContentLoaded', function() {
    console.log('📋 Inicializando módulo de ingresos...');
    
    // Inicializar componentes
    initializeDatePickers();
    initializeEventListeners();
    
    // Cargar datos iniciales
    loadInitialData();
});

function initializeDatePickers() {
    // Configurar Flatpickr para campos de fecha
    flatpickr('#incomeDate', {
        dateFormat: 'Y-m-d',
        locale: 'es',
        defaultDate: new Date()
    });
    
    flatpickr('.date-picker', {
        dateFormat: 'Y-m-d',
        locale: 'es',
        allowInput: true
    });
}

function initializeEventListeners() {
    // Búsqueda en tiempo real
    document.getElementById('searchInput').addEventListener('input', function() {
        debounce(loadIncomes, 300)();
    });
    
    // Submit del formulario
    document.getElementById('incomeForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveIncome();
    });
    
    // Eventos de filtros
    document.getElementById('teamFilter').addEventListener('change', loadIncomes);
    document.getElementById('statusFilter').addEventListener('change', loadIncomes);
}

// ============================================================================
// CARGA DE DATOS INICIALES
// ============================================================================

async function loadInitialData() {
    try {
        showLoading(true);
        
        // Cargar datos en paralelo
        await Promise.all([
            loadTeams(),
            loadContractors(),
            loadJobTypes(),
            loadPaymentTypes(),
            loadIncomes()
        ]);
        
        populateFilterDropdowns();
        
    } catch (error) {
        console.error('❌ Error cargando datos iniciales:', error);
        showNotification('Error cargando datos iniciales', 'error');
    } finally {
        showLoading(false);
    }
}

async function loadTeams() {
    try {
        const response = await fetch('api/income/IncomesController.php?action=getTeams');
        const result = await response.json();
        
        if (result.success) {
            teamsData = result.data;
            populateTeamsDropdown();
        }
    } catch (error) {
        console.error('❌ Error cargando equipos:', error);
    }
}

async function loadContractors() {
    try {
        const response = await fetch('api/income/IncomesController.php?action=getContractors');
        const result = await response.json();
        
        if (result.success) {
            contractorsData = result.data;
        }
    } catch (error) {
        console.error('❌ Error cargando contratistas:', error);
    }
}

async function loadJobTypes() {
    try {
        const response = await fetch('api/income/IncomesController.php?action=getJobTypes');
        const result = await response.json();
        
        if (result.success) {
            jobTypesData = result.data;
        }
    } catch (error) {
        console.error('❌ Error cargando tipos de trabajo:', error);
    }
}

async function loadPaymentTypes() {
    try {
        const response = await fetch('api/income/IncomesController.php?action=getPaymentTypes');
        const result = await response.json();
        
        if (result.success) {
            paymentTypesData = result.data;
        }
    } catch (error) {
        console.error('❌ Error cargando tipos de pago:', error);
    }
}

// ============================================================================
// GESTIÓN DE INGRESOS
// ============================================================================

async function loadIncomes() {
    try {
        const params = new URLSearchParams({
            action: 'getIncomes',
            page: currentPage,
            search: document.getElementById('searchInput').value,
            team_filter: document.getElementById('teamFilter').value,
            status_filter: document.getElementById('statusFilter').value,
            date_from: document.getElementById('dateFromFilter')?.value || '',
            date_to: document.getElementById('dateToFilter')?.value || ''
        });
        
        const response = await fetch(`api/income/IncomesController.php?${params}`);
        const result = await response.json();
        
        if (result.success) {
            incomesData = result.data;
            totalPages = result.pagination.total_pages;
            updateIncomesTable();
            updatePagination();
            document.getElementById('totalIncomes').textContent = result.pagination.total_records;
        } else {
            throw new Error(result.error || 'Error desconocido');
        }
    } catch (error) {
        console.error('❌ Error cargando ingresos:', error);
        showNotification('Error cargando la lista de ingresos', 'error');
    }
}

function updateIncomesTable() {
    const tbody = document.getElementById('incomesTableBody');
    tbody.innerHTML = '';
    
    if (incomesData.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="9" style="text-align: center; padding: 40px; color: #6b7280;">
                    <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 16px; opacity: 0.3;"></i>
                    <div>No se encontraron ingresos</div>
                </td>
            </tr>
        `;
        return;
    }
    
    incomesData.forEach(income => {
        const row = createIncomeRow(income);
        tbody.appendChild(row);
    });
}

function createIncomeRow(income) {
    const row = document.createElement('tr');
    
    // Status badge
    const statusBadge = getStatusBadge(income.status);
    
    // Progress bar
    const progressBar = createProgressBar(income.payment_percentage || 0);
    
    row.innerHTML = `
        <td>${income.invoice_number || 'Sin número'}</td>
        <td>${formatDate(income.date)}</td>
        <td>${income.team_name || 'Sin equipo'}</td>
        <td class="text-right">$${formatMoney(income.total_income)}</td>
        <td class="text-right">$${formatMoney(income.total_payments)}</td>
        <td class="text-right ${income.balance >= 0 ? 'text-green' : 'text-red'}">
            $${formatMoney(income.balance)}
        </td>
        <td>${progressBar}</td>
        <td>${statusBadge}</td>
        <td>
            <div class="action-buttons">
                <button class="btn-action btn-view" onclick="viewIncome('${income.id}')" title="Ver">
                    <i class="fas fa-eye"></i>
                </button>
                <button class="btn-action btn-edit" onclick="editIncome('${income.id}')" title="Editar">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn-action btn-delete" onclick="deleteIncome('${income.id}')" title="Eliminar">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </td>
    `;
    
    return row;
}

function getStatusBadge(status) {
    const statusConfig = {
        'draft': { label: 'Borrador', class: 'badge-secondary' },
        'pending': { label: 'Pendiente', class: 'badge-warning' },
        'partial_paid': { label: 'Parcial', class: 'badge-info' },
        'paid': { label: 'Pagado', class: 'badge-success' },
        'cancelled': { label: 'Cancelado', class: 'badge-danger' }
    };
    
    const config = statusConfig[status] || { label: status, class: 'badge-secondary' };
    return `<span class="status-badge ${config.class}">${config.label}</span>`;
}

function createProgressBar(percentage) {
    return `
        <div class="progress-bar">
            <div class="progress-fill" style="width: ${percentage}%"></div>
            <span class="progress-text">${percentage}%</span>
        </div>
    `;
}

// ============================================================================
// MODAL Y FORMULARIOS
// ============================================================================

function resetIncomeForm() {
    isEditing = false;
    currentIncomeId = null;
    
    document.getElementById('modalTitle').textContent = 'Nuevo Ingreso';
    document.getElementById('incomeForm').reset();
    document.getElementById('incomeId').value = '';
    
    // Limpiar secciones dinámicas
    document.getElementById('contractors-list').innerHTML = '';
    document.getElementById('income-lines').innerHTML = '';
    document.getElementById('payments-list').innerHTML = '';
    
    updateTotals();
    
    // Agregar línea inicial
    addIncomeLine();
}

async function editIncome(id) {
    try {
        isEditing = true;
        currentIncomeId = id;
        
        const response = await fetch(`api/income/IncomesController.php?action=getIncome&id=${id}`);
        const result = await response.json();
        
        if (result.success) {
            populateIncomeForm(result.data);
            document.getElementById('modalTitle').textContent = 'Editar Ingreso';
            openModal('incomeModal');
        } else {
            throw new Error(result.error);
        }
    } catch (error) {
        console.error('❌ Error cargando ingreso:', error);
        showNotification('Error cargando los datos del ingreso', 'error');
    }
}

function populateIncomeForm(data) {
    const { income, contractors, lines, payments } = data;
    
    // Llenar campos principales
    document.getElementById('incomeId').value = income.id;
    document.getElementById('invoiceNumber').value = income.invoice_number || '';
    document.getElementById('incomeDate').value = income.date;
    document.getElementById('team').value = income.team_id || '';
    document.getElementById('status').value = income.status;
    document.getElementById('generalNote').value = income.general_note || '';
    
    // Limpiar y llenar contratistas
    document.getElementById('contractors-list').innerHTML = '';
    contractors.forEach(contractor => {
        addContractorSelection(contractor);
    });
    
    // Limpiar y llenar líneas
    document.getElementById('income-lines').innerHTML = '';
    lines.forEach(line => {
        addIncomeLine(line);
    });
    
    // Limpiar y llenar pagos
    document.getElementById('payments-list').innerHTML = '';
    payments.forEach(payment => {
        addPaymentLine(payment);
    });
    
    updateTotals();
}

async function saveIncome() {
    try {
        showLoading(true);
        
        const formData = collectFormData();
        const url = 'api/income/IncomesController.php';
        const action = isEditing ? 'updateIncome' : 'createIncome';
        
        if (isEditing) {
            formData.id = currentIncomeId;
        }
        
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: action,
                ...formData
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification(
                isEditing ? 'Ingreso actualizado exitosamente' : 'Ingreso creado exitosamente',
                'success'
            );
            closeModal('incomeModal');
            loadIncomes();
        } else {
            throw new Error(result.error);
        }
    } catch (error) {
        console.error('❌ Error guardando ingreso:', error);
        showNotification('Error guardando el ingreso', 'error');
    } finally {
        showLoading(false);
    }
}

function collectFormData() {
    return {
        invoice_number: document.getElementById('invoiceNumber').value,
        date: document.getElementById('incomeDate').value,
        team_id: document.getElementById('team').value,
        status: document.getElementById('status').value,
        general_note: document.getElementById('generalNote').value,
        contractors: collectContractors(),
        lines: collectIncomeLines(),
        payments: collectPayments()
    };
}

// ============================================================================
// GESTIÓN DE CONTRATISTAS
// ============================================================================

function addContractorSelection(data = null) {
    const container = document.getElementById('contractors-list');
    const div = document.createElement('div');
    div.className = 'contractor-row';
    
    div.innerHTML = `
        <div class="form-row">
            <div class="form-group">
                <select class="form-input contractor-select" required>
                    <option value="">Seleccionar contratista...</option>
                    ${contractorsData.map(c => 
                        `<option value="${c.id}" ${data && data.contractor_id === c.id ? 'selected' : ''}>
                            ${c.name}
                        </option>`
                    ).join('')}
                </select>
            </div>
            <div class="form-group">
                <input type="number" class="form-input contractor-percentage" 
                       placeholder="%" min="0" max="100" step="0.01"
                       value="${data ? data.percentage : ''}">
            </div>
            <div class="form-group">
                <input type="number" class="form-input contractor-amount" 
                       placeholder="Monto" min="0" step="0.01"
                       value="${data ? data.amount : ''}">
            </div>
            <div class="form-group">
                <button type="button" class="btn btn-danger btn-sm" onclick="removeContractor(this)">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `;
    
    container.appendChild(div);
}

function removeContractor(button) {
    button.closest('.contractor-row').remove();
}

function collectContractors() {
    const contractors = [];
    const rows = document.querySelectorAll('.contractor-row');
    
    rows.forEach(row => {
        const contractorId = row.querySelector('.contractor-select').value;
        const percentage = parseFloat(row.querySelector('.contractor-percentage').value) || 0;
        const amount = parseFloat(row.querySelector('.contractor-amount').value) || 0;
        
        if (contractorId) {
            contractors.push({
                contractor_id: contractorId,
                percentage: percentage,
                amount: amount
            });
        }
    });
    
    return contractors;
}

// ============================================================================
// GESTIÓN DE LÍNEAS DE INGRESO
// ============================================================================

function addIncomeLine(data = null) {
    const container = document.getElementById('income-lines');
    const div = document.createElement('div');
    div.className = 'income-line-row';
    
    div.innerHTML = `
        <div class="form-row">
            <div class="form-group">
                <input type="text" class="form-input line-description" 
                       placeholder="Descripción del servicio..." required
                       value="${data ? data.description : ''}">
            </div>
            <div class="form-group">
                <select class="form-input line-job-type">
                    <option value="">Seleccionar tipo...</option>
                    ${jobTypesData.map(jt => 
                        `<option value="${jt.id}" ${data && data.job_type_id === jt.id ? 'selected' : ''}>
                            ${jt.name}
                        </option>`
                    ).join('')}
                </select>
            </div>
            <div class="form-group">
                <input type="number" class="form-input line-units" 
                       placeholder="Unidades" min="0" step="0.01" required
                       value="${data ? data.units : '1'}"
                       onchange="calculateLineTotal(this)">
            </div>
            <div class="form-group">
                <input type="number" class="form-input line-unit-price" 
                       placeholder="Precio" min="0" step="0.01" required
                       value="${data ? data.unit_price : ''}"
                       onchange="calculateLineTotal(this)">
            </div>
            <div class="form-group">
                <input type="number" class="form-input line-total" 
                       placeholder="Total" readonly
                       value="${data ? data.total_amount : '0'}">
            </div>
            <div class="form-group">
                <button type="button" class="btn btn-danger btn-sm" onclick="removeLine(this)">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `;
    
    container.appendChild(div);
    
    if (data) {
        calculateLineTotal(div.querySelector('.line-units'));
    }
}

function removeLine(button) {
    button.closest('.income-line-row').remove();
    updateTotals();
}

function calculateLineTotal(input) {
    const row = input.closest('.income-line-row');
    const units = parseFloat(row.querySelector('.line-units').value) || 0;
    const unitPrice = parseFloat(row.querySelector('.line-unit-price').value) || 0;
    const total = units * unitPrice;
    
    row.querySelector('.line-total').value = total.toFixed(2);
    updateTotals();
}

function collectIncomeLines() {
    const lines = [];
    const rows = document.querySelectorAll('.income-line-row');
    
    rows.forEach(row => {
        const description = row.querySelector('.line-description').value;
        const jobTypeId = row.querySelector('.line-job-type').value;
        const units = parseFloat(row.querySelector('.line-units').value) || 0;
        const unitPrice = parseFloat(row.querySelector('.line-unit-price').value) || 0;
        
        if (description && units > 0 && unitPrice > 0) {
            lines.push({
                description: description,
                job_type_id: jobTypeId || null,
                units: units,
                unit_price: unitPrice
            });
        }
    });
    
    return lines;
}

// ============================================================================
// GESTIÓN DE PAGOS
// ============================================================================

function addPaymentLine(data = null) {
    const container = document.getElementById('payments-list');
    const div = document.createElement('div');
    div.className = 'payment-row';
    
    div.innerHTML = `
        <div class="form-row">
            <div class="form-group">
                <select class="form-input payment-type">
                    <option value="">Seleccionar tipo...</option>
                    ${paymentTypesData.map(pt => 
                        `<option value="${pt.id}" ${data && data.payment_type_id === pt.id ? 'selected' : ''}>
                            ${pt.name}
                        </option>`
                    ).join('')}
                </select>
            </div>
            <div class="form-group">
                <input type="number" class="form-input payment-amount" 
                       placeholder="Monto" min="0" step="0.01"
                       value="${data ? data.amount : ''}"
                       onchange="calculatePaymentNet(this)">
            </div>
            <div class="form-group">
                <input type="number" class="form-input payment-fee" 
                       placeholder="Fee" min="0" step="0.01"
                       value="${data ? data.fee : '0'}"
                       onchange="calculatePaymentNet(this)">
            </div>
            <div class="form-group">
                <input type="number" class="form-input payment-net" 
                       placeholder="Neto" readonly
                       value="${data ? (data.amount - data.fee) : '0'}">
            </div>
            <div class="form-group">
                <input type="text" class="form-input payment-date date-picker" 
                       placeholder="Fecha" 
                       value="${data ? data.payment_date : ''}">
            </div>
            <div class="form-group">
                <input type="text" class="form-input payment-reference" 
                       placeholder="Referencia"
                       value="${data ? data.reference_number : ''}">
            </div>
            <div class="form-group">
                <button type="button" class="btn btn-danger btn-sm" onclick="removePayment(this)">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `;
    
    container.appendChild(div);
    
    // Inicializar datepicker para el nuevo campo
    flatpickr(div.querySelector('.date-picker'), {
        dateFormat: 'Y-m-d',
        locale: 'es',
        allowInput: true
    });
    
    if (data) {
        calculatePaymentNet(div.querySelector('.payment-amount'));
    }
}

function removePayment(button) {
    button.closest('.payment-row').remove();
    updateTotals();
}

function calculatePaymentNet(input) {
    const row = input.closest('.payment-row');
    const amount = parseFloat(row.querySelector('.payment-amount').value) || 0;
    const fee = parseFloat(row.querySelector('.payment-fee').value) || 0;
    const net = amount - fee;
    
    row.querySelector('.payment-net').value = net.toFixed(2);
    updateTotals();
}

function collectPayments() {
    const payments = [];
    const rows = document.querySelectorAll('.payment-row');
    
    rows.forEach(row => {
        const paymentTypeId = row.querySelector('.payment-type').value;
        const amount = parseFloat(row.querySelector('.payment-amount').value) || 0;
        const fee = parseFloat(row.querySelector('.payment-fee').value) || 0;
        const paymentDate = row.querySelector('.payment-date').value;
        const reference = row.querySelector('.payment-reference').value;
        
        if (amount > 0) {
            payments.push({
                payment_type_id: paymentTypeId || null,
                amount: amount,
                fee: fee,
                payment_date: paymentDate || null,
                reference_number: reference || null
            });
        }
    });
    
    return payments;
}

// ============================================================================
// CÁLCULOS Y TOTALES
// ============================================================================

function updateTotals() {
    // Calcular total de ingresos
    let totalIncome = 0;
    document.querySelectorAll('.line-total').forEach(input => {
        totalIncome += parseFloat(input.value) || 0;
    });
    
    // Calcular total de pagos
    let totalPayments = 0;
    document.querySelectorAll('.payment-net').forEach(input => {
        totalPayments += parseFloat(input.value) || 0;
    });
    
    // Calcular balance
    const balance = totalIncome - totalPayments;
    
    // Actualizar displays
    document.getElementById('totalIncomeAmount').textContent = '$' + formatMoney(totalIncome);
    document.getElementById('totalPaymentsAmount').textContent = '$' + formatMoney(totalPayments);
    
    const balanceElement = document.getElementById('balanceAmount');
    balanceElement.textContent = '$' + formatMoney(balance);
    balanceElement.style.color = balance >= 0 ? '#28a745' : '#dc3545';
}

// ============================================================================
// FUNCIONES AUXILIARES
// ============================================================================

async function generateInvoiceNumber() {
    try {
        const response = await fetch('api/income/IncomesController.php?action=generateInvoiceNumber');
        const result = await response.json();
        
        if (result.success) {
            document.getElementById('invoiceNumber').value = result.data.invoice_number;
        }
    } catch (error) {
        console.error('❌ Error generando número de factura:', error);
    }
}

function populateTeamsDropdown() {
    const selects = ['#team', '#teamFilter'];
    
    selects.forEach(selector => {
        const select = document.querySelector(selector);
        if (select && selector !== '#teamFilter') {
            // Limpiar opciones existentes excepto la primera
            while (select.children.length > 1) {
                select.removeChild(select.lastChild);
            }
            
            teamsData.forEach(team => {
                const option = document.createElement('option');
                option.value = team.id;
                option.textContent = team.name;
                select.appendChild(option);
            });
        }
    });
    
    // Llenar filtro de equipos
    const teamFilter = document.getElementById('teamFilter');
    teamFilter.innerHTML = '<option value="">Todos los equipos</option>';
    teamsData.forEach(team => {
        const option = document.createElement('option');
        option.value = team.id;
        option.textContent = team.name;
        teamFilter.appendChild(option);
    });
}

function populateFilterDropdowns() {
    populateTeamsDropdown();
}

// Funciones de utilidad
function formatMoney(amount) {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(amount);
}

function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('es-ES');
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function showLoading(show) {
    // Implementar indicador de carga si es necesario
    console.log(show ? '⏳ Cargando...' : '✅ Carga completada');
}

function showNotification(message, type = 'info') {
    // Implementar sistema de notificaciones
    console.log(`${type.toUpperCase()}: ${message}`);
    alert(message); // Temporal
}

// Funciones de modal (deben existir globalmente)
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

// Funciones adicionales requeridas
function toggleFilterDropdown() {
    const dropdown = document.getElementById('filterDropdown');
    dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
}

function clearAllFilters() {
    document.getElementById('teamFilter').value = '';
    document.getElementById('statusFilter').value = '';
    document.getElementById('dateFromFilter').value = '';
    document.getElementById('dateToFilter').value = '';
    document.getElementById('searchInput').value = '';
    loadIncomes();
    toggleFilterDropdown();
}

function applyFilters() {
    loadIncomes();
    toggleFilterDropdown();
}

function updatePagination() {
    // Implementar paginación si es necesario
    console.log(`Página ${currentPage} de ${totalPages}`);
}

// Funciones para acciones de tabla
function viewIncome(id) {
    console.log('Ver ingreso:', id);
    // Implementar vista de ingreso
}

function deleteIncome(id) {
    if (confirm('¿Está seguro de que desea eliminar este ingreso?')) {
        console.log('Eliminar ingreso:', id);
        // Implementar eliminación
    }
} 