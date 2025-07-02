// --- Configuración ---
const API_URL = 'api/expense/ExpenseController.php';
let editingExpenseId = null;
let sortField = 'expense_date';
let sortDir = 'desc';
let expenseLineCounter = 0;

// --- Variables de paginación ---
let currentPage = 1;
let pageSize = 10;
let totalExpensesCount = 0;

// --- Variables de filtros ---
let currentFilters = {
    search: '',
    team: '',
    vendor: '',
    dateFrom: '',
    dateTo: ''
};

// Variables temporales para filtros del dropdown
let tempFilters = {
    team: '',
    vendor: '',
    dateFrom: '',
    dateTo: ''
};

// --- Datos de referencia ---
let teams = [];
let vendors = [];
let bankAccounts = [];
let expenseTypes = [];

// Variable global para mantener los archivos seleccionados
let selectedFiles = [];

// --- Cargar datos al iniciar ---
document.addEventListener('DOMContentLoaded', function() {
    loadReferenceData();
    loadExpenses();
    setupEventListeners();
    initializeFlatpickr();
    updateActiveFiltersDisplay(); // Mostrar filtros activos al cargar
    
    // Configurar ordenamiento después de un delay para asegurar que el DOM esté listo
    setTimeout(() => {
        setupTableSorting();
    }, 100);
    
    // Verificar si hay un parámetro 'view' en la URL para abrir automáticamente la vista del gasto
    checkForViewParameter();
});

// --- Verificar parámetros en URL ---
function checkForViewParameter() {
    const urlParams = new URLSearchParams(window.location.search);
    const viewExpenseId = urlParams.get('view');
    const vendorId = urlParams.get('vendor');
    
    if (viewExpenseId) {
        // Esperar un poco para que se carguen los datos de referencia
        setTimeout(() => {
            viewExpense(viewExpenseId);
            // Limpiar el parámetro de la URL sin recargar la página
            const newUrl = window.location.pathname + window.location.search.replace(/[?&]view=[^&]*/, '').replace(/^&/, '?');
            window.history.replaceState({}, '', newUrl);
        }, 500);
    }
    
    if (vendorId) {
        // Esperar a que se carguen los datos de referencia y luego preseleccionar el proveedor
        setTimeout(() => {
            preselectVendorAndOpenModal(vendorId);
            // Limpiar el parámetro de la URL sin recargar la página
            const newUrl = window.location.pathname + window.location.search.replace(/[?&]vendor=[^&]*/, '').replace(/^&/, '?');
            window.history.replaceState({}, '', newUrl);
        }, 500);
    }
}

// --- Preseleccionar proveedor y abrir modal ---
function preselectVendorAndOpenModal(vendorId) {
    // Verificar que el proveedor existe y está activo en la lista cargada
    const vendor = vendors.find(v => v.id === vendorId);
    
    if (!vendor) {
        showToast('El proveedor especificado no está disponible o está inactivo.', 'warning');
        return;
    }
    
    // Abrir modal de nuevo gasto
    openModal('expenseModal');
    
    // Preseleccionar el proveedor
    const vendorSelect = document.getElementById('vendor');
    if (vendorSelect) {
        vendorSelect.value = vendorId;
        // Disparar evento change para cualquier validación
        vendorSelect.dispatchEvent(new Event('change'));
    }
    
    showToast(`Proveedor "${vendor.name}" preseleccionado.`, 'success');
}

// --- Inicializar Flatpickr ---
function initializeFlatpickr() {
    // Configuración en español
    flatpickr.localize(flatpickr.l10ns.es);
    
    // Date picker para modal
    flatpickr("#expenseDate", {
        dateFormat: "Y-m-d",
        defaultDate: new Date(),
        locale: "es",
        allowInput: false,
        clickOpens: true
    });
    
    // Date pickers para filtros con configuración mejorada
    window.dateFromPicker = flatpickr("#dateFromFilter", {
        dateFormat: "Y-m-d",
        locale: "es",
        allowInput: true,
        clickOpens: true,
        appendTo: document.body, // Renderizar en el body para evitar problemas con el dropdown
        onChange: function(selectedDates, dateStr) {
            tempFilters.dateFrom = dateStr;
            
            // Actualizar el mínimo del date picker "hasta"
            if (dateStr && window.dateToPicker) {
                window.dateToPicker.set('minDate', dateStr);
                
                // Si la fecha "hasta" es anterior a la nueva fecha "desde", limpiarla
                if (tempFilters.dateTo && tempFilters.dateTo < dateStr) {
                    window.dateToPicker.clear();
                    tempFilters.dateTo = '';
                }
            }
        },
        onClose: function(selectedDates, dateStr) {
            tempFilters.dateFrom = dateStr;
        },
        onOpen: function(selectedDates, dateStr, instance) {
            // Asegurar que el calendario esté por encima del dropdown
            instance.calendarContainer.style.zIndex = '9999';
        }
    });
    
    window.dateToPicker = flatpickr("#dateToFilter", {
        dateFormat: "Y-m-d", 
        locale: "es",
        allowInput: true,
        clickOpens: true,
        appendTo: document.body, // Renderizar en el body para evitar problemas con el dropdown
        onChange: function(selectedDates, dateStr) {
            tempFilters.dateTo = dateStr;
        },
        onClose: function(selectedDates, dateStr) {
            tempFilters.dateTo = dateStr;
        },
        onOpen: function(selectedDates, dateStr, instance) {
            // Asegurar que el calendario esté por encima del dropdown
            instance.calendarContainer.style.zIndex = '9999';
        }
    });
    
    // Agregar event listeners adicionales como backup
    document.getElementById('dateFromFilter').addEventListener('change', function() {
        tempFilters.dateFrom = this.value;
    });
    
    document.getElementById('dateToFilter').addEventListener('change', function() {
        tempFilters.dateTo = this.value;
    });
    
    // Forzar que los inputs sean clickeables
    setTimeout(() => {
        const dateFromInput = document.getElementById('dateFromFilter');
        const dateToInput = document.getElementById('dateToFilter');
        
        if (dateFromInput) {
            dateFromInput.removeAttribute('readonly');
            dateFromInput.style.cursor = 'pointer';
        }
        
        if (dateToInput) {
            dateToInput.removeAttribute('readonly');
            dateToInput.style.cursor = 'pointer';
        }
    }, 100);
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
            loadExpenses(1);
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

// --- Cargar datos de referencia ---
function loadReferenceData() {
    Promise.all([
        fetch(`${API_URL}?action=getTeams`).then(res => res.json()),
        fetch(`${API_URL}?action=getVendors`).then(res => res.json()),
        fetch(`${API_URL}?action=getBankAccounts`).then(res => res.json()),
        fetch(`${API_URL}?action=getExpenseTypes`).then(res => res.json())
    ]).then(([teamsData, vendorsData, bankAccountsData, expenseTypesData]) => {
        teams = teamsData;
        vendors = vendorsData;
        bankAccounts = bankAccountsData;
        expenseTypes = expenseTypesData;
        
        populateSelectors();
    }).catch(error => {
        showToast('Error al cargar datos de referencia', 'error');
    });
}

// --- Poblar selectores ---
function populateSelectors() {
    // Poblar selector de equipos en el modal
    const teamSelect = document.getElementById('team');
    teamSelect.innerHTML = '<option value="">Seleccionar equipo...</option>';
    teams.forEach(team => {
        teamSelect.innerHTML += `<option value="${team.id}">${team.name}</option>`;
    });
    
    // Poblar selector de equipos en el filtro
    const teamFilter = document.getElementById('teamFilter');
    teamFilter.innerHTML = '<option value="">Todos los equipos</option>';
    teams.forEach(team => {
        teamFilter.innerHTML += `<option value="${team.id}">${team.name}</option>`;
    });
    
    // Poblar selector de proveedores en el modal (solo activos - filtrado en backend)
    const vendorSelect = document.getElementById('vendor');
    vendorSelect.innerHTML = '<option value="">Seleccionar proveedor...</option>';
    // Filtro adicional en frontend por seguridad (aunque el backend ya filtra)
    const activeVendors = vendors.filter(vendor => vendor.status !== 0 && vendor.status !== 'inactive');
    activeVendors.forEach(vendor => {
        vendorSelect.innerHTML += `<option value="${vendor.id}">${vendor.name}</option>`;
    });
    
    // Poblar selector de proveedores en el filtro (incluye todos para filtrado)
    const vendorFilter = document.getElementById('vendorFilter');
    vendorFilter.innerHTML = '<option value="">Todos los proveedores</option>';
    vendors.forEach(vendor => {
        vendorFilter.innerHTML += `<option value="${vendor.id}">${vendor.name}</option>`;
    });
    
    // Poblar selector de cuentas bancarias (solo activas)
    const bankAccountSelect = document.getElementById('bankAccount');
    bankAccountSelect.innerHTML = '<option value="">Seleccionar cuenta...</option>';
    const activeBankAccounts = bankAccounts.filter(account => account.active === 1 || account.active === '1' || account.active === true);
    activeBankAccounts.forEach(account => {
        bankAccountSelect.innerHTML += `<option value="${account.id}">${account.name} (${account.account_type}) - $${parseFloat(account.balance).toLocaleString('es-MX', {minimumFractionDigits:2})}</option>`;
    });
}

// --- Configurar event listeners ---
function setupEventListeners() {
    // Búsqueda
    document.getElementById('searchInput').addEventListener('input', function() {
        clearTimeout(this.searchTimeout);
        this.searchTimeout = setTimeout(() => {
            currentFilters.search = this.value;
            loadExpenses(1);
        }, 300);
    });
    
    // Filtros de equipo y proveedor
    document.getElementById('teamFilter').addEventListener('change', function() {
        tempFilters.team = this.value;
    });
    
    document.getElementById('vendorFilter').addEventListener('change', function() {
        tempFilters.vendor = this.value;
    });
    
    // Formulario de gasto
    document.getElementById('expenseForm').addEventListener('submit', handleExpenseSubmit);
    
    // Drag and drop para archivos
    setupDragAndDrop();
    
    // Cerrar dropdown de filtros al hacer click fuera
    document.addEventListener('click', function(e) {
        const dropdown = document.getElementById('filterDropdown');
        const filterContainer = e.target.closest('.filter-dropdown-container');
        const flatpickrCalendar = e.target.closest('.flatpickr-calendar');
        const flatpickrInput = e.target.closest('.flatpickr-input');
        const flatpickrElement = e.target.closest('.flatpickr-wrapper');
        const datePickerInput = e.target.classList.contains('date-picker') || e.target.closest('.date-picker');
        
        // No cerrar si se hace clic dentro del dropdown, en elementos de flatpickr
        if (!filterContainer && !flatpickrCalendar && !flatpickrInput && !flatpickrElement && !datePickerInput && dropdown && dropdown.classList.contains('show')) {
            dropdown.classList.remove('show');
            revertTempFilters();
        }
    });
}

// --- Configurar drag and drop ---
function setupDragAndDrop() {
    const fileUploadArea = document.getElementById('fileUploadArea');
    const fileInput = document.getElementById('attachments');
    
    // Click para abrir selector de archivos
    fileUploadArea.addEventListener('click', () => {
        fileInput.click();
    });
    
    // Manejar selección manual de archivos
    fileInput.addEventListener('change', function(e) {
        if (this.files.length > 0) {
            const newFiles = Array.from(this.files);
            addFilesToSelected(newFiles);
            // Limpiar el input para permitir seleccionar los mismos archivos otra vez
            this.value = '';
        }
    });
    
    // Prevenir comportamiento por defecto
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        fileUploadArea.addEventListener(eventName, preventDefaults, false);
        document.body.addEventListener(eventName, preventDefaults, false);
    });
    
    // Resaltar zona de drop
    ['dragenter', 'dragover'].forEach(eventName => {
        fileUploadArea.addEventListener(eventName, highlight, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        fileUploadArea.addEventListener(eventName, unhighlight, false);
    });
    
    // Manejar drop
    fileUploadArea.addEventListener('drop', handleDrop, false);
}

function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

function highlight(e) {
    document.getElementById('fileUploadArea').classList.add('drag-over');
}

function unhighlight(e) {
    document.getElementById('fileUploadArea').classList.remove('drag-over');
}

function handleDrop(e) {
    const dt = e.dataTransfer;
    const newFiles = Array.from(dt.files);
    addFilesToSelected(newFiles);
}

function addFilesToSelected(newFiles) {
    // Validar límite total de archivos
    const existingAttachments = document.querySelectorAll('.existing-attachments .attachment-item-preview').length;
    const filesToDelete = window.attachmentsToDelete ? window.attachmentsToDelete.length : 0;
    const currentAttachments = existingAttachments - filesToDelete;
    const totalFiles = selectedFiles.length + newFiles.length + currentAttachments;
    
    if (totalFiles > 4) {
        const currentTotal = selectedFiles.length + currentAttachments;
        showToast(`No puedes agregar ${newFiles.length} archivo(s). Límite: 4 archivos. Actualmente tienes ${currentTotal} archivos.`, 'error');
        return;
    }
    
    let addedCount = 0;
    
    // Validar cada archivo nuevo
    for (let file of newFiles) {
        if (file.size > 2 * 1024 * 1024) {
            showToast(`Archivo ${file.name} excede 2MB`, 'error');
            continue;
        }
        
        const allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
        if (!allowedTypes.includes(file.type)) {
            showToast(`Tipo de archivo no permitido: ${file.name}`, 'error');
            continue;
        }
        
        // Verificar duplicados por nombre
        if (selectedFiles.some(f => f.name === file.name && f.size === file.size)) {
            showToast(`El archivo ${file.name} ya está seleccionado`, 'warning');
            continue;
        }
        
        selectedFiles.push(file);
        addedCount++;
    }
    
    // Actualizar el input con todos los archivos
    updateFileInput();
    
    // Actualizar vista previa
    handleFilePreview();
    
    // Mostrar mensaje de confirmación
    if (addedCount > 0) {
        const total = selectedFiles.length + currentAttachments;
        showToast(`${addedCount} archivo(s) agregado(s). Total: ${total}/4 archivos`, 'success');
    }
}

function updateFileInput() {
    const fileInput = document.getElementById('attachments');
    const dt = new DataTransfer();
    selectedFiles.forEach(file => dt.items.add(file));
    fileInput.files = dt.files;
}

function removeNewAttachment(index) {
    selectedFiles.splice(index, 1);
    updateFileInput();
    handleFilePreview();
}

// --- Paginación ---
function renderPagination() {
    const container = document.getElementById('expensesPagination');
    if (!container) return;
    
    const totalPages = Math.ceil(totalExpensesCount / pageSize);
    if (totalPages <= 1) {
        container.innerHTML = '';
        return;
    }
    
    let html = '<div style="display: flex; gap: 4px; align-items: center;">';
    
    // Botón anterior
    if (currentPage > 1) {
        html += `<button onclick="loadExpenses(${currentPage - 1})" class="btn-pagination">‹</button>`;
    }
    
    // Números de página
    const startPage = Math.max(1, currentPage - 2);
    const endPage = Math.min(totalPages, currentPage + 2);
    
    if (startPage > 1) {
        html += `<button onclick="loadExpenses(1)" class="btn-pagination">1</button>`;
        if (startPage > 2) html += '<span style="padding: 0 8px;">...</span>';
    }
    
    for (let i = startPage; i <= endPage; i++) {
        const isActive = i === currentPage ? ' active' : '';
        html += `<button onclick="loadExpenses(${i})" class="btn-pagination${isActive}">${i}</button>`;
    }
    
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) html += '<span style="padding: 0 8px;">...</span>';
        html += `<button onclick="loadExpenses(${totalPages})" class="btn-pagination">${totalPages}</button>`;
    }
    
    // Botón siguiente
    if (currentPage < totalPages) {
        html += `<button onclick="loadExpenses(${currentPage + 1})" class="btn-pagination">›</button>`;
    }
    
    html += '</div>';
    container.innerHTML = html;
}

// --- Cargar gastos ---
function loadExpenses(page = 1) {
    currentPage = page;
    setTableLoading(true);
    
    let url = `${API_URL}?action=getAllExpenses&limit=${pageSize}&offset=${(page-1)*pageSize}&sort=${sortField}&dir=${sortDir}`;
    
    if (currentFilters.search) url += `&search=${encodeURIComponent(currentFilters.search)}`;
    if (currentFilters.team) url += `&team=${encodeURIComponent(currentFilters.team)}`;
    if (currentFilters.vendor) url += `&vendor=${encodeURIComponent(currentFilters.vendor)}`;
    if (currentFilters.dateFrom) url += `&dateFrom=${encodeURIComponent(currentFilters.dateFrom)}`;
    if (currentFilters.dateTo) url += `&dateTo=${encodeURIComponent(currentFilters.dateTo)}`;
    

    
    fetch(url)
        .then(res => res.json())
        .then(data => {
            const expenses = data.data || data;
            totalExpensesCount = data.total || expenses.length;
            renderExpensesTable(expenses);
            renderPagination();
            renderPageSizeSelector();
            
            // Actualizar contador total en el header
            document.getElementById('totalExpenses').textContent = totalExpensesCount;
            
            // Actualizar iconos de ordenamiento
            updateSortIcons();
        })
        .catch(() => {
            document.getElementById('expensesTableBody').innerHTML = '<tr><td colspan="8">Error al cargar gastos</td></tr>';
            document.getElementById('totalExpenses').textContent = '0';
        })
        .finally(() => setTableLoading(false));
}

function setTableLoading(loading) {
    const tbody = document.getElementById('expensesTableBody');
    if (loading) {
        tbody.innerHTML = '<tr><td colspan="8"><div class="loading-spinner">Cargando...</div></td></tr>';
    }
}

// --- Renderizar tabla de gastos ---
function renderExpensesTable(expenses) {
    const tbody = document.getElementById('expensesTableBody');
    
    if (!expenses || expenses.length === 0) {
        // Determinar mensaje apropiado basado en filtros activos
        let message = 'No hay gastos registrados';
        
        const hasActiveFilters = currentFilters.team || currentFilters.vendor || 
                               currentFilters.dateFrom || currentFilters.dateTo || 
                               currentFilters.search;
        
        if (hasActiveFilters) {
            message = 'No se encontraron gastos que coincidan con los filtros aplicados';
        }
        
        tbody.innerHTML = `<tr><td colspan="9" class="text-center text-muted" style="padding: 40px 20px;">${message}</td></tr>`;
        return;
    }
    
    tbody.innerHTML = expenses.map(expense => {
        // Formatear cuenta bancaria
        const bankAccount = expense.bank_account_name 
            ? `${expense.bank_account_name}${expense.bank_account_type ? ` (${expense.bank_account_type})` : ''}`
            : 'N/A';
        
        // Indicador de archivos adjuntos
        const attachmentCount = parseInt(expense.attachment_count) || 0;
        const attachmentIcon = attachmentCount > 0 
            ? `<div class="attachment-badge-container" onclick="toggleAttachmentsDropdown('${expense.id}', this)" title="Click para ver archivos">
                 <span class="badge badge-info attachment-badge">
                   <i class="fas fa-paperclip"></i> ${attachmentCount}
                 </span>
                 <div class="attachments-dropdown" data-expense-id="${expense.id}">
                   <!-- Se carga dinámicamente -->
                 </div>
               </div>`
            : '<span class="text-muted">—</span>';
        
        // Notas del gasto (campo notes de la tabla expenses)
        const notesText = expense.notes || '';
        const notes = notesText && notesText.trim() 
            ? (notesText.length > 60 
                ? `<span title="${escapeHtml(notesText)}" class="notes-preview">${escapeHtml(notesText.substring(0, 60))}...</span>`
                : `<span class="notes-preview">${escapeHtml(notesText)}</span>`)
            : '<span class="text-muted">—</span>';
        
        return `
            <tr>
                <td><strong>${generateExpenseNumber(expense)}</strong></td>
                <td>${formatDate(expense.expense_date)}</td>
                <td>${escapeHtml(expense.team_name || 'N/A')}</td>
                <td>${escapeHtml(expense.vendor_name || 'N/A')}</td>
                <td>${escapeHtml(bankAccount)}</td>
                <td>$${parseFloat(expense.total_amount || 0).toLocaleString('es-MX', {minimumFractionDigits: 2})}</td>
                <td style="text-align: left;">${attachmentIcon}</td>
                <td style="max-width: 200px;">${notes}</td>
                <td class="table-actions">
                    <button onclick="viewExpense('${expense.id}')" class="btn-action" title="Ver detalles">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button onclick="editExpense('${expense.id}')" class="btn-action" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button onclick="showDeleteModal('${expense.id}')" class="btn-action btn-danger" title="Eliminar">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

// --- Modales ---
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    
    // Resetear scroll a la parte superior del modal
    const modalContent = modal.querySelector('.modal-content');
    if (modalContent) {
        modalContent.scrollTop = 0;
    }
    
    // También resetear scroll del body del modal si existe
    const modalBody = modal.querySelector('.modal-body');
    if (modalBody) {
        modalBody.scrollTop = 0;
    }
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
    document.body.style.overflow = 'auto';
}

function resetExpenseForm() {
    document.getElementById('expenseForm').reset();
    document.getElementById('expense-lines').innerHTML = '';
    document.getElementById('attachments-preview').innerHTML = '';
    document.getElementById('totalAmount').textContent = '$0.00';
    editingExpenseId = null;
    
    // Establecer fecha actual usando Flatpickr
    flatpickr("#expenseDate").setDate(new Date());
    
    // Limpiar archivos adjuntos y lista de eliminación
    document.getElementById('attachments').value = '';
    window.attachmentsToDelete = [];
    selectedFiles = []; // Limpiar array de archivos seleccionados
    
    // Configurar modal para nuevo gasto
    document.querySelector('#expenseModal .modal-header h2').textContent = 'Nuevo Gasto';
    document.querySelector('#expenseModal .btn-primary').style.display = 'inline-flex';
    
    // Asegurar que todos los campos estén habilitados
    const form = document.getElementById('expenseForm');
    const inputs = form.querySelectorAll('input, select, textarea, button');
    inputs.forEach(input => {
        input.disabled = false;
    });
    
    addExpenseLine(); // Agregar línea inicial
}

// --- Manejo de líneas de gastos ---
function addExpenseLine() {
    expenseLineCounter++;
    const lineId = expenseLineCounter;
    
    // Crear opciones de tipos de gasto
    let expenseTypeOptions = '<option value="">Seleccionar tipo...</option>';
    expenseTypes.forEach(type => {
        expenseTypeOptions += `<option value="${type.id}">${type.name}</option>`;
    });
    
    const lineHtml = `
        <div class="expense-line" data-line-id="${lineId}">
            <input type="text" class="form-input line-description" placeholder="Descripción del gasto..." required onblur="validateLineField(this, 'description')">
            <select class="form-input line-expense-type" required onchange="validateLineField(this, 'expense_type')">
                ${expenseTypeOptions}
            </select>
            <input type="number" class="form-input line-amount" min="0.01" step="0.01" placeholder="0.00" required onchange="calculateTotal(); validateLineField(this, 'amount')" onblur="validateLineField(this, 'amount')">
            <label class="deducible-switch">
                <input type="checkbox" class="line-deducible">
                <span class="switch-slider"></span>
            </label>
            <button type="button" class="btn-remove-line" onclick="removeExpenseLine(${lineId})" title="Eliminar línea">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    `;
    
    document.getElementById('expense-lines').insertAdjacentHTML('beforeend', lineHtml);
    calculateTotal();
}

function removeExpenseLine(lineId) {
    const line = document.querySelector(`[data-line-id="${lineId}"]`);
    if (line) {
        line.remove();
        calculateTotal();
    }
}

function calculateTotal() {
    let total = 0;
    document.querySelectorAll('.line-amount').forEach(input => {
        const amount = parseFloat(input.value) || 0;
        total += amount;
    });
    document.getElementById('totalAmount').textContent = `$${total.toLocaleString('es-MX', {minimumFractionDigits: 2})}`;
}

// Función para validar campos de línea en tiempo real
function validateLineField(element, fieldType) {
    const line = element.closest('.expense-line');
    let isValid = true;
    let errorMessage = '';
    
    // Remover clases de error previas
    element.classList.remove('error', 'success');
    
    switch (fieldType) {
        case 'description':
            const description = element.value.trim();
            if (!description) {
                isValid = false;
                errorMessage = 'La descripción es obligatoria';
            }
            break;
            
        case 'expense_type':
            if (!element.value) {
                isValid = false;
                errorMessage = 'Seleccione un tipo de gasto';
            }
            break;
            
        case 'amount':
            const amount = parseFloat(element.value);
            if (isNaN(amount) || amount < 0.01) {
                isValid = false;
                errorMessage = 'El importe debe ser mayor a $0.01';
            }
            break;
    }
    
    // Aplicar estilos según validación
    if (isValid) {
        element.classList.add('success');
        // Remover mensaje de error si existe
        const existingError = line.querySelector('.field-error-message');
        if (existingError) {
            existingError.remove();
        }
    } else {
        element.classList.add('error');
        // Mostrar mensaje de error
        showFieldError(element, errorMessage);
    }
    
    return isValid;
}

// Función para mostrar mensaje de error en campo específico
function showFieldError(element, message) {
    // Remover mensaje de error anterior si existe
    const existingError = element.parentNode.querySelector('.field-error-message');
    if (existingError) {
        existingError.remove();
    }
    
    // Crear nuevo mensaje de error
    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error-message';
    errorDiv.textContent = message;
    
    // Insertar después del elemento
    element.parentNode.insertBefore(errorDiv, element.nextSibling);
    
    // Remover automáticamente después de 3 segundos
    setTimeout(() => {
        if (errorDiv.parentNode) {
            errorDiv.remove();
        }
    }, 3000);
}

// Función para validar campos principales del formulario
function validateMainField(element, fieldType) {
    let isValid = true;
    let errorMessage = '';
    
    // Remover clases de error previas
    element.classList.remove('error', 'success');
    
    switch (fieldType) {
        case 'date':
            if (!element.value) {
                isValid = false;
                errorMessage = 'La fecha es obligatoria';
            }
            break;
            
        case 'team':
            if (!element.value) {
                isValid = false;
                errorMessage = 'Seleccione un equipo';
            }
            break;
            
        case 'vendor':
            if (!element.value) {
                isValid = false;
                errorMessage = 'Seleccione un proveedor';
            }
            break;
            
        case 'bankAccount':
            if (!element.value) {
                isValid = false;
                errorMessage = 'Seleccione una cuenta bancaria';
            }
            break;
    }
    
    // Aplicar estilos según validación
    if (isValid) {
        element.classList.add('success');
        // Remover mensaje de error si existe
        const existingError = element.parentNode.querySelector('.field-error-message');
        if (existingError) {
            existingError.remove();
        }
    } else {
        element.classList.add('error');
        // Mostrar mensaje de error
        showFieldError(element, errorMessage);
    }
    
    return isValid;
}

// Función para validar todos los campos del formulario
function validateAllFields() {
    let allValid = true;
    
    // Validar campos principales
    const dateField = document.getElementById('expenseDate');
    const teamField = document.getElementById('team');
    const vendorField = document.getElementById('vendor');
    const bankAccountField = document.getElementById('bankAccount');
    
    if (!validateMainField(dateField, 'date')) allValid = false;
    if (!validateMainField(teamField, 'team')) allValid = false;
    if (!validateMainField(vendorField, 'vendor')) allValid = false;
    if (!validateMainField(bankAccountField, 'bankAccount')) allValid = false;
    
    // Validar líneas de gasto
    const expenseLines = document.querySelectorAll('.expense-line');
    if (expenseLines.length === 0) {
        showToast('Debe agregar al menos una línea de gasto', 'error');
        allValid = false;
    } else {
        expenseLines.forEach((line, index) => {
            const description = line.querySelector('.line-description');
            const expenseType = line.querySelector('.line-expense-type');
            const amount = line.querySelector('.line-amount');
            
            if (!validateLineField(description, 'description')) allValid = false;
            if (!validateLineField(expenseType, 'expense_type')) allValid = false;
            if (!validateLineField(amount, 'amount')) allValid = false;
        });
    }
    
    return allValid;
}

// --- Manejo de archivos adjuntos ---
function handleFilePreview() {
    const files = document.getElementById('attachments').files;
    const preview = document.getElementById('attachments-preview');
    
    // No validar límites aquí ya que se valida en addFilesToSelected
    
    // Buscar si ya existe un contenedor para nuevos archivos
    let newFilesContainer = preview.querySelector('.new-attachments');
    if (!newFilesContainer) {
        newFilesContainer = document.createElement('div');
        newFilesContainer.className = 'new-attachments';
        if (files.length > 0) {
            newFilesContainer.innerHTML = '<h4 class="new-files-title">Nuevos archivos:</h4>';
        }
        preview.appendChild(newFilesContainer);
    } else {
        // Limpiar contenido existente pero mantener el título
        const title = newFilesContainer.querySelector('.new-files-title');
        newFilesContainer.innerHTML = '';
        if (files.length > 0 && title) {
            newFilesContainer.appendChild(title);
        }
    }
    
    // Mostrar todos los archivos nuevos
    Array.from(files).forEach((file, index) => {
        const fileIcon = getFileIcon(file.type);
        const fileSize = (file.size / 1024 / 1024).toFixed(2);
        
        const div = document.createElement('div');
        div.className = 'attachment-item-preview new';
        div.innerHTML = `
            <div class="attachment-preview-content">
                <i class="fas ${fileIcon}"></i>
                <div class="attachment-info">
                    <div class="attachment-name">${escapeHtml(file.name)}</div>
                    <div class="attachment-size">${fileSize} MB</div>
                </div>
            </div>
            <button type="button" class="btn-remove-attachment" onclick="removeNewAttachment(${index})" title="Eliminar archivo">
                <i class="fas fa-times"></i>
            </button>
        `;
        newFilesContainer.appendChild(div);
    });
    
    // Si no hay archivos nuevos, remover el contenedor
    if (files.length === 0 && newFilesContainer) {
        newFilesContainer.remove();
    }
}

// Función para mostrar archivos existentes en modo edición
function displayExistingAttachments(attachments) {
    const preview = document.getElementById('attachments-preview');
    
    if (!attachments || attachments.length === 0) {
        return;
    }
    
    // Crear contenedor para archivos existentes
    const existingContainer = document.createElement('div');
    existingContainer.className = 'existing-attachments';
    existingContainer.innerHTML = '<h4 class="existing-title">Archivos actuales:</h4>';
    
    attachments.forEach((attachment, index) => {
        const fileIcon = getFileIcon(attachment.mime_type);
        const fileSize = (attachment.file_size / 1024 / 1024).toFixed(2);
        
        const div = document.createElement('div');
        div.className = 'attachment-item-preview existing';
        div.innerHTML = `
            <div class="attachment-preview-content">
                <i class="fas ${fileIcon}"></i>
                <div class="attachment-info">
                    <div class="attachment-name">${escapeHtml(attachment.original_filename)}</div>
                    <div class="attachment-size">${fileSize} MB</div>
                </div>
            </div>
            <button type="button" class="btn-remove-attachment" onclick="removeExistingAttachment('${attachment.id}')" title="Eliminar archivo">
                <i class="fas fa-times"></i>
            </button>
        `;
        existingContainer.appendChild(div);
    });
    
    preview.appendChild(existingContainer);
}

function removeExistingAttachment(attachmentId) {
    // Marcar para eliminación
    if (!window.attachmentsToDelete) {
        window.attachmentsToDelete = [];
    }
    window.attachmentsToDelete.push(attachmentId);
    
    // Remover del DOM
    const attachmentElement = document.querySelector(`[onclick*="${attachmentId}"]`).closest('.attachment-item-preview');
    attachmentElement.remove();
    
    showToast('Archivo marcado para eliminación', 'info');
}

// --- Manejo del formulario ---
function handleExpenseSubmit(e) {
    e.preventDefault();
    
    // Hacer una validación final de todos los campos antes de enviar
    if (!validateAllFields()) {
        showToast('Por favor, corrija los errores en el formulario antes de continuar', 'error');
        return;
    }
    
    const formData = collectFormData();
    if (!validateFormData(formData)) return;
    
    // Validar límite de archivos (nuevos + existentes)
    const existingFiles = document.querySelectorAll('.existing-attachments .attachment-item-preview').length;
    const filesToDelete = window.attachmentsToDelete ? window.attachmentsToDelete.length : 0;
    const totalFiles = selectedFiles.length + existingFiles - filesToDelete;
    
    if (totalFiles > 4) {
        showToast(`Máximo 4 archivos permitidos. Actualmente: ${existingFiles - filesToDelete} existentes + ${selectedFiles.length} nuevos = ${totalFiles} total.`, 'error');
        return;
    }
    
    const submitData = new FormData();
    
    // Datos básicos
    Object.entries(formData).forEach(([key, value]) => {
        if (key !== 'lines' && key !== 'attachments') {
            submitData.append(key, value);
        }
    });
    
    // Líneas de gastos
    submitData.append('lines', JSON.stringify(formData.lines));
    
    // Archivos adjuntos nuevos
    for (let i = 0; i < selectedFiles.length; i++) {
        submitData.append('attachments[]', selectedFiles[i]);
    }
    
    // Archivos a eliminar
    if (window.attachmentsToDelete && window.attachmentsToDelete.length > 0) {
        submitData.append('delete_attachments', JSON.stringify(window.attachmentsToDelete));
    }
    
    const url = editingExpenseId 
        ? `${API_URL}?action=updateExpense&id=${editingExpenseId}`
        : `${API_URL}?action=createExpense`;
    
    fetch(url, {
        method: 'POST',
        body: submitData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(editingExpenseId ? 'Gasto actualizado' : 'Gasto creado', 'success');
            closeModal('expenseModal');
            loadExpenses();
        } else {
            showToast(data.message || 'Error al guardar gasto', 'error');
        }
    })
    .catch(() => {
        showToast('Error de conexión', 'error');
    });
}

function collectFormData() {
    const lines = [];
    document.querySelectorAll('.expense-line').forEach(line => {
        const description = line.querySelector('.line-description').value.trim();
        const expenseTypeId = line.querySelector('.line-expense-type').value;
        const amount = parseFloat(line.querySelector('.line-amount').value) || 0;
        const deducible = line.querySelector('.line-deducible').checked;
        
        if (description && amount > 0) {
            lines.push({
                description: description,
                expense_type_id: expenseTypeId,
                amount: amount,
                deducible: deducible
            });
        }
    });

    return {
        team_id: document.getElementById('team').value,
        vendor_id: document.getElementById('vendor').value,
        bank_account_id: document.getElementById('bankAccount').value,
        expense_date: document.getElementById('expenseDate').value,
        notes: document.getElementById('notes').value.trim(),
        lines: lines
    };
}

function validateFormData(data) {
    // Validar campos obligatorios principales
    if (!data.team_id) {
        showToast('Seleccione un equipo', 'error');
        return false;
    }
    if (!data.vendor_id) {
        showToast('Seleccione un proveedor', 'error');
        return false;
    }
    if (!data.bank_account_id) {
        showToast('Seleccione una cuenta bancaria', 'error');
        return false;
    }
    if (!data.expense_date) {
        showToast('Ingrese la fecha del gasto', 'error');
        return false;
    }
    
    // Validar que haya al menos una línea de gasto
    if (!data.lines || data.lines.length === 0) {
        showToast('Agregue al menos una línea de gasto', 'error');
        return false;
    }
    
    // Validar cada línea de gasto
    for (let i = 0; i < data.lines.length; i++) {
        const line = data.lines[i];
        const lineNumber = i + 1;
        
        // Validar descripción (obligatoria)
        if (!line.description || line.description.trim() === '') {
            showToast(`La línea ${lineNumber} debe tener una descripción`, 'error');
            return false;
        }
        
        // Validar tipo de gasto (obligatorio)
        if (!line.expense_type_id) {
            showToast(`Seleccione el tipo de gasto para la línea ${lineNumber}`, 'error');
            return false;
        }
        
        // Validar importe (obligatorio y mínimo 0.01)
        const amount = parseFloat(line.amount);
        if (isNaN(amount) || amount < 0.01) {
            showToast(`El importe de la línea ${lineNumber} debe ser mayor a $0.01`, 'error');
            return false;
        }
    }
    
    return true;
}

// --- Ver gasto ---
function viewExpense(id) {
    fetch(`${API_URL}?action=getExpenseById&id=${id}`)
        .then(res => res.json())
        .then(expense => {
            if (!expense) {
                showToast('Gasto no encontrado', 'error');
                return;
            }
            
            populateViewModal(expense);
            openModal('viewExpenseModal');
        })
        .catch(() => {
            showToast('Error al cargar gasto', 'error');
        });
}

function populateViewModal(expense) {
    // Header
    document.getElementById('viewExpenseNumber').textContent = generateExpenseNumber(expense);
    
    // Información general
    document.getElementById('viewExpenseDate').textContent = formatDate(expense.expense_date);
    document.getElementById('viewTeamName').textContent = expense.team_name || 'N/A';
    document.getElementById('viewVendorName').textContent = expense.vendor_name || 'N/A';
    
    // Cuenta bancaria
    const bankAccount = expense.bank_account_name 
        ? `${expense.bank_account_name}${expense.bank_account_type ? ` (${expense.bank_account_type})` : ''}`
        : 'N/A';
    document.getElementById('viewBankAccount').textContent = bankAccount;
    
    // Líneas de gasto
    const linesContainer = document.getElementById('viewExpenseLines');
    linesContainer.innerHTML = '';
    
    if (expense.lines && expense.lines.length > 0) {
        expense.lines.forEach(line => {
            const deducibleBadge = line.deducible 
                ? '<span class="deducible-badge yes">Sí</span>'
                : '<span class="deducible-badge no">No</span>';
                
            const lineHtml = `
                <div class="line-item">
                    <div class="line-description">${escapeHtml(line.description || 'Sin descripción')}</div>
                    <div class="line-type">${escapeHtml(line.expense_type_name || 'N/A')}</div>
                    <div class="line-amount">$${parseFloat(line.amount || 0).toLocaleString('es-MX', {minimumFractionDigits: 2})}</div>
                    <div class="line-deducible">${deducibleBadge}</div>
                </div>
            `;
            linesContainer.insertAdjacentHTML('beforeend', lineHtml);
        });
    } else {
        linesContainer.innerHTML = '<div class="line-item"><div colspan="4" style="text-align: center; color: var(--text-secondary);">No hay líneas de gasto</div></div>';
    }
    
    // Total
    document.getElementById('viewTotalAmount').textContent = `$${parseFloat(expense.total_amount || 0).toLocaleString('es-MX', {minimumFractionDigits: 2})}`;
    
    // Archivos adjuntos
    const attachmentsSection = document.getElementById('viewAttachmentsSection');
    const attachmentsContainer = document.getElementById('viewAttachments');
    
    if (expense.attachments && expense.attachments.length > 0) {
        attachmentsSection.style.display = 'block';
        attachmentsContainer.innerHTML = '';
        
        expense.attachments.forEach(attachment => {
            const fileIcon = getFileIcon(attachment.mime_type);
            const fileSize = (attachment.file_size / 1024 / 1024).toFixed(2);
            
            const attachmentHtml = `
                <div class="attachment-item clickable" onclick="openAttachment('${attachment.id}', '${escapeHtml(attachment.original_filename)}')" title="Click para abrir archivo">
                    <i class="fas ${fileIcon}"></i>
                    <div class="attachment-info">
                        <div class="attachment-name">${escapeHtml(attachment.original_filename)}</div>
                        <div class="attachment-size">${fileSize} MB</div>
                    </div>
                    <i class="fas fa-external-link-alt open-icon"></i>
                </div>
            `;
            attachmentsContainer.insertAdjacentHTML('beforeend', attachmentHtml);
        });
    } else {
        attachmentsSection.style.display = 'none';
    }
    
    // Notas
    const notesSection = document.getElementById('viewNotesSection');
    const notesContainer = document.getElementById('viewNotes');
    
    if (expense.notes && expense.notes.trim()) {
        notesSection.style.display = 'block';
        notesContainer.textContent = expense.notes;
    } else {
        notesSection.style.display = 'none';
    }
    
    // Guardar ID para edición
    document.getElementById('editFromViewBtn').onclick = () => editExpenseFromView(expense.id);
}

function getFileIcon(mimeType) {
    if (mimeType.includes('pdf')) return 'fa-file-pdf';
    if (mimeType.includes('image')) return 'fa-file-image';
    return 'fa-file';
}

function editExpenseFromView(expenseId = null) {
    const id = expenseId || document.getElementById('editFromViewBtn').getAttribute('data-expense-id');
    closeModal('viewExpenseModal');
    setTimeout(() => {
        editExpense(id, false);
    }, 300);
}

function printExpense() {
    // Crear ventana de impresión
    const printWindow = window.open('', '_blank');
    const expenseContent = document.querySelector('.expense-invoice').cloneNode(true);
    
    // Remover botones de acción del contenido de impresión
    const footer = expenseContent.querySelector('.invoice-footer');
    if (footer) footer.remove();
    
    const printContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Detalle de Gasto</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
                ${document.querySelector('style').textContent}
                .invoice-header .modal-close { display: none; }
                @media print {
                    body { margin: 0; }
                    .invoice-header { background: #2563eb !important; }
                }
            </style>
        </head>
        <body>
            ${expenseContent.outerHTML}
        </body>
        </html>
    `;
    
    printWindow.document.write(printContent);
    printWindow.document.close();
    
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 500);
}

// --- Editar gasto ---
function editExpense(id, readOnly = false) {
    fetch(`${API_URL}?action=getExpenseById&id=${id}`)
        .then(res => res.json())
        .then(expense => {
            if (!expense) {
                showToast('Gasto no encontrado', 'error');
                return;
            }
            
            populateExpenseForm(expense);
            editingExpenseId = id;
            document.querySelector('#expenseModal .modal-header h2').textContent = 'Editar Gasto';
            openModal('expenseModal');
        })
        .catch(() => {
            showToast('Error al cargar gasto', 'error');
        });
}

function populateExpenseForm(expense) {
    document.getElementById('expenseDate').value = expense.expense_date;
    document.getElementById('team').value = expense.team_id;
    document.getElementById('vendor').value = expense.vendor_id;
    document.getElementById('bankAccount').value = expense.bank_account_id;
    document.getElementById('notes').value = expense.notes || '';
    
    // Limpiar archivos adjuntos y archivos marcados para eliminación
    document.getElementById('attachments').value = '';
    document.getElementById('attachments-preview').innerHTML = '';
    window.attachmentsToDelete = [];
    selectedFiles = []; // Limpiar array de archivos seleccionados
    
    // Mostrar archivos existentes si los hay
    if (expense.attachments && expense.attachments.length > 0) {
        displayExistingAttachments(expense.attachments);
    }
    
    // Poblar líneas
    populateLines(expense.lines || []);
    
    // Asegurar que todos los campos estén habilitados para edición
    const form = document.getElementById('expenseForm');
    const inputs = form.querySelectorAll('input, select, textarea, button');
    inputs.forEach(input => {
        input.disabled = false;
    });
    
    // Mostrar botón de guardar
    document.querySelector('#expenseModal .btn-primary').style.display = 'inline-flex';
}

function populateLines(lines) {
    const container = document.getElementById('expense-lines');
    container.innerHTML = '';
    
    if (lines && lines.length > 0) {
        lines.forEach(line => {
            expenseLineCounter++;
            const lineId = expenseLineCounter;
            
            // Crear opciones de tipos de gasto
            let expenseTypeOptions = '<option value="">Seleccionar tipo...</option>';
            expenseTypes.forEach(type => {
                const selected = type.id === line.expense_type_id ? 'selected' : '';
                expenseTypeOptions += `<option value="${type.id}" ${selected}>${type.name}</option>`;
            });
            
            const deducibleChecked = line.deducible ? 'checked' : '';
            
            const lineHtml = `
                <div class="expense-line" data-line-id="${lineId}">
                    <input type="text" class="form-input line-description" value="${escapeHtml(line.description || '')}" placeholder="Descripción del gasto..." required onblur="validateLineField(this, 'description')">
                    <select class="form-input line-expense-type" required onchange="validateLineField(this, 'expense_type')">
                        ${expenseTypeOptions}
                    </select>
                    <input type="number" class="form-input line-amount" value="${line.amount || 0}" min="0.01" step="0.01" placeholder="0.00" required onchange="calculateTotal(); validateLineField(this, 'amount')" onblur="validateLineField(this, 'amount')">
                    <label class="deducible-switch">
                        <input type="checkbox" class="line-deducible" ${deducibleChecked}>
                        <span class="switch-slider"></span>
                    </label>
                    <button type="button" class="btn-remove-line" onclick="removeExpenseLine(${lineId})" title="Eliminar línea">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', lineHtml);
        });
    } else {
        addExpenseLine(); // Agregar línea vacía
    }
    
    calculateTotal();
}

// --- Eliminar gasto ---
function showDeleteModal(id) {
    document.getElementById('deleteExpenseId').value = id;
    openModal('deleteModal');
}

function deleteExpenseConfirmed() {
    const id = document.getElementById('deleteExpenseId').value;
    
    fetch(`${API_URL}?action=deleteExpense&id=${id}`, {
        method: 'DELETE'
    })
    .then(res => res.json())
    .then(data => {
        // Cerrar el modal siempre, independientemente del resultado
        closeModal('deleteModal');
        
        if (data && data.success) {
            // Éxito
            showToast(data.message || 'Gasto eliminado exitosamente', 'success');
            
            // Recargar datos después de un breve delay para que se vea el toast
            setTimeout(() => {
                // Verificar si necesitamos ir a página anterior
                const currentRows = document.querySelectorAll('#expensesTableBody tr:not(.no-data)').length;
                if (currentRows === 1 && currentPage > 1) {
                    // Si solo hay un elemento y no estamos en la página 1, ir a la anterior
                    loadExpenses(currentPage - 1);
                } else {
                    // Recargar la página actual
                    loadExpenses(currentPage);
                }
            }, 200);
        } else {
            // Error desde el servidor
            showToast(data.error || 'No se pudo eliminar el gasto', 'error');
        }
    })
    .catch(error => {
        console.error('Error eliminando gasto:', error);
        closeModal('deleteModal');
        showToast('Error de conexión al eliminar el gasto', 'error');
    });
}

// --- Utilidades ---
// --- Generar número de gasto ---
function generateExpenseNumber(expense) {
    if (expense.expense_number) {
        return expense.expense_number;
    }
    
    // Si no hay expense_number, generar uno basado en el ID
    const id = expense.id || 0;
    return 'EXP' + String(id).padStart(6, '0');
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    
    // Usar split para evitar problemas de zona horaria
    const parts = dateString.split('-');
    if (parts.length === 3) {
        const year = parts[0];
        const month = parts[1];
        const day = parts[2];
        return `${day}/${month}/${year}`;
    }
    
    // Fallback al método original si el formato no es YYYY-MM-DD
    const date = new Date(dateString);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    return `${day}/${month}/${year}`;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.add('show');
    }, 100);
    
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => {
            document.body.removeChild(toast);
        }, 300);
    }, 3000);
}

// --- Funciones para archivos adjuntos ---
function openAttachment(attachmentId, filename) {
    const url = `api/expense/ExpenseController.php?action=downloadAttachment&id=${attachmentId}`;
    window.open(url, '_blank');
}

function toggleAttachmentsDropdown(expenseId, element) {
    // Cerrar otros dropdowns abiertos
    document.querySelectorAll('.attachments-dropdown.show').forEach(dropdown => {
        if (dropdown.dataset.expenseId !== expenseId) {
            dropdown.classList.remove('show');
        }
    });
    
    const dropdown = element.querySelector('.attachments-dropdown');
    if (dropdown.classList.contains('show')) {
        dropdown.classList.remove('show');
        return;
    }
    
    // Si el dropdown no tiene contenido, cargar archivos
    if (!dropdown.dataset.loaded) {
        loadAttachmentsForDropdown(expenseId, dropdown);
    }
    
    dropdown.classList.add('show');
}

function loadAttachmentsForDropdown(expenseId, dropdown) {
    dropdown.innerHTML = '<div class="dropdown-loading">Cargando...</div>';
    
    fetch(`${API_URL}?action=getExpenseAttachments&id=${expenseId}`)
        .then(res => res.json())
        .then(attachments => {
            if (!attachments || attachments.length === 0) {
                dropdown.innerHTML = '<div class="dropdown-empty">Sin archivos</div>';
                return;
            }
            
            let html = '';
            attachments.forEach(attachment => {
                const fileIcon = getFileIcon(attachment.mime_type);
                const fileSize = (attachment.file_size / 1024 / 1024).toFixed(2);
                
                html += `
                    <div class="dropdown-item" onclick="openAttachment('${attachment.id}', '${escapeHtml(attachment.original_filename)}')" title="Click para abrir">
                        <i class="fas ${fileIcon}"></i>
                        <div class="file-info">
                            <div class="file-name">${escapeHtml(attachment.original_filename)}</div>
                            <div class="file-size">${fileSize} MB</div>
                        </div>
                    </div>
                `;
            });
            
            dropdown.innerHTML = html;
            dropdown.dataset.loaded = 'true';
        })
        .catch(() => {
            dropdown.innerHTML = '<div class="dropdown-error">Error al cargar archivos</div>';
        });
}

// Cerrar dropdowns al hacer click fuera
document.addEventListener('click', function(e) {
    if (!e.target.closest('.attachment-badge-container')) {
        document.querySelectorAll('.attachments-dropdown.show').forEach(dropdown => {
            dropdown.classList.remove('show');
        });
    }
});

// --- Funciones de filtros ---
function toggleFilterDropdown() {
    const dropdown = document.getElementById('filterDropdown');
    if (!dropdown) {
        return;
    }
    
    if (dropdown.classList.contains('show')) {
        // Cerrar dropdown - revertir filtros temporales si no se aplicaron
        dropdown.classList.remove('show');
        revertTempFilters();
    } else {
        // Abrir dropdown - sincronizar filtros temporales con los actuales
        dropdown.classList.add('show');
        syncTempFilters();
    }
}

function syncTempFilters() {
    // Sincronizar filtros temporales con los actuales cuando se abre el dropdown
    tempFilters.team = currentFilters.team;
    tempFilters.vendor = currentFilters.vendor;
    tempFilters.dateFrom = currentFilters.dateFrom;
    tempFilters.dateTo = currentFilters.dateTo;
    
    // Actualizar valores en los inputs
    document.getElementById('teamFilter').value = tempFilters.team;
    document.getElementById('vendorFilter').value = tempFilters.vendor;
    
    // Actualizar date pickers con timeout para asegurar que se inicialicen correctamente
    setTimeout(() => {
        if (tempFilters.dateFrom) {
            window.dateFromPicker.setDate(tempFilters.dateFrom, false);
        } else {
            window.dateFromPicker.clear();
        }
        
        if (tempFilters.dateTo) {
            window.dateToPicker.setDate(tempFilters.dateTo, false);
        } else {
            window.dateToPicker.clear();
        }
        
        // Configurar restricciones si hay fecha desde
        if (tempFilters.dateFrom && window.dateToPicker) {
            window.dateToPicker.set('minDate', tempFilters.dateFrom);
        }
    }, 100);
}

function revertTempFilters() {
    // Revertir filtros temporales a los valores actuales
    tempFilters.team = currentFilters.team;
    tempFilters.vendor = currentFilters.vendor;
    tempFilters.dateFrom = currentFilters.dateFrom;
    tempFilters.dateTo = currentFilters.dateTo;
    
    // Actualizar valores en los inputs
    document.getElementById('teamFilter').value = currentFilters.team;
    document.getElementById('vendorFilter').value = currentFilters.vendor;
    
    // Actualizar date pickers
    if (currentFilters.dateFrom) {
        window.dateFromPicker.setDate(currentFilters.dateFrom, false);
    } else {
        window.dateFromPicker.clear();
    }
    
    if (currentFilters.dateTo) {
        window.dateToPicker.setDate(currentFilters.dateTo, false);
    } else {
        window.dateToPicker.clear();
    }
    
    // Configurar restricciones si hay fecha desde
    if (currentFilters.dateFrom && window.dateToPicker) {
        window.dateToPicker.set('minDate', currentFilters.dateFrom);
    } else if (window.dateToPicker) {
        window.dateToPicker.set('minDate', null);
    }
}

function updateActiveFiltersDisplay() {
    let count = 0;
    const filtersData = [];
    
    if (currentFilters.team) {
        count++;
        const teamName = teams.find(t => t.id === currentFilters.team)?.name || 'Equipo';
        filtersData.push({ type: 'team', label: `Equipo: ${teamName}`, value: currentFilters.team });
    }
    
    if (currentFilters.vendor) {
        count++;
        const vendorName = vendors.find(v => v.id === currentFilters.vendor)?.name || 'Proveedor';
        filtersData.push({ type: 'vendor', label: `Proveedor: ${vendorName}`, value: currentFilters.vendor });
    }
    
    // Si hay ambas fechas, crear un solo botón de rango
    if (currentFilters.dateFrom && currentFilters.dateTo) {
        count++; // Contar como un solo filtro
        const formattedDateFrom = formatDate(currentFilters.dateFrom);
        const formattedDateTo = formatDate(currentFilters.dateTo);
        filtersData.push({ 
            type: 'dateRange', 
            label: ` ${formattedDateFrom} → ${formattedDateTo}`, 
            value: `${currentFilters.dateFrom}|${currentFilters.dateTo}` 
        });
    } else {
        // Si solo hay una fecha, mostrar botones individuales
        if (currentFilters.dateFrom) {
            count++;
            const formattedDateFrom = formatDate(currentFilters.dateFrom);
            filtersData.push({ type: 'dateFrom', label: ` Desde: ${formattedDateFrom}`, value: currentFilters.dateFrom });
        }
        
        if (currentFilters.dateTo) {
            count++;
            const formattedDateTo = formatDate(currentFilters.dateTo);
            filtersData.push({ type: 'dateTo', label: ` Hasta: ${formattedDateTo}`, value: currentFilters.dateTo });
        }
    }
    
    // Actualizar contador
    const countElement = document.getElementById('activeFiltersCount');
    if (count > 0) {
        countElement.textContent = count;
        countElement.style.display = 'flex';
    } else {
        countElement.style.display = 'none';
    }
    
    // Mostrar/ocultar botones de filtros activos
    const container = document.getElementById('activeFiltersContainer');
    if (filtersData.length > 0) {
        container.style.display = 'flex';
        container.innerHTML = filtersData.map(filter => `
            <div class="active-filter-btn">
                ${filter.label}
                <button class="remove-filter" onclick="removeFilter('${filter.type}')" title="Eliminar filtro">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `).join('');
    } else {
        container.style.display = 'none';
        container.innerHTML = '';
    }
}

function removeFilter(filterType) {
    let messageText = '';
    
    switch(filterType) {
        case 'team':
            currentFilters.team = '';
            tempFilters.team = '';
            document.getElementById('teamFilter').value = '';
            messageText = 'Filtro de equipo eliminado';
            break;
        case 'vendor':
            currentFilters.vendor = '';
            tempFilters.vendor = '';
            document.getElementById('vendorFilter').value = '';
            messageText = 'Filtro de proveedor eliminado';
            break;
        case 'dateFrom':
            currentFilters.dateFrom = '';
            tempFilters.dateFrom = '';
            window.dateFromPicker.clear();
            // Quitar restricción de fecha mínima del picker "hasta"
            if (window.dateToPicker) {
                window.dateToPicker.set('minDate', null);
            }
            messageText = 'Fecha "desde" eliminada';
            break;
        case 'dateTo':
            currentFilters.dateTo = '';
            tempFilters.dateTo = '';
            window.dateToPicker.clear();
            messageText = 'Fecha "hasta" eliminada';
            break;
        case 'dateRange':
            // Eliminar ambas fechas cuando se elimina el rango
            currentFilters.dateFrom = '';
            currentFilters.dateTo = '';
            tempFilters.dateFrom = '';
            tempFilters.dateTo = '';
            window.dateFromPicker.clear();
            window.dateToPicker.clear();
            // Quitar restricción de fecha mínima
            if (window.dateToPicker) {
                window.dateToPicker.set('minDate', null);
            }
            messageText = 'Rango de fechas eliminado';
            break;
    }
    
    updateActiveFiltersDisplay();
    loadExpenses(1);
    
    if (messageText) {
        showToast(messageText, 'info');
    }
}

function applyFilters() {
    // Validar fechas antes de aplicar
    if (tempFilters.dateFrom && tempFilters.dateTo) {
        const dateFrom = new Date(tempFilters.dateFrom);
        const dateTo = new Date(tempFilters.dateTo);
        
        if (dateFrom > dateTo) {
            showToast('La fecha "desde" no puede ser mayor que la fecha "hasta"', 'error');
            return;
        }
    }
    
    // Aplicar filtros temporales a los filtros actuales
    currentFilters.team = tempFilters.team;
    currentFilters.vendor = tempFilters.vendor;
    currentFilters.dateFrom = tempFilters.dateFrom;
    currentFilters.dateTo = tempFilters.dateTo;
    

    
    // Actualizar display de filtros activos
    updateActiveFiltersDisplay();
    
    // Cargar gastos con nuevos filtros
    loadExpenses(1);
    
    // Cerrar dropdown
    document.getElementById('filterDropdown').classList.remove('show');
}


function clearAllFilters() {
    // Limpiar filtros actuales
    currentFilters = {
        search: '',
        team: '',
        vendor: '',
        dateFrom: '',
        dateTo: ''
    };
    
    // Limpiar filtros temporales
    tempFilters = {
        team: '',
        vendor: '',
        dateFrom: '',
        dateTo: ''
    };
    
    // Limpiar inputs
    document.getElementById('searchInput').value = '';
    document.getElementById('teamFilter').value = '';
    document.getElementById('vendorFilter').value = '';
    
    // Limpiar y establecer fecha actual en date pickers
    const today = new Date().toISOString().split('T')[0]; // Formato YYYY-MM-DD
    
    window.dateFromPicker.clear();
    window.dateToPicker.clear();
    
    // Quitar todas las restricciones de fecha
    if (window.dateToPicker) {
        window.dateToPicker.set('minDate', null);
    }
    
    // Establecer fecha actual como sugerencia
    setTimeout(() => {
        window.dateFromPicker.setDate(today, false);
        window.dateToPicker.setDate(today, false);
    }, 100);
    
    updateActiveFiltersDisplay();
    loadExpenses(1);
    document.getElementById('filterDropdown').classList.remove('show');
}

// --- Renderizar selector de tamaño de página ---
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
    [10, 20, 50, 100].forEach(size => {
        const opt = document.createElement('option');
        opt.value = size;
        opt.textContent = `${size} por página`;
        selector.appendChild(opt);
    });
    selector.value = pageSize;
    selector.onchange = function() {
        pageSize = parseInt(this.value);
        loadExpenses(1);
    };
    container.appendChild(label);
    container.appendChild(selector);
}