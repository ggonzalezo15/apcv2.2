<?php
require_once 'config.php';

try {
    $pdo = getConnection();
    
    echo "=== CORRECCIÓN DE TABLA EXPENSES V2 ===\n\n";
    
    // Verificar si hay datos existentes
    $stmt = $pdo->query('SELECT COUNT(*) FROM expenses');
    $expenseCount = $stmt->fetchColumn();
    echo "Gastos existentes: $expenseCount\n";
    
    // 1. Mostrar foreign keys actuales
    echo "\n1. Foreign keys actuales en expenses:\n";
    $stmt = $pdo->query("
        SELECT 
            CONSTRAINT_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'expenses' 
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    
    $constraints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($constraints as $constraint) {
        echo "   - {$constraint['CONSTRAINT_NAME']}: {$constraint['COLUMN_NAME']} -> {$constraint['REFERENCED_TABLE_NAME']}.{$constraint['REFERENCED_COLUMN_NAME']}\n";
    }
    
    // 2. Eliminar foreign key constraint de expense_type_id si existe
    $expenseTypeConstraint = null;
    foreach($constraints as $constraint) {
        if ($constraint['COLUMN_NAME'] === 'expense_type_id') {
            $expenseTypeConstraint = $constraint['CONSTRAINT_NAME'];
            break;
        }
    }
    
    if ($expenseTypeConstraint) {
        echo "\n2. Eliminando foreign key constraint: $expenseTypeConstraint\n";
        $pdo->exec("ALTER TABLE expenses DROP FOREIGN KEY $expenseTypeConstraint");
        echo "   ✅ Foreign key constraint eliminado\n";
    } else {
        echo "\n2. ✅ No hay foreign key constraint para expense_type_id\n";
    }
    
    // 3. Eliminar columna expense_type_id
    $stmt = $pdo->query("SHOW COLUMNS FROM expenses LIKE 'expense_type_id'");
    if ($stmt->rowCount() > 0) {
        echo "\n3. Eliminando columna expense_type_id:\n";
        $pdo->exec("ALTER TABLE expenses DROP COLUMN expense_type_id");
        echo "   ✅ Columna expense_type_id eliminada\n";
    } else {
        echo "\n3. ✅ Columna expense_type_id ya no existe\n";
    }
    
    // 4. Corregir bank_account_id si es necesario
    echo "\n4. Verificando tipo de bank_account_id:\n";
    $stmt = $pdo->query("DESCRIBE expenses");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $bankAccountColumn = array_filter($columns, function($col) {
        return $col['Field'] === 'bank_account_id';
    });
    
    if (!empty($bankAccountColumn)) {
        $bankAccountColumn = array_values($bankAccountColumn)[0];
        echo "   - Tipo actual: {$bankAccountColumn['Type']}\n";
        
        if ($bankAccountColumn['Type'] !== 'char(36)') {
            echo "   - Cambiando a CHAR(36)...\n";
            
            // Encontrar constraint de bank_account_id
            $bankConstraint = null;
            foreach($constraints as $constraint) {
                if ($constraint['COLUMN_NAME'] === 'bank_account_id') {
                    $bankConstraint = $constraint['CONSTRAINT_NAME'];
                    break;
                }
            }
            
            // Eliminar foreign key si existe
            if ($bankConstraint) {
                try {
                    $pdo->exec("ALTER TABLE expenses DROP FOREIGN KEY $bankConstraint");
                    echo "   - Foreign key temporal eliminado\n";
                } catch (Exception $e) {
                    echo "   - Error eliminando FK: " . $e->getMessage() . "\n";
                }
            }
            
            // Cambiar tipo de columna
            $pdo->exec("ALTER TABLE expenses MODIFY COLUMN bank_account_id CHAR(36)");
            echo "   - Tipo de columna cambiado\n";
            
            // Recrear foreign key
            try {
                $pdo->exec("ALTER TABLE expenses ADD FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id)");
                echo "   ✅ Foreign key recreado\n";
            } catch (Exception $e) {
                echo "   ⚠️  Error recreando FK: " . $e->getMessage() . "\n";
            }
        } else {
            echo "   ✅ bank_account_id ya es CHAR(36)\n";
        }
    }
    
    // 5. Estructura final
    echo "\n5. Estructura final de expenses:\n";
    $stmt = $pdo->query('DESCRIBE expenses');
    while($row = $stmt->fetch()) {
        echo "   - {$row['Field']} ({$row['Type']}) - Null: {$row['Null']}\n";
    }
    
    echo "\n✅ CORRECCIONES COMPLETADAS\n";
    
} catch(Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
    echo 'Línea: ' . $e->getLine() . "\n";
}
?> 