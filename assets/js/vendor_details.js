// --- Configuración ---
const API_EXPENSE_URL = 'api/expense/ExpenseController.php';
const API_VENDOR_URL = 'api/vendor/VendorController.php';

// --- Variables globales ---
let allExpenses = [];
let filteredExpenses = [];
let currentPage = 1;
let pageSize = 10;
let sortField = 'expense_date';
let sortDir = 'desc';

// Filtros
let currentFilters = {
    dateFrom: '',
    dateTo: ''
};

let tempFilters = {
    dateFrom: '',
    dateTo: ''
};

// --- Inicialización ---
document.addEventListener('DOMContentLoaded', function() {
    initializeFlatpickr();
    setupEventListeners();
    renderPageSizeSelector(); // Renderizar selector por defecto
    loadVendorDetails();
    loadVendorExpenses();
});

// --- Configuración de Flatpickr ---
function initializeFlatpickr() {
    // Configurar date pickers para filtros
    const dateFromPicker = flatpickr("#dateFromFilter", {
        locale: "es",
        dateFormat: "Y-m-d",
        allowInput: true,
        clickOpens: true,
        onChange: function(selectedDates, dateStr) {
            tempFilters.dateFrom = dateStr;
        }
    });

    const dateToPicker = flatpickr("#dateToFilter", {
        locale: "es", 
        dateFormat: "Y-m-d",
        allowInput: true,
        clickOpens: true,
        onChange: function(selectedDates, dateStr) {
            tempFilters.dateTo = dateStr;
        }
    });

    // Almacenar referencias globales
    window.dateFromPicker = dateFromPicker;
    window.dateToPicker = dateToPicker;
}

// --- Event Listeners ---
function setupEventListeners() {
    // Cerrar dropdown al hacer clic fuera
    document.addEventListener('click', function(e) {
        const dropdown = document.getElementById('filterDropdown');
        const button = document.getElementById('filterDropdownBtn');
        
        if (dropdown && !dropdown.contains(e.target) && !button.contains(e.target)) {
            dropdown.classList.remove('show');
        }
    });
}

// --- Cargar datos del proveedor ---
function loadVendorDetails() {
    fetch(`${API_VENDOR_URL}?action=getVendorById&id=${window.vendorId}`)
        .then(res => res.json())
        .then(vendor => {
            if (!vendor) {
                showToast('Proveedor no encontrado', 'error');
                setTimeout(() => window.location.href = 'vendors.php', 2000);
                return;
            }

            // Actualizar título de la página
            const titleElement = document.getElementById('vendorNameTitle');
            if (titleElement) {
                titleElement.textContent = `${vendor.name}`;
            }

            // Actualizar detalles del proveedor
            const vendorDetails = document.getElementById('vendorDetails');
            if (vendorDetails) {
                vendorDetails.innerHTML = `
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px;">
                        <div>
                            <strong>Nombre:</strong><br>
                            <span style="color: var(--text-secondary);">${vendor.name}</span>
                        </div>
                        <div>
                            <strong>Email:</strong><br>
                            <span style="color: var(--text-secondary);">${vendor.email || 'No especificado'}</span>
                        </div>
                        <div>
                            <strong>Teléfono:</strong><br>
                            <span style="color: var(--text-secondary);">${vendor.phone || 'No especificado'}</span>
                        </div>
                        <div>
                            <strong>Dirección:</strong><br>
                            <span style="color: var(--text-secondary);">${vendor.address || 'No especificada'}</span>
                        </div>
                        <div>
                            <strong>Total Gastado:</strong><br>
                            <span id="totalSpentInHeader" style="color: var(--primary-color); font-weight: 600; font-size: 16px;">$0.00</span>
                        </div>
                        <div>
                            <strong>Promedio por Gasto:</strong><br>
                            <span id="averageExpenseInHeader" style="color: var(--primary-color); font-weight: 600; font-size: 16px;">$0.00</span>
                        </div>
                    </div>
                `;
            }
        })
        .catch(err => {
            console.error('Error cargando detalles del proveedor:', err);
            showToast('Error al cargar los detalles del proveedor', 'error');
        });
}

// --- Cargar gastos del proveedor ---
function loadVendorExpenses() {
    setTableLoading(true);
    
    const params = new URLSearchParams({
        action: 'getAllExpenses',
        vendor: window.vendorId,
        limit: pageSize,
        offset: (currentPage - 1) * pageSize,
        sort: sortField,
        dir: sortDir
    });

    // Agregar filtros si están activos
    if (currentFilters.dateFrom) {
        params.append('dateFrom', currentFilters.dateFrom);
    }
    if (currentFilters.dateTo) {
        params.append('dateTo', currentFilters.dateTo);
    }

    fetch(`${API_EXPENSE_URL}?${params.toString()}`)
        .then(res => res.json())
        .then(data => {
            allExpenses = data.data || [];
            const totalCount = data.total || 0;
            
            renderExpensesTable(allExpenses);
            renderPagination(totalCount);
            renderPageSizeSelector(); // Actualizar selector
            updateStatistics();
            updateSortHeaders();
        })
        .catch(err => {
            console.error('Error cargando gastos:', err);
            showToast('Error al cargar los gastos', 'error');
            document.getElementById('expensesTableBody').innerHTML = 
                '<tr><td colspan="7" style="text-align: center; color: var(--text-secondary);">Error al cargar gastos</td></tr>';
        })
        .finally(() => {
            setTableLoading(false);
        });
}

// --- Filtros de fecha ---
function toggleFilterDropdown() {
    const dropdown = document.getElementById('filterDropdown');
    dropdown.classList.toggle('show');
    
    if (dropdown.classList.contains('show')) {
        // Sincronizar filtros temporales con los actuales
        syncTempFilters();
    }
}

function syncTempFilters() {
    tempFilters.dateFrom = currentFilters.dateFrom;
    tempFilters.dateTo = currentFilters.dateTo;
    
    // Actualizar los inputs
    if (window.dateFromPicker) {
        window.dateFromPicker.setDate(tempFilters.dateFrom || null);
    }
    if (window.dateToPicker) {
        window.dateToPicker.setDate(tempFilters.dateTo || null);
    }
}

function revertTempFilters() {
    tempFilters.dateFrom = currentFilters.dateFrom;
    tempFilters.dateTo = currentFilters.dateTo;
    
    // Actualizar los inputs
    if (window.dateFromPicker) {
        window.dateFromPicker.setDate(tempFilters.dateFrom || null);
    }
    if (window.dateToPicker) {
        window.dateToPicker.setDate(tempFilters.dateTo || null);
    }
}

function applyDateFilters() {
    // Validar que la fecha desde no sea mayor que la fecha hasta
    if (tempFilters.dateFrom && tempFilters.dateTo) {
        const fromDate = new Date(tempFilters.dateFrom);
        const toDate = new Date(tempFilters.dateTo);
        
        if (fromDate > toDate) {
            showToast('La fecha desde no puede ser mayor que la fecha hasta', 'error');
            return;
        }
    }
    
    // Aplicar filtros
    currentFilters.dateFrom = tempFilters.dateFrom;
    currentFilters.dateTo = tempFilters.dateTo;
    
    // Cerrar dropdown
    document.getElementById('filterDropdown').classList.remove('show');
    
    // Actualizar display de filtros activos
    updateActiveFiltersDisplay();
    
    // Recargar datos con filtros
    currentPage = 1;
    loadVendorExpenses();
}

function clearDateFilters() {
    tempFilters.dateFrom = '';
    tempFilters.dateTo = '';
    
    // Limpiar date pickers
    if (window.dateFromPicker) {
        window.dateFromPicker.clear();
    }
    if (window.dateToPicker) {
        window.dateToPicker.clear();
    }
}

function updateActiveFiltersDisplay() {
    const container = document.getElementById('activeFiltersContainer');
    const countElement = document.getElementById('activeFiltersCount');
    
    if (!container || !countElement) return;
    
    container.innerHTML = '';
    let activeFiltersCount = 0;
    
    // Filtro de fecha desde
    if (currentFilters.dateFrom) {
        activeFiltersCount++;
        const tag = document.createElement('div');
        tag.className = 'filter-tag';
        tag.innerHTML = `
            Desde: ${formatDate(currentFilters.dateFrom)}
            <button type="button" class="remove-filter" onclick="removeFilter('dateFrom')" title="Quitar filtro">
                <i class="fas fa-times"></i>
            </button>
        `;
        container.appendChild(tag);
    }
    
    // Filtro de fecha hasta
    if (currentFilters.dateTo) {
        activeFiltersCount++;
        const tag = document.createElement('div');
        tag.className = 'filter-tag';
        tag.innerHTML = `
            Hasta: ${formatDate(currentFilters.dateTo)}
            <button type="button" class="remove-filter" onclick="removeFilter('dateTo')" title="Quitar filtro">
                <i class="fas fa-times"></i>
            </button>
        `;
        container.appendChild(tag);
    }
    
    // Mostrar/ocultar contenedor y contador
    if (activeFiltersCount > 0) {
        container.style.display = 'flex';
        countElement.style.display = 'flex';
        countElement.textContent = activeFiltersCount;
    } else {
        container.style.display = 'none';
        countElement.style.display = 'none';
    }
}

function removeFilter(filterType) {
    switch (filterType) {
        case 'dateFrom':
            currentFilters.dateFrom = '';
            tempFilters.dateFrom = '';
            if (window.dateFromPicker) {
                window.dateFromPicker.clear();
            }
            break;
        case 'dateTo':
            currentFilters.dateTo = '';
            tempFilters.dateTo = '';
            if (window.dateToPicker) {
                window.dateToPicker.clear();
            }
            break;
    }
    
    updateActiveFiltersDisplay();
    currentPage = 1;
    loadVendorExpenses();
}

// --- Renderizar tabla de gastos ---
function renderExpensesTable(expenses = null) {
    const tbody = document.getElementById('expensesTableBody');
    
    if (!expenses || !expenses.length) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: var(--text-secondary);">No hay gastos registrados</td></tr>';
        return;
    }
    
    tbody.innerHTML = '';
    
    expenses.forEach(expense => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${formatDate(expense.expense_date)}</td>
            <td>
                <a href="javascript:void(0)" onclick="viewExpense('${expense.id}')" class="expense-number-link" title="Ver detalles del gasto">
                    <span style="font-weight: 500; color: var(--primary-color);">${expense.expense_number || '-'}</span>
                </a>
            </td>
            <td>
                <span style="font-weight: 600; color: var(--success-color);">$${parseFloat(expense.total_amount || 0).toFixed(2)}</span>
            </td>
            <td>${expense.team_name || '-'}</td>
            <td>${expense.bank_account_name || '-'}</td>
            <td>
                <span style="color: var(--text-secondary); font-size: 14px;">
                    ${expense.notes ? (expense.notes.length > 50 ? expense.notes.substring(0, 50) + '...' : expense.notes) : '-'}
                </span>
            </td>
            <td style="text-align: center;">
                <div style="display: flex; gap: 4px; justify-content: center; align-items: center;">
                    <button type="button" class="btn-icon" onclick="viewExpense('${expense.id}')" title="Ver detalles">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

// --- Estadísticas ---
function updateStatistics() {
    // Cargar estadísticas del servidor con filtros aplicados
    const params = new URLSearchParams({
        action: 'getAllExpenses',
        vendor: window.vendorId
    });
    
    // Agregar filtros si están activos
    if (currentFilters.dateFrom) {
        params.append('dateFrom', currentFilters.dateFrom);
    }
    if (currentFilters.dateTo) {
        params.append('dateTo', currentFilters.dateTo);
    }
    
    fetch(`${API_EXPENSE_URL}?${params.toString()}`)
        .then(res => res.json())
        .then(data => {
            const expenses = data.data || [];
            const stats = calculateStatistics(expenses);
            
            // Actualizar elementos en el header del proveedor
            const totalSpentInHeaderEl = document.getElementById('totalSpentInHeader');
            const averageExpenseInHeaderEl = document.getElementById('averageExpenseInHeader');
            
            if (totalSpentInHeaderEl) totalSpentInHeaderEl.textContent = '$' + stats.totalSpent.toFixed(2);
            if (averageExpenseInHeaderEl) averageExpenseInHeaderEl.textContent = '$' + stats.averageExpense.toFixed(2);
        })
        .catch(err => {
            console.error('Error cargando estadísticas:', err);
            // Fallback usando datos locales
            const stats = calculateStatistics(allExpenses);
            const totalSpentInHeaderEl = document.getElementById('totalSpentInHeader');
            const averageExpenseInHeaderEl = document.getElementById('averageExpenseInHeader');
            
            if (totalSpentInHeaderEl) totalSpentInHeaderEl.textContent = '$' + stats.totalSpent.toFixed(2);
            if (averageExpenseInHeaderEl) averageExpenseInHeaderEl.textContent = '$' + stats.averageExpense.toFixed(2);
        });
}

function calculateStatistics(expenses) {
    if (!expenses.length) {
        return {
            totalSpent: 0,
            averageExpense: 0,
            lastExpenseDate: 'N/A',
            expensesCount: 0
        };
    }
    
    const totalSpent = expenses.reduce((sum, expense) => sum + parseFloat(expense.total_amount), 0);
    const averageExpense = totalSpent / expenses.length;
    
    // Encontrar la fecha más reciente
    const sortedByDate = [...expenses].sort((a, b) => new Date(b.expense_date) - new Date(a.expense_date));
    const lastExpenseDate = sortedByDate.length > 0 ? formatDate(sortedByDate[0].expense_date) : 'N/A';
    
    return {
        totalSpent,
        averageExpense,
        lastExpenseDate,
        expensesCount: expenses.length
    };
}

// --- Funciones auxiliares ---
function viewExpense(id) {
    // Navegar a expenses.php y cargar automáticamente la vista del gasto específico
    window.location.href = `expenses.php?view=${id}`;
}

function setTableLoading(loading) {
    const tbody = document.getElementById('expensesTableBody');
    if (loading) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:40px 0;">
            <div class="loading-spinner"></div>
            <span style="display:block; margin-top:8px; color:var(--text-secondary);">Cargando gastos...</span>
        </td></tr>`;
    }
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
    setTimeout(() => { 
        toast.style.display = 'none'; 
    }, 3200);
}

function goBack() {
    window.location.href = 'vendors.php';
}

// --- Paginación ---
function renderPagination(totalCount) {
    const container = document.getElementById('expensesPagination');
    if (!container) return;
    
    container.innerHTML = '';
    
    const totalPages = Math.ceil(totalCount / pageSize);
    
    if (totalPages <= 1) {
        container.style.display = 'none';
        return;
    }
    
    container.style.display = 'flex';
    
    // Botón anterior
    if (currentPage > 1) {
        const prevBtn = document.createElement('button');
        prevBtn.className = 'btn-pagination';
        prevBtn.innerHTML = '<i class="fas fa-chevron-left"></i>';
        prevBtn.onclick = () => changePage(currentPage - 1);
        container.appendChild(prevBtn);
    }
    
    // Páginas
    const startPage = Math.max(1, currentPage - 2);
    const endPage = Math.min(totalPages, currentPage + 2);
    
    for (let i = startPage; i <= endPage; i++) {
        const btn = document.createElement('button');
        btn.className = 'btn-pagination' + (i === currentPage ? ' active' : '');
        btn.textContent = i;
        btn.onclick = () => changePage(i);
        container.appendChild(btn);
    }
    
    // Botón siguiente
    if (currentPage < totalPages) {
        const nextBtn = document.createElement('button');
        nextBtn.className = 'btn-pagination';
        nextBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
        nextBtn.onclick = () => changePage(currentPage + 1);
        container.appendChild(nextBtn);
    }
}

function renderPageSizeSelector() {
    const container = document.getElementById('pageSizeSelectorContainer');
    if (!container) return;
    
    // Solo actualizar el valor seleccionado si el selector ya existe
    const existingSelector = document.getElementById('pageSizeSelector');
    if (existingSelector) {
        existingSelector.value = pageSize;
        return;
    }
    
    container.innerHTML = '';
    const label = document.createElement('label');
    label.textContent = 'Mostrar:';
    label.style = 'margin-right: 4px; font-weight: 500; color: var(--text-secondary);';
    const selector = document.createElement('select');
    selector.id = 'pageSizeSelector';
    selector.className = 'form-input';
    selector.style = 'width: auto; display: inline-block;';
    [5, 10, 20, 50].forEach(size => {
        const opt = document.createElement('option');
        opt.value = size;
        opt.textContent = `${size} por página`;
        selector.appendChild(opt);
    });
    selector.value = pageSize;
    selector.onchange = function() {
        changePageSize(this.value);
    };
    container.appendChild(label);
    container.appendChild(selector);
}

function changePage(page) {
    currentPage = page;
    loadVendorExpenses();
}

function changePageSize(newSize) {
    pageSize = parseInt(newSize);
    currentPage = 1;
    loadVendorExpenses();
}

// --- Ordenamiento ---
function sortTable(field) {
    if (sortField === field) {
        sortDir = sortDir === 'asc' ? 'desc' : 'asc';
    } else {
        sortField = field;
        sortDir = 'asc';
    }
    
    currentPage = 1;
    loadVendorExpenses();
}

function updateSortHeaders() {
    document.querySelectorAll('.sortable').forEach(th => {
        th.classList.remove('sort-asc', 'sort-desc');
        if (th.dataset.sort === sortField) {
            th.classList.add(`sort-${sortDir}`);
        }
    });
}

// --- Formateo de fechas ---
function formatDate(dateString) {
    if (!dateString) return 'N/A';
    
    try {
        // Evitar problemas de zona horaria tratando la fecha como local
        const dateOnly = dateString.split('T')[0]; // Solo tomar la parte de fecha (YYYY-MM-DD)
        const dateParts = dateOnly.split('-');
        
        if (dateParts.length !== 3) {
            throw new Error('Formato de fecha inválido');
        }
        
        const year = parseInt(dateParts[0]);
        const month = parseInt(dateParts[1]) - 1; // Mes es 0-indexado en JavaScript
        const day = parseInt(dateParts[2]);
        
        const date = new Date(year, month, day);
        
        // Verificar que la fecha sea válida
        if (isNaN(date.getTime())) {
            throw new Error('Fecha inválida');
        }
        
        return date.toLocaleDateString('es-ES', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    } catch (error) {
        console.warn('Error formateando fecha:', dateString, error);
        return dateString; // Devolver el string original si hay error
    }
} 