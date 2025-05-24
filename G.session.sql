-- ============================================================================
-- SCRIPT SQL COMPLETO PARA SISTEMA APCUADRE
-- Base de datos MySQL con sistema de autenticación + gestión empresarial
-- Adaptado de PostgreSQL a MySQL
-- Ejecutar en MySQL Workbench o línea de comandos
-- ============================================================================

-- Crear y usar la base de datos
CREATE DATABASE IF NOT EXISTS cloude_apcuadre CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cloude_apcuadre;

-- ============================================================================
-- TABLAS DEL SISTEMA DE AUTENTICACIÓN
-- ============================================================================

-- Tabla de usuarios del sistema
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de sesiones de usuario
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLAS DEL SISTEMA APCUADRE
-- ============================================================================

-- Tabla de equipos de trabajo
CREATE TABLE IF NOT EXISTS teams (
    id CHAR(36) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_name (name),
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de contratistas
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
    INDEX idx_email (email),
    INDEX idx_tax_id (tax_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de proveedores
CREATE TABLE IF NOT EXISTS vendors (
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

CREATE TABLE IF NOT EXISTS job_types (
    id CHAR(36) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    pay_as_contractor DECIMAL(15,2) DEFAULT 0.00,
    pay_as_sub_contractor DECIMAL(15,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_name (name),
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de tipos de gastos
CREATE TABLE IF NOT EXISTS expense_types (
    id CHAR(36) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_name (name),
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de cuentas bancarias
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de tipos de pago
CREATE TABLE IF NOT EXISTS payment_types (
    id CHAR(36) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    bank_account_id CHAR(36) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_name (name),
    INDEX idx_name (name),
    CONSTRAINT fk_payment_type_bank_account FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla principal de ingresos
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de relación entre ingresos y contratistas
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de líneas de detalle de ingresos
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de pagos recibidos por ingresos
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla principal de gastos
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de líneas de detalle de gastos
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de adjuntos de gastos (facturas, recibos, etc.)
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de transacciones bancarias
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- DATOS DE EJEMPLO
-- ============================================================================

-- Insertar usuarios del sistema
INSERT INTO users (username, email, password, role) VALUES 
('admin', 'admin@apcuadre.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('user', 'user@apcuadre.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user');

-- Insertar equipos de trabajo
INSERT INTO teams (id, name, description) VALUES 
('550e8400-e29b-41d4-a716-446655440001', 'Equipo Desarrollo', 'Equipo de desarrollo de software'),
('550e8400-e29b-41d4-a716-446655440002', 'Equipo Marketing', 'Equipo de marketing y ventas'),
('550e8400-e29b-41d4-a716-446655440003', 'Equipo Operaciones', 'Equipo de operaciones y soporte');

-- Insertar contratistas
INSERT INTO contractors (id, name, email, phone, tax_id) VALUES 
('550e8400-e29b-41d4-a716-446655440011', 'Juan Pérez', 'juan@example.com', '+1234567890'),
('550e8400-e29b-41d4-a716-446655440012', 'María García', 'maria@example.com', '+1234567891'),
('550e8400-e29b-41d4-a716-446655440013', 'Carlos López', 'carlos@example.com', '+1234567892');

-- Insertar proveedores
INSERT INTO vendors (id, name, email, phone, tax_id) VALUES 
('550e8400-e29b-41d4-a716-446655440021', 'Proveedor Tech SA', 'tech@proveedor.com', '+1234567893', 'RFC123456792'),
('550e8400-e29b-41d4-a716-446655440022', 'Suministros Office', 'office@suministros.com', '+1234567894', 'RFC123456793'),
('550e8400-e29b-41d4-a716-446655440023', 'Servicios Generales', 'general@servicios.com', '+1234567895', 'RFC123456794');

-- Insertar tipos de trabajo
INSERT INTO job_types (id, name, description) VALUES 
('550e8400-e29b-41d4-a716-446655440031', 'Desarrollo Web', 'Desarrollo de aplicaciones web'),
('550e8400-e29b-41d4-a716-446655440032', 'Consultoría', 'Servicios de consultoría técnica'),
('550e8400-e29b-41d4-a716-446655440033', 'Mantenimiento', 'Servicios de mantenimiento de sistemas');

-- Insertar tipos de gastos
INSERT INTO expense_types (id, name, description) VALUES 
('550e8400-e29b-41d4-a716-446655440041', 'Materiales', 'Gastos en materiales y suministros'),
('550e8400-e29b-41d4-a716-446655440042', 'Servicios', 'Gastos en servicios externos'),
('550e8400-e29b-41d4-a716-446655440043', 'Transporte', 'Gastos de transporte y combustible');

-- Insertar cuentas bancarias
INSERT INTO bank_accounts (id, name, bank_name, account_number, account_type, balance) VALUES 
('550e8400-e29b-41d4-a716-446655440051', 'Cuenta Principal', 'Banco Nacional', '1234567890', 'business', 50000.00),
('550e8400-e29b-41d4-a716-446655440052', 'Cuenta Secundaria', 'Banco Regional', '0987654321', 'checking', 25000.00);

-- Insertar tipos de pago
INSERT INTO payment_types (id, name, description, bank_account_id) VALUES 
('550e8400-e29b-41d4-a716-446655440061', 'Transferencia Bancaria', 'Pago por transferencia bancaria', '550e8400-e29b-41d4-a716-446655440051'),
('550e8400-e29b-41d4-a716-446655440062', 'Efectivo', 'Pago en efectivo', '550e8400-e29b-41d4-a716-446655440052'),
('550e8400-e29b-41d4-a716-446655440063', 'Cheque', 'Pago con cheque', '550e8400-e29b-41d4-a716-446655440051');

-- Insertar ingreso de ejemplo
INSERT INTO incomes (id, team_id, job_type_id, name, description, total_amount, status, start_date, end_date) VALUES 
('550e8400-e29b-41d4-a716-446655440071', '550e8400-e29b-41d4-a716-446655440001', '550e8400-e29b-41d4-a716-446655440031', 'Proyecto Web E-commerce', 'Desarrollo de plataforma de comercio electrónico', 75000.00, 'in_progress', '2024-01-01', '2024-03-31');

-- Insertar líneas de ingreso
INSERT INTO income_lines (id, income_id, description, quantity, unit_price, total_amount) VALUES 
('550e8400-e29b-41d4-a716-446655440081', '550e8400-e29b-41d4-a716-446655440071', 'Diseño y desarrollo frontend', 1.00, 30000.00, 30000.00),
('550e8400-e29b-41d4-a716-446655440082', '550e8400-e29b-41d4-a716-446655440071', 'Desarrollo backend y API', 1.00, 25000.00, 25000.00),
('550e8400-e29b-41d4-a716-446655440083', '550e8400-e29b-41d4-a716-446655440071', 'Integración de pagos', 1.00, 20000.00, 20000.00);

-- Insertar contratistas del ingreso
INSERT INTO income_contractors (id, income_id, contractor_id, percentage, amount) VALUES 
('550e8400-e29b-41d4-a716-446655440091', '550e8400-e29b-41d4-a716-446655440071', '550e8400-e29b-41d4-a716-446655440011', 40.00, 30000.00),
('550e8400-e29b-41d4-a716-446655440092', '550e8400-e29b-41d4-a716-446655440071', '550e8400-e29b-41d4-a716-446655440012', 35.00, 26250.00);

-- Insertar pago de ingreso
INSERT INTO income_payments (id, income_id, payment_type_id, bank_account_id, amount, payment_date, reference_number, notes) VALUES 
('550e8400-e29b-41d4-a716-446655440101', '550e8400-e29b-41d4-a716-446655440071', '550e8400-e29b-41d4-a716-446655440061', '550e8400-e29b-41d4-a716-446655440051', 37500.00, '2024-01-15', 'TRF20240115001', 'Primer pago del 50%');

-- Insertar gasto de ejemplo
INSERT INTO expenses (id, team_id, vendor_id, expense_type_id, name, description, total_amount, expense_date, status) VALUES 
('550e8400-e29b-41d4-a716-446655440111', '550e8400-e29b-41d4-a716-446655440001', '550e8400-e29b-41d4-a716-446655440021', '550e8400-e29b-41d4-a716-446655440042', 'Licencias de Software', 'Compra de licencias de desarrollo', 5000.00, '2024-01-15', 'approved');

-- Insertar líneas de gasto
INSERT INTO expense_lines (id, expense_id, description, quantity, unit_price, total_amount) VALUES 
('550e8400-e29b-41d4-a716-446655440121', '550e8400-e29b-41d4-a716-446655440111', 'Licencia IDE Profesional', 3.00, 1000.00, 3000.00),
('550e8400-e29b-41d4-a716-446655440122', '550e8400-e29b-41d4-a716-446655440111', 'Licencia Base de Datos', 1.00, 2000.00, 2000.00);

-- Insertar transacciones
INSERT INTO transactions (id, bank_account_id, income_payment_id, type, amount, balance_after, description, transaction_date) VALUES 
('550e8400-e29b-41d4-a716-446655440131', '550e8400-e29b-41d4-a716-446655440051', '550e8400-e29b-41d4-a716-446655440101', 'income', 37500.00, 87500.00, 'Pago recibido del proyecto e-commerce', '2024-01-15');

INSERT INTO transactions (id, bank_account_id, expense_id, type, amount, balance_after, description, transaction_date) VALUES 
('550e8400-e29b-41d4-a716-446655440132', '550e8400-e29b-41d4-a716-446655440051', '550e8400-e29b-41d4-a716-446655440111', 'expense', -5000.00, 82500.00, 'Pago de licencias de software', '2024-01-16');

-- ============================================================================
-- VISTAS ÚTILES PARA REPORTES
-- ============================================================================

-- Vista de resumen de ingresos por equipo
CREATE VIEW v_income_summary AS
SELECT 
    t.name AS team_name,
    jt.name AS job_type,
    COUNT(i.id) AS total_projects,
    SUM(i.total_amount) AS total_income,
    AVG(i.total_amount) AS average_income,
    SUM(CASE WHEN i.status = 'completed' THEN i.total_amount ELSE 0 END) AS completed_income,
    SUM(CASE WHEN i.status = 'in_progress' THEN i.total_amount ELSE 0 END) AS pending_income
FROM incomes i
JOIN teams t ON i.team_id = t.id
JOIN job_types jt ON i.job_type_id = jt.id
GROUP BY t.id, jt.id;

-- Vista de resumen de gastos por equipo
CREATE VIEW v_expense_summary AS
SELECT 
    t.name AS team_name,
    et.name AS expense_type,
    COUNT(e.id) AS total_expenses,
    SUM(e.total_amount) AS total_amount,
    AVG(e.total_amount) AS average_expense,
    SUM(CASE WHEN e.status = 'paid' THEN e.total_amount ELSE 0 END) AS paid_amount,
    SUM(CASE WHEN e.status = 'pending' THEN e.total_amount ELSE 0 END) AS pending_amount
FROM expenses e
JOIN teams t ON e.team_id = t.id
JOIN expense_types et ON e.expense_type_id = et.id
GROUP BY t.id, et.id;

-- Vista de balance de cuentas bancarias
CREATE VIEW v_bank_balance AS
SELECT 
    ba.name AS account_name,
    ba.bank_name,
    ba.account_number,
    ba.balance AS current_balance,
    SUM(CASE WHEN tr.type = 'income' THEN tr.amount ELSE 0 END) AS total_income,
    SUM(CASE WHEN tr.type = 'expense' THEN ABS(tr.amount) ELSE 0 END) AS total_expenses,
    COUNT(tr.id) AS total_transactions
FROM bank_accounts ba
LEFT JOIN transactions tr ON ba.id = tr.bank_account_id
GROUP BY ba.id;

-- ============================================================================
-- COMENTARIOS FINALES
-- ============================================================================

/*
CREDENCIALES DE ACCESO:
- Administrador: admin / admin123
- Usuario normal: user / user123

NOTAS IMPORTANTES:
1. Las contraseñas en los datos de ejemplo están hasheadas con bcrypt
2. Todos los UUIDs son de ejemplo, en producción se generarían automáticamente
3. Las foreign keys mantienen integridad referencial
4. Los índices optimizan las consultas más frecuentes
5. Las vistas simplifican la generación de reportes

SIGUIENTE PASO:
Ejecutar este script en MySQL Workbench y luego usar los archivos PHP
del sistema para interactuar con la base de datos.
*/

-- Mostrar información de finalización
SELECT 'Base de datos APCUADRE creada exitosamente' AS resultado;
SELECT COUNT(*) AS total_tablas FROM information_schema.tables WHERE table_schema = 'cloude_apcuadre';
SELECT 'Sistema listo para usar' AS estado;





DESCRIBE job_types
SELECT * FROM payment_types;

ALTER TABLE job_types
    -- Eliminar la columna description
    DROP COLUMN description,
    -- Agregar columnas de pago como DECIMAL(10,2)
    ADD COLUMN pay_as_contractor DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER name,
    ADD COLUMN pay_as_sub_contractor DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER pay_as_contractor,
    -- Ajustar created_at y updated_at para ser NOT NULL y con los defaults correctos
    MODIFY COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    MODIFY COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- Eliminar el índice único actual en name (si existe con ese nombre)
  
    
    ENGINE=InnoDB,
    DEFAULT CHARSET=utf8mb4,
    COLLATE=utf8mb4_unicode_ci;

ALTER TABLE payment_types
    DROP COLUMN requires_bank_account,
    ADD COLUMN bank_account_id CHAR(36) NOT NULL AFTER description,
    ADD CONSTRAINT fk_payment_type_bank_account FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id) ON DELETE RESTRICT;

ALTER TABLE payment_types
    ADD COLUMN bank_account_id CHAR(36) NULL AFTER description;

UPDATE payment_types
SET bank_account_id = '550e8400-e29b-41d4-a716-446655440051'
WHERE bank_account_id IS NULL
   OR bank_account_id NOT IN (SELECT id FROM bank_accounts);

   ALTER TABLE payment_types
    MODIFY COLUMN bank_account_id CHAR(36) NOT NULL,
    ADD CONSTRAINT fk_payment_type_bank_account FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id) ON DELETE RESTRICT;

    ALTER TABLE payment_types
    DROP COLUMN requires_bank_account;