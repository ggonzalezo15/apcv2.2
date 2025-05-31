<?php
require_once 'config.php';

try {
    $pdo = getConnection();
    echo "=== VERIFICACIÓN Y CORRECCIÓN DE TABLAS ===\n\n";
    
    // Verificar si las tablas existen
    echo "1. Verificando si las tablas existen...\n";
    
    $tables = $pdo->query("SHOW TABLES LIKE 'expense%'")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tablas encontradas: " . implode(', ', $tables) . "\n\n";
    
    // Verificar estructura de expense_categories
    if (in_array('expense_categories', $tables)) {
        echo "2. Estructura de expense_categories:\n";
        $columns = $pdo->query("DESCRIBE expense_categories")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $column) {
            echo "  - {$column['Field']} ({$column['Type']})\n";
        }
        
        // Contar registros
        $count = $pdo->query("SELECT COUNT(*) FROM expense_categories")->fetchColumn();
        echo "  Registros: $count\n\n";
    }
    
    // Verificar estructura de expense_types
    if (in_array('expense_types', $tables)) {
        echo "3. Estructura de expense_types:\n";
        $columns = $pdo->query("DESCRIBE expense_types")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $column) {
            echo "  - {$column['Field']} ({$column['Type']})\n";
        }
        
        // Contar registros
        $count = $pdo->query("SELECT COUNT(*) FROM expense_types")->fetchColumn();
        echo "  Registros: $count\n\n";
    }
    
    // Si hay categorías, insertar tipos de gastos
    $categoryCount = $pdo->query("SELECT COUNT(*) FROM expense_categories")->fetchColumn();
    if ($categoryCount > 0) {
        echo "4. Insertando tipos de gastos por defecto...\n";
        
        // Obtener categorías existentes
        $categories = $pdo->query("SELECT id, name FROM expense_categories")->fetchAll(PDO::FETCH_ASSOC);
        $categoryMap = array_column($categories, 'id', 'name');
        
        echo "Categorías disponibles:\n";
        foreach ($categories as $cat) {
            echo "  - {$cat['name']} (ID: {$cat['id']})\n";
        }
        echo "\n";
        
        // Verificar si ya hay tipos de gastos
        $typeCount = $pdo->query("SELECT COUNT(*) FROM expense_types")->fetchColumn();
        if ($typeCount == 0) {
            $defaultExpenseTypes = [
                ['name' => 'Combustible', 'category' => 'Transporte', 'description' => 'Gasolina y diesel'],
                ['name' => 'Taxi/Uber', 'category' => 'Transporte', 'description' => 'Servicios de transporte'],
                ['name' => 'Peajes', 'category' => 'Transporte', 'description' => 'Costos de peajes'],
                ['name' => 'Almuerzos', 'category' => 'Alimentación', 'description' => 'Comidas de mediodía'],
                ['name' => 'Cenas de trabajo', 'category' => 'Alimentación', 'description' => 'Comidas con clientes'],
                ['name' => 'Café y snacks', 'category' => 'Alimentación', 'description' => 'Refrigerios'],
                ['name' => 'Papelería', 'category' => 'Oficina', 'description' => 'Papel, bolígrafos, etc.'],
                ['name' => 'Software', 'category' => 'Oficina', 'description' => 'Licencias de software'],
                ['name' => 'Internet', 'category' => 'Servicios', 'description' => 'Servicio de internet'],
                ['name' => 'Teléfono', 'category' => 'Servicios', 'description' => 'Servicios telefónicos'],
                ['name' => 'Publicidad online', 'category' => 'Marketing', 'description' => 'Anuncios en redes sociales'],
                ['name' => 'Material promocional', 'category' => 'Marketing', 'description' => 'Folletos, tarjetas, etc.']
            ];
            
            $stmt = $pdo->prepare("INSERT INTO expense_types (id, name, description, category_id) VALUES (?, ?, ?, ?)");
            
            foreach ($defaultExpenseTypes as $type) {
                $categoryId = $categoryMap[$type['category']] ?? null;
                if ($categoryId) {
                    $stmt->execute([
                        uniqid('type_', true),
                        $type['name'],
                        $type['description'],
                        $categoryId
                    ]);
                    echo "  ✓ Insertado: {$type['name']} en {$type['category']}\n";
                } else {
                    echo "  ✗ No se encontró la categoría: {$type['category']}\n";
                }
            }
        } else {
            echo "Los tipos de gastos ya existen ($typeCount registros)\n";
        }
    }
    
    echo "\n=== VERIFICACIÓN FINAL ===\n";
    $catCount = $pdo->query("SELECT COUNT(*) FROM expense_categories")->fetchColumn();
    $typeCount = $pdo->query("SELECT COUNT(*) FROM expense_types")->fetchColumn();
    
    echo "Categorías de gastos: $catCount\n";
    echo "Tipos de gastos: $typeCount\n";
    echo "\n¡Tablas configuradas correctamente!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 