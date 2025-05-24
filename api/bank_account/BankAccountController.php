<?php
require_once '../../config.php';

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
    $data = json_decode(file_get_contents("php://input"), true);
    $uuid = uniqid('', true);
    $stmt = $pdo->prepare("INSERT INTO bank_accounts (id, name, bank_name, account_number, account_type, balance, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
    $stmt->execute([
        $uuid,
        $data['name'],
        $data['bank_name'],
        $data['account_number'],
        $data['account_type'],
        $data['balance'] ?? 0.00
    ]);
    echo json_encode(["message" => "Bank account created", "id" => $uuid]);
}

function updateBankAccount($id) {
    global $pdo;
    $data = json_decode(file_get_contents("php://input"), true);
    $stmt = $pdo->prepare("UPDATE bank_accounts SET name = ?, bank_name = ?, account_number = ?, account_type = ?, balance = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([
        $data['name'],
        $data['bank_name'],
        $data['account_number'],
        $data['account_type'],
        $data['balance'] ?? 0.00,
        $id
    ]);
    echo json_encode(["message" => "Bank account updated"]);
}

function deleteBankAccount($id) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM bank_accounts WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(["message" => "Bank account deleted"]);
}

function getTransactionsByAccount($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE bank_account_id = ? ORDER BY transaction_date DESC, id DESC");
    $stmt->execute([$id]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}
