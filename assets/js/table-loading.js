/**
 * ==============================================
 * SISTEMA UNIVERSAL DE LOADING PARA TABLAS
 * ==============================================
 * 
 * Funciones universales para mostrar estados de carga
 * en todas las tablas del proyecto
 */

// Configuración global
const TableLoadingConfig = {
    overlayClass: 'table-loading-overlay',
    spinnerClass: 'modern-spinner',
    containerClass: 'table-container'
};

/**
 * Mostrar loading en una tabla específica
 * @param {string} tableId - ID de la tabla
 * @param {string} message - Mensaje de carga (opcional)
 * @param {string} type - Tipo de spinner: 'overlay', 'inline', 'cell'
 */
function showTableLoading(tableId, message = 'Cargando...', type = 'overlay') {
    const tableElement = document.getElementById(tableId);
    if (!tableElement) return;

    // Remover loading previo si existe
    hideTableLoading(tableId);

    switch (type) {
        case 'overlay':
            showOverlayLoading(tableElement, message);
            break;
        case 'inline':
            showInlineLoading(tableElement, message);
            break;
        case 'cell':
            showCellLoading(tableElement, message);
            break;
    }
}

/**
 * Ocultar loading de una tabla específica
 * @param {string} tableId - ID de la tabla
 */
function hideTableLoading(tableId) {
    const tableElement = document.getElementById(tableId);
    if (!tableElement) return;

    // Remover overlay si existe
    const overlay = tableElement.parentElement.querySelector('.table-loading-overlay');
    if (overlay) {
        overlay.remove();
    }

    // Restaurar contenido original si está guardado
    const tbody = tableElement.querySelector('tbody');
    if (tbody && tbody.dataset.originalContent) {
        tbody.innerHTML = tbody.dataset.originalContent;
        delete tbody.dataset.originalContent;
    }
}

/**
 * Mostrar loading como overlay sobre la tabla
 * @param {HTMLElement} tableElement - Elemento tabla
 * @param {string} message - Mensaje de carga
 */
function showOverlayLoading(tableElement, message) {
    // Asegurar que el contenedor padre tenga position relative
    const parent = tableElement.parentElement;
    if (!parent.classList.contains('table-container')) {
        parent.classList.add('table-container');
    }

    // Crear overlay
    const overlay = document.createElement('div');
    overlay.className = 'table-loading-overlay';
    overlay.innerHTML = `
        <div class="modern-spinner">
            <div class="spinner-circle"></div>
            <div class="spinner-text">${message}</div>
        </div>
    `;

    parent.appendChild(overlay);

    // Animación de entrada
    setTimeout(() => {
        overlay.style.opacity = '1';
    }, 10);
}

/**
 * Mostrar loading inline en el tbody
 * @param {HTMLElement} tableElement - Elemento tabla
 * @param {string} message - Mensaje de carga
 */
function showInlineLoading(tableElement, message) {
    const tbody = tableElement.querySelector('tbody');
    if (!tbody) return;

    // Guardar contenido original
    tbody.dataset.originalContent = tbody.innerHTML;

    // Contar columnas para el colspan
    const thead = tableElement.querySelector('thead tr');
    const colCount = thead ? thead.children.length : 5;

    tbody.innerHTML = `
        <tr>
            <td colspan="${colCount}" style="text-align: center; padding: 0; border: none;">
                <div class="loading-spinner">
                    <div class="spinner-circle"></div>
                    <div class="spinner-text">${message}</div>
                </div>
            </td>
        </tr>
    `;
}

/**
 * Mostrar loading simple en una celda
 * @param {HTMLElement} tableElement - Elemento tabla
 * @param {string} message - Mensaje de carga
 */
function showCellLoading(tableElement, message) {
    const tbody = tableElement.querySelector('tbody');
    if (!tbody) return;

    // Guardar contenido original
    tbody.dataset.originalContent = tbody.innerHTML;

    // Contar columnas para el colspan
    const thead = tableElement.querySelector('thead tr');
    const colCount = thead ? thead.children.length : 5;

    tbody.innerHTML = `
        <tr>
            <td colspan="${colCount}" style="text-align: center; padding: 40px 20px; border: none;">
                <div class="table-state">
                    <div class="spinner-circle"></div>
                    <div class="table-state-message">${message}</div>
                </div>
            </td>
        </tr>
    `;
}

/**
 * Mostrar estado vacío en tabla
 * @param {string} tableId - ID de la tabla
 * @param {string} message - Mensaje a mostrar
 * @param {string} icon - Icono a mostrar
 * @param {string} description - Descripción adicional
 */
function showEmptyTableState(tableId, message = 'No hay datos', icon = 'fas fa-inbox', description = '') {
    const tableElement = document.getElementById(tableId);
    if (!tableElement) return;

    const tbody = tableElement.querySelector('tbody');
    if (!tbody) return;

    // Contar columnas para el colspan
    const thead = tableElement.querySelector('thead tr');
    const colCount = thead ? thead.children.length : 5;

    tbody.innerHTML = `
        <tr>
            <td colspan="${colCount}" style="text-align: center; padding: 0; border: none;">
                <div class="table-state">
                    <div class="table-state-icon">
                        <i class="${icon}"></i>
                    </div>
                    <div class="table-state-message">${message}</div>
                    ${description ? `<div class="table-state-description">${description}</div>` : ''}
                </div>
            </td>
        </tr>
    `;
}

/**
 * Mostrar estado de error en tabla
 * @param {string} tableId - ID de la tabla
 * @param {string} message - Mensaje de error
 * @param {string} action - Acción para retry (opcional)
 */
function showErrorTableState(tableId, message = 'Error al cargar datos', action = null) {
    const tableElement = document.getElementById(tableId);
    if (!tableElement) return;

    const tbody = tableElement.querySelector('tbody');
    if (!tbody) return;

    // Contar columnas para el colspan
    const thead = tableElement.querySelector('thead tr');
    const colCount = thead ? thead.children.length : 5;

    const actionButton = action ? `
        <button class="btn btn-primary" onclick="${action}" style="margin-top: 16px;">
            <i class="fas fa-redo"></i> Reintentar
        </button>
    ` : '';

    tbody.innerHTML = `
        <tr>
            <td colspan="${colCount}" style="text-align: center; padding: 0; border: none;">
                <div class="table-state">
                    <div class="table-state-icon" style="color: var(--danger-color);">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="table-state-message" style="color: var(--danger-color);">${message}</div>
                    ${actionButton}
                </div>
            </td>
        </tr>
    `;
}

/**
 * Funciones auxiliares para compatibilidad con código existente
 */
function setTableLoading(loading, tableId = null, message = 'Cargando...') {
    // Si no se especifica tableId, intentar detectar automáticamente
    if (!tableId) {
        // Buscar el primer tbody en el DOM actual
        const tbody = document.querySelector('tbody');
        if (tbody) {
            const table = tbody.closest('table');
            if (table && table.id) {
                tableId = table.id;
            }
        }
    }

    if (loading) {
        showTableLoading(tableId, message, 'inline');
    } else {
        hideTableLoading(tableId);
    }
}

/**
 * Función para mostrar loading en botones
 * @param {string} buttonId - ID del botón
 * @param {boolean} loading - Estado de loading
 * @param {string} originalText - Texto original del botón
 */
function setButtonLoading(buttonId, loading, originalText = '') {
    const button = document.getElementById(buttonId);
    if (!button) return;

    if (loading) {
        if (!button.dataset.originalText) {
            button.dataset.originalText = button.innerHTML;
        }
        button.innerHTML = `
            <div class="loading-inline">
                <div class="spinner-circle"></div>
                <span>Cargando...</span>
            </div>
        `;
        button.disabled = true;
    } else {
        button.innerHTML = button.dataset.originalText || originalText;
        button.disabled = false;
        delete button.dataset.originalText;
    }
}

/**
 * Función para mostrar loading en selectores de paginación
 * @param {string} containerId - ID del contenedor de paginación
 * @param {boolean} loading - Estado de loading
 */
function setPaginationLoading(containerId, loading) {
    const container = document.getElementById(containerId);
    if (!container) return;

    if (loading) {
        container.style.opacity = '0.5';
        container.style.pointerEvents = 'none';
    } else {
        container.style.opacity = '1';
        container.style.pointerEvents = 'auto';
    }
}

/**
 * Función para mostrar loading en filtros
 * @param {string} filterContainerId - ID del contenedor de filtros
 * @param {boolean} loading - Estado de loading
 */
function setFilterLoading(filterContainerId, loading) {
    const container = document.getElementById(filterContainerId);
    if (!container) return;

    if (loading) {
        container.style.opacity = '0.6';
        const inputs = container.querySelectorAll('input, select, button');
        inputs.forEach(input => {
            input.disabled = true;
        });
    } else {
        container.style.opacity = '1';
        const inputs = container.querySelectorAll('input, select, button');
        inputs.forEach(input => {
            input.disabled = false;
        });
    }
}

// Exportar funciones para uso global
window.TableLoading = {
    show: showTableLoading,
    hide: hideTableLoading,
    showEmpty: showEmptyTableState,
    showError: showErrorTableState,
    setTableLoading,
    setButtonLoading,
    setPaginationLoading,
    setFilterLoading
};

// Mantener compatibilidad con código existente
window.showTableLoading = showTableLoading;
window.hideTableLoading = hideTableLoading;
window.setTableLoading = setTableLoading; 