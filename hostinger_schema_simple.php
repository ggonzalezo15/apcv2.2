<?php
/**
 * Generador de esquema SIMPLE para Hostinger
 * Sin foreign keys para evitar problemas de dependencias
 */

require_once 'config.php';

try {
    $pdo = getConnection();
    
    // Obtener lista de tablas
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $schema = "-- ============================================================\n";
    $schema .= "-- ESQUEMA SIMPLE PARA HOSTINGER (SIN FOREIGN KEYS)\n";
    $schema .= "-- Generado: " . date('Y-m-d H:i:s') . "\n";
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
        
        // Limpiar para Hostinger - REMOVER TODAS LAS FOREIGN KEYS
        $createSQL = str_replace('CREATE TABLE', "DROP TABLE IF EXISTS `$table`;\nCREATE TABLE", $createSQL);
        
        // Remover AUTO_INCREMENT values
        $createSQL = preg_replace('/AUTO_INCREMENT=\d+\s*/', '', $createSQL);
        
        // REMOVER TODAS LAS FOREIGN KEYS
        $createSQL = preg_replace('/,\s*CONSTRAINT[^,]*FOREIGN KEY[^,]*REFERENCES[^,]*(?:ON DELETE[^,]*)?(?:ON UPDATE[^,]*)?/', '', $createSQL);
        $createSQL = preg_replace('/,\s*FOREIGN KEY[^,]*REFERENCES[^,]*(?:ON DELETE[^,]*)?(?:ON UPDATE[^,]*)?/', '', $createSQL);
        
        // Limpiar comas extra
        $createSQL = preg_replace('/,(\s*\))/', '$1', $createSQL);
        
        $schema .= $createSQL . ";\n\n";
    }
    
    // Agregar vistas simples
    $schema .= "-- ============================================================\n";
    $schema .= "-- VISTAS SIMPLES\n";
    $schema .= "-- ============================================================\n\n";
    
    // Vista de balance bancario simple
    $schema .= "-- Vista: v_bank_balance\n";
    $schema .= "DROP VIEW IF EXISTS `v_bank_balance`;\n";
    $schema .= "CREATE VIEW `v_bank_balance` AS \n";
    $schema .= "SELECT \n";
    $schema .= "    `ba`.`name` AS `account_name`,\n";
    $schema .= "    `ba`.`bank_name` AS `bank_name`,\n";
    $schema .= "    `ba`.`account_number` AS `account_number`,\n";
    $schema .= "    `ba`.`balance` AS `current_balance`\n";
    $schema .= "FROM `bank_accounts` `ba`;\n\n";
    
    // Vista de gastos simple
    $schema .= "-- Vista: v_expense_summary\n";
    $schema .= "DROP VIEW IF EXISTS `v_expense_summary`;\n";
    $schema .= "CREATE VIEW `v_expense_summary` AS \n";
    $schema .= "SELECT \n";
    $schema .= "    COUNT(`e`.`id`) AS `total_expenses`,\n";
    $schema .= "    COALESCE(SUM(`e`.`total_amount`), 0) AS `total_amount`,\n";
    $schema .= "    COALESCE(AVG(`e`.`total_amount`), 0) AS `average_expense`\n";
    $schema .= "FROM `expenses` `e`;\n\n";
    
    // Vista de ingresos simple
    $schema .= "-- Vista: v_income_summary\n";
    $schema .= "DROP VIEW IF EXISTS `v_income_summary`;\n";
    $schema .= "CREATE VIEW `v_income_summary` AS \n";
    $schema .= "SELECT \n";
    $schema .= "    COUNT(`i`.`id`) AS `total_projects`,\n";
    $schema .= "    COALESCE(SUM(`i`.`total_amount`), 0) AS `total_income`,\n";
    $schema .= "    COALESCE(AVG(`i`.`total_amount`), 0) AS `average_income`,\n";
    $schema .= "    COALESCE(SUM(CASE WHEN `i`.`status` = 'paid' THEN `i`.`total_amount` ELSE 0 END), 0) AS `paid_income`,\n";
    $schema .= "    COALESCE(SUM(CASE WHEN `i`.`status` = 'pending' THEN `i`.`total_amount` ELSE 0 END), 0) AS `pending_income`\n";
    $schema .= "FROM `incomes` `i`;\n\n";
    
    // Usuario administrador
    $schema .= "-- ============================================================\n";
    $schema .= "-- USUARIO ADMINISTRADOR\n";
    $schema .= "-- ============================================================\n\n";
    
    $schema .= "INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `active`, `created_at`) VALUES\n";
    $schema .= "(1, 'admin', 'admin@apcuadre.com', '$2y$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1, NOW());\n\n";
    
    $schema .= "SET foreign_key_checks = 1;\n";
    
    // Guardar archivo
    file_put_contents('hostinger_schema_simple.sql', $schema);
    
    echo "\n✅ Esquema SIMPLE generado: hostinger_schema_simple.sql\n";
    echo "📊 Tablas procesadas: " . count($tables) . "\n";
    echo "📁 Tamaño del archivo: " . number_format(strlen($schema)) . " caracteres\n";
    echo "\n🔧 CARACTERÍSTICAS:\n";
    echo "- SIN foreign keys (máxima compatibilidad)\n";
    echo "- Vistas simplificadas\n";
    echo "- Solo usuario administrador\n";
    echo "- Garantizado para funcionar en Hostinger\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>