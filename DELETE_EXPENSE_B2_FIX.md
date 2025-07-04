# Corrección: Eliminación de Archivos B2 en Delete Expense

## 🔍 Problema Identificado

La función de **eliminar gasto** (botón "Eliminar" en la columna acciones de expenses.php) **NO** estaba eliminando los archivos adjuntos del bucket BackBlaze B2, solo eliminaba:
- ✅ Registros de base de datos
- ✅ Archivos locales (si existían)
- ❌ **Archivos en BackBlaze B2** (se quedaban huérfanos)

## 📊 Análisis del Problema

### Flujo Original (Problemático):
```
1. Usuario hace clic en "Eliminar" gasto
2. Frontend: deleteExpenseConfirmed() → API deleteExpense
3. Backend: deleteExpense() eliminaba solo archivos locales
4. Archivos en BackBlaze B2 quedaban huérfanos
5. Desperdicio de espacio y costos en B2
```

### Código Problemático:
```php
// En deleteExpense() - ANTES
$stmt = $pdo->prepare("SELECT file_path FROM expense_attachments WHERE expense_id = ?");
$stmt->execute([$id]);
$attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($attachments as $attachment) {
    if (file_exists($attachment['file_path'])) {
        unlink($attachment['file_path']); // ❌ Solo archivos locales
    }
}
```

## ✅ Solución Implementada

### Uso de Función Existente:
Ya existía la función `deleteAttachment()` que **SÍ** maneja correctamente archivos B2:
- ✅ Verifica si el archivo tiene `file_key` (es B2)
- ✅ Elimina de BackBlaze B2 usando `B2FileUploader`
- ✅ Elimina archivos locales si no es B2
- ✅ Elimina registro de base de datos

### Código Corregido:
```php
// En deleteExpense() - DESPUÉS
$stmt = $pdo->prepare("SELECT id FROM expense_attachments WHERE expense_id = ?");
$stmt->execute([$id]);
$attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($attachments as $attachment) {
    try {
        deleteAttachment($attachment['id']); // ✅ Usa función que maneja B2
    } catch (Exception $e) {
        error_log("Error eliminando archivo adjunto {$attachment['id']}: " . $e->getMessage());
        // Continúa con el siguiente archivo aunque falle uno
    }
}
```

## 🔧 Cambios Realizados

### 1. Modificación en `deleteExpense()`:
- **Antes**: Lógica propia que solo eliminaba archivos locales
- **Después**: Usa `deleteAttachment()` para cada archivo adjunto

### 2. Eliminación de Código Duplicado:
- Removida línea duplicada que eliminaba registros de BD
- La función `deleteAttachment()` ya se encarga de esto

### 3. Manejo de Errores:
- Agregado try-catch para cada archivo
- Registra errores en log pero continúa eliminando otros archivos
- No falla toda la operación si un archivo individual falla

## 📈 Beneficios

### ✅ Funcionalidad Completa:
- **Archivos B2**: Se eliminan correctamente del bucket
- **Archivos locales**: Se eliminan si existen
- **Base de datos**: Se limpia completamente

### ✅ Ahorro de Costos:
- No más archivos huérfanos en BackBlaze B2
- Reducción significativa en costos de almacenamiento
- Mejor gestión de recursos

### ✅ Consistencia:
- Misma lógica para eliminar archivos individuales y gastos completos
- Manejo uniforme de errores
- Código más mantenible

## 🎯 Casos de Uso

### Escenario 1: Gasto con Archivos B2
```
Gasto con 3 archivos en B2:
- expenses/2024/EXP001_20241201_abc123.jpg
- expenses/2024/EXP001_20241201_def456.pdf
- expenses/2024/EXP001_20241201_ghi789.png

Al eliminar el gasto:
✅ Se eliminan los 3 archivos del bucket B2
✅ Se eliminan los 3 registros de expense_attachments
✅ Se elimina el gasto y todas sus relaciones
```

### Escenario 2: Gasto con Archivos Locales
```
Gasto con archivos locales (legacy):
- uploads/expenses/old_file1.jpg
- uploads/expenses/old_file2.pdf

Al eliminar el gasto:
✅ Se eliminan los archivos locales
✅ Se eliminan los registros de BD
✅ Se elimina el gasto completo
```

### Escenario 3: Gasto Mixto
```
Gasto con archivos B2 y locales:
- BackBlaze B2: expenses/2024/file1.jpg
- Local: uploads/expenses/file2.pdf

Al eliminar el gasto:
✅ Se elimina del bucket B2
✅ Se elimina del sistema local
✅ Limpieza completa
```

## 🔍 Verificación

### Para confirmar que funciona:
1. **Crear gasto de prueba** con archivos adjuntos
2. **Verificar en B2**: Archivos presentes en bucket
3. **Eliminar gasto**: Usar botón "Eliminar" en expenses.php
4. **Verificar en B2**: Archivos eliminados del bucket
5. **Verificar BD**: Registros eliminados de expense_attachments

### Logs de Verificación:
```bash
# Revisar logs de errores si hay problemas
tail -f /path/to/php/error.log | grep "archivo adjunto"
```

## 📝 Notas Técnicas

- **Transacciones**: El proceso usa transacciones de BD para consistencia
- **Manejo de errores**: Errores en archivos individuales no fallan toda la operación
- **Compatibilidad**: Funciona con archivos B2 y locales
- **Rendimiento**: Operación optimizada sin consultas innecesarias

---

**Implementado el**: <?php echo date('Y-m-d H:i:s'); ?>
**Archivo modificado**: `api/expense/ExpenseController.php`
**Función corregida**: `deleteExpense()`
**Problema resuelto**: ✅ **Archivos B2 ahora se eliminan correctamente** 