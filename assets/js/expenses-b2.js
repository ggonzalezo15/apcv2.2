/**
 * Integración BackBlaze B2 para Expenses
 * Este archivo extiende la funcionalidad de expenses.js para usar BackBlaze B2
 */

// --- Configuración B2 ---
const B2_API_URL = 'api/upload/B2UploadController.php';

// Variables globales para compresión
let isCompressionEnabled = true;

// --- Inicialización B2 ---
document.addEventListener('DOMContentLoaded', function() {
    // Verificar estado de compresión al cargar
    checkCompressionStatus();
    
    // Interceptar el envío del formulario para usar B2
    setTimeout(() => {
        interceptExpenseFormSubmit();
    }, 200);
    
    // Agregar indicador de compresión en el modal
    setTimeout(() => {
        addCompressionIndicator();
    }, 300);
});

// --- Verificar estado de compresión ---
function checkCompressionStatus() {
    fetch('toggle_image_compression.php?action=check')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                isCompressionEnabled = data.current_status === 'enabled';
                updateCompressionIndicator();
            }
        })
        .catch(error => {
            console.log('Error checking compression status:', error);
        });
}

// --- Agregar indicador de compresión ---
function addCompressionIndicator() {
    const attachmentSection = document.querySelector('.form-section h3');
    if (attachmentSection && attachmentSection.textContent.includes('Archivos Adjuntos')) {
        const indicator = document.createElement('div');
        indicator.id = 'compressionIndicator';
        indicator.className = 'compression-indicator';
        indicator.style.cssText = 'margin-top: 10px; padding: 8px 12px; border-radius: 4px; font-size: 12px; display: flex; align-items: center; gap: 6px;';
        
        attachmentSection.parentElement.appendChild(indicator);
        updateCompressionIndicator();
    }
}

// --- Actualizar indicador de compresión ---
function updateCompressionIndicator() {
    const indicator = document.getElementById('compressionIndicator');
    if (!indicator) return;
    
    if (isCompressionEnabled) {
        indicator.style.backgroundColor = '#f0fff4';
        indicator.style.color = '#22543d';
        indicator.style.border = '1px solid #bbf7d0';
        indicator.innerHTML = '<i class="fas fa-compress-arrows-alt"></i> Compresión de imágenes: <strong>Activada</strong>';
    } else {
        indicator.style.backgroundColor = '#fffaf0';
        indicator.style.color = '#7b341e';
        indicator.style.border = '1px solid #fed7aa';
        indicator.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Compresión de imágenes: <strong>Desactivada</strong>';
    }
}

// --- Interceptar envío del formulario para usar B2 ---
function interceptExpenseFormSubmit() {
    const form = document.getElementById('expenseForm');
    if (!form) return;
    
    // Remover event listeners existentes
    form.removeEventListener('submit', handleExpenseSubmit);
    
    // Agregar nuevo event listener para B2
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Hacer validación final
        if (!validateAllFields()) {
            showToast('Por favor, corrija los errores en el formulario antes de continuar', 'error');
            return;
        }
        
        const formData = collectFormData();
        if (!validateFormData(formData)) return;
        
        // Validar límite de archivos
        const existingFiles = document.querySelectorAll('.existing-attachments .attachment-item-preview').length;
        const filesToDelete = window.attachmentsToDelete ? window.attachmentsToDelete.length : 0;
        const totalFiles = selectedFiles.length + existingFiles - filesToDelete;
        
        if (totalFiles > 4) {
            showToast(`Máximo 4 archivos permitidos. Actualmente: ${existingFiles - filesToDelete} existentes + ${selectedFiles.length} nuevos = ${totalFiles} total.`, 'error');
            return;
        }
        
        // Si hay archivos nuevos, usar B2. Si no, usar el método original
        if (selectedFiles.length > 0) {
            handleExpenseSubmitWithB2(formData);
        } else {
            handleExpenseSubmitWithoutFiles(formData);
        }
    });
}

// --- Envío con archivos B2 ---
function handleExpenseSubmitWithB2(formData) {
    showUploadProgress(true);
    console.log('Iniciando envío con B2, editingExpenseId:', editingExpenseId);
    console.log('Form data:', formData);
    
    // Primer paso: Crear/actualizar el expense sin archivos
    saveExpenseData(formData)
        .then(expenseData => {
            console.log('Respuesta de saveExpenseData:', expenseData);
            
            if (!expenseData.success) {
                console.error('Error en saveExpenseData:', expenseData);
                throw new Error(expenseData.message || expenseData.error || 'Error desconocido al guardar gasto');
            }
            
            const expenseId = expenseData.id || editingExpenseId;
            const expenseDate = formData.expense_date;
            
            console.log('Expense ID para upload:', expenseId);
            console.log('Archivos a subir:', selectedFiles.length);
            
            // Segundo paso: Subir archivos a B2
            return uploadFilesToB2(selectedFiles, expenseId, expenseDate);
        })
        .then(uploadResult => {
            console.log('Resultado de upload B2:', uploadResult);
            
            if (!uploadResult.success) {
                console.error('Error en upload B2:', uploadResult);
                throw new Error(uploadResult.error || uploadResult.message || 'Error al subir archivos a B2');
            }
            
            // Tercer paso: Eliminar archivos marcados para eliminación
            if (window.attachmentsToDelete && window.attachmentsToDelete.length > 0) {
                console.log('Eliminando archivos marcados:', window.attachmentsToDelete);
                return deleteMarkedAttachments();
            }
            
            return { success: true };
        })
        .then(() => {
            console.log('Proceso completado exitosamente');
            showUploadProgress(false);
            showToast(editingExpenseId ? 'Gasto actualizado exitosamente' : 'Gasto creado exitosamente', 'success');
            closeModal('expenseModal');
            loadExpenses();
        })
        .catch(error => {
            showUploadProgress(false);
            console.error('Error en envío con B2:', error);
            console.error('Stack trace:', error.stack);
            showToast(error.message || 'Error al guardar gasto', 'error');
        });
}

// --- Envío sin archivos ---
function handleExpenseSubmitWithoutFiles(formData) {
    const submitData = new FormData();
    
    // Datos básicos
    Object.entries(formData).forEach(([key, value]) => {
        if (key !== 'lines') {
            submitData.append(key, value);
        }
    });
    
    // Líneas de gastos
    submitData.append('lines', JSON.stringify(formData.lines));
    
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

// --- Guardar datos del expense ---
function saveExpenseData(formData) {
    const submitData = new FormData();
    
    // Datos básicos
    Object.entries(formData).forEach(([key, value]) => {
        if (key !== 'lines') {
            submitData.append(key, value);
        }
    });
    
    // Líneas de gastos
    submitData.append('lines', JSON.stringify(formData.lines));
    
    const url = editingExpenseId 
        ? `${API_URL}?action=updateExpense&id=${editingExpenseId}`
        : `${API_URL}?action=createExpense`;
    
    console.log('URL para saveExpenseData:', url);
    console.log('Datos enviados:', {
        ...formData,
        lines: formData.lines
    });
    
    return fetch(url, {
        method: 'POST',
        body: submitData
    })
    .then(response => {
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        return response.text();
    })
    .then(text => {
        console.log('Response text:', text);
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('Error parsing JSON response:', e);
            throw new Error('Respuesta inválida del servidor: ' + text);
        }
    })
    .catch(error => {
        console.error('Error en fetch:', error);
        throw error;
    });
}

// --- Subir archivos a B2 ---
function uploadFilesToB2(files, expenseId, expenseDate) {
    const formData = new FormData();
    formData.append('expense_id', expenseId);
    formData.append('expense_date', expenseDate);
    
    files.forEach(file => {
        formData.append('files[]', file);
    });
    
    return fetch(`${B2_API_URL}?action=uploadFiles`, {
        method: 'POST',
        body: formData
    })
    .then(res => res.json());
}

// --- Eliminar archivos marcados ---
function deleteMarkedAttachments() {
    const promises = window.attachmentsToDelete.map(attachmentId => {
        return fetch(`${B2_API_URL}?action=deleteFile`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                attachment_id: attachmentId
            })
        })
        .then(res => res.json());
    });
    
    return Promise.all(promises);
}

// --- Mostrar progreso de upload ---
function showUploadProgress(show) {
    const submitBtn = document.querySelector('#expenseModal .btn-primary');
    const form = document.getElementById('expenseForm');
    
    if (show) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Subiendo archivos...';
        
        // Deshabilitar todos los inputs del formulario
        const inputs = form.querySelectorAll('input, select, textarea, button');
        inputs.forEach(input => {
            if (input !== submitBtn) {
                input.disabled = true;
            }
        });
    } else {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-save"></i> Guardar Gasto';
        
        // Rehabilitar todos los inputs del formulario
        const inputs = form.querySelectorAll('input, select, textarea, button');
        inputs.forEach(input => {
            input.disabled = false;
        });
    }
}

// --- Función para abrir attachments usando B2 ---
window.openAttachmentB2 = function(fileKey, filename, mimeType) {
    if (mimeType && mimeType.startsWith('image/')) {
        // Para imágenes, obtener URL firmada y mostrar en modal
        fetch(`${B2_API_URL}?action=getSignedUrl&file_key=${encodeURIComponent(fileKey)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showImageModal(data.signed_url, filename);
                } else {
                    showToast('Error al obtener la URL del archivo', 'error');
                }
            })
            .catch(error => {
                showToast('Error al cargar el archivo', 'error');
            });
    } else {
        // Para PDFs y otros, abrir en nueva ventana
        fetch(`${B2_API_URL}?action=getSignedUrl&file_key=${encodeURIComponent(fileKey)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.open(data.signed_url, '_blank');
                } else {
                    showToast('Error al obtener la URL del archivo', 'error');
                }
            })
            .catch(error => {
                showToast('Error al cargar el archivo', 'error');
            });
    }
};

// --- Modal para mostrar imágenes ---
function showImageModal(imageUrl, title) {
    let modal = document.getElementById('imageViewModal');
    
    if (!modal) {
        // Crear modal si no existe
        modal = document.createElement('div');
        modal.id = 'imageViewModal';
        modal.className = 'modal';
        modal.style.display = 'none';
        modal.innerHTML = `
            <div class="modal-overlay" onclick="closeImageModal()"></div>
            <div class="modal-content" style="max-width: 90vw; max-height: 90vh; padding: 0; border-radius: 8px; overflow: hidden;">
                <div class="modal-header" style="padding: 15px 20px; border-bottom: 1px solid #e2e8f0;">
                    <h3 id="imageModalTitle" style="margin: 0; color: #2d3748;">Vista previa</h3>
                    <button type="button" class="modal-close" onclick="closeImageModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #718096;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body" style="padding: 0; text-align: center; background: #f8fafc;">
                    <img id="imageModalImg" style="max-width: 100%; height: auto; display: block;" alt="Vista previa">
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }
    
    const modalTitle = document.getElementById('imageModalTitle');
    const modalImg = document.getElementById('imageModalImg');
    
    modalTitle.textContent = title;
    modalImg.src = imageUrl;
    modalImg.alt = title;
    
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

// --- Cerrar modal de imagen ---
function closeImageModal() {
    const modal = document.getElementById('imageViewModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

// --- Cargar attachments en dropdown para usar B2 ---
window.loadAttachmentsForDropdownB2 = function(expenseId, dropdown) {
    dropdown.innerHTML = '<div class="dropdown-loading">Cargando...</div>';
    
    fetch(`${API_URL}?action=getExpenseAttachmentsB2&id=${expenseId}`)
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
                const compressionBadge = attachment.compressed ? 
                    ' <span style="font-size: 10px; background: #22543d; color: white; padding: 1px 4px; border-radius: 2px;">C</span>' : '';
                
                html += `
                    <div class="dropdown-item" onclick="openAttachmentB2('${attachment.file_key}', '${escapeHtml(attachment.original_name)}', '${attachment.mime_type}')" title="Click para abrir">
                        <i class="fas ${fileIcon}"></i>
                        <div class="file-info">
                            <div class="file-name">${escapeHtml(attachment.original_name)}${compressionBadge}</div>
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
};

// --- Mostrar archivos existentes en el modal de edición ---
window.displayExistingAttachmentsB2 = function(attachments) {
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
        const compressionBadge = attachment.compressed ? 
            '<span style="font-size: 10px; background: #22543d; color: white; padding: 2px 6px; border-radius: 4px; margin-left: 8px;">Comprimido</span>' : '';
        
        const div = document.createElement('div');
        div.className = 'attachment-item-preview existing';
        div.innerHTML = `
            <div class="attachment-preview-content">
                <i class="fas ${fileIcon}"></i>
                <div class="attachment-info">
                    <div class="attachment-name">${escapeHtml(attachment.original_filename)}${compressionBadge}</div>
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
};

// --- Mostrar attachments en el modal de vista ---
window.populateViewAttachmentsB2 = function(attachments) {
    const attachmentsContainer = document.getElementById('viewAttachments');
    const attachmentsSection = document.getElementById('viewAttachmentsSection');
    
    if (!attachments || attachments.length === 0) {
        attachmentsSection.style.display = 'none';
        return;
    }
    
    attachmentsSection.style.display = 'block';
    
    let html = '';
    attachments.forEach(attachment => {
        const fileIcon = getFileIcon(attachment.mime_type);
        const fileSize = (attachment.file_size / 1024 / 1024).toFixed(2);
        const compressionBadge = attachment.compressed ? 
            ' <span style="font-size: 10px; background: #22543d; color: white; padding: 1px 4px; border-radius: 2px;">C</span>' : '';
        
        html += `
            <div class="attachment-item" onclick="openAttachmentB2('${attachment.file_key}', '${escapeHtml(attachment.original_filename)}', '${attachment.mime_type}')" style="cursor: pointer;">
                <i class="fas ${fileIcon}" style="margin-right: 8px;"></i>
                <span>${escapeHtml(attachment.original_filename)}${compressionBadge}</span>
                <small style="margin-left: 8px; color: #718096;">(${fileSize} MB)</small>
            </div>
        `;
    });
    
    attachmentsContainer.innerHTML = html;
};

// --- Actualizar preview de archivos para mostrar compresión ---
window.handleFilePreviewB2 = function() {
    const files = document.getElementById('attachments').files;
    const preview = document.getElementById('attachments-preview');
    
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
        
        // Verificar si el archivo será comprimido
        const willBeCompressed = isCompressionEnabled && (file.type === 'image/jpeg' || file.type === 'image/png');
        const compressionBadge = willBeCompressed ? 
            '<span style="font-size: 10px; background: #22543d; color: white; padding: 2px 6px; border-radius: 4px; margin-left: 8px;">Se comprimirá</span>' : '';
        
        const div = document.createElement('div');
        div.className = 'attachment-item-preview new';
        div.innerHTML = `
            <div class="attachment-preview-content">
                <i class="fas ${fileIcon}"></i>
                <div class="attachment-info">
                    <div class="attachment-name">${escapeHtml(file.name)}${compressionBadge}</div>
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
};

// --- Interceptar función de vista de expense para usar B2 ---
window.populateViewModalB2 = function(expense) {
    // Llenar información básica (reusar función original pero sin attachments)
    document.getElementById('viewExpenseNumber').textContent = generateExpenseNumber(expense);
    document.getElementById('viewExpenseDate').textContent = formatDate(expense.expense_date);
    document.getElementById('viewTeamName').textContent = expense.team_name || 'N/A';
    document.getElementById('viewVendorName').textContent = expense.vendor_name || 'N/A';
    document.getElementById('viewBankAccount').textContent = 
        expense.bank_account_name ? 
        `${expense.bank_account_name}${expense.bank_account_type ? ` (${expense.bank_account_type})` : ''}` : 
        'N/A';
    
    // Líneas de gasto
    const linesContainer = document.getElementById('viewExpenseLines');
    if (expense.lines && expense.lines.length > 0) {
        let linesHtml = '';
        let total = 0;
        
        expense.lines.forEach(line => {
            const amount = parseFloat(line.amount) || 0;
            total += amount;
            const deducibleClass = line.deducible ? 'yes' : 'no';
            
            linesHtml += `
                <div class="expense-line-view">
                    <div class="line-description">${escapeHtml(line.description)}</div>
                    <div class="line-type">${escapeHtml(line.expense_type_name || 'N/A')}</div>
                    <div class="line-amount">$${amount.toLocaleString('es-MX', {minimumFractionDigits: 2})}</div>
                    <div class="line-deducible ${deducibleClass}">${line.deducible ? 'Sí' : 'No'}</div>
                </div>
            `;
        });
        
        linesContainer.innerHTML = linesHtml;
        document.getElementById('viewTotalAmount').textContent = 
            `$${total.toLocaleString('es-MX', {minimumFractionDigits: 2})}`;
    } else {
        linesContainer.innerHTML = '<div class="expense-line-view"><div style="text-align: center; color: var(--text-secondary); grid-column: 1 / -1; padding: 20px;">No hay líneas de gasto registradas</div></div>';
        document.getElementById('viewTotalAmount').textContent = '$0.00';
    }
    
    // Archivos adjuntos usando B2
    populateViewAttachmentsB2(expense.attachments);
    
    // Notas
    const notesContainer = document.getElementById('viewNotes');
    const notesSection = document.getElementById('viewNotesSection');
    if (expense.notes && expense.notes.trim()) {
        notesSection.style.display = 'block';
        notesContainer.textContent = expense.notes;
    } else {
        notesSection.style.display = 'none';
    }
    
    // Guardar ID para edición
    document.getElementById('editFromViewBtn').onclick = () => editExpenseFromView(expense.id);
};

// --- Sobrescribir funciones existentes para usar B2 ---
document.addEventListener('DOMContentLoaded', function() {
    // Esperar un poco para asegurar que expenses.js esté cargado
    setTimeout(() => {
        // Verificar dependencias
        console.log('=== B2 Integration Debug ===');
        console.log('API_URL:', typeof API_URL !== 'undefined' ? API_URL : 'UNDEFINED');
        console.log('B2_API_URL:', B2_API_URL);
        console.log('selectedFiles:', typeof selectedFiles !== 'undefined' ? 'Disponible' : 'UNDEFINED');
        console.log('editingExpenseId:', typeof editingExpenseId !== 'undefined' ? 'Disponible' : 'UNDEFINED');
        console.log('validateAllFields:', typeof validateAllFields === 'function' ? 'Disponible' : 'UNDEFINED');
        console.log('collectFormData:', typeof collectFormData === 'function' ? 'Disponible' : 'UNDEFINED');
        console.log('validateFormData:', typeof validateFormData === 'function' ? 'Disponible' : 'UNDEFINED');
        console.log('showToast:', typeof showToast === 'function' ? 'Disponible' : 'UNDEFINED');
        console.log('closeModal:', typeof closeModal === 'function' ? 'Disponible' : 'UNDEFINED');
        console.log('loadExpenses:', typeof loadExpenses === 'function' ? 'Disponible' : 'UNDEFINED');
        console.log('generateExpenseNumber:', typeof generateExpenseNumber === 'function' ? 'Disponible' : 'UNDEFINED');
        console.log('formatDate:', typeof formatDate === 'function' ? 'Disponible' : 'UNDEFINED');
        console.log('escapeHtml:', typeof escapeHtml === 'function' ? 'Disponible' : 'UNDEFINED');
        console.log('getFileIcon:', typeof getFileIcon === 'function' ? 'Disponible' : 'UNDEFINED');
        console.log('=== End Debug ===');
        
        // Reemplazar funciones específicas para usar B2
        if (typeof window.loadAttachmentsForDropdown === 'function') {
            window.loadAttachmentsForDropdown = window.loadAttachmentsForDropdownB2;
        }
        
        if (typeof window.handleFilePreview === 'function') {
            window.handleFilePreview = window.handleFilePreviewB2;
        }
        
        if (typeof window.displayExistingAttachments === 'function') {
            window.displayExistingAttachments = window.displayExistingAttachmentsB2;
        }
        
        if (typeof window.populateViewModal === 'function') {
            window.populateViewModal = window.populateViewModalB2;
        }
        
        console.log('B2 Integration loaded successfully');
    }, 500);
});

// CSS adicional para indicadores de compresión
const style = document.createElement('style');
style.textContent = `
    .compression-indicator {
        font-family: inherit;
        font-weight: 500;
    }
    
    .compression-indicator i {
        font-size: 14px;
    }
    
    .attachment-item {
        padding: 8px 12px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        margin-bottom: 8px;
        background: #f8fafc;
        transition: background-color 0.2s;
    }
    
    .attachment-item:hover {
        background: #f1f5f9;
    }
    
    #imageViewModal .modal-content {
        animation: slideIn 0.3s ease;
    }
    
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: scale(0.9);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }
`;
document.head.appendChild(style);
