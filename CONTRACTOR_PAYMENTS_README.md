# 💰 Módulo de Pagos a Contratistas

## 📋 Descripción General

El módulo de pagos a contratistas permite registrar, gestionar y hacer seguimiento de todos los pagos realizados a contratistas externos. Se integra completamente con el sistema de cuentas bancarias y transacciones existente.

## 🚀 Funcionalidades Implementadas

### ✅ Gestión de Pagos
- **Registro de pagos**: Crear nuevos pagos a contratistas
- **Edición de pagos**: Modificar pagos existentes (con reversión automática de transacciones)
- **Eliminación de pagos**: Borrar pagos y revertir transacciones bancarias
- **Historial completo**: Ver todos los pagos por contratista

### ✅ Integración Bancaria
- **Validación de fondos**: Verificación automática de saldo disponible
- **Exclusión de crédito**: Solo permite usar cuentas bancarias (no tarjetas de crédito)
- **Actualización de balances**: Actualización automática de saldos de cuentas
- **Registro de transacciones**: Cada pago genera una transacción bancaria

### ✅ Páginas y Funcionalidades
- **Página de detalles del contratista**: Vista completa con información y historial
- **Modal de pagos**: Registro rápido desde la página principal
- **Estadísticas**: Métricas automáticas de pagos por contratista
- **Navegación mejorada**: Botón "View" en la tabla de contratistas

## 🗃️ Estructura de Base de Datos

### Tabla `contractor_payments`
```sql
CREATE TABLE contractor_payments (
    id CHAR(36) PRIMARY KEY,                    -- UUID único del pago
    contractor_id CHAR(36) NOT NULL,            -- Referencia al contratista
    bank_account_id CHAR(36) NOT NULL,          -- Cuenta bancaria origen
    amount DECIMAL(15,2) NOT NULL,              -- Monto del pago
    payment_date DATE NOT NULL,                 -- Fecha del pago
    notes TEXT,                                 -- Notas adicionales
    reference_number VARCHAR(255),              -- Número de referencia
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by VARCHAR(255),                    -- Usuario que creó el pago
    
    -- Claves foráneas
    FOREIGN KEY (contractor_id) REFERENCES contractors(id) ON DELETE RESTRICT,
    FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id) ON DELETE RESTRICT,
    
    -- Índices para optimización
    INDEX idx_contractor_id (contractor_id),
    INDEX idx_bank_account_id (bank_account_id),
    INDEX idx_payment_date (payment_date),
    INDEX idx_created_at (created_at)
);
```

### Integración con `transactions`
Cada pago genera automáticamente una transacción:
- **type**: `'contractor_payment'`
- **amount**: Negativo (representa gasto)
- **description**: "Pago a contratista: {nombre}"
- **balance_after**: Balance actualizado de la cuenta

## 📁 Archivos del Sistema

### Backend (PHP)
```
api/contractor/
├── ContractorController.php           # API existente de contratistas
└── ContractorPaymentController.php    # Nuevo API para pagos
```

### Frontend (Páginas)
```
├── contractors.php                    # Página principal (modificada)
└── contractor_details.php            # Nueva página de detalles
```

### Frontend (JavaScript)
```
assets/js/
└── contractors.js                     # JavaScript (modificado)
```

## 🔌 API Endpoints

### ContractorPaymentController.php

#### `getAllContractorPayments`
- **Descripción**: Obtiene todos los pagos con información del contratista y cuenta
- **Parámetros**: `limit`, `offset`, `sort`, `dir`
- **Respuesta**: Array con pagos y datos relacionados

#### `getContractorPaymentById`
- **Descripción**: Obtiene un pago específico por ID
- **Parámetros**: `id` (required)
- **Respuesta**: Objeto con datos del pago

#### `getPaymentsByContractor`
- **Descripción**: Obtiene todos los pagos de un contratista específico
- **Parámetros**: `contractor_id` (required)
- **Respuesta**: Array de pagos ordenados por fecha

#### `getBankAccountsForPayments`
- **Descripción**: Obtiene cuentas bancarias válidas (excluye crédito)
- **Respuesta**: Array de cuentas disponibles

#### `createContractorPayment`
- **Método**: POST
- **Datos requeridos**: `contractor_id`, `bank_account_id`, `amount`, `payment_date`
- **Datos opcionales**: `notes`, `reference_number`, `created_by`
- **Funcionalidad**: 
  - Valida datos y fondos
  - Crea el pago
  - Actualiza balance bancario
  - Registra transacción

#### `updateContractorPayment`
- **Método**: PUT
- **Parámetros**: `id` (required)
- **Funcionalidad**:
  - Revierte transacción anterior
  - Aplica nuevos valores
  - Actualiza balances
  - Registra nueva transacción

#### `deleteContractorPayment`
- **Método**: DELETE
- **Parámetros**: `id` (required)
- **Funcionalidad**:
  - Revierte dinero a la cuenta
  - Elimina transacción bancaria
  - Elimina registro de pago

## 🎨 Interfaz de Usuario

### Página Principal (`contractors.php`)
- **Botón "Registrar Pago"**: Modal para pagos rápidos
- **Botón "View"**: Navega a detalles del contratista
- **Modal de pago**: Formulario completo con validaciones

### Página de Detalles (`contractor_details.php`)
- **Información del contratista**: Datos completos y edición
- **Historial de pagos**: Tabla con todos los pagos
- **Estadísticas**: Métricas automáticas (total, promedio, último pago)
- **Gestión de pagos**: Crear, editar y eliminar pagos

### Características de UX
- **Validación en tiempo real**: Verificación de fondos y datos
- **Confirmaciones**: Modales de confirmación para acciones destructivas
- **Feedback visual**: Toasts informativos y estados de carga
- **Navegación intuitiva**: Botones de regreso y enlaces directos

## 🔒 Validaciones y Seguridad

### Validaciones del Backend
- **Fondos suficientes**: Verificación antes de crear pagos
- **Cuentas válidas**: Solo cuentas bancarias (no crédito)
- **Datos requeridos**: Validación de campos obligatorios
- **Referencias válidas**: Verificación de contratistas y cuentas existentes

### Transacciones Atómicas
- **Consistencia**: Todas las operaciones usan transacciones DB
- **Rollback automático**: En caso de error, se revierten todos los cambios
- **Integridad referencial**: Claves foráneas protegen la consistencia

### Permisos
- **Autenticación**: Requiere login válido
- **Restricción de eliminación**: Foreign keys con RESTRICT
- **Validación de entrada**: Sanitización de todos los inputs

## 📊 Reportes y Estadísticas

### Por Contratista
- **Total pagado**: Suma de todos los pagos
- **Promedio por pago**: Cálculo automático
- **Último pago**: Fecha del pago más reciente
- **Cantidad de pagos**: Contador total

### Integración con Transacciones
- **Type específico**: `contractor_payment` en tabla transactions
- **Descripción descriptiva**: Incluye nombre del contratista
- **Balance tracking**: Seguimiento automático de balances

## 🚀 Cómo Usar el Sistema

### Registrar un Pago
1. **Desde página principal**: Botón "Registrar Pago" → Modal
2. **Desde detalles**: Botón "Nuevo Pago" en historial
3. **Seleccionar contratista** y cuenta bancaria
4. **Ingresar monto** y fecha de pago
5. **Agregar referencia y notas** (opcional)
6. **Confirmar**: El sistema valida y procesa automáticamente

### Ver Historial
1. **Navegar** a contratistas
2. **Hacer clic** en botón "View" (👁️) del contratista
3. **Revisar** estadísticas y tabla de pagos
4. **Gestionar** pagos desde esta vista

### Editar/Eliminar Pagos
1. **Desde detalles** del contratista
2. **Botones** de editar (✏️) o eliminar (🗑️) en cada pago
3. **Confirmación** automática para eliminaciones
4. **Reversión** automática de transacciones

## 🔗 Integración con Módulos Existentes

### Sistema Bancario
- **Reutiliza** API de cuentas bancarias
- **Actualiza** balances automáticamente
- **Registra** en tabla transactions con type específico

### Sistema de Contratistas
- **Extiende** funcionalidad existente
- **Mantiene** API original intacta
- **Agrega** nueva navegación y vistas

### Sistema de Transacciones
- **Compatible** con estructura existente
- **Agrega** nuevo tipo: `contractor_payment`
- **Integra** con reportes bancarios

## 🛠️ Instalación y Configuración

### Requisitos Previos
- Base de datos con tablas: `contractors`, `bank_accounts`, `transactions`
- Cuentas bancarias configuradas (al menos una no-crédito)
- Contratistas registrados

### Instalación
1. **Crear tabla**: La tabla `contractor_payments` se creó automáticamente
2. **Subir archivos**: Todos los archivos están en su lugar
3. **Verificar permisos**: Acceso a carpetas y base de datos
4. **Probar funcionalidad**: Navegar a contractors.php

### Configuración
- **No requiere configuración adicional**
- **Usa configuración existente** del sistema
- **Integración automática** con módulos existentes

## 🎯 Próximas Mejoras Sugeridas

### Funcionalidades Adicionales
- **Pagos recurrentes**: Programar pagos automáticos
- **Aprobaciones**: Workflow de aprobación para pagos grandes
- **Reportes avanzados**: Gráficos y análisis de tendencias
- **Exportación**: PDF/Excel de historial de pagos

### Integraciones
- **Notificaciones**: Email/SMS al registrar pagos
- **Auditoría**: Log detallado de cambios
- **API externa**: Integración con sistemas bancarios
- **Facturación**: Generar comprobantes de pago

## 🐛 Solución de Problemas

### Errores Comunes
- **"Fondos insuficientes"**: Verificar balance de cuenta seleccionada
- **"Cuenta no válida"**: Asegurar que no sea cuenta de crédito
- **"Contratista no encontrado"**: Verificar que el contratista exista

### Debug
- **Verificar logs** en navegador (F12 → Console)
- **Revisar respuestas** del API en Network tab
- **Validar datos** en base de datos directamente

---

✅ **Estado**: Completamente implementado y funcional  
🚀 **Versión**: 1.0  
📅 **Fecha**: 2024  
👨‍💻 **Desarrollado por**: Assistant & Usuario 