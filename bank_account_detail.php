<?php
require_once 'config.php';

if (!isLoggedIn() || !checkSessionTimeout()) {
    header('Location: login.php');
    exit;
}

$id = $_GET['id'] ?? '';
if (!$id) {
    header('Location: bank_accounts.php');
    exit;
}

$pageTitle = 'Detalle de Cuenta Bancaria';
?>
<?php include 'includes/header.php'; ?>

<div class="main-layout">
    <?php include 'includes/sidebar.php'; ?>
    <main class="content">
        <div class="content-header">
            <h1 class="content-title">
                <i class="fas fa-university"></i>
                Detalle de Cuenta Bancaria
            </h1>
            <a href="bank_accounts.php" class="btn" style="margin-bottom: 12px;"><i class="fas fa-arrow-left"></i> Volver a cuentas</a>
        </div>
        <div class="card" id="accountDetailCard" style="margin-bottom: 24px;"></div>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-exchange-alt"></i> Transacciones asociadas</h3>
            </div>
            <div style="overflow-x: auto;">
                <table class="data-table" id="transactionsTable" style="min-width: 700px;">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Monto</th>
                            <th>Descripción</th>
                            <th>Saldo después</th>
                        </tr>
                    </thead>
                    <tbody id="transactionsTableBody">
                        <!-- Transacciones -->
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
<script>
const accountId = "<?= htmlspecialchars($id) ?>";

// Cargar detalle de cuenta
fetch(`api/bank_account/BankAccountController.php?action=getBankAccountById&id=${accountId}`)
    .then(res => res.json())
    .then(account => {
        if (!account || !account.id) {
            document.getElementById('accountDetailCard').innerHTML = '<div style="padding:24px;">Cuenta no encontrada.</div>';
            return;
        }
        document.getElementById('accountDetailCard').innerHTML = `
            <div class="card-header">
                <h2 class="card-title">${account.name}</h2>
                <span class="card-subtitle">${account.bank_name} &mdash; ${account.account_number}</span>
            </div>
            <div class="card-body" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px,1fr)); gap: 16px;">
                <div><b>Tipo:</b> ${account.account_type === 'checking' ? 'Cheques' : account.account_type === 'savings' ? 'Ahorros' : 'Empresarial'}</div>
                <div><b>Saldo actual:</b> $${parseFloat(account.balance).toLocaleString('es-MX', {minimumFractionDigits:2})}</div>
                <div><b>Creada:</b> ${account.created_at ? account.created_at.split(' ')[0] : ''}</div>
                <div><b>Actualizada:</b> ${account.updated_at ? account.updated_at.split(' ')[0] : ''}</div>
            </div>
        `;
    });

// Cargar transacciones asociadas
fetch(`api/bank_account/BankAccountController.php?action=getTransactionsByAccount&id=${accountId}`)
    .then(res => res.json())
    .then(transactions => {
        const tbody = document.getElementById('transactionsTableBody');
        tbody.innerHTML = '';
        if (!transactions || !transactions.length) {
            tbody.innerHTML = '<tr><td colspan="5">No hay transacciones asociadas</td></tr>';
            return;
        }
        transactions.forEach(tr => {
            tbody.innerHTML += `
                <tr>
                    <td>${tr.transaction_date || ''}</td>
                    <td>${tr.type === 'income' ? 'Ingreso' : tr.type === 'expense' ? 'Gasto' : 'Transferencia'}</td>
                    <td style="color:${tr.type==='income' ? 'green':'red'};">${tr.type==='income'?'+':'-'}$${Math.abs(parseFloat(tr.amount)).toLocaleString('es-MX', {minimumFractionDigits:2})}</td>
                    <td>${tr.description || ''}</td>
                    <td>$${parseFloat(tr.balance_after).toLocaleString('es-MX', {minimumFractionDigits:2})}</td>
                </tr>
            `;
        });
    });
</script>
