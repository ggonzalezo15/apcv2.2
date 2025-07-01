// SECCIÓN TIPOS DE PAGO - Con funcionalidad de estado activo/inactivo
function loadPaymentTypesSection() {
    // Variables específicas para tipos de pago
    let paymentTypesData = [];
    let bankAccountsData = [];
    let paymentTypesCurrentPage = 1;
    let paymentTypesItemsPerPage = 10;
    let paymentTypesFilteredData = [];
    let editingPaymentTypeId = null;
    let paymentTypesStatusFilter = '';
    
    const content = `
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; gap: 16px;">
                <div style="display: flex; align-items: center; gap: 16px; flex: 1;">
                    <input
                        type="text"
                        id="paymentTypesSearchInput"
                        class="form-input"
                        placeholder="Buscar tipo de pago..."
                        style="max-width: 300px;"
                        autocomplete="off"
                    >
                    <div class="status-filter-container" style="position: relative;">
                        <button type="button" class="btn" id="paymentTypesStatusFilterBtn" onclick="togglePaymentTypesStatusFilterDropdown()" style="background: var(--bg-secondary); border: 1px solid var(--border-color); display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-filter"></i>
                            <span id="paymentTypesStatusFilterText">Todos los estados</span>
                            <i class="fas fa-chevron-down" style="font-size: 12px;"></i>
                        </button>
                        <div class="status-filter-dropdown" id="paymentTypesStatusFilterDropdown" style="display: none; position: absolute; top: 100%; left: 0; background: white; border: 1px solid var(--border-color); border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); z-index: 1000; min-width: 180px; margin-top: 4px;">
                            <div class="filter-option" onclick="applyPaymentTypesStatusFilter('')" style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid var(--border-color);">
                                <i class="fas fa-list" style="width: 16px; margin-right: 8px;"></i>
                                Todos los estados
                            </div>
                            <div class="filter-option" onclick="applyPaymentTypesStatusFilter('active')" style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid var(--border-color);">
                                <i class="fas fa-check-circle" style="width: 16px; margin-right: 8px; color: var(--success-color);"></i>
                                Solo activos
                            </div>
                            <div class="filter-option" onclick="applyPaymentTypesStatusFilter('inactive')" style="padding: 8px 12px; cursor: pointer;">
                                <i class="fas fa-times-circle" style="width: 16px; margin-right: 8px; color: var(--danger-color);"></i>
                                Solo inactivos
                            </div>
                        </div>
                    </div>
                </div>
                <div>
                    <button type="button" class="btn btn-primary" onclick="openPaymentTypeModal()">
                        <i class="fas fa-plus"></i>
                        Nuevo Tipo de Pago
                    </button>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-credit-card"></i>
                    Lista de Tipos de Pago
                </h3>
                <p class="card-subtitle">Total: <span id="totalPaymentTypes">0</span> tipos registrados</p>
            </div>
            <div style="overflow-x: auto;">
                <table class="data-table sortable-table" id="paymentTypesTable" style="min-width: 800px;">
                    <thead>
                        <tr>
                            <th class="sortable" data-sort="name">
                                Nombre
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th class="sortable" data-sort="description">
                                Descripción
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th>Cuenta Bancaria Asociada</th>
                            <th class="sortable" data-sort="status" style="width: 100px; text-align: center;">
                                Estado
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th style="width: 120px; text-align: center;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="paymentTypesTableBody">
                        <!-- Las filas se llenarán dinámicamente -->
                    </tbody>
                </table>
                <div id="paymentTypesTableFooter" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0 0 0;">
                    <div id="paymentTypesPageSizeContainer"></div>
                    <div id="paymentTypesPagination"></div>
                </div>
            </div>
        </div>

        <!-- Modal de confirmación para cambio de estado -->
        <div class="modal" id="confirmPaymentTypeStatusModal" style="display: none;">
            <div class="modal-overlay" onclick="closeModal('confirmPaymentTypeStatusModal')"></div>
            <div class="modal-content" style="max-width: 400px;">
                <div class="modal-header">
                    <h2>Confirmar cambio de estado</h2>
                    <button type="button" class="modal-close" onclick="closeModal('confirmPaymentTypeStatusModal')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <p id="confirmPaymentTypeStatusMessage">¿Está seguro de que desea cambiar el estado de este tipo de pago?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeModal('confirmPaymentTypeStatusModal')">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="confirmPaymentTypeStatusBtn">Confirmar</button>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('settingsContent').innerHTML = content;
    
    // Funciones internas para tipos de pago
    async function loadPaymentTypes() {
        try {
            const response = await fetch('api/payment_type/PaymentTypeController.php');
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            
            if (result.error) {
                showToast('Error: ' + result.error, 'error');
                paymentTypesData = [];
                bankAccountsData = [];
            } else {
                paymentTypesData = result.data || [];
                bankAccountsData = result.bank_accounts || [];
            }
            
            filterPaymentTypes();
            
        } catch (error) {
            console.error('Error loading payment types:', error);
            showToast('Error al cargar los tipos de pago: ' + error.message, 'error');
            paymentTypesData = [];
            bankAccountsData = [];
            filterPaymentTypes();
        }
    }

    function filterPaymentTypes() {
        const searchTerm = document.getElementById('paymentTypesSearchInput')?.value.toLowerCase() || '';
        
        paymentTypesFilteredData = paymentTypesData.filter(type => {
            const matchesSearch = type.name.toLowerCase().includes(searchTerm) ||
                (type.description && type.description.toLowerCase().includes(searchTerm));
            
            const matchesStatus = paymentTypesStatusFilter === '' || type.status === paymentTypesStatusFilter;
            
            return matchesSearch && matchesStatus;
        });
        
        paymentTypesCurrentPage = 1;
        renderPaymentTypesTable();
        updatePaymentTypesPagination();
    }

    function renderPaymentTypesTable() {
        const tbody = document.getElementById('paymentTypesTableBody');
        const totalElement = document.getElementById('totalPaymentTypes');
        
        if (!tbody) return;
        
        // Actualizar contador total
        if (totalElement) {
            totalElement.textContent = paymentTypesFilteredData.length;
        }
        
        // Calcular índices para paginación
        const startIndex = (paymentTypesCurrentPage - 1) * paymentTypesItemsPerPage;
        const endIndex = startIndex + paymentTypesItemsPerPage;
        const pageData = paymentTypesFilteredData.slice(startIndex, endIndex);
        
        // Limpiar tabla
        tbody.innerHTML = '';
        
        if (pageData.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" style="text-align: center; padding: 2rem; color: #6B7280;">
                        <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>
                        No se encontraron tipos de pago
                    </td>
                </tr>
            `;
            return;
        }
        
        pageData.forEach(type => {
            const row = document.createElement('tr');
            
            // Buscar la cuenta bancaria asociada
            const bankAccount = bankAccountsData.find(acc => acc.id === type.bank_account_id);
            const bankAccountLabel = bankAccount 
                ? `${bankAccount.name} (${bankAccount.bank_name} - ${bankAccount.account_number})`
                : 'No asignada';
            
            // Crear badge de estado
            const statusBadge = type.status === 'active' 
                ? '<span class="status-badge status-active">Activo</span>'
                : '<span class="status-badge status-inactive">Inactivo</span>';
            
            row.innerHTML = `
                <td>
                    <div style="font-weight: 500;">${type.name || ''}</div>
                </td>
                <td>
                    <div style="color: #6B7280; max-width: 300px; overflow: hidden; text-overflow: ellipsis;">
                        ${type.description || '-'}
                    </div>
                </td>
                <td>
                    <div style="padding: 4px 8px; background-color: #F3F4F6; border-radius: 4px; font-size: 0.875rem; font-weight: 500; display: inline-block;">
                        ${bankAccountLabel}
                    </div>
                </td>
                <td style="text-align: center;">
                    ${statusBadge}
                </td>
                <td style="text-align: center;">
                    <div style="display: flex; gap: 8px; justify-content: center;">
                        <button type="button" class="btn-action" onclick="togglePaymentTypeStatus('${type.id}', '${type.name || ''}', '${type.status}')" title="${type.status === 'active' ? 'Desactivar' : 'Activar'}">
                            <i class="fas fa-${type.status === 'active' ? 'toggle-on' : 'toggle-off'}"></i>
                        </button>
                        <button type="button" class="btn-action" onclick="editPaymentTypeFromSettings('${type.id}')" title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" class="btn-action btn-danger" onclick="deletePaymentTypeFromSettings('${type.id}', '${type.name || ''}')" title="Eliminar">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            `;
            
            tbody.appendChild(row);
        });
    }

    function updatePaymentTypesPagination() {
        const totalPages = Math.ceil(paymentTypesFilteredData.length / paymentTypesItemsPerPage);
        const paginationContainer = document.getElementById('paymentTypesPagination');
        const pageSizeContainer = document.getElementById('paymentTypesPageSizeContainer');
        
        if (!paginationContainer) return;
        
        // Selector de elementos por página
        if (pageSizeContainer) {
            pageSizeContainer.innerHTML = `
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 0.875rem; color: #6B7280;">Mostrar:</span>
                    <select id="paymentTypesPageSizeSelector" style="padding: 4px 8px; border: 1px solid #D1D5DB; border-radius: 4px; font-size: 0.875rem;">
                        <option value="10" ${paymentTypesItemsPerPage === 10 ? 'selected' : ''}>10</option>
                        <option value="25" ${paymentTypesItemsPerPage === 25 ? 'selected' : ''}>25</option>
                        <option value="50" ${paymentTypesItemsPerPage === 50 ? 'selected' : ''}>50</option>
                    </select>
                    <span style="font-size: 0.875rem; color: #6B7280;">por página</span>
                </div>
            `;
            
            const pageSizeSelector = document.getElementById('paymentTypesPageSizeSelector');
            if (pageSizeSelector) {
                pageSizeSelector.addEventListener('change', function() {
                    paymentTypesItemsPerPage = parseInt(this.value);
                    paymentTypesCurrentPage = 1;
                    renderPaymentTypesTable();
                    updatePaymentTypesPagination();
                });
            }
        }
        
        // Paginación
        if (totalPages <= 1) {
            paginationContainer.innerHTML = '';
            return;
        }
        
        let paginationHTML = '<div style="display: flex; gap: 4px; align-items: center;">';
        
        // Botón anterior
        if (paymentTypesCurrentPage > 1) {
            paginationHTML += `<button class="pagination-btn" onclick="changePaymentTypesPage(${paymentTypesCurrentPage - 1})">‹</button>`;
        }
        
        // Números de página
        for (let i = 1; i <= totalPages; i++) {
            if (i === paymentTypesCurrentPage) {
                paginationHTML += `<button class="pagination-btn active">${i}</button>`;
            } else if (i === 1 || i === totalPages || (i >= paymentTypesCurrentPage - 1 && i <= paymentTypesCurrentPage + 1)) {
                paginationHTML += `<button class="pagination-btn" onclick="changePaymentTypesPage(${i})">${i}</button>`;
            } else if (i === paymentTypesCurrentPage - 2 || i === paymentTypesCurrentPage + 2) {
                paginationHTML += `<span style="padding: 0 4px;">...</span>`;
            }
        }
        
        // Botón siguiente
        if (paymentTypesCurrentPage < totalPages) {
            paginationHTML += `<button class="pagination-btn" onclick="changePaymentTypesPage(${paymentTypesCurrentPage + 1})">›</button>`;
        }
        
        paginationHTML += '</div>';
        paginationContainer.innerHTML = paginationHTML;
    }

    function renderBankAccountsSelect() {
        const select = document.getElementById('paymentTypeBankAccount');
        if (!select) return;
        
        select.innerHTML = '<option value="">Seleccione una cuenta...</option>';
        
        // Solo mostrar cuentas activas
        const activeBankAccounts = bankAccountsData.filter(account => account.active === 1 || account.active === '1' || account.active === true);
        activeBankAccounts.forEach(account => {
            const option = document.createElement('option');
            option.value = account.id;
            option.textContent = `${account.name} (${account.bank_name} - ${account.account_number})`;
            select.appendChild(option);
        });
    }

    // Función para cambiar página
    window.changePaymentTypesPage = function(page) {
        paymentTypesCurrentPage = page;
        renderPaymentTypesTable();
        updatePaymentTypesPagination();
    };

    // Función para abrir modal de tipo de pago
    window.openPaymentTypeModal = function() {
        const modalBody = `
            <form id="paymentTypeForm">
                <div class="modal-body">
                    <input type="hidden" id="paymentTypeId" name="paymentTypeId" value="">
                    
                    <div class="form-group">
                        <label class="form-label" for="paymentTypeName">Nombre del Tipo de Pago *</label>
                        <input 
                            type="text" 
                            class="form-input" 
                            id="paymentTypeName" 
                            name="paymentTypeName" 
                            required 
                            maxlength="255"
                            placeholder="Ej: Efectivo, Transferencia Bancaria, Tarjeta de Débito..."
                            autocomplete="off"
                        >
                        <div class="input-feedback" id="namefeedback"></div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="paymentTypeDescription">Descripción</label>
                        <textarea 
                            class="form-input" 
                            id="paymentTypeDescription" 
                            name="paymentTypeDescription" 
                            rows="3" 
                            maxlength="500"
                            placeholder="Descripción opcional del método de pago..."
                        ></textarea>
                        <small class="form-text">Máximo 500 caracteres</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="paymentTypeBankAccount">Cuenta Bancaria Asociada *</label>
                        <select class="form-input" id="paymentTypeBankAccount" name="paymentTypeBankAccount" required>
                            <option value="">Seleccione una cuenta bancaria...</option>
                        </select>
                        <small class="form-text">Esta cuenta se usará para registrar los movimientos de este método de pago</small>
                        <div class="input-feedback" id="accountFeedback"></div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Estado</label>
                        <div style="display: flex; align-items: center; gap: 12px; margin-top: 8px;">
                            <label class="switch">
                                <input type="checkbox" id="paymentTypeStatus" name="paymentTypeStatus" checked>
                                <span class="slider round"></span>
                            </label>
                            <span id="paymentTypeStatusLabel" style="font-weight: 500; color: var(--success-color);">Activo</span>
                        </div>
                        <small class="form-text">Los tipos de pago inactivos no aparecerán en los formularios de gastos</small>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeModal('formModal')" style="background-color: var(--secondary-color); color: white;">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary" id="savePaymentTypeBtn">
                        <i class="fas fa-save"></i>
                        Guardar Tipo de Pago
                    </button>
                </div>
            </form>
        `;
        
        document.getElementById('formModalTitle').innerHTML = '<i class="fas fa-plus-circle"></i> Nuevo Tipo de Pago';
        document.getElementById('formModalBody').innerHTML = modalBody;
        
        // Cargar cuentas bancarias en el select
        renderBankAccountsSelect();
        
        openModal('formModal');
        
        editingPaymentTypeId = null;
        
        // Validación en tiempo real
        setupPaymentTypeValidation();
        
        // Event listener para el switch de estado
        const statusSwitch = document.getElementById('paymentTypeStatus');
        const statusLabel = document.getElementById('paymentTypeStatusLabel');
        
        if (statusSwitch && statusLabel) {
            statusSwitch.addEventListener('change', function() {
                if (this.checked) {
                    statusLabel.textContent = 'Activo';
                    statusLabel.style.color = 'var(--success-color)';
                } else {
                    statusLabel.textContent = 'Inactivo';
                    statusLabel.style.color = 'var(--danger-color)';
                }
            });
        }
        
        // Event listener para el formulario
        document.getElementById('paymentTypeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            savePaymentTypeFromSettings();
        });
    };

    // Función para validación en tiempo real
    function setupPaymentTypeValidation() {
        const nameInput = document.getElementById('paymentTypeName');
        const accountSelect = document.getElementById('paymentTypeBankAccount');
        const submitBtn = document.getElementById('savePaymentTypeBtn');
        
        function validateName() {
            const value = nameInput.value.trim();
            const feedback = document.getElementById('namefeedback');
            
            if (value.length === 0) {
                feedback.textContent = '';
                return false;
            } else if (value.length < 3) {
                feedback.textContent = 'El nombre debe tener al menos 3 caracteres';
                feedback.style.color = '#ef4444';
                return false;
            } else {
                feedback.textContent = '✓ Nombre válido';
                feedback.style.color = '#10b981';
                return true;
            }
        }
        
        function validateAccount() {
            const value = accountSelect.value;
            const feedback = document.getElementById('accountFeedback');
            
            if (value) {
                feedback.textContent = '✓ Cuenta bancaria seleccionada';
                feedback.style.color = '#10b981';
                return true;
            } else {
                feedback.textContent = '';
                return false;
            }
        }
        
        function updateSubmitButton() {
            const isValid = validateName() && validateAccount();
            submitBtn.disabled = !isValid;
            if (!isValid) {
                submitBtn.style.opacity = '0.5';
                submitBtn.style.cursor = 'not-allowed';
            } else {
                submitBtn.style.opacity = '1';
                submitBtn.style.cursor = 'pointer';
            }
        }
        
        nameInput.addEventListener('input', () => {
            validateName();
            updateSubmitButton();
        });
        
        accountSelect.addEventListener('change', () => {
            validateAccount();
            updateSubmitButton();
        });
        
        // Validación inicial
        updateSubmitButton();
    }

    // Función para guardar tipo de pago
    async function savePaymentTypeFromSettings() {
        const typeId = document.getElementById('paymentTypeId').value;
        const typeName = document.getElementById('paymentTypeName').value.trim();
        const typeDescription = document.getElementById('paymentTypeDescription').value.trim();
        const typeBankAccount = document.getElementById('paymentTypeBankAccount').value;
        const typeStatus = document.getElementById('paymentTypeStatus').checked;
        
        if (!typeName) {
            showToast('El nombre del tipo es obligatorio', 'error');
            return;
        }
        
        if (!typeBankAccount) {
            showToast('La cuenta bancaria es obligatoria', 'error');
            return;
        }
        
        // Preparar datos
        const data = {
            name: typeName,
            description: typeDescription,
            bank_account_id: typeBankAccount,
            status: typeStatus,
            csrf_token: window.CSRF_TOKEN || 'dummy_token'
        };
        
        if (typeId) {
            data.id = typeId;
        }
        
        try {
            const url = 'api/payment_type/PaymentTypeController.php';
            const method = typeId ? 'PUT' : 'POST';
            
            const response = await fetch(url, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            
            if (result.error) {
                showToast(result.error, 'error');
            } else {
                showToast(typeId ? 'Tipo actualizado exitosamente' : 'Tipo creado exitosamente', 'success');
                closeModal('formModal');
                await loadPaymentTypes();
            }
            
        } catch (error) {
            console.error('Error saving payment type:', error);
            showToast('Error al guardar el tipo: ' + error.message, 'error');
        }
    }

    // Función para editar tipo de pago
    window.editPaymentTypeFromSettings = async function(typeId) {
        const type = paymentTypesData.find(t => t.id === typeId);
        
        if (!type) {
            showToast('Tipo de pago no encontrado', 'error');
            return;
        }
        
        const modalBody = `
            <form id="paymentTypeForm">
                <div class="modal-body">
                    <input type="hidden" id="paymentTypeId" name="paymentTypeId" value="${type.id}">
                    
                    <div class="form-group">
                        <label class="form-label" for="paymentTypeName">Nombre del Tipo de Pago *</label>
                        <input 
                            type="text" 
                            class="form-input" 
                            id="paymentTypeName" 
                            name="paymentTypeName" 
                            value="${type.name || ''}" 
                            required 
                            maxlength="255"
                            placeholder="Ej: Efectivo, Transferencia Bancaria, Tarjeta de Débito..."
                            autocomplete="off"
                        >
                        <div class="input-feedback" id="namefeedback"></div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="paymentTypeDescription">Descripción</label>
                        <textarea 
                            class="form-input" 
                            id="paymentTypeDescription" 
                            name="paymentTypeDescription" 
                            rows="3" 
                            maxlength="500"
                            placeholder="Descripción opcional del método de pago..."
                        >${type.description || ''}</textarea>
                        <small class="form-text">Máximo 500 caracteres</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="paymentTypeBankAccount">Cuenta Bancaria Asociada *</label>
                        <select class="form-input" id="paymentTypeBankAccount" name="paymentTypeBankAccount" required>
                            <option value="">Seleccione una cuenta bancaria...</option>
                        </select>
                        <small class="form-text">Esta cuenta se usará para registrar los movimientos de este método de pago</small>
                        <div class="input-feedback" id="accountFeedback"></div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Estado</label>
                        <div style="display: flex; align-items: center; gap: 12px; margin-top: 8px;">
                            <label class="switch">
                                <input type="checkbox" id="paymentTypeStatus" name="paymentTypeStatus" ${type.status === 'active' ? 'checked' : ''}>
                                <span class="slider round"></span>
                            </label>
                            <span id="paymentTypeStatusLabel" style="font-weight: 500; color: ${type.status === 'active' ? 'var(--success-color)' : 'var(--danger-color)'};">${type.status === 'active' ? 'Activo' : 'Inactivo'}</span>
                        </div>
                        <small class="form-text">Los tipos de pago inactivos no aparecerán en los formularios de gastos</small>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeModal('formModal')" style="background-color: var(--secondary-color); color: white;">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary" id="savePaymentTypeBtn">
                        <i class="fas fa-save"></i>
                        Actualizar Tipo de Pago
                    </button>
                </div>
            </form>
        `;
        
        document.getElementById('formModalTitle').innerHTML = '<i class="fas fa-edit"></i> Editar Tipo de Pago';
        document.getElementById('formModalBody').innerHTML = modalBody;
        
        // Cargar cuentas bancarias y seleccionar la actual
        renderBankAccountsSelect();
        setTimeout(() => {
            document.getElementById('paymentTypeBankAccount').value = type.bank_account_id || '';
        }, 100);
        
        openModal('formModal');
        
        editingPaymentTypeId = type.id;
        
        // Validación en tiempo real
        setupPaymentTypeValidation();
        
        // Event listener para el switch de estado
        const statusSwitch = document.getElementById('paymentTypeStatus');
        const statusLabel = document.getElementById('paymentTypeStatusLabel');
        
        if (statusSwitch && statusLabel) {
            statusSwitch.addEventListener('change', function() {
                if (this.checked) {
                    statusLabel.textContent = 'Activo';
                    statusLabel.style.color = 'var(--success-color)';
                } else {
                    statusLabel.textContent = 'Inactivo';
                    statusLabel.style.color = 'var(--danger-color)';
                }
            });
        }
        
        // Event listener para el formulario de edición
        document.getElementById('paymentTypeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            savePaymentTypeFromSettings();
        });
    };

    // Función para eliminar tipo de pago
    window.deletePaymentTypeFromSettings = async function(typeId, typeName) {
        let message = `¿Está seguro de que desea eliminar el tipo "${typeName}"?`;
        
        document.getElementById('confirmDeleteMessage').textContent = message;
        document.getElementById('confirmDeleteBtn').onclick = async function() {
            try {
                const response = await fetch('api/payment_type/PaymentTypeController.php', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `id=${encodeURIComponent(typeId)}&csrf_token=${encodeURIComponent(window.CSRF_TOKEN || 'dummy_token')}`
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                
                if (result.error) {
                    showToast(result.error, 'error');
                } else {
                    showToast('Tipo eliminado exitosamente', 'success');
                    closeModal('confirmDeleteModal');
                    await loadPaymentTypes();
                }
                
            } catch (error) {
                console.error('Error deleting payment type:', error);
                showToast('Error al eliminar el tipo: ' + error.message, 'error');
            }
        };
        
        openModal('confirmDeleteModal');
    };

    // Event listener para búsqueda
    document.getElementById('paymentTypesSearchInput').addEventListener('input', function() {
        filterPaymentTypes();
    });
    
    // Cargar datos iniciales
    loadPaymentTypes();
    
    // Variables de ordenamiento para tipos de pago
    let paymentTypesSortField = 'name';
    let paymentTypesSortDirection = 'asc';
    
    // Funciones de ordenamiento para tipos de pago
    function setupPaymentTypesTableSorting() {
        const table = document.getElementById('paymentTypesTable');
        if (!table) return;
        
        const sortableHeaders = table.querySelectorAll('th.sortable');
        sortableHeaders.forEach(header => {
            header.addEventListener('click', () => {
                const sortBy = header.getAttribute('data-sort');
                if (paymentTypesSortField === sortBy) {
                    paymentTypesSortDirection = paymentTypesSortDirection === 'asc' ? 'desc' : 'asc';
                } else {
                    paymentTypesSortField = sortBy;
                    paymentTypesSortDirection = 'asc';
                }
                
                sortPaymentTypesData();
                updatePaymentTypesSortIcons();
            });
        });
    }
    
    function sortPaymentTypesData() {
        paymentTypesFilteredData.sort((a, b) => {
            let valueA = a[paymentTypesSortField] || '';
            let valueB = b[paymentTypesSortField] || '';
            
            if (typeof valueA === 'string') {
                valueA = valueA.toLowerCase().trim();
                valueB = valueB.toLowerCase().trim();
            }
            
            if (valueA < valueB) return paymentTypesSortDirection === 'asc' ? -1 : 1;
            if (valueA > valueB) return paymentTypesSortDirection === 'asc' ? 1 : -1;
            return 0;
        });
        
        paymentTypesCurrentPage = 1;
        renderPaymentTypesTable();
        updatePaymentTypesPagination();
    }
    
    function updatePaymentTypesSortIcons() {
        const table = document.getElementById('paymentTypesTable');
        if (!table) return;
        
        const sortableHeaders = table.querySelectorAll('th.sortable');
        sortableHeaders.forEach(header => {
            const icon = header.querySelector('.sort-icon');
            const sortBy = header.getAttribute('data-sort');
            
            if (sortBy === paymentTypesSortField) {
                icon.className = paymentTypesSortDirection === 'asc' ? 'fas fa-sort-up sort-icon' : 'fas fa-sort-down sort-icon';
            } else {
                icon.className = 'fas fa-sort sort-icon';
            }
        });
    }
    
    // Funciones para manejo de estado
    window.togglePaymentTypesStatusFilterDropdown = function() {
        const dropdown = document.getElementById('paymentTypesStatusFilterDropdown');
        if (dropdown) {
            dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
        }
    };

    window.applyPaymentTypesStatusFilter = function(status) {
        paymentTypesStatusFilter = status;
        
        // Actualizar texto del botón
        const filterText = document.getElementById('paymentTypesStatusFilterText');
        if (filterText) {
            switch(status) {
                case 'active':
                    filterText.textContent = 'Solo activos';
                    break;
                case 'inactive':
                    filterText.textContent = 'Solo inactivos';
                    break;
                default:
                    filterText.textContent = 'Todos los estados';
            }
        }
        
        // Cerrar dropdown
        const dropdown = document.getElementById('paymentTypesStatusFilterDropdown');
        if (dropdown) {
            dropdown.style.display = 'none';
        }
        
        // Aplicar filtro
        filterPaymentTypes();
    };

    window.togglePaymentTypeStatus = function(id, name, currentStatus) {
        const newStatus = currentStatus === 'active' ? 'inactivo' : 'activo';
        const action = currentStatus === 'active' ? 'desactivar' : 'activar';
        
        // Mostrar modal de confirmación
        const modal = document.getElementById('confirmPaymentTypeStatusModal');
        const message = document.getElementById('confirmPaymentTypeStatusMessage');
        const confirmBtn = document.getElementById('confirmPaymentTypeStatusBtn');
        
        if (modal && message && confirmBtn) {
            message.textContent = `¿Está seguro de que desea ${action} el tipo de pago "${name}"?`;
            
            confirmBtn.onclick = async function() {
                try {
                    const response = await fetch('api/payment_type/PaymentTypeController.php?action=togglePaymentTypeStatus', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            id: id,
                            csrf_token: window.CSRF_TOKEN
                        })
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        showToast(result.message || 'Estado actualizado correctamente', 'success');
                        closeModal('confirmPaymentTypeStatusModal');
                        loadPaymentTypes(); // Recargar datos
                    } else {
                        showToast(result.error || 'Error al cambiar estado', 'error');
                    }
                } catch (error) {
                    console.error('Error toggling payment type status:', error);
                    showToast('Error al cambiar estado del tipo de pago', 'error');
                }
            };
            
            openModal('confirmPaymentTypeStatusModal');
        }
    };

    // Cerrar dropdown al hacer clic fuera
    document.addEventListener('click', function(event) {
        const dropdown = document.getElementById('paymentTypesStatusFilterDropdown');
        const button = document.getElementById('paymentTypesStatusFilterBtn');
        
        if (dropdown && button && !button.contains(event.target) && !dropdown.contains(event.target)) {
            dropdown.style.display = 'none';
        }
    });

    // Configurar ordenamiento después de un breve delay para asegurar que el DOM esté listo
    setTimeout(() => {
        setupPaymentTypesTableSorting();
        updatePaymentTypesSortIcons();
    }, 100);
}

// Función global para escapar HTML
function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

// SECCIÓN CATEGORÍAS DE GASTOS - Basado en expense_categories.php funcional
function loadExpenseCategoriesSection() {
    // Variables específicas para categorías
    let categoriesData = [];
    let categoriesCurrentPage = 1;
    let categoriesItemsPerPage = 10;
    let categoriesFilteredData = [];
    let categoriesStatusFilter = '';
    
    const content = `
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; gap: 16px;">
                <div style="display: flex; align-items: center; gap: 16px; flex: 1;">
                    <input
                        type="text"
                        id="categoriesSearchInput"
                        class="form-input"
                        placeholder="Buscar categoría..."
                        style="max-width: 300px;"
                        autocomplete="off"
                    >
                    <div class="status-filter-container" style="position: relative;">
                        <button type="button" class="btn" id="categoriesStatusFilterBtn" onclick="toggleCategoriesStatusFilterDropdown()" style="background: var(--bg-secondary); border: 1px solid var(--border-color); display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-filter"></i>
                            <span id="categoriesStatusFilterText">Todos los estados</span>
                            <i class="fas fa-chevron-down" style="font-size: 12px;"></i>
                        </button>
                        <div class="status-filter-dropdown" id="categoriesStatusFilterDropdown" style="display: none; position: absolute; top: 100%; left: 0; background: white; border: 1px solid var(--border-color); border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); z-index: 1000; min-width: 180px; margin-top: 4px;">
                            <div class="filter-option" onclick="applyCategoriesStatusFilter('')" style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid var(--border-color);">
                                <i class="fas fa-list" style="width: 16px; margin-right: 8px;"></i>
                                Todos los estados
                            </div>
                            <div class="filter-option" onclick="applyCategoriesStatusFilter('active')" style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid var(--border-color);">
                                <i class="fas fa-check-circle" style="width: 16px; margin-right: 8px; color: var(--success-color);"></i>
                                Solo activas
                            </div>
                            <div class="filter-option" onclick="applyCategoriesStatusFilter('inactive')" style="padding: 8px 12px; cursor: pointer;">
                                <i class="fas fa-times-circle" style="width: 16px; margin-right: 8px; color: var(--danger-color);"></i>
                                Solo inactivas
                            </div>
                        </div>
                    </div>
                </div>
                <div>
                    <button type="button" class="btn btn-primary" onclick="openCategoryModal()">
                        <i class="fas fa-plus"></i>
                        Nueva Categoría
                    </button>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-table"></i>
                    Lista de Categorías
                </h3>
                <p class="card-subtitle">Total: <span id="totalCategories">0</span> categorías registradas</p>
            </div>
            <div style="overflow-x: auto;">
                <table class="data-table sortable-table" id="categoriesTable" style="min-width: 700px;">
                    <thead>
                        <tr>
                            <th class="sortable" data-sort="name">
                                Nombre
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th class="sortable" data-sort="description">
                                Descripción
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th style="width: 100px; text-align: center;">Tipos</th>
                            <th class="sortable" data-sort="status" style="width: 100px; text-align: center;">
                                Estado
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th style="width: 120px; text-align: center;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="categoriesTableBody">
                        <!-- Las filas se llenarán dinámicamente -->
                    </tbody>
                </table>
                <div id="categoriesTableFooter" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0 0 0;">
                    <div id="categoriesPageSizeContainer"></div>
                    <div id="categoriesPagination"></div>
                </div>
            </div>
        </div>

        <!-- Modal de confirmación para cambio de estado -->
        <div class="modal" id="confirmCategoryStatusModal" style="display: none;">
            <div class="modal-overlay" onclick="closeModal('confirmCategoryStatusModal')"></div>
            <div class="modal-content" style="max-width: 400px;">
                <div class="modal-header">
                    <h2>Confirmar cambio de estado</h2>
                    <button type="button" class="modal-close" onclick="closeModal('confirmCategoryStatusModal')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <p id="confirmCategoryStatusMessage">¿Está seguro de que desea cambiar el estado de esta categoría?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeModal('confirmCategoryStatusModal')">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="confirmCategoryStatusBtn">Confirmar</button>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('settingsContent').innerHTML = content;
    
    // Funciones internas para categorías
    async function loadCategories() {
        try {
            let url = 'api/expense_category/ExpenseCategoryController.php?action=list';
            if (categoriesStatusFilter) {
                url += `&status=${categoriesStatusFilter}`;
            }
            
            const response = await fetch(url);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            
            if (result.success) {
                categoriesData = result.data || [];
                filterCategories();
            } else {
                showToast('Error: ' + (result.message || 'No se pudieron cargar las categorías'), 'error');
                categoriesData = [];
                filterCategories();
            }
        } catch (error) {
            console.error('Error loading categories:', error);
            showToast('Error al cargar las categorías: ' + error.message, 'error');
            categoriesData = [];
            filterCategories();
        }
    }

    function filterCategories() {
        const searchTerm = document.getElementById('categoriesSearchInput')?.value.toLowerCase() || '';
        
        categoriesFilteredData = categoriesData.filter(category => {
            const matchesSearch = category.name.toLowerCase().includes(searchTerm) ||
                (category.description && category.description.toLowerCase().includes(searchTerm));
            
            const matchesStatus = categoriesStatusFilter === '' || category.status === categoriesStatusFilter;
            
            return matchesSearch && matchesStatus;
        });
        
        categoriesCurrentPage = 1;
        renderCategoriesTable();
        updateCategoriesPagination();
    }

    function renderCategoriesTable() {
        const tbody = document.getElementById('categoriesTableBody');
        const totalElement = document.getElementById('totalCategories');
        
        if (!tbody) return;
        
        // Actualizar contador total
        if (totalElement) {
            totalElement.textContent = categoriesFilteredData.length;
        }
        
        // Calcular índices para paginación
        const startIndex = (categoriesCurrentPage - 1) * categoriesItemsPerPage;
        const endIndex = startIndex + categoriesItemsPerPage;
        const pageData = categoriesFilteredData.slice(startIndex, endIndex);
        
        // Limpiar tabla
        tbody.innerHTML = '';
        
        if (pageData.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" style="text-align: center; padding: 2rem; color: #6B7280;">
                        <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>
                        No se encontraron categorías
                    </td>
                </tr>
            `;
            return;
        }
        
        pageData.forEach(category => {
            const row = document.createElement('tr');
            
            // Obtener conteo de tipos asociados
            const typesCount = category.types_count || 0;
            const isActive = category.status === 'active' || category.status_numeric === 1;
            const statusText = isActive ? 'Activa' : 'Inactiva';
            const statusBadgeClass = isActive ? 'status-active' : 'status-inactive';
            
            row.innerHTML = `
                <td>
                    <div style="font-weight: 500;">${escapeHtml(category.name)}</div>
                </td>
                <td>
                    <div style="color: #6B7280; max-width: 300px; overflow: hidden; text-overflow: ellipsis;">
                        ${category.description ? escapeHtml(category.description) : '-'}
                    </div>
                </td>
                <td style="text-align: center;">
                    <span style="padding: 4px 8px; background-color: #F3F4F6; border-radius: 4px; font-size: 0.875rem; font-weight: 500;">
                        ${typesCount}
                    </span>
                </td>
                <td style="text-align: center;">
                    <span class="status-badge ${statusBadgeClass}">
                        ${statusText}
                    </span>
                </td>
                <td style="text-align: center;">
                    <div style="display: flex; gap: 8px; justify-content: center;">
                        <button type="button" class="btn-action" onclick="toggleCategoryStatusFromSettings('${category.id}', '${escapeHtml(category.name)}', '${category.status}')" title="${isActive ? 'Desactivar' : 'Activar'}">
                            <i class="fas ${isActive ? 'fa-toggle-on' : 'fa-toggle-off'}"></i>
                        </button>
                        <button type="button" class="btn-action" onclick="editCategoryFromSettings('${category.id}')" title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" class="btn-action btn-danger" onclick="deleteCategoryFromSettings('${category.id}', '${escapeHtml(category.name)}')" title="Eliminar">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            `;
            
            tbody.appendChild(row);
        });
    }

    function updateCategoriesPagination() {
        const totalPages = Math.ceil(categoriesFilteredData.length / categoriesItemsPerPage);
        const paginationContainer = document.getElementById('categoriesPagination');
        const pageSizeContainer = document.getElementById('categoriesPageSizeContainer');
        
        if (!paginationContainer) return;
        
        // Selector de elementos por página
        if (pageSizeContainer) {
            pageSizeContainer.innerHTML = `
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 0.875rem; color: #6B7280;">Mostrar:</span>
                    <select id="categoriesPageSizeSelector" style="padding: 4px 8px; border: 1px solid #D1D5DB; border-radius: 4px; font-size: 0.875rem;">
                        <option value="10" ${categoriesItemsPerPage === 10 ? 'selected' : ''}>10</option>
                        <option value="25" ${categoriesItemsPerPage === 25 ? 'selected' : ''}>25</option>
                        <option value="50" ${categoriesItemsPerPage === 50 ? 'selected' : ''}>50</option>
                    </select>
                    <span style="font-size: 0.875rem; color: #6B7280;">por página</span>
                </div>
            `;
            
            const pageSizeSelector = document.getElementById('categoriesPageSizeSelector');
            if (pageSizeSelector) {
                pageSizeSelector.addEventListener('change', function() {
                    categoriesItemsPerPage = parseInt(this.value);
                    categoriesCurrentPage = 1;
                    renderCategoriesTable();
                    updateCategoriesPagination();
                });
            }
        }
        
        // Paginación
        if (totalPages <= 1) {
            paginationContainer.innerHTML = '';
            return;
        }
        
        let paginationHTML = '<div style="display: flex; gap: 4px; align-items: center;">';
        
        // Botón anterior
        if (categoriesCurrentPage > 1) {
            paginationHTML += `<button class="pagination-btn" onclick="changeCategoriesPage(${categoriesCurrentPage - 1})">‹</button>`;
        }
        
        // Números de página
        for (let i = 1; i <= totalPages; i++) {
            if (i === categoriesCurrentPage) {
                paginationHTML += `<button class="pagination-btn active">${i}</button>`;
            } else if (i === 1 || i === totalPages || (i >= categoriesCurrentPage - 1 && i <= categoriesCurrentPage + 1)) {
                paginationHTML += `<button class="pagination-btn" onclick="changeCategoriesPage(${i})">${i}</button>`;
            } else if (i === categoriesCurrentPage - 2 || i === categoriesCurrentPage + 2) {
                paginationHTML += `<span style="padding: 0 4px;">...</span>`;
            }
        }
        
        // Botón siguiente
        if (categoriesCurrentPage < totalPages) {
            paginationHTML += `<button class="pagination-btn" onclick="changeCategoriesPage(${categoriesCurrentPage + 1})">›</button>`;
        }
        
        paginationHTML += '</div>';
        paginationContainer.innerHTML = paginationHTML;
    }

    // Función para cambiar página
    window.changeCategoriesPage = function(page) {
        categoriesCurrentPage = page;
        renderCategoriesTable();
        updateCategoriesPagination();
    };

    // Función para abrir modal de categoría
    window.openCategoryModal = function() {
        const modalBody = `
            <form id="categoryForm">
                <div class="modal-body">
                    <input type="hidden" id="categoryId" name="categoryId" value="">
                    
                    <div class="form-group">
                        <label class="form-label" for="categoryName">Nombre *</label>
                        <input type="text" class="form-input" id="categoryName" name="categoryName" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="categoryDescription">Descripción</label>
                        <textarea class="form-input" id="categoryDescription" name="categoryDescription" rows="3" placeholder="Descripción de la categoría"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="categoryStatus">Estado</label>
                        <label class="switch">
                            <input type="checkbox" id="categoryStatus" name="categoryStatus" checked>
                            <span class="slider"></span>
                        </label>
                        <span style="margin-left: 8px; font-size: 14px; color: var(--text-secondary);">Activa</span>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeModal('formModal')" style="background-color: var(--secondary-color); color: white;">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Guardar Categoría
                    </button>
                </div>
            </form>
        `;
        
        document.getElementById('formModalTitle').textContent = 'Nueva Categoría';
        document.getElementById('formModalBody').innerHTML = modalBody;
        openModal('formModal');
        
        // Event listener para el formulario
        document.getElementById('categoryForm').addEventListener('submit', function(e) {
            e.preventDefault();
            saveCategoryFromSettings();
        });
    };

    // Función para guardar categoría
    async function saveCategoryFromSettings() {
        const formData = new FormData();
        const categoryId = document.getElementById('categoryId').value;
        const categoryName = document.getElementById('categoryName').value.trim();
        const categoryDescription = document.getElementById('categoryDescription').value.trim();
        const categoryStatus = document.getElementById('categoryStatus').checked;
        
        if (!categoryName) {
            showToast('El nombre de la categoría es obligatorio', 'error');
            return;
        }
        
        // Preparar datos
        formData.append('name', categoryName);
        formData.append('description', categoryDescription);
        formData.append('status', categoryStatus ? '1' : '0');
        
        try {
            let url, method;
            if (categoryId) {
                // Editar
                url = `api/expense_category/ExpenseCategoryController.php?action=update&id=${categoryId}`;
                method = 'POST';
            } else {
                // Crear
                url = 'api/expense_category/ExpenseCategoryController.php?action=create';
                method = 'POST';
            }
            
            const response = await fetch(url, {
                method: method,
                body: formData
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            
            if (result.success) {
                showToast(categoryId ? 'Categoría actualizada exitosamente' : 'Categoría creada exitosamente', 'success');
                closeModal('formModal');
                await loadCategories();
            } else {
                showToast(result.message || 'Error al guardar la categoría', 'error');
            }
            
        } catch (error) {
            console.error('Error saving category:', error);
            showToast('Error al guardar la categoría: ' + error.message, 'error');
        }
    }

    // Función para editar categoría
    window.editCategoryFromSettings = async function(categoryId) {
        try {
            const response = await fetch(`api/expense_category/ExpenseCategoryController.php?action=get&id=${categoryId}`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            
            if (result.success && result.data) {
                const category = result.data;
                
                const modalBody = `
                    <form id="categoryForm">
                        <div class="modal-body">
                            <input type="hidden" id="categoryId" name="categoryId" value="${category.id}">
                            
                            <div class="form-group">
                                <label class="form-label" for="categoryName">Nombre *</label>
                                <input type="text" class="form-input" id="categoryName" name="categoryName" value="${category.name || ''}" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="categoryDescription">Descripción</label>
                                <textarea class="form-input" id="categoryDescription" name="categoryDescription" rows="3" placeholder="Descripción de la categoría">${category.description || ''}</textarea>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="categoryStatus">Estado</label>
                                <label class="switch">
                                    <input type="checkbox" id="categoryStatus" name="categoryStatus" ${category.status === 'active' || category.status_numeric === 1 ? 'checked' : ''}>
                                    <span class="slider"></span>
                                </label>
                                <span style="margin-left: 8px; font-size: 14px; color: var(--text-secondary);">Activa</span>
                            </div>
                        </div>
                        
                        <div class="modal-footer">
                            <button type="button" class="btn" onclick="closeModal('formModal')" style="background-color: var(--secondary-color); color: white;">
                                Cancelar
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i>
                                Actualizar Categoría
                            </button>
                        </div>
                    </form>
                `;
                
                document.getElementById('formModalTitle').textContent = 'Editar Categoría';
                document.getElementById('formModalBody').innerHTML = modalBody;
                openModal('formModal');
                
                // Event listener para el formulario de edición
                document.getElementById('categoryForm').addEventListener('submit', function(e) {
                    e.preventDefault();
                    saveCategoryFromSettings();
                });
            } else {
                showToast(result.message || 'No se pudo cargar la categoría', 'error');
            }
            
        } catch (error) {
            console.error('Error loading category for edit:', error);
            showToast('Error al cargar la categoría: ' + error.message, 'error');
        }
    };

    // Función para eliminar categoría
    window.deleteCategoryFromSettings = async function(categoryId, categoryName) {
        // Buscar la categoría en los datos locales para verificar si tiene tipos asociados
        const category = categoriesData.find(cat => cat.id === categoryId);
        const typesCount = category ? (category.types_count || 0) : 0;
        
        let message = `¿Está seguro de que desea eliminar la categoría "${categoryName}"?`;
        if (typesCount > 0) {
            message += `\n\nEsta categoría tiene ${typesCount} tipo(s) de gasto asociado(s).`;
        }
        
        document.getElementById('confirmDeleteMessage').textContent = message;
        document.getElementById('confirmDeleteBtn').onclick = async function() {
            try {
                const response = await fetch(`api/expense_category/ExpenseCategoryController.php?action=delete&id=${categoryId}`, {
                    method: 'DELETE'
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('Categoría eliminada exitosamente', 'success');
                    closeModal('confirmDeleteModal');
                    await loadCategories();
                } else {
                    showToast(result.message || 'Error al eliminar la categoría', 'error');
                }
                
            } catch (error) {
                console.error('Error deleting category:', error);
                showToast('Error al eliminar la categoría: ' + error.message, 'error');
            }
        };
        
        openModal('confirmDeleteModal');
    };

    // Funciones para filtro de estado
    window.toggleCategoriesStatusFilterDropdown = function() {
        const dropdown = document.getElementById('categoriesStatusFilterDropdown');
        if (dropdown) {
            dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
        }
    };

    window.applyCategoriesStatusFilter = function(status) {
        categoriesStatusFilter = status;
        
        // Actualizar texto del botón
        const filterText = document.getElementById('categoriesStatusFilterText');
        if (filterText) {
            switch(status) {
                case 'active':
                    filterText.textContent = 'Solo activas';
                    break;
                case 'inactive':
                    filterText.textContent = 'Solo inactivas';
                    break;
                default:
                    filterText.textContent = 'Todos los estados';
            }
        }
        
        // Cerrar dropdown
        const dropdown = document.getElementById('categoriesStatusFilterDropdown');
        if (dropdown) {
            dropdown.style.display = 'none';
        }
        
        // Recargar datos
        loadCategories();
    };

    // Función para toggle de estado
    window.toggleCategoryStatusFromSettings = function(categoryId, categoryName, currentStatus) {
        const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
        const actionText = newStatus === 'active' ? 'activar' : 'desactivar';
        
        // Configurar modal de confirmación
        const message = document.getElementById('confirmCategoryStatusMessage');
        const confirmBtn = document.getElementById('confirmCategoryStatusBtn');
        
        if (message) {
            message.textContent = `¿Está seguro de que desea ${actionText} la categoría "${categoryName}"?`;
        }
        
        if (confirmBtn) {
            confirmBtn.onclick = async function() {
                try {
                    const response = await fetch(`api/expense_category/ExpenseCategoryController.php?action=toggleStatus&id=${categoryId}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        }
                    });
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        showToast(result.message, 'success');
                        closeModal('confirmCategoryStatusModal');
                        await loadCategories();
                    } else {
                        showToast(result.message || 'Error al cambiar el estado', 'error');
                    }
                    
                } catch (error) {
                    console.error('Error toggling category status:', error);
                    showToast('Error al cambiar el estado: ' + error.message, 'error');
                }
            };
        }
        
        openModal('confirmCategoryStatusModal');
    };

    // Event listener para búsqueda
    document.getElementById('categoriesSearchInput').addEventListener('input', function() {
        filterCategories();
    });
    
    // Cerrar dropdown al hacer clic fuera
    document.addEventListener('click', function(event) {
        const dropdown = document.getElementById('categoriesStatusFilterDropdown');
        const button = document.getElementById('categoriesStatusFilterBtn');
        
        if (dropdown && button && !button.contains(event.target) && !dropdown.contains(event.target)) {
            dropdown.style.display = 'none';
        }
    });
    
    // Cargar datos iniciales
    loadCategories();
    
    // Variables de ordenamiento para categorías
    let categoriesSortField = 'name';
    let categoriesSortDirection = 'asc';
    
    // Funciones de ordenamiento para categorías
    function setupCategoriesTableSorting() {
        const table = document.getElementById('categoriesTable');
        if (!table) return;
        
        const sortableHeaders = table.querySelectorAll('th.sortable');
        sortableHeaders.forEach(header => {
            header.addEventListener('click', () => {
                const sortBy = header.getAttribute('data-sort');
                if (categoriesSortField === sortBy) {
                    categoriesSortDirection = categoriesSortDirection === 'asc' ? 'desc' : 'asc';
                } else {
                    categoriesSortField = sortBy;
                    categoriesSortDirection = 'asc';
                }
                
                sortCategoriesData();
                updateCategoriesSortIcons();
            });
        });
    }
    
    function sortCategoriesData() {
        categoriesFilteredData.sort((a, b) => {
            let valueA = a[categoriesSortField] || '';
            let valueB = b[categoriesSortField] || '';
            
            if (typeof valueA === 'string') {
                valueA = valueA.toLowerCase().trim();
                valueB = valueB.toLowerCase().trim();
            }
            
            // Para el campo status, convertir a boolean para ordenamiento
            if (categoriesSortField === 'status') {
                valueA = valueA === 'active' || valueA === 1;
                valueB = valueB === 'active' || valueB === 1;
            }
            
            if (valueA < valueB) return categoriesSortDirection === 'asc' ? -1 : 1;
            if (valueA > valueB) return categoriesSortDirection === 'asc' ? 1 : -1;
            return 0;
        });
        
        categoriesCurrentPage = 1;
        renderCategoriesTable();
        updateCategoriesPagination();
    }
    
    function updateCategoriesSortIcons() {
        const table = document.getElementById('categoriesTable');
        if (!table) return;
        
        const sortableHeaders = table.querySelectorAll('th.sortable');
        sortableHeaders.forEach(header => {
            const icon = header.querySelector('.sort-icon');
            const sortBy = header.getAttribute('data-sort');
            
            if (sortBy === categoriesSortField) {
                icon.className = categoriesSortDirection === 'asc' ? 'fas fa-sort-up sort-icon' : 'fas fa-sort-down sort-icon';
            } else {
                icon.className = 'fas fa-sort sort-icon';
            }
        });
    }
    
    // Configurar ordenamiento después de un breve delay
    setTimeout(() => {
        setupCategoriesTableSorting();
        updateCategoriesSortIcons();
    }, 100);
}

// SECCIÓN TIPOS DE GASTOS - Basado en expense_types.php funcional
function loadExpenseTypesSection() {
    // Variables específicas para tipos de gastos
    let typesData = [];
    let categoriesData = [];
    let typesCurrentPage = 1;
    let typesItemsPerPage = 10;
    let typesFilteredData = [];
    let typesStatusFilter = '';
    
    const content = `
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; gap: 16px;">
                <div style="display: flex; align-items: center; gap: 16px; flex: 1;">
                    <input
                        type="text"
                        id="typesSearchInput"
                        class="form-input"
                        placeholder="Buscar tipo de gasto..."
                        style="max-width: 300px;"
                        autocomplete="off"
                    >
                    <div class="status-filter-container" style="position: relative;">
                        <button type="button" class="btn" id="typesStatusFilterBtn" onclick="toggleTypesStatusFilterDropdown()" style="background: var(--bg-secondary); border: 1px solid var(--border-color); display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-filter"></i>
                            <span id="typesStatusFilterText">Todos los estados</span>
                            <i class="fas fa-chevron-down" style="font-size: 12px;"></i>
                        </button>
                        <div class="status-filter-dropdown" id="typesStatusFilterDropdown" style="display: none; position: absolute; top: 100%; left: 0; background: white; border: 1px solid var(--border-color); border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); z-index: 1000; min-width: 180px; margin-top: 4px;">
                            <div class="filter-option" onclick="applyTypesStatusFilter('')" style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid var(--border-color);">
                                <i class="fas fa-list" style="width: 16px; margin-right: 8px;"></i>
                                Todos los estados
                            </div>
                            <div class="filter-option" onclick="applyTypesStatusFilter('active')" style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid var(--border-color);">
                                <i class="fas fa-check-circle" style="width: 16px; margin-right: 8px; color: var(--success-color);"></i>
                                Solo activos
                            </div>
                            <div class="filter-option" onclick="applyTypesStatusFilter('inactive')" style="padding: 8px 12px; cursor: pointer;">
                                <i class="fas fa-times-circle" style="width: 16px; margin-right: 8px; color: var(--danger-color);"></i>
                                Solo inactivos
                            </div>
                        </div>
                    </div>
                </div>
                <div>
                    <button type="button" class="btn btn-primary" onclick="openExpenseTypeModal()">
                        <i class="fas fa-plus"></i>
                        Nuevo Tipo
                    </button>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-table"></i>
                    Lista de Tipos de Gastos
                </h3>
                <p class="card-subtitle">Total: <span id="totalExpenseTypes">0</span> tipos registrados</p>
            </div>
            <div style="overflow-x: auto;">
                <table class="data-table sortable-table" id="expenseTypesTable" style="min-width: 800px;">
                    <thead>
                        <tr>
                            <th class="sortable" data-sort="name">
                                Nombre
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th class="sortable" data-sort="description">
                                Descripción
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th class="sortable" data-sort="category_name">
                                Categoría
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th class="sortable" data-sort="status" style="width: 100px; text-align: center;">
                                Estado
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th style="width: 120px; text-align: center;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="expenseTypesTableBody">
                        <!-- Las filas se llenarán dinámicamente -->
                    </tbody>
                </table>
                <div id="expenseTypesTableFooter" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0 0 0;">
                    <div id="expenseTypesPageSizeContainer"></div>
                    <div id="expenseTypesPagination"></div>
                </div>
            </div>
        </div>

        <!-- Modal de confirmación para cambio de estado -->
        <div class="modal" id="confirmExpenseTypeStatusModal" style="display: none;">
            <div class="modal-overlay" onclick="closeModal('confirmExpenseTypeStatusModal')"></div>
            <div class="modal-content" style="max-width: 400px;">
                <div class="modal-header">
                    <h2>Confirmar cambio de estado</h2>
                    <button type="button" class="modal-close" onclick="closeModal('confirmExpenseTypeStatusModal')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <p id="confirmExpenseTypeStatusMessage">¿Está seguro de que desea cambiar el estado de este tipo de gasto?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeModal('confirmExpenseTypeStatusModal')">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="confirmExpenseTypeStatusBtn">Confirmar</button>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('settingsContent').innerHTML = content;
    
    // Funciones internas para tipos de gastos
    async function loadExpenseTypesCategories() {
        try {
            const response = await fetch('api/expense_type/ExpenseTypeController.php?action=categories');
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            
            if (result.success) {
                categoriesData = result.data || [];
                updateExpenseTypeCategorySelect();
            } else {
                console.error('Error loading categories:', result.message);
            }
        } catch (error) {
            console.error('Error loading categories:', error);
        }
    }

    function updateExpenseTypeCategorySelect() {
        const select = document.getElementById('expenseTypeCategory');
        if (!select) return;
        
        // Limpiar opciones excepto la primera
        select.innerHTML = '<option value="">Seleccionar categoría...</option>';
        
        categoriesData.forEach(category => {
            const option = document.createElement('option');
            option.value = category.id;
            option.textContent = category.name;
            select.appendChild(option);
        });
    }

    async function loadExpenseTypes() {
        try {
            let url = 'api/expense_type/ExpenseTypeController.php?action=list';
            if (typesStatusFilter) {
                url += `&status=${typesStatusFilter}`;
            }
            
            const response = await fetch(url);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            
            if (result.success) {
                typesData = result.data || [];
                filterExpenseTypes();
            } else {
                showToast('Error: ' + (result.message || 'No se pudieron cargar los tipos de gastos'), 'error');
                typesData = [];
                filterExpenseTypes();
            }
        } catch (error) {
            console.error('Error loading types:', error);
            showToast('Error al cargar los tipos de gastos: ' + error.message, 'error');
            typesData = [];
            filterExpenseTypes();
        }
    }

    function filterExpenseTypes() {
        const searchTerm = document.getElementById('typesSearchInput')?.value.toLowerCase() || '';
        
        typesFilteredData = typesData.filter(type => {
            const matchesSearch = type.name.toLowerCase().includes(searchTerm) ||
                (type.description && type.description.toLowerCase().includes(searchTerm)) ||
                (type.category_name && type.category_name.toLowerCase().includes(searchTerm));
            
            const matchesStatus = typesStatusFilter === '' || type.status === typesStatusFilter;
            
            return matchesSearch && matchesStatus;
        });
        
        typesCurrentPage = 1;
        renderExpenseTypesTable();
        updateExpenseTypesPagination();
    }

    function renderExpenseTypesTable() {
        const tbody = document.getElementById('expenseTypesTableBody');
        const totalElement = document.getElementById('totalExpenseTypes');
        
        if (!tbody) return;
        
        // Actualizar contador total
        if (totalElement) {
            totalElement.textContent = typesFilteredData.length;
        }
        
        // Calcular índices para paginación
        const startIndex = (typesCurrentPage - 1) * typesItemsPerPage;
        const endIndex = startIndex + typesItemsPerPage;
        const pageData = typesFilteredData.slice(startIndex, endIndex);
        
        // Limpiar tabla
        tbody.innerHTML = '';
        
        if (pageData.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" style="text-align: center; padding: 2rem; color: #6B7280;">
                        <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>
                        No se encontraron tipos de gastos
                    </td>
                </tr>
            `;
            return;
        }
        
        pageData.forEach(type => {
            const row = document.createElement('tr');
            
            const isActive = type.status === 'active' || type.status_numeric === 1;
            const statusBadgeClass = isActive ? 'status-active' : 'status-inactive';
            const statusText = isActive ? 'Activo' : 'Inactivo';
            
            row.innerHTML = `
                <td>
                    <div style="font-weight: 500;">${escapeHtml(type.name || '')}</div>
                </td>
                <td>
                    <div style="color: #6B7280; max-width: 300px; overflow: hidden; text-overflow: ellipsis;">
                        ${escapeHtml(type.description || '-')}
                    </div>
                </td>
                <td>
                    <div style="padding: 4px 8px; background-color: #F3F4F6; border-radius: 4px; font-size: 0.875rem; font-weight: 500; display: inline-block;">
                        ${escapeHtml(type.category_name || 'Sin categoría')}
                    </div>
                </td>
                <td style="text-align: center;">
                    <span class="status-badge ${statusBadgeClass}">
                        ${statusText}
                    </span>
                </td>
                <td style="text-align: center;">
                    <div style="display: flex; gap: 8px; justify-content: center;">
                        <label class="switch" title="Cambiar estado">
                            <input type="checkbox" ${isActive ? 'checked' : ''} onchange="toggleExpenseTypeStatus('${type.id}', '${escapeHtml(type.name || '')}')">
                            <span class="slider"></span>
                        </label>
                        <button type="button" class="btn-action" onclick="editExpenseTypeFromSettings('${type.id}')" title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" class="btn-action btn-danger" onclick="deleteExpenseTypeFromSettings('${type.id}', '${escapeHtml(type.name || '')}')" title="Eliminar">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            `;
            
            tbody.appendChild(row);
        });
    }

    function updateExpenseTypesPagination() {
        const totalPages = Math.ceil(typesFilteredData.length / typesItemsPerPage);
        const paginationContainer = document.getElementById('expenseTypesPagination');
        const pageSizeContainer = document.getElementById('expenseTypesPageSizeContainer');
        
        if (!paginationContainer) return;
        
        // Selector de elementos por página
        if (pageSizeContainer) {
            pageSizeContainer.innerHTML = `
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 0.875rem; color: #6B7280;">Mostrar:</span>
                    <select id="expenseTypesPageSizeSelector" style="padding: 4px 8px; border: 1px solid #D1D5DB; border-radius: 4px; font-size: 0.875rem;">
                        <option value="10" ${typesItemsPerPage === 10 ? 'selected' : ''}>10</option>
                        <option value="25" ${typesItemsPerPage === 25 ? 'selected' : ''}>25</option>
                        <option value="50" ${typesItemsPerPage === 50 ? 'selected' : ''}>50</option>
                    </select>
                    <span style="font-size: 0.875rem; color: #6B7280;">por página</span>
                </div>
            `;
            
            const pageSizeSelector = document.getElementById('expenseTypesPageSizeSelector');
            if (pageSizeSelector) {
                pageSizeSelector.addEventListener('change', function() {
                    typesItemsPerPage = parseInt(this.value);
                    typesCurrentPage = 1;
                    renderExpenseTypesTable();
                    updateExpenseTypesPagination();
                });
            }
        }
        
        // Paginación
        if (totalPages <= 1) {
            paginationContainer.innerHTML = '';
            return;
        }
        
        let paginationHTML = '<div style="display: flex; gap: 4px; align-items: center;">';
        
        // Botón anterior
        if (typesCurrentPage > 1) {
            paginationHTML += `<button class="pagination-btn" onclick="changeExpenseTypesPage(${typesCurrentPage - 1})">‹</button>`;
        }
        
        // Números de página
        for (let i = 1; i <= totalPages; i++) {
            if (i === typesCurrentPage) {
                paginationHTML += `<button class="pagination-btn active">${i}</button>`;
            } else if (i === 1 || i === totalPages || (i >= typesCurrentPage - 1 && i <= typesCurrentPage + 1)) {
                paginationHTML += `<button class="pagination-btn" onclick="changeExpenseTypesPage(${i})">${i}</button>`;
            } else if (i === typesCurrentPage - 2 || i === typesCurrentPage + 2) {
                paginationHTML += `<span style="padding: 0 4px;">...</span>`;
            }
        }
        
        // Botón siguiente
        if (typesCurrentPage < totalPages) {
            paginationHTML += `<button class="pagination-btn" onclick="changeExpenseTypesPage(${typesCurrentPage + 1})">›</button>`;
        }
        
        paginationHTML += '</div>';
        paginationContainer.innerHTML = paginationHTML;
    }

    // Función para cambiar página
    window.changeExpenseTypesPage = function(page) {
        typesCurrentPage = page;
        renderExpenseTypesTable();
        updateExpenseTypesPagination();
    };

    // Función para abrir modal de tipo de gasto
    window.openExpenseTypeModal = function() {
        const modalBody = `
            <form id="expenseTypeForm">
                <div class="modal-body">
                    <input type="hidden" id="expenseTypeId" name="expenseTypeId" value="">
                    
                    <div class="form-group">
                        <label class="form-label" for="expenseTypeName">Nombre *</label>
                        <input type="text" class="form-input" id="expenseTypeName" name="expenseTypeName" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="expenseTypeDescription">Descripción</label>
                        <textarea class="form-input" id="expenseTypeDescription" name="expenseTypeDescription" rows="3" placeholder="Descripción del tipo de gasto"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="expenseTypeCategory">Categoría *</label>
                        <select class="form-input" id="expenseTypeCategory" name="expenseTypeCategory" required>
                            <option value="">Seleccionar categoría...</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="expenseTypeStatus">Estado</label>
                        <label class="switch">
                            <input type="checkbox" id="expenseTypeStatus" name="expenseTypeStatus" checked>
                            <span class="slider"></span>
                        </label>
                        <span style="margin-left: 8px; font-size: 14px; color: var(--text-secondary);">Activo</span>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeModal('formModal')" style="background-color: var(--secondary-color); color: white;">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Guardar Tipo
                    </button>
                </div>
            </form>
        `;
        
        document.getElementById('formModalTitle').textContent = 'Nuevo Tipo de Gasto';
        document.getElementById('formModalBody').innerHTML = modalBody;
        
        // Cargar categorías en el select
        updateExpenseTypeCategorySelect();
        
        openModal('formModal');
        
        // Event listener para el formulario
        document.getElementById('expenseTypeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            saveExpenseTypeFromSettings();
        });
    };

    // Función para guardar tipo de gasto
    async function saveExpenseTypeFromSettings() {
        const formData = new FormData();
        const typeId = document.getElementById('expenseTypeId').value;
        const typeName = document.getElementById('expenseTypeName').value.trim();
        const typeDescription = document.getElementById('expenseTypeDescription').value.trim();
        const typeCategory = document.getElementById('expenseTypeCategory').value;
        const typeStatus = document.getElementById('expenseTypeStatus').checked;
        
        if (!typeName) {
            showToast('El nombre del tipo es obligatorio', 'error');
            return;
        }
        
        if (!typeCategory) {
            showToast('La categoría es obligatoria', 'error');
            return;
        }
        
        // Preparar datos
        formData.append('name', typeName);
        formData.append('description', typeDescription);
        formData.append('category_id', typeCategory);
        formData.append('status', typeStatus ? '1' : '0');
        
        try {
            let url, method;
            if (typeId) {
                // Editar
                url = `api/expense_type/ExpenseTypeController.php?action=update&id=${typeId}`;
                method = 'POST';
            } else {
                // Crear
                url = 'api/expense_type/ExpenseTypeController.php?action=create';
                method = 'POST';
            }
            
            const response = await fetch(url, {
                method: method,
                body: formData
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            
            if (result.error) {
                showToast(result.error, 'error');
            } else {
                showToast(typeId ? 'Tipo actualizado exitosamente' : 'Tipo creado exitosamente', 'success');
                closeModal('formModal');
                await loadExpenseTypes();
            }
            
        } catch (error) {
            console.error('Error saving type:', error);
            showToast('Error al guardar el tipo: ' + error.message, 'error');
        }
    }

    // Función para editar tipo de gasto
    window.editExpenseTypeFromSettings = async function(typeId) {
        try {
            const response = await fetch(`api/expense_type/ExpenseTypeController.php?action=get&id=${typeId}`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            
            if (result.success && result.data) {
                const type = result.data;
                
                const modalBody = `
                    <form id="expenseTypeForm">
                        <div class="modal-body">
                            <input type="hidden" id="expenseTypeId" name="expenseTypeId" value="${type.id}">
                            
                            <div class="form-group">
                                <label class="form-label" for="expenseTypeName">Nombre *</label>
                                <input type="text" class="form-input" id="expenseTypeName" name="expenseTypeName" value="${type.name || ''}" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="expenseTypeDescription">Descripción</label>
                                <textarea class="form-input" id="expenseTypeDescription" name="expenseTypeDescription" rows="3" placeholder="Descripción del tipo de gasto">${type.description || ''}</textarea>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="expenseTypeCategory">Categoría *</label>
                                <select class="form-input" id="expenseTypeCategory" name="expenseTypeCategory" required>
                                    <option value="">Seleccionar categoría...</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="expenseTypeStatus">Estado</label>
                                <label class="switch">
                                    <input type="checkbox" id="expenseTypeStatus" name="expenseTypeStatus" ${type.status === 'active' || type.status_numeric === 1 ? 'checked' : ''}>
                                    <span class="slider"></span>
                                </label>
                                <span style="margin-left: 8px; font-size: 14px; color: var(--text-secondary);">Activo</span>
                            </div>
                        </div>
                        
                        <div class="modal-footer">
                            <button type="button" class="btn" onclick="closeModal('formModal')" style="background-color: var(--secondary-color); color: white;">
                                Cancelar
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i>
                                Actualizar Tipo
                            </button>
                        </div>
                    </form>
                `;
                
                document.getElementById('formModalTitle').textContent = 'Editar Tipo de Gasto';
                document.getElementById('formModalBody').innerHTML = modalBody;
                
                // Cargar categorías y seleccionar la actual
                updateExpenseTypeCategorySelect();
                setTimeout(() => {
                    document.getElementById('expenseTypeCategory').value = type.category_id || '';
                }, 100);
                
                openModal('formModal');
                
                // Event listener para el formulario de edición
                document.getElementById('expenseTypeForm').addEventListener('submit', function(e) {
                    e.preventDefault();
                    saveExpenseTypeFromSettings();
                });
            } else {
                showToast(result.message || 'No se pudo cargar el tipo', 'error');
            }
            
        } catch (error) {
            console.error('Error loading type for edit:', error);
            showToast('Error al cargar el tipo: ' + error.message, 'error');
        }
    };

    // Función para eliminar tipo de gasto
    window.deleteExpenseTypeFromSettings = async function(typeId, typeName) {
        let message = `¿Está seguro de que desea eliminar el tipo "${typeName}"?`;
        
        document.getElementById('confirmDeleteMessage').textContent = message;
        document.getElementById('confirmDeleteBtn').onclick = async function() {
            try {
                const response = await fetch(`api/expense_type/ExpenseTypeController.php?action=delete&id=${typeId}`, {
                    method: 'DELETE'
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('Tipo eliminado exitosamente', 'success');
                    closeModal('confirmDeleteModal');
                    await loadExpenseTypes();
                } else {
                    showToast(result.message || 'Error al eliminar el tipo', 'error');
                }
                
            } catch (error) {
                console.error('Error deleting type:', error);
                showToast('Error al eliminar el tipo: ' + error.message, 'error');
            }
        };
        
        openModal('confirmDeleteModal');
    };

    // Funciones para filtro de estado
    window.toggleTypesStatusFilterDropdown = function() {
        const dropdown = document.getElementById('typesStatusFilterDropdown');
        if (dropdown) {
            dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
        }
    };

    window.applyTypesStatusFilter = function(status) {
        typesStatusFilter = status;
        
        // Actualizar texto del botón
        const filterText = document.getElementById('typesStatusFilterText');
        if (filterText) {
            switch(status) {
                case 'active':
                    filterText.textContent = 'Solo activos';
                    break;
                case 'inactive':
                    filterText.textContent = 'Solo inactivos';
                    break;
                default:
                    filterText.textContent = 'Todos los estados';
            }
        }
        
        // Cerrar dropdown
        const dropdown = document.getElementById('typesStatusFilterDropdown');
        if (dropdown) {
            dropdown.style.display = 'none';
        }
        
        // Recargar datos
        loadExpenseTypes();
    };

    // Función para toggle de estado
    window.toggleExpenseTypeStatus = function(typeId, typeName) {
        const modal = document.getElementById('confirmExpenseTypeStatusModal');
        const message = document.getElementById('confirmExpenseTypeStatusMessage');
        const confirmBtn = document.getElementById('confirmExpenseTypeStatusBtn');
        
        if (modal && message && confirmBtn) {
            message.textContent = `¿Está seguro de que desea cambiar el estado del tipo de gasto "${typeName}"?`;
            
            confirmBtn.onclick = async function() {
                try {
                    const response = await fetch(`api/expense_type/ExpenseTypeController.php?action=toggleStatus&id=${typeId}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        }
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        showToast(result.message, 'success');
                        loadExpenseTypes(); // Recargar la tabla
                    } else {
                        showToast(result.message || 'Error al cambiar el estado', 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showToast('Error al cambiar el estado del tipo de gasto', 'error');
                }
                
                closeModal('confirmExpenseTypeStatusModal');
            };
            
            openModal('confirmExpenseTypeStatusModal');
        }
    };

    // Cerrar dropdown al hacer clic fuera
    document.addEventListener('click', function(event) {
        const dropdown = document.getElementById('typesStatusFilterDropdown');
        const button = document.getElementById('typesStatusFilterBtn');
        
        if (dropdown && button && !button.contains(event.target) && !dropdown.contains(event.target)) {
            dropdown.style.display = 'none';
        }
    });

    // Event listener para búsqueda
    document.getElementById('typesSearchInput').addEventListener('input', function() {
        filterExpenseTypes();
    });
    
    // Cargar datos iniciales
    loadExpenseTypesCategories();
    loadExpenseTypes();
    
    // Variables de ordenamiento para tipos de gastos
    let expenseTypesSortField = 'name';
    let expenseTypesSortDirection = 'asc';
    
    // Funciones de ordenamiento para tipos de gastos
    function setupExpenseTypesTableSorting() {
        const table = document.getElementById('expenseTypesTable');
        if (!table) return;
        
        const sortableHeaders = table.querySelectorAll('th.sortable');
        sortableHeaders.forEach(header => {
            header.addEventListener('click', () => {
                const sortBy = header.getAttribute('data-sort');
                if (expenseTypesSortField === sortBy) {
                    expenseTypesSortDirection = expenseTypesSortDirection === 'asc' ? 'desc' : 'asc';
                } else {
                    expenseTypesSortField = sortBy;
                    expenseTypesSortDirection = 'asc';
                }
                
                sortExpenseTypesData();
                updateExpenseTypesSortIcons();
            });
        });
    }
    
    function sortExpenseTypesData() {
        typesFilteredData.sort((a, b) => {
            let valueA = a[expenseTypesSortField] || '';
            let valueB = b[expenseTypesSortField] || '';
            
            if (typeof valueA === 'string') {
                valueA = valueA.toLowerCase().trim();
                valueB = valueB.toLowerCase().trim();
            }
            
            // Para el campo status, convertir a boolean para ordenamiento
            if (expenseTypesSortField === 'status') {
                valueA = valueA === 'active' || valueA === 1;
                valueB = valueB === 'active' || valueB === 1;
            }
            
            if (valueA < valueB) return expenseTypesSortDirection === 'asc' ? -1 : 1;
            if (valueA > valueB) return expenseTypesSortDirection === 'asc' ? 1 : -1;
            return 0;
        });
        
        typesCurrentPage = 1;
        renderExpenseTypesTable();
        updateExpenseTypesPagination();
    }
    
    function updateExpenseTypesSortIcons() {
        const table = document.getElementById('expenseTypesTable');
        if (!table) return;
        
        const sortableHeaders = table.querySelectorAll('th.sortable');
        sortableHeaders.forEach(header => {
            const icon = header.querySelector('.sort-icon');
            const sortBy = header.getAttribute('data-sort');
            
            if (sortBy === expenseTypesSortField) {
                icon.className = expenseTypesSortDirection === 'asc' ? 'fas fa-sort-up sort-icon' : 'fas fa-sort-down sort-icon';
            } else {
                icon.className = 'fas fa-sort sort-icon';
            }
        });
    }
    
    // Configurar ordenamiento después de un breve delay
    setTimeout(() => {
        setupExpenseTypesTableSorting();
        updateExpenseTypesSortIcons();
    }, 100);
}

// SECCIÓN TIPOS DE TRABAJOS - Basado en job_types.php funcional
function loadJobTypesSection() {
    // Variables específicas para job types
    let jobTypesCurrentPage = 1;
    let jobTypesPageSize = 10;
    let jobTypesTotalCount = 0;
    let jobTypesSortField = 'created_at';
    let jobTypesSortDir = 'desc';
    let jobTypesStatusFilter = '';
    let editingJobTypeId = null;
    
    const content = `
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; gap: 16px;">
                <div style="display: flex; align-items: center; gap: 16px; flex: 1;">
                    <input
                        type="text"
                        id="jobTypesSearchInput"
                        class="form-input"
                        placeholder="Buscar tipo de trabajo..."
                        style="max-width: 300px;"
                        autocomplete="off"
                    >
                    <div class="status-filter-container" style="position: relative;">
                        <button type="button" class="btn" id="jobTypesStatusFilterBtn" onclick="toggleJobTypesStatusFilterDropdown()" style="background: var(--bg-secondary); border: 1px solid var(--border-color); display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-filter"></i>
                            <span id="jobTypesStatusFilterText">Todos los estados</span>
                            <i class="fas fa-chevron-down" style="font-size: 12px;"></i>
                        </button>
                        <div class="status-filter-dropdown" id="jobTypesStatusFilterDropdown" style="display: none; position: absolute; top: 100%; left: 0; background: white; border: 1px solid var(--border-color); border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); z-index: 1000; min-width: 180px; margin-top: 4px;">
                            <div class="filter-option" onclick="applyJobTypesStatusFilter('')" style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid var(--border-color);">
                                <i class="fas fa-list" style="width: 16px; margin-right: 8px;"></i>
                                Todos los estados
                            </div>
                            <div class="filter-option" onclick="applyJobTypesStatusFilter('active')" style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid var(--border-color);">
                                <i class="fas fa-check-circle" style="width: 16px; margin-right: 8px; color: var(--success-color);"></i>
                                Solo activos
                            </div>
                            <div class="filter-option" onclick="applyJobTypesStatusFilter('inactive')" style="padding: 8px 12px; cursor: pointer;">
                                <i class="fas fa-times-circle" style="width: 16px; margin-right: 8px; color: var(--danger-color);"></i>
                                Solo inactivos
                            </div>
                        </div>
                    </div>
                </div>
                <div>
                    <button type="button" class="btn btn-primary" onclick="openJobTypeModal()">
                        <i class="fas fa-plus"></i>
                        Nuevo Tipo
                    </button>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-briefcase"></i>
                    Tipos de Trabajo
                </h3>
                <p class="card-subtitle">Total: <span id="totalJobTypes">0</span> tipos registrados</p>
            </div>
            <div style="overflow-x: auto;">
                <table class="data-table sortable-table" id="jobTypesTable" style="min-width: 800px;">
                    <thead>
                        <tr>
                            <th class="sortable" data-sort="name">
                                Nombre
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th class="sortable" data-sort="pay_as_contractor">
                                Paga como Contratista
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th class="sortable" data-sort="pay_as_sub_contractor">
                                Paga como Subcontratista
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th class="sortable" data-sort="status" style="width: 100px; text-align: center;">
                                Estado
                                <i class="fas fa-sort sort-icon"></i>
                            </th>
                            <th style="width: 120px; text-align: center;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="jobTypesTableBody">
                        <!-- Las filas se llenarán dinámicamente -->
                    </tbody>
                </table>
                <div id="jobTypesTableFooter" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0 0 0;">
                    <div id="jobTypesPageSizeContainer"></div>
                    <div id="jobTypesPagination"></div>
                </div>
            </div>
        </div>

        <!-- Modal de confirmación para cambio de estado -->
        <div class="modal" id="confirmJobTypeStatusModal" style="display: none;">
            <div class="modal-overlay" onclick="closeModal('confirmJobTypeStatusModal')"></div>
            <div class="modal-content" style="max-width: 400px;">
                <div class="modal-header">
                    <h2>Confirmar cambio de estado</h2>
                    <button type="button" class="modal-close" onclick="closeModal('confirmJobTypeStatusModal')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <p id="confirmJobTypeStatusMessage">¿Está seguro de que desea cambiar el estado de este tipo de trabajo?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeModal('confirmJobTypeStatusModal')">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="confirmJobTypeStatusBtn">Confirmar</button>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('settingsContent').innerHTML = content;
    
    // Funciones internas para job types
    function renderJobTypesPageSizeSelector() {
        const container = document.getElementById('jobTypesPageSizeContainer');
        if (!container) return;
        
        container.innerHTML = '';
        const label = document.createElement('label');
        label.textContent = 'Mostrar:';
        label.style = 'margin-right: 4px; font-weight: 500; color: var(--text-secondary);';
        
        const selector = document.createElement('select');
        selector.id = 'jobTypesPageSizeSelector';
        selector.className = 'form-input';
        selector.style = 'width: auto; display: inline-block;';
        
        [5, 10, 20, 50, 100].forEach(size => {
            const opt = document.createElement('option');
            opt.value = size;
            opt.textContent = `${size} por página`;
            selector.appendChild(opt);
        });
        
        selector.value = jobTypesPageSize;
        selector.onchange = function() {
            jobTypesPageSize = parseInt(this.value);
            loadJobTypesData(1);
        };
        
        container.appendChild(label);
        container.appendChild(selector);
    }
    
    function loadJobTypesData(page = 1) {
        jobTypesCurrentPage = page;
        setJobTypesTableLoading(true);
        
        const offset = (page - 1) * jobTypesPageSize;
        let url = `api/job_type/JobTypeController.php?action=getAllJobTypes&limit=${jobTypesPageSize}&offset=${offset}&sort=${jobTypesSortField}&dir=${jobTypesSortDir}`;
        
        if (jobTypesStatusFilter) {
            url += `&status=${jobTypesStatusFilter}`;
        }
        
        fetch(url)
            .then(res => res.json())
            .then(data => {
                const types = data.data || data;
                jobTypesTotalCount = data.total || types.length;
                renderJobTypesTable(types);
                renderJobTypesPagination();
            })
            .catch(err => {
                console.error('Error loading job types:', err);
                document.getElementById('jobTypesTableBody').innerHTML = '<tr><td colspan="5" style="text-align: center; color: var(--danger-color);">Error al cargar tipos de trabajo</td></tr>';
            })
            .finally(() => setJobTypesTableLoading(false));
    }
    
    function setJobTypesTableLoading(loading) {
        const tbody = document.getElementById('jobTypesTableBody');
        if (loading) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" style="text-align: center; padding: 40px 0;">
                        <div class="loading-spinner">
                            <div class="spinner"></div>
                        </div>
                        <span style="display: block; margin-top: 8px; color: var(--text-secondary);">Cargando tipos...</span>
                    </td>
                </tr>
            `;
        }
    }
    
    function renderJobTypesTable(types) {
        const tbody = document.getElementById('jobTypesTableBody');
        tbody.innerHTML = '';
        
        if (!types.length) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: var(--text-secondary);">No hay tipos registrados</td></tr>';
            document.getElementById('totalJobTypes').textContent = '0';
            return;
        }
        
        document.getElementById('totalJobTypes').textContent = jobTypesTotalCount;
        
        types.forEach(type => {
            const tr = document.createElement('tr');
            const isActive = type.status === 'active' || type.status_numeric === 1;
            const statusText = isActive ? 'Activo' : 'Inactivo';
            const statusBadgeClass = isActive ? 'status-active' : 'status-inactive';
            
            tr.innerHTML = `
                <td>${escapeHtml(type.name)}</td>
                <td>$${parseFloat(type.pay_as_contractor).toLocaleString('es-MX', {minimumFractionDigits:2})}</td>
                <td>$${parseFloat(type.pay_as_sub_contractor).toLocaleString('es-MX', {minimumFractionDigits:2})}</td>
                <td style="text-align: center;">
                    <span class="status-badge ${statusBadgeClass}">
                        ${statusText}
                    </span>
                </td>
                <td style="text-align: center;">
                    <div style="display: flex; gap: 8px; justify-content: center;">
                        <button type="button" class="btn-action" onclick="toggleJobTypeStatusFromSettings('${type.id}', '${escapeHtml(type.name)}', '${type.status}')" title="${isActive ? 'Desactivar' : 'Activar'}">
                            <i class="fas ${isActive ? 'fa-toggle-on' : 'fa-toggle-off'}"></i>
                        </button>
                        <button type="button" class="btn-action" onclick="editJobType('${type.id}')" title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" class="btn-action btn-danger" onclick="deleteJobType('${type.id}')" title="Eliminar">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
        
        renderJobTypesPageSizeSelector();
    }
    
    function renderJobTypesPagination() {
        const container = document.getElementById('jobTypesPagination');
        if (!container) return;
        
        container.innerHTML = '';
        const totalPages = Math.ceil(jobTypesTotalCount / jobTypesPageSize);
        
        if (totalPages <= 1) {
            container.style.display = 'none';
            return;
        }
        
        container.style.display = 'flex';
        container.style.gap = '4px';
        
        for (let i = 1; i <= totalPages; i++) {
            const btn = document.createElement('button');
            btn.className = 'btn' + (i === jobTypesCurrentPage ? ' btn-primary' : '');
            btn.textContent = i;
            btn.onclick = () => loadJobTypesData(i);
            container.appendChild(btn);
        }
    }
    
    // Funciones globales para job types (deben estar en el scope global)
    window.openJobTypeModal = function() {
        // Generar el HTML del modal para crear un nuevo tipo de trabajo
        const modalBodyHTML = `
            <form id="jobTypeForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label" for="jobTypeName">Nombre *</label>
                        <input type="text" class="form-input" id="jobTypeName" name="jobTypeName" value="" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="payAsContractor">Paga como Contratista</label>
                        <input type="number" step="0.01" class="form-input" id="payAsContractor" name="payAsContractor" value="0.00">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="payAsSubContractor">Paga como Subcontratista</label>
                        <input type="number" step="0.01" class="form-input" id="payAsSubContractor" name="payAsSubContractor" value="0.00">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="jobTypeStatus">Estado</label>
                        <label class="switch">
                            <input type="checkbox" id="jobTypeStatus" name="jobTypeStatus" checked>
                            <span class="slider"></span>
                        </label>
                        <small class="form-text">Activo/Inactivo</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeModal('formModal')" style="background-color: var(--secondary-color); color: white;">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Crear Tipo
                    </button>
                </div>
            </form>
        `;
        
        // Establecer el título del modal
        const titleElement = document.getElementById('formModalTitle');
        if (titleElement) {
            titleElement.textContent = 'Crear Tipo de Trabajo';
        }
        
        // Establecer el contenido del modal
        const bodyElement = document.getElementById('formModalBody');
        if (bodyElement) {
            bodyElement.innerHTML = modalBodyHTML;
        }
        
        // Abrir el modal
        openModal('formModal');
        
        // Agregar event listener para el formulario
        const form = document.getElementById('jobTypeForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Recopilar datos del formulario
                const formData = {
                    name: document.getElementById('jobTypeName').value,
                    pay_as_contractor: document.getElementById('payAsContractor').value,
                    pay_as_sub_contractor: document.getElementById('payAsSubContractor').value,
                    status: document.getElementById('jobTypeStatus').checked ? '1' : '0'
                };
                
                // Enviar solicitud para crear el tipo
                fetch('api/job_type/JobTypeController.php?action=createJobType', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(formData)
                })
                .then(response => response.json())
                .then(result => {
                    closeModal('formModal');
                    if (result && result.error) {
                        showToast('Error al guardar el tipo: ' + result.error, 'error');
                    } else {
                        showToast('Tipo creado con éxito', 'success');
                        loadJobTypesData(jobTypesCurrentPage);
                    }
                })
                .catch(error => {
                    console.error('Error saving job type:', error);
                    showToast('Error al guardar el tipo', 'error');
                });
            });
        }
    };
    
    window.editJobType = function(id) {
        editingJobTypeId = id;
        
        // Cargar datos del tipo de trabajo
        fetch(`api/job_type/JobTypeController.php?action=getJobTypeById&id=${id}`)
            .then(response => response.json())
            .then(jobTypeData => {
                if (!jobTypeData || jobTypeData.error) {
                    showToast('Error al cargar el tipo de trabajo', 'error');
                    return;
                }
                
                // Generar el HTML del modal para editar tipo de trabajo
                const modalBodyHTML = `
                    <form id="jobTypeEditForm">
                        <div class="modal-body">
                            <input type="hidden" id="jobTypeId" name="jobTypeId" value="${jobTypeData.id}">
                            
                            <div class="form-group">
                                <label class="form-label" for="jobTypeName">Nombre *</label>
                                <input type="text" class="form-input" id="jobTypeName" name="jobTypeName" value="${jobTypeData.name || ''}" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="payAsContractor">Paga como Contratista</label>
                                <input type="number" step="0.01" class="form-input" id="payAsContractor" name="payAsContractor" value="${jobTypeData.pay_as_contractor || '0.00'}" min="0">
                                <small class="form-text">Monto que se paga cuando actúa como contratista</small>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="payAsSubContractor">Paga como Subcontratista</label>
                                <input type="number" step="0.01" class="form-input" id="payAsSubContractor" name="payAsSubContractor" value="${jobTypeData.pay_as_sub_contractor || '0.00'}" min="0">
                                <small class="form-text">Monto que se paga cuando actúa como subcontratista</small>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="jobTypeStatus">Estado</label>
                                <label class="switch">
                                    <input type="checkbox" id="jobTypeStatus" name="jobTypeStatus" ${jobTypeData.status === 'active' ? 'checked' : ''}>
                                    <span class="slider"></span>
                                </label>
                                <small class="form-text">Activo/Inactivo</small>
                            </div>
                        </div>
                        
                        <div class="modal-footer">
                            <button type="button" class="btn" onclick="closeModal('formModal')" style="background-color: var(--secondary-color); color: white;">
                                Cancelar
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i>
                                Actualizar Tipo
                            </button>
                        </div>
                    </form>
                `;
                
                // Establecer el título del modal
                const titleElement = document.getElementById('formModalTitle');
                if (titleElement) {
                    titleElement.textContent = 'Editar Tipo de Trabajo';
                }
                
                // Establecer el contenido del modal
                const bodyElement = document.getElementById('formModalBody');
                if (bodyElement) {
                    bodyElement.innerHTML = modalBodyHTML;
                }
                
                // Abrir el modal
                openModal('formModal');
                
                // Agregar event listener para el formulario de edición
                const form = document.getElementById('jobTypeEditForm');
                if (form) {
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        
                        // Recopilar datos del formulario
                        const formData = {
                            name: document.getElementById('jobTypeName').value.trim(),
                            pay_as_contractor: parseFloat(document.getElementById('payAsContractor').value) || 0,
                            pay_as_sub_contractor: parseFloat(document.getElementById('payAsSubContractor').value) || 0,
                            status: document.getElementById('jobTypeStatus').checked ? '1' : '0'
                        };
                        
                        // Validar datos
                        if (!formData.name) {
                            showToast('El nombre del tipo de trabajo es obligatorio', 'error');
                            return;
                        }
                        
                        // Enviar solicitud para actualizar el tipo
                        fetch(`api/job_type/JobTypeController.php?action=updateJobType&id=${editingJobTypeId}`, {
                            method: 'PUT',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(formData)
                        })
                        .then(response => response.json())
                        .then(result => {
                            closeModal('formModal');
                            if (result && result.error) {
                                showToast('Error al actualizar el tipo: ' + result.error, 'error');
                            } else {
                                showToast('Tipo actualizado con éxito', 'success');
                                loadJobTypesData(jobTypesCurrentPage);
                            }
                            editingJobTypeId = null;
                        })
                        .catch(error => {
                            console.error('Error updating job type:', error);
                            showToast('Error al actualizar el tipo', 'error');
                            closeModal('formModal');
                            editingJobTypeId = null;
                        });
                    });
                }
            })
            .catch(error => {
                console.error('Error loading job type:', error);
                showToast('Error al cargar el tipo de trabajo', 'error');
                editingJobTypeId = null;
            });
    };
    
    window.deleteJobType = function(id) {
        // Usar el modal de confirmación existente
        document.getElementById('confirmDeleteMessage').textContent = '¿Está seguro de que desea eliminar este tipo de trabajo?';
        openModal('confirmDeleteModal');
        
        // Configurar el botón de confirmación
        document.getElementById('confirmDeleteBtn').onclick = function() {
            fetch(`api/job_type/JobTypeController.php?action=deleteJobType&id=${id}`, { 
                method: 'DELETE' 
            })
            .then(res => res.json())
            .then(result => {
                closeModal('confirmDeleteModal');
                if (result && result.error) {
                    showToast('No se puede eliminar el tipo: ' + result.error, 'error');
                } else {
                    showToast('Tipo eliminado con éxito', 'success');
                    // Verificar si necesitamos ir a página anterior
                    const tbody = document.getElementById('jobTypesTableBody');
                    const currentRows = tbody.querySelectorAll('tr').length;
                    if (currentRows === 1 && jobTypesCurrentPage > 1) {
                        loadJobTypesData(jobTypesCurrentPage - 1);
                    } else {
                        loadJobTypesData(jobTypesCurrentPage);
                    }
                }
            })
            .catch(err => {
                console.error('Error deleting job type:', err);
                showToast('Error al eliminar el tipo', 'error');
                closeModal('confirmDeleteModal');
            });
        };
    };
    
    window.sortJobTypes = function(field) {
        if (jobTypesSortField === field) {
            jobTypesSortDir = jobTypesSortDir === 'asc' ? 'desc' : 'asc';
        } else {
            jobTypesSortField = field;
            jobTypesSortDir = 'asc';
        }
        
        // Actualizar iconos de ordenamiento
        document.querySelectorAll('#jobTypesTable th i[id^="sortIcon"]').forEach(icon => {
            icon.className = 'fas fa-sort';
        });
        
        const icon = document.getElementById(`sortIcon${field.charAt(0).toUpperCase() + field.slice(1)}`);
        if (icon) {
            icon.className = `fas fa-sort-${jobTypesSortDir === 'asc' ? 'up' : 'down'}`;
        }
        
        loadJobTypesData(1);
    };
    
    // Búsqueda local
    document.getElementById('jobTypesSearchInput').addEventListener('input', function() {
        const search = this.value.trim().toLowerCase();
        const rows = document.querySelectorAll('#jobTypesTableBody tr');
        let count = 0;
        
        rows.forEach(row => {
            // Verificar que no sea la fila de loading o de "no hay datos"
            if (row.children.length < 4) {
                return;
            }
            
            const name = row.children[0]?.textContent.toLowerCase() || '';
            if (name.includes(search)) {
                row.style.display = '';
                count++;
            } else {
                row.style.display = 'none';
            }
        });
        
        // Solo actualizar el contador si hay filas reales
        if (document.querySelectorAll('#jobTypesTableBody tr').length > 0 && 
            !document.querySelector('#jobTypesTableBody tr td[colspan]')) {
            document.getElementById('totalJobTypes').textContent = count;
        }
    });
    
    // Funciones para filtro de estado
    window.toggleJobTypesStatusFilterDropdown = function() {
        const dropdown = document.getElementById('jobTypesStatusFilterDropdown');
        if (dropdown) {
            dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
        }
    };

    window.applyJobTypesStatusFilter = function(status) {
        jobTypesStatusFilter = status;
        
        // Actualizar texto del botón
        const filterText = document.getElementById('jobTypesStatusFilterText');
        if (filterText) {
            switch(status) {
                case 'active':
                    filterText.textContent = 'Solo activos';
                    break;
                case 'inactive':
                    filterText.textContent = 'Solo inactivos';
                    break;
                default:
                    filterText.textContent = 'Todos los estados';
            }
        }
        
        // Cerrar dropdown
        const dropdown = document.getElementById('jobTypesStatusFilterDropdown');
        if (dropdown) {
            dropdown.style.display = 'none';
        }
        
        // Recargar datos
        loadJobTypesData(1);
    };

    // Función para toggle de estado
    window.toggleJobTypeStatusFromSettings = function(jobTypeId, jobTypeName, currentStatus) {
        const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
        const actionText = newStatus === 'active' ? 'activar' : 'desactivar';
        
        // Configurar modal de confirmación
        const message = document.getElementById('confirmJobTypeStatusMessage');
        const confirmBtn = document.getElementById('confirmJobTypeStatusBtn');
        
        if (message) {
            message.textContent = `¿Está seguro de que desea ${actionText} el tipo de trabajo "${jobTypeName}"?`;
        }
        
        if (confirmBtn) {
            confirmBtn.onclick = async function() {
                try {
                    const response = await fetch(`api/job_type/JobTypeController.php?action=toggleStatus&id=${jobTypeId}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        }
                    });
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        showToast(result.message, 'success');
                        closeModal('confirmJobTypeStatusModal');
                        loadJobTypesData(jobTypesCurrentPage);
                    } else {
                        showToast(result.error || 'Error al cambiar el estado', 'error');
                    }
                    
                } catch (error) {
                    console.error('Error toggling job type status:', error);
                    showToast('Error al cambiar el estado: ' + error.message, 'error');
                }
            };
        }
        
        openModal('confirmJobTypeStatusModal');
    };

    // Cerrar dropdown al hacer clic fuera
    document.addEventListener('click', function(event) {
        const dropdown = document.getElementById('jobTypesStatusFilterDropdown');
        const button = document.getElementById('jobTypesStatusFilterBtn');
        
        if (dropdown && button && !button.contains(event.target) && !dropdown.contains(event.target)) {
            dropdown.style.display = 'none';
        }
    });

    // Cargar datos iniciales
    loadJobTypesData(1);
    
    // Funciones de ordenamiento para tipos de trabajo
    function setupJobTypesTableSorting() {
        const table = document.getElementById('jobTypesTable');
        if (!table) return;
        
        const sortableHeaders = table.querySelectorAll('th.sortable');
        sortableHeaders.forEach(header => {
            header.addEventListener('click', () => {
                const sortBy = header.getAttribute('data-sort');
                if (jobTypesSortField === sortBy) {
                    jobTypesSortDir = jobTypesSortDir === 'asc' ? 'desc' : 'asc';
                } else {
                    jobTypesSortField = sortBy;
                    jobTypesSortDir = 'asc';
                }
                
                loadJobTypesData(1);
                updateJobTypesSortIcons();
            });
        });
    }
    
    function updateJobTypesSortIcons() {
        const table = document.getElementById('jobTypesTable');
        if (!table) return;
        
        const sortableHeaders = table.querySelectorAll('th.sortable');
        sortableHeaders.forEach(header => {
            const icon = header.querySelector('.sort-icon');
            const sortBy = header.getAttribute('data-sort');
            
            if (sortBy === jobTypesSortField) {
                icon.className = jobTypesSortDir === 'asc' ? 'fas fa-sort-up sort-icon' : 'fas fa-sort-down sort-icon';
            } else {
                icon.className = 'fas fa-sort sort-icon';
            }
        });
    }
    
    // Configurar ordenamiento después de un breve delay
    setTimeout(() => {
        setupJobTypesTableSorting();
        updateJobTypesSortIcons();
    }, 100);
}

// --- Configuración ---
const USERS_API_URL = 'api/user/UserController.php';
let currentEditingUserId = null;
let usersCurrentPage = 1;
let usersPageSize = 10;
let usersTotalCount = 0;

// --- Funciones de utilidad ---
function formatDate(dateString) {
    if (!dateString) return 'Nunca';
    const date = new Date(dateString);
    return date.toLocaleDateString('es-ES', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function formatUserRole(role) {
    const roles = {
        'admin': 'Administrador',
        'user': 'Usuario'
    };
    return roles[role] || role;
}

function getUserStatusBadge(active) {
    if (active) {
        return `<span style="color: var(--success-color); font-weight: 500;">
            <i class="fas fa-circle" style="font-size: 8px; margin-right: 6px;"></i>
            Activo
        </span>`;
    } else {
        return `<span style="color: var(--danger-color); font-weight: 500;">
            <i class="fas fa-circle" style="font-size: 8px; margin-right: 6px;"></i>
            Inactivo
        </span>`;
    }
}

// --- SECCIÓN USUARIOS COMPLETA ---
function loadUsersSection() {
    const content = `
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h3 class="card-title">
                        <i class="fas fa-users"></i>
                        Gestión de Usuarios
                    </h3>
                    <p class="card-subtitle">Administrar usuarios del sistema</p>
                </div>
                <button type="button" class="btn btn-primary" onclick="openUserModal()">
                    <i class="fas fa-plus"></i>
                    Nuevo Usuario
                </button>
            </div>
            
            <div id="usersTableContainer">
                <!-- La tabla se cargará aquí -->
            </div>
        </div>
        
        <!-- Stats de usuarios -->
        <div class="card" style="margin-top: 24px;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-chart-bar"></i>
                    Estadísticas del Sistema
                </h3>
            </div>
            <div style="padding: 20px 0;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;" id="userStats">
                    <!-- Las estadísticas se cargarán aquí -->
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('settingsContent').innerHTML = content;
    
    // Cargar datos
    loadUsersTable();
    loadUserStats();
}

function loadUsersTable() {
    const container = document.getElementById('usersTableContainer');
    if (!container) return;
    
    container.innerHTML = `
        <div style="text-align: center; padding: 40px;">
            <div class="spinner"></div>
            <p style="margin-top: 12px; color: var(--text-secondary);">Cargando usuarios...</p>
        </div>
    `;
    
    fetch(`${USERS_API_URL}?action=getAllUsers&limit=${usersPageSize}&offset=${(usersCurrentPage-1)*usersPageSize}`)
        .then(res => res.json())
        .then(data => {
            if (data.error) {
                container.innerHTML = `<div class="error-message">${data.error}</div>`;
                return;
            }
            
            const users = data.data || [];
            usersTotalCount = data.total || 0;
            
            renderUsersTable(users);
            renderUsersPagination();
        })
        .catch(err => {
            console.error('Error loading users:', err);
            container.innerHTML = '<div class="error-message">Error al cargar usuarios</div>';
        });
}

function renderUsersTable(users) {
    const container = document.getElementById('usersTableContainer');
    
    let tableHTML = `
        <div style="overflow-x: auto;">
            <table class="data-table" style="min-width: 700px;">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Último Acceso</th>
                        <th style="width: 160px; text-align: center;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    if (users.length === 0) {
        tableHTML += `
            <tr>
                <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-secondary);">
                    No hay usuarios registrados
                </td>
            </tr>
        `;
    } else {
        users.forEach(user => {
            tableHTML += `
                <tr>
                    <td>
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div style="width: 32px; height: 32px; background: var(--primary-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                                ${user.username.charAt(0).toUpperCase()}
                            </div>
                            <div>
                                <div style="font-weight: 500;">${user.username}</div>
                                <div style="font-size: 12px; color: var(--text-secondary);">ID: ${user.id}</div>
                            </div>
                        </div>
                    </td>
                    <td>${user.email}</td>
                    <td>
                        <span class="role-badge role-${user.role}">
                            ${formatUserRole(user.role)}
                        </span>
                    </td>
                    <td>${getUserStatusBadge(user.active)}</td>
                    <td style="font-size: 13px;">${formatDate(user.last_login)}</td>
                    <td style="text-align: center;">
                        <div style="display: flex; gap: 8px; justify-content: center;">
                            <button type="button" class="btn-action" title="Editar" onclick="editUser(${user.id})">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn-action ${user.active ? 'btn-warning' : 'btn-success'}" 
                                    title="${user.active ? 'Desactivar' : 'Activar'}" 
                                    onclick="toggleUserStatus(${user.id})">
                                <i class="fas fa-${user.active ? 'ban' : 'check'}"></i>
                            </button>
                            <button type="button" class="btn-action" title="Cambiar contraseña" onclick="changeUserPassword(${user.id})">
                                <i class="fas fa-key"></i>
                            </button>
                            <button type="button" class="btn-action btn-danger" title="Eliminar" onclick="deleteUser(${user.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });
    }
    
    tableHTML += `
                </tbody>
            </table>
        </div>
        <div id="usersPaginationContainer" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0 0 0;">
            <div>
                <span style="color: var(--text-secondary); font-size: 14px;">
                    Mostrando ${Math.min((usersCurrentPage-1)*usersPageSize + 1, usersTotalCount)} - ${Math.min(usersCurrentPage*usersPageSize, usersTotalCount)} de ${usersTotalCount} usuarios
                </span>
            </div>
            <div id="usersPagination"></div>
        </div>
    `;
    
    container.innerHTML = tableHTML;
}

function renderUsersPagination() {
    const container = document.getElementById('usersPagination');
    if (!container) return;
    
    const totalPages = Math.ceil(usersTotalCount / usersPageSize);
    if (totalPages <= 1) {
        container.style.display = 'none';
        return;
    }
    
    container.style.display = 'flex';
    container.innerHTML = '';
    
    // Botón anterior
    if (usersCurrentPage > 1) {
        const prevBtn = document.createElement('button');
        prevBtn.className = 'btn';
        prevBtn.innerHTML = '<i class="fas fa-chevron-left"></i>';
        prevBtn.onclick = () => loadUsersPage(usersCurrentPage - 1);
        container.appendChild(prevBtn);
    }
    
    // Números de página
    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= usersCurrentPage - 1 && i <= usersCurrentPage + 1)) {
            const btn = document.createElement('button');
            btn.className = 'btn' + (i === usersCurrentPage ? ' btn-primary' : '');
            btn.textContent = i;
            btn.onclick = () => loadUsersPage(i);
            container.appendChild(btn);
        } else if (i === usersCurrentPage - 2 || i === usersCurrentPage + 2) {
            const span = document.createElement('span');
            span.textContent = '...';
            span.style.padding = '0 8px';
            container.appendChild(span);
        }
    }
    
    // Botón siguiente
    if (usersCurrentPage < totalPages) {
        const nextBtn = document.createElement('button');
        nextBtn.className = 'btn';
        nextBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
        nextBtn.onclick = () => loadUsersPage(usersCurrentPage + 1);
        container.appendChild(nextBtn);
    }
}

function loadUsersPage(page) {
    usersCurrentPage = page;
    loadUsersTable();
}

function loadUserStats() {
    fetch(`${USERS_API_URL}?action=getAllUsers`)
        .then(res => res.json())
        .then(data => {
            const users = data.data || [];
            const total = users.length;
            const active = users.filter(u => u.active).length;
            const admins = users.filter(u => u.role === 'admin').length;
            const recentLogin = users.filter(u => {
                if (!u.last_login) return false;
                const loginDate = new Date(u.last_login);
                const weekAgo = new Date();
                weekAgo.setDate(weekAgo.getDate() - 7);
                return loginDate > weekAgo;
            }).length;
            
            const statsContainer = document.getElementById('userStats');
            if (statsContainer) {
                statsContainer.innerHTML = `
                    <div style="background: var(--bg-secondary); padding: 16px; border-radius: 8px;">
                        <h4 style="margin: 0 0 8px 0; color: var(--primary-color);">Total Usuarios</h4>
                        <p style="font-size: 24px; font-weight: bold; margin: 0;">${total}</p>
                        <p style="font-size: 12px; color: var(--text-secondary); margin: 4px 0 0 0;">Usuarios registrados</p>
                    </div>
                    <div style="background: var(--bg-secondary); padding: 16px; border-radius: 8px;">
                        <h4 style="margin: 0 0 8px 0; color: var(--success-color);">Usuarios Activos</h4>
                        <p style="font-size: 24px; font-weight: bold; margin: 0;">${active}</p>
                        <p style="font-size: 12px; color: var(--text-secondary); margin: 4px 0 0 0;">${((active/total)*100).toFixed(1)}% del total</p>
                    </div>
                    <div style="background: var(--bg-secondary); padding: 16px; border-radius: 8px;">
                        <h4 style="margin: 0 0 8px 0; color: var(--warning-color);">Administradores</h4>
                        <p style="font-size: 24px; font-weight: bold; margin: 0;">${admins}</p>
                        <p style="font-size: 12px; color: var(--text-secondary); margin: 4px 0 0 0;">Con privilegios de admin</p>
                    </div>
                    <div style="background: var(--bg-secondary); padding: 16px; border-radius: 8px;">
                        <h4 style="margin: 0 0 8px 0; color: var(--info-color);">Activos Recientes</h4>
                        <p style="font-size: 24px; font-weight: bold; margin: 0;">${recentLogin}</p>
                        <p style="font-size: 12px; color: var(--text-secondary); margin: 4px 0 0 0;">Login en últimos 7 días</p>
                    </div>
                `;
            }
        })
        .catch(err => console.error('Error loading user stats:', err));
}

function openUserModal(userId = null) {
    currentEditingUserId = userId;
    const isEdit = userId !== null;
    
    const formContent = `
        <form id="userForm">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="userUsername">Nombre de Usuario *</label>
                    <input type="text" class="form-input" id="userUsername" name="username" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="userEmail">Email *</label>
                    <input type="email" class="form-input" id="userEmail" name="email" required>
                </div>
                
                ${!isEdit ? `
                <div class="form-group">
                    <label class="form-label" for="userPassword">Contraseña *</label>
                    <input type="password" class="form-input" id="userPassword" name="password" required minlength="6">
                    <small class="form-text">Mínimo 6 caracteres</small>
                </div>
                ` : ''}
                
                <div class="form-group">
                    <label class="form-label" for="userRole">Rol *</label>
                    <select class="form-input" id="userRole" name="role" required>
                        <option value="user">Usuario</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px;">
                        <input type="checkbox" id="userActive" name="active" checked>
                        <span>Usuario activo</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('formModal')">Cancelar</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    ${isEdit ? 'Actualizar' : 'Crear'} Usuario
                </button>
            </div>
        </form>
    `;
    
    document.getElementById('formModalTitle').textContent = isEdit ? 'Editar Usuario' : 'Nuevo Usuario';
    document.getElementById('formModalBody').innerHTML = formContent;
    
    if (isEdit) {
        loadUserForEdit(userId);
    }
    
    // Event listener para el formulario
    document.getElementById('userForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveUser();
    });
    
    openModal('formModal');
}

function loadUserForEdit(userId) {
    fetch(`${USERS_API_URL}?action=getUserById&id=${userId}`)
        .then(res => res.json())
        .then(user => {
            if (user.error) {
                showToast(user.error, 'error');
                closeModal('formModal');
                return;
            }
            
            document.getElementById('userUsername').value = user.username;
            document.getElementById('userEmail').value = user.email;
            document.getElementById('userRole').value = user.role;
            document.getElementById('userActive').checked = user.active;
        })
        .catch(err => {
            console.error('Error loading user:', err);
            showToast('Error al cargar usuario', 'error');
        });
}

function saveUser() {
    const formData = new FormData(document.getElementById('userForm'));
    const data = {
        username: formData.get('username'),
        email: formData.get('email'),
        role: formData.get('role'),
        active: formData.has('active')
    };
    
    if (!currentEditingUserId) {
        data.password = formData.get('password');
    }
    
    const url = currentEditingUserId 
        ? `${USERS_API_URL}?action=updateUser&id=${currentEditingUserId}`
        : `${USERS_API_URL}?action=createUser`;
    
    const method = currentEditingUserId ? 'PUT' : 'POST';
    
    fetch(url, {
        method: method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(result => {
        if (result.error) {
            showToast(result.error, 'error');
            return;
        }
        
        showToast(result.message, 'success');
        closeModal('formModal');
        loadUsersTable();
        loadUserStats();
    })
    .catch(err => {
        console.error('Error saving user:', err);
        showToast('Error al guardar usuario', 'error');
    });
}

function editUser(userId) {
    openUserModal(userId);
}

function deleteUser(userId) {
    document.getElementById('confirmDeleteMessage').textContent = '¿Está seguro de que desea eliminar este usuario? Esta acción no se puede deshacer.';
    
    document.getElementById('confirmDeleteBtn').onclick = function() {
        fetch(`${USERS_API_URL}?action=deleteUser&id=${userId}`, {
            method: 'DELETE'
        })
        .then(res => res.json())
        .then(result => {
            closeModal('confirmDeleteModal');
            
            if (result.error) {
                showToast(result.error, 'error');
                return;
            }
            
            showToast(result.message, 'success');
            loadUsersTable();
            loadUserStats();
        })
        .catch(err => {
            console.error('Error deleting user:', err);
            showToast('Error al eliminar usuario', 'error');
        });
    };
    
    openModal('confirmDeleteModal');
}

function toggleUserStatus(userId) {
    fetch(`${USERS_API_URL}?action=toggleUserStatus&id=${userId}`, {
        method: 'PUT'
    })
    .then(res => res.json())
    .then(result => {
        if (result.error) {
            showToast(result.error, 'error');
            return;
        }
        
        showToast(result.message, 'success');
        loadUsersTable();
        loadUserStats();
    })
    .catch(err => {
        console.error('Error toggling user status:', err);
        showToast('Error al cambiar estado del usuario', 'error');
    });
}

function changeUserPassword(userId) {
    const formContent = `
        <form id="passwordForm">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="newPassword">Nueva Contraseña *</label>
                    <input type="password" class="form-input" id="newPassword" name="new_password" required minlength="6">
                    <small class="form-text">Mínimo 6 caracteres</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="confirmPassword">Confirmar Contraseña *</label>
                    <input type="password" class="form-input" id="confirmPassword" name="confirm_password" required minlength="6">
                </div>
                
                <div style="background: var(--bg-warning); padding: 12px; border-radius: 6px; border-left: 4px solid var(--warning-color);">
                    <p style="font-size: 13px; margin: 0;"><strong>Atención:</strong> Esta acción cambiará la contraseña del usuario inmediatamente.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('formModal')">Cancelar</button>
                <button type="submit" class="btn btn-warning">
                    <i class="fas fa-key"></i>
                    Cambiar Contraseña
                </button>
            </div>
        </form>
    `;
    
    document.getElementById('formModalTitle').textContent = 'Cambiar Contraseña';
    document.getElementById('formModalBody').innerHTML = formContent;
    
    document.getElementById('passwordForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const newPassword = document.getElementById('newPassword').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        
        if (newPassword !== confirmPassword) {
            showToast('Las contraseñas no coinciden', 'error');
            return;
        }
        
        fetch(`${USERS_API_URL}?action=updatePassword&id=${userId}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ new_password: newPassword })
        })
        .then(res => res.json())
        .then(result => {
            if (result.error) {
                showToast(result.error, 'error');
                return;
            }
            
            showToast(result.message, 'success');
            closeModal('formModal');
        })
        .catch(err => {
            console.error('Error updating password:', err);
            showToast('Error al cambiar contraseña', 'error');
        });
    });
    
    openModal('formModal');
}

// --- Estilos adicionales que se inyectan dinámicamente ---
if (!document.getElementById('dynamicSettingsStyles')) {
    const style = document.createElement('style');
    style.id = 'dynamicSettingsStyles';
    style.textContent = `
        .role-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .role-admin {
            background-color: rgb(239 68 68 / 0.1);
            color: var(--danger-color);
        }
        
        .role-user {
            background-color: rgb(34 197 94 / 0.1);
            color: var(--success-color);
        }
        
        .error-message {
            text-align: center;
            padding: 40px;
            color: var(--danger-color);
            background: rgb(239 68 68 / 0.1);
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .form-text {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 4px;
            display: block;
        }
        
        .btn-action {
            padding: 6px 8px;
            border: none;
            border-radius: 4px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 12px;
        }
        
        .btn-action:hover {
            background: var(--bg-primary);
        }
        
        .btn-action.btn-danger {
            background: rgb(239 68 68 / 0.1);
            color: var(--danger-color);
        }
        
        .btn-action.btn-danger:hover {
            background: var(--danger-color);
            color: white;
        }
        
        .btn-action.btn-warning {
            background: rgb(245 158 11 / 0.1);
            color: var(--warning-color);
        }
        
        .btn-action.btn-warning:hover {
            background: var(--warning-color);
            color: white;
        }
        
        .btn-action.btn-success {
            background: rgb(34 197 94 / 0.1);
            color: var(--success-color);
        }
        
        .btn-action.btn-success:hover {
            background: var(--success-color);
            color: white;
        }
        
        .pagination-btn {
            padding: 6px 10px;
            border: 1px solid #D1D5DB;
            background: white;
            color: #374151;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s ease;
        }
        
        .pagination-btn:hover {
            background: #F3F4F6;
            border-color: #9CA3AF;
        }
        
        .pagination-btn.active {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }
        
        .text-success {
            color: #10b981 !important;
        }
        
        .text-danger {
            color: #ef4444 !important;
        }
    `;
    document.head.appendChild(style);
} 