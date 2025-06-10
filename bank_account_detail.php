<?php
require_once 'config.php';

if (!isLoggedIn() || !checkSessionTimeout()) {
    header('Location: auth/login.php');
    exit;
}

$accountId = $_GET['id'] ?? '';

if (empty($accountId)) {
    header('Location: bank_accounts.php');
    exit;
}

// Obtener información de la cuenta
try {
    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT * FROM bank_accounts WHERE id = ?");
    $stmt->execute([$accountId]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$account) {
        header('Location: bank_accounts.php');
        exit;
    }
} catch (Exception $e) {
    header('Location: bank_accounts.php');
    exit;
}

$pageTitle = 'Detalle de Cuenta - ' . $account['name'];
?>
<?php include 'includes/header.php'; ?>

<div class="main-layout">
    <?php include 'includes/sidebar.php'; ?>
    <main class="content">
        <div class="content-header">
            <h1 class="content-title">
                <i class="fas fa-university"></i>
                <?php echo htmlspecialchars($account['name']); ?>
            </h1>
            <p class="content-subtitle">Información detallada de la cuenta bancaria</p>
        </div>
        
        <!-- Información de la cuenta -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-info-circle"></i>
                    Información de la Cuenta
                </h3>
                <div>
                    <a href="bank_accounts.php" class="btn" style="background-color: var(--secondary-color); color: white; margin-right: 12px;">
                        <i class="fas fa-arrow-left"></i>
                        Volver
                    </a>
                    <?php if ($account['account_type'] === 'credito'): ?>
                        <button type="button" class="btn btn-success" onclick="openCreditPaymentModal('<?php echo $account['id']; ?>', '<?php echo htmlspecialchars($account['name']); ?>')">
                            <i class="fas fa-credit-card"></i>
                            Hacer Pago
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="account-details">
                <div class="detail-grid">
                    <div class="detail-item">
                        <label class="detail-label">Nombre de la Cuenta:</label>
                        <span class="detail-value"><?php echo htmlspecialchars($account['name']); ?></span>
                    </div>
                    
                    <div class="detail-item">
                        <label class="detail-label">Banco:</label>
                        <span class="detail-value"><?php echo htmlspecialchars($account['bank_name']); ?></span>
                    </div>
                    
                    <div class="detail-item">
                        <label class="detail-label">Número de Cuenta:</label>
                        <span class="detail-value"><?php echo htmlspecialchars($account['account_number']); ?></span>
                    </div>
                    
                    <div class="detail-item">
                        <label class="detail-label">Tipo de Cuenta:</label>
                        <span class="detail-value">
                            <?php 
                            $types = [
                                'cheque' => 'Cheque',
                                'credito' => 'Crédito',
                                'ahorro' => 'Ahorro',
                                'caja_chica' => 'Caja Chica'
                            ];
                            $typeClass = 'account-type-' . $account['account_type'];
                            echo "<span class='account-type-badge {$typeClass}'>" . ($types[$account['account_type']] ?? $account['account_type']) . "</span>";
                            ?>
                        </span>
                    </div>
                    
                    <div class="detail-item">
                        <label class="detail-label">Saldo Actual:</label>
                        <span class="detail-value">
                            <?php 
                            $balance = floatval($account['balance']);
                            $formattedAmount = number_format(abs($balance), 2);
                            
                            if ($account['account_type'] === 'credito') {
                                if ($balance < 0) {
                                    echo "<span class='credit-balance'>$" . $formattedAmount . "</span>";
                                    echo "<br><small style='color: var(--text-secondary);'>Saldo usado del límite de crédito</small>";
                                } else {
                                    echo "<span class='positive-balance'>$" . $formattedAmount . "</span>";
                                    echo "<br><small style='color: var(--text-secondary);'>Crédito disponible</small>";
                                }
                            } else {
                                if ($balance > 0) {
                                    echo "<span class='positive-balance'>$" . $formattedAmount . "</span>";
                                } else if ($balance === 0.0) {
                                    echo "<span class='zero-balance'>$" . $formattedAmount . "</span>";
                                } else {
                                    echo "<span class='credit-balance'>$" . $formattedAmount . "</span>";
                                }
                            }
                            ?>
                        </span>
                    </div>
                    
                    <div class="detail-item">
                        <label class="detail-label">Fecha de Creación:</label>
                        <span class="detail-value"><?php echo date('d/m/Y H:i', strtotime($account['created_at'])); ?></span>
                    </div>
                    
                    <div class="detail-item">
                        <label class="detail-label">Última Actualización:</label>
                        <span class="detail-value"><?php echo date('d/m/Y H:i', strtotime($account['updated_at'])); ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Historial de transacciones (si existe) -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-history"></i>
                    Historial de Transacciones
                </h3>
                <p class="card-subtitle">Últimas transacciones de esta cuenta</p>
            </div>
            <div id="transactionsContainer">
                <div style="text-align: center; padding: 40px 0; color: var(--text-secondary);">
                    <i class="fas fa-spinner fa-spin" style="font-size: 24px; margin-bottom: 12px;"></i>
                    <p>Cargando transacciones...</p>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modal para pago de crédito -->
<div class="modal" id="creditPaymentModal">
    <div class="modal-overlay" onclick="closeModal('creditPaymentModal')"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h2>Pago a Tarjeta de Crédito</h2>
            <button type="button" class="modal-close" onclick="closeModal('creditPaymentModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="creditPaymentForm">
            <div class="modal-body">
                <input type="hidden" id="creditAccountId" name="creditAccountId">
                
                <div class="form-group">
                    <label class="form-label">Cuenta de Crédito</label>
                    <input type="text" class="form-input" id="creditAccountName" readonly style="background-color: var(--bg-primary);">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="paymentFromAccount">Pagar desde *</label>
                    <select class="form-input" id="paymentFromAccount" name="paymentFromAccount" required>
                        <option value="">Seleccionar cuenta...</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="paymentAmount">Monto del Pago *</label>
                    <input type="number" step="0.01" class="form-input" id="paymentAmount" name="paymentAmount" required min="0.01">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="paymentDescription">Descripción</label>
                    <textarea class="form-input" id="paymentDescription" name="paymentDescription" rows="3" placeholder="Concepto del pago"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('creditPaymentModal')" style="background-color: var(--secondary-color); color: white;">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-credit-card"></i>
                    Realizar Pago
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Toast de notificación -->
<div id="toast" style="position: fixed; bottom: 32px; right: 32px; min-width: 220px; z-index: 9999; display: none; background: var(--primary-color, #2563eb); color: #fff; padding: 16px 24px; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,0.12); font-size: 16px; font-weight: 500; align-items: center; gap: 8px;">
    <span id="toastIcon" style="margin-right: 8px;"></span>
    <span id="toastMessage"></span>
</div>

<!-- Estilos específicos para la página de detalle -->
<style>
.detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    padding: 0;
}

.detail-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.detail-label {
    font-weight: 600;
    color: var(--text-secondary);
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.detail-value {
    font-size: 16px;
    color: var(--text-primary);
    font-weight: 500;
}

.account-details {
    padding: 20px 0;
}

/* Estilos para balances */
.credit-balance {
    color: var(--danger-color) !important;
    font-weight: 600;
}

.positive-balance {
    color: var(--success-color);
    font-weight: 600;
}

.zero-balance {
    color: var(--text-secondary);
}

/* Badge para tipos de cuenta */
.account-type-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
    text-transform: uppercase;
}

.account-type-cheque {
    background-color: rgb(37 99 235 / 0.1);
    color: var(--primary-color);
}

.account-type-credito {
    background-color: rgb(220 38 38 / 0.1);
    color: var(--danger-color);
}

.account-type-ahorro {
    background-color: rgb(5 150 105 / 0.1);
    color: var(--success-color);
}

.account-type-caja_chica {
    background-color: rgb(217 119 6 / 0.1);
    color: var(--warning-color);
}

/* Responsive */
@media (max-width: 768px) {
    .detail-grid {
        grid-template-columns: 1fr;
        gap: 16px;
    }
    
    .card-header {
        flex-direction: column;
        gap: 12px;
        align-items: flex-start !important;
    }
}
</style>

<?php include 'includes/footer.php'; ?>

<script>
const API_URL = 'api/bank_account/BankAccountController.php';

// Funciones para modales
function openModal(modalId) {
    document.getElementById(modalId).style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
    document.body.style.overflow = 'auto';
    if (modalId === 'creditPaymentModal') {
        document.getElementById('creditPaymentForm').reset();
    }
}

function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    const toastIcon = document.getElementById('toastIcon');
    const toastMessage = document.getElementById('toastMessage');
    toastMessage.textContent = message;
    if (type === 'success') {
        toast.style.background = 'var(--success-color, #059669)';
        toastIcon.innerHTML = '<i class="fas fa-check-circle"></i>';
    } else if (type === 'error') {
        toast.style.background = 'var(--danger-color, #dc2626)';
        toastIcon.innerHTML = '<i class="fas fa-times-circle"></i>';
    } else {
        toast.style.background = 'var(--primary-color, #2563eb)';
        toastIcon.innerHTML = '<i class="fas fa-info-circle"></i>';
    }
    toast.style.display = 'flex';
    setTimeout(() => { toast.style.display = 'none'; }, 3200);
}

// Cargar cuentas para pagos
function loadAccountsForPayment() {
    fetch(`${API_URL}?action=getAllBankAccounts`)
        .then(res => res.json())
        .then(data => {
            const accounts = data.data || data;
            const nonCreditAccounts = accounts.filter(acc => acc.account_type !== 'credito');
            
            const paymentFromSelect = document.getElementById('paymentFromAccount');
            if (paymentFromSelect) {
                paymentFromSelect.innerHTML = '<option value="">Seleccionar cuenta...</option>';
                nonCreditAccounts.forEach(account => {
                    paymentFromSelect.innerHTML += `<option value="${account.id}">${account.name} - ${account.bank_name} ($${parseFloat(account.balance).toLocaleString('es-MX', {minimumFractionDigits:2})})</option>`;
                });
            }
        })
        .catch(err => {
            console.error('Error loading accounts:', err);
        });
}

// Abrir modal de pago de crédito
function openCreditPaymentModal(accountId, accountName) {
    document.getElementById('creditAccountId').value = accountId;
    document.getElementById('creditAccountName').value = accountName;
    loadAccountsForPayment();
    openModal('creditPaymentModal');
}

// Formulario de pago de crédito
document.getElementById('creditPaymentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const creditAccountId = document.getElementById('creditAccountId').value;
    const fromAccount = document.getElementById('paymentFromAccount').value;
    const amount = document.getElementById('paymentAmount').value;
    const description = document.getElementById('paymentDescription').value;
    
    const paymentData = {
        credit_account_id: creditAccountId,
        from_account_id: fromAccount,
        amount: amount,
        description: description
    };
    
    fetch(`${API_URL}?action=creditPayment`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(paymentData)
    })
    .then(res => res.json())
    .then(result => {
        closeModal('creditPaymentModal');
        if (result.success) {
            showToast('Pago realizado con éxito', 'success');
            // Recargar la página para mostrar el balance actualizado
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showToast(result.message || 'Error al realizar el pago', 'error');
        }
    })
    .catch(() => {
        showToast('Error al procesar el pago', 'error');
    });
});

// Cargar transacciones
function loadTransactions() {
    const accountId = '<?php echo $accountId; ?>';
    
    fetch(`${API_URL}?action=getTransactionsByAccount&id=${accountId}`)
        .then(res => res.json())
        .then(transactions => {
            const container = document.getElementById('transactionsContainer');
            
            if (!transactions || transactions.length === 0) {
                container.innerHTML = `
                    <div style="text-align: center; padding: 40px 0; color: var(--text-secondary);">
                        <i class="fas fa-file-alt" style="font-size: 48px; margin-bottom: 12px; opacity: 0.5;"></i>
                        <p>No hay transacciones registradas para esta cuenta</p>
                    </div>
                `;
                return;
            }
            
            let html = `
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th>Monto</th>
                                <th>Descripción</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            transactions.forEach(transaction => {
                const date = new Date(transaction.transaction_date).toLocaleDateString('es-MX');
                const amount = parseFloat(transaction.amount);
                const amountClass = amount >= 0 ? 'positive-balance' : 'credit-balance';
                const formattedAmount = Math.abs(amount).toLocaleString('es-MX', {minimumFractionDigits: 2});
                
                html += `
                    <tr>
                        <td>${date}</td>
                        <td>${transaction.type}</td>
                        <td><span class="${amountClass}">$${formattedAmount}</span></td>
                        <td>${transaction.description || '-'}</td>
                    </tr>
                `;
            });
            
            html += `
                        </tbody>
                    </table>
                </div>
            `;
            
            container.innerHTML = html;
        })
        .catch(err => {
            console.error('Error loading transactions:', err);
            document.getElementById('transactionsContainer').innerHTML = `
                <div style="text-align: center; padding: 40px 0; color: var(--danger-color);">
                    <i class="fas fa-exclamation-triangle" style="font-size: 24px; margin-bottom: 12px;"></i>
                    <p>Error al cargar las transacciones</p>
                </div>
            `;
        });
}

// Cargar transacciones al iniciar
document.addEventListener('DOMContentLoaded', loadTransactions);

// Cerrar modales con ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (modal.style.display === 'flex') {
                closeModal(modal.id);
            }
        });
    }
});
</script>
