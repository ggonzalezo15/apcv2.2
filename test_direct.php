<?php
require_once 'config.php';

echo "=== PRUEBA DIRECTA DE FUNCIONES ===\n\n";

try {
    $pdo = getConnection();
    echo "✅ Conexión a base de datos exitosa\n\n";
    
    // Simular llamada a API de categorías
    echo "1. Probando función getAllCategories:\n";
    
    $sql = "SELECT ec.*, 
            COUNT(et.id) as expense_types_count
            FROM expense_categories ec 
            LEFT JOIN expense_types et ON ec.id = et.category_id 
            GROUP BY ec.id 
            ORDER BY ec.created_at DESC, ec.id DESC
            LIMIT 10";
    
    $stmt = $pdo->query($sql);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total = $pdo->query("SELECT COUNT(*) FROM expense_categories")->fetchColumn();
    
    $result = ['data' => $categories, 'total' => (int)$total];
    $json = json_encode($result);
    
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "✅ JSON válido generado\n";
        echo "📊 Categorías encontradas: " . count($categories) . "\n";
        echo "📊 Total en BD: $total\n\n";
        
        foreach ($categories as $cat) {
            echo "  - {$cat['name']} ({$cat['expense_types_count']} tipos)\n";
        }
    } else {
        echo "❌ Error en JSON: " . json_last_error_msg() . "\n";
    }
    
    echo "\n2. Probando función de tipos de gastos:\n";
    
    $sql = "SELECT et.*, 
            ec.name as category_name,
            ec.color as category_color,
            ec.icon as category_icon
            FROM expense_types et 
            LEFT JOIN expense_categories ec ON et.category_id = ec.id 
            ORDER BY et.created_at DESC
            LIMIT 10";
    
    $stmt = $pdo->query($sql);
    $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalTypes = $pdo->query("SELECT COUNT(*) FROM expense_types")->fetchColumn();
    
    $resultTypes = ['data' => $types, 'total' => (int)$totalTypes];
    $jsonTypes = json_encode($resultTypes);
    
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "✅ JSON válido generado\n";
        echo "📊 Tipos encontrados: " . count($types) . "\n";
        echo "📊 Total en BD: $totalTypes\n\n";
        
        foreach ($types as $type) {
            echo "  - {$type['name']} -> {$type['category_name']}\n";
        }
    } else {
        echo "❌ Error en JSON: " . json_last_error_msg() . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== FIN DE PRUEBAS ===\n";
?> 