<?php
require_once 'config.php';

/**
 * Script para aplicar la migración de status en ingresos
 * Este script agrega la columna status y sus funciones automáticas
 */

class IncomeStatusMigration {
    private $pdo;
    private $backupTableName;
    
    public function __construct() {
        $this->pdo = getConnection();
        $this->backupTableName = 'incomes_backup_status_migration_' . date('Y_m_d_H_i_s');
    }
    
    public function run() {
        echo "=== MIGRACIÓN DE STATUS PARA INGRESOS ===\n\n";
        
        try {
            // Paso 1: Verificar pre-requisitos
            $this->checkPrerequisites();
            
            // Paso 2: Crear backup
            $this->createBackup();
            
            // Paso 3: Aplicar migración
            $this->applyMigration();
            
            // Paso 4: Verificar resultados
            $this->verifyResults();
            
            echo "\n✅ MIGRACIÓN COMPLETADA EXITOSAMENTE\n";
            echo "- Se agregó la columna 'status' a la tabla incomes\n";
            echo "- Se crearon funciones automáticas para calcular el status\n";
            echo "- Se configuraron triggers para actualización automática\n";
            echo "- Todos los registros existentes han sido actualizados\n\n";
            
            $this->showStatusStatistics();
            
        } catch (Exception $e) {
            echo "\n❌ ERROR EN LA MIGRACIÓN: " . $e->getMessage() . "\n";
            echo "🔄 Iniciando rollback...\n";
            $this->rollback();
            throw $e;
        }
    }
    
    private function checkPrerequisites() {
        echo "📋 Verificando pre-requisitos...\n";
        
        // Verificar si la columna ya existe
        $stmt = $this->pdo->query("
            SELECT COLUMN_NAME 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'incomes' 
            AND COLUMN_NAME = 'status'
        ");
        
        if ($stmt->fetch()) {
            throw new Exception("La columna 'status' ya existe en la tabla incomes. Migración no necesaria.");
        }
        
        // Verificar que las tablas relacionadas existen
        $requiredTables = ['incomes', 'income_lines', 'income_payments'];
        foreach ($requiredTables as $table) {
            $stmt = $this->pdo->query("SHOW TABLES LIKE '$table'");
            if (!$stmt->fetch()) {
                throw new Exception("Tabla requerida '$table' no encontrada.");
            }
        }
        
        echo "✅ Pre-requisitos verificados\n";
    }
    
    private function createBackup() {
        echo "💾 Creando backup de la tabla incomes...\n";
        
        $sql = "CREATE TABLE {$this->backupTableName} AS SELECT * FROM incomes";
        $this->pdo->exec($sql);
        
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM {$this->backupTableName}");
        $count = $stmt->fetchColumn();
        
        echo "✅ Backup creado: {$this->backupTableName} ({$count} registros)\n";
    }
    
    private function applyMigration() {
        echo "🔧 Aplicando migración...\n";
        
        // Leer y ejecutar el script de migración
        $migrationSQL = file_get_contents('migrate_add_income_status.sql');
        
        if (!$migrationSQL) {
            throw new Exception("No se pudo leer el archivo migrate_add_income_status.sql");
        }
        
        // Dividir en statements individuales
        $statements = $this->parseSQLStatements($migrationSQL);
        
        foreach ($statements as $i => $statement) {
            $statement = trim($statement);
            if (empty($statement) || strpos($statement, '--') === 0) {
                continue;
            }
            
            try {
                echo "   Ejecutando statement " . ($i + 1) . "...\n";
                $this->pdo->exec($statement);
            } catch (Exception $e) {
                // Algunos statements pueden fallar si ya existen (DROP IF EXISTS)
                if (strpos($statement, 'DROP') === false) {
                    throw new Exception("Error en statement " . ($i + 1) . ": " . $e->getMessage());
                }
            }
        }
        
        echo "✅ Migración aplicada\n";
    }
    
    private function parseSQLStatements($sql) {
        // Remover comentarios
        $sql = preg_replace('/--.*$/m', '', $sql);
        
        // Manejar bloques DELIMITER especiales
        $sql = preg_replace_callback('/DELIMITER \$\$(.*?)DELIMITER ;/s', function($matches) {
            return str_replace(';', '|SEMICOLON|', $matches[0]);
        }, $sql);
        
        // Dividir por punto y coma
        $statements = explode(';', $sql);
        
        // Restaurar ; en procedimientos/funciones y limpiar
        $cleanedStatements = array();
        foreach ($statements as $stmt) {
            $stmt = str_replace('|SEMICOLON|', ';', trim($stmt));
            if (!empty($stmt) && $stmt !== 'DELIMITER $$' && $stmt !== 'DELIMITER ;') {
                $cleanedStatements[] = $stmt;
            }
        }
        
        return $cleanedStatements;
    }
    
    private function verifyResults() {
        echo "🔍 Verificando resultados...\n";
        
        // Verificar que la columna fue creada
        $stmt = $this->pdo->query("
            SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_DEFAULT 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'incomes' 
            AND COLUMN_NAME = 'status'
        ");
        
        $columnInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$columnInfo) {
            throw new Exception("La columna 'status' no fue creada correctamente");
        }
        
        // Verificar que las funciones fueron creadas
        $stmt = $this->pdo->query("SHOW FUNCTION STATUS WHERE Name = 'CalculateIncomeStatus'");
        if (!$stmt->fetch()) {
            throw new Exception("La función CalculateIncomeStatus no fue creada");
        }
        
        // Verificar que los procedimientos fueron creados
        $stmt = $this->pdo->query("SHOW PROCEDURE STATUS WHERE Name = 'UpdateIncomeStatus'");
        if (!$stmt->fetch()) {
            throw new Exception("El procedimiento UpdateIncomeStatus no fue creado");
        }
        
        // Verificar que los triggers fueron creados
        $stmt = $this->pdo->query("SHOW TRIGGERS LIKE 'income_lines_after_insert'");
        if (!$stmt->fetch()) {
            throw new Exception("Los triggers no fueron creados correctamente");
        }
        
        echo "✅ Verificación completada\n";
    }
    
    private function showStatusStatistics() {
        echo "📊 ESTADÍSTICAS DE STATUS:\n";
        
        $stmt = $this->pdo->query("
            SELECT 
                status,
                COUNT(*) as count,
                ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM incomes)), 2) as percentage
            FROM incomes 
            GROUP BY status
            ORDER BY status
        ");
        
        $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($stats as $stat) {
            echo sprintf("   %-10s: %d registros (%.2f%%)\n", 
                ucfirst($stat['status']), 
                $stat['count'], 
                $stat['percentage']
            );
        }
        
        $totalStmt = $this->pdo->query("SELECT COUNT(*) FROM incomes");
        $total = $totalStmt->fetchColumn();
        echo sprintf("   %-10s: %d registros\n", "TOTAL", $total);
    }
    
    private function rollback() {
        try {
            echo "🔄 Realizando rollback...\n";
            
            // Eliminar triggers
            $triggers = [
                'income_lines_after_insert',
                'income_lines_after_update', 
                'income_lines_after_delete',
                'income_payments_after_insert',
                'income_payments_after_update',
                'income_payments_after_delete'
            ];
            
            foreach ($triggers as $trigger) {
                $this->pdo->exec("DROP TRIGGER IF EXISTS $trigger");
            }
            
            // Eliminar procedimientos y funciones
            $this->pdo->exec("DROP PROCEDURE IF EXISTS UpdateIncomeStatus");
            $this->pdo->exec("DROP PROCEDURE IF EXISTS RecalculateAllIncomeStatus");
            $this->pdo->exec("DROP FUNCTION IF EXISTS CalculateIncomeStatus");
            
            // Eliminar columna status
            $this->pdo->exec("ALTER TABLE incomes DROP COLUMN IF EXISTS status");
            
            echo "✅ Rollback completado\n";
            
        } catch (Exception $e) {
            echo "❌ Error durante rollback: " . $e->getMessage() . "\n";
            echo "🚨 ACCIÓN MANUAL REQUERIDA: Restaurar desde backup {$this->backupTableName}\n";
        }
    }
}

// Ejecución del script
if (php_sapi_name() === 'cli') {
    // Ejecutar desde línea de comandos
    try {
        $migration = new IncomeStatusMigration();
        $migration->run();
        echo "\n🎉 Migración completada exitosamente!\n";
        exit(0);
    } catch (Exception $e) {
        echo "\n💥 Error: " . $e->getMessage() . "\n";
        exit(1);
    }
} else {
    // Ejecutar desde navegador (solo para desarrollo)
    header('Content-Type: text/plain');
    
    echo "=== APLICAR MIGRACIÓN DE STATUS PARA INGRESOS ===\n\n";
    echo "⚠️  ADVERTENCIA: Esta operación modificará la estructura de la base de datos.\n";
    echo "📋 Se recomienda hacer un backup completo antes de continuar.\n\n";
    
    if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
        try {
            $migration = new IncomeStatusMigration();
            $migration->run();
        } catch (Exception $e) {
            echo "\n💥 Error: " . $e->getMessage() . "\n";
        }
    } else {
        echo "Para ejecutar la migración, visite:\n";
        echo $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . "?confirm=yes\n\n";
        echo "O ejecute desde línea de comandos:\n";
        echo "php apply_income_status_migration.php\n";
    }
}
?> 