// SECCIÓN TIPOS DE PAGO
function loadPaymentTypesSection() {
    const content = `
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-credit-card"></i>
                    Tipos de Pagos
                </h3>
                <p class="card-subtitle">Gestionar tipos de pagos del sistema</p>
            </div>
            <div style="padding: 40px; text-align: center; color: var(--text-secondary);">
                <i class="fas fa-tools" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;"></i>
                <h3>Función en desarrollo</h3>
                <p>Esta sección estará disponible próximamente</p>
            </div>
        </div>
    `;
    
    document.getElementById('settingsContent').innerHTML = content;
}

function openPaymentTypeModal(id = null) {
    const isEdit = id !== null;
    const title = isEdit ? 'Editar Tipo de Pago' : 'Nuevo Tipo de Pago';
    
    const modalContent = `
        <form id="paymentTypeForm">
            <div class="modal-body">
                <input type="hidden" id="paymentTypeId" value="${id || ''}">
                
                <div class="form-group">
                    <label class="form-label" for="paymentTypeName">Nombre *</label>
                    <input type="text" class="form-input" id="paymentTypeName" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="paymentTypeDescription">Descripción</label>
                    <textarea class="form-input" id="paymentTypeDescription" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="paymentTypeBankAccount">Cuenta Bancaria *</label>
                    <select class="form-input" id="paymentTypeBankAccount" required>
                        <option value="">Seleccionar cuenta...</option>
                        <option value="1">Caja General</option>
                        <option value="2">Cuenta Bancaria Principal</option>
                        <option value="3">Cuenta de Ahorros</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('formModal')">Cancelar</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Guardar
                </button>
            </div>
        </form>
    `;
    
    document.getElementById('formModalTitle').textContent = title;
    document.getElementById('formModalBody').innerHTML = modalContent;
    
    // Event listener para el formulario
    document.getElementById('paymentTypeForm').addEventListener('submit', function(e) {
        e.preventDefault();
        showToast('Tipo de pago guardado exitosamente', 'success');
        closeModal('formModal');
        loadPaymentTypesSection(); // Recargar la tabla
    });
    
    openModal('formModal');
}

function editPaymentType(id) {
    // Simular datos para edición
    const mockData = {
        1: { name: 'Efectivo', description: 'Pagos en efectivo', bankAccount: '1' },
        2: { name: 'Tarjeta de Débito', description: 'Pagos con tarjeta de débito', bankAccount: '2' },
        3: { name: 'Transferencia', description: 'Transferencias bancarias', bankAccount: '2' }
    };
    
    openPaymentTypeModal(id);
    
    // Llenar formulario con datos existentes
    setTimeout(() => {
        const data = mockData[id];
        if (data) {
            document.getElementById('paymentTypeName').value = data.name;
            document.getElementById('paymentTypeDescription').value = data.description;
            document.getElementById('paymentTypeBankAccount').value = data.bankAccount;
        }
    }, 100);
}

function deletePaymentType(id) {
    document.getElementById('confirmDeleteMessage').textContent = '¿Está seguro de que desea eliminar este tipo de pago?';
    document.getElementById('confirmDeleteBtn').onclick = function() {
        showToast('Tipo de pago eliminado exitosamente', 'success');
        closeModal('confirmDeleteModal');
        loadPaymentTypesSection();
    };
    openModal('confirmDeleteModal');
}

// SECCIÓN CATEGORÍAS DE GASTOS
function loadExpenseCategoriesSection() {
    const content = `
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-tags"></i>
                    Categorías de Gastos
                </h3>
                <p class="card-subtitle">Gestionar categorías de gastos</p>
            </div>
            <div style="padding: 40px; text-align: center; color: var(--text-secondary);">
                <i class="fas fa-tools" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;"></i>
                <h3>Función en desarrollo</h3>
                <p>Esta sección estará disponible próximamente</p>
            </div>
        </div>
    `;
    
    document.getElementById('settingsContent').innerHTML = content;
}

function openCategoryModal(id = null) {
    const isEdit = id !== null;
    const title = isEdit ? 'Editar Categoría' : 'Nueva Categoría';
    
    const modalContent = `
        <form id="categoryForm">
            <div class="modal-body">
                <input type="hidden" id="categoryId" value="${id || ''}">
                
                <div class="form-group">
                    <label class="form-label" for="categoryName">Nombre *</label>
                    <input type="text" class="form-input" id="categoryName" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="categoryDescription">Descripción</label>
                    <textarea class="form-input" id="categoryDescription" rows="3" placeholder="Descripción de la categoría"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <input type="checkbox" id="categoryIsActive" checked>
                        Categoría activa
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('formModal')">Cancelar</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Guardar Categoría
                </button>
            </div>
        </form>
    `;
    
    document.getElementById('formModalTitle').textContent = title;
    document.getElementById('formModalBody').innerHTML = modalContent;
    
    document.getElementById('categoryForm').addEventListener('submit', function(e) {
        e.preventDefault();
        showToast('Categoría guardada exitosamente', 'success');
        closeModal('formModal');
        loadExpenseCategoriesSection();
    });
    
    openModal('formModal');
}

function editCategory(id) {
    const mockData = {
        1: { name: 'Transporte', description: 'Gastos relacionados con transporte', active: true },
        2: { name: 'Alimentación', description: 'Gastos en comida y bebidas', active: true },
        3: { name: 'Oficina', description: 'Gastos de oficina y suministros', active: true },
        4: { name: 'Servicios', description: 'Servicios públicos y profesionales', active: true },
        5: { name: 'Marketing', description: 'Gastos en publicidad y marketing', active: true }
    };
    
    openCategoryModal(id);
    
    setTimeout(() => {
        const data = mockData[id];
        if (data) {
            document.getElementById('categoryName').value = data.name;
            document.getElementById('categoryDescription').value = data.description;
            document.getElementById('categoryIsActive').checked = data.active;
        }
    }, 100);
}

function deleteCategory(id) {
    document.getElementById('confirmDeleteMessage').textContent = '¿Está seguro de que desea eliminar esta categoría?';
    document.getElementById('confirmDeleteBtn').onclick = function() {
        showToast('Categoría eliminada exitosamente', 'success');
        closeModal('confirmDeleteModal');
        loadExpenseCategoriesSection();
    };
    openModal('confirmDeleteModal');
}

// SECCIÓN TIPOS DE GASTOS
function loadExpenseTypesSection() {
    const content = `
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-list"></i>
                    Tipos de Gastos
                </h3>
                <p class="card-subtitle">Gestionar tipos de gastos</p>
            </div>
            <div style="padding: 40px; text-align: center; color: var(--text-secondary);">
                <i class="fas fa-tools" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;"></i>
                <h3>Función en desarrollo</h3>
                <p>Esta sección estará disponible próximamente</p>
            </div>
        </div>
    `;
    
    document.getElementById('settingsContent').innerHTML = content;
}

function openExpenseTypeModal(id = null) {
    const isEdit = id !== null;
    const title = isEdit ? 'Editar Tipo de Gasto' : 'Nuevo Tipo de Gasto';
    
    const modalContent = `
        <form id="expenseTypeForm">
            <div class="modal-body">
                <input type="hidden" id="expenseTypeId" value="${id || ''}">
                
                <div class="form-group">
                    <label class="form-label" for="expenseTypeName">Nombre *</label>
                    <input type="text" class="form-input" id="expenseTypeName" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="expenseTypeDescription">Descripción</label>
                    <textarea class="form-input" id="expenseTypeDescription" rows="3" placeholder="Descripción del tipo de gasto"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="expenseTypeCategory">Categoría *</label>
                    <select class="form-input" id="expenseTypeCategory" required>
                        <option value="">Seleccionar categoría...</option>
                        <option value="1">Transporte</option>
                        <option value="2">Alimentación</option>
                        <option value="3">Oficina</option>
                        <option value="4">Servicios</option>
                        <option value="5">Marketing</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <input type="checkbox" id="expenseTypeIsActive" checked>
                        Tipo activo
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('formModal')">Cancelar</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Guardar Tipo
                </button>
            </div>
        </form>
    `;
    
    document.getElementById('formModalTitle').textContent = title;
    document.getElementById('formModalBody').innerHTML = modalContent;
    
    document.getElementById('expenseTypeForm').addEventListener('submit', function(e) {
        e.preventDefault();
        showToast('Tipo de gasto guardado exitosamente', 'success');
        closeModal('formModal');
        loadExpenseTypesSection();
    });
    
    openModal('formModal');
}

function editExpenseType(id) {
    const mockData = {
        1: { name: 'Combustible', description: 'Gastos en combustible para vehículos', category: '1', active: true },
        2: { name: 'Papelería', description: 'Suministros de oficina y papelería', category: '3', active: true },
        3: { name: 'Almuerzo de Trabajo', description: 'Comidas durante horarios laborales', category: '2', active: true },
        4: { name: 'Internet', description: 'Servicios de internet y conectividad', category: '4', active: true }
    };
    
    openExpenseTypeModal(id);
    
    setTimeout(() => {
        const data = mockData[id];
        if (data) {
            document.getElementById('expenseTypeName').value = data.name;
            document.getElementById('expenseTypeDescription').value = data.description;
            document.getElementById('expenseTypeCategory').value = data.category;
            document.getElementById('expenseTypeIsActive').checked = data.active;
        }
    }, 100);
}

function deleteExpenseType(id) {
    document.getElementById('confirmDeleteMessage').textContent = '¿Está seguro de que desea eliminar este tipo de gasto?';
    document.getElementById('confirmDeleteBtn').onclick = function() {
        showToast('Tipo de gasto eliminado exitosamente', 'success');
        closeModal('confirmDeleteModal');
        loadExpenseTypesSection();
    };
    openModal('confirmDeleteModal');
}

// SECCIÓN TIPOS DE TRABAJOS
function loadJobTypesSection() {
    document.getElementById('settingsContent').innerHTML = `
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h3 class="card-title">
                        <i class="fas fa-briefcase"></i>
                        Tipos de Trabajos
                    </h3>
                    <p class="card-subtitle">Gestionar tipos de trabajos del sistema</p>
                </div>
                <button type="button" class="btn btn-primary" onclick="openJobTypeModal()">
                    <i class="fas fa-plus"></i>
                    Nuevo Tipo de Trabajo
                </button>
            </div>
            
            <div style="overflow-x: auto;">
                <table class="data-table" style="min-width: 800px;">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Pago Contratista</th>
                            <th>Pago Sub-contratista</th>
                            <th>Fecha Creación</th>
                            <th style="width: 120px; text-align: center;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="jobTypesTableBody">
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 40px;">
                                <div style="display: inline-block; width: 20px; height: 20px; border: 2px solid #f3f3f3; border-top: 2px solid #3498db; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                                <p style="margin-top: 12px; color: var(--text-secondary);">Cargando tipos de trabajos...</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    `;
    
    // Cargar datos desde la API
    setTimeout(() => loadJobTypesData(), 500);
}

async function loadJobTypesData() {
    const tbody = document.getElementById('jobTypesTableBody');
    if (!tbody) return;
    
    try {
        const response = await fetch('api/job_type/JobTypeController.php?action=getAllJobTypes');
        
        if (!response.ok) {
            throw new Error('API not available');
        }
        
        const result = await response.json();
        
        if (result.error) {
            throw new Error(result.error);
        }
        
        if (!Array.isArray(result.data) || result.data.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" style="text-align: center; padding: 40px; color: var(--text-secondary);">
                        <i class="fas fa-briefcase" style="font-size: 48px; margin-bottom: 16px; opacity: 0.3;"></i>
                        <p>No hay tipos de trabajos registrados</p>
                    </td>
                </tr>
            `;
            return;
        }
        
        let tableHTML = '';
        result.data.forEach(jobType => {
            const createdDate = new Date(jobType.created_at).toLocaleDateString('es-ES');
            
            tableHTML += `
                <tr>
                    <td><strong>${jobType.name}</strong></td>
                    <td style="text-align: right;">$${parseFloat(jobType.pay_as_contractor || 0).toFixed(2)}</td>
                    <td style="text-align: right;">$${parseFloat(jobType.pay_as_sub_contractor || 0).toFixed(2)}</td>
                    <td>${createdDate}</td>
                    <td style="text-align: center;">
                        <div style="display: flex; gap: 4px; justify-content: center;">
                            <button type="button" class="btn-action" title="Editar" onclick="editJobType('${jobType.id}')">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn-action btn-danger" title="Eliminar" onclick="deleteJobType('${jobType.id}')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });
        
        tbody.innerHTML = tableHTML;
        
    } catch (error) {
        console.error('Error loading job types:', error);
        tbody.innerHTML = `
            <tr>
                <td colspan="5" style="text-align: center; padding: 40px; color: var(--danger-color);">
                    <i class="fas fa-exclamation-triangle" style="font-size: 48px; margin-bottom: 16px;"></i>
                    <p>Error al cargar tipos de trabajos</p>
                    <small>${error.message}</small>
                </td>
            </tr>
        `;
    }
}

function openJobTypeModal(id = null) {
    const isEdit = id !== null;
    const title = isEdit ? 'Editar Tipo de Trabajo' : 'Nuevo Tipo de Trabajo';
    
    const modalContent = `
        <form id="jobTypeForm">
            <div class="modal-body">
                <input type="hidden" id="jobTypeId" value="${id || ''}">
                
                <div class="form-group">
                    <label class="form-label" for="jobTypeName">Nombre *</label>
                    <input type="text" class="form-input" id="jobTypeName" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="jobTypePayContractor">Pago como Contratista *</label>
                    <input type="number" step="0.01" min="0" class="form-input" id="jobTypePayContractor" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="jobTypePaySubContractor">Pago como Sub-contratista *</label>
                    <input type="number" step="0.01" min="0" class="form-input" id="jobTypePaySubContractor" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('formModal')">Cancelar</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Guardar Tipo
                </button>
            </div>
        </form>
    `;
    
    document.getElementById('formModalTitle').textContent = title;
    document.getElementById('formModalBody').innerHTML = modalContent;
    
    document.getElementById('jobTypeForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const data = {
            name: document.getElementById('jobTypeName').value,
            pay_as_contractor: parseFloat(document.getElementById('jobTypePayContractor').value),
            pay_as_sub_contractor: parseFloat(document.getElementById('jobTypePaySubContractor').value)
        };
        
        const url = isEdit 
            ? `api/job_type/JobTypeController.php?action=updateJobType&id=${id}`
            : 'api/job_type/JobTypeController.php?action=createJobType';
        
        const method = isEdit ? 'PUT' : 'POST';
        
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
            
            showToast(result.message || 'Tipo de trabajo guardado exitosamente', 'success');
            closeModal('formModal');
            loadJobTypesSection();
        })
        .catch(err => {
            console.error('Error saving job type:', err);
            showToast('Error al guardar tipo de trabajo', 'error');
        });
    });
    
    openModal('formModal');
}

function editJobType(id) {
    // Cargar datos del tipo de trabajo desde la API
    fetch(`api/job_type/JobTypeController.php?action=getJobTypeById&id=${id}`)
        .then(res => res.json())
        .then(jobType => {
            if (jobType.error || !jobType.id) {
                showToast('Error al cargar tipo de trabajo', 'error');
                return;
            }
            
            openJobTypeModal(id);
            
            // Llenar formulario con datos existentes
            setTimeout(() => {
                document.getElementById('jobTypeName').value = jobType.name;
                document.getElementById('jobTypePayContractor').value = jobType.pay_as_contractor || 0;
                document.getElementById('jobTypePaySubContractor').value = jobType.pay_as_sub_contractor || 0;
            }, 100);
        })
        .catch(err => {
            console.error('Error loading job type:', err);
            showToast('Error al cargar tipo de trabajo', 'error');
        });
}

function deleteJobType(id) {
    document.getElementById('confirmDeleteMessage').textContent = '¿Está seguro de que desea eliminar este tipo de trabajo?';
    document.getElementById('confirmDeleteBtn').onclick = function() {
        fetch(`api/job_type/JobTypeController.php?action=deleteJobType&id=${id}`, {
            method: 'DELETE'
        })
        .then(res => res.json())
        .then(result => {
            closeModal('confirmDeleteModal');
            
            if (result.error) {
                showToast(result.error, 'error');
                return;
            }
            
            showToast(result.message || 'Tipo de trabajo eliminado exitosamente', 'success');
            loadJobTypesSection();
        })
        .catch(err => {
            console.error('Error deleting job type:', err);
            showToast('Error al eliminar tipo de trabajo', 'error');
        });
    };
    openModal('confirmDeleteModal');
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
    `;
    document.head.appendChild(style);
} 