# Dashboard de Seguridad - Documentación Completa

## Introducción

El Dashboard de Seguridad es una herramienta completa de monitoreo y análisis de seguridad que proporciona visibilidad en tiempo real sobre el estado de seguridad del sistema AP Cuadre. Esta implementación aprovecha todas las funciones de seguridad existentes en el sistema para ofrecer una vista consolidada y profesional.

## Arquitectura del Sistema

### Archivos Implementados

1. **`security_dashboard.php`** - Página principal del dashboard
2. **`security_dashboard_ajax.php`** - Controlador AJAX para datos en tiempo real
3. **`assets/js/security_dashboard.js`** - Funcionalidad JavaScript del frontend
4. **`assets/css/style.css`** - Estilos CSS (añadidos al archivo existente)

### Estructura de Navegación

El dashboard se ha integrado al menú lateral principal con el nombre "Seguridad" y el icono de escudo (shield-alt).

## Características Principales

### 1. Métricas de Seguridad en Tiempo Real

#### Nivel de Seguridad
- **Cálculo automático** basado en múltiples factores
- **Algoritmo de puntuación** que evalúa:
  - Configuración de sesión (30 puntos)
  - Uso de HTTPS (20 puntos)
  - Configuración de cookies (15 puntos cada una)
  - Rate limiting (10 puntos)
  - Headers de seguridad (10 puntos)

**Niveles:**
- **ALTO** (80+ puntos): Configuración segura
- **MEDIO** (60-79 puntos): Mejoras recomendadas
- **BAJO** (<60 puntos): Problemas críticos

#### Intentos Fallidos
- **Conteo en tiempo real** de intentos de login fallidos en la última hora
- **Detección automática** desde la base de datos
- **Alertas visuales** cuando se detectan intentos

#### Alertas Activas
- **Monitoreo de amenazas** en tiempo real
- **Clasificación por severidad**: HIGH, MEDIUM, LOW
- **Detección automática** de ataques de fuerza bruta y enumeración de usuarios

#### IPs Sospechosas
- **Análisis de las últimas 24 horas**
- **Identificación automática** de IPs con comportamiento sospechoso
- **Criterios de detección**: 3+ intentos fallidos

### 2. Configuración de Seguridad

Monitorea el estado de los sistemas de seguridad implementados:

#### Cookies de Sesión
- **HttpOnly**: Previene acceso desde JavaScript
- **Secure**: Solo transmisión por HTTPS
- **SameSite**: Protección contra CSRF
- **Evaluación automática** del nivel de seguridad

#### Protección Anti-Ataques
- **Rate Limiting**: 5 intentos máximo por 15 minutos
- **Timing Attack Protection**: Delays consistentes
- **Monitoring activo** de intentos por IP

#### Headers de Seguridad
- **CSP (Content Security Policy)**: Protección contra XSS
- **X-Frame-Options**: Prevención de clickjacking
- **X-XSS-Protection**: Protección XSS del navegador
- **HSTS**: Strict Transport Security

#### Sistema de Logs
- **Registro de intentos fallidos** en base de datos
- **Tracking de actividad** de usuarios
- **Auditoría completa** de eventos de seguridad

### 3. Alertas de Seguridad

#### Tipos de Alertas Detectadas

**BRUTE_FORCE (Severidad: HIGH)**
- Criterio: 10+ intentos desde la misma IP en 15 minutos
- Acción: Alertas automáticas y logging

**USER_ENUMERATION (Severidad: MEDIUM)**
- Criterio: 5+ usuarios diferentes probados desde la misma IP en 30 minutos
- Acción: Monitoreo y alerta

**SECURITY_CONFIG (Severidad: MEDIUM)**
- Conexión sin HTTPS
- Configuración de cookies insegura

**COOKIE_SECURITY (Severidad: MEDIUM)**
- Cookies sin HttpOnly o Secure
- Configuración de sesión subóptima

### 4. IPs Sospechosas

#### Criterios de Clasificación
- **3+ intentos fallidos** en las últimas 24 horas
- **Múltiples usuarios probados** desde la misma IP
- **Patrones de ataque** identificados

#### Información Mostrada
- **Dirección IP** del atacante
- **Número de intentos** realizados
- **Fecha del último intento**
- **Lista de usuarios probados**

### 5. Información de Sesión

#### Datos en Tiempo Real
- **ID de Sesión** actual
- **Duración de la sesión** en minutos
- **Tiempo hasta expiración**
- **Última actividad** registrada
- **Barra de progreso** visual de expiración

#### Actualización Automática
- Actualización cada **30 segundos**
- **Alertas visuales** cuando quedan menos de 5 minutos
- **Indicadores de estado** claros

### 6. Estadísticas de Acceso

#### Métricas Principales
- **Total de intentos fallidos** (última hora)
- **IPs únicas** que han fallado
- **Usuarios únicos** probados
- **Razones de fallos** categorizadas

#### Análisis de Tendencias
- **Distribución por razones** de fallo
- **Contadores visuales** claros
- **Identificación de patrones** de ataque

## Funcionalidades Administrativas

### 1. Limpiar Intentos Fallidos
- **Eliminación automática** de intentos antiguos (>1 hora)
- **Limpieza de sesión** de contadores de rate limiting
- **Confirmación de usuario** antes de ejecutar
- **Feedback visual** del resultado

### 2. Generar Reporte de Seguridad
- **Exportación completa** en formato JSON
- **Datos incluidos**:
  - Estado de seguridad actual
  - Estadísticas de 24 horas
  - Alertas activas
  - Información de sesión
  - Información del sistema
- **Descarga automática** con timestamp

### 3. Actualización Manual
- **Refresh completo** de todos los datos
- **Recarga de métricas** en tiempo real
- **Sincronización** con base de datos

## Sistema AJAX y Tiempo Real

### Endpoints Disponibles

1. **`getSecurityStatus`** - Estado general de seguridad
2. **`getFailedLoginStats`** - Estadísticas de intentos fallidos
3. **`getSecurityAlerts`** - Alertas activas del sistema
4. **`getSuspiciousIPs`** - Lista de IPs sospechosas
5. **`getSessionInfo`** - Información de sesión actual
6. **`clearFailedAttempts`** - Limpiar registros
7. **`generateSecurityReport`** - Generar reporte

### Actualización Automática
- **Dashboard completo**: Cada 60 segundos
- **Información de sesión**: Cada 30 segundos
- **Métricas en tiempo real** sin interrumpir la experiencia del usuario

## Seguridad y Autenticación

### Verificaciones Implementadas
- **Autenticación requerida** para todos los endpoints
- **Verificación de sesión** con gracia de 5 minutos para APIs
- **Headers de seguridad** en todas las respuestas
- **Validación de entrada** en todos los parámetros

### Protección CSRF
- **Tokens CSRF** implementados donde sea necesario
- **Verificación de origen** en peticiones AJAX
- **Protección contra ataques** de falsificación

## Diseño y UX

### Principios de Diseño
- **Dashboard profesional** con métricas claras
- **Colores intuitivos**: Verde (seguro), Amarillo (advertencia), Rojo (peligro)
- **Iconografía consistente** con Font Awesome
- **Responsive design** para todos los dispositivos

### Estados de Carga
- **Loading spinners** para todas las secciones
- **Estados de error** con opciones de reintento
- **Feedback visual** para todas las acciones
- **Notificaciones toast** para operaciones

### Accesibilidad
- **Contraste adecuado** en todos los elementos
- **Iconos descriptivos** con tooltips
- **Navegación por teclado** soportada
- **Screen reader friendly**

## Integración con el Sistema Existente

### Funciones Utilizadas (config.php)
- `getSessionSecurityStatus()` - Estado de seguridad de sesiones
- `getSessionTimeoutInfo()` - Información de timeout
- `getFailedLoginStats()` - Estadísticas de fallos
- `detectPotentialAttacks()` - Detección de ataques
- `getClientIP()` - Obtención de IP del cliente
- `checkAPIAuthentication()` - Autenticación de APIs

### Base de Datos
- **Tabla `failed_login_attempts`** para tracking de intentos
- **Campos indexados** para consultas rápidas
- **Limpieza automática** de registros antiguos

## Monitoreo y Alertas

### Criterios de Alerta
- **Ataques de fuerza bruta**: 10+ intentos/15min
- **Enumeración de usuarios**: 5+ usuarios/IP/30min
- **Configuración insegura**: HTTPS, cookies, headers
- **Sesiones comprometidas**: Timeouts anómalos

### Niveles de Severidad
- **CRITICAL**: Ataques activos en curso
- **HIGH**: Amenazas inmediatas detectadas
- **MEDIUM**: Problemas de configuración
- **LOW**: Advertencias menores

## Rendimiento y Optimización

### Optimizaciones Implementadas
- **Consultas SQL optimizadas** con índices apropiados
- **Caching de datos** estáticos de configuración
- **Peticiones AJAX paralelas** para carga rápida
- **Debouncing** en actualizaciones automáticas

### Límites y Escalabilidad
- **Máximo 50 IPs sospechosas** mostradas
- **Estadísticas limitadas a 24 horas** para rendimiento
- **Rate limiting** en endpoints de administración
- **Limpieza automática** de datos antiguos

## Troubleshooting

### Problemas Comunes

#### Dashboard no carga datos
1. Verificar que `security_dashboard_ajax.php` sea accesible
2. Comprobar permisos de base de datos
3. Revisar logs de errores PHP
4. Verificar configuración de headers de seguridad

#### Métricas incorrectas
1. Verificar funciones en `config.php`
2. Comprobar tabla `failed_login_attempts`
3. Revisar configuración de sesiones
4. Validar configuración de HTTPS

#### JavaScript no funciona
1. Verificar carga de `security_dashboard.js`
2. Comprobar consola del navegador
3. Verificar CSP headers
4. Revisar configuración de CDN

### Logs y Debugging
- **Error logs PHP**: `/var/log/php_errors.log`
- **Logs de base de datos**: Tabla `failed_login_attempts`
- **Console del navegador**: Para errores JavaScript
- **Network tab**: Para verificar peticiones AJAX

## Mantenimiento

### Tareas Regulares
- **Limpieza de intentos fallidos**: Automática cada hora
- **Revisión de alertas**: Diaria
- **Actualización de configuración**: Según necesidades
- **Backup de logs**: Semanal

### Actualizaciones
- **Nuevas amenazas**: Actualizar criterios de detección
- **Métricas adicionales**: Expandir dashboard según necesidades
- **Optimizaciones**: Mejorar rendimiento periódicamente

## Conclusión

El Dashboard de Seguridad proporciona una vista completa y profesional del estado de seguridad del sistema AP Cuadre. Integra todas las funciones de seguridad existentes en una interfaz unificada y en tiempo real, ofreciendo:

- **Monitoreo proactivo** de amenazas
- **Métricas claras** de seguridad
- **Alertas automáticas** para amenazas
- **Herramientas administrativas** para respuesta
- **Documentación completa** para mantenimiento

Esta implementación fortalece significativamente la postura de seguridad del sistema y proporciona las herramientas necesarias para mantener un entorno seguro y monitoreado. 