<?php
require_once 'config.php';

echo "<h2>Análisis de estructura de contratistas</h2>\n";

try {
    $pdo = getConnection();
    
    // Verificar si la tabla contractors existe
    $contractorsExists = $pdo->query("SHOW TABLES LIKE 'contractors'")->rowCount() > 0;
    
    if (!$contractorsExists) {
        echo "<p style='color: red;'>❌ La tabla 'contractors' no existe en la base de datos.</p>\n";
        
        // Buscar tablas relacionadas con contractors
        echo "<h3>Buscando tablas relacionadas con contratistas...</h3>\n";
        $tablesResult = $pdo->query("SHOW TABLES");
        $tables = $tablesResult->fetchAll(PDO::FETCH_COLUMN);
        
        $contractorTables = array_filter($tables, function($table) {
            return stripos($table, 'contractor') !== false;
        });
        
        if (!empty($contractorTables)) {
            echo "<p style='color: blue;'>📋 Tablas encontradas relacionadas con contratistas:</p>\n";
            echo "<ul>\n";
            foreach ($contractorTables as $table) {
                echo "<li>$table</li>\n";
            }
            echo "</ul>\n";
        } else {
            echo "<p style='color: orange;'>⚠️ No se encontraron tablas relacionadas con contratistas.</p>\n";
        }
        
    } else {
        echo "<p style='color: green;'>✅ La tabla 'contractors' existe.</p>\n";
        
        // Obtener estructura de contractors
        echo "<h3>Estructura de la tabla 'contractors':</h3>\n";
        $result = $pdo->query("DESCRIBE contractors");
        $columns = $result->fetchAll();
        
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
        echo "<tr style='background-color: #f0f0f0;'>";
        echo "<th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th>";
        echo "</tr>\n";
        
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>" . $column['Field'] . "</td>";
            echo "<td>" . $column['Type'] . "</td>";
            echo "<td>" . $column['Null'] . "</td>";
            echo "<td>" . $column['Key'] . "</td>";
            echo "<td>" . ($column['Default'] ?? 'NULL') . "</td>";
            echo "<td>" . $column['Extra'] . "</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
        
        // Contar registros
        $countResult = $pdo->query("SELECT COUNT(*) as total FROM contractors");
        $count = $countResult->fetch()['total'];
        echo "<h3>Total de contratistas: $count</h3>\n";
        
        // Mostrar algunos registros de ejemplo
        if ($count > 0) {
            echo "<h3>Primeros 5 contratistas (ejemplo):</h3>\n";
            $exampleResult = $pdo->query("SELECT * FROM contractors LIMIT 5");
            $examples = $exampleResult->fetchAll();
            
            if (!empty($examples)) {
                echo "<table border='1' style='border-collapse: collapse; width: 100%; font-size: 12px;'>\n";
                echo "<tr style='background-color: #f0f0f0;'>";
                foreach (array_keys($examples[0]) as $header) {
                    echo "<th>" . $header . "</th>";
                }
                echo "</tr>\n";
                
                foreach ($examples as $row) {
                    echo "<tr>";
                    foreach ($row as $value) {
                        echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
                    }
                    echo "</tr>\n";
                }
                echo "</table>\n";
            }
        }
    }
    
    // Verificar estructura actual de incomes
    echo "<hr>\n";
    echo "<h2>Estructura actual de la tabla 'incomes'</h2>\n";
    
    $incomesResult = $pdo->query("DESCRIBE incomes");
    $incomesColumns = $incomesResult->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th>";
    echo "</tr>\n";
    
    foreach ($incomesColumns as $column) {
        echo "<tr>";
        echo "<td>" . $column['Field'] . "</td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "<td>" . ($column['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . $column['Extra'] . "</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    echo "<hr>\n";
    echo "<h2>🎯 Plan para agregar nuevas columnas a 'incomes'</h2>\n";
    echo "<h3>Columnas a agregar:</h3>\n";
    echo "<ul>\n";
    echo "<li><strong>invoice_number</strong> - VARCHAR(50) - Número de factura</li>\n";
    echo "<li><strong>income_date</strong> - DATE - Fecha del ingreso</li>\n";
    echo "<li><strong>contractor_id</strong> - Depende de la estructura de contratistas</li>\n";
    echo "</ul>\n";
    
    // Opciones para contractor_id
    echo "<h3>Opciones para 'contractor_id' (múltiples contratistas):</h3>\n";
    echo "<ol>\n";
    echo "<li><strong>JSON:</strong> Almacenar un array de IDs en formato JSON</li>\n";
    echo "<li><strong>Tabla pivot:</strong> Crear tabla 'income_contractors' para relación many-to-many</li>\n";
    echo "<li><strong>String separado:</strong> Almacenar IDs separados por comas</li>\n";
    echo "</ol>\n";
    
    echo "<p style='color: blue;'>💡 <strong>Recomendación:</strong> ";
    if ($contractorsExists) {
        echo "Usar JSON para almacenar múltiples contractor_ids, ya que es más flexible y eficiente para este caso.</p>\n";
    } else {
        echo "Primero necesitamos verificar o crear la tabla de contratistas.</p>\n";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>\n";
}
?> 