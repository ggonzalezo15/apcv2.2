# 📦 INSTALACIÓN DE BASE DE DATOS LIMPIA

## 🎯 PROPÓSITO
Este paquete contiene un esquema limpio de la base de datos `cloude_apcuadre` después de la limpieza automatizada, listo para ser instalado en una nueva máquina.

## 📁 ARCHIVOS INCLUIDOS
- `clean_schema_2025_07_04_21_11_09.sql` - Esquema SQL completo
- `install_database.php` - Instalador automatizado
- `README_INSTALLATION.md` - Este archivo

## 🚀 INSTALACIÓN RÁPIDA

### Opción 1: Instalador Automatizado (Recomendado)
```bash
php install_database.php
```

### Opción 2: Instalación Manual
```bash
mysql -u root -p < clean_schema_2025_07_04_21_11_09.sql
```

## ⚙️ CONFIGURACIÓN PREVIA

### 1. Requisitos del Sistema
- PHP 7.4 o superior
- MySQL 5.7 o superior
- Extensión PDO MySQL habilitada

### 2. Configurar Base de Datos
```sql
-- Crear base de datos si no existe
CREATE DATABASE IF NOT EXISTS cloude_apcuadre;

-- Crear usuario (opcional)
CREATE USER "apcuadre"@"localhost" IDENTIFIED BY "tu_password";
GRANT ALL PRIVILEGES ON cloude_apcuadre.* TO "apcuadre"@"localhost";
FLUSH PRIVILEGES;
```

### 3. Configurar Archivo config.php
```php
define("DB_HOST", "localhost");
define("DB_USER", "root");
define("DB_PASS", "tu_password");
define("DB_NAME", "cloude_apcuadre");
```

## 🔧 PROCESO DE INSTALACIÓN

### PASO 1: Descargar Archivos
Copie todos los archivos de instalación a su servidor.

### PASO 2: Configurar Variables
Edite `install_database.php` con sus credenciales de MySQL:
```php
$config = [
    "host" => "localhost",
    "username" => "root", 
    "password" => "SU_PASSWORD",
    "database" => "cloude_apcuadre"
];
```

### PASO 3: Ejecutar Instalación
```bash
php install_database.php
```

### PASO 4: Verificar Instalación
- Acceda al sistema web
- Login: admin / password (cambiar inmediatamente)
- Verifique todas las funcionalidades

## 📊 CONTENIDO DEL ESQUEMA

### Tablas Principales
- `users` - Usuarios del sistema
- `expenses` - Gastos
- `incomes` - Ingresos
- `contractors` - Contratistas
- `vendors` - Proveedores
- `bank_accounts` - Cuentas bancarias

### Tablas de Configuración
- `teams` - Equipos
- `job_types` - Tipos de trabajo
- `payment_types` - Tipos de pago
- `expense_types` - Tipos de gasto
- `expense_categories` - Categorías de gasto

### Tablas Operacionales
- `expense_lines` - Líneas de gasto
- `expense_attachments` - Archivos adjuntos
- `income_lines` - Líneas de ingreso
- `income_payments` - Pagos de ingreso
- `contractor_payments` - Pagos a contratistas
- `transactions` - Transacciones bancarias

### Tablas del Sistema
- `activity_logs` - Logs de auditoría
- `v_bank_balance` - Vista de balances bancarios

## 🔒 SEGURIDAD POST-INSTALACIÓN

### 1. Cambiar Contraseña Admin
```sql
UPDATE users SET password = PASSWORD("nueva_password") WHERE username = "admin";
```

### 2. Configurar Permisos
- Establecer permisos adecuados en archivos
- Configurar SSL si es necesario
- Revisar configuraciones de seguridad

### 3. Configurar BackBlaze B2 (Si se usa)
```php
define("B2_KEY_ID", "tu_key_id");
define("B2_APPLICATION_KEY", "tu_application_key");
define("B2_BUCKET_NAME", "tu_bucket");
```

## 🆘 SOLUCIÓN DE PROBLEMAS

### Error de Conexión
- Verificar credenciales MySQL
- Comprobar que MySQL esté ejecutándose
- Verificar permisos de usuario

### Error de Esquema
- Verificar que el archivo SQL esté completo
- Comprobar versión de MySQL
- Revisar logs de error

### Error de Permisos
- Verificar permisos de archivos
- Comprobar usuario web server
- Revisar configuración de PHP

## 📞 SOPORTE

Si encuentra problemas:
1. Revisar logs de error
2. Verificar configuración
3. Consultar documentación del sistema
4. Contactar soporte técnico

---

**Versión:** 1.0
**Fecha:** 2025-07-04
**Estado:** ✅ Listo para producción
