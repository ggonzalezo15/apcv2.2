<?php
// test_db_income_status.php

require_once 'config.php';

try {
    $pdo = getConnection();

    echo "<h2>Verificación de Status de Ingresos</h2>";
    $stmt = $pdo->query("SELECT i.id, i.invoice_number, i.total_amount, i.status, 
        (SELECT COALESCE(SUM(amount),0) FROM income_payments p WHERE p.income_id = i.id) AS total_pagado
        FROM incomes i ORDER BY i.created_at DESC LIMIT 20");
    $rows = $stmt->fetchAll();

    if (!$rows) {
        echo "<p>No hay ingresos en la base de datos.</p>";
    } else {
        echo "<table border='1' cellpadding='6' style='border-collapse:collapse;'>";
        echo "<tr><th>ID</th><th>Factura</th><th>Total</th><th>Total Pagado</th><th>Status</th></tr>";
        foreach ($rows as $row) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['id']) . "</td>";
            echo "<td>" . htmlspecialchars($row['invoice_number']) . "</td>";
            echo "<td>" . number_format($row['total_amount'], 2) . "</td>";
            echo "<td>" . number_format($row['total_pagado'], 2) . "</td>";
            echo "<td>" . htmlspecialchars($row['status']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

} catch (Exception $e) {
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
} 