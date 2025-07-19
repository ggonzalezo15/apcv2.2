/**
 * ==============================================
 * UTILIDADES API - ESTÁNDAR DE LA INDUSTRIA
 * ==============================================
 * 
 * Funciones universales para manejar respuestas HTTP
 * basadas en status codes (estándar de la industria)
 */

/**
 * Maneja respuestas HTTP de manera estándar
 * @param {Response} response - Objeto Response de fetch
 * @returns {Promise} - Promesa con datos o error
 */
async function handleResponse(response) {
    // Verificar si la respuesta es exitosa (200-299)
    if (!response.ok) {
        // Intentar obtener mensaje de error del backend
        let errorMessage = `HTTP Error ${response.status}: ${response.statusText}`;
        
        try {
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                const errorData = await response.json();
                errorMessage = errorData.error || errorData.message || errorMessage;
            }
        } catch (e) {
            // Si no se puede parsear el JSON, usar el mensaje HTTP por defecto
        }
        
        throw new Error(errorMessage);
    }

    // Verificar si hay contenido para parsear
    const contentType = response.headers.get('content-type');
    if (contentType && contentType.includes('application/json')) {
        const data = await response.json();
        
        // Verificar si el backend devuelve errores en JSON con status 200
        // (para compatibilidad con código existente)
        if (data && data.error) {
            throw new Error(data.error);
        }
        
        return data;
    }
    
    // Para respuestas 204 No Content o respuestas sin JSON
    return null;
}

/**
 * Wrapper para realizar peticiones HTTP con manejo estándar
 * @param {string} url - URL del endpoint
 * @param {object} options - Opciones de fetch
 * @returns {Promise} - Promesa con datos o error
 */
async function apiRequest(url, options = {}) {
    try {
        const response = await fetch(url, options);
        return await handleResponse(response);
    } catch (error) {
        // Re-lanzar el error para que sea manejado por el código llamador
        throw error;
    }
}

/**
 * Método GET
 * @param {string} url - URL del endpoint
 * @param {object} options - Opciones adicionales
 * @returns {Promise} - Promesa con datos
 */
async function apiGet(url, options = {}) {
    return apiRequest(url, {
        method: 'GET',
        ...options
    });
}

/**
 * Método POST
 * @param {string} url - URL del endpoint
 * @param {object} data - Datos a enviar
 * @param {object} options - Opciones adicionales
 * @returns {Promise} - Promesa con datos
 */
async function apiPost(url, data, options = {}) {
    return apiRequest(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            ...options.headers
        },
        body: JSON.stringify(data),
        ...options
    });
}

/**
 * Método PUT
 * @param {string} url - URL del endpoint
 * @param {object} data - Datos a enviar
 * @param {object} options - Opciones adicionales
 * @returns {Promise} - Promesa con datos
 */
async function apiPut(url, data, options = {}) {
    return apiRequest(url, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            ...options.headers
        },
        body: JSON.stringify(data),
        ...options
    });
}

/**
 * Método DELETE
 * @param {string} url - URL del endpoint
 * @param {object} options - Opciones adicionales
 * @returns {Promise} - Promesa con datos
 */
async function apiDelete(url, options = {}) {
    return apiRequest(url, {
        method: 'DELETE',
        ...options
    });
}

/**
 * Función auxiliar para mostrar toast basado en resultado de API
 * @param {function} apiCall - Función que realiza la llamada API
 * @param {string} successMessage - Mensaje de éxito
 * @param {string} errorMessage - Mensaje de error (opcional)
 * @param {function} onSuccess - Callback de éxito (opcional)
 * @param {function} onError - Callback de error (opcional)
 */
async function handleApiCall(apiCall, successMessage, errorMessage = 'Ocurrió un error', onSuccess = null, onError = null) {
    try {
        const result = await apiCall();
        
        // Mostrar toast de éxito
        if (typeof showToast === 'function') {
            showToast(successMessage, 'success');
        }
        
        // Ejecutar callback de éxito si se proporciona
        if (onSuccess && typeof onSuccess === 'function') {
            onSuccess(result);
        }
        
        return result;
        
    } catch (error) {
        // Mostrar toast de error
        if (typeof showToast === 'function') {
            showToast(error.message || errorMessage, 'error');
        }
        
        // Ejecutar callback de error si se proporciona
        if (onError && typeof onError === 'function') {
            onError(error);
        }
        
        throw error; // Re-lanzar para manejo adicional si es necesario
    }
}

/**
 * Función de compatibilidad para migrar código existente gradualmente
 * Convierte el estilo antiguo al nuevo estilo
 * @param {string} url - URL del endpoint
 * @param {object} options - Opciones de fetch
 * @returns {Promise} - Promesa compatible con el código existente
 */
async function legacyFetch(url, options = {}) {
    try {
        const response = await fetch(url, options);
        const result = await handleResponse(response);
        
        // Devolver en formato compatible con código existente
        return {
            success: true,
            data: result,
            message: result && result.message || 'Operación exitosa'
        };
        
    } catch (error) {
        // Devolver error en formato compatible
        return {
            success: false,
            error: error.message,
            message: error.message
        };
    }
}

// Exportar funciones para uso global
window.ApiUtils = {
    request: apiRequest,
    get: apiGet,
    post: apiPost,
    put: apiPut,
    delete: apiDelete,
    handleResponse: handleResponse,
    handleApiCall: handleApiCall,
    legacyFetch: legacyFetch
};

// Mantener compatibilidad con código existente
window.handleResponse = handleResponse;
window.apiRequest = apiRequest;
window.handleApiCall = handleApiCall; 