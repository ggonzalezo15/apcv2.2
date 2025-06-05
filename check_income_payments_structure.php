<?php
require_once 'config.php';

echo "<h2>Estructura actual de la tabla 'income_payments'</h2>\n";

try {
    $pdo = getConnection();
    
    // Verificar si la tabla income_payments existe
    $tableExists = $pdo->query("SHOW TABLES LIKE 'income_payments'")->rowCount() > 0;
    
    if (!$tableExists) {
        echo "<p style='color: red;'>❌ La tabla 'income_payments' no existe en la base de datos.</p>\n";
        
        // Buscar tablas relacionadas
        echo "<h3>Buscando tablas relacionadas con payment...</h3>\n";
        $tablesResult = $pdo->query("SHOW TABLES");
        $tables = $tablesResult->fetchAll(PDO::FETCH_COLUMN);
        
        $paymentTables = array_filter($tables, function($table) {
            return stripos($table, 'payment') !== false || stripos($table, 'income') !== false;
        });
        
        if (!empty($paymentTables)) {
            echo "<p style='color: blue;'>📋 Tablas encontradas relacionadas:</p>\n";
            echo "<ul>\n";
            foreach ($paymentTables as $table) {
                echo "<li>$table</li>\n";
            }
            echo "</ul>\n";
        }
        exit;
    }
    
    echo "<p style='color: green;'>✅ La tabla 'income_payments' existe.</p>\n";
    
    // Columnas a eliminar
    $columnsToRemove = ['payment_date', 'reference_number', 'notes'];
    
    // Obtener la estructura de la tabla
    echo "<h3>Columnas actuales:</h3>\n";
    $result = $pdo->query("DESCRIBE income_payments");
    $columns = $result->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th>";
    echo "</tr>\n";
    
    $foundColumnsToRemove = [];
    $missingColumns = [];
    
    foreach ($columns as $column) {
        $isToRemove = in_array($column['Field'], $columnsToRemove);
        
        if ($isToRemove) {
            $foundColumnsToRemove[] = $column['Field'];
        }
        
        $rowStyle = $isToRemove ? "style='background-color: #ffcccc;'" : "";
        
        echo "<tr $rowStyle>";
        echo "<td>" . $column['Field'] . ($isToRemove ? " ❌ (eliminar)" : "") . "</td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "<td>" . ($column['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . $column['Extra'] . "</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    // Verificar columnas faltantes
    $missingColumns = array_diff($columnsToRemove, $foundColumnsToRemove);
    
    echo "<h3>Estado de las columnas a eliminar:</h3>\n";
    echo "<ul>\n";
    
    foreach ($columnsToRemove as $col) {
        if (in_array($col, $foundColumnsToRemove)) {
            echo "<li style='color: red;'>❌ <strong>$col</strong> - Encontrada (se eliminará)</li>\n";
        } else {
            echo "<li style='color: orange;'>⚠️ <strong>$col</strong> - NO encontrada (se omitirá)</li>\n";
        }
    }
    echo "</ul>\n";
    
    // Contar registros
    $countResult = $pdo->query("SELECT COUNT(*) as total FROM income_payments");
    $count = $countResult->fetch()['total'];
    echo "<h3>Total de registros: $count</h3>\n";
    
    // Mostrar algunos registros de ejemplo
    if ($count > 0) {
        echo "<h3>Primeros 5 registros (ejemplo):</h3>\n";
        $exampleResult = $pdo->query("SELECT * FROM income_payments LIMIT 5");
        $examples = $exampleResult->fetchAll();
        
        if (!empty($examples)) {
            echo "<table border='1' style='border-collapse: collapse; width: 100%; font-size: 12px;'>\n";
            echo "<tr style='background-color: #f0f0f0;'>";
            foreach (array_keys($examples[0]) as $header) {
                $isToRemove = in_array($header, $columnsToRemove);
                $headerStyle = $isToRemove ? "style='background-color: #ffcccc;'" : "";
                echo "<th $headerStyle>" . $header . ($isToRemove ? " ❌" : "") . "</th>";
            }
            echo "</tr>\n";
            
            foreach ($examples as $row) {
                echo "<tr>";
                foreach ($row as $key => $value) {
                    $isToRemove = in_array($key, $columnsToRemove);
                    $cellStyle = $isToRemove ? "style='background-color: #ffcccc;'" : "";
                    echo "<td $cellStyle>" . htmlspecialchars($value ?? 'NULL') . "</td>";
                }
                echo "</tr>\n";
            }
            echo "</table>\n";
        }
    }
    
    echo "<hr>\n";
    echo "<h2>🎯 Plan de eliminación para 'income_payments'</h2>\n";
    
    if (!empty($foundColumnsToRemove)) {
        echo "<h3>Columnas que se eliminarán:</h3>\n";
        echo "<ul style='color: red;'>\n";
        foreach ($foundColumnsToRemove as $col) {
            echo "<li>❌ <strong>$col</strong></li>\n";
        }
        echo "</ul>\n";
        
        echo "<p style='color: blue;'>💡 <strong>Nota:</strong> Se creará un backup automático antes de proceder.</p>\n";
    } else {
        echo "<p style='color: blue;'>ℹ️ No se encontraron las columnas especificadas para eliminar.</p>\n";
    }
    
    if (!empty($missingColumns)) {
        echo "<h3>Columnas solicitadas que NO existen:</h3>\n";
        echo "<ul style='color: orange;'>\n";
        foreach ($missingColumns as $col) {
            echo "<li>⚠️ <strong>$col</strong></li>\n";
        }
        echo "</ul>\n";
    }
    
    // Verificar restricciones de clave foránea
    echo "<h3>Verificando restricciones de clave foránea...</h3>\n";
    
    $foreignKeysQuery = "
        SELECT 
            CONSTRAINT_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM 
            INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE 
            TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'income_payments' 
            AND REFERENCED_TABLE_NAME IS NOT NULL
    ";
    
    $fkResult = $pdo->query($foreignKeysQuery);
    $foreignKeys = $fkResult->fetchAll();
    
    if (empty($foreignKeys)) {
        echo "<p style='color: blue;'>ℹ️ No se encontraron restricciones de clave foránea en las columnas a eliminar.</p>\n";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
        echo "<tr style='background-color: #f0f0f0;'>";
        echo "<th>Restricción</th><th>Columna</th><th>Tabla Referenciada</th><th>Columna Referenciada</th>";
        echo "</tr>\n";
        
        $hasConstraintsOnTargetColumns = false;
        
        foreach ($foreignKeys as $fk) {
            $isTargetColumn = in_array($fk['COLUMN_NAME'], $columnsToRemove);
            if ($isTargetColumn) $hasConstraintsOnTargetColumns = true;
            
            $rowStyle = $isTargetColumn ? "style='background-color: #ffcccc;'" : "";
            
            echo "<tr $rowStyle>";
            echo "<td>" . $fk['CONSTRAINT_NAME'] . ($isTargetColumn ? " ⚠️" : "") . "</td>";
            echo "<td>" . $fk['COLUMN_NAME'] . "</td>";
            echo "<td>" . $fk['REFERENCED_TABLE_NAME'] . "</td>";
            echo "<td>" . $fk['REFERENCED_COLUMN_NAME'] . "</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
        
        if ($hasConstraintsOnTargetColumns) {
            echo "<p style='color: red;'>⚠️ <strong>ADVERTENCIA:</strong> Algunas columnas tienen restricciones de clave foránea que deben eliminarse primero.</p>\n";
        }
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>\n";
}
?> 