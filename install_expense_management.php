<?php
require_once 'config.php';

// Obtener conexión a la base de datos
$pdo = getConnection();

// Crear tabla de categorías de gastos
$createCategoriesTable = "
CREATE TABLE IF NOT EXISTS expense_categories (
    id VARCHAR(255) PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    color VARCHAR(7) DEFAULT '#6B7280',
    icon VARCHAR(50) DEFAULT 'fas fa-tag',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

// Crear tabla de tipos de gastos
$createExpenseTypesTable = "
CREATE TABLE IF NOT EXISTS expense_types (
    id VARCHAR(255) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    category_id VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES expense_categories(id) ON DELETE SET NULL,
    UNIQUE KEY unique_name_category (name, category_id)
)";

try {
    // Crear tabla de categorías
    $pdo->exec($createCategoriesTable);
    echo "Tabla 'expense_categories' creada exitosamente.<br>";
    
    // Crear tabla de tipos de gastos
    $pdo->exec($createExpenseTypesTable);
    echo "Tabla 'expense_types' creada exitosamente.<br>";
    
    // Insertar categorías por defecto
    $defaultCategories = [
        [
            'id' => uniqid('cat_', true),
            'name' => 'Transporte',
            'description' => 'Gastos relacionados con transporte y combustible',
            'color' => '#3B82F6',
            'icon' => 'fas fa-car'
        ],
        [
            'id' => uniqid('cat_', true),
            'name' => 'Alimentación',
            'description' => 'Gastos en comidas y bebidas',
            'color' => '#10B981',
            'icon' => 'fas fa-utensils'
        ],
        [
            'id' => uniqid('cat_', true),
            'name' => 'Oficina',
            'description' => 'Gastos de oficina y suministros',
            'color' => '#F59E0B',
            'icon' => 'fas fa-building'
        ],
        [
            'id' => uniqid('cat_', true),
            'name' => 'Servicios',
            'description' => 'Servicios públicos y comunicaciones',
            'color' => '#8B5CF6',
            'icon' => 'fas fa-cogs'
        ],
        [
            'id' => uniqid('cat_', true),
            'name' => 'Marketing',
            'description' => 'Gastos de marketing y publicidad',
            'color' => '#EF4444',
            'icon' => 'fas fa-bullhorn'
        ]
    ];
    
    $stmt = $pdo->prepare("INSERT INTO expense_categories (id, name, description, color, icon) VALUES (?, ?, ?, ?, ?)");
    
    foreach ($defaultCategories as $category) {
        $stmt->execute([
            $category['id'],
            $category['name'],
            $category['description'],
            $category['color'],
            $category['icon']
        ]);
    }
    
    echo "Categorías por defecto insertadas exitosamente.<br>";
    
    // Insertar tipos de gastos por defecto
    $categories = $pdo->query("SELECT id, name FROM expense_categories")->fetchAll(PDO::FETCH_ASSOC);
    $categoryMap = array_column($categories, 'id', 'name');
    
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
        }
    }
    
    echo "Tipos de gastos por defecto insertados exitosamente.<br>";
    echo "<br><strong>Instalación completada exitosamente!</strong><br>";
    echo "<a href='expense_categories.php'>Ir a Categorías de Gastos</a> | <a href='expense_types.php'>Ir a Tipos de Gastos</a>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 