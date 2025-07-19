let typesData = [];
let categoriesData = [];
let currentPage = 1;
let itemsPerPage = 10;
let filteredData = [];

document.addEventListener('DOMContentLoaded', function() {
    loadCategories();
    loadTypes();
    setupEventListeners();
});

function setupEventListeners() {
    // Búsqueda
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            filterTypes();
        });
    }

    // Formulario
    const typeForm = document.getElementById('typeForm');
    if (typeForm) {
        typeForm.addEventListener('submit', function(e) {
            e.preventDefault();
            saveType();
        });
    }
}

async function loadCategories() {
    try {
        const response = await fetch('api/expense_type/ExpenseTypeController.php?action=categories');
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (result.success) {
            categoriesData = result.data || [];
            updateCategorySelect();
        } else {
            console.error('Error loading categories:', result.message);
        }
    } catch (error) {
        console.error('Error loading categories:', error);
    }
}

function updateCategorySelect() {
    const select = document.getElementById('typeCategory');
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

async function loadTypes() {
    try {
        const response = await fetch('api/expense_type/ExpenseTypeController.php?action=list');
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (result.success) {
            typesData = result.data || [];
            filterTypes();
        } else {
            showToast('Error: ' + (result.message || 'No se pudieron cargar los tipos de gastos'), 'error');
            typesData = [];
            filterTypes();
        }
    } catch (error) {
        console.error('Error loading types:', error);
        showToast('Error al cargar los tipos de gastos: ' + error.message, 'error');
        typesData = [];
        filterTypes();
    }
}

function filterTypes() {
    const searchTerm = document.getElementById('searchInput')?.value.toLowerCase() || '';
    
    filteredData = typesData.filter(type => 
        type.name.toLowerCase().includes(searchTerm) ||
        (type.description && type.description.toLowerCase().includes(searchTerm)) ||
        (type.category_name && type.category_name.toLowerCase().includes(searchTerm))
    );
    
    currentPage = 1;
    renderTable();
    updatePagination();
}

function renderTable() {
    const tbody = document.getElementById('typesTableBody');
    const totalElement = document.getElementById('totalTypes');
    
    if (!tbody) return;
    
    // Actualizar contador total
    if (totalElement) {
        totalElement.textContent = filteredData.length;
    }
    
    // Calcular índices para paginación
    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    const pageData = filteredData.slice(startIndex, endIndex);
    
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
        
        const statusText = type.is_active ? 'Activo' : 'Inactivo';
        const statusClass = type.is_active ? 'text-success' : 'text-danger';
        
        row.innerHTML = `
            <td>
                <div style="font-weight: 500;">${escapeHtml(type.name)}</div>
            </td>
            <td>
                <div style="color: #6B7280; max-width: 300px; overflow: hidden; text-overflow: ellipsis;">
                    ${type.description ? escapeHtml(type.description) : '-'}
                </div>
            </td>
            <td>
                <div style="padding: 4px 8px; background-color: #F3F4F6; border-radius: 4px; font-size: 0.875rem; font-weight: 500; display: inline-block;">
                    ${type.category_name || 'Sin categoría'}
                </div>
            </td>
            <td>
                <span class="${statusClass}" style="font-weight: 500;">
                    ${statusText}
                </span>
            </td>
            <td style="text-align: center;">
                <div style="display: flex; gap: 8px; justify-content: center;">
                    <button type="button" class="btn-action" onclick="editType('${type.id}')" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button type="button" class="btn-action btn-danger" onclick="deleteType('${type.id}', '${escapeHtml(type.name)}')" title="Eliminar">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        `;
        
        tbody.appendChild(row);
    });
}

function updatePagination() {
    const totalPages = Math.ceil(filteredData.length / itemsPerPage);
    const paginationContainer = document.getElementById('typesPagination');
    const pageSizeContainer = document.getElementById('pageSizeSelectorContainer');
    
    if (!paginationContainer) return;
    
    // Selector de elementos por página
    if (pageSizeContainer) {
        pageSizeContainer.innerHTML = `
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 0.875rem; color: #6B7280;">Mostrar:</span>
                <select id="pageSizeSelector" style="padding: 4px 8px; border: 1px solid #D1D5DB; border-radius: 4px; font-size: 0.875rem;">
                    <option value="10" ${itemsPerPage === 10 ? 'selected' : ''}>10</option>
                    <option value="25" ${itemsPerPage === 25 ? 'selected' : ''}>25</option>
                    <option value="50" ${itemsPerPage === 50 ? 'selected' : ''}>50</option>
                </select>
                <span style="font-size: 0.875rem; color: #6B7280;">por página</span>
            </div>
        `;
        
        const pageSizeSelector = document.getElementById('pageSizeSelector');
        if (pageSizeSelector) {
            pageSizeSelector.addEventListener('change', function() {
                itemsPerPage = parseInt(this.value);
                currentPage = 1;
                renderTable();
                updatePagination();
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
    if (currentPage > 1) {
        paginationHTML += `<button class="pagination-btn" onclick="changePage(${currentPage - 1})">‹</button>`;
    }
    
    // Números de página
    for (let i = 1; i <= totalPages; i++) {
        if (i === currentPage) {
            paginationHTML += `<button class="pagination-btn active">${i}</button>`;
        } else if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
            paginationHTML += `<button class="pagination-btn" onclick="changePage(${i})">${i}</button>`;
        } else if (i === currentPage - 2 || i === currentPage + 2) {
            paginationHTML += `<span style="padding: 0 4px;">...</span>`;
        }
    }
    
    // Botón siguiente
    if (currentPage < totalPages) {
        paginationHTML += `<button class="pagination-btn" onclick="changePage(${currentPage + 1})">›</button>`;
    }
    
    paginationHTML += '</div>';
    paginationContainer.innerHTML = paginationHTML;
}

function changePage(page) {
    currentPage = page;
    renderTable();
    updatePagination();
}

async function saveType() {
    const formData = new FormData();
    const typeId = document.getElementById('typeId').value;
    const typeName = document.getElementById('typeName').value.trim();
    const typeDescription = document.getElementById('typeDescription').value.trim();
    const typeCategory = document.getElementById('typeCategory').value;
    const typeIsActive = document.getElementById('typeIsActive').checked;
    
    if (!typeName) {
        showNotification('Error', 'El nombre del tipo es obligatorio', 'error');
        return;
    }
    
    if (!typeCategory) {
        showNotification('Error', 'La categoría es obligatoria', 'error');
        return;
    }
    
    // Preparar datos
    formData.append('name', typeName);
    formData.append('description', typeDescription);
    formData.append('category_id', typeCategory);
    formData.append('is_active', typeIsActive ? '1' : '0');
    
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
        
        await handleApiCall(
            () => fetch(url, {
                method: method,
                body: formData
            }).then(response => handleResponse(response)),
            typeId ? 'Tipo actualizado exitosamente' : 'Tipo creado exitosamente',
            'Error al guardar el tipo',
            () => {
                // Callback de éxito
                closeModal('typeModal');
                loadTypes();
            },
            (error) => {
                // Callback de error - mostrar notificación detallada
                showNotification('Error', 'Error al guardar el tipo: ' + error.message, 'error');
            }
        );
        
    } catch (error) {
        console.error('Error saving type:', error);
        showNotification('Error', 'Error al guardar el tipo: ' + error.message, 'error');
    }
}

async function editType(typeId) {
    console.log('Editando tipo:', typeId);
    
    try {
        const response = await fetch(`api/expense_type/ExpenseTypeController.php?action=get&id=${typeId}`);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        console.log('Respuesta del servidor:', result);
        
        if (result.success && result.data) {
            const type = result.data;
            
            // Llenar formulario
            document.getElementById('typeId').value = type.id;
            document.getElementById('typeName').value = type.name || '';
            document.getElementById('typeDescription').value = type.description || '';
            document.getElementById('typeCategory').value = type.category_id || '';
            document.getElementById('typeIsActive').checked = type.is_active === '1' || type.is_active === 1 || type.is_active === true;
            
            // Cambiar título del modal
            document.getElementById('modalTitle').textContent = 'Editar Tipo de Gasto';
            
            // Abrir modal
            openModal('typeModal');
        } else {
            showNotification('Error', result.message || 'No se pudo cargar el tipo', 'error');
        }
        
    } catch (error) {
        console.error('Error loading type for edit:', error);
        showNotification('Error', 'Error al cargar el tipo: ' + error.message, 'error');
    }
}

async function deleteType(typeId, typeName) {
    let message = `¿Está seguro de que desea eliminar el tipo "${typeName}"?`;
    
    document.getElementById('confirmDeleteMessage').textContent = message;
    document.getElementById('confirmDeleteBtn').onclick = async function() {
        await handleApiCall(
            () => apiDelete(`api/expense_type/ExpenseTypeController.php?action=delete&id=${typeId}`),
            'Tipo eliminado exitosamente',
            'Error al eliminar el tipo',
            () => {
                // Callback de éxito
                closeModal('confirmDeleteModal');
                loadTypes();
            },
            (error) => {
                // Callback de error - mostrar notificación detallada si es necesario
                showNotification('Error', 'Error al eliminar el tipo: ' + error.message, 'error');
            }
        );
    };
    
    openModal('confirmDeleteModal');
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        
        // Si es el modal de tipo, resetear para nuevo tipo
        if (modalId === 'typeModal') {
            const typeId = document.getElementById('typeId');
            if (!typeId.value) {
                document.getElementById('modalTitle').textContent = 'Nuevo Tipo de Gasto';
                document.getElementById('typeForm').reset();
                document.getElementById('typeIsActive').checked = true;
            }
        }
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        
        // Limpiar formulario si es el modal de tipo
        if (modalId === 'typeModal') {
            document.getElementById('typeForm').reset();
            document.getElementById('typeId').value = '';
        }
    }
}

function showNotification(title, message, type = 'info') {
    document.getElementById('notificationTitle').textContent = title;
    document.getElementById('notificationMessage').textContent = message;
    openModal('notificationModal');
}

function showToast(message, type = 'info') {
    const toast = document.getElementById('toast');
    const toastMessage = document.getElementById('toastMessage');
    const toastIcon = document.getElementById('toastIcon');
    
    if (!toast || !toastMessage || !toastIcon) return;
    
    // Configurar icono y color según el tipo
    let icon = 'fas fa-info-circle';
    let backgroundColor = '#2563eb';
    
    switch (type) {
        case 'success':
            icon = 'fas fa-check-circle';
            backgroundColor = '#10b981';
            break;
        case 'error':
            icon = 'fas fa-exclamation-circle';
            backgroundColor = '#ef4444';
            break;
        case 'warning':
            icon = 'fas fa-exclamation-triangle';
            backgroundColor = '#f59e0b';
            break;
    }
    
    toastIcon.className = icon;
    toastMessage.textContent = message;
    toast.style.backgroundColor = backgroundColor;
    
    // Mostrar toast
    toast.style.display = 'flex';
    
    // Ocultar después de 3 segundos
    setTimeout(() => {
        toast.style.display = 'none';
    }, 3000);
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
} 