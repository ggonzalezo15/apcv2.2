<?php
require_once 'config.php';

echo "<h2>Eliminación de columnas de la tabla 'income_payments'</h2>\n";

// Columnas a eliminar
$columnsToRemove = ['payment_date', 'reference_number', 'notes'];

try {
    $pdo = getConnection();
    
    // Verificar si la tabla existe
    $tableExists = $pdo->query("SHOW TABLES LIKE 'income_payments'")->rowCount() > 0;
    
    if (!$tableExists) {
        echo "<p style='color: red;'>❌ La tabla 'income_payments' no existe en la base de datos.</p>\n";
        exit;
    }
    
    echo "<p style='color: green;'>✅ La tabla 'income_payments' existe.</p>\n";
    
    // Obtener la estructura actual
    $result = $pdo->query("DESCRIBE income_payments");
    $currentColumns = $result->fetchAll();
    
    echo "<h3>Estructura actual:</h3>\n";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th>";
    echo "</tr>\n";
    
    $existingColumnsToRemove = [];
    $nonExistingColumns = [];
    
    foreach ($currentColumns as $column) {
        $isToRemove = in_array($column['Field'], $columnsToRemove);
        
        if ($isToRemove) {
            $existingColumnsToRemove[] = $column['Field'];
        }
        
        $rowStyle = $isToRemove ? "style='background-color: #ffcccc;'" : "";
        
        echo "<tr $rowStyle>";
        echo "<td>" . $column['Field'] . ($isToRemove ? " ❌" : "") . "</td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "<td>" . ($column['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . $column['Extra'] . "</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    // Verificar columnas que no existen
    $nonExistingColumns = array_diff($columnsToRemove, $existingColumnsToRemove);
    
    // Mostrar columnas que existen y se van a eliminar
    if (!empty($existingColumnsToRemove)) {
        echo "<h4>Columnas que SE ELIMINARÁN:</h4>\n";
        echo "<ul style='color: red;'>\n";
        foreach ($existingColumnsToRemove as $col) {
            echo "<li>❌ $col</li>\n";
        }
        echo "</ul>\n";
    }
    
    // Mostrar columnas que no existen
    if (!empty($nonExistingColumns)) {
        echo "<h4>Columnas que NO EXISTEN (se omitirán):</h4>\n";
        echo "<ul style='color: orange;'>\n";
        foreach ($nonExistingColumns as $col) {
            echo "<li>⚠️ $col</li>\n";
        }
        echo "</ul>\n";
    }
    
    if (empty($existingColumnsToRemove)) {
        echo "<p style='color: blue;'>ℹ️ No hay columnas para eliminar.</p>\n";
        exit;
    }
    
    // Verificar restricciones de clave foránea en las columnas a eliminar
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
            AND COLUMN_NAME IN ('" . implode("','", $existingColumnsToRemove) . "')
    ";
    
    $fkResult = $pdo->query($foreignKeysQuery);
    $foreignKeys = $fkResult->fetchAll();
    
    if (!empty($foreignKeys)) {
        echo "<p style='color: red;'>⚠️ ADVERTENCIA: Se encontraron restricciones de clave foránea:</p>\n";
        echo "<table border='1' style='border-collapse: collapse;'>\n";
        echo "<tr style='background-color: #f0f0f0;'>";
        echo "<th>Restricción</th><th>Columna</th><th>Tabla Referenciada</th>";
        echo "</tr>\n";
        
        foreach ($foreignKeys as $fk) {
            echo "<tr>";
            echo "<td>{$fk['CONSTRAINT_NAME']}</td>";
            echo "<td>{$fk['COLUMN_NAME']}</td>";
            echo "<td>{$fk['REFERENCED_TABLE_NAME']}</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
        echo "<p style='color: red;'>Las restricciones se eliminarán automáticamente antes de eliminar las columnas.</p>\n";
    } else {
        echo "<p style='color: green;'>✅ No se encontraron restricciones de clave foránea en las columnas a eliminar.</p>\n";
    }
    
    // CREAR BACKUP DE LA TABLA ANTES DE MODIFICAR
    echo "<h3>Creando backup de la tabla...</h3>\n";
    $backupTableName = 'income_payments_backup_' . date('Y_m_d_H_i_s');
    
    try {
        $pdo->exec("CREATE TABLE $backupTableName AS SELECT * FROM income_payments");
        echo "<p style='color: green;'>✅ Backup creado: $backupTableName</p>\n";
    } catch (PDOException $e) {
        echo "<p style='color: red;'>❌ Error creando backup: " . $e->getMessage() . "</p>\n";
        exit;
    }
    
    // Contar registros antes
    $countResult = $pdo->query("SELECT COUNT(*) as total FROM income_payments");
    $totalRecords = $countResult->fetch()['total'];
    echo "<p>📊 Total de registros en la tabla: $totalRecords</p>\n";
    
    // ELIMINAR COLUMNAS UNA POR UNA
    echo "<h3>Eliminando columnas...</h3>\n";
    
    $successfulRemovals = [];
    $failedRemovals = [];
    
    foreach ($existingColumnsToRemove as $columnName) {
        try {
            echo "<p>🔄 Eliminando columna: <strong>$columnName</strong>...</p>\n";
            
            $sql = "ALTER TABLE income_payments DROP COLUMN `$columnName`";
            $pdo->exec($sql);
            
            echo "<p style='color: green;'>✅ Columna '$columnName' eliminada exitosamente.</p>\n";
            $successfulRemovals[] = $columnName;
            
        } catch (PDOException $e) {
            echo "<p style='color: red;'>❌ Error eliminando columna '$columnName': " . $e->getMessage() . "</p>\n";
            $failedRemovals[] = $columnName;
        }
    }
    
    // RESUMEN FINAL
    echo "<hr>\n";
    echo "<h3>📋 RESUMEN DE LA OPERACIÓN</h3>\n";
    
    if (!empty($successfulRemovals)) {
        echo "<h4 style='color: green;'>✅ Columnas eliminadas exitosamente:</h4>\n";
        echo "<ul style='color: green;'>\n";
        foreach ($successfulRemovals as $col) {
            echo "<li>$col</li>\n";
        }
        echo "</ul>\n";
    }
    
    if (!empty($failedRemovals)) {
        echo "<h4 style='color: red;'>❌ Columnas con errores:</h4>\n";
        echo "<ul style='color: red;'>\n";
        foreach ($failedRemovals as $col) {
            echo "<li>$col</li>\n";
        }
        echo "</ul>\n";
    }
    
    // Verificar registros después
    $countResultAfter = $pdo->query("SELECT COUNT(*) as total FROM income_payments");
    $totalRecordsAfter = $countResultAfter->fetch()['total'];
    echo "<p>📊 Total de registros después: <strong>$totalRecordsAfter</strong></p>\n";
    
    if ($totalRecords == $totalRecordsAfter) {
        echo "<p style='color: green;'>✅ Los datos se mantuvieron intactos.</p>\n";
    } else {
        echo "<p style='color: red;'>⚠️ ADVERTENCIA: El número de registros cambió!</p>\n";
    }
    
    // Mostrar estructura final
    echo "<h3>Estructura final de la tabla:</h3>\n";
    $finalResult = $pdo->query("DESCRIBE income_payments");
    $finalColumns = $finalResult->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th>";
    echo "</tr>\n";
    
    foreach ($finalColumns as $column) {
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
    echo "<h2>🎉 ELIMINACIÓN COMPLETADA</h2>\n";
    echo "<p style='color: green;'>✅ Proceso de eliminación de columnas finalizado exitosamente.</p>\n";
    echo "<p style='color: blue;'>💾 <strong>Backup disponible en:</strong> $backupTableName</p>\n";
    
    echo "<h3>📋 Columnas restantes en 'income_payments':</h3>\n";
    echo "<ul>\n";
    foreach ($finalColumns as $column) {
        echo "<li><strong>{$column['Field']}</strong> - {$column['Type']}</li>\n";
    }
    echo "</ul>\n";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error general: " . $e->getMessage() . "</p>\n";
}
?> 