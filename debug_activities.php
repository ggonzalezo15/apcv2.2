<?php
/**
 * Script de Debug para Actividades Recientes
 * Verificar por qué no se muestran las actividades en el dashboard
 */

require_once 'config.php';
require_once 'audit_system.php';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug - Actividades Recientes</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1000px; margin: 20px auto; padding: 20px; }
        .debug-section { background: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #007bff; }
        .error { border-left-color: #dc3545; background: #f8d7da; }
        .success { border-left-color: #28a745; background: #d4edda; }
        .warning { border-left-color: #ffc107; background: #fff3cd; }
        pre { background: #f1f1f1; padding: 10px; border-radius: 3px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f2f2f2; }
    </style>
</head>
<body>
    <h1>🔍 Debug - Sistema de Actividades Recientes</h1>

    <?php
    echo "<div class='debug-section'>";
    echo "<h3>1. Verificación de Conexión a Base de Datos</h3>";
    try {
        $pdo = getConnection();
        echo "<div class='success'>✅ Conexión a base de datos exitosa</div>";
    } catch (Exception $e) {
        echo "<div class='error'>❌ Error de conexión: " . $e->getMessage() . "</div>";
        exit;
    }
    echo "</div>";

    echo "<div class='debug-section'>";
    echo "<h3>2. Verificación de Tabla activity_logs</h3>";
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM activity_logs");
        $count = $stmt->fetchColumn();
        echo "<div class='success'>✅ Tabla activity_logs existe con {$count} registros</div>";
        
        // Mostrar algunos registros
        $stmt = $pdo->query("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 5");
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($logs)) {
            echo "<table>";
            echo "<thead><tr><th>ID</th><th>Usuario</th><th>Acción</th><th>Entidad</th><th>Descripción</th><th>Fecha</th></tr></thead>";
            echo "<tbody>";
            foreach ($logs as $log) {
                echo "<tr>";
                echo "<td>{$log['id']}</td>";
                echo "<td>{$log['user_id']}</td>";
                echo "<td>{$log['action']}</td>";
                echo "<td>{$log['entity_type']}</td>";
                echo "<td>" . substr($log['description'], 0, 50) . "...</td>";
                echo "<td>{$log['created_at']}</td>";
                echo "</tr>";
            }
            echo "</tbody></table>";
        } else {
            echo "<div class='warning'>⚠️ No hay registros en activity_logs</div>";
        }
        
    } catch (Exception $e) {
        echo "<div class='error'>❌ Error accediendo a activity_logs: " . $e->getMessage() . "</div>";
    }
    echo "</div>";

    echo "<div class='debug-section'>";
    echo "<h3>3. Prueba de Función getRecentActivities() Directa</h3>";
    try {
        $activities = getRecentActivities(5);
        echo "<div class='success'>✅ Función getRecentActivities() ejecutada</div>";
        echo "<div>Resultados encontrados: " . count($activities) . "</div>";
        
        if (!empty($activities)) {
            echo "<pre>" . print_r($activities, true) . "</pre>";
        } else {
            echo "<div class='warning'>⚠️ La función no devolvió actividades</div>";
        }
        
    } catch (Exception $e) {
        echo "<div class='error'>❌ Error en getRecentActivities(): " . $e->getMessage() . "</div>";
    }
    echo "</div>";

    echo "<div class='debug-section'>";
    echo "<h3>4. Prueba de AuditSystem Class Directa</h3>";
    try {
        $audit = new AuditSystem();
        $activities = $audit->getRecentActivities(5);
        echo "<div class='success'>✅ Clase AuditSystem instanciada correctamente</div>";
        echo "<div>Actividades encontradas: " . count($activities) . "</div>";
        
        if (!empty($activities)) {
            echo "<table>";
            echo "<thead><tr><th>Acción</th><th>Entidad</th><th>Usuario</th><th>Descripción</th><th>Fecha</th></tr></thead>";
            echo "<tbody>";
            foreach ($activities as $activity) {
                echo "<tr>";
                echo "<td>{$activity['action_icon']} {$activity['action']}</td>";
                echo "<td>{$activity['entity_name']}</td>";
                echo "<td>" . ($activity['username'] ?? 'Sistema') . "</td>";
                echo "<td>" . substr($activity['description'], 0, 60) . "...</td>";
                echo "<td>{$activity['created_at']}</td>";
                echo "</tr>";
            }
            echo "</tbody></table>";
        } else {
            echo "<div class='warning'>⚠️ AuditSystem no devolvió actividades</div>";
        }
        
    } catch (Exception $e) {
        echo "<div class='error'>❌ Error en AuditSystem: " . $e->getMessage() . "</div>";
    }
    echo "</div>";

    echo "<div class='debug-section'>";
    echo "<h3>5. Simulación de la Función del Dashboard</h3>";
    try {
        // Simular la función tal como está en dashboard.php
        function testGetRecentActivity($pdo, $limit = 5) {
            // Incluir el sistema de auditoría
            require_once 'audit_system.php';
            
            try {
                // Intentar obtener actividades del sistema de auditoría primero
                $activities = getRecentActivities($limit);
                
                if (!empty($activities)) {
                    // Formatear las actividades del sistema de auditoría
                    $formattedActivities = [];
                    foreach ($activities as $activity) {
                        $formattedActivities[] = [
                            'type' => $activity['entity_type'],
                            'date' => $activity['created_at'],
                            'description' => $activity['action_icon'] . ' ' . $activity['description'],
                            'amount' => null,
                            'account_name' => $activity['username'] ?? 'Sistema',
                            'user' => $activity['username'],
                            'action_icon' => $activity['action_icon'],
                            'entity_name' => $activity['entity_name']
                        ];
                    }
                    return $formattedActivities;
                }
            } catch (Exception $e) {
                echo "<div class='error'>Error en auditoría: " . $e->getMessage() . "</div>";
            }
            
            // Fallback
            return [];
        }
        
        $dashboardActivities = testGetRecentActivity($pdo, 5);
        echo "<div class='success'>✅ Función de dashboard simulada</div>";
        echo "<div>Actividades del dashboard: " . count($dashboardActivities) . "</div>";
        
        if (!empty($dashboardActivities)) {
            echo "<table>";
            echo "<thead><tr><th>Tipo</th><th>Descripción</th><th>Usuario</th><th>Fecha</th></tr></thead>";
            echo "<tbody>";
            foreach ($dashboardActivities as $activity) {
                echo "<tr>";
                echo "<td>{$activity['type']}</td>";
                echo "<td>{$activity['description']}</td>";
                echo "<td>" . ($activity['user'] ?? 'N/A') . "</td>";
                echo "<td>{$activity['date']}</td>";
                echo "</tr>";
            }
            echo "</tbody></table>";
        } else {
            echo "<div class='warning'>⚠️ Dashboard no muestra actividades</div>";
        }
        
    } catch (Exception $e) {
        echo "<div class='error'>❌ Error simulando dashboard: " . $e->getMessage() . "</div>";
    }
    echo "</div>";

    echo "<div class='debug-section'>";
    echo "<h3>6. Verificación de Datos Fallback</h3>";
    try {
        // Verificar income_payments
        $stmt = $pdo->query("SELECT COUNT(*) FROM income_payments");
        $incomePaymentsCount = $stmt->fetchColumn();
        echo "<div>Registros en income_payments: {$incomePaymentsCount}</div>";
        
        // Verificar expenses
        $stmt = $pdo->query("SELECT COUNT(*) FROM expenses");
        $expensesCount = $stmt->fetchColumn();
        echo "<div>Registros en expenses: {$expensesCount}</div>";
        
        if ($incomePaymentsCount > 0 || $expensesCount > 0) {
            echo "<div class='success'>✅ Hay datos para fallback</div>";
        } else {
            echo "<div class='warning'>⚠️ No hay datos de fallback disponibles</div>";
        }
        
    } catch (Exception $e) {
        echo "<div class='error'>❌ Error verificando datos fallback: " . $e->getMessage() . "</div>";
    }
    echo "</div>";

    echo "<div class='debug-section'>";
    echo "<h3>7. Crear Actividad de Prueba</h3>";
    try {
        $audit = new AuditSystem();
        $testResult = $audit->log(
            'create', 
            'test_entity', 
            'test-123', 
            'Actividad de prueba desde debug - ' . date('H:i:s'), 
            null, 
            ['debug' => true, 'timestamp' => time()]
        );
        
        if ($testResult) {
            echo "<div class='success'>✅ Actividad de prueba creada</div>";
            
            // Verificar que se guardó
            $stmt = $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE entity_type = 'test_entity'");
            $testCount = $stmt->fetchColumn();
            echo "<div>Actividades de prueba en DB: {$testCount}</div>";
            
        } else {
            echo "<div class='error'>❌ No se pudo crear actividad de prueba</div>";
        }
        
    } catch (Exception $e) {
        echo "<div class='error'>❌ Error creando actividad de prueba: " . $e->getMessage() . "</div>";
    }
    echo "</div>";
    ?>

    <div style="text-align: center; margin-top: 30px;">
        <a href="dashboard.php" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;">
            Ver Dashboard
        </a>
        <a href="debug_activities.php" style="background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; margin-left: 10px;">
            Refrescar Debug
        </a>
    </div>
</body>
</html> 