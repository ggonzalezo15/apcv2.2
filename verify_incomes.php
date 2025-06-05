<?php
require_once 'config.php';

echo "=== VERIFICACIÓN DE TABLAS DE INGRESOS ===\n\n";

try {
    $pdo = getConnection();
    
    // Verificar tabla incomes
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM incomes");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✅ Tabla incomes: " . $result['count'] . " registros\n";
    
    // Verificar tabla income_lines
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM income_lines");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✅ Tabla income_lines: " . $result['count'] . " registros\n";
    
    // Verificar tabla income_payments
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM income_payments");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✅ Tabla income_payments: " . $result['count'] . " registros\n";
    
    // Mostrar ejemplo de ingreso
    $stmt = $pdo->query("
        SELECT i.*, t.name as team_name 
        FROM incomes i 
        LEFT JOIN teams t ON i.team_id = t.id 
        LIMIT 1
    ");
    $income = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($income) {
        echo "\n📋 EJEMPLO DE INGRESO:\n";
        echo "- Factura: " . $income['invoice_number'] . "\n";
        echo "- Fecha: " . $income['date'] . "\n";
        echo "- Equipo: " . $income['team_name'] . "\n";
        echo "- Total Ingresos: $" . number_format($income['total_income'], 2) . "\n";
        echo "- Total Pagos: $" . number_format($income['total_payments'], 2) . "\n";
        echo "- Balance: $" . number_format($income['balance'], 2) . "\n";
        echo "- Estado: " . $income['status'] . "\n";
    }
    
    echo "\n✅ VERIFICACIÓN COMPLETADA - Todo funcionando correctamente!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?> 