# 📊 Análisis de Tablas de Ingresos

## 📋 Resumen Ejecutivo
Basado en el análisis de la base de datos, el sistema de ingresos tiene una estructura bien definida con **3 tablas principales** que manejan proyectos/ingresos, pagos y líneas de detalle.

---

## 🗃️ Estructura de Tablas

### 1. **INCOMES** (Tabla Principal)
**Propósito:** Almacena información de proyectos/contratos que generan ingresos

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | char(36) | UUID primario |
| `team_id` | char(36) | Referencia al equipo |
| `job_type_id` | char(36) | Tipo de trabajo/proyecto |
| `name` | varchar(255) | Nombre del proyecto |
| `description` | text | Descripción detallada |
| `total_amount` | decimal(15,2) | Monto total del proyecto |
| `status` | enum | Estado: pending, in_progress, completed, cancelled |
| `start_date` | date | Fecha de inicio |
| `end_date` | date | Fecha de finalización |
| `created_at` | timestamp | Fecha de creación |
| `updated_at` | timestamp | Última actualización |

**📊 Registros actuales:** 1  
**🔑 Índices:** team_id, job_type_id, status, start_date, end_date

---

### 2. **INCOME_PAYMENTS** (Pagos)
**Propósito:** Gestiona los pagos recibidos por cada proyecto

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | char(36) | UUID primario |
| `income_id` | char(36) | Referencia al ingreso |
| `payment_type_id` | char(36) | Tipo de pago |
| `bank_account_id` | char(36) | Cuenta bancaria receptora |
| `amount` | decimal(15,2) | Monto del pago |
| `payment_date` | date | Fecha del pago |
| `reference_number` | varchar(255) | Número de referencia |
| `notes` | text | Notas adicionales |
| `created_at` | timestamp | Fecha de creación |
| `updated_at` | timestamp | Última actualización |

**📊 Registros actuales:** 1  
**🔑 Índices:** income_id, payment_type_id, bank_account_id, payment_date

---

### 3. **INCOME_LINES** (Líneas de Detalle)
**Propósito:** Desglose detallado de servicios/productos por proyecto

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | char(36) | UUID primario |
| `income_id` | char(36) | Referencia al ingreso |
| `description` | text | Descripción del servicio/producto |
| `quantity` | decimal(10,2) | Cantidad |
| `unit_price` | decimal(15,2) | Precio unitario |
| `total_amount` | decimal(15,2) | Monto total de la línea |
| `created_at` | timestamp | Fecha de creación |
| `updated_at` | timestamp | Última actualización |

**📊 Registros actuales:** 3  
**🔑 Índices:** income_id

---

## 🔗 Relaciones Identificadas

```
INCOMES (1) ←→ (N) INCOME_PAYMENTS
    ↓
INCOMES (1) ←→ (N) INCOME_LINES

Relaciones externas:
- incomes.team_id → teams.id
- incomes.job_type_id → job_types.id
- income_payments.payment_type_id → payment_types.id
- income_payments.bank_account_id → bank_accounts.id
```

---

## 📊 Datos de Ejemplo Encontrados

### Proyecto Ejemplo:
- **Nombre:** "Proyecto Web E-commerce"
- **Descripción:** Desarrollo de plataforma de comercio electrónico
- **Monto Total:** $75,000.00
- **Estado:** in_progress
- **Período:** 2024-01-01 a 2024-03-31

### Pago Registrado:
- **Monto:** $37,500.00 (50% del total)
- **Fecha:** 2024-01-15
- **Referencia:** TRF20240115001
- **Nota:** "Primer pago del 50%"

### Líneas de Detalle:
1. Diseño y desarrollo frontend - $30,000.00
2. Desarrollo backend y API - $25,000.00
3. Integración de pagos - $20,000.00
**Total:** $75,000.00 ✅

---

## 🎯 Funcionalidades Sugeridas para el Módulo

### 📋 **Gestión de Proyectos/Ingresos**
- [x] Crear, editar, eliminar proyectos
- [x] Cambiar estado del proyecto
- [x] Asignar equipo y tipo de trabajo
- [x] Establecer fechas y montos

### 💰 **Gestión de Pagos**
- [x] Registrar pagos recibidos
- [x] Asociar a cuentas bancarias
- [x] Tipos de pago flexibles
- [x] Referencias y notas

### 📝 **Líneas de Detalle**
- [x] Desglose de servicios/productos
- [x] Cálculo automático de totales
- [x] Cantidades y precios unitarios

### 📊 **Reportes y Analytics**
- [ ] Dashboard de ingresos
- [ ] Seguimiento de pagos pendientes
- [ ] Análisis por equipo/tipo de trabajo
- [ ] Proyección de ingresos

### 🔍 **Funcionalidades Avanzadas**
- [ ] Paginación y sorting (como expenses)
- [ ] Filtros por fecha, estado, equipo
- [ ] Búsqueda por texto
- [ ] Exportación de reportes

---

## 🚀 Plan de Implementación Sugerido

### **Fase 1:** Páginas Principales
1. `incomes.php` - Lista de proyectos con paginación/sorting
2. `income_details.php` - Detalles del proyecto + pagos + líneas

### **Fase 2:** API Controllers
1. `IncomeController.php` - CRUD de proyectos
2. `IncomePaymentController.php` - Gestión de pagos
3. `IncomeLineController.php` - Gestión de líneas

### **Fase 3:** Funcionalidades Avanzadas
1. Dashboard de analytics
2. Reportes y exportación
3. Notificaciones de pagos pendientes

---

## ✅ Próximos Pasos
1. **Validar** la estructura con el usuario
2. **Decidir** qué funcionalidades implementar primero
3. **Comenzar** con la página principal de ingresos
4. **Seguir** el patrón establecido en expenses/contractors

---

*Análisis generado el: 2024-01-XX*  
*Basado en: test_income_tables_structure.php* 