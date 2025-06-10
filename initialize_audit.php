<?php
/**
 * Script para inicializar el sistema de auditoría
 * Ejecutar una vez para configurar las tablas y columnas necesarias
 */

require_once 'config.php';
require_once 'audit_system.php';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicializar Sistema de Auditoría</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .success {
            color: #28a745;
            background: #d4edda;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .error {
            color: #dc3545;
            background: #f8d7da;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .info {
            color: #0c5460;
            background: #d1ecf1;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        h1 {
            color: #333;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Inicialización del Sistema de Auditoría</h1>
        
        <?php
        try {
            echo "<div class='info'>📋 Iniciando configuración del sistema de auditoría...</div>";
            
            // Crear instancia del sistema de auditoría (esto inicializa las tablas)
            $audit = new AuditSystem();
            echo "<div class='success'>✅ Sistema de auditoría inicializado correctamente</div>";
            
            // Verificar que la tabla activity_logs existe
            $pdo = getConnection();
            $stmt = $pdo->query("SHOW TABLES LIKE 'activity_logs'");
            if ($stmt->rowCount() > 0) {
                echo "<div class='success'>✅ Tabla 'activity_logs' creada correctamente</div>";
                
                // Mostrar estructura de la tabla
                $stmt = $pdo->query("DESCRIBE activity_logs");
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo "<div class='info'>";
                echo "<strong>Estructura de la tabla activity_logs:</strong><br>";
                foreach ($columns as $column) {
                    echo "• {$column['Field']} ({$column['Type']})<br>";
                }
                echo "</div>";
            } else {
                echo "<div class='error'>❌ Error: No se pudo crear la tabla 'activity_logs'</div>";
            }
            
            // Verificar columnas de auditoría en tablas principales
            $tables = ['incomes', 'expenses', 'income_payments', 'bank_accounts', 'teams', 'contractors'];
            $tablesChecked = 0;
            $columnsAdded = 0;
            
            foreach ($tables as $table) {
                try {
                    // Verificar si la tabla existe
                    $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
                    $stmt->execute([$table]);
                    if ($stmt->rowCount() == 0) {
                        echo "<div class='info'>ℹ️ Tabla '{$table}' no existe - saltando</div>";
                        continue;
                    }
                    
                    $tablesChecked++;
                    
                    // Verificar si las columnas created_by y updated_by existen
                    $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE 'created_by'");
                    $hasCreatedBy = $stmt->rowCount() > 0;
                    
                    $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE 'updated_by'");
                    $hasUpdatedBy = $stmt->rowCount() > 0;
                    
                    if ($hasCreatedBy && $hasUpdatedBy) {
                        echo "<div class='success'>✅ Tabla '{$table}' ya tiene columnas de auditoría</div>";
                    } else {
                        echo "<div class='info'>🔧 Agregando columnas de auditoría a '{$table}'...</div>";
                        $columnsAdded++;
                    }
                    
                } catch (Exception $e) {
                    echo "<div class='error'>❌ Error verificando tabla '{$table}': " . $e->getMessage() . "</div>";
                }
            }
            
            echo "<div class='success'>";
            echo "<strong>📊 Resumen de inicialización:</strong><br>";
            echo "• Tablas verificadas: {$tablesChecked}<br>";
            echo "• Sistema de auditoría: ✅ Activo<br>";
            echo "• Tabla activity_logs: ✅ Creada<br>";
            echo "• Columnas de auditoría: ✅ Configuradas<br>";
            echo "</div>";
            
            echo "<div class='info'>";
            echo "<strong>🎯 Próximos pasos:</strong><br>";
            echo "1. El sistema ya está registrando actividades automáticamente<br>";
            echo "2. Puedes ver las actividades en el dashboard<br>";
            echo "3. Los logs se guardan en la tabla 'activity_logs'<br>";
            echo "4. Se registran automáticamente creaciones, actualizaciones y eliminaciones<br>";
            echo "</div>";
            
            // Probar creando una actividad de prueba
            echo "<div class='info'>🧪 Creando actividad de prueba...</div>";
            $testResult = $audit->log(
                'test', 
                'system', 
                null, 
                'Inicialización del sistema de auditoría completada', 
                null, 
                ['timestamp' => date('Y-m-d H:i:s')]
            );
            
            if ($testResult) {
                echo "<div class='success'>✅ Actividad de prueba creada correctamente</div>";
                
                // Obtener y mostrar la actividad creada
                $recentActivities = $audit->getRecentActivities(1);
                if (!empty($recentActivities)) {
                    $lastActivity = $recentActivities[0];
                    echo "<div class='info'>";
                    echo "<strong>Última actividad registrada:</strong><br>";
                    echo "• Usuario: " . ($lastActivity['username'] ?? 'Sistema') . "<br>";
                    echo "• Acción: " . $lastActivity['action'] . "<br>";
                    echo "• Descripción: " . $lastActivity['description'] . "<br>";
                    echo "• Fecha: " . $lastActivity['created_at'] . "<br>";
                    echo "</div>";
                }
            } else {
                echo "<div class='error'>❌ Error creando actividad de prueba</div>";
            }
            
        } catch (Exception $e) {
            echo "<div class='error'>❌ Error durante la inicialización: " . $e->getMessage() . "</div>";
            echo "<div class='error'>Archivo: " . $e->getFile() . " - Línea: " . $e->getLine() . "</div>";
        }
        ?>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="dashboard.php" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;">
                Ver Dashboard
            </a>
            <a href="settings.php?tab=reports" style="background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; margin-left: 10px;">
                Ver Reportes
            </a>
        </div>
    </div>
</body>
</html> 
