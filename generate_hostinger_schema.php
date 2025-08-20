<?php
/**
 * Generador de esquema optimizado para Hostinger
 * Conecta a la BD actual y genera un esquema limpio
 */

require_once 'config.php';

try {
    $pdo = getConnection();
    
    // Obtener lista de tablas
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $schema = "-- ============================================================\n";
    $schema .= "-- ESQUEMA OPTIMIZADO PARA HOSTINGER\n";
    $schema .= "-- Generado automáticamente: " . date('Y-m-d H:i:s') . "\n";
    $schema .= "-- Compatible con hosting compartido\n";
    $schema .= "-- ============================================================\n\n";
    
    $schema .= "SET foreign_key_checks = 0;\n";
    $schema .= "SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';\n";
    $schema .= "SET time_zone = '+00:00';\n\n";
    
    foreach ($tables as $table) {
        echo "Procesando tabla: $table\n";
        
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
        
        $schema .= $createSQL . ";\n\n";
    }
    
    // Agregar vistas optimizadas
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
    
    // Vista de resumen de gastos
    $schema .= "-- Vista: v_expense_summary\n";
    $schema .= "DROP VIEW IF EXISTS `v_expense_summary`;\n";
    $schema .= "CREATE VIEW `v_expense_summary` AS \n";
    $schema .= "SELECT \n";
    $schema .= "    `t`.`name` AS `team_name`,\n";
    $schema .= "    `et`.`name` AS `expense_type`,\n";
    $schema .= "    COUNT(`e`.`id`) AS `total_expenses`,\n";
    $schema .= "    COALESCE(SUM(`e`.`total_amount`), 0) AS `total_amount`,\n";
    $schema .= "    COALESCE(AVG(`e`.`total_amount`), 0) AS `average_expense`\n";
    $schema .= "FROM `expenses` `e` \n";
    $schema .= "JOIN `teams` `t` ON (`e`.`team_id` = `t`.`id`)\n";
    $schema .= "JOIN `expense_types` `et` ON (`e`.`expense_type_id` = `et`.`id`)\n";
    $schema .= "GROUP BY `t`.`id`, `t`.`name`, `et`.`id`, `et`.`name`;\n\n";
    
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
    
    $schema .= "-- ============================================================\n";
    $schema .= "-- CONFIGURACIONES FINALES\n";
    $schema .= "-- ============================================================\n";
    $schema .= "SET foreign_key_checks = 1;\n";
    
    // Guardar archivo
    file_put_contents('hostinger_schema_final.sql', $schema);
    
    echo "\n✅ Esquema generado exitosamente: hostinger_schema_final.sql\n";
    echo "📊 Tablas procesadas: " . count($tables) . "\n";
    echo "📁 Tamaño del archivo: " . number_format(strlen($schema)) . " caracteres\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>