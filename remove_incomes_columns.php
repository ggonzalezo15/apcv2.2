<?php
require_once 'config.php';

echo "<h2>Eliminación de columnas de la tabla 'incomes'</h2>\n";

// Columnas a eliminar
$columnsToRemove = ['job_type_id', 'name', 'description', 'status', 'start_date', 'end_date'];

try {
    $pdo = getConnection();
    
    // Verificar si la tabla existe
    $tableExists = $pdo->query("SHOW TABLES LIKE 'incomes'")->rowCount() > 0;
    
    if (!$tableExists) {
        echo "<p style='color: red;'>❌ La tabla 'incomes' no existe en la base de datos.</p>\n";
        exit;
    }
    
    echo "<p style='color: green;'>✅ La tabla 'incomes' existe.</p>\n";
    
    // Obtener la estructura actual
    $result = $pdo->query("DESCRIBE incomes");
    $currentColumns = $result->fetchAll();
    
    echo "<h3>Verificando columnas existentes...</h3>\n";
    
    $existingColumnsToRemove = [];
    $nonExistingColumns = [];
    
    foreach ($columnsToRemove as $columnName) {
        $exists = false;
        foreach ($currentColumns as $column) {
            if ($column['Field'] === $columnName) {
                $exists = true;
                $existingColumnsToRemove[] = $columnName;
                break;
            }
        }
        if (!$exists) {
            $nonExistingColumns[] = $columnName;
        }
    }
    
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
    
    // CREAR BACKUP DE LA TABLA ANTES DE MODIFICAR
    echo "<h3>Creando backup de la tabla...</h3>\n";
    $backupTableName = 'incomes_backup_' . date('Y_m_d_H_i_s');
    
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
    
    // ELIMINAR COLUMNAS UNA POR UNA
    echo "<h3>Eliminando columnas...</h3>\n";
    
    $successfulRemovals = [];
    $failedRemovals = [];
    
    foreach ($existingColumnsToRemove as $columnName) {
        try {
            echo "<p>🔄 Eliminando columna: <strong>$columnName</strong>...</p>\n";
            
            $sql = "ALTER TABLE incomes DROP COLUMN `$columnName`";
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
    echo "<p style='color: blue;'>💾 <strong>Backup disponible en:</strong> $backupTableName</p>\n";
    echo "<p style='color: blue;'>💡 <strong>Nota:</strong> Si necesitas restaurar, ejecuta: CREATE TABLE incomes_restore AS SELECT * FROM $backupTableName</p>\n";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error general: " . $e->getMessage() . "</p>\n";
}
?> 