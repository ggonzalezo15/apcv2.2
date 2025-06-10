<?php
require_once '../../config.php';

header('Content-Type: application/json');

$pdo = getConnection();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'getAllContractorPayments':
        getAllContractorPayments();
        break;
    case 'getContractorPaymentById':
        getContractorPaymentById($_GET['id'] ?? '');
        break;
    case 'getPaymentsByContractor':
        getPaymentsByContractor($_GET['contractor_id'] ?? '');
        break;
    case 'createContractorPayment':
        createContractorPayment();
        break;
    case 'updateContractorPayment':
        updateContractorPayment($_GET['id'] ?? '');
        break;
    case 'deleteContractorPayment':
        deleteContractorPayment($_GET['id'] ?? '');
        break;
    case 'getBankAccountsForPayments':
        getBankAccountsForPayments();
        break;
    case 'getPaymentStatistics':
        $contractorId = $_GET['contractor_id'] ?? '';
        if (empty($contractorId)) {
            echo json_encode(["success" => false, "error" => "ID de contratista requerido"]);
            exit;
        }
        getPaymentStatistics($contractorId);
        break;
    default:
        echo json_encode(['error' => 'Acción no válida']);
}

function getAllContractorPayments() {
    global $pdo;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 0;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $sort = $_GET['sort'] ?? 'created_at';
    $dir = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
    $allowedSort = ['payment_date', 'amount', 'contractor_name', 'bank_account_name', 'created_at', 'updated_at', 'id'];
    if (!in_array($sort, $allowedSort)) $sort = 'created_at';
    
    $sql = "
        SELECT 
            cp.*,
            c.name as contractor_name,
            c.email as contractor_email,
            ba.name as bank_account_name,
            ba.bank_name,
            ba.account_number
        FROM contractor_payments cp
        INNER JOIN contractors c ON cp.contractor_id = c.id
        INNER JOIN bank_accounts ba ON cp.bank_account_id = ba.id
        ORDER BY $sort $dir, cp.id DESC
    ";
    
    if ($limit > 0) {
        $sql .= " LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        $stmt = $pdo->query($sql);
    }
    
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total = $pdo->query("SELECT COUNT(*) FROM contractor_payments")->fetchColumn();
    echo json_encode(['data' => $payments, 'total' => (int)$total]);
}

function getContractorPaymentById($id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT 
            cp.*,
            c.name as contractor_name,
            c.email as contractor_email,
            ba.name as bank_account_name,
            ba.bank_name,
            ba.account_number
        FROM contractor_payments cp
        INNER JOIN contractors c ON cp.contractor_id = c.id
        INNER JOIN bank_accounts ba ON cp.bank_account_id = ba.id
        WHERE cp.id = ?
    ");
    $stmt->execute([$id]);
    echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
}

function getPaymentsByContractor($contractorId) {
    global $pdo;
    
    // Parámetros para paginación y sorting
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $pageSize = isset($_GET['pageSize']) ? max(1, intval($_GET['pageSize'])) : 10;
    $sortField = $_GET['sortField'] ?? 'payment_date';
    $sortDir = strtoupper($_GET['sortDir'] ?? 'DESC');
    
    // Validar dirección de sorting
    if (!in_array($sortDir, ['ASC', 'DESC'])) {
        $sortDir = 'DESC';
    }
    
    // Mapear campos de sorting a campos de base de datos
    $sortFieldMap = [
        'payment_date' => 'cp.payment_date',
        'amount' => 'cp.amount',
        'account_name' => 'ba.name',
        'reference_number' => 'cp.reference_number'
    ];
    
    $dbSortField = $sortFieldMap[$sortField] ?? 'cp.payment_date';
    
    // Construir WHERE clause con filtros
    $whereClause = "WHERE cp.contractor_id = ?";
    $params = [$contractorId];
    
    // Filtros de fecha
    if (!empty($_GET['dateFrom'])) {
        $whereClause .= " AND cp.payment_date >= ?";
        $params[] = $_GET['dateFrom'];
    }
    
    if (!empty($_GET['dateTo'])) {
        $whereClause .= " AND cp.payment_date <= ?";
        $params[] = $_GET['dateTo'];
    }
    
    // Obtener total de registros para paginación
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM contractor_payments cp
        INNER JOIN contractors c ON cp.contractor_id = c.id
        INNER JOIN bank_accounts ba ON cp.bank_account_id = ba.id
        {$whereClause}
    ");
    $countStmt->execute($params);
    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Calcular offset para paginación
    $offset = ($page - 1) * $pageSize;
    
    // Consulta principal con paginación y sorting
    $sql = "
        SELECT 
            cp.*,
            c.name as contractor_name,
            ba.name as bank_account_name,
            ba.name as account_name,
            ba.bank_name,
            ba.account_number
        FROM contractor_payments cp
        INNER JOIN contractors c ON cp.contractor_id = c.id
        INNER JOIN bank_accounts ba ON cp.bank_account_id = ba.id
        {$whereClause}
        ORDER BY {$dbSortField} {$sortDir}, cp.created_at DESC
        LIMIT {$pageSize} OFFSET {$offset}
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Devolver datos con información de paginación
    echo json_encode([
        'success' => true,
        'data' => $payments,
        'total' => intval($totalCount),
        'page' => $page,
        'pageSize' => $pageSize,
        'totalPages' => ceil($totalCount / $pageSize)
    ]);
}

function getBankAccountsForPayments() {
    global $pdo;
    // Solo obtener cuentas que NO sean de crédito
    $stmt = $pdo->query("
        SELECT id, name, bank_name, account_number, account_type, balance
        FROM bank_accounts 
        WHERE account_type != 'credito'
        ORDER BY name
    ");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function createContractorPayment() {
    global $pdo;
    
    try {
        $data = json_decode(file_get_contents("php://input"), true);
        
        // Validar datos requeridos
        if (empty($data['contractor_id']) || empty($data['bank_account_id']) || 
            empty($data['amount']) || empty($data['payment_date'])) {
            throw new Exception('Faltan datos requeridos');
        }
        
        $contractorId = $data['contractor_id'];
        $bankAccountId = $data['bank_account_id'];
        $amount = floatval($data['amount']);
        $paymentDate = $data['payment_date'];
        $notes = $data['notes'] ?? '';
        $referenceNumber = $data['reference_number'] ?? '';
        $createdBy = $data['created_by'] ?? '';
        
        if ($amount <= 0) {
            throw new Exception('El monto debe ser mayor a 0');
        }
        
        // Verificar que el contratista existe
        $stmt = $pdo->prepare("SELECT name FROM contractors WHERE id = ?");
        $stmt->execute([$contractorId]);
        $contractor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$contractor) {
            throw new Exception('Contratista no encontrado');
        }
        
        // Verificar que la cuenta bancaria existe y no es de crédito
        $stmt = $pdo->prepare("SELECT * FROM bank_accounts WHERE id = ? AND account_type != 'credito'");
        $stmt->execute([$bankAccountId]);
        $bankAccount = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$bankAccount) {
            throw new Exception('Cuenta bancaria no válida o no permitida');
        }
        
        // Verificar fondos suficientes
        if (floatval($bankAccount['balance']) < $amount) {
            throw new Exception('Fondos insuficientes en la cuenta seleccionada');
        }
        
        // Iniciar transacción
        $pdo->beginTransaction();
        
        // Crear el pago
        $paymentId = generateUUID();
        $stmt = $pdo->prepare("
            INSERT INTO contractor_payments 
            (id, contractor_id, bank_account_id, amount, payment_date, notes, reference_number, created_by, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $paymentId,
            $contractorId,
            $bankAccountId,
            $amount,
            $paymentDate,
            $notes,
            $referenceNumber,
            $createdBy
        ]);
        
        // Actualizar balance de la cuenta bancaria
        $newBalance = floatval($bankAccount['balance']) - $amount;
        $stmt = $pdo->prepare("UPDATE bank_accounts SET balance = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newBalance, $bankAccountId]);
        
        // Registrar transacción bancaria
        $transactionId = generateUUID();
        $description = "Pago a contratista: {$contractor['name']}";
        if (!empty($notes)) {
            $description .= " - {$notes}";
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO transactions 
            (id, bank_account_id, type, amount, balance_after, description, transaction_date, created_at) 
            VALUES (?, ?, 'expense', ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $transactionId,
            $bankAccountId,
            -$amount, // Negativo porque es un gasto
            $newBalance,
            $description,
            $paymentDate
        ]);
        
        $pdo->commit();
        
        echo json_encode([
            "success" => true,
            "message" => "Pago a contratista registrado exitosamente",
            "id" => $paymentId,
            "new_balance" => $newBalance
        ]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
}

function updateContractorPayment($id) {
    global $pdo;
    
    try {
        $data = json_decode(file_get_contents("php://input"), true);
        
        // Obtener el pago existente
        $stmt = $pdo->prepare("
            SELECT cp.*, ba.balance as current_balance 
            FROM contractor_payments cp
            INNER JOIN bank_accounts ba ON cp.bank_account_id = ba.id
            WHERE cp.id = ?
        ");
        $stmt->execute([$id]);
        $existingPayment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$existingPayment) {
            throw new Exception('Pago no encontrado');
        }
        
        // Iniciar transacción
        $pdo->beginTransaction();
        
        // Revertir transacción anterior (devolver dinero a la cuenta)
        $revertedBalance = floatval($existingPayment['current_balance']) + floatval($existingPayment['amount']);
        $stmt = $pdo->prepare("UPDATE bank_accounts SET balance = ? WHERE id = ?");
        $stmt->execute([$revertedBalance, $existingPayment['bank_account_id']]);
        
        // Eliminar transacción anterior
        $stmt = $pdo->prepare("DELETE FROM transactions WHERE bank_account_id = ? AND amount = ? AND transaction_date = ?");
        $stmt->execute([
            $existingPayment['bank_account_id'], 
            -floatval($existingPayment['amount']), 
            $existingPayment['payment_date']
        ]);
        
        // Crear nueva transacción con los datos actualizados
        $newAmount = floatval($data['amount']);
        $newBankAccountId = $data['bank_account_id'];
        $newPaymentDate = $data['payment_date'];
        $newNotes = $data['notes'] ?? '';
        $newReferenceNumber = $data['reference_number'] ?? '';
        
        // Obtener información de la nueva cuenta
        $stmt = $pdo->prepare("SELECT * FROM bank_accounts WHERE id = ? AND account_type != 'credito'");
        $stmt->execute([$newBankAccountId]);
        $newBankAccount = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$newBankAccount) {
            throw new Exception('Cuenta bancaria no válida');
        }
        
        // Si es la misma cuenta, usar el balance revertido, si no, usar el balance actual
        $availableBalance = ($newBankAccountId === $existingPayment['bank_account_id']) 
            ? $revertedBalance 
            : floatval($newBankAccount['balance']);
            
        if ($availableBalance < $newAmount) {
            throw new Exception('Fondos insuficientes');
        }
        
        // Actualizar el pago
        $stmt = $pdo->prepare("
            UPDATE contractor_payments 
            SET bank_account_id = ?, amount = ?, payment_date = ?, notes = ?, reference_number = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $newBankAccountId,
            $newAmount,
            $newPaymentDate,
            $newNotes,
            $newReferenceNumber,
            $id
        ]);
        
        // Actualizar balance de la cuenta
        $finalBalance = $availableBalance - $newAmount;
        $stmt = $pdo->prepare("UPDATE bank_accounts SET balance = ? WHERE id = ?");
        $stmt->execute([$finalBalance, $newBankAccountId]);
        
        // Crear nueva transacción
        $transactionId = generateUUID();
        $stmt = $pdo->prepare("
            INSERT INTO transactions 
            (id, bank_account_id, type, amount, balance_after, description, transaction_date, created_at) 
            VALUES (?, ?, 'expense', ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $transactionId,
            $newBankAccountId,
            -$newAmount,
            $finalBalance,
            "Pago a contratista (actualizado)" . (!empty($newNotes) ? " - {$newNotes}" : ""),
            $newPaymentDate
        ]);
        
        $pdo->commit();
        
        echo json_encode([
            "success" => true,
            "message" => "Pago actualizado exitosamente",
            "new_balance" => $finalBalance
        ]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
}

function deleteContractorPayment($id) {
    global $pdo;
    
    try {
        // Obtener el pago para revertir la transacción
        $stmt = $pdo->prepare("
            SELECT cp.*, ba.balance as current_balance 
            FROM contractor_payments cp
            INNER JOIN bank_accounts ba ON cp.bank_account_id = ba.id
            WHERE cp.id = ?
        ");
        $stmt->execute([$id]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$payment) {
            throw new Exception('Pago no encontrado');
        }
        
        // Iniciar transacción
        $pdo->beginTransaction();
        
        // Revertir el dinero a la cuenta
        $revertedBalance = floatval($payment['current_balance']) + floatval($payment['amount']);
        $stmt = $pdo->prepare("UPDATE bank_accounts SET balance = ? WHERE id = ?");
        $stmt->execute([$revertedBalance, $payment['bank_account_id']]);
        
        // Eliminar transacción bancaria relacionada
        $stmt = $pdo->prepare("DELETE FROM transactions WHERE bank_account_id = ? AND amount = ? AND transaction_date = ?");
        $stmt->execute([
            $payment['bank_account_id'], 
            -floatval($payment['amount']), 
            $payment['payment_date']
        ]);
        
        // Eliminar el pago
        $stmt = $pdo->prepare("DELETE FROM contractor_payments WHERE id = ?");
        $stmt->execute([$id]);
        
        $pdo->commit();
        
        echo json_encode([
            "success" => true,
            "message" => "Pago eliminado y transacción revertida exitosamente",
            "reverted_balance" => $revertedBalance
        ]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
}

function getPaymentStatistics($contractorId) {
    global $pdo;
    
    // Construir WHERE clause con filtros
    $whereClause = "WHERE cp.contractor_id = ?";
    $params = [$contractorId];
    
    // Filtros de fecha
    if (!empty($_GET['dateFrom'])) {
        $whereClause .= " AND cp.payment_date >= ?";
        $params[] = $_GET['dateFrom'];
    }
    
    if (!empty($_GET['dateTo'])) {
        $whereClause .= " AND cp.payment_date <= ?";
        $params[] = $_GET['dateTo'];
    }
    
    // Consulta para estadísticas
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(cp.amount), 0) as totalPaid,
            COALESCE(AVG(cp.amount), 0) as averagePayment,
            COUNT(cp.id) as totalPayments,
            MAX(cp.payment_date) as lastPaymentDate
        FROM contractor_payments cp
        {$whereClause}
    ");
    
    $stmt->execute($params);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Formatear la fecha del último pago
    if ($stats['lastPaymentDate']) {
        $date = new DateTime($stats['lastPaymentDate']);
        $stats['lastPaymentDate'] = $date->format('Y-m-d');
    } else {
        $stats['lastPaymentDate'] = null;
    }
    
    echo json_encode($stats);
}

function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}
?> 
