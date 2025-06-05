<?php
require_once 'config.php';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estructura de Tablas de Ingresos</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .table-section {
            background: white;
            margin-bottom: 30px;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .table-title {
            color: #2563eb;
            font-size: 24px;
            margin-bottom: 15px;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid #d1d5db;
            padding: 12px;
            text-align: left;
        }
        th {
            background-color: #f3f4f6;
            font-weight: bold;
            color: #374151;
        }
        tr:nth-child(even) {
            background-color: #f9fafb;
        }
        .error {
            color: #dc2626;
            background-color: #fef2f2;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #fecaca;
        }
        .info {
            color: #059669;
            background-color: #ecfdf5;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #a7f3d0;
            margin-bottom: 20px;
        }
        .count-info {
            background-color: #eff6ff;
            color: #1d4ed8;
            padding: 10px;
            border-radius: 6px;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 style="text-align: center; color: #1f2937;">📊 Estructura de Tablas de Ingresos</h1>
        
        <div class="info">
            <strong>📋 Propósito:</strong> Examinar la estructura y datos de las tablas relacionadas con ingresos para planificar la implementación del módulo.
        </div>

        <?php
        try {
            $pdo = getConnection();
            
            // Array de tablas a examinar
            $tables = ['incomes', 'income_payments', 'income_lines'];
            
            foreach ($tables as $tableName) {
                echo "<div class='table-section'>";
                echo "<h2 class='table-title'>🗃️ Tabla: {$tableName}</h2>";
                
                // Verificar si la tabla existe
                $checkTable = $pdo->prepare("SHOW TABLES LIKE ?");
                $checkTable->execute([$tableName]);
                
                if ($checkTable->rowCount() === 0) {
                    echo "<div class='error'>❌ La tabla '{$tableName}' no existe en la base de datos.</div>";
                    echo "</div>";
                    continue;
                }
                
                // Obtener estructura de la tabla
                echo "<h3>📋 Estructura de Columnas:</h3>";
                $describe = $pdo->query("DESCRIBE {$tableName}");
                $columns = $describe->fetchAll(PDO::FETCH_ASSOC);
                
                echo "<table>";
                echo "<thead>";
                echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
                echo "</thead>";
                echo "<tbody>";
                
                foreach ($columns as $column) {
                    echo "<tr>";
                    echo "<td><strong>{$column['Field']}</strong></td>";
                    echo "<td>{$column['Type']}</td>";
                    echo "<td>{$column['Null']}</td>";
                    echo "<td>{$column['Key']}</td>";
                    echo "<td>" . ($column['Default'] ?? 'NULL') . "</td>";
                    echo "<td>{$column['Extra']}</td>";
                    echo "</tr>";
                }
                
                echo "</tbody>";
                echo "</table>";
                
                // Obtener conteo de registros
                $countStmt = $pdo->query("SELECT COUNT(*) as total FROM {$tableName}");
                $count = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
                
                echo "<div class='count-info'>";
                echo "<strong>📊 Total de registros:</strong> {$count}";
                echo "</div>";
                
                // Si hay registros, mostrar algunos ejemplos
                if ($count > 0) {
                    echo "<h3>📄 Ejemplos de Datos (primeros 5 registros):</h3>";
                    $sampleData = $pdo->query("SELECT * FROM {$tableName} LIMIT 5");
                    $samples = $sampleData->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (!empty($samples)) {
                        echo "<table>";
                        echo "<thead><tr>";
                        foreach (array_keys($samples[0]) as $header) {
                            echo "<th>{$header}</th>";
                        }
                        echo "</tr></thead>";
                        echo "<tbody>";
                        
                        foreach ($samples as $row) {
                            echo "<tr>";
                            foreach ($row as $value) {
                                $displayValue = $value === null ? '<em>NULL</em>' : htmlspecialchars($value);
                                if (strlen($displayValue) > 50) {
                                    $displayValue = substr($displayValue, 0, 50) . '...';
                                }
                                echo "<td>{$displayValue}</td>";
                            }
                            echo "</tr>";
                        }
                        
                        echo "</tbody>";
                        echo "</table>";
                    }
                }
                
                // Mostrar índices de la tabla
                echo "<h3>🔑 Índices de la Tabla:</h3>";
                $indexStmt = $pdo->query("SHOW INDEX FROM {$tableName}");
                $indexes = $indexStmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($indexes)) {
                    echo "<table>";
                    echo "<thead>";
                    echo "<tr><th>Nombre del Índice</th><th>Columna</th><th>Único</th><th>Tipo</th></tr>";
                    echo "</thead>";
                    echo "<tbody>";
                    
                    foreach ($indexes as $index) {
                        echo "<tr>";
                        echo "<td>{$index['Key_name']}</td>";
                        echo "<td>{$index['Column_name']}</td>";
                        echo "<td>" . ($index['Non_unique'] == 0 ? 'Sí' : 'No') . "</td>";
                        echo "<td>{$index['Index_type']}</td>";
                        echo "</tr>";
                    }
                    
                    echo "</tbody>";
                    echo "</table>";
                } else {
                    echo "<div class='info'>Sin índices definidos.</div>";
                }
                
                echo "</div>";
            }
            
            // Información adicional sobre relaciones
            echo "<div class='table-section'>";
            echo "<h2 class='table-title'>🔗 Información Adicional</h2>";
            
            echo "<h3>📋 Análisis de Relaciones:</h3>";
            echo "<ul>";
            echo "<li><strong>incomes:</strong> Tabla principal de ingresos</li>";
            echo "<li><strong>income_payments:</strong> Pagos relacionados con ingresos</li>";
            echo "<li><strong>income_lines:</strong> Líneas de detalle de ingresos</li>";
            echo "</ul>";
            
            // Verificar foreign keys si existen
            echo "<h3>🔑 Foreign Keys (si existen):</h3>";
            $fkQuery = "
                SELECT 
                    TABLE_NAME,
                    COLUMN_NAME,
                    CONSTRAINT_NAME,
                    REFERENCED_TABLE_NAME,
                    REFERENCED_COLUMN_NAME
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME IN ('incomes', 'income_payments', 'income_lines')
            ";
            
            $fkStmt = $pdo->query($fkQuery);
            $foreignKeys = $fkStmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($foreignKeys)) {
                echo "<table>";
                echo "<thead>";
                echo "<tr><th>Tabla</th><th>Columna</th><th>Referencia Tabla</th><th>Referencia Columna</th></tr>";
                echo "</thead>";
                echo "<tbody>";
                
                foreach ($foreignKeys as $fk) {
                    echo "<tr>";
                    echo "<td>{$fk['TABLE_NAME']}</td>";
                    echo "<td>{$fk['COLUMN_NAME']}</td>";
                    echo "<td>{$fk['REFERENCED_TABLE_NAME']}</td>";
                    echo "<td>{$fk['REFERENCED_COLUMN_NAME']}</td>";
                    echo "</tr>";
                }
                
                echo "</tbody>";
                echo "</table>";
            } else {
                echo "<div class='info'>No se encontraron foreign keys definidas para estas tablas.</div>";
            }
            
            echo "</div>";
            
        } catch (Exception $e) {
            echo "<div class='error'>";
            echo "<strong>❌ Error de conexión:</strong> " . htmlspecialchars($e->getMessage());
            echo "</div>";
        }
        ?>
        
        <div style="text-align: center; margin-top: 30px; padding: 20px; background-color: #f3f4f6; border-radius: 8px;">
            <p><strong>🚀 Próximo paso:</strong> Usar esta información para diseñar el módulo de ingresos</p>
            <p style="color: #6b7280; font-size: 14px;">Archivo: test_income_tables_structure.php</p>
        </div>
    </div>
</body>
</html> 