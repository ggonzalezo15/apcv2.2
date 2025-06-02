# Sistema de Gestión de Gastos (Expenses)

## Descripción
Sistema completo de gestión de gastos con funcionalidades CRUD, manejo de archivos adjuntos, registro automático de transacciones bancarias y filtros avanzados.

## Características Implementadas

### ✅ Funcionalidades Principales
- **Sistema CRUD completo** para gastos
- **Modal dinámico** con secciones organizadas
- **Selector de fecha** con fecha actual por defecto
- **Selector de equipo** (requerido)
- **Selector de proveedor** (requerido)
- **Selector de cuenta bancaria** (requerido)
- **Líneas de gastos dinámicas** con:
  - Campo de descripción (requerido)
  - Selector de tipo de gasto (requerido)
  - Campo de monto (requerido)
  - Botón para eliminar línea
  - Botón para agregar nueva línea
- **Cálculo automático del balance total**
- **Sistema de archivos adjuntos**:
  - Máximo 4 archivos
  - 2MB por archivo
  - Solo imágenes (JPG, PNG) y PDF
- **Sección de notas** para descripción adicional
- **Registro automático en transacciones** para todas las operaciones

### ✅ Funcionalidades de Tabla
- **Tabla responsive** con scroll horizontal
- **Ordenamiento** por columnas (fecha, monto)
- **Paginación** con selector de elementos por página
- **Filtros**:
  - Por equipo
  - Búsqueda por texto
- **Acciones por fila**:
  - Ver (solo lectura)
  - Editar
  - Eliminar

### ✅ Características Técnicas
- **Compatibilidad con múltiples estructuras de BD**
- **Detección automática** de esquema de base de datos
- **Manejo de errores** robusto
- **Validaciones** del lado cliente y servidor
- **Transacciones** de base de datos para integridad
- **Subida de archivos** segura con validaciones
- **Notificaciones** toast y modales
- **Diseño responsive** para móviles

## Estructura de Archivos

```
├── expenses.php                     # Página principal
├── api/expense/
│   └── ExpenseController.php        # Controlador API
├── assets/js/
│   └── expenses.js                  # JavaScript frontend
├── uploads/expenses/                # Directorio de archivos adjuntos
└── test_expenses.php               # Archivo de pruebas
```

## Estructura de Base de Datos

### Tabla `expenses`
```sql
- id (CHAR(36)) - UUID único
- team_id (CHAR(36)) - Referencia a equipos (requerido)
- vendor_id (CHAR(36)) - Referencia a proveedores (requerido)
- expense_type_id (CHAR(36)) - Tipo de gasto (si es requerido por esquema)
- description (TEXT) - Descripción/notas
- total_amount (DECIMAL(15,2)) - Monto total
- expense_date (DATE) - Fecha del gasto
- bank_account_id (INT) - Cuenta bancaria (requerido)
- created_at, updated_at - Timestamps
- created_by (INT) - Usuario que creó el gasto
- expense_number (VARCHAR(50)) - Número de gasto
- notes (TEXT) - Notas adicionales
```

### Tabla `expense_lines`
```sql
- id (CHAR(36)) - UUID único
- expense_id (CHAR(36)) - Referencia al gasto
- description (TEXT) - Descripción de la línea (requerido)
- expense_type_id (CHAR(36)) - Tipo de gasto (requerido)
- amount (DECIMAL) - Monto de la línea
- quantity, unit_price, total_amount - Para esquemas con detalle
```

### Tabla `expense_attachments`
```sql
- id (CHAR(36)) - UUID único
- expense_id (CHAR(36)) - Referencia al gasto
- filename (VARCHAR(255)) - Nombre del archivo en servidor
- original_filename (VARCHAR(255)) - Nombre original
- file_path (VARCHAR(500)) - Ruta completa del archivo
- file_size (INT) - Tamaño en bytes
- mime_type (VARCHAR(100)) - Tipo MIME
```

### Tabla `transactions`
```sql
- id (CHAR(36)) - UUID único
- bank_account_id (CHAR(36)) - Cuenta bancaria
- expense_id (CHAR(36)) - Referencia al gasto
- type (ENUM) - 'expense'
- amount (DECIMAL) - Monto (negativo para gastos)
- balance_after (DECIMAL) - Balance después de la transacción
- description (TEXT) - Descripción
- transaction_date (DATE) - Fecha de la transacción
```

## API Endpoints

### GET Endpoints
- `?action=getAllExpenses` - Obtener todos los gastos con filtros y paginación
- `?action=getExpenseById&id={id}` - Obtener gasto específico con líneas y adjuntos
- `?action=getTeams` - Obtener lista de equipos
- `?action=getVendors` - Obtener lista de proveedores
- `?action=getBankAccounts` - Obtener lista de cuentas bancarias
- `?action=getExpenseTypes` - Obtener tipos de gastos

### POST Endpoints
- `?action=createExpense` - Crear nuevo gasto
- `?action=updateExpense&id={id}` - Actualizar gasto existente

### DELETE Endpoints
- `?action=deleteExpense&id={id}` - Eliminar gasto

## Parámetros de Filtrado

### getAllExpenses
- `limit` - Número de registros por página
- `offset` - Desplazamiento para paginación
- `sort` - Campo de ordenamiento
- `dir` - Dirección (asc/desc)
- `team` - Filtro por equipo

## Validaciones

### Cliente (JavaScript)
- Campos requeridos
- Formato de archivos
- Tamaño de archivos
- Número máximo de archivos
- Al menos una línea de gasto

### Servidor (PHP)
- Validación de datos requeridos
- Verificación de tipos de archivo
- Límites de tamaño
- Integridad referencial
- Transacciones atómicas

## Características de Seguridad

- **Validación de tipos de archivo** (solo PDF e imágenes)
- **Límites de tamaño** de archivo (2MB)
- **Nombres de archivo únicos** para evitar conflictos
- **Transacciones de BD** para consistencia
- **Escape de datos** para prevenir SQL injection
- **Validación de entrada** en cliente y servidor

## Uso

### Crear un Gasto
1. Hacer clic en "Nuevo Gasto"
2. Llenar información general (fecha, equipo, proveedor, cuenta bancaria)
3. Agregar líneas de gasto con descripción, tipo y monto
4. Subir archivos adjuntos (opcional)
5. Agregar notas (opcional)
6. Guardar

### Editar un Gasto
1. Hacer clic en el icono de editar en la tabla
2. Modificar los campos necesarios
3. Guardar cambios

### Ver un Gasto
1. Hacer clic en el icono de ver en la tabla
2. Se abre en modo solo lectura

### Filtrar Gastos
- Usar el selector de equipo
- Escribir en el campo de búsqueda
- Cambiar el número de elementos por página

## Pruebas

Ejecutar `test_expenses.php` para verificar:
- Estructura de tablas
- Datos de referencia
- Endpoints de API
- Directorio de uploads
- Permisos

## Notas de Implementación

- **Sin nombre de gasto**: El sistema no utiliza un campo nombre para los gastos
- **Sin estados**: Los gastos no tienen estados como pendiente/aprobado/etc
- **Campos obligatorios**: Fecha, equipo, proveedor, cuenta bancaria y líneas de gastos
- **Compatibilidad**: El sistema detecta automáticamente la estructura de la BD
- **Flexibilidad**: Funciona con diferentes esquemas de `expenses` y `expense_lines`
- **Escalabilidad**: Paginación y filtros para manejar grandes volúmenes
- **Mantenibilidad**: Código modular y bien documentado
- **UX**: Interfaz intuitiva con validaciones en tiempo real

## Dependencias

- PHP 7.4+
- MySQL/MariaDB
- JavaScript ES6+
- CSS Grid/Flexbox
- Font Awesome (iconos)

## Integración

El sistema se integra automáticamente con:
- **Equipos** (teams)
- **Proveedores** (vendors)
- **Tipos de gastos** (expense_types)
- **Cuentas bancarias** (bank_accounts)
- **Sistema de transacciones** (transactions)

Todas las operaciones de gastos registran automáticamente transacciones bancarias que actualizan los balances de las cuentas. 