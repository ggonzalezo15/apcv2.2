<?php
// Configurar manejo de errores
error_reporting(0);
ini_set('display_errors', 0);

// Buffer de salida para capturar cualquier output no deseado
ob_start();

require_once dirname(__DIR__, 2) . '/config.php';

// Limpiar buffer
ob_clean();

header('Content-Type: application/json');

$pdo = getConnection();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'getAllBankAccounts':
        getAllBankAccounts();
        break;
    case 'getBankAccountById':
        getBankAccountById($_GET['id'] ?? '');
        break;
    case 'getTransactionsByAccount':
        getTransactionsByAccount($_GET['id'] ?? '');
        break;
    case 'createBankAccount':
        createBankAccount();
        break;
    case 'updateBankAccount':
        updateBankAccount($_GET['id'] ?? '');
        break;
    case 'deleteBankAccount':
        deleteBankAccount($_GET['id'] ?? '');
        break;
    case 'transfer':
        performTransfer();
        break;
    case 'creditPayment':
        performCreditPayment();
        break;
    case 'toggleAccountStatus':
        toggleAccountStatus($_GET['id'] ?? '');
        break;
    default:
        echo json_encode(['error' => 'Acción no válida']);
}

function getAllBankAccounts() {
    global $pdo;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 0;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $sort = $_GET['sort'] ?? 'created_at';
    $dir = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
    $allowedSort = ['name', 'bank_name', 'account_number', 'account_type', 'balance', 'created_at', 'updated_at', 'id'];
    if (!in_array($sort, $allowedSort)) $sort = 'created_at';
    $sql = "SELECT * FROM bank_accounts ORDER BY $sort $dir, id DESC";
    if ($limit > 0) {
        $sql .= " LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        $stmt = $pdo->query($sql);
    }
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total = $pdo->query("SELECT COUNT(*) FROM bank_accounts")->fetchColumn();
    echo json_encode(['data' => $accounts, 'total' => (int)$total]);
}

function getBankAccountById($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM bank_accounts WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
}

function createBankAccount() {
    global $pdo;
    
    try {
        $data = json_decode(file_get_contents("php://input"), true);
        
        // Validar datos requeridos
        $name = trim($data['name'] ?? '');
        if (empty($name)) {
            throw new Exception('El nombre de la cuenta es obligatorio');
        }
        
        // Verificar si ya existe una cuenta con ese nombre (case-insensitive y trimmed)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bank_accounts WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))");
        $stmt->execute([$name]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('El nombre de cuenta ya existe');
        }
        
        $uuid = uniqid('', true);
        $balance = floatval($data['balance'] ?? 0.00);
        
        // Iniciar transacción
        $pdo->beginTransaction();
        
        // Crear cuenta bancaria
        $active = isset($data['active']) ? (int)$data['active'] : 1; // Por defecto activa
        $stmt = $pdo->prepare("INSERT INTO bank_accounts (id, name, bank_name, account_number, account_type, balance, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([
            $uuid,
            $name,
            $data['bank_name'],
            $data['account_number'],
            $data['account_type'],
            $balance,
            $active
        ]);
        
        // Registrar transacción de balance inicial si hay balance diferente de 0
        if ($balance != 0) {
            $tablesQuery = $pdo->query("SHOW TABLES LIKE 'transactions'");
            if ($tablesQuery->rowCount() > 0) {
                $transactionId = uniqid('', true);
                
                // Determinar tipo de transacción y descripción según el tipo de cuenta y balance
                if ($data['account_type'] === 'credito' && $balance < 0) {
                    // Para cuentas de crédito con balance negativo (deuda existente)
                    $transactionType = 'balance_inicial_credito';
                    $description = 'Balance inicial de deuda existente en cuenta de crédito';
                } elseif ($balance > 0) {
                    // Para cualquier cuenta con balance positivo
                    $transactionType = 'deposito_inicial';
                    $description = 'Depósito inicial al crear la cuenta';
                } else {
                    // Para cuentas de crédito con balance positivo (límite disponible)
                    $transactionType = 'limite_inicial_credito';
                    $description = 'Límite de crédito inicial disponible';
                }
                
                $stmt = $pdo->prepare("INSERT INTO transactions (id, bank_account_id, type, amount, balance_after, description, transaction_date, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
                $stmt->execute([$transactionId, $uuid, $transactionType, $balance, $balance, $description]);
            }
        }
        
        $pdo->commit();
        echo json_encode(["message" => "Bank account created", "id" => $uuid]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        echo json_encode(["error" => "Error al crear la cuenta: " . $e->getMessage()]);
    }
}

function updateBankAccount($id) {
    global $pdo;
    
    try {
        $data = json_decode(file_get_contents("php://input"), true);
        
        // Validar datos requeridos
        $name = trim($data['name'] ?? '');
        if (empty($name)) {
            throw new Exception('El nombre de la cuenta es obligatorio');
        }
        
        // Verificar si ya existe otra cuenta con ese nombre (case-insensitive y trimmed)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bank_accounts WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) AND id != ?");
        $stmt->execute([$name, $id]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('El nombre de cuenta ya existe');
        }
        
        // Actualizar los campos editables: nombre, banco, número de cuenta y estado
        // NO se actualiza account_type ni balance
        $active = isset($data['active']) ? (int)$data['active'] : 1;
        $stmt = $pdo->prepare("UPDATE bank_accounts SET name = ?, bank_name = ?, account_number = ?, active = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([
            $name,
            $data['bank_name'],
            $data['account_number'],
            $active,
            $id
        ]);
        echo json_encode(["message" => "Bank account updated"]);
        
    } catch (Exception $e) {
        echo json_encode(["error" => $e->getMessage()]);
    }
}

function deleteBankAccount($id) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM bank_accounts WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(["message" => "Bank account deleted"]);
}

function getTransactionsByAccount($id) {
    global $pdo;
    
    try {
        // Validar el ID de la cuenta
        if (empty($id)) {
            echo json_encode(['error' => 'ID de cuenta requerido']);
            return;
        }
        
        // Parámetros de paginación
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = ($page - 1) * $limit;
        
        // Parámetros de ordenamiento
        $sort = $_GET['sort'] ?? 'transaction_date';
        $dir = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
        $allowedSort = ['transaction_date', 'type', 'amount', 'description', 'id'];
        if (!in_array($sort, $allowedSort)) $sort = 'transaction_date';
        
        // Validar parámetros
        if ($page < 1) $page = 1;
        if ($limit < 1 || $limit > 100) $limit = 10;
        
        // Verificar si la tabla transactions existe
        $tablesQuery = $pdo->query("SHOW TABLES LIKE 'transactions'");
        if ($tablesQuery->rowCount() == 0) {
            echo json_encode([
                'data' => [],
                'total' => 0,
                'page' => $page,
                'limit' => $limit,
                'pages' => 0
            ]);
            return;
        }
        
        // Contar el total de transacciones
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM transactions WHERE bank_account_id = ?");
        $stmt->execute([$id]);
        $total = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Si no hay transacciones, devolver respuesta vacía
        if ($total == 0) {
            echo json_encode([
                'data' => [],
                'total' => 0,
                'page' => $page,
                'limit' => $limit,
                'pages' => 0
            ]);
            return;
        }
        
        // Obtener transacciones paginadas - usar LIMIT sin parámetros preparados
        $sql = "SELECT * FROM transactions WHERE bank_account_id = ? ORDER BY $sort $dir, id DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'data' => $transactions,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ]);
        
    } catch (Exception $e) {
        error_log("Error in getTransactionsByAccount: " . $e->getMessage());
        echo json_encode([
            'error' => 'Error al cargar transacciones: ' . $e->getMessage(),
            'data' => [],
            'total' => 0,
            'page' => 1,
            'limit' => 10,
            'pages' => 0
        ]);
    }
}

function performTransfer() {
    global $pdo;
    
    try {
        $data = json_decode(file_get_contents("php://input"), true);
        
        $fromAccountId = $data['from_account_id'];
        $toAccountId = $data['to_account_id'];
        $amount = floatval($data['amount']);
        $description = $data['description'] ?? 'Transferencia entre cuentas';
        
        if ($amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'El monto debe ser mayor a 0']);
            return;
        }
        
        // Verificar que ambas cuentas existen, no son de crédito y están activas
        $stmt = $pdo->prepare("SELECT * FROM bank_accounts WHERE id IN (?, ?) AND account_type != 'credito' AND active = 1");
        $stmt->execute([$fromAccountId, $toAccountId]);
        $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($accounts) !== 2) {
            echo json_encode(['success' => false, 'message' => 'Las cuentas seleccionadas no son válidas para transferencia']);
            return;
        }
        
        // Encontrar cuenta origen y destino
        $fromAccount = null;
        $toAccount = null;
        foreach ($accounts as $account) {
            if ($account['id'] === $fromAccountId) {
                $fromAccount = $account;
            } else {
                $toAccount = $account;
            }
        }
        
        // Verificar fondos suficientes
        if (floatval($fromAccount['balance']) < $amount) {
            echo json_encode(['success' => false, 'message' => 'Fondos insuficientes en la cuenta origen']);
            return;
        }
        
        // Iniciar transacción
        $pdo->beginTransaction();
        
        // Restar del origen
        $newFromBalance = floatval($fromAccount['balance']) - $amount;
        $stmt = $pdo->prepare("UPDATE bank_accounts SET balance = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newFromBalance, $fromAccountId]);
        
        // Sumar al destino
        $newToBalance = floatval($toAccount['balance']) + $amount;
        $stmt = $pdo->prepare("UPDATE bank_accounts SET balance = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newToBalance, $toAccountId]);
        
        // Registrar la transacción si existe la tabla transactions
        $tablesQuery = $pdo->query("SHOW TABLES LIKE 'transactions'");
        if ($tablesQuery->rowCount() > 0) {
            // Registrar salida de cuenta origen
            $transactionId1 = uniqid('', true);
            $stmt = $pdo->prepare("INSERT INTO transactions (id, bank_account_id, type, amount, balance_after, description, transaction_date, created_at) VALUES (?, ?, 'transfer_out', ?, ?, ?, NOW(), NOW())");
            $stmt->execute([$transactionId1, $fromAccountId, -$amount, $newFromBalance, "Transferencia a {$toAccount['name']}: {$description}"]);
            
            // Registrar entrada a cuenta destino
            $transactionId2 = uniqid('', true);
            $stmt = $pdo->prepare("INSERT INTO transactions (id, bank_account_id, type, amount, balance_after, description, transaction_date, created_at) VALUES (?, ?, 'transfer_in', ?, ?, ?, NOW(), NOW())");
            $stmt->execute([$transactionId2, $toAccountId, $amount, $newToBalance, "Transferencia de {$fromAccount['name']}: {$description}"]);
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Transferencia realizada con éxito',
            'from_balance' => $newFromBalance,
            'to_balance' => $newToBalance
        ]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        echo json_encode(['success' => false, 'message' => 'Error al procesar la transferencia: ' . $e->getMessage()]);
    }
}

function performCreditPayment() {
    global $pdo;
    
    try {
        $data = json_decode(file_get_contents("php://input"), true);
        
        $creditAccountId = $data['credit_account_id'];
        $fromAccountId = $data['from_account_id'];
        $amount = floatval($data['amount']);
        $description = $data['description'] ?? 'Pago a tarjeta de crédito';
        
        if ($amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'El monto debe ser mayor a 0']);
            return;
        }
        
        // Verificar que la cuenta de crédito existe, es de tipo crédito y está activa
        $stmt = $pdo->prepare("SELECT * FROM bank_accounts WHERE id = ? AND account_type = 'credito' AND active = 1");
        $stmt->execute([$creditAccountId]);
        $creditAccount = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$creditAccount) {
            echo json_encode(['success' => false, 'message' => 'La cuenta de crédito no es válida o está inactiva']);
            return;
        }
        
        // Verificar que la cuenta origen existe, no es de crédito y está activa
        $stmt = $pdo->prepare("SELECT * FROM bank_accounts WHERE id = ? AND account_type != 'credito' AND active = 1");
        $stmt->execute([$fromAccountId]);
        $fromAccount = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$fromAccount) {
            echo json_encode(['success' => false, 'message' => 'La cuenta origen no es válida o está inactiva']);
            return;
        }
        
        // Verificar fondos suficientes en cuenta origen
        if (floatval($fromAccount['balance']) < $amount) {
            echo json_encode(['success' => false, 'message' => 'Fondos insuficientes en la cuenta origen']);
            return;
        }
        
        // Iniciar transacción
        $pdo->beginTransaction();
        
        // Restar de la cuenta origen
        $newFromBalance = floatval($fromAccount['balance']) - $amount;
        $stmt = $pdo->prepare("UPDATE bank_accounts SET balance = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newFromBalance, $fromAccountId]);
        
        // Para crédito: sumar al balance (reduce la deuda o aumenta el crédito disponible)
        $newCreditBalance = floatval($creditAccount['balance']) + $amount;
        $stmt = $pdo->prepare("UPDATE bank_accounts SET balance = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newCreditBalance, $creditAccountId]);
        
        // Registrar la transacción si existe la tabla transactions
        $tablesQuery = $pdo->query("SHOW TABLES LIKE 'transactions'");
        if ($tablesQuery->rowCount() > 0) {
            // Registrar salida de cuenta origen
            $transactionId1 = uniqid('', true);
            $stmt = $pdo->prepare("INSERT INTO transactions (id, bank_account_id, type, amount, balance_after, description, transaction_date, created_at) VALUES (?, ?, 'credit_payment', ?, ?, ?, NOW(), NOW())");
            $stmt->execute([$transactionId1, $fromAccountId, -$amount, $newFromBalance, "Pago a {$creditAccount['name']}: {$description}"]);
            
            // Registrar pago en cuenta de crédito
            $transactionId2 = uniqid('', true);
            $stmt = $pdo->prepare("INSERT INTO transactions (id, bank_account_id, type, amount, balance_after, description, transaction_date, created_at) VALUES (?, ?, 'payment_received', ?, ?, ?, NOW(), NOW())");
            $stmt->execute([$transactionId2, $creditAccountId, $amount, $newCreditBalance, "Pago recibido de {$fromAccount['name']}: {$description}"]);
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Pago realizado con éxito',
            'from_balance' => $newFromBalance,
            'credit_balance' => $newCreditBalance
        ]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        echo json_encode(['success' => false, 'message' => 'Error al procesar el pago: ' . $e->getMessage()]);
    }
}

function toggleAccountStatus($id) {
    global $pdo;
    
    try {
        $data = json_decode(file_get_contents("php://input"), true);
        $active = isset($data['active']) ? (int)$data['active'] : 0;
        
        // Debug: log para verificar el ID y estado
        error_log("toggleAccountStatus - ID: $id, Active: $active");
        
        // Si se está desactivando, verificar si tiene payment types asociados
        if ($active == 0) {
            // Verificar si existen payment types que usan esta cuenta
            $tablesQuery = $pdo->query("SHOW TABLES LIKE 'payment_types'");
            error_log("payment_types table exists: " . ($tablesQuery->rowCount() > 0 ? 'YES' : 'NO'));
            
            if ($tablesQuery->rowCount() > 0) {
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM payment_types WHERE bank_account_id = ?");
                $stmt->execute([$id]);
                $paymentTypesCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                error_log("Payment types count for account $id: $paymentTypesCount");
                
                if ($paymentTypesCount > 0) {
                    // Obtener los nombres de los payment types para mostrar en el mensaje
                    $stmt = $pdo->prepare("SELECT name FROM payment_types WHERE bank_account_id = ?");
                    $stmt->execute([$id]);
                    $paymentTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $paymentTypeNames = array_map(function($type) { return $type['name']; }, $paymentTypes);
                    
                    $response = [
                        "error" => "No se puede desactivar la cuenta",
                        "message" => "Esta cuenta bancaria tiene tipos de pago asociados que la están utilizando.",
                        "details" => [
                            "count" => $paymentTypesCount,
                            "payment_types" => $paymentTypeNames
                        ]
                    ];
                    
                    error_log("Returning conflict response: " . json_encode($response));
                    echo json_encode($response);
                    return;
                }
            }
        }
        
        $stmt = $pdo->prepare("UPDATE bank_accounts SET active = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$active, $id]);
        
        $response = ["message" => "Account status updated"];
        error_log("Returning success response: " . json_encode($response));
        echo json_encode($response);
        
    } catch (Exception $e) {
        error_log("Error in toggleAccountStatus: " . $e->getMessage());
        echo json_encode(["error" => "Error interno del servidor", "message" => $e->getMessage()]);
    }
}
