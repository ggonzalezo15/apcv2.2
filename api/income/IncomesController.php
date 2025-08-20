<?php
require_once '../../config.php';

// Verificar autenticación
checkAPIAuthentication();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$pdo = getConnection();

// Obtener datos JSON si existen
$jsonData = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? $_POST['action'] ?? ($jsonData['action'] ?? '');

try {
    switch ($action) {
        // ===== OPERACIONES CRUD DE INGRESOS =====
        case 'getIncomes':
            getIncomes();
            break;
        case 'getIncome':
            getIncome($_GET['id'] ?? '');
            break;
        case 'createIncome':
            createIncome();
            break;
        case 'updateIncome':
            updateIncome();
            break;
        case 'deleteIncome':
            deleteIncome($_POST['id'] ?? ($jsonData['id'] ?? ''));
            break;
            
        // ===== DATOS AUXILIARES =====
        case 'getTeams':
            getTeams();
            break;
        case 'getContractors':
            getContractors();
            break;
        case 'getJobTypes':
            getJobTypes();
            break;
        case 'getPaymentTypes':
            getPaymentTypes();
            break;
            
        // ===== OPERACIONES ESPECÍFICAS =====
        case 'generateInvoiceNumber':
            generateInvoiceNumber();
            break;
        case 'getIncomeStats':
            getIncomeStats();
            break;
        case 'recalculateStatus':
            recalculateIncomeStatus($_POST['id'] ?? ($jsonData['id'] ?? ''));
            break;
        case 'recalculateAllStatus':
            recalculateAllIncomeStatus();
            break;
        case 'checkInvoiceNumber':
            checkInvoiceNumber();
            break;
            
        default:
            throw new Exception('Acción no válida: ' . $action);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'line' => $e->getLine(),
        'file' => basename($e->getFile())
    ]);
}

// ============================================================================
// FUNCIONES CRUD DE INGRESOS
// ============================================================================

function getIncomes() {
    global $pdo;
    
    // Parámetros de paginación y filtros
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    $search = $_GET['search'] ?? '';
    $teamFilter = $_GET['team_filter'] ?? '';
    $dateFrom = $_GET['date_from'] ?? '';
    $dateTo = $_GET['date_to'] ?? '';
    $sortBy = $_GET['sort_by'] ?? 'created_at';
    $sortOrder = $_GET['sort_order'] ?? 'DESC';
    
    $offset = ($page - 1) * $limit;
    
    // Construir query base
    $whereConditions = [];
    $params = [];
    
    if (!empty($search)) {
        $whereConditions[] = "(i.invoice_number LIKE ? OR i.note LIKE ? OR t.name LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if (!empty($teamFilter)) {
        $whereConditions[] = "i.team_id = ?";
        $params[] = $teamFilter;
    }
    
    if (!empty($dateFrom)) {
        $whereConditions[] = "i.income_date >= ?";
        $params[] = $dateFrom;
    }
    
    if (!empty($dateTo)) {
        $whereConditions[] = "i.income_date <= ?";
        $params[] = $dateTo;
    }
    
    // Agregar filtro de status si se especifica
    $statusFilter = $_GET['status_filter'] ?? '';
    if (!empty($statusFilter)) {
        $whereConditions[] = "i.status = ?";
        $params[] = $statusFilter;
    }
    
    $whereClause = empty($whereConditions) ? '' : 'WHERE ' . implode(' AND ', $whereConditions);
    
    // Query para obtener total de registros
    $countQuery = "
        SELECT COUNT(DISTINCT i.id) as total
        FROM incomes i
        LEFT JOIN teams t ON i.team_id = t.id
        {$whereClause}
    ";
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Query principal - ahora usa la columna status de la base de datos
    $query = "
        SELECT 
            i.*,
            t.name as team_name,
            DATE_FORMAT(i.income_date, '%Y-%m-%d') as date,
            i.note as general_note,
            i.status,
            COUNT(DISTINCT il.id) as lines_count,
            COUNT(DISTINCT ip.id) as payments_count,
            COALESCE(SUM(DISTINCT il.total_amount), 0) as total_income,
            COALESCE(SUM(DISTINCT ip.amount), 0) as total_payments,
            COALESCE(SUM(DISTINCT ip.fee), 0) as total_fees
        FROM incomes i
        LEFT JOIN teams t ON i.team_id = t.id
        LEFT JOIN income_lines il ON i.id = il.income_id
        LEFT JOIN income_payments ip ON i.id = ip.income_id
        {$whereClause}
        GROUP BY i.id, i.team_id, i.total_amount, i.status, i.invoice_number, i.income_date, i.contractor_ids, i.note, i.created_at, i.updated_at, t.name
        ORDER BY i.{$sortBy} {$sortOrder}
        LIMIT {$limit} OFFSET {$offset}
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $incomes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular estadísticas para cada ingreso
    foreach ($incomes as &$income) {
        // Calcular balance
        $totalIncome = floatval($income['total_income']);
        $totalPayments = floatval($income['total_payments']);
        $balance = $totalIncome - $totalPayments;
        
        $income['balance'] = $balance;
        $income['balance_formatted'] = number_format($balance, 2);
        $income['total_income_formatted'] = number_format($totalIncome, 2);
        $income['total_payments_formatted'] = number_format($totalPayments, 2);
        
        // Porcentaje de pago
        $income['payment_percentage'] = $totalIncome > 0 
            ? round(($totalPayments / $totalIncome) * 100, 1)
            : 0;
            
        // Decodificar contratistas JSON
        $contractors = [];
        if (!empty($income['contractor_ids'])) {
            $contractorIds = json_decode($income['contractor_ids'], true);
            if ($contractorIds && is_array($contractorIds)) {
                // Obtener nombres de contratistas
                $placeholders = str_repeat('?,', count($contractorIds) - 1) . '?';
                $contractorStmt = $pdo->prepare("SELECT name FROM contractors WHERE id IN ({$placeholders})");
                $contractorStmt->execute($contractorIds);
                $contractorNames = $contractorStmt->fetchAll(PDO::FETCH_COLUMN);
                
                foreach ($contractorNames as $name) {
                    $contractors[] = ['name' => $name];
                }
            }
        }
        $income['contractors'] = $contractors;
    }
    
    echo json_encode([
        'data' => $incomes,
        'total' => (int)$totalRecords
    ]);
}

function getIncome($id) {
    global $pdo;
    
    if (empty($id)) {
        throw new Exception('ID de ingreso requerido');
    }
    
    // Obtener información principal del ingreso
    $stmt = $pdo->prepare("
        SELECT 
            i.*,
            t.name as team_name,
            DATE_FORMAT(i.income_date, '%Y-%m-%d') as formatted_date
        FROM incomes i
        LEFT JOIN teams t ON i.team_id = t.id
        WHERE i.id = ?
    ");
    $stmt->execute([$id]);
    $income = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$income) {
        throw new Exception('Ingreso no encontrado');
    }
    
    // Procesar contratistas desde JSON
    $contractors = [];
    if (!empty($income['contractor_ids'])) {
        $contractorIds = json_decode($income['contractor_ids'], true);
        if ($contractorIds && is_array($contractorIds)) {
            $placeholders = str_repeat('?,', count($contractorIds) - 1) . '?';
            $stmt = $pdo->prepare("
                SELECT id, name, email
                FROM contractors 
                WHERE id IN ({$placeholders})
                ORDER BY name
            ");
            $stmt->execute($contractorIds);
            $contractorData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Formatear para compatibilidad con frontend
            foreach ($contractorData as $contractor) {
                $contractors[] = [
                    'contractor_id' => $contractor['id'],
                    'name' => $contractor['name'],
                    'email' => $contractor['email']
                ];
            }
        }
    }
    
    // Obtener líneas de ingreso con campo correcto
    $stmt = $pdo->prepare("
        SELECT 
            il.*,
            jt.name as job_type_name,
            il.quantity as units,  -- Mapear quantity a units para frontend
            il.job_types_id as job_type_id  -- Mapear job_types_id a job_type_id
        FROM income_lines il
        LEFT JOIN job_types jt ON il.job_types_id = jt.id
        WHERE il.income_id = ?
        ORDER BY il.created_at
    ");
    $stmt->execute([$id]);
    $lines = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener pagos con amount y fee por separado
    $stmt = $pdo->prepare("
        SELECT 
            ip.*,
            pt.name as payment_type_name,
            ba.name as bank_account_name,
            (ip.amount - ip.fee) as net_amount
        FROM income_payments ip
        LEFT JOIN payment_types pt ON ip.payment_type_id = pt.id
        LEFT JOIN bank_accounts ba ON ip.bank_account_id = ba.id
        WHERE ip.income_id = ?
        ORDER BY ip.created_at DESC
    ");
    $stmt->execute([$id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Renombrar campos para compatibilidad con frontend
    $income['date'] = $income['formatted_date'] ?: $income['income_date'];  // Usar fecha formateada
    $income['general_note'] = $income['note']; // Mapear note a general_note
    
    echo json_encode([
        'income' => $income,
        'contractors' => $contractors,
        'lines' => $lines,
        'payments' => $payments
    ]);
}

function createIncome() {
    global $pdo;
    
    // Incluir sistema de auditoría
    require_once '../../audit_system.php';
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        throw new Exception('Datos inválidos');
    }
    
    $pdo->beginTransaction();
    
    try {
        $incomeId = generateUUID();
        
        // Validar número de factura único
        $invoiceNumber = trim($data['invoice_number'] ?? '');
        if (empty($invoiceNumber)) {
            throw new Exception('El número de factura es obligatorio');
        }
        
        // Verificar si ya existe un ingreso con ese número de factura
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM incomes WHERE LOWER(TRIM(invoice_number)) = LOWER(TRIM(?))");
        $stmt->execute([$invoiceNumber]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('El número de factura ya existe');
        }
        
        // Preparar contractor_ids como JSON
        $contractorIds = null;
        if (!empty($data['contractors'])) {
            $contractorIds = json_encode(array_map(function($contractor) {
                return $contractor['contractor_id'];
            }, $data['contractors']));
        }
        
        // Insertar ingreso principal con campos correctos
        $stmt = $pdo->prepare("
            INSERT INTO incomes (id, team_id, total_amount, invoice_number, income_date, contractor_ids, note)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        // Calcular total_amount de las líneas
        $totalAmount = 0;
        if (!empty($data['lines'])) {
            foreach ($data['lines'] as $line) {
                $quantity = floatval($line['units'] ?? 1);
                $unitPrice = floatval($line['unit_price'] ?? 0);
                $totalAmount += $quantity * $unitPrice;
            }
        }
        
        $stmt->execute([
            $incomeId,
            $data['team_id'] ?? null,
            $totalAmount,
            $data['invoice_number'] ?? null,
            $data['income_date'] ?? date('Y-m-d'),
            $contractorIds,
            $data['note'] ?? null
        ]);
        
        // Insertar líneas de ingreso con campos correctos
        if (!empty($data['lines'])) {
            $lineStmt = $pdo->prepare("
                INSERT INTO income_lines (id, income_id, job_types_id, quantity, unit_price, total_amount)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($data['lines'] as $line) {
                $quantity = floatval($line['units'] ?? 1);
                $unitPrice = floatval($line['unit_price'] ?? 0);
                $lineTotal = $quantity * $unitPrice;
                
                $lineStmt->execute([
                    generateUUID(),
                    $incomeId,
                    $line['job_type_id'] ?? null,  // Frontend envía job_type_id
                    $quantity,
                    $unitPrice,
                    $lineTotal
                ]);
            }
        }
        
        // Insertar pagos y crear transacciones bancarias automáticamente
        if (!empty($data['payments'])) {
            $paymentStmt = $pdo->prepare("
                INSERT INTO income_payments (id, income_id, payment_type_id, bank_account_id, amount, fee)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($data['payments'] as $payment) {
                $paymentId = generateUUID();
                $amount = floatval($payment['amount'] ?? 0);  // Monto bruto (usado para balance)
                $fee = floatval($payment['fee'] ?? 0);        // Fee solo para registro
                $paymentTypeId = $payment['payment_type_id'] ?? null;
                
                // Insertar payment
                $paymentStmt->execute([
                    $paymentId,
                    $incomeId,
                    $paymentTypeId,
                    $payment['bank_account_id'] ?? null,  // Puede ser null
                    $amount,  // Balance usa este monto bruto
                    $fee      // Fee solo para registro/otros módulos
                ]);
                
                // Crear transacciones bancarias automáticamente si hay payment_type_id
                if ($paymentTypeId && ($amount > 0 || $fee > 0)) {
                    $description = "Factura " . ($data['invoice_number'] ?? 'Sin número');
                    createIncomeTransactions(
                        $paymentId, 
                        $paymentTypeId, 
                        $amount, 
                        $fee, 
                        $data['income_date'] ?? date('Y-m-d'),
                        $description
                    );
                }
            }
        }
        
        $pdo->commit();
        
        // Registrar en el log de auditoría
        try {
            $audit = new AuditSystem();
            $audit->logIncomeCreated($incomeId, $data);
        } catch (Exception $e) {
            error_log("Error registrando auditoría: " . $e->getMessage());
        }
        
        // Recalcular y actualizar el status en la base de datos
        recalculateIncomeStatus($incomeId, true);
        
        echo json_encode([
            'success' => true,
            'message' => 'Ingreso creado exitosamente',
            'data' => ['id' => $incomeId]
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function updateIncome() {
    global $pdo;
    
    // Incluir sistema de auditoría
    require_once '../../audit_system.php';
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || empty($data['id'])) {
        throw new Exception('ID de ingreso requerido');
    }
    
    $incomeId = $data['id'];
    
    // Obtener datos anteriores para auditoría
    $oldData = [];
    try {
        $stmt = $pdo->prepare("SELECT * FROM incomes WHERE id = ?");
        $stmt->execute([$incomeId]);
        $oldData = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Continuar sin datos antiguos
    }
    
    $pdo->beginTransaction();
    
    try {
        // Validar número de factura único
        $invoiceNumber = trim($data['invoice_number'] ?? '');
        if (empty($invoiceNumber)) {
            throw new Exception('El número de factura es obligatorio');
        }
        
        // Verificar si ya existe otro ingreso con ese número de factura (excluyendo el actual)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM incomes WHERE LOWER(TRIM(invoice_number)) = LOWER(TRIM(?)) AND id != ?");
        $stmt->execute([$invoiceNumber, $incomeId]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('El número de factura ya existe');
        }
        
        // Preparar contractor_ids como JSON
        $contractorIds = null;
        if (!empty($data['contractors'])) {
            $contractorIds = json_encode(array_map(function($contractor) {
                return $contractor['contractor_id'];
            }, $data['contractors']));
        }
        
        // Calcular total_amount de las líneas
        $totalAmount = 0;
        if (!empty($data['lines'])) {
            foreach ($data['lines'] as $line) {
                $quantity = floatval($line['units'] ?? 1);
                $unitPrice = floatval($line['unit_price'] ?? 0);
                $totalAmount += $quantity * $unitPrice;
            }
        }
        
        // Actualizar ingreso principal con campos correctos
        $stmt = $pdo->prepare("
            UPDATE incomes 
            SET team_id = ?, total_amount = ?, invoice_number = ?, income_date = ?, contractor_ids = ?, note = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $data['team_id'] ?? null,
            $totalAmount,
            $data['invoice_number'] ?? null,
            $data['income_date'] ?? date('Y-m-d'),
            $contractorIds,
            $data['note'] ?? null,
            $incomeId
        ]);
        
        // Eliminar y recrear líneas
        $pdo->prepare("DELETE FROM income_lines WHERE income_id = ?")->execute([$incomeId]);
        
        if (!empty($data['lines'])) {
            $lineStmt = $pdo->prepare("
                INSERT INTO income_lines (id, income_id, job_types_id, quantity, unit_price, total_amount)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($data['lines'] as $line) {
                $quantity = floatval($line['units'] ?? 1);
                $unitPrice = floatval($line['unit_price'] ?? 0);
                $lineTotal = $quantity * $unitPrice;
                
                $lineStmt->execute([
                    generateUUID(),
                    $incomeId,
                    $line['job_type_id'] ?? null,  // Frontend envía job_type_id
                    $quantity,
                    $unitPrice,
                    $lineTotal
                ]);
            }
        }
        
        // Eliminar transacciones existentes y recrear pagos
        deleteAllIncomeTransactions($incomeId);  // Eliminar transacciones primero
        $pdo->prepare("DELETE FROM income_payments WHERE income_id = ?")->execute([$incomeId]);
        
        if (!empty($data['payments'])) {
            $paymentStmt = $pdo->prepare("
                INSERT INTO income_payments (id, income_id, payment_type_id, bank_account_id, amount, fee)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($data['payments'] as $payment) {
                $paymentId = generateUUID();
                $amount = floatval($payment['amount'] ?? 0);  // Monto bruto (usado para balance)
                $fee = floatval($payment['fee'] ?? 0);        // Fee solo para registro
                $paymentTypeId = $payment['payment_type_id'] ?? null;
                
                // Insertar payment
                $paymentStmt->execute([
                    $paymentId,
                    $incomeId,
                    $paymentTypeId,
                    $payment['bank_account_id'] ?? null,  // Puede ser null
                    $amount,  // Balance usa este monto bruto
                    $fee      // Fee solo para registro/otros módulos
                ]);
                
                // Crear transacciones bancarias automáticamente si hay payment_type_id
                if ($paymentTypeId && ($amount > 0 || $fee > 0)) {
                    $description = "Factura " . ($data['invoice_number'] ?? 'Sin número');
                    createIncomeTransactions(
                        $paymentId, 
                        $paymentTypeId, 
                        $amount, 
                        $fee, 
                        $data['income_date'] ?? date('Y-m-d'),
                        $description
                    );
                }
            }
        }
        
        $pdo->commit();
        
        // Registrar en el log de auditoría
        try {
            $audit = new AuditSystem();
            $audit->logIncomeUpdated($incomeId, $oldData, $data);
        } catch (Exception $e) {
            error_log("Error registrando auditoría: " . $e->getMessage());
        }
        
        // Recalcular y actualizar el status en la base de datos
        recalculateIncomeStatus($incomeId, true);
        
        echo json_encode([
            'success' => true,
            'message' => 'Ingreso actualizado exitosamente'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function deleteIncome($id) {
    global $pdo;
    
    // Incluir sistema de auditoría
    require_once '../../audit_system.php';
    
    if (empty($id)) {
        throw new Exception('ID de ingreso requerido');
    }
    
    // Obtener datos antes de eliminar para auditoría
    $deletedData = [];
    try {
        $stmt = $pdo->prepare("SELECT * FROM incomes WHERE id = ?");
        $stmt->execute([$id]);
        $deletedData = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Continuar sin datos
    }
    
    $pdo->beginTransaction();
    
    try {
        // Eliminar todas las transacciones bancarias relacionadas al income
        deleteAllIncomeTransactions($id);
        
        // Eliminar el income (esto eliminará automáticamente payments y lines por CASCADE)
        $stmt = $pdo->prepare("DELETE FROM incomes WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Ingreso no encontrado');
        }
        
        $pdo->commit();
        
        // Registrar en el log de auditoría
        try {
            $audit = new AuditSystem();
            $audit->logIncomeDeleted($id, $deletedData);
        } catch (Exception $e) {
            error_log("Error registrando auditoría: " . $e->getMessage());
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Ingreso eliminado exitosamente'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// ============================================================================
// FUNCIONES DE DATOS AUXILIARES
// ============================================================================

function getTeams() {
    global $pdo;
    
    $stmt = $pdo->query("SELECT id, name, description FROM teams WHERE status = 'active' ORDER BY name");
    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($teams);
}

function getContractors() {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT id, name, email, phone FROM contractors WHERE status = 'active' AND type = 'Tecnico' ORDER BY name");
    $stmt->execute();
    $contractors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($contractors);
}

function getJobTypes() {
    global $pdo;
    
    $stmt = $pdo->query("SELECT id, name, pay_as_contractor, pay_as_sub_contractor FROM job_types WHERE status = 'active' ORDER BY name");
    $jobTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($jobTypes);
}

function getPaymentTypes() {
    global $pdo;
    
    // Solo obtener tipos de pago activos
    $stmt = $pdo->query("SELECT id, name, description FROM payment_types WHERE status = 'active' ORDER BY name");
    $paymentTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($paymentTypes);
}

// ============================================================================
// FUNCIONES ESPECÍFICAS
// ============================================================================

function generateInvoiceNumber() {
    global $pdo;
    
    $year = date('Y');
    
    // Obtener el último número de factura del año
    $stmt = $pdo->prepare("
        SELECT invoice_number 
        FROM incomes 
        WHERE invoice_number LIKE ? 
        ORDER BY invoice_number DESC 
        LIMIT 1
    ");
    $stmt->execute(["INV-{$year}-%"]);
    $lastInvoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($lastInvoice) {
        // Extraer el número secuencial
        preg_match('/INV-\d{4}-(\d+)/', $lastInvoice['invoice_number'], $matches);
        $nextNumber = isset($matches[1]) ? (int)$matches[1] + 1 : 1;
    } else {
        $nextNumber = 1;
    }
    
    $invoiceNumber = sprintf("INV-%s-%03d", $year, $nextNumber);
    
    echo json_encode([
        'success' => true,
        'data' => ['invoice_number' => $invoiceNumber]
    ]);
}

function getIncomeStats() {
    global $pdo;
    
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_incomes,
            COALESCE(SUM(total_amount), 0) as total_income_amount,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
            COUNT(CASE WHEN status = 'paid' THEN 1 END) as paid_count,
            COUNT(CASE WHEN status = 'overpaid' THEN 1 END) as overpaid_count
        FROM incomes
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener totales calculados de líneas y pagos
    $detailsStmt = $pdo->query("
        SELECT 
            COALESCE(SUM(il.total_amount), 0) as total_income_lines,
            COALESCE(SUM(ip.amount), 0) as total_payments_amount,
            COALESCE(SUM(ip.fee), 0) as total_fees
        FROM incomes i
        LEFT JOIN income_lines il ON i.id = il.income_id
        LEFT JOIN income_payments ip ON i.id = ip.income_id
    ");
    $details = $detailsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Combinar estadísticas
    $combinedStats = array_merge($stats, $details);
    $combinedStats['total_balance'] = $details['total_income_lines'] - $details['total_payments_amount'];
    
    echo json_encode([
        'success' => true,
        'data' => $combinedStats
    ]);
}

// ============================================================================
// FUNCIÓN AUXILIAR
// ============================================================================

function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// ============================================================================
// FUNCIONES PARA TRANSACCIONES BANCARIAS AUTOMÁTICAS
// ============================================================================

/**
 * Crear transacciones bancarias cuando se registra un income payment
 * @param string $paymentId ID del income_payment
 * @param string $paymentTypeId ID del payment_type
 * @param float $amount Monto del pago (positivo)
 * @param float $fee Fee del pago (positivo)
 * @param string $incomeDate Fecha del ingreso
 * @param string $description Descripción base para las transacciones
 */
function createIncomeTransactions($paymentId, $paymentTypeId, $amount, $fee, $incomeDate, $description) {
    global $pdo;
    
    // Obtener la cuenta bancaria asociada al payment_type
    $stmt = $pdo->prepare("
        SELECT ba.id, ba.name, ba.balance 
        FROM payment_types pt 
        JOIN bank_accounts ba ON pt.bank_account_id = ba.id 
        WHERE pt.id = ?
    ");
    $stmt->execute([$paymentTypeId]);
    $bankAccount = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$bankAccount) {
        throw new Exception('No se encontró cuenta bancaria asociada al tipo de pago');
    }
    
    $bankAccountId = $bankAccount['id'];
    $currentBalance = floatval($bankAccount['balance']);
    
    // 1. Crear transacción POSITIVA para el monto del pago (payment_income)
    if ($amount > 0) {
        $newBalance = $currentBalance + $amount;
        
        $transactionId = generateUUID();
        $stmt = $pdo->prepare("
            INSERT INTO transactions (id, bank_account_id, income_payment_id, type, amount, balance_after, description, transaction_date, created_at) 
            VALUES (?, ?, ?, 'payment_income', ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $transactionId,
            $bankAccountId,
            $paymentId,
            $amount, // Positivo
            $newBalance,
            "Ingreso: $description",
            $incomeDate
        ]);
        
        // Actualizar balance de la cuenta
        $stmt = $pdo->prepare("UPDATE bank_accounts SET balance = ? WHERE id = ?");
        $stmt->execute([$newBalance, $bankAccountId]);
        
        $currentBalance = $newBalance;
    }
    
    // 2. Crear transacción NEGATIVA para el fee (payment_fee)
    if ($fee > 0) {
        $newBalance = $currentBalance - $fee;
        
        $transactionId = generateUUID();
        $stmt = $pdo->prepare("
            INSERT INTO transactions (id, bank_account_id, income_payment_id, type, amount, balance_after, description, transaction_date, created_at) 
            VALUES (?, ?, ?, 'payment_fee', ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $transactionId,
            $bankAccountId,
            $paymentId,
            -$fee, // Negativo
            $newBalance,
            "Fee: $description",
            $incomeDate
        ]);
        
        // Actualizar balance de la cuenta
        $stmt = $pdo->prepare("UPDATE bank_accounts SET balance = ? WHERE id = ?");
        $stmt->execute([$newBalance, $bankAccountId]);
    }
}

/**
 * Eliminar transacciones bancarias asociadas a un income payment
 * @param string $paymentId ID del income_payment
 */
function deleteIncomeTransactions($paymentId) {
    global $pdo;
    
    // Obtener todas las transacciones asociadas al payment
    $stmt = $pdo->prepare("
        SELECT t.*, ba.balance as current_balance 
        FROM transactions t
        JOIN bank_accounts ba ON t.bank_account_id = ba.id
        WHERE t.income_payment_id = ? 
        ORDER BY t.created_at DESC
    ");
    $stmt->execute([$paymentId]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Revertir cada transacción (en orden inverso)
    foreach ($transactions as $transaction) {
        $bankAccountId = $transaction['bank_account_id'];
        $transactionAmount = floatval($transaction['amount']);
        $currentBalance = floatval($transaction['current_balance']);
        
        // Revertir el balance (suma si era negativo, resta si era positivo)
        $newBalance = $currentBalance - $transactionAmount;
        
        // Actualizar balance de la cuenta
        $stmt = $pdo->prepare("UPDATE bank_accounts SET balance = ? WHERE id = ?");
        $stmt->execute([$newBalance, $bankAccountId]);
    }
    
    // Eliminar las transacciones
    $stmt = $pdo->prepare("DELETE FROM transactions WHERE income_payment_id = ?");
    $stmt->execute([$paymentId]);
}

/**
 * Eliminar todas las transacciones asociadas a un income completo
 * @param string $incomeId ID del income
 */
function deleteAllIncomeTransactions($incomeId) {
    global $pdo;
    
    // Obtener todos los payment IDs del income
    $stmt = $pdo->prepare("SELECT id FROM income_payments WHERE income_id = ?");
    $stmt->execute([$incomeId]);
    $paymentIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Eliminar transacciones de cada payment
    foreach ($paymentIds as $paymentId) {
        deleteIncomeTransactions($paymentId);
    }
}

// ============================================================================
// FUNCIONES PARA MANEJAR STATUS
// ============================================================================

/**
 * Recalcular el status de un ingreso específico
 * @param string $incomeId ID del ingreso
 */
function recalculateIncomeStatus($incomeId, $silent = false) {
    global $pdo;
    
    if (empty($incomeId)) {
        if (!$silent) throw new Exception('ID de ingreso requerido');
        else return;
    }
    
    try {
        // Verificar que el ingreso existe
        $checkStmt = $pdo->prepare("SELECT id FROM incomes WHERE id = ?");
        $checkStmt->execute([$incomeId]);
        if (!$checkStmt->fetch()) {
            if (!$silent) throw new Exception('Ingreso no encontrado');
            else return;
        }
        
        // Llamar al procedimiento almacenado para actualizar el status
        $stmt = $pdo->prepare("CALL UpdateIncomeStatus(?)");
        $stmt->execute([$incomeId]);
        
        if (!$silent) {
            // Obtener el nuevo status
            $statusStmt = $pdo->prepare("SELECT status FROM incomes WHERE id = ?");
            $statusStmt->execute([$incomeId]);
            $result = $statusStmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'message' => 'Status actualizado correctamente',
                'new_status' => $result['status']
            ]);
        }
        
    } catch (Exception $e) {
        if (!$silent) throw new Exception('Error al recalcular status: ' . $e->getMessage());
    }
}

/**
 * Recalcular el status de todos los ingresos
 */
function recalculateAllIncomeStatus() {
    global $pdo;
    
    try {
        // Llamar al procedimiento almacenado para recalcular todos los status
        $stmt = $pdo->prepare("CALL RecalculateAllIncomeStatus()");
        $stmt->execute();
        
        // Obtener estadísticas del resultado
        $statsStmt = $pdo->prepare("
            SELECT 
                status,
                COUNT(*) as count
            FROM incomes 
            GROUP BY status
            ORDER BY status
        ");
        $statsStmt->execute();
        $stats = $statsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener total de registros
        $totalStmt = $pdo->prepare("SELECT COUNT(*) as total FROM incomes");
        $totalStmt->execute();
        $total = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        echo json_encode([
            'success' => true,
            'message' => "Todos los status han sido recalculados. Total: {$total} registros",
            'total_records' => $total,
            'statistics' => $stats
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Error al recalcular todos los status: ' . $e->getMessage());
    }
}

/**
 * Verificar si un número de factura ya existe
 */
function checkInvoiceNumber() {
    global $pdo;
    
    $invoiceNumber = trim($_GET['invoice_number'] ?? '');
    $excludeId = $_GET['exclude_id'] ?? '';
    
    if (empty($invoiceNumber)) {
        echo json_encode(['exists' => false, 'message' => 'Número de factura requerido']);
        return;
    }
    
    try {
        if (!empty($excludeId)) {
            // Para edición - excluir el ID actual
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM incomes WHERE LOWER(TRIM(invoice_number)) = LOWER(TRIM(?)) AND id != ?");
            $stmt->execute([$invoiceNumber, $excludeId]);
        } else {
            // Para creación
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM incomes WHERE LOWER(TRIM(invoice_number)) = LOWER(TRIM(?))");
            $stmt->execute([$invoiceNumber]);
        }
        
        $exists = $stmt->fetchColumn() > 0;
        
        echo json_encode([
            'exists' => $exists,
            'message' => $exists ? 'El número de factura ya existe' : 'Número de factura disponible'
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Error verificando número de factura: ' . $e->getMessage());
    }
}
?> 
