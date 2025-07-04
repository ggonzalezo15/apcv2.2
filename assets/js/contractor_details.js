// --- Configuración ---
const API_URL = 'api/contractor/ContractorController.php';
const API_PAYMENT_URL = 'api/contractor/ContractorPaymentController.php';

// Variables globales
// contractorId se define en el script inline del HTML
let allPayments = []; // Almacenar todos los pagos

// Variables de paginación
let currentPage = 1;
let pageSize = 10;
let totalPaymentsCount = 0;

// Variables de sorting
let sortField = 'payment_date';
let sortDir = 'desc';

// Variables de filtro
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
    // Esperar un poco para que el script inline se ejecute y defina window.contractorId
    setTimeout(() => {
        // Verificar que contractorId esté disponible globalmente
        if (!window.contractorId) {
            console.error('No se pudo obtener el ID del contratista');
            return;
        }
        
        // Inicializar componentes
        initializeFlatpickr();
        loadContractorDetails();
        loadContractorPayments();
        loadBankAccountsForPayment();
        setupEventListeners();
        
        // Inicializar indicadores de sorting
        updateSortHeaders();
    }, 50);
});

// --- Inicializar Flatpickr ---
function initializeFlatpickr() {
    // Verificar que flatpickr esté disponible
    if (typeof flatpickr === 'undefined') {
        console.error('Flatpickr no está disponible en contractor_details. Esperando...');
        setTimeout(() => {
            if (typeof flatpickr !== 'undefined') {
                initializeFlatpickr();
            }
        }, 500);
        return;
    }

    // Configuración base
    const baseConfig = {
        dateFormat: "Y-m-d",
        allowInput: true,
        clickOpens: true
    };
    
    // Configuración para modal
    const modalConfig = {
        ...baseConfig,
        defaultDate: new Date(),
        allowInput: false
    };
    
    // Configuración para filtros
    const filterConfig = {
        ...baseConfig,
        static: true
    };
    
    // Agregar localización española si está disponible
    if (flatpickr.l10ns && flatpickr.l10ns.es) {
        flatpickr.localize(flatpickr.l10ns.es);
        baseConfig.locale = 'es';
        modalConfig.locale = 'es';
        filterConfig.locale = 'es';
    }
    
    // Date picker para modal de pago
    flatpickr("#paymentDate", modalConfig);
    
    // Date pickers para filtros
    window.dateFromPicker = flatpickr("#dateFromFilter", {
        ...filterConfig,
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
        }
    });
    
    window.dateToPicker = flatpickr("#dateToFilter", {
        ...filterConfig,
        onChange: function(selectedDates, dateStr) {
            tempFilters.dateTo = dateStr;
        }
    });
}

// --- Event Listeners ---
function setupEventListeners() {
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

// --- Funciones de carga de datos ---
function loadContractorDetails() {
    fetch(`${API_URL}?action=getContractorById&id=${window.contractorId}`)
        .then(res => res.json())
        .then(contractor => {
            if (!contractor) {
                showToast('Contratista no encontrado', 'error');
                window.location.href = 'contractors.php';
                return;
            }
            
            // Actualizar información del contratista
            const nameTitle = document.getElementById('contractorNameTitle');
            if (nameTitle) nameTitle.textContent = contractor.name;
            
            // Actualizar título de la página
            document.title = `Detalles - ${contractor.name}`;
            
            // Actualizar detalles del contratista
            const contractorDetails = document.getElementById('contractorDetails');
            if (contractorDetails) {
                contractorDetails.innerHTML = `
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px;">
                        <div>
                            <strong>Nombre:</strong><br>
                            <span style="color: var(--text-secondary);">${contractor.name}</span>
                        </div>
                        <div>
                            <strong>Email:</strong><br>
                            <span style="color: var(--text-secondary);">${contractor.email || 'No especificado'}</span>
                        </div>
                        <div>
                            <strong>Teléfono:</strong><br>
                            <span style="color: var(--text-secondary);">${contractor.phone || 'No especificado'}</span>
                        </div>
                        <div>
                            <strong>Dirección:</strong><br>
                            <span style="color: var(--text-secondary);">${contractor.address || 'No especificada'}</span>
                        </div>
                        <div>
                            <strong>Total Pagado:</strong><br>
                            <span id="totalPaidInHeader" style="color: var(--primary-color); font-weight: 600; font-size: 16px;">$0.00</span>
                        </div>
                        <div>
                            <strong>Promedio por Pago:</strong><br>
                            <span id="averagePaymentInHeader" style="color: var(--primary-color); font-weight: 600; font-size: 16px;">$0.00</span>
                        </div>
                    </div>
                `;
            }
        })
        .catch(err => {
            console.error('Error cargando detalles del contratista:', err);
            showToast('Error al cargar los detalles del contratista', 'error');
        });
}

function loadContractorPayments() {
    // Construir query params para paginación y sorting
    const params = new URLSearchParams({
        action: 'getPaymentsByContractor',
        contractor_id: window.contractorId,
        page: currentPage,
        pageSize: pageSize,
        sortField: sortField,
        sortDir: sortDir
    });
    
    // Agregar filtros si están activos
    if (currentFilters.dateFrom) {
        params.append('dateFrom', currentFilters.dateFrom);
    }
    if (currentFilters.dateTo) {
        params.append('dateTo', currentFilters.dateTo);
    }
    
    fetch(`${API_PAYMENT_URL}?${params.toString()}`)
        .then(res => res.json())
        .then(result => {
            if (result && result.data) {
                allPayments = result.data || [];
                totalPaymentsCount = result.total || allPayments.length;
                renderPaymentsTable();
                renderPagination();
                updateStatistics();
            } else {
                // Fallback al formato anterior
                allPayments = result || [];
                totalPaymentsCount = allPayments.length;
                renderPaymentsTable();
                renderPagination();
                updateStatistics();
            }
        })
        .catch(err => {
            console.error('Error cargando pagos:', err);
            showToast('Error al cargar el historial de pagos', 'error');
        });
}

function loadBankAccountsForPayment() {
    fetch(`${API_PAYMENT_URL}?action=getBankAccountsForPayments`)
        .then(res => res.json())
        .then(accounts => {
            const select = document.getElementById('bankAccountId');
            if (select) {
                select.innerHTML = '<option value="">Seleccionar cuenta...</option>';
                // Solo mostrar cuentas activas
                const activeAccounts = accounts.filter(account => account.active === 1 || account.active === '1' || account.active === true);
                activeAccounts.forEach(account => {
                    const option = document.createElement('option');
                    option.value = account.id;
                    option.textContent = `${account.name} (${account.bank_name}) - $${parseFloat(account.balance).toFixed(2)}`;
                    select.appendChild(option);
                });
            }
        })
        .catch(err => {
            console.error('Error cargando cuentas bancarias:', err);
        });
}

// --- Funciones de filtrado ---
function toggleFilterDropdown() {
    const dropdown = document.getElementById('filterDropdown');
    if (!dropdown) return;
    
    if (dropdown.classList.contains('show')) {
        dropdown.classList.remove('show');
        revertTempFilters();
    } else {
        dropdown.classList.add('show');
        syncTempFilters();
    }
}

function syncTempFilters() {
    tempFilters.dateFrom = currentFilters.dateFrom;
    tempFilters.dateTo = currentFilters.dateTo;
    
    // Actualizar date pickers
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
    tempFilters.dateFrom = currentFilters.dateFrom;
    tempFilters.dateTo = currentFilters.dateTo;
    
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
    
    // Configurar restricciones
    if (currentFilters.dateFrom && window.dateToPicker) {
        window.dateToPicker.set('minDate', currentFilters.dateFrom);
    } else if (window.dateToPicker) {
        window.dateToPicker.set('minDate', null);
    }
}

function applyDateFilters() {
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
    currentFilters.dateFrom = tempFilters.dateFrom;
    currentFilters.dateTo = tempFilters.dateTo;
    
    // Resetear paginación al aplicar filtros
    currentPage = 1;
    
    // Cargar datos con los nuevos filtros
    loadContractorPayments();
    
    // Actualizar display de filtros activos
    updateActiveFiltersDisplay();
    
    // Cerrar dropdown
    document.getElementById('filterDropdown').classList.remove('show');
    
    showToast('Filtros aplicados correctamente', 'success');
}

function clearDateFilters() {
    currentFilters.dateFrom = '';
    currentFilters.dateTo = '';
    tempFilters.dateFrom = '';
    tempFilters.dateTo = '';
    
    // Limpiar date pickers
    window.dateFromPicker.clear();
    window.dateToPicker.clear();
    
    // Quitar restricciones
    if (window.dateToPicker) {
        window.dateToPicker.set('minDate', null);
    }
    
    // Resetear paginación al limpiar filtros
    currentPage = 1;
    
    // Cargar datos sin filtros
    loadContractorPayments();
    
    // Actualizar display
    updateActiveFiltersDisplay();
    
    // Cerrar dropdown
    document.getElementById('filterDropdown').classList.remove('show');
    
    showToast('Filtros eliminados', 'info');
}



function updateActiveFiltersDisplay() {
    let count = 0;
    const filtersData = [];
    
    // Si hay ambas fechas, crear un solo botón de rango
    if (currentFilters.dateFrom && currentFilters.dateTo) {
        count++;
        const formattedDateFrom = formatDate(currentFilters.dateFrom);
        const formattedDateTo = formatDate(currentFilters.dateTo);
        filtersData.push({ 
            type: 'dateRange', 
            label: `📅 ${formattedDateFrom} → ${formattedDateTo}`, 
            value: `${currentFilters.dateFrom}|${currentFilters.dateTo}` 
        });
    } else {
        // Si solo hay una fecha, mostrar botones individuales
        if (currentFilters.dateFrom) {
            count++;
            const formattedDateFrom = formatDate(currentFilters.dateFrom);
            filtersData.push({ type: 'dateFrom', label: `📅 Desde: ${formattedDateFrom}`, value: currentFilters.dateFrom });
        }
        
        if (currentFilters.dateTo) {
            count++;
            const formattedDateTo = formatDate(currentFilters.dateTo);
            filtersData.push({ type: 'dateTo', label: `📅 Hasta: ${formattedDateTo}`, value: currentFilters.dateTo });
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
    
    // Mostrar/ocultar contenedor de filtros activos
    const container = document.getElementById('activeFiltersContainer');
    if (filtersData.length > 0) {
        container.style.display = 'flex';
        container.innerHTML = filtersData.map(filter => 
            `<div class="filter-tag">
                ${filter.label}
                <button type="button" class="remove-filter" onclick="removeFilter('${filter.type}')" title="Eliminar filtro">
                    ×
                </button>
            </div>`
        ).join('');
    } else {
        container.style.display = 'none';
        container.innerHTML = '';
    }
}

function removeFilter(filterType) {
    let messageText = '';
    
    switch(filterType) {
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
    
    // Resetear paginación y cargar datos
    currentPage = 1;
    loadContractorPayments();
    updateActiveFiltersDisplay();
    
    if (messageText) {
        showToast(messageText, 'info');
    }
}

// --- Funciones de renderizado ---
function renderPaymentsTable(payments = null) {
    const paymentsToRender = payments || allPayments;
    const tbody = document.getElementById('paymentsTableBody');
    tbody.innerHTML = '';
    
    if (!paymentsToRender.length) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 40px; color: var(--text-secondary);">No hay pagos registrados con los filtros aplicados</td></tr>';
        return;
    }
    
    // No hacer sorting local, el sorting viene del servidor
    paymentsToRender.forEach(payment => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${formatDate(payment.payment_date)}</td>
            <td style="font-weight: 600; color: var(--primary-color);">$${parseFloat(payment.amount).toFixed(2)}</td>
            <td>
                <div style="font-weight: 500;">${payment.bank_account_name || 'N/A'}</div>
                <small style="color: var(--text-secondary);">${payment.bank_name || ''} ${payment.account_number ? '- ' + payment.account_number : ''}</small>
            </td>
            <td>${payment.reference_number || '-'}</td>
            <td>${payment.notes || '-'}</td>
            <td style="text-align: center;">
                <div style="display: flex; gap: 4px; justify-content: center;">
                    <button type="button" class="btn-icon" onclick="editPayment('${payment.id}')" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button type="button" class="btn-icon btn-danger" onclick="deletePayment('${payment.id}')" title="Eliminar">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function updateStatistics() {
    // Cargar estadísticas del servidor con filtros aplicados
    const params = new URLSearchParams({
        action: 'getPaymentStatistics',
        contractor_id: window.contractorId
    });
    
    // Agregar filtros si están activos
    if (currentFilters.dateFrom) {
        params.append('dateFrom', currentFilters.dateFrom);
    }
    if (currentFilters.dateTo) {
        params.append('dateTo', currentFilters.dateTo);
    }
    
    fetch(`${API_PAYMENT_URL}?${params.toString()}`)
        .then(res => res.json())
        .then(stats => {
            // Actualizar elementos en el header del contratista
            const totalPaidInHeaderEl = document.getElementById('totalPaidInHeader');
            const averagePaymentInHeaderEl = document.getElementById('averagePaymentInHeader');
            
            if (totalPaidInHeaderEl) totalPaidInHeaderEl.textContent = '$' + parseFloat(stats.totalPaid || 0).toFixed(2);
            if (averagePaymentInHeaderEl) averagePaymentInHeaderEl.textContent = '$' + parseFloat(stats.averagePayment || 0).toFixed(2);
        })
        .catch(err => {
            console.error('Error cargando estadísticas:', err);
            // Fallback usando datos locales
            const stats = calculateStatistics(allPayments);
            const totalPaidInHeaderEl = document.getElementById('totalPaidInHeader');
            const averagePaymentInHeaderEl = document.getElementById('averagePaymentInHeader');
            
            if (totalPaidInHeaderEl) totalPaidInHeaderEl.textContent = '$' + stats.totalPaid.toFixed(2);
            if (averagePaymentInHeaderEl) averagePaymentInHeaderEl.textContent = '$' + stats.averagePayment.toFixed(2);
        });
}

function calculateStatistics(payments) {
    if (!payments.length) {
        return {
            totalPaid: 0,
            averagePayment: 0,
            lastPaymentDate: 'N/A',
            paymentsCount: 0
        };
    }
    
    const totalPaid = payments.reduce((sum, payment) => sum + parseFloat(payment.amount), 0);
    const averagePayment = totalPaid / payments.length;
    
    // Encontrar la fecha más reciente
    const sortedByDate = [...payments].sort((a, b) => new Date(b.payment_date) - new Date(a.payment_date));
    const lastPaymentDate = sortedByDate.length > 0 ? formatDate(sortedByDate[0].payment_date) : 'N/A';
    
    return {
        totalPaid,
        averagePayment,
        lastPaymentDate,
        paymentsCount: payments.length
    };
}

// --- Funciones de modal ---
function openNewPaymentModal() {
    // Resetear formulario
    const form = document.getElementById('paymentForm');
    if (form) {
        form.reset();
        
        // Limpiar valores específicos
        const editingId = document.getElementById('paymentId');
        if (editingId) editingId.value = '';
        
        // Reset modal title
        const modalTitle = document.getElementById('paymentModalTitle');
        if (modalTitle) modalTitle.textContent = 'Nuevo Pago a Contratista';
        
        const submitText = document.getElementById('paymentSubmitText');
        if (submitText) submitText.textContent = 'Registrar Pago';
    }
    
    // Pre-seleccionar el contratista actual (si existe el campo)
    const contractorSelect = document.getElementById('paymentContractorId');
    if (contractorSelect) {
        contractorSelect.value = window.contractorId;
    }
    
    // Establecer fecha actual usando Flatpickr
    const dateInput = document.getElementById('paymentDate');
    if (dateInput) {
        flatpickr(dateInput).setDate(new Date());
    }
    
    // Limpiar variables de edición
    window.editingPaymentId = null;
    
    // Abrir modal
    openModal('paymentModal');
}

function openModal(modalId) {
    document.getElementById(modalId).style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
    document.body.style.overflow = 'auto';
    
    // Limpiar variables de edición al cerrar modal de pago
    if (modalId === 'paymentModal') {
        window.editingPaymentId = null;
        
        // Resetear títulos del modal
        const modalTitle = document.getElementById('paymentModalTitle');
        if (modalTitle) modalTitle.textContent = 'Nuevo Pago a Contratista';
        
        const submitText = document.getElementById('paymentSubmitText');
        if (submitText) submitText.textContent = 'Registrar Pago';
    }
}

// Event listener para formulario de pago se maneja en el script inline

// --- Funciones auxiliares ---
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

function formatDateTime(dateString) {
    if (!dateString) return '-';
    
    // Para datetime, podemos usar directamente new Date() ya que incluye información de hora
    const date = new Date(dateString);
    return date.toLocaleDateString('es-ES') + ' ' + date.toLocaleTimeString('es-ES', {hour: '2-digit', minute: '2-digit'});
}

// --- Funciones de gestión de pagos ---
function editPayment(id) {
    fetch(`${API_PAYMENT_URL}?action=getContractorPaymentById&id=${id}`)
        .then(res => res.json())
        .then(payment => {
            // Actualizar títulos del modal
            document.getElementById('paymentModalTitle').textContent = 'Editar Pago';
            document.getElementById('paymentSubmitText').textContent = 'Actualizar Pago';
            
            // Llenar formulario con datos del pago
            document.getElementById('bankAccountId').value = payment.bank_account_id;
            document.getElementById('amount').value = payment.amount;
            document.getElementById('referenceNumber').value = payment.reference_number || '';
            document.getElementById('notes').value = payment.notes || '';
            
            // Establecer fecha usando Flatpickr
            const dateInput = document.getElementById('paymentDate');
            if (dateInput && payment.payment_date) {
                flatpickr(dateInput).setDate(payment.payment_date);
            }
            
            // Establecer ID para edición
            window.editingPaymentId = payment.id;
            
            // Abrir modal
            openModal('paymentModal');
        })
        .catch(err => {
            console.error('Error cargando pago:', err);
            showToast('Error al cargar los datos del pago', 'error');
        });
}

function deletePayment(id) {
    window.paymentIdToDelete = id;
    openModal('confirmDeletePaymentModal');
}

// --- Funciones de loading ---
function setTableLoading(loading) {
    const tbody = document.getElementById('paymentsTableBody');
    if (loading) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:40px 0;">
            <div class="loading-spinner"></div>
            <span style="display:block; margin-top:8px; color:var(--text-secondary);">Cargando pagos...</span>
        </td></tr>`;
    }
}

function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    const toastIcon = document.getElementById('toastIcon');
    const toastMessage = document.getElementById('toastMessage');
    
    if (!toast || !toastIcon || !toastMessage) return;
    
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

// Las funciones editPayment y deletePayment están definidas arriba en "Funciones de gestión de pagos"

// --- Función para volver atrás ---
function goBack() {
    window.location.href = 'contractors.php';
}

// --- Funciones de paginación ---
function renderPagination() {
    const totalPages = Math.ceil(totalPaymentsCount / pageSize);
    
    // Actualizar selector de tamaño de página
    renderPageSizeSelector();
    
    // Renderizar paginación
    const paginationContainer = document.getElementById('paymentsPagination');
    
    if (!paginationContainer || totalPages <= 1) {
        if (paginationContainer) paginationContainer.innerHTML = '';
        return;
    }
    
    let paginationHTML = `
        <button type="button" class="btn-pagination" ${currentPage === 1 ? 'disabled' : ''} onclick="changePage(${currentPage - 1})">
            <i class="fas fa-chevron-left"></i>
        </button>
    `;
    
    // Generar botones de páginas
    const startPage = Math.max(1, currentPage - 2);
    const endPage = Math.min(totalPages, currentPage + 2);
    
    if (startPage > 1) {
        paginationHTML += `<button type="button" class="btn-pagination" onclick="changePage(1)">1</button>`;
        if (startPage > 2) {
            paginationHTML += `<span style="padding: 0 8px; color: var(--text-secondary);">...</span>`;
        }
    }
    
    for (let i = startPage; i <= endPage; i++) {
        paginationHTML += `<button type="button" class="btn-pagination ${i === currentPage ? 'active' : ''}" onclick="changePage(${i})">${i}</button>`;
    }
    
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            paginationHTML += `<span style="padding: 0 8px; color: var(--text-secondary);">...</span>`;
        }
        paginationHTML += `<button type="button" class="btn-pagination" onclick="changePage(${totalPages})">${totalPages}</button>`;
    }
    
    paginationHTML += `
        <button type="button" class="btn-pagination" ${currentPage === totalPages ? 'disabled' : ''} onclick="changePage(${currentPage + 1})">
            <i class="fas fa-chevron-right"></i>
        </button>
    `;
    
    paginationContainer.innerHTML = paginationHTML;
}

function renderPageSizeSelector() {
    const container = document.getElementById('pageSizeSelectorContainer');
    if (!container) return;
    
    const showingFrom = ((currentPage - 1) * pageSize) + 1;
    const showingTo = Math.min(currentPage * pageSize, totalPaymentsCount);
    
    container.innerHTML = `
        <label>Mostrando ${showingFrom} - ${showingTo} de ${totalPaymentsCount} registros</label>
        <select onchange="changePageSize(this.value)" class="form-input">
            <option value="10" ${pageSize === 10 ? 'selected' : ''}>10 por página</option>
            <option value="25" ${pageSize === 25 ? 'selected' : ''}>25 por página</option>
            <option value="50" ${pageSize === 50 ? 'selected' : ''}>50 por página</option>
            <option value="100" ${pageSize === 100 ? 'selected' : ''}>100 por página</option>
        </select>
    `;
}

function changePage(page) {
    if (page < 1 || page > Math.ceil(totalPaymentsCount / pageSize)) return;
    currentPage = page;
    loadContractorPayments();
}

function changePageSize(newSize) {
    pageSize = parseInt(newSize);
    currentPage = 1; // Reset to first page
    loadContractorPayments();
}

// --- Funciones de sorting ---
function sortTable(field) {
    if (sortField === field) {
        sortDir = sortDir === 'asc' ? 'desc' : 'asc';
    } else {
        sortField = field;
        sortDir = 'desc'; // Default to descending for dates and amounts
    }
    
    currentPage = 1; // Reset to first page when sorting
    updateSortHeaders();
    loadContractorPayments();
}

function updateSortHeaders() {
    // Remover clases de ordenamiento existentes
    document.querySelectorAll('.sortable-table th.sortable').forEach(th => {
        th.classList.remove('sort-asc', 'sort-desc');
    });
    
    // Agregar clase de ordenamiento al campo actual
    const currentHeader = document.querySelector(`.sortable-table th.sortable[data-sort="${sortField}"]`);
    if (currentHeader) {
        currentHeader.classList.add(sortDir === 'asc' ? 'sort-asc' : 'sort-desc');
    }
}