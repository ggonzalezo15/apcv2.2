<?php
require_once 'config.php';

try {
    $pdo = getConnection();
    
    echo "<h2>🔍 Verificación de Fechas en Base de Datos</h2>";
    
    // Obtener todas las fechas de gastos
    $stmt = $pdo->query("
        SELECT 
            id,
            expense_number,
            expense_date, 
            DATE_FORMAT(expense_date, '%Y-%m-%d') as formatted_date,
            created_at,
            DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') as formatted_created
        FROM expenses 
        ORDER BY expense_date DESC
    ");
    
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>📅 Total de gastos encontrados: " . count($expenses) . "</h3>";
    
    if (count($expenses) > 0) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f0f0f0;'>";
        echo "<th>Número</th>";
        echo "<th>Fecha Gasto (expense_date)</th>";
        echo "<th>Fecha Registro (created_at)</th>";
        echo "</tr>";
        
        foreach ($expenses as $expense) {
            echo "<tr>";
            echo "<td>{$expense['expense_number']}</td>";
            echo "<td><strong>{$expense['formatted_date']}</strong></td>";
            echo "<td>{$expense['formatted_created']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Mostrar rango de fechas
        $minDate = min(array_column($expenses, 'formatted_date'));
        $maxDate = max(array_column($expenses, 'formatted_date'));
        
        echo "<h3>📊 Rango de fechas:</h3>";
        echo "<p><strong>Fecha más antigua:</strong> {$minDate}</p>";
        echo "<p><strong>Fecha más reciente:</strong> {$maxDate}</p>";
        
        // Probar las consultas problemáticas
        echo "<h3>🧪 Pruebas de Consultas:</h3>";
        
        // Prueba 1: Solo dateFrom (debería funcionar)
        echo "<h4>1. Solo dateFrom = '2024-01-01':</h4>";
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM expenses WHERE expense_date >= ?");
        $stmt->execute(['2024-01-01']);
        $count1 = $stmt->fetchColumn();
        echo "<p>Resultado: <strong>{$count1} registros</strong></p>";
        
        // Prueba 2: Solo dateTo (problema)
        echo "<h4>2. Solo dateTo = '2024-12-31':</h4>";
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM expenses WHERE expense_date <= ?");
        $stmt->execute(['2024-12-31']);
        $count2 = $stmt->fetchColumn();
        echo "<p>Resultado: <strong>{$count2} registros</strong></p>";
        
        // Prueba 3: Rango completo (problema)
        echo "<h4>3. Rango: '2024-01-01' a '2024-12-31':</h4>";
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM expenses WHERE expense_date >= ? AND expense_date <= ?");
        $stmt->execute(['2024-01-01', '2024-12-31']);
        $count3 = $stmt->fetchColumn();
        echo "<p>Resultado: <strong>{$count3} registros</strong></p>";
        
        // Prueba 4: Sin filtros
        echo "<h4>4. Sin filtros (todos los registros):</h4>";
        $stmt = $pdo->query("SELECT COUNT(*) FROM expenses");
        $countAll = $stmt->fetchColumn();
        echo "<p>Resultado: <strong>{$countAll} registros</strong></p>";
        
        // Prueba 5: Con dateTo más amplio
        echo "<h4>5. Con dateTo = '2025-12-31' (más amplio):</h4>";
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM expenses WHERE expense_date <= ?");
        $stmt->execute(['2025-12-31']);
        $count5 = $stmt->fetchColumn();
        echo "<p>Resultado: <strong>{$count5} registros</strong></p>";
        
    } else {
        echo "<p>❌ No se encontraron gastos en la base de datos.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { margin: 20px 0; }
th, td { padding: 8px 12px; text-align: left; }
h2, h3, h4 { color: #333; }
p { margin: 10px 0; }
</style> 