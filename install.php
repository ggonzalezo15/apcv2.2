<?php
/**
 * Script de inicialización completa de la base de datos APCUADRE
 * Incluye sistema de autenticación + sistema APCUADRE completo
 * Adaptado de PostgreSQL a MySQL
 */

require_once 'config.php';

try {
    // Crear conexión sin especificar base de datos
    $pdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Crear base de datos si no existe
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE " . DB_NAME);
    
    echo "<h2>🗄️ Creando tablas del sistema...</h2>\n";
    
    // ==========================================================================
    // TABLAS DEL SISTEMA DE AUTENTICACIÓN
    // ==========================================================================
    
    echo "<p>📋 Creando tabla de usuarios...</p>\n";
    $createUsersTable = "
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin', 'user') DEFAULT 'user',
            active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL,
            INDEX idx_username (username),
            INDEX idx_email (email),
            INDEX idx_active (active),
            INDEX idx_role (role)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $pdo->exec($createUsersTable);
    
    echo "<p>📋 Creando tabla de sesiones...</p>\n";
    $createSessionsTable = "
        CREATE TABLE IF NOT EXISTS user_sessions (
            id VARCHAR(128) PRIMARY KEY,
            user_id INT NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $pdo->exec($createSessionsTable);
    
    // ==========================================================================
    // TABLAS DEL SISTEMA APCUADRE (Adaptadas de PostgreSQL a MySQL)
    // ==========================================================================
    
    echo "<p>📋 Creando tabla de equipos...</p>\n";
    $createTeamsTable = "
        CREATE TABLE IF NOT EXISTS teams (
            id CHAR(36) PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_name (name),
            INDEX idx_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $pdo->exec($createTeamsTable);
    
    echo "<p>📋 Creando tabla de contratistas...</p>\n";
    $createContractorsTable = "
        CREATE TABLE IF NOT EXISTS contractors (
            id CHAR(36) PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255),
            phone VARCHAR(50),
            address TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_name (name),
            INDEX idx_name (name),
            INDEX idx_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $pdo->exec($createContractorsTable);
    
    echo "<p>📋 Creando tabla de proveedores...</p>\n";
    $createVendorsTable = "
        CREATE TABLE IF NOT EXISTS vendors (
            id CHAR(36) PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255),
            phone VARCHAR(50),
            address TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_name (name),
            INDEX idx_name (name),
            INDEX idx_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $pdo->exec($createVendorsTable);
    
    echo "<p>📋 Creando tabla de tipos de trabajo...</p>\n";
    $createJobTypesTable = "
        CREATE TABLE IF NOT EXISTS job_types (
            id CHAR(36) PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_name (name),
            INDEX idx_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $pdo->exec($createJobTypesTable);
    
    echo "<p>📋 Creando tabla de tipos de gastos...</p>\n";
    $createExpenseTypesTable = "
        CREATE TABLE IF NOT EXISTS expense_types (
            id CHAR(36) PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_name (name),
            INDEX idx_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $pdo->exec($createExpenseTypesTable);
    
    echo "<p>📋 Creando tabla de cuentas bancarias...</p>\n";
    $createBankAccountsTable = "
        CREATE TABLE IF NOT EXISTS bank_accounts (
            id CHAR(36) PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            bank_name VARCHAR(255) NOT NULL,
            account_number VARCHAR(50) NOT NULL,
            account_type ENUM('checking', 'savings', 'business') DEFAULT 'checking',
            balance DECIMAL(15,2) DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_account_number (account_number),
            INDEX idx_name (name),
            INDEX idx_bank_name (bank_name),
            INDEX idx_account_number (account_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $pdo->exec($createBankAccountsTable);
    
    echo "<p>📋 Creando tabla de tipos de pago...</p>\n";
    $createPaymentTypesTable = "
        CREATE TABLE IF NOT EXISTS payment_types (
            id CHAR(36) PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            requires_bank_account BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_name (name),
            INDEX idx_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $pdo->exec($createPaymentTypesTable);
    
    echo "<p>📋 Creando tabla de ingresos...</p>\n";
    $createIncomesTable = "
        CREATE TABLE IF NOT EXISTS incomes (
            id CHAR(36) PRIMARY KEY,
            team_id CHAR(36) NOT NULL,
            job_type_id CHAR(36) NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            total_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
            start_date DATE,
            end_date DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE RESTRICT,
            FOREIGN KEY (job_type_id) REFERENCES job_types(id) ON DELETE RESTRICT,
            INDEX idx_team_id (team_id),
            INDEX idx_job_type_id (job_type_id),
            INDEX idx_status (status),
            INDEX idx_start_date (start_date),
            INDEX idx_end_date (end_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $pdo->exec($createIncomesTable);
    
    echo "<p>📋 Creando tabla de contratistas por ingreso...</p>\n";
    $createIncomeContractorsTable = "
        CREATE TABLE IF NOT EXISTS income_contractors (
            id CHAR(36) PRIMARY KEY,
            income_id CHAR(36) NOT NULL,
            contractor_id CHAR(36) NOT NULL,
            percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (income_id) REFERENCES incomes(id) ON DELETE CASCADE,
            FOREIGN KEY (contractor_id) REFERENCES contractors(id) ON DELETE CASCADE,
            UNIQUE KEY unique_income_contractor (income_id, contractor_id),
            INDEX idx_income_id (income_id),
            INDEX idx_contractor_id (contractor_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $pdo->exec($createIncomeContractorsTable);
    
    echo "<p>📋 Creando tabla de líneas de ingreso...</p>\n";
    $createIncomeLinesTable = "
        CREATE TABLE IF NOT EXISTS income_lines (
            id CHAR(36) PRIMARY KEY,
            income_id CHAR(36) NOT NULL,
            description TEXT NOT NULL,
            quantity DECIMAL(10,2) NOT NULL DEFAULT 1.00,
            unit_price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            total_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (income_id) REFERENCES incomes(id) ON DELETE CASCADE,
            INDEX idx_income_id (income_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $pdo->exec($createIncomeLinesTable);
    
    echo "<p>📋 Creando tabla de pagos de ingresos...</p>\n";
    $createIncomePaymentsTable = "
        CREATE TABLE IF NOT EXISTS income_payments (
            id CHAR(36) PRIMARY KEY,
            income_id CHAR(36) NOT NULL,
            payment_type_id CHAR(36) NOT NULL,
            bank_account_id CHAR(36),
            amount DECIMAL(15,2) NOT NULL,
            payment_date DATE NOT NULL,
            reference_number VARCHAR(255),
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (income_id) REFERENCES incomes(id) ON DELETE CASCADE,
            FOREIGN KEY (payment_type_id) REFERENCES payment_types(id) ON DELETE RESTRICT,
            FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id) ON DELETE SET NULL,
            INDEX idx_income_id (income_id),
            INDEX idx_payment_type_id (payment_type_id),
            INDEX idx_bank_account_id (bank_account_id),
            INDEX idx_payment_date (payment_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $pdo->exec($createIncomePaymentsTable);
    
    echo "<p>📋 Creando tabla de gastos...</p>\n";
    $createExpensesTable = "
        CREATE TABLE IF NOT EXISTS expenses (
            id CHAR(36) PRIMARY KEY,
            team_id CHAR(36) NOT NULL,
            vendor_id CHAR(36),
            expense_type_id CHAR(36) NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            total_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            expense_date DATE NOT NULL,
            status ENUM('pending', 'approved', 'paid', 'rejected') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE RESTRICT,
            FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE SET NULL,
            FOREIGN KEY (expense_type_id) REFERENCES expense_types(id) ON DELETE RESTRICT,
            INDEX idx_team_id (team_id),
            INDEX idx_vendor_id (vendor_id),
            INDEX idx_expense_type_id (expense_type_id),
            INDEX idx_expense_date (expense_date),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $pdo->exec($createExpensesTable);
    
    echo "<p>📋 Creando tabla de líneas de gastos...</p>\n";
    $createExpenseLinesTable = "
        CREATE TABLE IF NOT EXISTS expense_lines (
            id CHAR(36) PRIMARY KEY,
            expense_id CHAR(36) NOT NULL,
            description TEXT NOT NULL,
            quantity DECIMAL(10,2) NOT NULL DEFAULT 1.00,
            unit_price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            total_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (expense_id) REFERENCES expenses(id) ON DELETE CASCADE,
            INDEX idx_expense_id (expense_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $pdo->exec($createExpenseLinesTable);
    
    echo "<p>📋 Creando tabla de adjuntos de gastos...</p>\n";
    $createExpenseAttachmentsTable = "
        CREATE TABLE IF NOT EXISTS expense_attachments (
            id CHAR(36) PRIMARY KEY,
            expense_id CHAR(36) NOT NULL,
            filename VARCHAR(255) NOT NULL,
            original_filename VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_size INT NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (expense_id) REFERENCES expenses(id) ON DELETE CASCADE,
            INDEX idx_expense_id (expense_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $pdo->exec($createExpenseAttachmentsTable);
    
    echo "<p>📋 Creando tabla de transacciones...</p>\n";
    $createTransactionsTable = "
        CREATE TABLE IF NOT EXISTS transactions (
            id CHAR(36) PRIMARY KEY,
            bank_account_id CHAR(36) NOT NULL,
            income_payment_id CHAR(36),
            expense_id CHAR(36),
            type ENUM('income', 'expense', 'transfer') NOT NULL,
            amount DECIMAL(15,2) NOT NULL,
            balance_after DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            description TEXT,
            transaction_date DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id) ON DELETE RESTRICT,
            FOREIGN KEY (income_payment_id) REFERENCES income_payments(id) ON DELETE SET NULL,
            FOREIGN KEY (expense_id) REFERENCES expenses(id) ON DELETE SET NULL,
            INDEX idx_bank_account_id (bank_account_id),
            INDEX idx_income_payment_id (income_payment_id),
            INDEX idx_expense_id (expense_id),
            INDEX idx_type (type),
            INDEX idx_transaction_date (transaction_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $pdo->exec($createTransactionsTable);
    
    // ==========================================================================
    // INSERTAR DATOS DE EJEMPLO
    // ==========================================================================
    
    echo "<h2>📊 Insertando datos de ejemplo...</h2>\n";
    
    // Verificar si ya existe un usuario admin
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute(['admin']);
    
    if ($stmt->fetchColumn() == 0) {
        echo "<p>👤 Creando usuario administrador...</p>\n";
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            'admin',
            'admin@apcuadre.com',
            password_hash('admin123', PASSWORD_DEFAULT),
            'admin'
        ]);
        
        echo "<p>👤 Creando usuario de prueba...</p>\n";
        $stmt->execute([
            'user',
            'user@apcuadre.com',
            password_hash('user123', PASSWORD_DEFAULT),
            'user'
        ]);
    }
    
    // Función para generar UUID compatible con MySQL
    function generateUUID() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    // Verificar si ya existen datos de ejemplo
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM teams");
    $stmt->execute();
    
    if ($stmt->fetchColumn() == 0) {
        echo "<p>🏢 Insertando equipos de ejemplo...</p>\n";
        $teamIds = [
            generateUUID(),
            generateUUID(),
            generateUUID()
        ];
        
        $stmt = $pdo->prepare("INSERT INTO teams (id, name, description) VALUES (?, ?, ?)");
        $stmt->execute([$teamIds[0], 'Equipo Desarrollo', 'Equipo de desarrollo de software']);
        $stmt->execute([$teamIds[1], 'Equipo Marketing', 'Equipo de marketing y ventas']);
        $stmt->execute([$teamIds[2], 'Equipo Operaciones', 'Equipo de operaciones y soporte']);
        
        echo "<p>👷 Insertando contratistas de ejemplo...</p>\n";
        $contractorIds = [
            generateUUID(),
            generateUUID(),
            generateUUID()
        ];
        
        $stmt = $pdo->prepare("INSERT INTO contractors (id, name, email, phone, address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$contractorIds[0], 'Juan Pérez', 'juan@example.com', '+1234567890', '']);
        $stmt->execute([$contractorIds[1], 'María García', 'maria@example.com', '+1234567891', '']);
        $stmt->execute([$contractorIds[2], 'Carlos López', 'carlos@example.com', '+1234567892', '']);
        
        echo "<p>🏪 Insertando proveedores de ejemplo...</p>\n";
        $vendorIds = [
            generateUUID(),
            generateUUID(),
            generateUUID()
        ];
        
        $stmt = $pdo->prepare("INSERT INTO vendors (id, name, email, phone) VALUES (?, ?, ?, ?)");
        $stmt->execute([$vendorIds[0], 'Proveedor Tech SA', 'tech@proveedor.com', '+1234567893']);
        $stmt->execute([$vendorIds[1], 'Suministros Office', 'office@suministros.com', '+1234567894']);
        $stmt->execute([$vendorIds[2], 'Servicios Generales', 'general@servicios.com', '+1234567895']);
        
        echo "<p>🔧 Insertando tipos de trabajo...</p>\n";
        $jobTypeIds = [
            generateUUID(),
            generateUUID(),
            generateUUID()
        ];
        
        $stmt = $pdo->prepare("INSERT INTO job_types (id, name, description) VALUES (?, ?, ?)");
        $stmt->execute([$jobTypeIds[0], 'Desarrollo Web', 'Desarrollo de aplicaciones web']);
        $stmt->execute([$jobTypeIds[1], 'Consultoría', 'Servicios de consultoría técnica']);
        $stmt->execute([$jobTypeIds[2], 'Mantenimiento', 'Servicios de mantenimiento de sistemas']);
        
        echo "<p>💳 Insertando tipos de gastos...</p>\n";
        $expenseTypeIds = [
            generateUUID(),
            generateUUID(),
            generateUUID()
        ];
        
        $stmt = $pdo->prepare("INSERT INTO expense_types (id, name, description) VALUES (?, ?, ?)");
        $stmt->execute([$expenseTypeIds[0], 'Materiales', 'Gastos en materiales y suministros']);
        $stmt->execute([$expenseTypeIds[1], 'Servicios', 'Gastos en servicios externos']);
        $stmt->execute([$expenseTypeIds[2], 'Transporte', 'Gastos de transporte y combustible']);
        
        echo "<p>🏦 Insertando cuentas bancarias...</p>\n";
        $bankAccountIds = [
            generateUUID(),
            generateUUID()
        ];
        
        $stmt = $pdo->prepare("INSERT INTO bank_accounts (id, name, bank_name, account_number, account_type, balance) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$bankAccountIds[0], 'Cuenta Principal', 'Banco Nacional', '1234567890', 'business', 50000.00]);
        $stmt->execute([$bankAccountIds[1], 'Cuenta Secundaria', 'Banco Regional', '0987654321', 'checking', 25000.00]);
        
        echo "<p>💰 Insertando tipos de pago...</p>\n";
        $paymentTypeIds = [
            generateUUID(),
            generateUUID(),
            generateUUID()
        ];
        
        $stmt = $pdo->prepare("INSERT INTO payment_types (id, name, description, requires_bank_account) VALUES (?, ?, ?, ?)");
        $stmt->execute([$paymentTypeIds[0], 'Transferencia Bancaria', 'Pago por transferencia bancaria', true]);
        $stmt->execute([$paymentTypeIds[1], 'Efectivo', 'Pago en efectivo', false]);
        $stmt->execute([$paymentTypeIds[2], 'Cheque', 'Pago con cheque', true]);
        
        echo "<p>📈 Insertando ingresos de ejemplo...</p>\n";
        $incomeId = generateUUID();
        $stmt = $pdo->prepare("INSERT INTO incomes (id, team_id, job_type_id, name, description, total_amount, status, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $incomeId,
            $teamIds[0],
            $jobTypeIds[0],
            'Proyecto Web E-commerce',
            'Desarrollo de plataforma de comercio electrónico',
            75000.00,
            'in_progress',
            '2024-01-01',
            '2024-03-31'
        ]);
        
        echo "<p>📉 Insertando gastos de ejemplo...</p>\n";
        $expenseId = generateUUID();
        $stmt = $pdo->prepare("INSERT INTO expenses (id, team_id, vendor_id, expense_type_id, name, description, total_amount, expense_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $expenseId,
            $teamIds[0],
            $vendorIds[0],
            $expenseTypeIds[1],
            'Licencias de Software',
            'Compra de licencias de desarrollo',
            5000.00,
            '2024-01-15',
            'approved'
        ]);
        
        echo "<p>✅ Datos de ejemplo insertados correctamente.</p>\n";
    } else {
        echo "<p>ℹ️ Los datos de ejemplo ya existen.</p>\n";
    }
    
    echo "<h2>✅ Base de datos inicializada correctamente</h2>\n";
    echo "<div style='background: #f0f9ff; border: 1px solid #0ea5e9; border-radius: 8px; padding: 20px; margin: 20px 0;'>\n";
    echo "<h3>🔐 Credenciales de Acceso</h3>\n";
    echo "<p><strong>Administrador:</strong><br>\n";
    echo "Usuario: <code>admin</code><br>\n";
    echo "Contraseña: <code>admin123</code></p>\n";
    echo "<p><strong>Usuario Normal:</strong><br>\n";
    echo "Usuario: <code>user</code><br>\n";
    echo "Contraseña: <code>user123</code></p>\n";
    echo "</div>\n";
    
    echo "<p><a href='login.php' style='background: #2563eb; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🚀 Ir al Sistema</a></p>\n";
    
} catch (PDOException $e) {
    echo "<h2>❌ Error de instalación</h2>\n";
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<p>Por favor, verifica tu configuración de base de datos en <code>config.php</code></p>\n";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalación APCUADRE</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: #f8fafc;
            color: #1e293b;
        }
        h1, h2, h3 {
            color: #0f172a;
        }
        code {
            background: #e2e8f0;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
        }
        p {
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <h1>🎯 Sistema APCUADRE - Instalación Completa</h1>
    <p>Este script ha configurado tu base de datos con todas las tablas necesarias para el sistema de gestión APCUADRE.</p>
</body>
</html>
