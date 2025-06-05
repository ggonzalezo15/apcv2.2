<?php
require_once 'config.php';

echo "<h2>Modificación de la tabla 'income_lines'</h2>\n";

try {
    $pdo = getConnection();
    
    // Verificar si la tabla income_lines existe
    $tableExists = $pdo->query("SHOW TABLES LIKE 'income_lines'")->rowCount() > 0;
    
    if (!$tableExists) {
        echo "<p style='color: red;'>❌ La tabla 'income_lines' no existe en la base de datos.</p>\n";
        exit;
    }
    
    echo "<p style='color: green;'>✅ La tabla 'income_lines' existe.</p>\n";
    
    // Obtener estructura actual
    $result = $pdo->query("DESCRIBE income_lines");
    $currentColumns = $result->fetchAll();
    $existingColumnNames = array_column($currentColumns, 'Field');
    
    echo "<h3>Estructura actual:</h3>\n";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th>";
    echo "</tr>\n";
    
    foreach ($currentColumns as $column) {
        $isDescription = ($column['Field'] === 'description');
        $rowStyle = $isDescription ? "style='background-color: #ffcccc;'" : "";
        
        echo "<tr $rowStyle>";
        echo "<td>" . $column['Field'] . ($isDescription ? " ❌ (eliminar)" : "") . "</td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "<td>" . ($column['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . $column['Extra'] . "</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    // Verificar columnas específicas
    $hasDescription = in_array('description', $existingColumnNames);
    $hasJobTypesId = in_array('job_types_id', $existingColumnNames);
    
    echo "<h3>Estado de las columnas:</h3>\n";
    echo "<ul>\n";
    echo "<li>" . ($hasDescription ? "✅ Columna 'description' encontrada (se eliminará)" : "⚠️ Columna 'description' NO encontrada") . "</li>\n";
    echo "<li>" . ($hasJobTypesId ? "⚠️ Columna 'job_types_id' YA EXISTE (se omitirá)" : "✅ Columna 'job_types_id' se agregará") . "</li>\n";
    echo "</ul>\n";
    
    // Si no hay nada que hacer
    if (!$hasDescription && $hasJobTypesId) {
        echo "<p style='color: blue;'>ℹ️ No hay modificaciones que realizar. La tabla ya está en el estado deseado.</p>\n";
        exit;
    }
    
    // Verificar la tabla job_types
    $jobTypesExists = $pdo->query("SHOW TABLES LIKE 'job_types'")->rowCount() > 0;
    if (!$jobTypesExists && !$hasJobTypesId) {
        echo "<p style='color: red;'>❌ ADVERTENCIA: La tabla 'job_types' no existe. No se puede crear la referencia.</p>\n";
        echo "<p>¿Deseas continuar sin la restricción de clave foránea? (Solo agregar la columna sin referencia)</p>\n";
    }
    
    // CREAR BACKUP DE LA TABLA ANTES DE MODIFICAR
    echo "<h3>Creando backup de la tabla...</h3>\n";
    $backupTableName = 'income_lines_backup_modify_' . date('Y_m_d_H_i_s');
    
    try {
        $pdo->exec("CREATE TABLE $backupTableName AS SELECT * FROM income_lines");
        echo "<p style='color: green;'>✅ Backup creado: $backupTableName</p>\n";
    } catch (PDOException $e) {
        echo "<p style='color: red;'>❌ Error creando backup: " . $e->getMessage() . "</p>\n";
        exit;
    }
    
    // Contar registros antes
    $countResult = $pdo->query("SELECT COUNT(*) as total FROM income_lines");
    $totalRecords = $countResult->fetch()['total'];
    echo "<p>📊 Total de registros en la tabla: $totalRecords</p>\n";
    
    $operationsPerformed = [];
    $operationsFailed = [];
    
    // ELIMINAR COLUMNA DESCRIPTION
    if ($hasDescription) {
        echo "<h3>Eliminando columna 'description'...</h3>\n";
        echo "<p>🔄 Eliminando columna: <strong>description</strong>...</p>\n";
        
        try {
            $sql = "ALTER TABLE income_lines DROP COLUMN `description`";
            $pdo->exec($sql);
            
            echo "<p style='color: green;'>✅ Columna 'description' eliminada exitosamente.</p>\n";
            $operationsPerformed[] = "Eliminada columna 'description'";
            
        } catch (PDOException $e) {
            echo "<p style='color: red;'>❌ Error eliminando columna 'description': " . $e->getMessage() . "</p>\n";
            $operationsFailed[] = "Eliminar columna 'description'";
        }
    }
    
    // AGREGAR COLUMNA JOB_TYPES_ID
    if (!$hasJobTypesId) {
        echo "<h3>Agregando columna 'job_types_id'...</h3>\n";
        echo "<p>🔄 Agregando columna: <strong>job_types_id</strong>...</p>\n";
        
        try {
            // Determinar el tipo de columna según la tabla job_types si existe
            $columnType = "CHAR(36)"; // Tipo por defecto para UUIDs
            
            if ($jobTypesExists) {
                // Verificar el tipo de la columna id en job_types
                $jobTypesStructure = $pdo->query("DESCRIBE job_types");
                $jobTypesColumns = $jobTypesStructure->fetchAll();
                
                foreach ($jobTypesColumns as $col) {
                    if ($col['Field'] === 'id') {
                        $columnType = $col['Type'];
                        break;
                    }
                }
            }
            
            $sql = "ALTER TABLE income_lines ADD COLUMN `job_types_id` $columnType NULL";
            $pdo->exec($sql);
            
            echo "<p style='color: green;'>✅ Columna 'job_types_id' ($columnType) agregada exitosamente.</p>\n";
            $operationsPerformed[] = "Agregada columna 'job_types_id'";
            
            // Agregar índice para mejorar performance
            try {
                $pdo->exec("ALTER TABLE income_lines ADD INDEX idx_job_types_id (job_types_id)");
                echo "<p style='color: green;'>✅ Índice agregado para 'job_types_id'.</p>\n";
            } catch (PDOException $e) {
                echo "<p style='color: orange;'>⚠️ No se pudo agregar índice: " . $e->getMessage() . "</p>\n";
            }
            
            // Agregar restricción de clave foránea si la tabla job_types existe
            if ($jobTypesExists) {
                try {
                    $pdo->exec("ALTER TABLE income_lines ADD CONSTRAINT fk_income_lines_job_types FOREIGN KEY (job_types_id) REFERENCES job_types(id) ON DELETE SET NULL ON UPDATE CASCADE");
                    echo "<p style='color: green;'>✅ Restricción de clave foránea agregada.</p>\n";
                } catch (PDOException $e) {
                    echo "<p style='color: orange;'>⚠️ No se pudo agregar clave foránea: " . $e->getMessage() . "</p>\n";
                }
            }
            
        } catch (PDOException $e) {
            echo "<p style='color: red;'>❌ Error agregando columna 'job_types_id': " . $e->getMessage() . "</p>\n";
            $operationsFailed[] = "Agregar columna 'job_types_id'";
        }
    }
    
    // RESUMEN FINAL
    echo "<hr>\n";
    echo "<h3>📋 RESUMEN DE LA OPERACIÓN</h3>\n";
    
    if (!empty($operationsPerformed)) {
        echo "<h4 style='color: green;'>✅ Operaciones exitosas:</h4>\n";
        echo "<ul style='color: green;'>\n";
        foreach ($operationsPerformed as $op) {
            echo "<li>$op</li>\n";
        }
        echo "</ul>\n";
    }
    
    if (!empty($operationsFailed)) {
        echo "<h4 style='color: red;'>❌ Operaciones con errores:</h4>\n";
        echo "<ul style='color: red;'>\n";
        foreach ($operationsFailed as $op) {
            echo "<li>$op</li>\n";
        }
        echo "</ul>\n";
    }
    
    // Verificar registros después
    $countResultAfter = $pdo->query("SELECT COUNT(*) as total FROM income_lines");
    $totalRecordsAfter = $countResultAfter->fetch()['total'];
    echo "<p>📊 Total de registros después: <strong>$totalRecordsAfter</strong></p>\n";
    
    if ($totalRecords == $totalRecordsAfter) {
        echo "<p style='color: green;'>✅ Los datos se mantuvieron intactos.</p>\n";
    } else {
        echo "<p style='color: red;'>⚠️ ADVERTENCIA: El número de registros cambió!</p>\n";
    }
    
    // Mostrar estructura final
    echo "<h3>Estructura final de la tabla:</h3>\n";
    $finalResult = $pdo->query("DESCRIBE income_lines");
    $finalColumns = $finalResult->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th>";
    echo "</tr>\n";
    
    foreach ($finalColumns as $column) {
        $isJobTypesId = ($column['Field'] === 'job_types_id');
        $rowStyle = $isJobTypesId ? "style='background-color: #ccffcc;'" : "";
        
        echo "<tr $rowStyle>";
        echo "<td>" . $column['Field'] . ($isJobTypesId ? " 🆕" : "") . "</td>";
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
    
    // Información sobre job_types disponibles
    if (!$hasJobTypesId && $jobTypesExists) {
        echo "<hr>\n";
        echo "<h3>💡 Tipos de trabajo disponibles para usar:</h3>\n";
        
        try {
            $jobTypesResult = $pdo->query("SELECT id, name FROM job_types ORDER BY name");
            $jobTypes = $jobTypesResult->fetchAll();
            
            echo "<ul>\n";
            foreach ($jobTypes as $jobType) {
                echo "<li><code>{$jobType['id']}</code> - {$jobType['name']}</li>\n";
            }
            echo "</ul>\n";
            
            echo "<p><strong>Ejemplo de actualización:</strong></p>\n";
            echo "<pre>UPDATE income_lines SET job_types_id = '{$jobTypes[0]['id']}' WHERE id = 'your_income_line_id';</pre>\n";
            
        } catch (PDOException $e) {
            echo "<p style='color: orange;'>⚠️ No se pudieron obtener los tipos de trabajo.</p>\n";
        }
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error general: " . $e->getMessage() . "</p>\n";
}
?> 