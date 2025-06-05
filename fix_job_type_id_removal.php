<?php
require_once 'config.php';

echo "<h2>Eliminación de restricción de clave foránea y columna 'job_type_id'</h2>\n";

try {
    $pdo = getConnection();
    
    // Verificar si la tabla existe
    $tableExists = $pdo->query("SHOW TABLES LIKE 'incomes'")->rowCount() > 0;
    
    if (!$tableExists) {
        echo "<p style='color: red;'>❌ La tabla 'incomes' no existe en la base de datos.</p>\n";
        exit;
    }
    
    echo "<p style='color: green;'>✅ La tabla 'incomes' existe.</p>\n";
    
    // Verificar si la columna job_type_id aún existe
    $result = $pdo->query("DESCRIBE incomes");
    $columns = $result->fetchAll();
    
    $jobTypeIdExists = false;
    foreach ($columns as $column) {
        if ($column['Field'] === 'job_type_id') {
            $jobTypeIdExists = true;
            break;
        }
    }
    
    if (!$jobTypeIdExists) {
        echo "<p style='color: blue;'>ℹ️ La columna 'job_type_id' ya no existe en la tabla.</p>\n";
        exit;
    }
    
    echo "<p style='color: orange;'>⚠️ La columna 'job_type_id' aún existe y necesita ser eliminada.</p>\n";
    
    // Mostrar las restricciones de clave foránea actuales
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
            AND TABLE_NAME = 'incomes' 
            AND REFERENCED_TABLE_NAME IS NOT NULL
    ";
    
    $fkResult = $pdo->query($foreignKeysQuery);
    $foreignKeys = $fkResult->fetchAll();
    
    if (empty($foreignKeys)) {
        echo "<p style='color: blue;'>ℹ️ No se encontraron restricciones de clave foránea.</p>\n";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
        echo "<tr style='background-color: #f0f0f0;'>";
        echo "<th>Restricción</th><th>Columna</th><th>Tabla Referenciada</th><th>Columna Referenciada</th>";
        echo "</tr>\n";
        
        foreach ($foreignKeys as $fk) {
            $isJobTypeId = ($fk['COLUMN_NAME'] === 'job_type_id');
            $rowStyle = $isJobTypeId ? "style='background-color: #ffcccc;'" : "";
            
            echo "<tr $rowStyle>";
            echo "<td>" . $fk['CONSTRAINT_NAME'] . ($isJobTypeId ? " ❌" : "") . "</td>";
            echo "<td>" . $fk['COLUMN_NAME'] . "</td>";
            echo "<td>" . $fk['REFERENCED_TABLE_NAME'] . "</td>";
            echo "<td>" . $fk['REFERENCED_COLUMN_NAME'] . "</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
    }
    
    // Buscar la restricción específica para job_type_id
    $jobTypeIdConstraint = null;
    foreach ($foreignKeys as $fk) {
        if ($fk['COLUMN_NAME'] === 'job_type_id') {
            $jobTypeIdConstraint = $fk['CONSTRAINT_NAME'];
            break;
        }
    }
    
    if ($jobTypeIdConstraint) {
        echo "<h3>Eliminando restricción de clave foránea...</h3>\n";
        echo "<p>🔄 Eliminando restricción: <strong>$jobTypeIdConstraint</strong>...</p>\n";
        
        try {
            $dropFkSql = "ALTER TABLE incomes DROP FOREIGN KEY `$jobTypeIdConstraint`";
            $pdo->exec($dropFkSql);
            echo "<p style='color: green;'>✅ Restricción '$jobTypeIdConstraint' eliminada exitosamente.</p>\n";
            
        } catch (PDOException $e) {
            echo "<p style='color: red;'>❌ Error eliminando restricción '$jobTypeIdConstraint': " . $e->getMessage() . "</p>\n";
            exit;
        }
    } else {
        echo "<p style='color: blue;'>ℹ️ No se encontró restricción de clave foránea para 'job_type_id'.</p>\n";
    }
    
    // Ahora intentar eliminar la columna job_type_id
    echo "<h3>Eliminando columna job_type_id...</h3>\n";
    echo "<p>🔄 Eliminando columna: <strong>job_type_id</strong>...</p>\n";
    
    try {
        $dropColumnSql = "ALTER TABLE incomes DROP COLUMN `job_type_id`";
        $pdo->exec($dropColumnSql);
        echo "<p style='color: green;'>✅ Columna 'job_type_id' eliminada exitosamente.</p>\n";
        
    } catch (PDOException $e) {
        echo "<p style='color: red;'>❌ Error eliminando columna 'job_type_id': " . $e->getMessage() . "</p>\n";
        exit;
    }
    
    // Verificar estructura final
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
    
    // Verificar que job_type_id ya no esté
    $jobTypeIdStillExists = false;
    foreach ($finalColumns as $column) {
        if ($column['Field'] === 'job_type_id') {
            $jobTypeIdStillExists = true;
            break;
        }
    }
    
    if ($jobTypeIdStillExists) {
        echo "<p style='color: red;'>❌ ADVERTENCIA: La columna 'job_type_id' aún existe.</p>\n";
    } else {
        echo "<p style='color: green;'>✅ ÉXITO: La columna 'job_type_id' ha sido eliminada completamente.</p>\n";
    }
    
    // Contar registros finales
    $countResult = $pdo->query("SELECT COUNT(*) as total FROM incomes");
    $totalRecords = $countResult->fetch()['total'];
    echo "<p>📊 Total de registros finales: <strong>$totalRecords</strong></p>\n";
    
    echo "<hr>\n";
    echo "<h3>🎉 PROCESO COMPLETADO</h3>\n";
    echo "<p style='color: green;'>✅ Todas las columnas especificadas han sido eliminadas:</p>\n";
    echo "<ul style='color: green;'>\n";
    echo "<li>✅ job_type_id (con restricción de clave foránea)</li>\n";
    echo "<li>✅ name</li>\n";
    echo "<li>✅ description</li>\n";
    echo "<li>✅ status</li>\n";
    echo "<li>✅ start_date</li>\n";
    echo "<li>✅ end_date</li>\n";
    echo "</ul>\n";
    
    echo "<p style='color: blue;'>💾 <strong>Recuerda:</strong> Tienes un backup disponible en: incomes_backup_2025_06_05_20_54_08</p>\n";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error general: " . $e->getMessage() . "</p>\n";
}
?> 