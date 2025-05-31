let categoriesData = [];
let currentPage = 1;
let itemsPerPage = 10;
let filteredData = [];

document.addEventListener('DOMContentLoaded', function() {
    loadCategories();
    setupEventListeners();
});

function setupEventListeners() {
    // Búsqueda
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            filterCategories();
        });
    }

    // Formulario
    const categoryForm = document.getElementById('categoryForm');
    if (categoryForm) {
        categoryForm.addEventListener('submit', function(e) {
            e.preventDefault();
            saveCategory();
        });
    }
}

async function loadCategories() {
    try {
        const response = await fetch('api/expense_category/ExpenseCategoryController.php?action=list');
        
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
    const searchTerm = document.getElementById('searchInput')?.value.toLowerCase() || '';
    
    filteredData = categoriesData.filter(category => 
        category.name.toLowerCase().includes(searchTerm) ||
        (category.description && category.description.toLowerCase().includes(searchTerm))
    );
    
    currentPage = 1;
    renderTable();
    updatePagination();
}

function renderTable() {
    const tbody = document.getElementById('categoriesTableBody');
    const totalElement = document.getElementById('totalCategories');
    
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
        const statusText = category.is_active ? 'Activa' : 'Inactiva';
        const statusClass = category.is_active ? 'text-success' : 'text-danger';
        
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
            <td>
                <span class="${statusClass}" style="font-weight: 500;">
                    ${statusText}
                </span>
            </td>
            <td style="text-align: center;">
                <div style="display: flex; gap: 8px; justify-content: center;">
                    <button type="button" class="btn-action" onclick="editCategory('${category.id}')" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button type="button" class="btn-action btn-danger" onclick="deleteCategory('${category.id}', '${escapeHtml(category.name)}')" title="Eliminar">
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
    const paginationContainer = document.getElementById('categoriesPagination');
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

async function saveCategory() {
    const formData = new FormData();
    const categoryId = document.getElementById('categoryId').value;
    const categoryName = document.getElementById('categoryName').value.trim();
    const categoryDescription = document.getElementById('categoryDescription').value.trim();
    const categoryIsActive = document.getElementById('categoryIsActive').checked;
    
    if (!categoryName) {
        showNotification('Error', 'El nombre de la categoría es obligatorio', 'error');
        return;
    }
    
    // Preparar datos
    formData.append('name', categoryName);
    formData.append('description', categoryDescription);
    formData.append('is_active', categoryIsActive ? '1' : '0');
    
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
            closeModal('categoryModal');
            await loadCategories();
        } else {
            showNotification('Error', result.message || 'Error al guardar la categoría', 'error');
        }
        
    } catch (error) {
        console.error('Error saving category:', error);
        showNotification('Error', 'Error al guardar la categoría: ' + error.message, 'error');
    }
}

async function editCategory(categoryId) {
    console.log('Editando categoría:', categoryId);
    
    try {
        const response = await fetch(`api/expense_category/ExpenseCategoryController.php?action=get&id=${categoryId}`);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        console.log('Respuesta del servidor:', result);
        
        if (result.success && result.data) {
            const category = result.data;
            
            // Llenar formulario
            document.getElementById('categoryId').value = category.id;
            document.getElementById('categoryName').value = category.name || '';
            document.getElementById('categoryDescription').value = category.description || '';
            document.getElementById('categoryIsActive').checked = category.is_active === '1' || category.is_active === 1 || category.is_active === true;
            
            // Cambiar título del modal
            document.getElementById('modalTitle').textContent = 'Editar Categoría';
            
            // Abrir modal
            openModal('categoryModal');
        } else {
            showNotification('Error', result.message || 'No se pudo cargar la categoría', 'error');
        }
        
    } catch (error) {
        console.error('Error loading category for edit:', error);
        showNotification('Error', 'Error al cargar la categoría: ' + error.message, 'error');
    }
}

async function deleteCategory(categoryId, categoryName) {
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
                showNotification('Error', result.message || 'Error al eliminar la categoría', 'error');
            }
            
        } catch (error) {
            console.error('Error deleting category:', error);
            showNotification('Error', 'Error al eliminar la categoría: ' + error.message, 'error');
        }
    };
    
    openModal('confirmDeleteModal');
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        
        // Si es el modal de categoría, resetear para nueva categoría
        if (modalId === 'categoryModal') {
            const categoryId = document.getElementById('categoryId');
            if (!categoryId.value) {
                document.getElementById('modalTitle').textContent = 'Nueva Categoría';
                document.getElementById('categoryForm').reset();
                document.getElementById('categoryIsActive').checked = true;
            }
        }
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        
        // Limpiar formulario si es el modal de categoría
        if (modalId === 'categoryModal') {
            document.getElementById('categoryForm').reset();
            document.getElementById('categoryId').value = '';
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