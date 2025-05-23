<?php
/**
 * Script de inicialización de la base de datos
 * Sistema de Autenticación + APCUADRE
 * Ejecuta este archivo para crear todas las tablas necesarias
 */

require_once 'config.php';

$message = '';
$error = '';

if ($_POST && isset($_POST['install'])) {
    try {
        // Conectar sin especificar base de datos para crearla
        $pdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Crear base de datos si no existe
        $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE " . DB_NAME);
        
        // ===================================
        // TABLAS DE AUTENTICACIÓN
        // ===================================
        
        // Crear tabla de usuarios (autenticación)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) NOT NULL UNIQUE,
                email VARCHAR(100) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                role VARCHAR(20) DEFAULT 'user',
                active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                last_login TIMESTAMP NULL,
                INDEX idx_username (username),
                INDEX idx_email (email),
                INDEX idx_active (active),
                INDEX idx_role (role)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Crear tabla de sesiones
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS user_sessions (
                id VARCHAR(128) PRIMARY KEY,
                user_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                ip_address VARCHAR(45),
                user_agent TEXT,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_user_id (user_id),
                INDEX idx_last_activity (last_activity)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Crear tabla de logs de actividad
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS activity_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT,
                action VARCHAR(100) NOT NULL,
                description TEXT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_user_id (user_id),
                INDEX idx_action (action),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // ===================================
        // TABLAS BASE DEL SISTEMA APCUADRE
        // ===================================
        
        // Tabla de equipos
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS teams (
                id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
                name VARCHAR(255) NOT NULL,
                status VARCHAR(20) DEFAULT 'activo',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_status (status),
                INDEX idx_name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Tabla de contratistas
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS contractors (
                id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
                name VARCHAR(255) NOT NULL,
                status VARCHAR(20) DEFAULT 'activo',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_status (status),
                INDEX idx_name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Tabla de proveedores
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS vendors (
                id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
                name VARCHAR(255) NOT NULL,
                status VARCHAR(20) DEFAULT 'activo',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_status (status),
                INDEX idx_name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Tabla de tipos de trabajo
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS job_types (
                id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
                name VARCHAR(255) NOT NULL,
                contractor_rate DECIMAL(10,2),
                subcontractor_rate DECIMAL(10,2),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Tabla de tipos de gastos
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS expense_types (
                id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
                name VARCHAR(255) NOT NULL,
                category VARCHAR(100) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_category (category),
                INDEX idx_name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Tabla de cuentas bancarias
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS bank_accounts (
                id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
                name VARCHAR(255) NOT NULL,
                account_type VARCHAR(50) NOT NULL COMMENT 'Cuenta Corriente / Cuenta de Crédito',
                initial_balance DECIMAL(12,2) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_account_type (account_type),
                INDEX idx_name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Tabla de tipos de pago
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS payment_types (
                id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
                name VARCHAR(255) NOT NULL,
                bank_account_id CHAR(36),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id) ON DELETE SET NULL,
                INDEX idx_name (name),
                INDEX idx_bank_account (bank_account_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // ===================================
        // TABLAS DE INGRESOS
        // ===================================
        
        // Tabla principal de ingresos
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS incomes (
                id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
                invoice_number VARCHAR(100),
                date DATE NOT NULL,
                team_id CHAR(36),
                general_note TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL,
                INDEX idx_date (date),
                INDEX idx_team (team_id),
                INDEX idx_invoice (invoice_number)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Tabla de contratistas por ingreso
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS income_contractors (
                id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
                income_id CHAR(36) NOT NULL,
                contractor_id CHAR(36),
                FOREIGN KEY (income_id) REFERENCES incomes(id) ON DELETE CASCADE,
                FOREIGN KEY (contractor_id) REFERENCES contractors(id) ON DELETE SET NULL,
                INDEX idx_income (income_id),
                INDEX idx_contractor (contractor_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Tabla de líneas de ingreso
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS income_lines (
                id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
                income_id CHAR(36) NOT NULL,
                job_type_id CHAR(36),
                units DECIMAL(10,2),
                price DECIMAL(10,2),
                note TEXT,
                FOREIGN KEY (income_id) REFERENCES incomes(id) ON DELETE CASCADE,
                FOREIGN KEY (job_type_id) REFERENCES job_types(id) ON DELETE SET NULL,
                INDEX idx_income (income_id),
                INDEX idx_job_type (job_type_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Tabla de pagos de ingreso
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS income_payments (
                id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
                income_id CHAR(36) NOT NULL,
                payment_type_id CHAR(36),
                amount DECIMAL(12,2),
                fee DECIMAL(12,2) DEFAULT 0,
                FOREIGN KEY (income_id) REFERENCES incomes(id) ON DELETE CASCADE,
                FOREIGN KEY (payment_type_id) REFERENCES payment_types(id) ON DELETE SET NULL,
                INDEX idx_income (income_id),
                INDEX idx_payment_type (payment_type_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // ===================================
        // TABLAS DE GASTOS
        // ===================================
        
        // Tabla principal de gastos
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS expenses (
                id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
                date DATE NOT NULL,
                team_id CHAR(36),
                vendor_id CHAR(36),
                bank_account_id CHAR(36),
                general_note TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL,
                FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE SET NULL,
                FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id) ON DELETE SET NULL,
                INDEX idx_date (date),
                INDEX idx_team (team_id),
                INDEX idx_vendor (vendor_id),
                INDEX idx_bank_account (bank_account_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Tabla de líneas de gasto
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS expense_lines (
                id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
                expense_id CHAR(36) NOT NULL,
                description TEXT,
                expense_type_id CHAR(36),
                amount DECIMAL(12,2),
                is_deductible BOOLEAN DEFAULT FALSE,
                FOREIGN KEY (expense_id) REFERENCES expenses(id) ON DELETE CASCADE,
                FOREIGN KEY (expense_type_id) REFERENCES expense_types(id) ON DELETE SET NULL,
                INDEX idx_expense (expense_id),
                INDEX idx_expense_type (expense_type_id),
                INDEX idx_deductible (is_deductible)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Tabla de archivos adjuntos de gastos
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS expense_attachments (
                id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
                expense_id CHAR(36) NOT NULL,
                file_url TEXT,
                file_type VARCHAR(50),
                file_size BIGINT,
                original_filename VARCHAR(255),
                FOREIGN KEY (expense_id) REFERENCES expenses(id) ON DELETE CASCADE,
                INDEX idx_expense (expense_id),
                INDEX idx_file_type (file_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // ===================================
        // TABLA DE TRANSACCIONES BANCARIAS
        // ===================================
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS transactions (
                id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
                bank_account_id CHAR(36) NOT NULL,
                reference_id CHAR(36),
                reference_type ENUM('income', 'expense', 'fee'),
                type ENUM('gasto', 'pago', 'transferencia', 'deposito', 'fee'),
                date DATE NOT NULL,
                description TEXT,
                amount DECIMAL(12,2) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id) ON DELETE CASCADE,
                INDEX idx_bank_account (bank_account_id),
                INDEX idx_reference (reference_id),
                INDEX idx_reference_type (reference_type),
                INDEX idx_type (type),
                INDEX idx_date (date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // ===================================
        // DATOS INICIALES
        // ===================================
        
        // Verificar si ya existen usuarios
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        $userCount = $stmt->fetchColumn();
        
        if ($userCount == 0) {
            // Crear usuarios por defecto
            $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
            $userPassword = password_hash('123456', PASSWORD_DEFAULT);
            
            $pdo->exec("
                INSERT INTO users (username, email, password, role) VALUES 
                ('admin', 'admin@sistema.com', '$adminPassword', 'admin'),
                ('usuario', 'usuario@test.com', '$userPassword', 'user')
            ");
        }
        
        // Insertar datos de ejemplo para el sistema APCUADRE
        $stmt = $pdo->query("SELECT COUNT(*) FROM teams");
        $teamCount = $stmt->fetchColumn();
        
        if ($teamCount == 0) {
            // Equipos de ejemplo
            $pdo->exec("
                INSERT INTO teams (name, status) VALUES 
                ('Equipo Alpha', 'activo'),
                ('Equipo Beta', 'activo'),
                ('Equipo Gamma', 'inactivo')
            ");
            
            // Contratistas de ejemplo
            $pdo->exec("
                INSERT INTO contractors (name, status) VALUES 
                ('Juan Pérez Construcciones', 'activo'),
                ('María García Servicios', 'activo'),
                ('Carlos López Contratista', 'activo')
            ");
            
            // Proveedores de ejemplo
            $pdo->exec("
                INSERT INTO vendors (name, status) VALUES 
                ('Ferretería Central', 'activo'),
                ('Materiales del Norte', 'activo'),
                ('Suministros Industriales', 'activo')
            ");
            
            // Tipos de trabajo de ejemplo
            $pdo->exec("
                INSERT INTO job_types (name, contractor_rate, subcontractor_rate) VALUES 
                ('Instalación Eléctrica', 150.00, 120.00),
                ('Plomería', 130.00, 100.00),
                ('Pintura', 80.00, 60.00),
                ('Construcción', 200.00, 180.00)
            ");
            
            // Tipos de gastos de ejemplo
            $pdo->exec("
                INSERT INTO expense_types (name, category) VALUES 
                ('Materiales', 'Suministros'),
                ('Combustible', 'Operación'),
                ('Herramientas', 'Equipos'),
                ('Mantenimiento', 'Servicios'),
                ('Oficina', 'Administrativo')
            ");
            
            // Cuentas bancarias de ejemplo
            $pdo->exec("
                INSERT INTO bank_accounts (name, account_type, initial_balance) VALUES 
                ('Cuenta Principal', 'Cuenta Corriente', 10000.00),
                ('Cuenta de Gastos', 'Cuenta Corriente', 5000.00),
                ('Línea de Crédito', 'Cuenta de Crédito', 0.00)
            ");
            
            // Tipos de pago de ejemplo (necesitamos obtener los IDs de las cuentas)
            $stmt = $pdo->query("SELECT id FROM bank_accounts LIMIT 2");
            $bankAccounts = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (count($bankAccounts) >= 2) {
                $pdo->exec("
                    INSERT INTO payment_types (name, bank_account_id) VALUES 
                    ('Efectivo', '{$bankAccounts[0]}'),
                    ('Transferencia Bancaria', '{$bankAccounts[0]}'),
                    ('Cheque', '{$bankAccounts[1]}'),
                    ('Tarjeta de Crédito', NULL)
                ");
            }
        }
        
        $message = '✅ <strong>Base de datos inicializada correctamente!</strong><br><br>';
        $message .= '👥 <strong>Usuarios creados:</strong><br>';
        $message .= '• admin / admin123 (Administrador)<br>';
        $message .= '• usuario / 123456 (Usuario estándar)<br><br>';
        $message .= '🏢 <strong>Sistema APCUADRE integrado:</strong><br>';
        $message .= '• Equipos, contratistas y proveedores<br>';
        $message .= '• Tipos de trabajo y gastos<br>';
        $message .= '• Cuentas bancarias y tipos de pago<br>';
        $message .= '• Gestión de ingresos y gastos<br>';
        $message .= '• Control de transacciones bancarias<br><br>';
        $message .= '📊 <strong>Datos de ejemplo:</strong><br>';
        $message .= '• 3 equipos de trabajo<br>';
        $message .= '• 3 contratistas<br>';
        $message .= '• 3 proveedores<br>';
        $message .= '• 4 tipos de trabajo con tarifas<br>';
        $message .= '• 5 categorías de gastos<br>';
        $message .= '• 3 cuentas bancarias<br>';
        $message .= '• 4 tipos de pago<br>';
        
    } catch (PDOException $e) {
        $error = '❌ <strong>Error al inicializar la base de datos:</strong><br>' . htmlspecialchars($e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalación - Sistema APCUADRE</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <div class="login-form" style="max-width: 600px;">
            <div class="login-header">
                <i class="fas fa-database" style="font-size: 48px; color: var(--primary-color); margin-bottom: 16px;"></i>
                <h1>Instalación del Sistema</h1>
                <p>Sistema de Autenticación + APCUADRE</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php echo $error; ?>
                </div>
                
                <div style="background: var(--bg-primary); padding: 20px; border-radius: 8px; margin: 20px 0;">
                    <h4 style="color: var(--text-secondary); margin-bottom: 12px;">
                        <i class="fas fa-tools"></i>
                        Verifica que:
                    </h4>
                    <ul style="color: var(--text-secondary); margin: 0; padding-left: 20px;">
                        <li>MySQL/MariaDB esté ejecutándose</li>
                        <li>Las credenciales en <code>config.php</code> sean correctas</li>
                        <li>El usuario tenga permisos para crear bases de datos</li>
                        <li>El puerto de MySQL esté disponible</li>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if ($message): ?>
                <div class="alert alert-success">
                    <?php echo $message; ?>
                </div>
                
                <div style="text-align: center; margin-top: 30px;">
                    <a href="login.php" class="btn btn-primary" style="margin-right: 10px;">
                        <i class="fas fa-sign-in-alt"></i>
                        Ir al Login
                    </a>
                    <a href="dashboard.php" class="btn" style="background: var(--secondary-color); color: white; text-decoration: none;">
                        <i class="fas fa-tachometer-alt"></i>
                        Ver Dashboard
                    </a>
                </div>
                
                <div style="background: var(--bg-primary); padding: 20px; border-radius: 8px; margin: 30px 0 0;">
                    <h4 style="color: var(--danger-color); margin-bottom: 12px;">
                        <i class="fas fa-exclamation-triangle"></i>
                        Importante - Seguridad
                    </h4>
                    <p style="color: var(--text-secondary); margin: 0; font-size: 14px;">
                        Por seguridad, se recomienda <strong>eliminar este archivo (install.php)</strong> 
                        después de completar la instalación, o moverlo fuera del directorio web.
                    </p>
                </div>
            <?php else: ?>
                <div style="background: var(--bg-primary); padding: 24px; border-radius: 8px; margin: 20px 0;">
                    <h3 style="color: var(--primary-color); margin-bottom: 16px;">
                        <i class="fas fa-info-circle"></i>
                        ¿Qué se va a instalar?
                    </h3>
                    
                    <div style="margin-bottom: 20px;">
                        <h4 style="color: var(--text-primary); font-size: 16px; margin-bottom: 8px;">
                            🔐 Sistema de Autenticación:
                        </h4>
                        <ul style="color: var(--text-secondary); font-size: 14px; margin: 0; padding-left: 20px;">
                            <li>Tabla de usuarios con roles</li>
                            <li>Gestión de sesiones</li>
                            <li>Logs de actividad</li>
                        </ul>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <h4 style="color: var(--text-primary); font-size: 16px; margin-bottom: 8px;">
                            💼 Sistema APCUADRE:
                        </h4>
                        <ul style="color: var(--text-secondary); font-size: 14px; margin: 0; padding-left: 20px;">
                            <li>Gestión de equipos, contratistas y proveedores</li>
                            <li>Tipos de trabajo con tarifas</li>
                            <li>Control de ingresos y gastos</li>
                            <li>Cuentas bancarias y transacciones</li>
                            <li>Sistema de archivos adjuntos</li>
                        </ul>
                    </div>
                    
                    <div>
                        <h4 style="color: var(--text-primary); font-size: 16px; margin-bottom: 8px;">
                            📊 Datos de ejemplo incluidos:
                        </h4>
                        <ul style="color: var(--text-secondary); font-size: 14px; margin: 0; padding-left: 20px;">
                            <li>Usuarios de prueba (admin/usuario)</li>
                            <li>Equipos y contratistas de ejemplo</li>
                            <li>Tipos de trabajo y gastos predefinidos</li>
                            <li>Cuentas bancarias de muestra</li>
                        </ul>
                    </div>
                </div>
                
                <form method="POST" action="">
                    <button type="submit" name="install" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-rocket"></i>
                        Inicializar Base de Datos
                    </button>
                </form>
                
                <div style="text-align: center; margin-top: 20px;">
                    <p style="color: var(--text-secondary); font-size: 12px;">
                        <i class="fas fa-shield-alt"></i>
                        Este proceso es seguro y no sobrescribirá datos existentes
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
