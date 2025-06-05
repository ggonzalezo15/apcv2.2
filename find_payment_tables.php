<?php
require_once 'config.php';

echo "<h2>Buscando tablas relacionadas con payments/income</h2>\n";

try {
    $pdo = getConnection();
    
    // Obtener todas las tablas
    $result = $pdo->query("SHOW TABLES");
    $tables = $result->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h3>Todas las tablas en la base de datos:</h3>\n";
    echo "<ul>\n";
    
    $paymentTables = [];
    $incomeTables = [];
    
    foreach ($tables as $table) {
        echo "<li>$table";
        
        if (stripos($table, 'payment') !== false) {
            echo " 💰 (payment)";
            $paymentTables[] = $table;
        }
        
        if (stripos($table, 'income') !== false) {
            echo " 💵 (income)";
            $incomeTables[] = $table;
        }
        
        echo "</li>\n";
    }
    echo "</ul>\n";
    
    echo "<hr>\n";
    echo "<h3>Tablas relacionadas con PAYMENTS:</h3>\n";
    if (!empty($paymentTables)) {
        echo "<ul>\n";
        foreach ($paymentTables as $table) {
            echo "<li><strong>$table</strong></li>\n";
        }
        echo "</ul>\n";
    } else {
        echo "<p style='color: orange;'>⚠️ No se encontraron tablas con 'payment' en el nombre.</p>\n";
    }
    
    echo "<h3>Tablas relacionadas con INCOME:</h3>\n";
    if (!empty($incomeTables)) {
        echo "<ul>\n";
        foreach ($incomeTables as $table) {
            echo "<li><strong>$table</strong></li>\n";
        }
        echo "</ul>\n";
    } else {
        echo "<p style='color: orange;'>⚠️ No se encontraron tablas con 'income' en el nombre.</p>\n";
    }
    
    // Verificar específicamente income_payments
    echo "<hr>\n";
    echo "<h3>Verificando 'income_payments' específicamente:</h3>\n";
    
    $incomePaymentsExists = $pdo->query("SHOW TABLES LIKE 'income_payments'")->rowCount() > 0;
    
    if ($incomePaymentsExists) {
        echo "<p style='color: green;'>✅ La tabla 'income_payments' EXISTE.</p>\n";
        
        // Mostrar estructura
        $structure = $pdo->query("DESCRIBE income_payments");
        $columns = $structure->fetchAll();
        
        echo "<h4>Estructura de 'income_payments':</h4>\n";
        echo "<table border='1' style='border-collapse: collapse;'>\n";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th></tr>\n";
        
        foreach ($columns as $col) {
            echo "<tr>";
            echo "<td>{$col['Field']}</td>";
            echo "<td>{$col['Type']}</td>";
            echo "<td>{$col['Null']}</td>";
            echo "<td>{$col['Key']}</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
        
    } else {
        echo "<p style='color: red;'>❌ La tabla 'income_payments' NO EXISTE.</p>\n";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>\n";
}
?> 