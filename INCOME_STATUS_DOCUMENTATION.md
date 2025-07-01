# 📊 Sistema de Status Automático para Ingresos

## 📝 Descripción

Este sistema agrega una columna `status` a la tabla `incomes` que se actualiza automáticamente basándose en el balance entre los ingresos totales y los pagos recibidos. Elimina la necesidad de calcular el status dinámicamente en cada consulta, mejorando el rendimiento y permitiendo filtros y reportes más eficientes.

## 🎯 Estados Disponibles

| Status | Descripción | Condición |
|--------|-------------|-----------|
| `pending` | **Pendiente** | Balance > 0 (se debe dinero) |
| `paid` | **Pagado** | Balance = 0 (completamente pagado) |
| `overpaid` | **Sobrepago** | Balance < 0 (se pagó de más) |

## ⚙️ Instalación

### Opción 1: Script Automático (Recomendado)

```bash
# Ejecutar desde línea de comandos
php apply_income_status_migration.php
```

### Opción 2: Ejecutar desde Navegador

Visita: `http://tu-dominio.com/apply_income_status_migration.php?confirm=yes`

### Opción 3: Manual

```sql
-- Ejecutar el contenido completo del archivo
SOURCE migrate_add_income_status.sql;
```

## 🔧 Funcionalidades Implementadas

### 1. **Columna Status**
- Se agrega `status ENUM('pending', 'paid', 'overpaid')` a la tabla `incomes`
- Valor por defecto: `pending`
- Índice agregado para optimizar consultas con filtros

### 2. **Función de Cálculo**
```sql
-- Calcular status de un ingreso específico
SELECT CalculateIncomeStatus('income_id_aqui');
```

### 3. **Procedimientos de Actualización**
```sql
-- Actualizar status de un ingreso específico
CALL UpdateIncomeStatus('income_id_aqui');

-- Recalcular todos los status (útil para mantenimiento)
CALL RecalculateAllIncomeStatus();
```

### 4. **Triggers Automáticos**
El status se actualiza automáticamente cuando:
- Se agregan/modifican/eliminan líneas de ingreso (`income_lines`)
- Se agregan/modifican/eliminan pagos (`income_payments`)

### 5. **API Endpoints**
```php
// Recalcular status de un ingreso específico
POST api/income/IncomesController.php
{
    "action": "recalculateStatus",
    "id": "income_id_aqui"
}

// Recalcular todos los status
POST api/income/IncomesController.php
{
    "action": "recalculateAllStatus"
}
```

## 📱 Uso en la Interfaz

### Filtros
Los usuarios pueden filtrar ingresos por status:
- **Pendiente**: Ingresos que aún deben pagos
- **Pagado**: Ingresos completamente pagados
- **Sobrepago**: Ingresos con pagos excedentes

### Badges Visuales
```html
<!-- Pendiente -->
<span class="badge badge-warning">Pendiente</span>

<!-- Pagado -->
<span class="badge badge-success">Pagado</span>

<!-- Sobrepago -->
<span class="badge badge-danger">Sobrepago</span>
```

## 🔍 Consultas Útiles

### Ver Estadísticas de Status
```sql
SELECT 
    status,
    COUNT(*) as cantidad,
    ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM incomes), 2) as porcentaje
FROM incomes 
GROUP BY status
ORDER BY status;
```

### Ingresos por Status y Equipo
```sql
SELECT 
    t.name as equipo,
    i.status,
    COUNT(*) as cantidad,
    SUM(i.total_amount) as monto_total
FROM incomes i
JOIN teams t ON i.team_id = t.id
GROUP BY t.name, i.status
ORDER BY t.name, i.status;
```

### Balance Total por Status
```sql
SELECT 
    status,
    COUNT(*) as ingresos,
    SUM(total_amount) as ingresos_totales,
    SUM((
        SELECT COALESCE(SUM(amount), 0) 
        FROM income_payments 
        WHERE income_id = i.id
    )) as pagos_totales,
    SUM(total_amount) - SUM((
        SELECT COALESCE(SUM(amount), 0) 
        FROM income_payments 
        WHERE income_id = i.id
    )) as balance_total
FROM incomes i
GROUP BY status;
```

## 🛠️ Mantenimiento

### Recalcular Todos los Status
Si por alguna razón los status no están sincronizados:

```sql
CALL RecalculateAllIncomeStatus();
```

### Verificar Integridad
```sql
-- Comparar status almacenado vs calculado
SELECT 
    i.id,
    i.invoice_number,
    i.status as status_actual,
    CalculateIncomeStatus(i.id) as status_calculado,
    CASE 
        WHEN i.status = CalculateIncomeStatus(i.id) THEN 'OK'
        ELSE 'DESINCRONIZADO'
    END as estado
FROM incomes i
HAVING estado = 'DESINCRONIZADO';
```

## 📊 Rendimiento

### Ventajas del Nuevo Sistema
- ✅ **Consultas más rápidas**: No se calcula status en tiempo real
- ✅ **Filtros eficientes**: Índice en columna status
- ✅ **Reportes optimizados**: Agregaciones directas sin JOINs complejos
- ✅ **Integridad garantizada**: Triggers automáticos mantienen consistencia

### Antes vs Después
```sql
-- ANTES: Cálculo dinámico (lento en tablas grandes)
SELECT 
    i.*,
    CASE 
        WHEN (ingresos - pagos) > 0 THEN 'pending'
        WHEN (ingresos - pagos) = 0 THEN 'paid'
        ELSE 'overpaid'
    END as status
FROM incomes i
LEFT JOIN (SELECT income_id, SUM(total_amount) as ingresos FROM income_lines GROUP BY income_id) il ON i.id = il.income_id
LEFT JOIN (SELECT income_id, SUM(amount) as pagos FROM income_payments GROUP BY income_id) ip ON i.id = ip.income_id;

-- DESPUÉS: Consulta directa (rápido)
SELECT * FROM incomes WHERE status = 'pending';
```

## 🚨 Rollback (Si es Necesario)

En caso de problemas, ejecutar:

```sql
-- Eliminar triggers
DROP TRIGGER IF EXISTS income_lines_after_insert;
DROP TRIGGER IF EXISTS income_lines_after_update;
DROP TRIGGER IF EXISTS income_lines_after_delete;
DROP TRIGGER IF EXISTS income_payments_after_insert;
DROP TRIGGER IF EXISTS income_payments_after_update;
DROP TRIGGER IF EXISTS income_payments_after_delete;

-- Eliminar procedimientos y funciones
DROP PROCEDURE IF EXISTS UpdateIncomeStatus;
DROP PROCEDURE IF EXISTS RecalculateAllIncomeStatus;
DROP FUNCTION IF EXISTS CalculateIncomeStatus;

-- Eliminar columna (CUIDADO: esto eliminará los datos)
ALTER TABLE incomes DROP COLUMN status;

-- Restaurar desde backup si es necesario
-- RENAME TABLE incomes_backup_[timestamp] TO incomes;
```

## 📋 Verificación Post-Instalación

### 1. Verificar Estructura
```sql
DESCRIBE incomes;
-- Debe mostrar la columna 'status'
```

### 2. Verificar Funciones
```sql
SHOW FUNCTION STATUS WHERE Name = 'CalculateIncomeStatus';
SHOW PROCEDURE STATUS WHERE Name LIKE '%IncomeStatus%';
```

### 3. Verificar Triggers
```sql
SHOW TRIGGERS WHERE `Table` IN ('income_lines', 'income_payments');
```

### 4. Probar Funcionalidad
```sql
-- Crear un ingreso de prueba y verificar que el status se actualiza automáticamente
INSERT INTO incomes (id, team_id, total_amount, invoice_number, income_date) 
VALUES (UUID(), 'team_id_existente', 1000.00, 'TEST-001', '2024-01-15');

-- El status debe ser 'pending' inicialmente
-- Agregar un pago y verificar que se actualiza automáticamente
```

## 🎯 Casos de Uso

### Reportes Gerenciales
- **Ingresos pendientes de pago**: `WHERE status = 'pending'`
- **Flujo de caja mensual**: Agrupar por mes y status
- **Indicadores de cobranza**: Porcentaje de ingresos pagados vs pendientes

### Alertas Automáticas
- Ingresos con más de 30 días en status 'pending'
- Notificaciones de sobrepagos para revisión
- Dashboard de estado de cobranzas

### Filtros de Usuario
- Los usuarios pueden filtrar fácilmente por status
- Búsquedas combinadas (equipo + status + fechas)
- Ordenamiento por status y otros campos

## 📞 Soporte

Si encuentras algún problema:
1. Verificar que todos los triggers estén activos
2. Ejecutar `CALL RecalculateAllIncomeStatus();`
3. Revisar logs de errores de MySQL
4. Contactar al equipo de desarrollo con detalles específicos

---

## ✅ Checklist de Implementación

- [ ] Ejecutar script de migración
- [ ] Verificar que la columna status existe
- [ ] Comprobar que las funciones y triggers funcionan
- [ ] Probar filtros en la interfaz web
- [ ] Verificar que los status se actualizan automáticamente
- [ ] Documentar cualquier customización adicional

**¡El sistema está listo para usar! 🎉** 