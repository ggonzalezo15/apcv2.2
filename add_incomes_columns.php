<?php
require_once 'config.php';

echo "<h2>Agregando nuevas columnas a la tabla 'incomes'</h2>\n";

// Columnas a agregar
$columnsToAdd = [
    'invoice_number' => [
        'type' => 'VARCHAR(100)',
        'nullable' => true,
        'default' => 'NULL',
        'description' => 'Número de factura'
    ],
    'income_date' => [
        'type' => 'DATE',
        'nullable' => true,
        'default' => 'NULL',
        'description' => 'Fecha del ingreso'
    ],
    'contractor_ids' => [
        'type' => 'JSON',
        'nullable' => true,
        'default' => 'NULL',
        'description' => 'IDs de contratistas en formato JSON'
    ]
];

try {
    $pdo = getConnection();
    
    // Verificar si la tabla incomes existe
    $incomesExists = $pdo->query("SHOW TABLES LIKE 'incomes'")->rowCount() > 0;
    
    if (!$incomesExists) {
        echo "<p style='color: red;'>❌ La tabla 'incomes' no existe en la base de datos.</p>\n";
        exit;
    }
    
    echo "<p style='color: green;'>✅ La tabla 'incomes' existe.</p>\n";
    
    // Obtener estructura actual
    $result = $pdo->query("DESCRIBE incomes");
    $currentColumns = $result->fetchAll();
    
    echo "<h3>Estructura actual de la tabla 'incomes':</h3>\n";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th>";
    echo "</tr>\n";
    
    foreach ($currentColumns as $column) {
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
    
    // Verificar qué columnas ya existen
    $existingColumnNames = array_column($currentColumns, 'Field');
    $columnsToActuallyAdd = [];
    $columnsAlreadyExist = [];
    
    foreach ($columnsToAdd as $columnName => $columnDef) {
        if (in_array($columnName, $existingColumnNames)) {
            $columnsAlreadyExist[] = $columnName;
        } else {
            $columnsToActuallyAdd[$columnName] = $columnDef;
        }
    }
    
    // Mostrar estado
    if (!empty($columnsAlreadyExist)) {
        echo "<h3>Columnas que YA EXISTEN (se omitirán):</h3>\n";
        echo "<ul style='color: orange;'>\n";
        foreach ($columnsAlreadyExist as $col) {
            echo "<li>⚠️ $col</li>\n";
        }
        echo "</ul>\n";
    }
    
    if (empty($columnsToActuallyAdd)) {
        echo "<p style='color: blue;'>ℹ️ Todas las columnas ya existen. No hay nada que agregar.</p>\n";
        exit;
    }
    
    echo "<h3>Columnas que se AGREGARÁN:</h3>\n";
    echo "<ul style='color: green;'>\n";
    foreach ($columnsToActuallyAdd as $columnName => $columnDef) {
        echo "<li>✅ <strong>$columnName</strong> - {$columnDef['type']} - {$columnDef['description']}</li>\n";
    }
    echo "</ul>\n";
    
    // CREAR BACKUP DE LA TABLA ANTES DE MODIFICAR
    echo "<h3>Creando backup de la tabla...</h3>\n";
    $backupTableName = 'incomes_backup_add_columns_' . date('Y_m_d_H_i_s');
    
    try {
        $pdo->exec("CREATE TABLE $backupTableName AS SELECT * FROM incomes");
        echo "<p style='color: green;'>✅ Backup creado: $backupTableName</p>\n";
    } catch (PDOException $e) {
        echo "<p style='color: red;'>❌ Error creando backup: " . $e->getMessage() . "</p>\n";
        exit;
    }
    
    // Contar registros antes
    $countResult = $pdo->query("SELECT COUNT(*) as total FROM incomes");
    $totalRecords = $countResult->fetch()['total'];
    echo "<p>📊 Total de registros en la tabla: $totalRecords</p>\n";
    
    // AGREGAR COLUMNAS UNA POR UNA
    echo "<h3>Agregando columnas...</h3>\n";
    
    $successfulAdditions = [];
    $failedAdditions = [];
    
    foreach ($columnsToActuallyAdd as $columnName => $columnDef) {
        try {
            echo "<p>🔄 Agregando columna: <strong>$columnName</strong>...</p>\n";
            
            // Construir SQL para agregar columna
            $nullClause = $columnDef['nullable'] ? 'NULL' : 'NOT NULL';
            $defaultClause = $columnDef['default'] !== 'NULL' ? "DEFAULT '{$columnDef['default']}'" : '';
            
            $sql = "ALTER TABLE incomes ADD COLUMN `$columnName` {$columnDef['type']} $nullClause $defaultClause";
            $pdo->exec($sql);
            
            echo "<p style='color: green;'>✅ Columna '$columnName' agregada exitosamente.</p>\n";
            $successfulAdditions[] = $columnName;
            
        } catch (PDOException $e) {
            echo "<p style='color: red;'>❌ Error agregando columna '$columnName': " . $e->getMessage() . "</p>\n";
            $failedAdditions[] = $columnName;
        }
    }
    
    // RESUMEN FINAL
    echo "<hr>\n";
    echo "<h3>📋 RESUMEN DE LA OPERACIÓN</h3>\n";
    
    if (!empty($successfulAdditions)) {
        echo "<h4 style='color: green;'>✅ Columnas agregadas exitosamente:</h4>\n";
        echo "<ul style='color: green;'>\n";
        foreach ($successfulAdditions as $col) {
            echo "<li>$col</li>\n";
        }
        echo "</ul>\n";
    }
    
    if (!empty($failedAdditions)) {
        echo "<h4 style='color: red;'>❌ Columnas con errores:</h4>\n";
        echo "<ul style='color: red;'>\n";
        foreach ($failedAdditions as $col) {
            echo "<li>$col</li>\n";
        }
        echo "</ul>\n";
    }
    
    // Verificar registros después
    $countResultAfter = $pdo->query("SELECT COUNT(*) as total FROM incomes");
    $totalRecordsAfter = $countResultAfter->fetch()['total'];
    echo "<p>📊 Total de registros después: <strong>$totalRecordsAfter</strong></p>\n";
    
    if ($totalRecords == $totalRecordsAfter) {
        echo "<p style='color: green;'>✅ Los datos se mantuvieron intactos.</p>\n";
    } else {
        echo "<p style='color: red;'>⚠️ ADVERTENCIA: El número de registros cambió!</p>\n";
    }
    
    // Mostrar estructura final
    echo "<h3>Estructura final de la tabla:</h3>\n";
    $finalResult = $pdo->query("DESCRIBE incomes");
    $finalColumns = $finalResult->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th>";
    echo "</tr>\n";
    
    foreach ($finalColumns as $column) {
        $isNew = in_array($column['Field'], $successfulAdditions);
        $rowStyle = $isNew ? "style='background-color: #ccffcc;'" : "";
        
        echo "<tr $rowStyle>";
        echo "<td>" . $column['Field'] . ($isNew ? " 🆕" : "") . "</td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "<td>" . ($column['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . $column['Extra'] . "</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    echo "<hr>\n";
    echo "<p style='color: blue;'>💾 <strong>Backup disponible en:</strong> $backupTableName</p>\n";
    
    // Información sobre uso de contractor_ids
    if (in_array('contractor_ids', $successfulAdditions)) {
        echo "<hr>\n";
        echo "<h3>💡 Cómo usar la columna 'contractor_ids':</h3>\n";
        echo "<p><strong>Formato JSON:</strong> Para almacenar múltiples contratistas</p>\n";
        echo "<p><strong>Ejemplo:</strong></p>\n";
        echo "<pre>[\n";
        echo "  \"683bb9ac5f6da9.81006111\",\n";
        echo "  \"683bb9b2b466b4.11058009\",\n";
        echo "  \"683dfc1a73de84.24032948\"\n";
        echo "]</pre>\n";
        
        echo "<p><strong>Para insertar:</strong></p>\n";
        echo "<pre>UPDATE incomes SET contractor_ids = '[\"683bb9ac5f6da9.81006111\", \"683bb9b2b466b4.11058009\"]' WHERE id = 'your_income_id';</pre>\n";
        
        echo "<p style='color: blue;'>📋 <strong>Contratistas disponibles:</strong></p>\n";
        
        // Mostrar contratistas disponibles
        try {
            $contractorsResult = $pdo->query("SELECT id, name FROM contractors ORDER BY name");
            $contractors = $contractorsResult->fetchAll();
            
            echo "<ul>\n";
            foreach ($contractors as $contractor) {
                echo "<li><code>{$contractor['id']}</code> - {$contractor['name']}</li>\n";
            }
            echo "</ul>\n";
        } catch (PDOException $e) {
            echo "<p style='color: orange;'>⚠️ No se pudieron obtener los contratistas.</p>\n";
        }
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error general: " . $e->getMessage() . "</p>\n";
}
?> 