// Funcionalidad del sidebar y aplicación
document.addEventListener('DOMContentLoaded', function() {
    // Elementos DOM con verificación de existencia
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const body = document.body;
    
    // Solo ejecutar código del sidebar si existe
    if (sidebar) {
        initializeSidebar(sidebar, sidebarToggle, body);
    }
    
    // Inicializar formularios
    initializeForms();
    
    // Inicializar otros componentes
    initializeOtherComponents();
});

// Función para inicializar el sidebar
function initializeSidebar(sidebar, sidebarToggle, body) {
    // Crear overlay para móvil
    const overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    overlay.id = 'sidebarOverlay';
    body.appendChild(overlay);
    
    // Toggle del sidebar en móvil
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            toggleSidebar();
        });
    }
    
    // Cerrar sidebar al hacer click en el overlay
    overlay.addEventListener('click', function() {
        closeSidebar();
    });
    
    // Función para alternar el sidebar
    function toggleSidebar() {
        if (window.innerWidth <= 768) {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
            body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
        }
    }
    
    // Función para cerrar el sidebar
    function closeSidebar() {
        if (window.innerWidth <= 768) {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            body.style.overflow = '';
        }
    }
    
    // Cerrar sidebar al redimensionar la ventana
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            closeSidebar();
        }
    });    // Cerrar sidebar al hacer click en un enlace (móvil) - CORREGIDO
    const navLinks = sidebar.querySelectorAll('.nav-link');
    if (navLinks.length > 0) {
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    setTimeout(() => {
                        closeSidebar();
                    }, 150);
                }
            });
        });
    }
    
    // Funcionalidad de expansión automática del sidebar en desktop
    if (window.innerWidth > 768) {
        let hoverTimeout;
        
        // Expandir al hacer hover
        sidebar.addEventListener('mouseenter', function() {
            clearTimeout(hoverTimeout);
            if (!body.classList.contains('sidebar-expanded')) {
                body.classList.add('sidebar-expanding');
                setTimeout(() => {
                    body.classList.add('sidebar-expanded');
                    body.classList.remove('sidebar-expanding');
                }, 50);
            }
        });
        
        // Contraer al salir del hover con un pequeño delay
        sidebar.addEventListener('mouseleave', function() {
            hoverTimeout = setTimeout(() => {
                body.classList.remove('sidebar-expanded');
            }, 300);
        });
    }
    
    // Manejar envío de formularios con AJAX (opcional)
    const forms = document.querySelectorAll('form[data-ajax="true"]');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            handleAjaxForm(this);
        });
    });
    
    // Función para manejar formularios AJAX
    function handleAjaxForm(form) {
        const formData = new FormData(form);
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        
        // Mostrar estado de carga
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
        
        fetch(form.action, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert(data.message, 'success');
                if (data.redirect) {
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1000);
                }
            } else {
                showAlert(data.message || 'Error al procesar la solicitud', 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Error de conexión. Inténtalo de nuevo.', 'danger');
        })
        .finally(() => {
            // Restaurar botón
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        });
    }
    
    // Función para mostrar alertas dinámicas
    function showAlert(message, type = 'info') {
        // Remover alertas existentes
        const existingAlerts = document.querySelectorAll('.alert-dynamic');
        existingAlerts.forEach(alert => alert.remove());
        
        // Crear nueva alerta
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dynamic`;
        alert.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span>${message}</span>
                <button type="button" class="btn-close" aria-label="Cerrar">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        // Insertar en el DOM
        const container = document.querySelector('.content') || document.body;
        container.insertBefore(alert, container.firstChild);
        
        // Manejar cierre manual
        const closeBtn = alert.querySelector('.btn-close');
        closeBtn.addEventListener('click', () => {
            alert.remove();
        });
        
        // Auto-ocultar después de 5 segundos
        setTimeout(() => {
            if (alert.parentNode) {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => {
                    alert.remove();
                }, 300);
            }
        }, 5000);
    }
    
    // Función para confirmar acciones destructivas
    window.confirmAction = function(message, callback) {
        if (confirm(message)) {
            callback();
        }
    };
    
    // Validación de formularios en tiempo real
    const inputs = document.querySelectorAll('.form-input');
    inputs.forEach(input => {
        input.addEventListener('blur', function() {
            validateField(this);
        });
        
        input.addEventListener('input', function() {
            // Limpiar errores mientras el usuario escribe
            clearFieldError(this);
        });
    });
    
    // Función para validar un campo
    function validateField(field) {
        const value = field.value.trim();
        let isValid = true;
        let message = '';
        
        // Validaciones básicas
        if (field.required && !value) {
            isValid = false;
            message = 'Este campo es obligatorio';
        } else if (field.type === 'email' && value && !isValidEmail(value)) {
            isValid = false;
            message = 'Ingresa un email válido';
        } else if (field.type === 'password' && value && value.length < 6) {
            isValid = false;
            message = 'La contraseña debe tener al menos 6 caracteres';
        }
        
        // Mostrar/ocultar error
        if (!isValid) {
            showFieldError(field, message);
        } else {
            clearFieldError(field);
        }
        
        return isValid;
    }
    
    // Función para mostrar error en un campo
    function showFieldError(field, message) {
        clearFieldError(field);
        
        field.classList.add('error');
        const errorDiv = document.createElement('div');
        errorDiv.className = 'field-error';
        errorDiv.textContent = message;
        field.parentNode.appendChild(errorDiv);
    }
    
    // Función para limpiar error de un campo
    function clearFieldError(field) {
        field.classList.remove('error');
        const errorDiv = field.parentNode.querySelector('.field-error');
        if (errorDiv) {
            errorDiv.remove();
        }
    }
    
    // Función para validar email
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    // Inicializar tooltips (si hay elementos con data-tooltip)
    const tooltipElements = document.querySelectorAll('[data-tooltip]');
    tooltipElements.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
    
    function showTooltip(e) {
        const tooltip = document.createElement('div');
        tooltip.className = 'tooltip';
        tooltip.textContent = e.target.getAttribute('data-tooltip');
        document.body.appendChild(tooltip);
        
        const rect = e.target.getBoundingClientRect();
        tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
        tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + 'px';
        
        e.target._tooltip = tooltip;
    }
    
    function hideTooltip(e) {
        if (e.target._tooltip) {
            e.target._tooltip.remove();
            delete e.target._tooltip;
        }
    }
    
    // Lazy loading para imágenes (si las hay)
    const images = document.querySelectorAll('img[data-src]');
    if (images.length > 0 && 'IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.removeAttribute('data-src');
                    imageObserver.unobserve(img);
                }
            });
        });
        
        images.forEach(img => imageObserver.observe(img));
    }
});

// Función global para logout con confirmación
function logout() {
    if (confirm('¿Estás seguro de que quieres cerrar sesión?')) {
        window.location.href = 'logout.php';
    }
}

// Funciones para el sidebar (complementar el código existente)
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (sidebar && overlay) {
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
        document.body.classList.toggle('sidebar-open');
    }
}

function openSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (sidebar && overlay) {
        sidebar.classList.add('active');
        overlay.classList.add('active');
        document.body.classList.add('sidebar-open');
    }
}

function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (sidebar && overlay) {
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
        document.body.classList.remove('sidebar-open');
    }
}

// Función para inicializar formularios
function initializeForms() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        // Validación en tiempo real
        const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                validateField(this);
            });
            
            input.addEventListener('input', function() {
                clearFieldError(this);
            });
        });
        
        // Validación al enviar
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
            }
        });
    });
}

// Función para validar un campo individual
function validateField(field) {
    const value = field.value.trim();
    let isValid = true;
    let message = '';
    
    // Validación de campos requeridos
    if (field.hasAttribute('required') && !value) {
        isValid = false;
        message = 'Este campo es requerido';
    }
    
    // Validación de email
    if (field.type === 'email' && value) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
            isValid = false;
            message = 'Por favor ingresa un email válido';
        }
    }
    
    // Validación de contraseña
    if (field.type === 'password' && value && field.hasAttribute('data-min-length')) {
        const minLength = parseInt(field.getAttribute('data-min-length'));
        if (value.length < minLength) {
            isValid = false;
            message = `La contraseña debe tener al menos ${minLength} caracteres`;
        }
    }
    
    // Mostrar/ocultar error
    if (!isValid) {
        showFieldError(field, message);
    } else {
        clearFieldError(field);
    }
    
    return isValid;
}

// Función para mostrar error en un campo
function showFieldError(field, message) {
    clearFieldError(field);
    
    field.classList.add('error');
    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error';
    errorDiv.textContent = message;
    field.parentNode.appendChild(errorDiv);
}

// Función para limpiar error de un campo
function clearFieldError(field) {
    field.classList.remove('error');
    const errorDiv = field.parentNode.querySelector('.field-error');
    if (errorDiv) {
        errorDiv.remove();
    }
}

// Función para validar formulario completo
function validateForm(form) {
    const requiredFields = form.querySelectorAll('input[required], select[required], textarea[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!validateField(field)) {
            isValid = false;
        }
    });
    
    return isValid;
}

// Función para inicializar otros componentes
function initializeOtherComponents() {
    // Inicializar tooltips
    const tooltips = document.querySelectorAll('[data-tooltip]');
    tooltips.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
    
    // Inicializar dropdowns
    const dropdowns = document.querySelectorAll('.dropdown');
    dropdowns.forEach(dropdown => {
        const toggle = dropdown.querySelector('.dropdown-toggle');
        const menu = dropdown.querySelector('.dropdown-menu');
        
        if (toggle && menu) {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                dropdown.classList.toggle('active');
            });
        }
    });
    
    // Cerrar dropdowns al hacer click fuera
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown')) {
            document.querySelectorAll('.dropdown.active').forEach(dropdown => {
                dropdown.classList.remove('active');
            });
        }
    });
}

// Funciones para tooltips
function showTooltip(e) {
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = e.target.getAttribute('data-tooltip');
    document.body.appendChild(tooltip);
    
    const rect = e.target.getBoundingClientRect();
    tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
    tooltip.style.top = rect.bottom + 5 + 'px';
}

function hideTooltip() {
    const tooltip = document.querySelector('.tooltip');
    if (tooltip) {
        tooltip.remove();
    }
}
