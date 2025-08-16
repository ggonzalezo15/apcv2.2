<?php
require_once '../../config.php';

// Verificar autenticación
checkAPIAuthentication();

header('Content-Type: application/json');

$pdo = getConnection();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'getAllExpenses':
        getAllExpenses();
        break;
    case 'getExpenseById':
        getExpenseById($_GET['id'] ?? '');
        break;
    case 'createExpense':
        createExpense();
        break;
    case 'updateExpense':
        updateExpense($_GET['id'] ?? '');
        break;
    case 'deleteExpense':
        deleteExpense($_GET['id'] ?? '');
        break;
    case 'getTeams':
        getTeams();
        break;
    case 'getVendors':
        getVendors();
        break;
    case 'getBankAccounts':
        getBankAccounts();
        break;
    case 'getExpenseTypes':
        getExpenseTypes();
        break;
    case 'downloadAttachment':
        downloadAttachment($_GET['id'] ?? '');
        break;
    case 'getExpenseAttachments':
        getExpenseAttachments($_GET['id'] ?? '');
        break;
    case 'getExpenseAttachmentsB2':
        getExpenseAttachmentsB2($_GET['id'] ?? '');
        break;
    case 'deleteAttachment':
        deleteAttachmentEndpoint($_GET['id'] ?? '');
        break;
    default:
        echo json_encode(['error' => 'Acción no válida']);
}

function getAllExpenses() {
    global $pdo;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 0;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $sort = $_GET['sort'] ?? 'expense_date';
    $dir = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
    $team = $_GET['team'] ?? '';
    $vendor = $_GET['vendor'] ?? '';
    $search = $_GET['search'] ?? '';
    $dateFrom = $_GET['dateFrom'] ?? '';
    $dateTo = $_GET['dateTo'] ?? '';
    
    $allowedSort = ['expense_number', 'expense_date', 'total_amount', 'created_at', 'team_name', 'vendor_name'];
    if (!in_array($sort, $allowedSort)) $sort = 'expense_date';
    
    // Mapear campos de ordenamiento a la tabla correcta
    $sortFieldMap = [
        'expense_number' => 'e.expense_number',
        'expense_date' => 'e.expense_date',
        'total_amount' => 'e.total_amount',
        'created_at' => 'e.created_at',
        'team_name' => 't.name',
        'vendor_name' => 'v.name'
    ];
    
    $sortField = $sortFieldMap[$sort];
    
    $whereConditions = [];
    $params = [];
    
    if (!empty($team)) {
        $whereConditions[] = "e.team_id = ?";
        $params[] = $team;
    }
    
    if (!empty($vendor)) {
        $whereConditions[] = "e.vendor_id = ?";
        $params[] = $vendor;
    }
    
    if (!empty($search)) {
        $whereConditions[] = "(e.notes LIKE ? OR e.expense_number LIKE ? OR t.name LIKE ? OR v.name LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if (!empty($dateFrom)) {
        $whereConditions[] = "e.expense_date >= ?";
        $params[] = $dateFrom;
    }
    
    if (!empty($dateTo)) {
        $whereConditions[] = "e.expense_date <= ?";
        $params[] = $dateTo;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    $sql = "SELECT e.*, 
                   t.name as team_name, 
                   v.name as vendor_name,
                   ba.name as bank_account_name,
                   ba.account_type as bank_account_type,
                   (SELECT COUNT(*) FROM expense_attachments ea WHERE ea.expense_id = e.id) as attachment_count
            FROM expenses e
            LEFT JOIN teams t ON e.team_id = t.id
            LEFT JOIN vendors v ON e.vendor_id = v.id
            LEFT JOIN bank_accounts ba ON e.bank_account_id = ba.id
            $whereClause
            ORDER BY $sortField $dir, e.id DESC";
    
    if ($limit > 0) {
        $sql .= " LIMIT $limit OFFSET $offset";
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener el total de registros
    $countSql = "SELECT COUNT(*) FROM expenses e 
                 LEFT JOIN teams t ON e.team_id = t.id
                 LEFT JOIN vendors v ON e.vendor_id = v.id
                 $whereClause";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = $countStmt->fetchColumn();
    
    echo json_encode(['data' => $expenses, 'total' => (int)$total]);
}

function getExpenseById($id) {
    global $pdo;
    
    // Obtener el gasto principal
    $stmt = $pdo->prepare("
        SELECT e.*, 
               t.name as team_name, 
               v.name as vendor_name,
               ba.name as bank_account_name,
               ba.account_type as bank_account_type
        FROM expenses e
        LEFT JOIN teams t ON e.team_id = t.id
        LEFT JOIN vendors v ON e.vendor_id = v.id
        LEFT JOIN bank_accounts ba ON e.bank_account_id = ba.id
        WHERE e.id = ?
    ");
    $stmt->execute([$id]);
    $expense = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$expense) {
        echo json_encode(['error' => 'Gasto no encontrado']);
        return;
    }
    
    // Obtener las líneas de gasto
    $stmt = $pdo->prepare("
        SELECT el.*, et.name as expense_type_name, et.description as expense_type_description
        FROM expense_lines el
        LEFT JOIN expense_types et ON el.expense_type_id = et.id
        WHERE el.expense_id = ?
        ORDER BY el.created_at
    ");
    $stmt->execute([$id]);
    $expense['lines'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener los archivos adjuntos
    $stmt = $pdo->prepare("
        SELECT *, file_key, compressed FROM expense_attachments 
        WHERE expense_id = ?
        ORDER BY created_at
    ");
    $stmt->execute([$id]);
    $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convertir el campo compressed a boolean
    foreach ($attachments as &$attachment) {
        $attachment['compressed'] = (bool)$attachment['compressed'];
    }
    $expense['attachments'] = $attachments;
    
    echo json_encode($expense);
}

function createExpense() {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Obtener datos del formulario
        $data = $_POST;
        $expenseId = generateUUID();
        $expenseNumber = generateExpenseNumber();
        
        // Validar datos requeridos principales
        if (empty($data['expense_date'])) {
            throw new Exception('La fecha del gasto es obligatoria');
        }
        if (empty($data['team_id'])) {
            throw new Exception('El equipo es obligatorio');
        }
        if (empty($data['vendor_id'])) {
            throw new Exception('El proveedor es obligatorio');
        }
        if (empty($data['bank_account_id'])) {
            throw new Exception('La cuenta bancaria es obligatoria');
        }
        
        // Procesar líneas de gastos
        $lines = json_decode($data['lines'] ?? '[]', true);
        if (empty($lines)) {
            throw new Exception('Debe agregar al menos una línea de gasto');
        }
        
        // Validar cada línea de gasto
        foreach ($lines as $index => $line) {
            $lineNumber = $index + 1;
            
            // Validar descripción
            if (empty($line['description']) || trim($line['description']) === '') {
                throw new Exception("La línea {$lineNumber} debe tener una descripción");
            }
            
            // Validar tipo de gasto
            if (empty($line['expense_type_id'])) {
                throw new Exception("La línea {$lineNumber} debe tener un tipo de gasto seleccionado");
            }
            
            // Validar importe
            $amount = floatval($line['amount'] ?? 0);
            if ($amount < 0.01) {
                throw new Exception("El importe de la línea {$lineNumber} debe ser mayor a $0.01");
            }
        }
        
        // Validar que las entidades relacionadas existan en la base de datos
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM teams WHERE id = ?");
        $stmt->execute([$data['team_id']]);
        if ($stmt->fetchColumn() == 0) {
            throw new Exception('El equipo seleccionado no existe');
        }
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM vendors WHERE id = ?");
        $stmt->execute([$data['vendor_id']]);
        if ($stmt->fetchColumn() == 0) {
            throw new Exception('El proveedor seleccionado no existe');
        }
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bank_accounts WHERE id = ?");
        $stmt->execute([$data['bank_account_id']]);
        if ($stmt->fetchColumn() == 0) {
            throw new Exception('La cuenta bancaria seleccionada no existe');
        }
        
        // Validar que los tipos de gasto existan
        foreach ($lines as $index => $line) {
            $lineNumber = $index + 1;
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM expense_types WHERE id = ?");
            $stmt->execute([$line['expense_type_id']]);
            if ($stmt->fetchColumn() == 0) {
                throw new Exception("El tipo de gasto seleccionado en la línea {$lineNumber} no existe");
            }
        }
        
        // Calcular total
        $totalAmount = 0;
        foreach ($lines as $line) {
            $totalAmount += floatval($line['amount'] ?? 0);
        }
        
        // Insertar gasto principal
        $stmt = $pdo->prepare("
            INSERT INTO expenses (id, expense_number, team_id, vendor_id, bank_account_id, notes, 
                                total_amount, expense_date, created_at, updated_at, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), 1)
        ");
        $stmt->execute([
            $expenseId,
            $expenseNumber,
            $data['team_id'],
            $data['vendor_id'],
            $data['bank_account_id'],
            $data['notes'] ?? '',
            $totalAmount,
            $data['expense_date']
        ]);
        
        // Insertar líneas de gasto
        foreach ($lines as $line) {
            $lineId = generateUUID();
            
            $stmt = $pdo->prepare("
                INSERT INTO expense_lines (id, expense_id, description, expense_type_id, amount, deducible, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $lineId,
                $expenseId,
                $line['description'] ?? '',
                $line['expense_type_id'],
                floatval($line['amount'] ?? 0),
                isset($line['deducible']) ? ($line['deducible'] ? 1 : 0) : 0
            ]);
        }
        
        // Procesar archivos adjuntos
        if (isset($_FILES['attachments'])) {
            handleFileUploads($expenseId, $_FILES['attachments']);
        }
        
        // Registrar transacción bancaria si se especificó cuenta
        registerBankTransaction($expenseId, $data['bank_account_id'], $totalAmount, $data['expense_date'], "Gasto #{$expenseNumber}");
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Gasto creado exitosamente', 'id' => $expenseId, 'expense_number' => $expenseNumber]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function updateExpense($id) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Obtener datos del formulario
        $data = $_POST;
        
        // Validar datos requeridos principales
        if (empty($data['expense_date'])) {
            throw new Exception('La fecha del gasto es obligatoria');
        }
        if (empty($data['team_id'])) {
            throw new Exception('El equipo es obligatorio');
        }
        if (empty($data['vendor_id'])) {
            throw new Exception('El proveedor es obligatorio');
        }
        if (empty($data['bank_account_id'])) {
            throw new Exception('La cuenta bancaria es obligatoria');
        }
        
        // Validar que el gasto existe
        $stmt = $pdo->prepare("SELECT * FROM expenses WHERE id = ?");
        $stmt->execute([$id]);
        $existingExpense = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$existingExpense) {
            throw new Exception('Gasto no encontrado');
        }
        
        // Procesar líneas de gastos
        $lines = json_decode($data['lines'] ?? '[]', true);
        if (empty($lines)) {
            throw new Exception('Debe agregar al menos una línea de gasto');
        }
        
        // Validar cada línea de gasto
        foreach ($lines as $index => $line) {
            $lineNumber = $index + 1;
            
            // Validar descripción
            if (empty($line['description']) || trim($line['description']) === '') {
                throw new Exception("La línea {$lineNumber} debe tener una descripción");
            }
            
            // Validar tipo de gasto
            if (empty($line['expense_type_id'])) {
                throw new Exception("La línea {$lineNumber} debe tener un tipo de gasto seleccionado");
            }
            
            // Validar importe
            $amount = floatval($line['amount'] ?? 0);
            if ($amount < 0.01) {
                throw new Exception("El importe de la línea {$lineNumber} debe ser mayor a $0.01");
            }
        }
        
        // Validar que las entidades relacionadas existan en la base de datos
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM teams WHERE id = ?");
        $stmt->execute([$data['team_id']]);
        if ($stmt->fetchColumn() == 0) {
            throw new Exception('El equipo seleccionado no existe');
        }
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM vendors WHERE id = ?");
        $stmt->execute([$data['vendor_id']]);
        if ($stmt->fetchColumn() == 0) {
            throw new Exception('El proveedor seleccionado no existe');
        }
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bank_accounts WHERE id = ?");
        $stmt->execute([$data['bank_account_id']]);
        if ($stmt->fetchColumn() == 0) {
            throw new Exception('La cuenta bancaria seleccionada no existe');
        }
        
        // Validar que los tipos de gasto existan
        foreach ($lines as $index => $line) {
            $lineNumber = $index + 1;
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM expense_types WHERE id = ?");
            $stmt->execute([$line['expense_type_id']]);
            if ($stmt->fetchColumn() == 0) {
                throw new Exception("El tipo de gasto seleccionado en la línea {$lineNumber} no existe");
            }
        }
        
        // Calcular nuevo total
        $totalAmount = 0;
        foreach ($lines as $line) {
            $totalAmount += floatval($line['amount'] ?? 0);
        }
        
        // Actualizar gasto principal
        $stmt = $pdo->prepare("
            UPDATE expenses 
            SET team_id = ?, vendor_id = ?, bank_account_id = ?, notes = ?, 
                total_amount = ?, expense_date = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $data['team_id'],
            $data['vendor_id'],
            $data['bank_account_id'],
            $data['notes'] ?? '',
            $totalAmount,
            $data['expense_date'],
            $id
        ]);
        
        // Eliminar líneas existentes
        $stmt = $pdo->prepare("DELETE FROM expense_lines WHERE expense_id = ?");
        $stmt->execute([$id]);
        
        // Insertar nuevas líneas
        foreach ($lines as $line) {
            $lineId = generateUUID();
            
            $stmt = $pdo->prepare("
                INSERT INTO expense_lines (id, expense_id, description, expense_type_id, amount, deducible, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $lineId,
                $id,
                $line['description'] ?? '',
                $line['expense_type_id'],
                floatval($line['amount'] ?? 0),
                isset($line['deducible']) ? ($line['deducible'] ? 1 : 0) : 0
            ]);
        }
        
        // Procesar nuevos archivos adjuntos
        if (isset($_FILES['attachments'])) {
            handleFileUploads($id, $_FILES['attachments']);
        }
        
        // Eliminar archivos adjuntos marcados para eliminación
        if (isset($_POST['delete_attachments'])) {
            $attachmentsToDelete = json_decode($_POST['delete_attachments'], true);
            if ($attachmentsToDelete && is_array($attachmentsToDelete)) {
                foreach ($attachmentsToDelete as $attachmentId) {
                    deleteAttachment($attachmentId);
                }
            }
        }
        
        // Actualizar transacción bancaria si cambió el monto
        if ($existingExpense['total_amount'] != $totalAmount || $existingExpense['bank_account_id'] != $data['bank_account_id']) {
            updateBankTransaction($id, $data['bank_account_id'], $totalAmount, $data['expense_date'], "Gasto #{$id}");
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Gasto actualizado exitosamente', 'id' => $id]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function deleteExpense($id) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Verificar que el gasto existe
        $stmt = $pdo->prepare("SELECT * FROM expenses WHERE id = ?");
        $stmt->execute([$id]);
        $expense = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$expense) {
            throw new Exception('Gasto no encontrado');
        }
        
        // Revertir transacción bancaria antes de eliminar
        $stmt = $pdo->prepare("SELECT * FROM transactions WHERE expense_id = ?");
        $stmt->execute([$id]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($transaction) {
            // Obtener balance actual de la cuenta
            $stmt = $pdo->prepare("SELECT balance FROM bank_accounts WHERE id = ?");
            $stmt->execute([$transaction['bank_account_id']]);
            $currentBalance = $stmt->fetchColumn();
            
            if ($currentBalance !== false) {
                // Revertir el gasto: restar el amount (que es negativo, por lo que se suma)
                $revertedBalance = $currentBalance - $transaction['amount'];
                
                // Actualizar balance de la cuenta
                $stmt = $pdo->prepare("UPDATE bank_accounts SET balance = ? WHERE id = ?");
                $stmt->execute([$revertedBalance, $transaction['bank_account_id']]);
            }
        }
        
        // Eliminar archivos adjuntos (tanto locales como B2)
        $stmt = $pdo->prepare("SELECT id FROM expense_attachments WHERE expense_id = ?");
        $stmt->execute([$id]);
        $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($attachments as $attachment) {
            try {
                deleteAttachment($attachment['id']);
            } catch (Exception $e) {
                error_log("Error eliminando archivo adjunto {$attachment['id']}: " . $e->getMessage());
                // Continúa con el siguiente archivo aunque falle uno
            }
        }
        
        // Eliminar transacción bancaria asociada
        $stmt = $pdo->prepare("DELETE FROM transactions WHERE expense_id = ?");
        $stmt->execute([$id]);
        
        // Eliminar líneas de gasto
        $stmt = $pdo->prepare("DELETE FROM expense_lines WHERE expense_id = ?");
        $stmt->execute([$id]);
        
        // Eliminar gasto principal
        $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ?");
        $stmt->execute([$id]);
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Gasto eliminado exitosamente']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getTeams() {
    global $pdo;
    $stmt = $pdo->query("SELECT id, name FROM teams WHERE status = 'active' ORDER BY name");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function getVendors() {
    global $pdo;
    // Solo obtener proveedores activos
    $stmt = $pdo->query("SELECT id, name FROM vendors WHERE status = 'active' ORDER BY name");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function getBankAccounts() {
    global $pdo;
    $stmt = $pdo->query("SELECT id, name, account_type, balance, active FROM bank_accounts ORDER BY name");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function getExpenseTypes() {
    global $pdo;
    $stmt = $pdo->query("SELECT id, name, description FROM expense_types WHERE status = 'active' ORDER BY name");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function handleFileUploads($expenseId, $files) {
    global $pdo;
    
    $uploadDir = '../../uploads/expenses/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
    $maxFileSize = 2 * 1024 * 1024; // 2MB
    $maxFiles = 4;
    
    $fileCount = is_array($files['name']) ? count($files['name']) : 1;
    
    if ($fileCount > $maxFiles) {
        throw new Exception("Máximo $maxFiles archivos permitidos");
    }
    
    for ($i = 0; $i < $fileCount; $i++) {
        $fileName = is_array($files['name']) ? $files['name'][$i] : $files['name'];
        $fileTmpName = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
        $fileSize = is_array($files['size']) ? $files['size'][$i] : $files['size'];
        $fileType = is_array($files['type']) ? $files['type'][$i] : $files['type'];
        $fileError = is_array($files['error']) ? $files['error'][$i] : $files['error'];
        
        if ($fileError !== UPLOAD_ERR_OK) {
            continue;
        }
        
        if ($fileSize > $maxFileSize) {
            throw new Exception("El archivo $fileName excede el tamaño máximo de 2MB");
        }
        
        if (!in_array($fileType, $allowedTypes)) {
            throw new Exception("Tipo de archivo no permitido: $fileName");
        }
        
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
        $newFileName = $expenseId . '_' . uniqid() . '.' . $fileExtension;
        $fullUploadPath = $uploadDir . $newFileName;
        
        if (move_uploaded_file($fileTmpName, $fullUploadPath)) {
            $attachmentId = generateUUID();
            // Guardar solo la ruta relativa desde la raíz del proyecto
            $relativeFilePath = 'uploads/expenses/' . $newFileName;
            
            $stmt = $pdo->prepare("
                INSERT INTO expense_attachments (id, expense_id, filename, original_filename, file_path, file_size, mime_type, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $attachmentId,
                $expenseId,
                $newFileName,
                $fileName,
                $relativeFilePath,
                $fileSize,
                $fileType
            ]);
        }
    }
}

function registerBankTransaction($expenseId, $bankAccountId, $amount, $transactionDate, $description) {
    global $pdo;
    
    // Obtener balance actual de la cuenta
    $stmt = $pdo->prepare("SELECT balance FROM bank_accounts WHERE id = ?");
    $stmt->execute([$bankAccountId]);
    $currentBalance = $stmt->fetchColumn();
    
    if ($currentBalance === false) {
        throw new Exception('Cuenta bancaria no encontrada');
    }
    
    $newBalance = $currentBalance - $amount; // Restar porque es un gasto
    
    // Actualizar balance de la cuenta
    $stmt = $pdo->prepare("UPDATE bank_accounts SET balance = ? WHERE id = ?");
    $stmt->execute([$newBalance, $bankAccountId]);
    
    // Registrar transacción
    $transactionId = generateUUID();
    $stmt = $pdo->prepare("
        INSERT INTO transactions (id, bank_account_id, expense_id, type, amount, balance_after, description, transaction_date, created_at) 
        VALUES (?, ?, ?, 'expense', ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $transactionId,
        $bankAccountId,
        $expenseId,
        -$amount, // Negativo porque es un gasto
        $newBalance,
        "Gasto: $description",
        $transactionDate
    ]);
}

function updateBankTransaction($expenseId, $bankAccountId, $newAmount, $transactionDate, $description) {
    global $pdo;
    
    // Obtener la transacción existente
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE expense_id = ?");
    $stmt->execute([$expenseId]);
    $existingTransaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingTransaction) {
        // Revertir la transacción anterior
        $stmt = $pdo->prepare("SELECT balance FROM bank_accounts WHERE id = ?");
        $stmt->execute([$existingTransaction['bank_account_id']]);
        $currentBalance = $stmt->fetchColumn();
        
        $revertedBalance = $currentBalance - $existingTransaction['amount']; // Sumar porque amount es negativo
        
        $stmt = $pdo->prepare("UPDATE bank_accounts SET balance = ? WHERE id = ?");
        $stmt->execute([$revertedBalance, $existingTransaction['bank_account_id']]);
        
        // Eliminar transacción anterior
        $stmt = $pdo->prepare("DELETE FROM transactions WHERE expense_id = ?");
        $stmt->execute([$expenseId]);
    }
    
    // Crear nueva transacción
    registerBankTransaction($expenseId, $bankAccountId, $newAmount, $transactionDate, $description);
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

function generateExpenseNumber() {
    global $pdo;
    // Obtener el último número de gasto
    $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(expense_number, 4) AS UNSIGNED)) as last_num FROM expenses WHERE expense_number LIKE 'EXP%'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $lastNum = $result['last_num'] ?? 0;
    return 'EXP' . str_pad($lastNum + 1, 6, '0', STR_PAD_LEFT);
}

function deleteAttachment($attachmentId) {
    global $pdo;
    
    try {
        // Obtener información completa del archivo
        $stmt = $pdo->prepare("SELECT file_path, file_key, original_filename FROM expense_attachments WHERE id = ?");
        $stmt->execute([$attachmentId]);
        $attachment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($attachment) {
            // Verificar si es un archivo B2 (tiene file_key)
            if (!empty($attachment['file_key'])) {
                // Eliminar de BackBlaze B2 con verificación avanzada
                require_once '../../includes/B2FileUploader.php';
                
                try {
                    $uploader = new B2FileUploader();
                    
                    // Primer intento de eliminación
                    $result = $uploader->deleteFile($attachment['file_key']);
                    
                    if (!$result['success']) {
                        error_log("Error en eliminación inicial de B2: " . ($result['error'] ?? 'Unknown error'));
                        // Continúa para eliminar el registro de BD aunque falle B2
                    } else {
                        // ✅ VERIFICACIÓN POST-ELIMINACIÓN MEJORADA
                        $verificationResult = verifyB2FileDeletionWithRetry($uploader, $attachment['file_key']);
                        
                        if ($verificationResult['verified']) {
                            error_log("✅ CONFIRMADO: Archivo eliminado exitosamente de B2 en {$verificationResult['attempts']} intento(s): " . $attachment['file_key']);
                        } else {
                            $attempts = $verificationResult['attempts'] ?? 'unknown';
                            error_log("⚠️ ADVERTENCIA: No se pudo verificar eliminación después de $attempts intentos: " . $attachment['file_key']);
                            
                            if ($verificationResult['max_retries_reached']) {
                                error_log("🚨 CRÍTICO: Máximo de reintentos alcanzado para: " . $attachment['file_key']);
                            }
                        }
                    }
                } catch (Exception $e) {
                    error_log("Error conectando con B2 para eliminar archivo: " . $e->getMessage());
                    // Continúa para eliminar el registro de BD aunque falle B2
                }
            } else {
                // Eliminar archivo local si existe
                $filePath = '../../' . $attachment['file_path']; // Ajustar ruta desde api/expense/
                if (file_exists($filePath)) {
                    if (unlink($filePath)) {
                        error_log("✅ Archivo local eliminado exitosamente: " . $filePath);
                    } else {
                        error_log("⚠️ Error eliminando archivo local: " . $filePath);
                    }
                } else {
                    error_log("ℹ️ Archivo local no existe (ya eliminado): " . $filePath);
                }
            }
            
            // Eliminar registro de la base de datos
            $stmt = $pdo->prepare("DELETE FROM expense_attachments WHERE id = ?");
            $stmt->execute([$attachmentId]);
            
            error_log("✅ Registro de BD eliminado para attachment: " . $attachmentId . " (" . ($attachment['original_filename'] ?? 'sin nombre') . ")");
        }
    } catch (Exception $e) {
        error_log("Error eliminando archivo adjunto: " . $e->getMessage());
        throw $e; // Re-lanzar la excepción para que el proceso padre pueda manejarla
    }
}

/**
 * Endpoint público para eliminar un attachment individual
 */
function deleteAttachmentEndpoint($attachmentId) {
    global $pdo;
    
    try {
        // Validar que se proporcionó un ID
        if (empty($attachmentId)) {
            echo json_encode(['success' => false, 'error' => 'ID de attachment requerido']);
            return;
        }
        
        // Verificar que el attachment existe antes de intentar eliminarlo
        $stmt = $pdo->prepare("SELECT id, filename FROM expense_attachments WHERE id = ?");
        $stmt->execute([$attachmentId]);
        $attachment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$attachment) {
            echo json_encode(['success' => false, 'error' => 'Attachment no encontrado']);
            return;
        }
        
        // Llamar a la función interna de eliminación
        deleteAttachment($attachmentId);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Attachment eliminado exitosamente',
            'attachment_id' => $attachmentId,
            'filename' => $attachment['filename']
        ]);
        
    } catch (Exception $e) {
        error_log("Error en deleteAttachmentEndpoint: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'error' => 'Error eliminando attachment: ' . $e->getMessage()
        ]);
    }
}

function downloadAttachment($attachmentId) {
    global $pdo;
    
    try {
        // Obtener información completa del archivo
        $stmt = $pdo->prepare("SELECT * FROM expense_attachments WHERE id = ?");
        $stmt->execute([$attachmentId]);
        $attachment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$attachment) {
            http_response_code(404);
            echo json_encode(['error' => 'Archivo no encontrado']);
            return;
        }
        
        // La ruta en BD ya es relativa, solo agregar ../../ desde el directorio api/expense/
        $filePath = '../../' . $attachment['file_path'];
        
        // Verificar que el archivo existe físicamente
        if (!file_exists($filePath)) {
            http_response_code(404);
            echo json_encode(['error' => 'Archivo físico no encontrado']);
            return;
        }
        
        // Configurar headers para descarga/visualización
        header('Content-Type: ' . $attachment['mime_type']);
        header('Content-Length: ' . filesize($filePath));
        header('Content-Disposition: inline; filename="' . $attachment['original_filename'] . '"');
        header('Cache-Control: public, max-age=3600');
        
        // Enviar el archivo
        readfile($filePath);
        exit;
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al descargar archivo: ' . $e->getMessage()]);
    }
}

function getExpenseAttachments($expenseId) {
    global $pdo;
    
    try {
        // Obtener los archivos adjuntos del gasto
        $stmt = $pdo->prepare("
            SELECT id, original_filename, mime_type, file_size, created_at 
            FROM expense_attachments 
            WHERE expense_id = ? 
            ORDER BY created_at
        ");
        $stmt->execute([$expenseId]);
        $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($attachments);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al obtener archivos: ' . $e->getMessage()]);
    }
}

function getExpenseAttachmentsB2($expenseId) {
    global $pdo;
    
    try {
        // Obtener los archivos adjuntos del gasto incluyendo información B2
        $stmt = $pdo->prepare("
            SELECT id, original_filename, mime_type, file_size, created_at,
                   file_key, compressed
            FROM expense_attachments 
            WHERE expense_id = ? 
            ORDER BY created_at
        ");
        $stmt->execute([$expenseId]);
        $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convertir el campo compressed a boolean
        foreach ($attachments as &$attachment) {
            $attachment['compressed'] = (bool)$attachment['compressed'];
            $attachment['original_name'] = $attachment['original_filename'];
        }
        
        echo json_encode($attachments);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al obtener archivos: ' . $e->getMessage()]);
    }
}

/**
 * Verificar que un archivo fue efectivamente eliminado de BackBlaze B2
 * 
 * @param B2FileUploader $uploader - Instancia del uploader B2
 * @param string $fileKey - Clave del archivo en B2
 * @return array - ['verified' => bool, 'message' => string, 'file_exists' => bool]
 */
function verifyB2FileDeletion($uploader, $fileKey) {
    try {
        // Intentar obtener información del archivo
        // Si el archivo fue eliminado, esto debería fallar
        $fileInfo = $uploader->getFileInfo($fileKey);
        
        if ($fileInfo && $fileInfo['success']) {
            // ⚠️ El archivo AÚN EXISTE - eliminación falló
            return [
                'verified' => false,
                'message' => 'Archivo aún existe en B2 después de la eliminación',
                'file_exists' => true,
                'file_info' => $fileInfo
            ];
        } else {
            // ✅ El archivo NO EXISTE - eliminación exitosa
            return [
                'verified' => true,
                'message' => 'Archivo confirmado como eliminado de B2',
                'file_exists' => false
            ];
        }
        
    } catch (Exception $e) {
        // Si hay una excepción, probablemente el archivo no existe
        // Esto es lo esperado después de una eliminación exitosa
        
        // Verificar si el error indica que el archivo no existe
        $errorMessage = strtolower($e->getMessage());
        $notFoundIndicators = ['not found', '404', 'does not exist', 'no such file'];
        
        $isNotFoundError = false;
        foreach ($notFoundIndicators as $indicator) {
            if (strpos($errorMessage, $indicator) !== false) {
                $isNotFoundError = true;
                break;
            }
        }
        
        if ($isNotFoundError) {
            // ✅ Error "not found" = archivo eliminado exitosamente
            return [
                'verified' => true,
                'message' => 'Archivo confirmado como eliminado (error not found esperado)',
                'file_exists' => false,
                'error_detail' => $e->getMessage()
            ];
        } else {
            // ⚠️ Error inesperado - no podemos verificar
            return [
                'verified' => false,
                'message' => 'No se pudo verificar la eliminación debido a error inesperado',
                'file_exists' => 'unknown',
                'error_detail' => $e->getMessage()
            ];
        }
    }
}

/**
 * Verificación avanzada con retry automático (optimizada para web)
 * 
 * @param B2FileUploader $uploader
 * @param string $fileKey
 * @param int $maxRetries
 * @return array
 */
function verifyB2FileDeletionWithRetry($uploader, $fileKey, $maxRetries = 2) {
    $attempts = 0;
    
    while ($attempts < $maxRetries) {
        $attempts++;
        
        $verification = verifyB2FileDeletion($uploader, $fileKey);
        
        if ($verification['verified']) {
            $verification['attempts'] = $attempts;
            return $verification;
        }
        
        // Si el archivo aún existe, intentar eliminarlo nuevamente
        if ($verification['file_exists'] === true && $attempts < $maxRetries) {
            error_log("Intento $attempts: Archivo aún existe, reintentando eliminación: $fileKey");
            $deleteResult = $uploader->deleteFile($fileKey);
            
            if (!$deleteResult['success']) {
                error_log("Fallo en reintento de eliminación: " . ($deleteResult['error'] ?? 'Unknown error'));
                break; // No seguir intentando si la eliminación falla
            }
        }
    }
    
    // Si llegamos aquí, no se pudo verificar la eliminación después de todos los intentos
    $verification['attempts'] = $attempts;
    $verification['max_retries_reached'] = true;
    
    return $verification;
}
?> 
