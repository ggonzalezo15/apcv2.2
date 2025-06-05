<?php
require_once 'config.php';

echo "<h2>Estructura actual de la tabla 'income_lines'</h2>\n";

try {
    $pdo = getConnection();
    
    // Verificar si la tabla income_lines existe
    $tableExists = $pdo->query("SHOW TABLES LIKE 'income_lines'")->rowCount() > 0;
    
    if (!$tableExists) {
        echo "<p style='color: red;'>❌ La tabla 'income_lines' no existe en la base de datos.</p>\n";
        
        // Buscar tablas relacionadas
        echo "<h3>Buscando tablas relacionadas con income...</h3>\n";
        $tablesResult = $pdo->query("SHOW TABLES");
        $tables = $tablesResult->fetchAll(PDO::FETCH_COLUMN);
        
        $incomeTables = array_filter($tables, function($table) {
            return stripos($table, 'income') !== false;
        });
        
        if (!empty($incomeTables)) {
            echo "<p style='color: blue;'>📋 Tablas encontradas relacionadas con income:</p>\n";
            echo "<ul>\n";
            foreach ($incomeTables as $table) {
                echo "<li>$table</li>\n";
            }
            echo "</ul>\n";
        }
        exit;
    }
    
    echo "<p style='color: green;'>✅ La tabla 'income_lines' existe.</p>\n";
    
    // Obtener la estructura de la tabla
    echo "<h3>Columnas actuales:</h3>\n";
    $result = $pdo->query("DESCRIBE income_lines");
    $columns = $result->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th>";
    echo "</tr>\n";
    
    $hasDescription = false;
    $hasJobTypesId = false;
    
    foreach ($columns as $column) {
        if ($column['Field'] === 'description') $hasDescription = true;
        if ($column['Field'] === 'job_types_id') $hasJobTypesId = true;
        
        $isDescription = ($column['Field'] === 'description');
        $isJobTypesId = ($column['Field'] === 'job_types_id');
        
        $rowStyle = "";
        if ($isDescription) {
            $rowStyle = "style='background-color: #ffcccc;'"; // Rojo para eliminar
        } elseif ($isJobTypesId) {
            $rowStyle = "style='background-color: #ccffcc;'"; // Verde para ya existe
        }
        
        echo "<tr $rowStyle>";
        echo "<td>" . $column['Field'];
        if ($isDescription) echo " ❌ (eliminar)";
        if ($isJobTypesId) echo " ✅ (ya existe)";
        echo "</td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "<td>" . ($column['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . $column['Extra'] . "</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    // Contar registros
    $countResult = $pdo->query("SELECT COUNT(*) as total FROM income_lines");
    $count = $countResult->fetch()['total'];
    echo "<h3>Total de registros: $count</h3>\n";
    
    // Mostrar algunos registros de ejemplo
    if ($count > 0) {
        echo "<h3>Primeros 5 registros (ejemplo):</h3>\n";
        $exampleResult = $pdo->query("SELECT * FROM income_lines LIMIT 5");
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
    
    echo "<hr>\n";
    echo "<h2>🎯 Plan de modificaciones para 'income_lines'</h2>\n";
    
    echo "<h3>Acciones a realizar:</h3>\n";
    echo "<ul>\n";
    
    if ($hasDescription) {
        echo "<li style='color: red;'>❌ <strong>Eliminar columna:</strong> description</li>\n";
    } else {
        echo "<li style='color: orange;'>⚠️ <strong>Columna 'description' no encontrada</strong> (se omitirá eliminación)</li>\n";
    }
    
    if ($hasJobTypesId) {
        echo "<li style='color: blue;'>ℹ️ <strong>Columna 'job_types_id' ya existe</strong> (no se agregará)</li>\n";
    } else {
        echo "<li style='color: green;'>✅ <strong>Agregar columna:</strong> job_types_id</li>\n";
    }
    
    echo "</ul>\n";
    
    // Verificar si existe la tabla job_types para la referencia
    echo "<h3>Verificando tabla de referencia 'job_types':</h3>\n";
    $jobTypesExists = $pdo->query("SHOW TABLES LIKE 'job_types'")->rowCount() > 0;
    
    if ($jobTypesExists) {
        echo "<p style='color: green;'>✅ La tabla 'job_types' existe.</p>\n";
        
        // Mostrar algunos job_types disponibles
        $jobTypesResult = $pdo->query("SELECT id, name FROM job_types LIMIT 5");
        $jobTypes = $jobTypesResult->fetchAll();
        
        if (!empty($jobTypes)) {
            echo "<p><strong>Tipos de trabajo disponibles (ejemplo):</strong></p>\n";
            echo "<ul>\n";
            foreach ($jobTypes as $jobType) {
                echo "<li><code>{$jobType['id']}</code> - {$jobType['name']}</li>\n";
            }
            echo "</ul>\n";
        }
    } else {
        echo "<p style='color: red;'>❌ La tabla 'job_types' NO existe.</p>\n";
        echo "<p style='color: orange;'>⚠️ Recomendación: Crear la tabla 'job_types' antes de agregar la referencia.</p>\n";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>\n";
}
?> 