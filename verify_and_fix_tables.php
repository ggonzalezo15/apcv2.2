<?php
require_once 'config.php';

try {
    $pdo = getConnection();
    echo "=== VERIFICACIÓN Y CORRECCIÓN DE ESTRUCTURA DE TABLAS ===\n\n";
    
    // Verificar si las tablas existen
    $tables = $pdo->query("SHOW TABLES LIKE 'expense%'")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tablas encontradas: " . implode(', ', $tables) . "\n\n";
    
    // Verificar estructura de expense_categories
    if (in_array('expense_categories', $tables)) {
        echo "1. Estructura de expense_categories:\n";
        $columns = $pdo->query("DESCRIBE expense_categories")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $column) {
            echo "  - {$column['Field']} ({$column['Type']})\n";
        }
        echo "\n";
    }
    
    // Verificar estructura de expense_types
    if (in_array('expense_types', $tables)) {
        echo "2. Estructura de expense_types:\n";
        $columns = $pdo->query("DESCRIBE expense_types")->fetchAll(PDO::FETCH_ASSOC);
        $columnNames = array_column($columns, 'Field');
        
        foreach ($columns as $column) {
            echo "  - {$column['Field']} ({$column['Type']})\n";
        }
        
        // Verificar si falta la columna category_id
        if (!in_array('category_id', $columnNames)) {
            echo "\n❌ ERROR: Falta la columna 'category_id' en expense_types\n";
            echo "🔧 Agregando columna category_id...\n";
            
            $pdo->exec("ALTER TABLE expense_types ADD COLUMN category_id VARCHAR(255) AFTER description");
            echo "✅ Columna category_id agregada\n";
            
            // Agregar la foreign key
            echo "🔧 Agregando foreign key constraint...\n";
            try {
                $pdo->exec("ALTER TABLE expense_types ADD CONSTRAINT fk_expense_types_category 
                           FOREIGN KEY (category_id) REFERENCES expense_categories(id) ON DELETE SET NULL");
                echo "✅ Foreign key agregada\n";
            } catch (Exception $e) {
                echo "⚠️ No se pudo agregar foreign key: " . $e->getMessage() . "\n";
            }
        } else {
            echo "✅ Columna category_id existe\n";
        }
        echo "\n";
    }
    
    // Si hay expense_types sin category_id asignado, intentar asignarlos
    $typesWithoutCategory = $pdo->query("SELECT COUNT(*) FROM expense_types WHERE category_id IS NULL")->fetchColumn();
    
    if ($typesWithoutCategory > 0) {
        echo "3. Corrigiendo tipos de gastos sin categoría ($typesWithoutCategory registros):\n";
        
        // Obtener categorías disponibles
        $categories = $pdo->query("SELECT id, name FROM expense_categories")->fetchAll(PDO::FETCH_ASSOC);
        $categoryMap = array_column($categories, 'id', 'name');
        
        if (!empty($categoryMap)) {
            // Mapeo de tipos a categorías basado en nombres
            $typeMapping = [
                'Combustible' => 'Transporte',
                'Taxi' => 'Transporte',
                'Uber' => 'Transporte',
                'Peajes' => 'Transporte',
                'Almuerzos' => 'Alimentación',
                'Cenas' => 'Alimentación',
                'Café' => 'Alimentación',
                'Papelería' => 'Oficina',
                'Software' => 'Oficina',
                'Internet' => 'Servicios',
                'Teléfono' => 'Servicios',
                'Publicidad' => 'Marketing',
                'Material' => 'Marketing'
            ];
            
            $typesWithoutCat = $pdo->query("SELECT id, name FROM expense_types WHERE category_id IS NULL")->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($typesWithoutCat as $type) {
                $assignedCategory = null;
                
                // Buscar coincidencia por nombre
                foreach ($typeMapping as $keyword => $categoryName) {
                    if (stripos($type['name'], $keyword) !== false) {
                        $assignedCategory = $categoryMap[$categoryName] ?? null;
                        break;
                    }
                }
                
                // Si no encontró coincidencia, asignar a una categoría por defecto
                if (!$assignedCategory && !empty($categoryMap)) {
                    $assignedCategory = array_values($categoryMap)[0]; // Primera categoría disponible
                }
                
                if ($assignedCategory) {
                    $stmt = $pdo->prepare("UPDATE expense_types SET category_id = ? WHERE id = ?");
                    $stmt->execute([$assignedCategory, $type['id']]);
                    echo "  ✅ {$type['name']} asignado a categoría\n";
                }
            }
        }
    }
    
    echo "\n=== ESTRUCTURA FINAL ===\n";
    
    // Mostrar estructura final
    if (in_array('expense_types', $tables)) {
        $columns = $pdo->query("DESCRIBE expense_types")->fetchAll(PDO::FETCH_ASSOC);
        echo "expense_types:\n";
        foreach ($columns as $column) {
            echo "  - {$column['Field']} ({$column['Type']})\n";
        }
    }
    
    // Mostrar estadísticas
    $catCount = $pdo->query("SELECT COUNT(*) FROM expense_categories")->fetchColumn();
    $typeCount = $pdo->query("SELECT COUNT(*) FROM expense_types")->fetchColumn();
    $typesWithCategory = $pdo->query("SELECT COUNT(*) FROM expense_types WHERE category_id IS NOT NULL")->fetchColumn();
    
    echo "\nEstadísticas:\n";
    echo "📊 Categorías: $catCount\n";
    echo "📊 Tipos de gastos: $typeCount\n";
    echo "📊 Tipos con categoría asignada: $typesWithCategory\n";
    
    if ($typeCount == $typesWithCategory) {
        echo "✅ Todas las tablas están correctamente configuradas\n";
    } else {
        echo "⚠️ Algunos tipos no tienen categoría asignada\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== FIN ===\n";
?> 