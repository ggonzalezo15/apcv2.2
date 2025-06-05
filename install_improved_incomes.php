<?php
require_once 'config.php';

// Configurar la salida para mostrar en el navegador
echo "<!DOCTYPE html><html><head><title>Actualización de Tablas de Ingresos</title>";
echo "<style>body{font-family:Arial,sans-serif;margin:40px;} .success{color:#059669;background:#ecfdf5;padding:15px;border-radius:8px;margin:10px 0;} .error{color:#dc2626;background:#fef2f2;padding:15px;border-radius:8px;margin:10px 0;} .info{color:#1f2937;background:#f8fafc;padding:15px;border-radius:8px;margin:10px 0;}</style>";
echo "</head><body>";

echo "<h1>🔄 Actualización de Estructura de Tablas de Ingresos</h1>";

try {
    $pdo = getConnection();
    
    echo "<div class='info'>📊 Conectado a la base de datos exitosamente</div>";
    
    // ==========================================================================
    // ACTUALIZAR TABLA PRINCIPAL DE INGRESOS
    // ==========================================================================
    
    echo "<h2>📋 Actualizando tabla principal de ingresos...</h2>";
    
    // Eliminar tabla existente si existe (para estructura limpia)
    $pdo->exec("DROP TABLE IF EXISTS income_payments");
    $pdo->exec("DROP TABLE IF EXISTS income_lines");
    $pdo->exec("DROP TABLE IF EXISTS income_contractors");
    $pdo->exec("DROP TABLE IF EXISTS incomes");
    
    echo "<div class='success'>✅ Tablas anteriores eliminadas (si existían)</div>";
    
    // Crear nueva tabla de ingresos con estructura mejorada
    $createIncomesTable = "
        CREATE TABLE incomes (
            id CHAR(36) PRIMARY KEY,
            invoice_number VARCHAR(100) UNIQUE,
            date DATE NOT NULL,
            team_id CHAR(36),
            general_note TEXT,
            total_income DECIMAL(15,2) DEFAULT 0.00,
            total_payments DECIMAL(15,2) DEFAULT 0.00,
            balance DECIMAL(15,2) GENERATED ALWAYS AS (total_income - total_payments) STORED,
            status ENUM('draft', 'pending', 'partial_paid', 'paid', 'cancelled') DEFAULT 'draft',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL,
            INDEX idx_date (date),
            INDEX idx_team (team_id),
            INDEX idx_invoice (invoice_number),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $pdo->exec($createIncomesTable);
    echo "<div class='success'>✅ Tabla 'incomes' creada con estructura mejorada</div>";
    
    // ==========================================================================
    // TABLA DE CONTRATISTAS POR INGRESO (SELECCIÓN MÚLTIPLE)
    // ==========================================================================
    
    echo "<h2>👥 Creando tabla de contratistas por ingreso...</h2>";
    
    $createIncomeContractorsTable = "
        CREATE TABLE income_contractors (
            id CHAR(36) PRIMARY KEY,
            income_id CHAR(36) NOT NULL,
            contractor_id CHAR(36) NOT NULL,
            percentage DECIMAL(5,2) DEFAULT 0.00,
            amount DECIMAL(15,2) DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (income_id) REFERENCES incomes(id) ON DELETE CASCADE,
            FOREIGN KEY (contractor_id) REFERENCES contractors(id) ON DELETE CASCADE,
            UNIQUE KEY unique_income_contractor (income_id, contractor_id),
            INDEX idx_income (income_id),
            INDEX idx_contractor (contractor_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $pdo->exec($createIncomeContractorsTable);
    echo "<div class='success'>✅ Tabla 'income_contractors' creada</div>";
    
    // ==========================================================================
    // TABLA DE LÍNEAS DE INGRESO (CON JOB TYPES)
    // ==========================================================================
    
    echo "<h2>📝 Creando tabla de líneas de ingreso...</h2>";
    
    $createIncomeLinesTable = "
        CREATE TABLE income_lines (
            id CHAR(36) PRIMARY KEY,
            income_id CHAR(36) NOT NULL,
            job_type_id CHAR(36),
            description TEXT NOT NULL,
            units DECIMAL(10,2) DEFAULT 1.00,
            unit_price DECIMAL(15,2) DEFAULT 0.00,
            total_amount DECIMAL(15,2) GENERATED ALWAYS AS (units * unit_price) STORED,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (income_id) REFERENCES incomes(id) ON DELETE CASCADE,
            FOREIGN KEY (job_type_id) REFERENCES job_types(id) ON DELETE SET NULL,
            INDEX idx_income (income_id),
            INDEX idx_job_type (job_type_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $pdo->exec($createIncomeLinesTable);
    echo "<div class='success'>✅ Tabla 'income_lines' creada</div>";
    
    // ==========================================================================
    // TABLA DE PAGOS DE INGRESO (CON FEES)
    // ==========================================================================
    
    echo "<h2>💳 Creando tabla de pagos de ingreso...</h2>";
    
    $createIncomePaymentsTable = "
        CREATE TABLE income_payments (
            id CHAR(36) PRIMARY KEY,
            income_id CHAR(36) NOT NULL,
            payment_type_id CHAR(36),
            amount DECIMAL(15,2) NOT NULL,
            fee DECIMAL(15,2) DEFAULT 0.00,
            net_amount DECIMAL(15,2) GENERATED ALWAYS AS (amount - fee) STORED,
            payment_date DATE,
            reference_number VARCHAR(255),
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (income_id) REFERENCES incomes(id) ON DELETE CASCADE,
            FOREIGN KEY (payment_type_id) REFERENCES payment_types(id) ON DELETE SET NULL,
            INDEX idx_income (income_id),
            INDEX idx_payment_type (payment_type_id),
            INDEX idx_payment_date (payment_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $pdo->exec($createIncomePaymentsTable);
    echo "<div class='success'>✅ Tabla 'income_payments' creada</div>";
    
    // ==========================================================================
    // TRIGGERS PARA ACTUALIZAR TOTALES AUTOMÁTICAMENTE
    // ==========================================================================
    
    echo "<h2>⚡ Creando triggers para cálculos automáticos...</h2>";
    
    // Trigger para actualizar total_income cuando se modifican las líneas
    $triggerIncomeLines = "
        CREATE TRIGGER update_income_total_after_line_insert
        AFTER INSERT ON income_lines
        FOR EACH ROW
        BEGIN
            UPDATE incomes 
            SET total_income = (
                SELECT COALESCE(SUM(total_amount), 0) 
                FROM income_lines 
                WHERE income_id = NEW.income_id
            )
            WHERE id = NEW.income_id;
        END
    ";
    $pdo->exec($triggerIncomeLines);
    
    $triggerIncomeLines2 = "
        CREATE TRIGGER update_income_total_after_line_update
        AFTER UPDATE ON income_lines
        FOR EACH ROW
        BEGIN
            UPDATE incomes 
            SET total_income = (
                SELECT COALESCE(SUM(total_amount), 0) 
                FROM income_lines 
                WHERE income_id = NEW.income_id
            )
            WHERE id = NEW.income_id;
        END
    ";
    $pdo->exec($triggerIncomeLines2);
    
    $triggerIncomeLines3 = "
        CREATE TRIGGER update_income_total_after_line_delete
        AFTER DELETE ON income_lines
        FOR EACH ROW
        BEGIN
            UPDATE incomes 
            SET total_income = (
                SELECT COALESCE(SUM(total_amount), 0) 
                FROM income_lines 
                WHERE income_id = OLD.income_id
            )
            WHERE id = OLD.income_id;
        END
    ";
    $pdo->exec($triggerIncomeLines3);
    
    // Trigger para actualizar total_payments cuando se modifican los pagos
    $triggerIncomePayments = "
        CREATE TRIGGER update_income_payments_after_payment_insert
        AFTER INSERT ON income_payments
        FOR EACH ROW
        BEGIN
            UPDATE incomes 
            SET total_payments = (
                SELECT COALESCE(SUM(net_amount), 0) 
                FROM income_payments 
                WHERE income_id = NEW.income_id
            )
            WHERE id = NEW.income_id;
        END
    ";
    $pdo->exec($triggerIncomePayments);
    
    $triggerIncomePayments2 = "
        CREATE TRIGGER update_income_payments_after_payment_update
        AFTER UPDATE ON income_payments
        FOR EACH ROW
        BEGIN
            UPDATE incomes 
            SET total_payments = (
                SELECT COALESCE(SUM(net_amount), 0) 
                FROM income_payments 
                WHERE income_id = NEW.income_id
            )
            WHERE id = NEW.income_id;
        END
    ";
    $pdo->exec($triggerIncomePayments2);
    
    $triggerIncomePayments3 = "
        CREATE TRIGGER update_income_payments_after_payment_delete
        AFTER DELETE ON income_payments
        FOR EACH ROW
        BEGIN
            UPDATE incomes 
            SET total_payments = (
                SELECT COALESCE(SUM(net_amount), 0) 
                FROM income_payments 
                WHERE income_id = OLD.income_id
            )
            WHERE id = OLD.income_id;
        END
    ";
    $pdo->exec($triggerIncomePayments3);
    
    echo "<div class='success'>✅ Triggers creados para cálculos automáticos</div>";
    
    // ==========================================================================
    // FUNCIÓN PARA GENERAR UUID
    // ==========================================================================
    
    echo "<h2>🔧 Creando función para generar UUIDs...</h2>";
    
    $createUuidFunction = "
        CREATE FUNCTION generate_uuid() RETURNS CHAR(36)
        READS SQL DATA
        DETERMINISTIC
        BEGIN
            RETURN UUID();
        END
    ";
    
    try {
        $pdo->exec($createUuidFunction);
        echo "<div class='success'>✅ Función UUID creada</div>";
    } catch (Exception $e) {
        echo "<div class='info'>ℹ️ Función UUID ya existe o no se pudo crear: " . $e->getMessage() . "</div>";
    }
    
    // ==========================================================================
    // INSERTAR DATOS DE EJEMPLO
    // ==========================================================================
    
    echo "<h2>📊 Insertando datos de ejemplo...</h2>";
    
    // Generar UUID para el ingreso de ejemplo
    $incomeId = generateUUID();
    
    // Insertar ingreso de ejemplo
    $stmt = $pdo->prepare("
        INSERT INTO incomes (id, invoice_number, date, team_id, general_note, status) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $incomeId,
        'INV-' . date('Y') . '-001',
        date('Y-m-d'),
        '550e8400-e29b-41d4-a716-446655440001', // Equipo existente
        'Proyecto de desarrollo web - Ejemplo de datos',
        'draft'
    ]);
    echo "<div class='success'>✅ Ingreso de ejemplo insertado</div>";
    
    // Insertar líneas de ejemplo
    $lineIds = [generateUUID(), generateUUID()];
    
    $stmt = $pdo->prepare("
        INSERT INTO income_lines (id, income_id, job_type_id, description, units, unit_price) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $lineIds[0],
        $incomeId,
        '550e8400-e29b-41d4-a716-446655440031', // Job type existente
        'Desarrollo frontend',
        40.0,
        125.00
    ]);
    
    $stmt->execute([
        $lineIds[1],
        $incomeId,
        '550e8400-e29b-41d4-a716-446655440032',
        'Desarrollo backend',
        60.0,
        150.00
    ]);
    echo "<div class='success'>✅ Líneas de ingreso de ejemplo insertadas</div>";
    
    // Insertar pago de ejemplo
    $paymentId = generateUUID();
    $stmt = $pdo->prepare("
        INSERT INTO income_payments (id, income_id, payment_type_id, amount, fee, payment_date, reference_number, notes) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $paymentId,
        $incomeId,
        '550e8400-e29b-41d4-a716-446655440061', // Payment type existente
        7500.00,
        150.00,
        date('Y-m-d'),
        'PAY-' . date('Ymd') . '-001',
        'Primer pago del 50%'
    ]);
    echo "<div class='success'>✅ Pago de ejemplo insertado</div>";
    
    // ==========================================================================
    // VERIFICAR RESULTADOS
    // ==========================================================================
    
    echo "<h2>🔍 Verificando datos insertados...</h2>";
    
    $stmt = $pdo->prepare("
        SELECT 
            i.*,
            t.name as team_name,
            COUNT(il.id) as lines_count,
            COUNT(ip.id) as payments_count
        FROM incomes i
        LEFT JOIN teams t ON i.team_id = t.id
        LEFT JOIN income_lines il ON i.id = il.income_id
        LEFT JOIN income_payments ip ON i.id = ip.income_id
        WHERE i.id = ?
        GROUP BY i.id
    ");
    $stmt->execute([$incomeId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo "<div class='success'>";
        echo "<h3>📋 Resumen del ingreso creado:</h3>";
        echo "<ul>";
        echo "<li><strong>Factura:</strong> " . $result['invoice_number'] . "</li>";
        echo "<li><strong>Fecha:</strong> " . $result['date'] . "</li>";
        echo "<li><strong>Equipo:</strong> " . $result['team_name'] . "</li>";
        echo "<li><strong>Total Ingresos:</strong> $" . number_format($result['total_income'], 2) . "</li>";
        echo "<li><strong>Total Pagos:</strong> $" . number_format($result['total_payments'], 2) . "</li>";
        echo "<li><strong>Balance:</strong> $" . number_format($result['balance'], 2) . "</li>";
        echo "<li><strong>Líneas:</strong> " . $result['lines_count'] . "</li>";
        echo "<li><strong>Pagos:</strong> " . $result['payments_count'] . "</li>";
        echo "<li><strong>Estado:</strong> " . $result['status'] . "</li>";
        echo "</ul>";
        echo "</div>";
    }
    
    echo "<div class='success'><h2>🎉 ¡Actualización completada exitosamente!</h2></div>";
    echo "<div class='info'>";
    echo "<h3>🚀 Próximos pasos:</h3>";
    echo "<ol>";
    echo "<li>Crear el API Controller mejorado</li>";
    echo "<li>Desarrollar el frontend incomes.php</li>";
    echo "<li>Implementar funcionalidades JavaScript</li>";
    echo "<li>Probar todas las funcionalidades</li>";
    echo "</ol>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='error'>❌ Error durante la actualización: " . $e->getMessage() . "</div>";
    echo "<div class='error'>📍 Línea: " . $e->getLine() . " | Archivo: " . $e->getFile() . "</div>";
}

// Función auxiliar para generar UUID
function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

echo "</body></html>";
?> 