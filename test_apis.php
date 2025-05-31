<?php
echo "=== TESTING EXPENSE MANAGEMENT APIs ===\n\n";

function testAPI($url) {
    $fullUrl = "http://localhost/apv2.1/" . $url;
    echo "Probando: $url\n";
    
    // Usar cURL para probar la API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Code: $httpCode\n";
    
    if ($response === false) {
        echo "❌ Error: No se pudo conectar\n";
        return false;
    }
    
    // Verificar si es JSON válido
    $json = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "❌ Error: Respuesta no es JSON válido\n";
        echo "Respuesta: " . substr($response, 0, 200) . "...\n";
        return false;
    }
    
    echo "✅ JSON válido\n";
    
    if (isset($json['error'])) {
        echo "⚠️ Error en respuesta: " . $json['error'] . "\n";
    } elseif (isset($json['data'])) {
        echo "📊 Datos recibidos: " . count($json['data']) . " registros\n";
    }
    
    echo "\n";
    return true;
}

// Probar APIs de categorías
echo "1. TESTING EXPENSE CATEGORIES API\n";
echo "--------------------------------\n";
testAPI("api/expense_category/ExpenseCategoryController.php?action=getAllCategories");
testAPI("api/expense_category/ExpenseCategoryController.php?action=getActiveCategories");

// Probar APIs de tipos
echo "2. TESTING EXPENSE TYPES API\n";
echo "-----------------------------\n";
testAPI("api/expense_type/ExpenseTypeController.php?action=getAllExpenseTypes");

echo "=== FIN DE PRUEBAS ===\n";
?> 