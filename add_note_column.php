<?php
require_once 'config.php';

echo "<h2>Agregando columna 'note' faltante a la tabla 'incomes'</h2>\n";

try {
    $pdo = getConnection();
    
    // Verificar si la tabla incomes existe
    $incomesExists = $pdo->query("SHOW TABLES LIKE 'incomes'")->rowCount() > 0;
    
    if (!$incomesExists) {
        echo "<p style='color: red;'>❌ La tabla 'incomes' no existe en la base de datos.</p>\n";
        exit;
    }
    
    echo "<p style='color: green;'>✅ La tabla 'incomes' existe.</p>\n";
    
    // Verificar si la columna note ya existe
    $result = $pdo->query("DESCRIBE incomes");
    $currentColumns = $result->fetchAll();
    $existingColumnNames = array_column($currentColumns, 'Field');
    
    if (in_array('note', $existingColumnNames)) {
        echo "<p style='color: blue;'>ℹ️ La columna 'note' ya existe en la tabla.</p>\n";
        
        // Mostrar estructura actual
        echo "<h3>Estructura actual de la tabla:</h3>\n";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
        echo "<tr style='background-color: #f0f0f0;'>";
        echo "<th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th>";
        echo "</tr>\n";
        
        foreach ($currentColumns as $column) {
            $isNote = ($column['Field'] === 'note');
            $rowStyle = $isNote ? "style='background-color: #ccffcc;'" : "";
            
            echo "<tr $rowStyle>";
            echo "<td>" . $column['Field'] . ($isNote ? " 📝" : "") . "</td>";
            echo "<td>" . $column['Type'] . "</td>";
            echo "<td>" . $column['Null'] . "</td>";
            echo "<td>" . $column['Key'] . "</td>";
            echo "<td>" . ($column['Default'] ?? 'NULL') . "</td>";
            echo "<td>" . $column['Extra'] . "</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
        exit;
    }
    
    echo "<p style='color: orange;'>⚠️ La columna 'note' NO existe. Procederemos a agregarla.</p>\n";
    
    // CREAR BACKUP DE LA TABLA ANTES DE MODIFICAR
    echo "<h3>Creando backup de la tabla...</h3>\n";
    $backupTableName = 'incomes_backup_add_note_' . date('Y_m_d_H_i_s');
    
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
    
    // AGREGAR COLUMNA NOTE
    echo "<h3>Agregando columna 'note'...</h3>\n";
    echo "<p>🔄 Agregando columna: <strong>note</strong> (TEXT)...</p>\n";
    
    try {
        $sql = "ALTER TABLE incomes ADD COLUMN `note` TEXT NULL";
        $pdo->exec($sql);
        
        echo "<p style='color: green;'>✅ Columna 'note' agregada exitosamente.</p>\n";
        
    } catch (PDOException $e) {
        echo "<p style='color: red;'>❌ Error agregando columna 'note': " . $e->getMessage() . "</p>\n";
        exit;
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
    echo "<h3>Estructura final completa de la tabla:</h3>\n";
    $finalResult = $pdo->query("DESCRIBE incomes");
    $finalColumns = $finalResult->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th>";
    echo "</tr>\n";
    
    foreach ($finalColumns as $column) {
        $isNote = ($column['Field'] === 'note');
        $rowStyle = $isNote ? "style='background-color: #ccffcc;'" : "";
        
        echo "<tr $rowStyle>";
        echo "<td>" . $column['Field'] . ($isNote ? " 🆕📝" : "") . "</td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "<td>" . ($column['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . $column['Extra'] . "</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    echo "<hr>\n";
    echo "<h3>🎉 COLUMNA 'NOTE' AGREGADA EXITOSAMENTE</h3>\n";
    echo "<p style='color: green;'>✅ La tabla 'incomes' ahora incluye la columna 'note' para observaciones y comentarios.</p>\n";
    echo "<p><strong>Tipo:</strong> TEXT - Permite textos largos</p>\n";
    echo "<p><strong>Nullable:</strong> Sí - Puede estar vacía</p>\n";
    echo "<p style='color: blue;'>💾 <strong>Backup disponible en:</strong> $backupTableName</p>\n";
    
    echo "<hr>\n";
    echo "<h2>📋 RESUMEN COMPLETO DE COLUMNAS EN 'INCOMES':</h2>\n";
    echo "<ul>\n";
    echo "<li>✅ <strong>id</strong> - Identificador único</li>\n";
    echo "<li>✅ <strong>team_id</strong> - Relación con equipo</li>\n";
    echo "<li>✅ <strong>total_amount</strong> - Monto total</li>\n";
    echo "<li>✅ <strong>invoice_number</strong> - Número de factura</li>\n";
    echo "<li>✅ <strong>income_date</strong> - Fecha del ingreso</li>\n";
    echo "<li>✅ <strong>contractor_ids</strong> - IDs de contratistas (JSON)</li>\n";
    echo "<li>✅ <strong>note</strong> - Notas y observaciones 🆕</li>\n";
    echo "<li>✅ <strong>created_at</strong> - Fecha de creación</li>\n";
    echo "<li>✅ <strong>updated_at</strong> - Fecha de actualización</li>\n";
    echo "</ul>\n";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error general: " . $e->getMessage() . "</p>\n";
}
?> 