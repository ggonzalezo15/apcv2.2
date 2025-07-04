-- ============================================================
-- ESQUEMA LIMPIO DE BASE DE DATOS: cloude_apcuadre
-- Generado: 2025-07-04 21:11:09
-- Después de limpieza automatizada
-- Total de tablas: 21
-- ============================================================

-- Configuraciones iniciales
SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

-- Crear base de datos
CREATE DATABASE IF NOT EXISTS `cloude_apcuadre` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `cloude_apcuadre`;

-- ============================================================
-- SECCIÓN 1: TABLAS DEL SISTEMA CORE
-- ============================================================

-- Tabla: users
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','user') COLLATE utf8mb4_unicode_ci DEFAULT 'user',
  `active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_username` (`username`),
  KEY `idx_email` (`email`),
  KEY `idx_active` (`active`),
  KEY `idx_role` (`role`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos para users (2 registros)
INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `active`, `created_at`, `updated_at`, `last_login`) VALUES
('1', 'ggonzalezo15', 'ggonzalez@airpropr.com', '$2y$10$8wWSWeOOLN.PhNet4DHdVeJVHo0ZmtJA9jawJS1ZF4EdSJZrT73X6', 'admin', '1', '2025-05-23 11:49:25', '2025-07-04 14:38:31', '2025-07-04 14:38:31'),
('6', 'jarroyo', 'jarroyo@airpropr.com', '$2y$10$cykvW9TICAoAUslc0P2PEe5xM8cCOwp7xAryBm3bTgDPC.WsW7F6G', 'admin', '1', '2025-05-31 16:30:04', '2025-05-31 16:30:04', NULL);

-- Tabla: teams
DROP TABLE IF EXISTS `teams`;
CREATE TABLE `teams` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_name` (`name`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos para teams (6 registros)
INSERT INTO `teams` (`id`, `name`, `description`, `status`, `created_at`, `updated_at`, `active`) VALUES
('', 'Equipo Administrativo', 'Equipo encargado de tareas administrativas', 'active', '2025-05-31 16:47:05', '2025-05-31 16:47:05', '1'),
('550e8400-e29b-41d4-a716-446655440001', 'Manuel l', '', 'active', '2025-05-23 11:49:25', '2025-07-02 10:06:02', '1'),
('683bb9c5580423.07435491', 'Abdel', '', 'active', '2025-05-31 22:24:05', '2025-06-12 18:44:13', '1'),
('683dfc261140a6.20405746', 'Robert', '', 'active', '2025-06-02 15:31:50', '2025-06-02 15:31:50', '1'),
('6841717044d0b4.74477968', 'Josue', '', 'inactive', '2025-06-05 06:29:04', '2025-07-02 16:16:04', '1'),
('684171a9849764.33761629', 'Christian', '', 'inactive', '2025-06-05 06:30:01', '2025-07-02 16:26:24', '1');

-- Tabla: job_types
DROP TABLE IF EXISTS `job_types`;
CREATE TABLE `job_types` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pay_as_contractor` decimal(10,2) NOT NULL DEFAULT '0.00',
  `pay_as_sub_contractor` decimal(10,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_name` (`name`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos para job_types (5 registros)
INSERT INTO `job_types` (`id`, `name`, `pay_as_contractor`, `pay_as_sub_contractor`, `created_at`, `updated_at`, `status`) VALUES
('550e8400-e29b-41d4-a716-446655440031', 'M18', '60.00', '70.00', '2025-05-23 11:49:25', '2025-06-12 19:43:50', 'active'),
('683bb631aeb542.15609012', 'M12', '55.00', '40.00', '2025-05-31 22:08:49', '2025-06-12 19:41:07', 'active'),
('68409e78edd707.92763215', 'M24', '60.00', '60.00', '2025-06-04 15:28:56', '2025-06-04 15:28:56', 'active'),
('6863ffff143d57.65882540', 'M36', '70.00', '70.00', '2025-07-01 11:34:23', '2025-07-02 15:59:19', 'inactive'),
('68653f1fc19a61.69387196', 'M 36', '0.00', '0.00', '2025-07-02 10:15:59', '2025-07-02 10:15:59', 'active');

-- Tabla: payment_types
DROP TABLE IF EXISTS `payment_types`;
CREATE TABLE `payment_types` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `bank_account_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_name` (`name`),
  KEY `idx_name` (`name`),
  KEY `fk_payment_type_bank_account` (`bank_account_id`),
  CONSTRAINT `fk_payment_type_bank_account` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos para payment_types (6 registros)
INSERT INTO `payment_types` (`id`, `name`, `description`, `bank_account_id`, `created_at`, `updated_at`, `status`) VALUES
('4e9147e1f4cf7b364110e5e0a00b1aa1', 'Juna', 'd', '68647982e070b8.97295793', '2025-07-02 16:03:09', '2025-07-02 16:03:09', 'active'),
('550e8400-e29b-41d4-a716-446655440061', 'Transferencia Bancaria', 'Pago por transferencia bancaria', '550e8400-e29b-41d4-a716-446655440051', '2025-05-23 11:49:25', '2025-05-23 22:31:39', 'active'),
('86914b673ce683ff25ad8eb5f1602f5d', 'ATH B', '', '68313cd27804a0.64298110', '2025-05-31 22:01:20', '2025-07-02 16:02:46', 'inactive'),
('a6ddd5632ba5f6f1bc9ca54db23f6f18', 'Tarjeta de Credito', '', '550e8400-e29b-41d4-a716-446655440051', '2025-07-01 19:58:10', '2025-07-01 20:10:23', 'active'),
('ae639c2e682e64fd70f927978bbc2d1c', 'ATH M', '', '550e8400-e29b-41d4-a716-446655440052', '2025-06-02 14:26:36', '2025-06-12 19:01:00', 'active'),
('ce38ab2708bce2256962cbfe6884a6c7', 'www', '', '68647982e070b8.97295793', '2025-07-02 10:34:36', '2025-07-02 10:34:36', 'active');

-- Tabla: expense_types
DROP TABLE IF EXISTS `expense_types`;
CREATE TABLE `expense_types` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `category_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_name` (`name`),
  KEY `idx_name` (`name`),
  KEY `fk_expense_types_category` (`category_id`),
  CONSTRAINT `fk_expense_types_category` FOREIGN KEY (`category_id`) REFERENCES `expense_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos para expense_types (6 registros)
INSERT INTO `expense_types` (`id`, `name`, `description`, `category_id`, `created_at`, `updated_at`, `status`) VALUES
('550e8400-e29b-41d4-a716-446655440042', 'Peaje', 'Gastos en servicios externos', 'cat_683b43d1ca6159.41251211', '2025-05-23 11:49:25', '2025-07-02 10:56:51', 'active'),
('type_683bb4a5492b4', 'Internet', '', 'cat_683bb49a7f5a3', '2025-05-31 22:02:13', '2025-05-31 22:02:13', 'active'),
('type_683bb4b2991a5', 'Gasolina', '', 'cat_683bb484143c6', '2025-05-31 22:02:26', '2025-05-31 22:02:26', 'active'),
('type_683bb4be16fe2', 'Peajes', '', 'cat_683bb484143c6', '2025-05-31 22:02:38', '2025-05-31 22:02:38', 'active'),
('type_6841848974708', 'Equipos AC', '', 'cat_684184753751b', '2025-06-05 07:50:33', '2025-07-02 10:04:58', 'active'),
('type_686548d7d65eb', 'sexo', '', 'cat_683b43d1ca6159.41251211', '2025-07-02 10:57:27', '2025-07-02 15:59:12', 'inactive');

-- Tabla: expense_categories
DROP TABLE IF EXISTS `expense_categories`;
CREATE TABLE `expense_categories` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `color` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT '#6B7280',
  `icon` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'fas fa-tag',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos para expense_categories (4 registros)
INSERT INTO `expense_categories` (`id`, `name`, `description`, `color`, `icon`, `created_at`, `updated_at`, `status`) VALUES
('cat_683b43d1ca6159.41251211', 'Alimentación', 'Gastos en comidas y bebidas', '#10B981', 'fas fa-utensils', '2025-05-31 14:00:49', '2025-06-12 19:28:50', 'active'),
('cat_683bb484143c6', 'Transportacion', '', '#6B7280', 'fas fa-tag', '2025-05-31 22:01:40', '2025-05-31 22:01:40', 'active'),
('cat_683bb49a7f5a3', 'Utilidades', '', '#6B7280', 'fas fa-tag', '2025-05-31 22:02:02', '2025-07-02 15:58:58', 'inactive'),
('cat_684184753751b', 'Materiales', '', '#6B7280', 'fas fa-tag', '2025-06-05 07:50:13', '2025-07-02 10:04:00', 'inactive');

-- ============================================================
-- SECCIÓN 2: TABLAS DE NEGOCIO
-- ============================================================

-- Tabla: contractors
DROP TABLE IF EXISTS `contractors`;
CREATE TABLE `contractors` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_name` (`name`),
  KEY `idx_name` (`name`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: vendors
DROP TABLE IF EXISTS `vendors`;
CREATE TABLE `vendors` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_name` (`name`),
  KEY `idx_name` (`name`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: bank_accounts
DROP TABLE IF EXISTS `bank_accounts`;
CREATE TABLE `bank_accounts` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'ahorros',
  `bank_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `balance` decimal(15,2) DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `account_type` enum('cheque','credito','ahorro','caja_chica') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cheque',
  `active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_account_number` (`account_number`),
  KEY `idx_name` (`name`),
  KEY `idx_bank_name` (`bank_name`),
  KEY `idx_account_number` (`account_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SECCIÓN 3: TABLAS OPERACIONALES
-- ============================================================

-- Tabla: expenses
DROP TABLE IF EXISTS `expenses`;
CREATE TABLE `expenses` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `team_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `expense_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int NOT NULL DEFAULT '1',
  `expense_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bank_account_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `expense_number` (`expense_number`),
  KEY `idx_team_id` (`team_id`),
  KEY `idx_vendor_id` (`vendor_id`),
  KEY `idx_expense_date` (`expense_date`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `expenses_ibfk_2` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `expenses_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: expense_lines
DROP TABLE IF EXISTS `expense_lines`;
CREATE TABLE `expense_lines` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expense_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `expense_type_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deducible` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_expense_id` (`expense_id`),
  KEY `fk_expense_lines_expense_type` (`expense_type_id`),
  CONSTRAINT `expense_lines_ibfk_1` FOREIGN KEY (`expense_id`) REFERENCES `expenses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_expense_lines_expense_type` FOREIGN KEY (`expense_type_id`) REFERENCES `expense_types` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: expense_attachments
DROP TABLE IF EXISTS `expense_attachments`;
CREATE TABLE `expense_attachments` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expense_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Ruta local o URL del archivo',
  `file_size` int NOT NULL COMMENT 'Tamaño del archivo en bytes',
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Tipo MIME del archivo',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `file_key` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Clave del archivo en BackBlaze B2',
  `compressed` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Indica si el archivo fue comprimido',
  PRIMARY KEY (`id`),
  KEY `idx_expense_id` (`expense_id`),
  KEY `idx_file_key` (`file_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: incomes
DROP TABLE IF EXISTS `incomes`;
CREATE TABLE `incomes` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `team_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `status` enum('pending','paid','overpaid') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `invoice_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `income_date` date DEFAULT NULL,
  `contractor_ids` json DEFAULT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `idx_team_id` (`team_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `incomes_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: income_lines
DROP TABLE IF EXISTS `income_lines`;
CREATE TABLE `income_lines` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `income_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT '1.00',
  `unit_price` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `job_types_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_income_id` (`income_id`),
  KEY `idx_job_types_id` (`job_types_id`),
  CONSTRAINT `fk_income_lines_job_types` FOREIGN KEY (`job_types_id`) REFERENCES `job_types` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `income_lines_ibfk_1` FOREIGN KEY (`income_id`) REFERENCES `incomes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: income_payments
DROP TABLE IF EXISTS `income_payments`;
CREATE TABLE `income_payments` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `income_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_type_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bank_account_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `fee` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_income_id` (`income_id`),
  KEY `idx_payment_type_id` (`payment_type_id`),
  KEY `idx_bank_account_id` (`bank_account_id`),
  CONSTRAINT `income_payments_ibfk_1` FOREIGN KEY (`income_id`) REFERENCES `incomes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `income_payments_ibfk_2` FOREIGN KEY (`payment_type_id`) REFERENCES `payment_types` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `income_payments_ibfk_3` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: contractor_payments
DROP TABLE IF EXISTS `contractor_payments`;
CREATE TABLE `contractor_payments` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contractor_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bank_account_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_date` date NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `reference_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_contractor_id` (`contractor_id`),
  KEY `idx_bank_account_id` (`bank_account_id`),
  KEY `idx_payment_date` (`payment_date`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `contractor_payments_ibfk_1` FOREIGN KEY (`contractor_id`) REFERENCES `contractors` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `contractor_payments_ibfk_2` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SECCIÓN 4: TABLAS DEL SISTEMA
-- ============================================================

-- Tabla: transactions
DROP TABLE IF EXISTS `transactions`;
CREATE TABLE `transactions` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bank_account_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `income_payment_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expense_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` enum('income','expense','transfer','transfer_out','transfer_in','credit_payment','payment_received','deposito_inicial','balance_inicial_credito','limite_inicial_credito','payment_income','payment_fee') COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `balance_after` decimal(15,2) NOT NULL DEFAULT '0.00',
  `description` text COLLATE utf8mb4_unicode_ci,
  `transaction_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bank_account_id` (`bank_account_id`),
  KEY `idx_income_payment_id` (`income_payment_id`),
  KEY `idx_expense_id` (`expense_id`),
  KEY `idx_type` (`type`),
  KEY `idx_transaction_date` (`transaction_date`),
  CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`income_payment_id`) REFERENCES `income_payments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `transactions_ibfk_3` FOREIGN KEY (`expense_id`) REFERENCES `expenses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: activity_logs
DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` varchar(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_entity` (`entity_type`,`entity_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: v_bank_balance
DROP TABLE IF EXISTS `v_bank_balance`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_bank_balance` AS select `ba`.`name` AS `account_name`,`ba`.`bank_name` AS `bank_name`,`ba`.`account_number` AS `account_number`,`ba`.`balance` AS `current_balance`,sum((case when (`tr`.`type` = 'income') then `tr`.`amount` else 0 end)) AS `total_income`,sum((case when (`tr`.`type` = 'expense') then abs(`tr`.`amount`) else 0 end)) AS `total_expenses`,count(`tr`.`id`) AS `total_transactions` from (`bank_accounts` `ba` left join `transactions` `tr` on((`ba`.`id` = `tr`.`bank_account_id`))) group by `ba`.`id`;

-- ============================================================
-- SECCIÓN 5: TABLAS ADICIONALES
-- ============================================================

-- Tabla: v_expense_summary
DROP TABLE IF EXISTS `v_expense_summary`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_expense_summary` AS select `t`.`name` AS `team_name`,`et`.`name` AS `expense_type`,count(`e`.`id`) AS `total_expenses`,sum(`e`.`total_amount`) AS `total_amount`,avg(`e`.`total_amount`) AS `average_expense`,sum((case when (`e`.`status` = 'paid') then `e`.`total_amount` else 0 end)) AS `paid_amount`,sum((case when (`e`.`status` = 'pending') then `e`.`total_amount` else 0 end)) AS `pending_amount` from ((`expenses` `e` join `teams` `t` on((`e`.`team_id` = `t`.`id`))) join `expense_types` `et` on((`e`.`expense_type_id` = `et`.`id`))) group by `t`.`id`,`et`.`id`;

-- Tabla: v_income_summary
DROP TABLE IF EXISTS `v_income_summary`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_income_summary` AS select `t`.`name` AS `team_name`,`jt`.`name` AS `job_type`,count(`i`.`id`) AS `total_projects`,sum(`i`.`total_amount`) AS `total_income`,avg(`i`.`total_amount`) AS `average_income`,sum((case when (`i`.`status` = 'completed') then `i`.`total_amount` else 0 end)) AS `completed_income`,sum((case when (`i`.`status` = 'in_progress') then `i`.`total_amount` else 0 end)) AS `pending_income` from ((`incomes` `i` join `teams` `t` on((`i`.`team_id` = `t`.`id`))) join `job_types` `jt` on((`i`.`job_type_id` = `jt`.`id`))) group by `t`.`id`,`jt`.`id`;

-- ============================================================
-- CONFIGURACIONES FINALES
-- ============================================================

SET foreign_key_checks = 1;

-- Crear usuario administrador por defecto (cambiar contraseña después)
INSERT IGNORE INTO `users` (`id`, `username`, `email`, `password`, `role`, `active`, `created_at`) VALUES 
(1, 'admin', 'admin@sistema.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1, NOW());

-- ============================================================
-- ESQUEMA LIMPIO COMPLETADO
-- Listo para usar en nueva máquina
-- ============================================================
