<?php
/**
 * Corrector del esquema para Hostinger
 * Reordena las tablas para evitar problemas de foreign keys
 */

require_once 'config.php';

try {
    $pdo = getConnection();
    
    // Obtener lista de tablas en orden correcto (sin foreign keys primero)
    $tablesOrder = [
        'users',                    // Sin foreign keys
        'teams',                    // Sin foreign keys
        'bank_accounts',            // Sin foreign keys
        'expense_categories',       // Sin foreign keys
        'job_types',               // Sin foreign keys
        'contractors',             // Sin foreign keys
        'vendors',                 // Sin foreign keys
        'cleanup_logs',            // Sin foreign keys
        'failed_login_attempts',   // Sin foreign keys
        'expense_types',           // FK a expense_categories
        'payment_types',           // FK a bank_accounts
        'expenses',                // FK a teams, vendors, users
        'incomes',                 // FK a teams
        'expense_lines',           // FK a expenses, expense_types
        'expense_attachments',     // FK a expenses
        'income_lines',            // FK a incomes, job_types
        'income_payments',         // FK a incomes, payment_types, bank_accounts
        'contractor_payments',     // FK a contractors, bank_accounts
        'transactions',            // FK a bank_accounts, income_payments, expenses
        'activity_logs'            // FK a users
    ];
    
    $schema = "-- ============================================================\n";
    $schema .= "-- ESQUEMA CORREGIDO PARA HOSTINGER\n";
    $schema .= "-- Generado: " . date('Y-m-d H:i:s') . "\n";
    $schema .= "-- Orden de tablas optimizado para evitar errores de FK\n";
    $schema .= "-- ============================================================\n\n";
    
    $schema .= "SET foreign_key_checks = 0;\n";
    $schema .= "SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';\n";
    $schema .= "SET time_zone = '+00:00';\n\n";
    
    foreach ($tablesOrder as $table) {
        echo "Procesando tabla: $table\n";
        
        // Verificar que la tabla existe
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() == 0) {
            echo "⚠️ Tabla $table no encontrada, saltando...\n";
            continue;
        }
        
        $schema .= "-- ============================================================\n";
        $schema .= "-- TABLA: $table\n";
        $schema .= "-- ============================================================\n";
        
        // Obtener CREATE TABLE
        $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
        $createTable = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $createSQL = $createTable['Create Table'];
        
        // Limpiar para Hostinger
        $createSQL = str_replace('CREATE TABLE', "DROP TABLE IF EXISTS `$table`;\nCREATE TABLE", $createSQL);
        
        // Remover AUTO_INCREMENT values
        $createSQL = preg_replace('/AUTO_INCREMENT=\d+\s*/', '', $createSQL);
        
        // Simplificar CONSTRAINT names
        $createSQL = preg_replace('/CONSTRAINT `[^`]+` FOREIGN KEY/', 'FOREIGN KEY', $createSQL);
        
        $schema .= $createSQL . ";\n\n";
    }
    
    // Agregar vistas al final
    $schema .= "-- ============================================================\n";
    $schema .= "-- VISTAS OPTIMIZADAS\n";
    $schema .= "-- ============================================================\n\n";
    
    // Vista de balance bancario
    $schema .= "-- Vista: v_bank_balance\n";
    $schema .= "DROP VIEW IF EXISTS `v_bank_balance`;\n";
    $schema .= "CREATE VIEW `v_bank_balance` AS \n";
    $schema .= "SELECT \n";
    $schema .= "    `ba`.`name` AS `account_name`,\n";
    $schema .= "    `ba`.`bank_name` AS `bank_name`,\n";
    $schema .= "    `ba`.`account_number` AS `account_number`,\n";
    $schema .= "    `ba`.`balance` AS `current_balance`,\n";
    $schema .= "    COALESCE(SUM(CASE WHEN `tr`.`type` = 'income' THEN `tr`.`amount` ELSE 0 END), 0) AS `total_income`,\n";
    $schema .= "    COALESCE(SUM(CASE WHEN `tr`.`type` = 'expense' THEN ABS(`tr`.`amount`) ELSE 0 END), 0) AS `total_expenses`,\n";
    $schema .= "    COUNT(`tr`.`id`) AS `total_transactions`\n";
    $schema .= "FROM `bank_accounts` `ba` \n";
    $schema .= "LEFT JOIN `transactions` `tr` ON (`ba`.`id` = `tr`.`bank_account_id`)\n";
    $schema .= "GROUP BY `ba`.`id`, `ba`.`name`, `ba`.`bank_name`, `ba`.`account_number`, `ba`.`balance`;\n\n";
    
    // Vista de resumen de gastos (sin FK problemáticas)
    $schema .= "-- Vista: v_expense_summary\n";
    $schema .= "DROP VIEW IF EXISTS `v_expense_summary`;\n";
    $schema .= "CREATE VIEW `v_expense_summary` AS \n";
    $schema .= "SELECT \n";
    $schema .= "    `t`.`name` AS `team_name`,\n";
    $schema .= "    COUNT(`e`.`id`) AS `total_expenses`,\n";
    $schema .= "    COALESCE(SUM(`e`.`total_amount`), 0) AS `total_amount`,\n";
    $schema .= "    COALESCE(AVG(`e`.`total_amount`), 0) AS `average_expense`\n";
    $schema .= "FROM `expenses` `e` \n";
    $schema .= "JOIN `teams` `t` ON (`e`.`team_id` = `t`.`id`)\n";
    $schema .= "GROUP BY `t`.`id`, `t`.`name`;\n\n";
    
    // Vista de resumen de ingresos
    $schema .= "-- Vista: v_income_summary\n";
    $schema .= "DROP VIEW IF EXISTS `v_income_summary`;\n";
    $schema .= "CREATE VIEW `v_income_summary` AS \n";
    $schema .= "SELECT \n";
    $schema .= "    `t`.`name` AS `team_name`,\n";
    $schema .= "    COUNT(`i`.`id`) AS `total_projects`,\n";
    $schema .= "    COALESCE(SUM(`i`.`total_amount`), 0) AS `total_income`,\n";
    $schema .= "    COALESCE(AVG(`i`.`total_amount`), 0) AS `average_income`,\n";
    $schema .= "    COALESCE(SUM(CASE WHEN `i`.`status` = 'paid' THEN `i`.`total_amount` ELSE 0 END), 0) AS `paid_income`,\n";
    $schema .= "    COALESCE(SUM(CASE WHEN `i`.`status` = 'pending' THEN `i`.`total_amount` ELSE 0 END), 0) AS `pending_income`,\n";
    $schema .= "    COALESCE(SUM(CASE WHEN `i`.`status` = 'overpaid' THEN `i`.`total_amount` ELSE 0 END), 0) AS `overpaid_income`\n";
    $schema .= "FROM `incomes` `i` \n";
    $schema .= "JOIN `teams` `t` ON (`i`.`team_id` = `t`.`id`)\n";
    $schema .= "GROUP BY `t`.`id`, `t`.`name`;\n\n";
    
    // Agregar solo usuario administrador
    $schema .= "-- ============================================================\n";
    $schema .= "-- USUARIO ADMINISTRADOR INICIAL\n";
    $schema .= "-- ============================================================\n\n";
    
    $schema .= "-- Usuario administrador por defecto (CAMBIAR CONTRASEÑA DESPUÉS DEL PRIMER LOGIN)\n";
    $schema .= "INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `active`, `created_at`) VALUES\n";
    $schema .= "(1, 'admin', 'admin@apcuadre.com', '$2y$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1, NOW());\n\n";
    
    $schema .= "-- ============================================================\n";
    $schema .= "-- CONFIGURACIONES FINALES\n";
    $schema .= "-- ============================================================\n";
    $schema .= "SET foreign_key_checks = 1;\n";
    
    // Guardar archivo corregido
    file_put_contents('hostinger_schema_fixed.sql', $schema);
    
    echo "\n✅ Esquema corregido generado: hostinger_schema_fixed.sql\n";
    echo "📊 Tablas procesadas: " . count($tablesOrder) . "\n";
    echo "📁 Tamaño del archivo: " . number_format(strlen($schema)) . " caracteres\n";
    echo "\n🔧 DIFERENCIAS PRINCIPALES:\n";
    echo "- Orden de tablas optimizado para evitar errores de FK\n";
    echo "- Foreign keys simplificadas\n";
    echo "- Vista v_expense_summary simplificada (sin expense_types)\n";
    echo "- Solo usuario administrador incluido\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>