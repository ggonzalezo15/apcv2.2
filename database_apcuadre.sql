-- ==========================================================================
-- SCRIPT SQL COMPLETO PARA MYSQL WORKBENCH
-- Sistema APCUADRE - Base de datos completa
-- Adaptado de PostgreSQL a MySQL
-- ==========================================================================

-- Crear base de datos
DROP DATABASE IF EXISTS apcuadre_db;
CREATE DATABASE apcuadre_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE apcuadre_db;

-- ==========================================================================
-- TABLAS DEL SISTEMA DE AUTENTICACIÓN
-- ==========================================================================

-- Tabla de usuarios
CREATE TABLE users (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de sesiones de usuario
CREATE TABLE user_sessions (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================================
-- TABLAS DEL SISTEMA APCUADRE
-- ==========================================================================

-- Tabla de equipos
CREATE TABLE teams (
    id CHAR(36) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_name (name),
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de contratistas
CREATE TABLE contractors (
    id CHAR(36) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    phone VARCHAR(50),
    address TEXT,
    tax_id VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_name (name),
    INDEX idx_name (name),
    INDEX idx_email (email),
    INDEX idx_tax_id (tax_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de proveedores
CREATE TABLE vendors (
    id CHAR(36) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    phone VARCHAR(50),
    address TEXT,
    tax_id VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_name (name),
    INDEX idx_name (name),
    INDEX idx_email (email),
    INDEX idx_tax_id (tax_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de tipos de trabajo
CREATE TABLE job_types (
    id CHAR(36) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_name (name),
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de tipos de gastos
CREATE TABLE expense_types (
    id CHAR(36) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_name (name),
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de cuentas bancarias
CREATE TABLE bank_accounts (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de tipos de pago
CREATE TABLE payment_types (
    id CHAR(36) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    requires_bank_account BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_name (name),
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de ingresos
CREATE TABLE incomes (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de contratistas por ingreso
CREATE TABLE income_contractors (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de líneas de ingreso
CREATE TABLE income_lines (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de pagos de ingresos
CREATE TABLE income_payments (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de gastos
CREATE TABLE expenses (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de líneas de gastos
CREATE TABLE expense_lines (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de adjuntos de gastos
CREATE TABLE expense_attachments (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de transacciones
CREATE TABLE transactions (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================================
-- INSERTAR DATOS DE EJEMPLO
-- ==========================================================================

-- Usuarios del sistema
INSERT INTO users (username, email, password, role) VALUES 
('admin', 'admin@apcuadre.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('user', 'user@apcuadre.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user');

-- Equipos
INSERT INTO teams (id, name, description) VALUES 
('550e8400-e29b-41d4-a716-446655440001', 'Equipo Desarrollo', 'Equipo de desarrollo de software'),
('550e8400-e29b-41d4-a716-446655440002', 'Equipo Marketing', 'Equipo de marketing y ventas'),
('550e8400-e29b-41d4-a716-446655440003', 'Equipo Operaciones', 'Equipo de operaciones y soporte');

-- Contratistas
INSERT INTO contractors (id, name, email, phone, tax_id) VALUES 
('650e8400-e29b-41d4-a716-446655440001', 'Juan Pérez', 'juan@example.com', '+1234567890', 'RFC123456789'),
('650e8400-e29b-41d4-a716-446655440002', 'María García', 'maria@example.com', '+1234567891', 'RFC123456790'),
('650e8400-e29b-41d4-a716-446655440003', 'Carlos López', 'carlos@example.com', '+1234567892', 'RFC123456791');

-- Proveedores
INSERT INTO vendors (id, name, email, phone, tax_id) VALUES 
('750e8400-e29b-41d4-a716-446655440001', 'Proveedor Tech SA', 'tech@proveedor.com', '+1234567893', 'RFC123456792'),
('750e8400-e29b-41d4-a716-446655440002', 'Suministros Office', 'office@suministros.com', '+1234567894', 'RFC123456793'),
('750e8400-e29b-41d4-a716-446655440003', 'Servicios Generales', 'general@servicios.com', '+1234567895', 'RFC123456794');

-- Tipos de trabajo
INSERT INTO job_types (id, name, description) VALUES 
('850e8400-e29b-41d4-a716-446655440001', 'Desarrollo Web', 'Desarrollo de aplicaciones web'),
('850e8400-e29b-41d4-a716-446655440002', 'Consultoría', 'Servicios de consultoría técnica'),
('850e8400-e29b-41d4-a716-446655440003', 'Mantenimiento', 'Servicios de mantenimiento de sistemas');

-- Tipos de gastos
INSERT INTO expense_types (id, name, description) VALUES 
('950e8400-e29b-41d4-a716-446655440001', 'Materiales', 'Gastos en materiales y suministros'),
('950e8400-e29b-41d4-a716-446655440002', 'Servicios', 'Gastos en servicios externos'),
('950e8400-e29b-41d4-a716-446655440003', 'Transporte', 'Gastos de transporte y combustible');

-- Cuentas bancarias
INSERT INTO bank_accounts (id, name, bank_name, account_number, account_type, balance) VALUES 
('a50e8400-e29b-41d4-a716-446655440001', 'Cuenta Principal', 'Banco Nacional', '1234567890', 'business', 50000.00),
('a50e8400-e29b-41d4-a716-446655440002', 'Cuenta Secundaria', 'Banco Regional', '0987654321', 'checking', 25000.00);

-- Tipos de pago
INSERT INTO payment_types (id, name, description, requires_bank_account) VALUES 
('b50e8400-e29b-41d4-a716-446655440001', 'Transferencia Bancaria', 'Pago por transferencia bancaria', TRUE),
('b50e8400-e29b-41d4-a716-446655440002', 'Efectivo', 'Pago en efectivo', FALSE),
('b50e8400-e29b-41d4-a716-446655440003', 'Cheque', 'Pago con cheque', TRUE);

-- Ingresos de ejemplo
INSERT INTO incomes (id, team_id, job_type_id, name, description, total_amount, status, start_date, end_date) VALUES 
('c50e8400-e29b-41d4-a716-446655440001', '550e8400-e29b-41d4-a716-446655440001', '850e8400-e29b-41d4-a716-446655440001', 'Proyecto Web E-commerce', 'Desarrollo de plataforma de comercio electrónico', 75000.00, 'in_progress', '2024-01-01', '2024-03-31'),
('c50e8400-e29b-41d4-a716-446655440002', '550e8400-e29b-41d4-a716-446655440002', '850e8400-e29b-41d4-a716-446655440002', 'Consultoría Marketing Digital', 'Estrategia de marketing digital para cliente', 25000.00, 'completed', '2024-02-01', '2024-02-28');

-- Líneas de ingreso
INSERT INTO income_lines (id, income_id, description, quantity, unit_price, total_amount) VALUES 
('d50e8400-e29b-41d4-a716-446655440001', 'c50e8400-e29b-41d4-a716-446655440001', 'Desarrollo Frontend', 1.00, 30000.00, 30000.00),
('d50e8400-e29b-41d4-a716-446655440002', 'c50e8400-e29b-41d4-a716-446655440001', 'Desarrollo Backend', 1.00, 25000.00, 25000.00),
('d50e8400-e29b-41d4-a716-446655440003', 'c50e8400-e29b-41d4-a716-446655440001', 'Base de Datos', 1.00, 20000.00, 20000.00);

-- Gastos de ejemplo
INSERT INTO expenses (id, team_id, vendor_id, expense_type_id, name, description, total_amount, expense_date, status) VALUES 
('e50e8400-e29b-41d4-a716-446655440001', '550e8400-e29b-41d4-a716-446655440001', '750e8400-e29b-41d4-a716-446655440001', '950e8400-e29b-41d4-a716-446655440002', 'Licencias de Software', 'Compra de licencias de desarrollo', 5000.00, '2024-01-15', 'approved'),
('e50e8400-e29b-41d4-a716-446655440002', '550e8400-e29b-41d4-a716-446655440002', '750e8400-e29b-41d4-a716-446655440002', '950e8400-e29b-41d4-a716-446655440001', 'Material de Oficina', 'Compra de suministros de oficina', 1500.00, '2024-01-20', 'paid');

-- Líneas de gastos
INSERT INTO expense_lines (id, expense_id, description, quantity, unit_price, total_amount) VALUES 
('f50e8400-e29b-41d4-a716-446655440001', 'e50e8400-e29b-41d4-a716-446655440001', 'Licencia Visual Studio Professional', 5.00, 800.00, 4000.00),
('f50e8400-e29b-41d4-a716-446655440002', 'e50e8400-e29b-41d4-a716-446655440001', 'Licencia Adobe Creative Suite', 2.00, 500.00, 1000.00);

-- Pagos de ingresos
INSERT INTO income_payments (id, income_id, payment_type_id, bank_account_id, amount, payment_date, reference_number, notes) VALUES 
('150e8400-e29b-41d4-a716-446655440001', 'c50e8400-e29b-41d4-a716-446655440001', 'b50e8400-e29b-41d4-a716-446655440001', 'a50e8400-e29b-41d4-a716-446655440001', 37500.00, '2024-01-15', 'TRF-001', 'Primer pago del 50%'),
('150e8400-e29b-41d4-a716-446655440002', 'c50e8400-e29b-41d4-a716-446655440002', 'b50e8400-e29b-41d4-a716-446655440001', 'a50e8400-e29b-41d4-a716-446655440001', 25000.00, '2024-02-28', 'TRF-002', 'Pago completo por consultoría');

-- Transacciones
INSERT INTO transactions (id, bank_account_id, income_payment_id, type, amount, balance_after, description, transaction_date) VALUES 
('250e8400-e29b-41d4-a716-446655440001', 'a50e8400-e29b-41d4-a716-446655440001', '150e8400-e29b-41d4-a716-446655440001', 'income', 37500.00, 87500.00, 'Pago proyecto e-commerce', '2024-01-15'),
('250e8400-e29b-41d4-a716-446655440002', 'a50e8400-e29b-41d4-a716-446655440001', '150e8400-e29b-41d4-a716-446655440002', 'income', 25000.00, 112500.00, 'Pago consultoría marketing', '2024-02-28'),
('250e8400-e29b-41d4-a716-446655440003', 'a50e8400-e29b-41d4-a716-446655440001', NULL, 'expense', -5000.00, 107500.00, 'Pago licencias software', '2024-01-20');

-- ==========================================================================
-- INFORMACIÓN DE CREDENCIALES
-- ==========================================================================
/*
CREDENCIALES DE ACCESO:

Administrador:
- Usuario: admin
- Contraseña: admin123

Usuario Normal:
- Usuario: user  
- Contraseña: user123

NOTA: Las contraseñas están hasheadas con PASSWORD_DEFAULT de PHP.
El hash mostrado corresponde a "secret" pero debes usar las contraseñas reales.
*/

-- Verificar que todo se instaló correctamente
SELECT 'Base de datos APCUADRE creada exitosamente' AS status;
SELECT COUNT(*) AS total_users FROM users;
SELECT COUNT(*) AS total_teams FROM teams;
SELECT COUNT(*) AS total_contractors FROM contractors;
SELECT COUNT(*) AS total_vendors FROM vendors;
SELECT COUNT(*) AS total_incomes FROM incomes;
SELECT COUNT(*) AS total_expenses FROM expenses;
