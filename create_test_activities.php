<?php
/**
 * Script para crear actividades de prueba
 * Esto ayuda a verificar que el sistema de auditoría funciona correctamente
 */

require_once 'config.php';
require_once 'audit_system.php';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Actividades de Prueba</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; background: #d4edda; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .info { color: #0c5460; background: #d1ecf1; padding: 10px; border-radius: 4px; margin: 10px 0; }
        h1 { color: #333; text-align: center; }
        .btn { padding: 10px 20px; margin: 5px; text-decoration: none; border-radius: 4px; display: inline-block; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Crear Actividades de Prueba</h1>
        
        <?php
        if (isset($_GET['action']) && $_GET['action'] === 'create') {
            echo "<div class='info'>📝 Creando actividades de prueba...</div>";
            
            try {
                $audit = new AuditSystem();
                $activitiesCreated = 0;
                
                // Simular diferentes tipos de actividades
                $testActivities = [
                    [
                        'action' => 'create',
                        'entity_type' => 'income',
                        'entity_id' => 'test-income-001',
                        'description' => 'Nuevo ingreso creado - Factura #2024-001',
                        'new_values' => ['invoice_number' => '2024-001', 'amount' => 5000.00]
                    ],
                    [
                        'action' => 'payment',
                        'entity_type' => 'income',
                        'entity_id' => 'test-income-001',
                        'description' => 'Pago recibido por $3,000.00',
                        'new_values' => ['payment_amount' => 3000.00, 'payment_type' => 'Transferencia']
                    ],
                    [
                        'action' => 'create',
                        'entity_type' => 'expense',
                        'entity_id' => 'test-expense-001',
                        'description' => 'Nuevo gasto registrado - Compra de suministros',
                        'new_values' => ['name' => 'Suministros de oficina', 'amount' => 250.00]
                    ],
                    [
                        'action' => 'update',
                        'entity_type' => 'bank_account',
                        'entity_id' => 'test-account-001',
                        'description' => 'Cuenta bancaria actualizada',
                        'new_values' => ['balance' => 15750.00]
                    ],
                    [
                        'action' => 'create',
                        'entity_type' => 'team',
                        'entity_id' => 'test-team-001',
                        'description' => 'Nuevo equipo creado - Desarrollo Frontend',
                        'new_values' => ['name' => 'Desarrollo Frontend']
                    ],
                    [
                        'action' => 'payment',
                        'entity_type' => 'income',
                        'entity_id' => 'test-income-002',
                        'description' => 'Pago recibido por $1,200.00 - Proyecto Web',
                        'new_values' => ['payment_amount' => 1200.00, 'project' => 'Desarrollo Web']
                    ],
                    [
                        'action' => 'update',
                        'entity_type' => 'expense',
                        'entity_id' => 'test-expense-001',
                        'description' => 'Gasto actualizado - Ajuste en monto',
                        'old_values' => ['amount' => 250.00],
                        'new_values' => ['amount' => 275.00]
                    ],
                    [
                        'action' => 'create',
                        'entity_type' => 'contractor',
                        'entity_id' => 'test-contractor-001',
                        'description' => 'Nuevo contratista agregado - Juan Pérez',
                        'new_values' => ['name' => 'Juan Pérez', 'email' => 'juan@example.com']
                    ]
                ];
                
                foreach ($testActivities as $activity) {
                    $result = $audit->log(
                        $activity['action'],
                        $activity['entity_type'], 
                        $activity['entity_id'],
                        $activity['description'],
                        $activity['old_values'] ?? null,
                        $activity['new_values'] ?? null
                    );
                    
                    if ($result) {
                        $activitiesCreated++;
                        echo "<div class='success'>✅ Actividad creada: {$activity['description']}</div>";
                        
                        // Pequeña pausa para que las fechas sean diferentes
                        usleep(500000); // 0.5 segundos
                    }
                }
                
                echo "<div class='success'>";
                echo "<h3>🎉 ¡Actividades de prueba creadas exitosamente!</h3>";
                echo "<p>Se crearon <strong>{$activitiesCreated}</strong> actividades de prueba.</p>";
                echo "</div>";
                
                // Mostrar las actividades creadas
                echo "<div class='info'>";
                echo "<h4>📋 Últimas actividades en el sistema:</h4>";
                $recentActivities = $audit->getRecentActivities(10);
                if (!empty($recentActivities)) {
                    echo "<ul>";
                    foreach ($recentActivities as $activity) {
                        echo "<li>";
                        echo "<strong>{$activity['action_icon']} {$activity['entity_name']}</strong>: ";
                        echo "{$activity['description']} ";
                        echo "<small>({$activity['created_at']})</small>";
                        echo "</li>";
                    }
                    echo "</ul>";
                }
                echo "</div>";
                
            } catch (Exception $e) {
                echo "<div class='error'>❌ Error creando actividades: " . $e->getMessage() . "</div>";
            }
        } else {
            echo "<div class='info'>";
            echo "<p>Este script creará varias actividades de prueba en el sistema de auditoría para verificar que el dashboard las muestre correctamente.</p>";
            echo "<p><strong>Las actividades de prueba incluyen:</strong></p>";
            echo "<ul>";
            echo "<li>🆕 Creación de ingresos</li>";
            echo "<li>💰 Pagos recibidos</li>";
            echo "<li>📝 Creación y actualización de gastos</li>";
            echo "<li>🏦 Actualizaciones de cuentas bancarias</li>";
            echo "<li>👥 Gestión de equipos y contratistas</li>";
            echo "</ul>";
            echo "</div>";
        }
        ?>
        
        <div style="text-align: center; margin-top: 30px;">
            <?php if (!isset($_GET['action'])): ?>
                <a href="?action=create" class="btn btn-primary">
                    🧪 Crear Actividades de Prueba
                </a>
            <?php endif; ?>
            
            <a href="dashboard.php" class="btn btn-success">
                📊 Ver Dashboard
            </a>
            
            <a href="debug_activities.php" class="btn" style="background: #6c757d; color: white;">
                🔍 Debug Actividades
            </a>
        </div>
        
        <?php if (isset($_GET['action'])): ?>
        <div style="text-align: center; margin-top: 20px;">
            <div class="info">
                <strong>💡 Tip:</strong> Ve al dashboard para ver las actividades recientes en acción.
                Las actividades se muestran independientemente del rango de fechas seleccionado.
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html> 