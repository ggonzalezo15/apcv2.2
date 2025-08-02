# 🧹 Sistema de Limpieza de Archivos Huérfanos

## 📋 **Descripción General**

El sistema de limpieza de archivos huérfanos es una suite completa de herramientas diseñadas para mantener la integridad y eficiencia del almacenamiento de archivos en el sistema APCV2.2. Detecta y elimina archivos que han perdido su relación con los registros de gastos, previniendo el desperdicio de espacio y costos innecesarios.

---

## 🎯 **Problemas que Resuelve**

### **1. Archivos Huérfanos en BackBlaze B2**
- **Problema:** Archivos que existen en B2 pero no tienen referencia en la base de datos
- **Causa:** Fallos en procesos de eliminación, errores de red, eliminación manual inconsistente
- **Impacto:** Costos de almacenamiento innecesarios

### **2. Registros Huérfanos en Base de Datos**
- **Problema:** Registros en `expense_attachments` que apuntan a gastos eliminados
- **Causa:** Eliminación de gastos sin limpiar attachments asociados
- **Impacto:** Referencias rotas, inconsistencia de datos

### **3. Archivos Locales Huérfanos**
- **Problema:** Archivos físicos en `/uploads/expenses/` sin registro en BD
- **Causa:** Fallos en subidas, procesos interrumpidos, eliminación manual
- **Impacto:** Uso innecesario de espacio en disco

---

## 🛠️ **Herramientas Disponibles**

### **1. 🌐 Interfaz Web Administrativa**
**Archivo:** `admin_cleanup_orphaned_files.php`

#### **Características:**
- **Interfaz Visual:** Dashboard web con estadísticas en tiempo real
- **Análisis Interactivo:** Visualización detallada de archivos huérfanos
- **Limpieza Segura:** Simulación antes de ejecutar cambios reales
- **Verificación B2:** Comprobación de consistencia con BackBlaze

#### **Modos de Operación:**
```php
// Análisis (Solo Lectura)
?mode=analyze&dry_run=true

// Limpieza Simulada
?mode=cleanup&dry_run=true

// Limpieza Real
?mode=cleanup&dry_run=false

// Limpieza Forzada
?mode=force_cleanup&dry_run=false&confirm=FORCE_DELETE_ALL
```

#### **Seguridad:**
- ✅ Verificación de rol de administrador
- ✅ Confirmación requerida para operaciones destructivas
- ✅ Logging detallado de todas las operaciones
- ✅ Transacciones de BD para rollback en caso de error

---

### **2. 🤖 Limpieza Automática (Cron Job)**
**Archivo:** `automated_cleanup.php`

#### **Características:**
- **Ejecución Automática:** Diseñado para ejecutarse vía cron
- **Procesamiento por Lotes:** Maneja grandes volúmenes sin timeouts
- **Logging Completo:** Registros detallados en archivos de log
- **Notificaciones Email:** Reportes automáticos al administrador

#### **Configuración Recomendada:**
```bash
# Ejecutar cada domingo a las 2:00 AM
0 2 * * 0 /usr/bin/php /path/to/automated_cleanup.php

# Ejecutar diariamente a las 3:00 AM (modo conservador)
0 3 * * * /usr/bin/php /path/to/automated_cleanup.php
```

#### **Configuración del Script:**
```php
$CONFIG = [
    'max_execution_time' => 300,     // 5 minutos máximo
    'max_files_per_run' => 100,      // Máximo 100 archivos por ejecución
    'min_age_days' => 7,             // Solo eliminar archivos de más de 7 días
    'dry_run' => false,              // Cambiar a true para solo simular
    'email_report' => true,          // Enviar reporte por email
    'admin_email' => 'admin@tudominio.com'
];
```

---

### **3. 🖥️ Herramienta CLI**
**Archivo:** `cleanup_cli.php`

#### **Características:**
- **Flexibilidad Total:** Múltiples opciones y modificadores
- **Salida Colorizada:** Interfaz amigable con códigos de color
- **Procesamiento por Chunks:** Evita memory overflow
- **Información Detallada:** Modo verbose para debugging

#### **Comandos Principales:**
```bash
# Mostrar ayuda
php cleanup_cli.php --help

# Analizar archivos huérfanos (solo lectura)
php cleanup_cli.php --analyze

# Mostrar estadísticas del sistema
php cleanup_cli.php --stats

# Simular limpieza
php cleanup_cli.php --cleanup --dry-run

# Ejecutar limpieza real
php cleanup_cli.php --cleanup --force

# Limpieza con parámetros personalizados
php cleanup_cli.php --cleanup --force --max-files=50 --min-age=14 --verbose

# Verificar consistencia con B2
php cleanup_cli.php --verify-b2 --verbose
```

#### **Opciones Disponibles:**
| Opción | Descripción |
|--------|-------------|
| `--analyze` | Analizar archivos huérfanos (solo lectura) |
| `--cleanup` | Ejecutar limpieza |
| `--stats` | Mostrar estadísticas del sistema |
| `--verify-b2` | Verificar consistencia con BackBlaze B2 |
| `--dry-run` | Simular operaciones sin ejecutar cambios |
| `--force` | Ejecutar limpieza real (usar con precaución) |
| `--max-files=N` | Máximo número de archivos a procesar |
| `--min-age=N` | Edad mínima en días para considerar archivos |
| `--verbose` | Mostrar información detallada |

---

## 🔍 **Tipos de Archivos Huérfanos Detectados**

### **1. Registros en BD sin Gasto Asociado**
```sql
SELECT ea.* 
FROM expense_attachments ea 
LEFT JOIN expenses e ON ea.expense_id = e.id 
WHERE e.id IS NULL
```
**Causa:** Gastos eliminados sin limpiar attachments

### **2. Archivos Locales sin Registro en BD**
- **Ubicación:** `/uploads/expenses/`
- **Detección:** Archivos físicos sin entrada en `expense_attachments`
- **Causa:** Fallos en procesos de subida o eliminación manual

### **3. Registros BD sin Archivo en B2**
```sql
SELECT * FROM expense_attachments 
WHERE file_key IS NOT NULL 
AND file_key != ''
-- Verificar existencia en B2 via API
```
**Causa:** Fallos en eliminación de B2 o eliminación manual

### **4. Archivos B2 sin Registro en BD**
- **Detección:** Via API de BackBlaze B2
- **Limitación:** Solo verificación muestral para evitar costos de API
- **Causa:** Eliminación inconsistente de registros de BD

---

## 📊 **Estadísticas y Métricas**

### **Métricas Principales:**
- **Total Attachments:** Número total de registros en BD
- **Archivos B2:** Attachments con `file_key` no nulo
- **Archivos Locales:** Attachments con `file_path` no nulo
- **Registros Huérfanos:** Attachments sin gasto asociado
- **Espacio Recuperable:** Estimación basada en `file_size`

### **Ejemplo de Salida:**
```
┌─────────────────────────────────────┬─────────────┐
│ Total Attachments en BD             │      1,247  │
│ Archivos en BackBlaze B2            │        892  │
│ Archivos Locales                    │        355  │
│ Total Gastos                        │        634  │
│ Registros Huérfanos                 │         23  │
│ Tamaño Total Estimado               │   15.7 MB   │
└─────────────────────────────────────┴─────────────┘
```

---

## 🔒 **Seguridad y Protecciones**

### **1. Verificación de Permisos**
```php
// Verificar autenticación
checkAPIAuthentication();

// Verificar rol de administrador
if ($userRole !== 'admin' && $userRole !== 'administrator') {
    die("❌ ERROR: Solo los administradores pueden ejecutar este script\n");
}
```

### **2. Confirmaciones Múltiples**
- **Confirmación de Usuario:** Para operaciones destructivas
- **Dry Run Obligatorio:** Para operaciones críticas
- **Edad Mínima:** Solo procesar archivos antiguos (default: 7 días)

### **3. Transacciones de Base de Datos**
```php
try {
    $pdo->beginTransaction();
    // Operaciones de limpieza
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    // Manejo de errores
}
```

### **4. Logging Completo**
- **Todas las Operaciones:** Registro detallado de cada acción
- **Rotación de Logs:** Logs diarios con rotación automática
- **Niveles de Log:** INFO, WARNING, ERROR para clasificación

---

## 📈 **Beneficios del Sistema**

### **1. Eficiencia de Costos**
- ✅ **Reducción de Costos B2:** Eliminación de archivos innecesarios
- ✅ **Optimización de Espacio:** Liberación de espacio en disco local
- ✅ **Prevención de Crecimiento:** Control proactivo de almacenamiento

### **2. Integridad de Datos**
- ✅ **Consistencia BD-Storage:** Sincronización entre registros y archivos
- ✅ **Eliminación de Referencias Rotas:** Limpieza de registros huérfanos
- ✅ **Verificación Automática:** Detección temprana de inconsistencias

### **3. Mantenimiento Simplificado**
- ✅ **Automatización:** Procesos automáticos sin intervención manual
- ✅ **Monitoreo:** Reportes regulares del estado del sistema
- ✅ **Escalabilidad:** Procesamiento eficiente de grandes volúmenes

---

## 🚀 **Implementación y Uso**

### **1. Configuración Inicial**

#### **Crear Directorio de Logs:**
```bash
mkdir -p logs
chmod 755 logs
```

#### **Configurar Permisos:**
```bash
chmod +x cleanup_cli.php
chmod 644 admin_cleanup_orphaned_files.php
chmod 644 automated_cleanup.php
```

#### **Configurar Cron Job:**
```bash
# Editar crontab
crontab -e

# Agregar línea para limpieza semanal
0 2 * * 0 /usr/bin/php /path/to/automated_cleanup.php
```

### **2. Uso Recomendado**

#### **Flujo de Trabajo Sugerido:**
```bash
# 1. Verificar estadísticas
php cleanup_cli.php --stats

# 2. Analizar archivos huérfanos
php cleanup_cli.php --analyze --verbose

# 3. Simular limpieza
php cleanup_cli.php --cleanup --dry-run --verbose

# 4. Ejecutar limpieza real
php cleanup_cli.php --cleanup --force

# 5. Verificar resultados
php cleanup_cli.php --stats
```

#### **Monitoreo Regular:**
```bash
# Chequeo diario rápido
php cleanup_cli.php --stats

# Análisis semanal detallado
php cleanup_cli.php --analyze --verbose

# Limpieza mensual
php cleanup_cli.php --cleanup --force --min-age=30
```

---

## 📧 **Reportes y Notificaciones**

### **Reporte de Limpieza Automática:**
```
🤖 REPORTE DE LIMPIEZA AUTOMÁTICA
======================================

⏰ Tiempo de ejecución: 45.2s
📅 Fecha: 2025-08-02 02:00:15

📊 ESTADÍSTICAS:
• Registros BD encontrados/limpiados: 23/23
• Archivos locales encontrados/limpiados: 12/12
• Archivos B2 verificados/limpiados: 20/3
• Espacio recuperado: 45.7 MB
• Errores: 0

🔗 Para más detalles, revisa los logs del sistema.
```

### **Configuración de Email:**
```php
$CONFIG = [
    'email_report' => true,
    'admin_email' => 'admin@tudominio.com',
    'smtp_config' => [
        'host' => 'smtp.tudominio.com',
        'port' => 587,
        'username' => 'sistema@tudominio.com',
        'password' => 'tu_password'
    ]
];
```

---

## 🎯 **Próximas Mejoras**

### **Funcionalidades Planificadas:**
1. **Dashboard de Métricas:** Interfaz web con gráficos de tendencias
2. **Alertas Proactivas:** Notificaciones cuando se detecten anomalías
3. **Limpieza Inteligente:** Machine learning para detectar patrones
4. **API REST:** Endpoints para integración con otros sistemas
5. **Backup Automático:** Respaldo antes de eliminaciones masivas

### **Optimizaciones Técnicas:**
1. **Procesamiento Paralelo:** Múltiples hilos para mejor rendimiento
2. **Cache Inteligente:** Reducción de consultas repetitivas
3. **Compresión de Logs:** Manejo eficiente de archivos de log
4. **Health Checks:** Verificaciones automáticas de integridad

---

## 🛡️ **Troubleshooting**

### **Problemas Comunes:**

#### **1. Error de Permisos**
```bash
# Verificar permisos de archivos
ls -la cleanup_cli.php
chmod +x cleanup_cli.php
```

#### **2. Error de Conexión a BD**
```php
// Verificar configuración en config.php
try {
    $pdo = getConnection();
    echo "✅ Conexión exitosa\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
```

#### **3. Error de Conexión B2**
```php
// Verificar credenciales B2
require_once 'includes/B2FileUploader.php';
try {
    $uploader = new B2FileUploader();
    echo "✅ B2 configurado correctamente\n";
} catch (Exception $e) {
    echo "❌ Error B2: " . $e->getMessage() . "\n";
}
```

#### **4. Timeout en Ejecución**
```php
// Ajustar configuración
$CONFIG['max_execution_time'] = 600; // 10 minutos
$CONFIG['max_files_per_run'] = 50;   // Procesar menos archivos
```

---

## 📚 **Referencias**

- **BackBlaze B2 API:** [Documentación oficial](https://www.backblaze.com/b2/docs/)
- **PHP PDO:** [Manual de PHP](https://www.php.net/manual/en/book.pdo.php)
- **Cron Jobs:** [Guía de configuración](https://crontab.guru/)
- **File System PHP:** [Funciones de archivos](https://www.php.net/manual/en/ref.filesystem.php)

---

## 🎉 **Conclusión**

El sistema de limpieza de archivos huérfanos proporciona una solución completa para mantener la integridad y eficiencia del almacenamiento en APCV2.2. Con herramientas para análisis, limpieza automatizada y monitoreo continuo, garantiza que el sistema permanezca limpio, eficiente y libre de archivos innecesarios.

**¡El mantenimiento regular del sistema nunca fue tan fácil!** 🚀
