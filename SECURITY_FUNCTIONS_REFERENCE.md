# 🔐 Referencia Rápida de Funciones de Seguridad

## 📋 Funciones Implementadas

### **1. Autenticación y Sesiones**
```php
// Verificar si usuario está logueado
isLoggedIn() // bool

// Verificar timeout de sesión
checkSessionTimeout() // bool

// Obtener información de timeout
getSessionTimeoutInfo() // array

// Obtener estado de seguridad de sesión
getSessionSecurityStatus() // array
```

### **2. Protección contra Ataques**
```php
// Prevenir timing attacks
preventTimingAttack($minDelay = 100000) // void

// Verificar rate limiting
checkLoginAttempts($identifier, $maxAttempts = 5, $timeWindow = 900) // bool

// Registrar intento fallido
recordFailedLogin($identifier, $username = null, $reason = 'Invalid credentials') // void

// Limpiar intentos tras éxito
clearLoginAttempts($identifier) // void
```

### **3. Registro y Análisis**
```php
// Registrar intento fallido en BD
logFailedLoginAttempt($identifier, $username, $reason) // bool

// Obtener estadísticas de intentos fallidos
getFailedLoginStats($timeWindow = 3600) // array

// Detectar ataques potenciales
detectPotentialAttacks() // array
```

### **4. Utilidades de Seguridad**
```php
// Obtener IP real del cliente
getClientIP() // string

// Establecer headers de seguridad
setSecurityHeaders() // void

// Generar token CSRF
generateCSRFToken() // string

// Verificar token CSRF
verifyCSRFToken($token) // bool
```

---

## 🛡️ Protecciones Activas

### **Timing Attack Protection**
- Hash dummy para usuarios inexistentes
- Delay consistente de 100ms mínimo
- Tiempo de respuesta uniforme

### **Rate Limiting**
- 5 intentos fallidos por IP/usuario
- Bloqueo de 15 minutos
- Contador automático en sesión

### **Failed Login Logging**
- Registro completo en base de datos
- IP, user-agent, timestamp, razón
- Análisis forense disponible

### **HTTP Security Headers**
- Content Security Policy
- X-Frame-Options: DENY
- X-Content-Type-Options: nosniff
- X-XSS-Protection: 1; mode=block
- Strict-Transport-Security (HTTPS)

### **Secure Session Cookies**
- HttpOnly: true
- Secure: auto-detectado
- SameSite: Strict
- Regeneración automática

---

## 📊 Análisis y Monitoreo

### **Estadísticas Disponibles**
```php
$stats = getFailedLoginStats(3600);
// Retorna:
// - totals: total, unique_ips, unique_usernames
// - by_reason: estadísticas por tipo de error
// - suspicious_ips: IPs con ≥3 intentos
```

### **Detección de Ataques**
```php
$alerts = detectPotentialAttacks();
// Detecta:
// - BRUTE_FORCE: ≥10 intentos en 15 min
// - USER_ENUMERATION: ≥5 usuarios desde misma IP
```

---

## 🚨 Tipos de Alertas

### **HIGH Severity**
- Ataques de fuerza bruta (≥10 intentos/15min)
- Múltiples IPs coordinadas

### **MEDIUM Severity**
- Enumeración de usuarios (≥5 usuarios/IP)
- Patrones sospechosos

---

## 🔧 Configuración

### **Variables Principales**
```php
SESSION_TIMEOUT = 3600;        // 1 hora
MAX_LOGIN_ATTEMPTS = 5;        // Intentos máximos
LOGIN_LOCKOUT_TIME = 900;      // 15 minutos
TIMING_ATTACK_DELAY = 100000;  // 100ms
```

### **Tabla de Base de Datos**
```sql
failed_login_attempts (
    id, ip_address, username, user_agent,
    reason, identifier_hash, attempt_time, session_id
)
```

---

## 📈 Métricas de Seguridad

### **Consultas Útiles**
```sql
-- Intentos fallidos por día
SELECT DATE(attempt_time) as date, COUNT(*) as attempts
FROM failed_login_attempts 
WHERE attempt_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY date;

-- IPs más activas
SELECT ip_address, COUNT(*) as attempts
FROM failed_login_attempts 
WHERE attempt_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY ip_address
ORDER BY attempts DESC;
```

---

## 🎯 Ejemplo de Uso

### **En página de login:**
```php
// Verificar rate limiting
$clientId = $_SERVER['REMOTE_ADDR'] . '_' . $username;
if (!checkLoginAttempts($clientId)) {
    $error = 'Demasiados intentos fallidos. Intenta en 15 minutos.';
    preventTimingAttack();
    exit;
}

// Si falla la autenticación
if (!$validCredentials) {
    recordFailedLogin($clientId, $username, 'Invalid credentials');
    preventTimingAttack();
}
```

### **En dashboard de seguridad:**
```php
// Verificar estado de seguridad
$securityStatus = getSessionSecurityStatus();
$stats = getFailedLoginStats(3600);
$alerts = detectPotentialAttacks();

echo "Nivel de seguridad: " . $securityStatus['security_level'];
echo "Intentos fallidos última hora: " . $stats['totals']['total'];
echo "Alertas activas: " . count($alerts);
```

---

## 🔍 Resolución de Problemas

### **Verificar Implementación**
1. Comprobar headers HTTP en DevTools
2. Probar rate limiting con intentos fallidos
3. Verificar timeout de sesión
4. Revisar logs de intentos fallidos

### **Archivos de Test (eliminar en producción)**
- `session_security_test.php`
- `session_timeout_test.php`
- `session_timeout_quick_test.php`
- `*_ajax.php`

---

## ✅ Checklist de Producción

- [ ] Eliminar archivos de test
- [ ] Configurar HTTPS
- [ ] Verificar headers de seguridad
- [ ] Configurar limpieza automática de logs
- [ ] Implementar monitoreo de alertas
- [ ] Documentar procedimientos de respuesta

---

**🔐 Estado: Sistema completamente funcional con nivel de seguridad HIGH** 