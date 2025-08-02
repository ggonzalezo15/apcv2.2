<?php
// Script simple para debug de fechas sin sesiones
try {
    // Configuración directa de BD sin config.php
    $host = '168.231.68.229';
    $port = '3306';
    $dbname = 'cloude_apcuadre';
    $username = 'workbench_user';
    $password = 'Mysql2025#';
    
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== ANÁLISIS DE FECHAS JULIO-AGOSTO 2025 ===\n\n";
    
    // Primero verificar la estructura de las tablas
    echo "=== ESTRUCTURA TABLA INCOMES ===\n";
    $result = $pdo->query("DESCRIBE incomes");
    $columns = $result->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        echo "Columna: {$column['Field']} | Tipo: {$column['Type']}\n";
    }
    
    echo "\n=== ESTRUCTURA TABLA INCOME_PAYMENTS ===\n";
    $result = $pdo->query("DESCRIBE income_payments");
    $columns = $result->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        echo "Columna: {$column['Field']} | Tipo: {$column['Type']}\n";
    }
    
    echo "\n";
    
    // 1. Registros de ingresos en julio y agosto
    $query = "SELECT i.id, i.income_date, i.total_amount, t.name as team_name, 
              DATE_FORMAT(i.income_date, '%Y-%m-%d') as formatted_date,
              YEAR(i.income_date) as year, MONTH(i.income_date) as month
              FROM incomes i 
              LEFT JOIN teams t ON i.team_id = t.id 
              WHERE i.income_date >= '2025-07-01' AND i.income_date <= '2025-08-31'
              ORDER BY i.income_date DESC";
    
    $result = $pdo->query($query);
    $incomes = $result->fetchAll(PDO::FETCH_ASSOC);
    
    echo "=== REGISTROS DE INGRESOS ===\n";
    echo "Total registros encontrados: " . count($incomes) . "\n\n";
    
    foreach ($incomes as $income) {
        echo "ID: {$income['id']} | Fecha: {$income['formatted_date']} | Año: {$income['year']} | Mes: {$income['month']} | Equipo: {$income['team_name']} | Monto: \${$income['total_amount']}\n";
    }
    
    echo "\n=== AGRUPACIÓN POR MES ===\n";
    $months = [];
    foreach ($incomes as $income) {
        $key = $income['year'] . '-' . str_pad($income['month'], 2, '0', STR_PAD_LEFT);
        if (!isset($months[$key])) {
            $months[$key] = 0;
        }
        $months[$key]++;
    }
    
    foreach ($months as $month => $count) {
        echo "Mes: $month | Total registros: $count\n";
    }
    
    // 2. Revisar específicamente agosto 2025
    echo "\n=== VERIFICACIÓN RANGO AGOSTO 2025 ===\n";
    $startDate = '2025-08-01';
    $endDate = '2025-08-31';
    
    $query2 = "SELECT COUNT(*) as total FROM incomes WHERE income_date >= ? AND income_date <= ?";
    $stmt = $pdo->prepare($query2);
    $stmt->execute([$startDate, $endDate]);
    $result2 = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Registros en agosto 2025 ($startDate - $endDate): {$result2['total']}\n";
    
    // 3. Revisar consulta exacta de team payment methods (NUEVA VERSIÓN)
    echo "\n=== CONSULTA TEAM PAYMENT METHODS AGOSTO 2025 (NUEVA) ===\n";
    $query3 = "SELECT 
                t.name as team_name,
                pt.name as payment_method,
                COUNT(ip.id) as payment_count,
                SUM(ip.amount) as total_amount,
                SUM(ip.fee) as total_fee
              FROM income_payments ip
              INNER JOIN incomes i ON ip.income_id = i.id
              INNER JOIN teams t ON i.team_id = t.id
              INNER JOIN payment_types pt ON ip.payment_type_id = pt.id
              WHERE i.income_date BETWEEN ? AND ?
                  AND pt.status = 'active'
              GROUP BY t.id, t.name, pt.id, pt.name
              ORDER BY t.name ASC, pt.name ASC";
              
    $stmt3 = $pdo->prepare($query3);
    $stmt3->execute([$startDate, $endDate]);
    $results3 = $stmt3->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Resultados de team payment methods (NUEVA): " . count($results3) . "\n";
    foreach ($results3 as $row) {
        echo "Equipo: {$row['team_name']} | Método: {$row['payment_method']} | Monto: \${$row['total_amount']} | Pagos: {$row['payment_count']}\n";
    }
    
    // 4. Comparar con la consulta anterior (VERSIÓN ANTIGUA)
    echo "\n=== CONSULTA TEAM PAYMENT METHODS AGOSTO 2025 (ANTIGUA) ===\n";
    $query4 = "SELECT 
                t.name as team_name,
                pt.name as payment_method,
                COUNT(ip.id) as payment_count,
                SUM(ip.amount) as total_amount,
                SUM(ip.fee) as total_fee
              FROM income_payments ip
              INNER JOIN incomes i ON ip.income_id = i.id
              INNER JOIN teams t ON i.team_id = t.id
              INNER JOIN payment_types pt ON ip.payment_type_id = pt.id
              WHERE DATE(ip.created_at) BETWEEN ? AND ?
                  AND pt.status = 'active'
              GROUP BY t.id, t.name, pt.id, pt.name
              ORDER BY t.name ASC, pt.name ASC";
              
    $stmt4 = $pdo->prepare($query4);
    $stmt4->execute([$startDate, $endDate]);
    $results4 = $stmt4->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Resultados de team payment methods (ANTIGUA): " . count($results4) . "\n";
    foreach ($results4 as $row) {
        echo "Equipo: {$row['team_name']} | Método: {$row['payment_method']} | Monto: \${$row['total_amount']} | Pagos: {$row['payment_count']}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
