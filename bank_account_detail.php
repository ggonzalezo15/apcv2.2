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
            <div style="display: flex; align-items: center; gap: 16px;">
                <button type="button" class="btn" onclick="window.location.href='bank_accounts.php'" title="Volver a cuentas bancarias">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <div>
                    <h1 class="content-title">
                        <i class="fas fa-university"></i>
                        <?php echo htmlspecialchars($account['name']); ?>
                        <span style="font-weight: 400; color: var(--text-secondary); margin-left: 8px;">
                            - <?php echo htmlspecialchars($account['account_number']); ?>
                        </span>
                    </h1>
                    <p class="content-subtitle">Información detallada de la cuenta bancaria</p>
                </div>
            </div>
        </div>
        
        <!-- Información de la cuenta -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-info-circle"></i>
                    Información de la Cuenta
                </h3>
                <?php if ($account['account_type'] === 'credito'): ?>
                    <div>
                        <button type="button" class="btn btn-success" onclick="openCreditPaymentModal('<?php echo $account['id']; ?>', '<?php echo htmlspecialchars($account['name']); ?>')">
                            <i class="fas fa-credit-card"></i>
                            Hacer Pago
                        </button>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="account-details">
                <div class="detail-grid">
                    <div class="detail-item">
                        <label class="detail-label">Banco:</label>
                        <span class="detail-value"><?php echo htmlspecialchars($account['bank_name']); ?></span>
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
                        <label class="detail-label">Estado de la Cuenta:</label>
                        <span class="detail-value">
                            <?php 
                            $isActive = $account['active'] == 1 || $account['active'] == '1' || $account['active'] == true;
                            if ($isActive) {
                                echo "<span class='status-badge status-active'>Activa</span>";
                            } else {
                                echo "<span class='status-badge status-inactive'>Inactiva</span>";
                            }
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
            
            <!-- Footer con selector de página y paginación -->
            <div id="transactionsTableFooter" style="display: none; justify-content: space-between; align-items: center; padding: 12px 0 0 0; border-top: 1px solid var(--border-color); margin-top: 16px;">
                <div id="transactionsPageSizeContainer"></div>
                <div id="transactionsPagination"></div>
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
                <input type="hidden" id="currentCreditBalance" name="currentCreditBalance">
                
                <!-- Sección de Balance Actual -->
                <div class="credit-balance-section">
                    <div class="balance-info">
                        <div class="balance-label">
                            <i class="fas fa-credit-card"></i>
                            Saldo Pendiente de Pago
                        </div>
                        <div class="balance-amount" id="currentBalanceDisplay">$0.00</div>
                        <div class="balance-note">Monto máximo que se puede pagar</div>
                    </div>
                    
                    <!-- Quick Actions para pagos comunes -->
                    <div class="quick-actions">
                        <div class="quick-actions-label">Pagos Rápidos:</div>
                        <div class="quick-actions-buttons">
                            <button type="button" class="quick-amount-btn" onclick="setQuickAmount('25')">25%</button>
                            <button type="button" class="quick-amount-btn" onclick="setQuickAmount('50')">50%</button>
                            <button type="button" class="quick-amount-btn" onclick="setQuickAmount('100')">Total</button>
                        </div>
                    </div>
                </div>
                
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
                    <label class="form-label" for="paymentAmount">
                        Monto del Pago *
                        <span class="max-amount-indicator" id="maxAmountIndicator"></span>
                    </label>
                    <input type="number" step="0.01" class="form-input" id="paymentAmount" name="paymentAmount" required min="0.01">
                    <div class="payment-amount-feedback" id="paymentAmountFeedback"></div>
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
    background-color: rgb(99 102 241 / 0.1);
    color: rgb(99 102 241);
}

.account-type-credito {
    background-color: rgb(6 182 212 / 0.1);
    color: rgb(6 182 212);
}

.account-type-ahorro {
    background-color: rgb(5 150 105 / 0.1);
    color: var(--success-color);
}

.account-type-caja_chica {
    background-color: rgb(59 130 246 / 0.1);
    color: rgb(59 130 246);
}

/* Badge para estado de cuenta */
.status-badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-active {
    background-color: rgb(5 150 105 / 0.1);
    color: var(--success-color, #059669);
}

.status-inactive {
    background-color: rgb(220 38 38 / 0.1);
    color: var(--danger-color, #dc2626);
}

/* Estilos para el modal de pago a crédito */
.credit-balance-section {
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 24px;
}

.balance-info {
    text-align: center;
    margin-bottom: 20px;
}

.balance-label {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: 8px;
}

.balance-amount {
    font-size: 32px;
    font-weight: 700;
    color: var(--danger-color);
    margin-bottom: 4px;
}

.balance-note {
    font-size: 12px;
    color: var(--text-secondary);
    font-style: italic;
}

.quick-actions {
    border-top: 1px solid #e2e8f0;
    padding-top: 16px;
}

.quick-actions-label {
    font-size: 13px;
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: 12px;
    text-align: center;
}

.quick-actions-buttons {
    display: flex;
    gap: 8px;
    justify-content: center;
}

.quick-amount-btn {
    padding: 8px 16px;
    border: 1px solid var(--primary-color);
    background: white;
    color: var(--primary-color);
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    min-width: 60px;
}

.quick-amount-btn:hover {
    background: var(--primary-color);
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(37, 99, 235, 0.3);
}

.max-amount-indicator {
    font-size: 11px;
    color: var(--text-secondary);
    font-weight: normal;
    margin-left: 8px;
}

.payment-amount-feedback {
    margin-top: 4px;
    font-size: 12px;
    min-height: 16px;
}

.payment-amount-feedback.valid {
    color: var(--success-color);
}

.payment-amount-feedback.invalid {
    color: var(--danger-color);
}

.payment-amount-feedback.warning {
    color: #f59e0b;
}

/* Estilos de paginación para transacciones */
#transactionsPagination {
    display: flex;
    gap: 8px;
    align-items: center;
}

#transactionsPageSizeContainer label {
    margin-right: 8px;
    font-weight: 500;
    color: var(--text-secondary);
    font-size: 14px;
}

#transactionsPageSizeContainer select {
    width: auto;
    display: inline-block;
    min-width: 120px;
}

.pagination-number {
    min-width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--border-color);
    background: var(--bg-primary);
    color: var(--text-primary);
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.2s ease;
    text-decoration: none;
    padding: 0 8px;
}

.pagination-number:hover {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

.pagination-number.active {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

.pagination-number:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    background: var(--bg-secondary);
}

.pagination-ellipsis {
    padding: 0 8px;
    color: var(--text-secondary);
    font-size: 14px;
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
    
    #transactionsTableFooter {
        flex-direction: column;
        align-items: center;
        gap: 12px;
    }
    
    #transactionsPagination {
        flex-wrap: wrap;
        justify-content: center;
        gap: 4px;
    }
    
    .pagination-number {
        min-width: 28px;
        height: 28px;
        font-size: 12px;
        padding: 0 6px;
    }
    
    #transactionsPageSizeContainer {
        text-align: center;
    }
    
    #transactionsPageSizeContainer select {
        min-width: 100px;
        font-size: 12px;
    }
}

/* Tabla ordenable */
.sortable-table th.sortable {
    cursor: pointer;
    user-select: none;
    position: relative;
    transition: background-color 0.2s ease;
}

.sortable-table th.sortable:hover {
    background-color: #f8fafc;
}

.sort-icon {
    margin-left: 8px;
    font-size: 12px;
    color: var(--text-secondary);
    transition: color 0.2s ease;
}

.sortable-table th.sortable.sort-asc .sort-icon:before {
    content: "\f0de"; /* fa-sort-up */
    color: var(--primary-color);
}

.sortable-table th.sortable.sort-desc .sort-icon:before {
    content: "\f0dd"; /* fa-sort-down */
    color: var(--primary-color);
}
</style>

<?php include 'includes/footer.php'; ?>

<script>
const API_URL = 'api/bank_account/BankAccountController.php';

// Variables de paginación para transacciones
let currentTransactionsPage = 1;
let transactionsPerPage = 10;
let totalTransactions = 0;
let totalTransactionsPages = 0;

// Variables de ordenamiento para transacciones
let transactionsSortField = 'transaction_date';
let transactionsSortDir = 'desc';

// Funciones para modales
function openModal(modalId) {
    document.getElementById(modalId).style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
    document.body.style.overflow = 'auto';
    if (modalId === 'creditPaymentModal') {
        // Resetear formulario
        document.getElementById('creditPaymentForm').reset();
        
        // Limpiar estado del modal
        currentCreditBalance = 0;
        updateBalanceDisplay();
        updateMaxAmountIndicator();
        
        // Limpiar feedback
        const feedback = document.getElementById('paymentAmountFeedback');
        if (feedback) {
            feedback.textContent = '';
            feedback.className = 'payment-amount-feedback';
        }
        
        // Rehabilitar botón de envío
        const submitButton = document.querySelector('#creditPaymentForm button[type="submit"]');
        if (submitButton) {
            submitButton.disabled = false;
            submitButton.innerHTML = '<i class="fas fa-credit-card"></i> Realizar Pago';
        }
        
        // Remover event listeners del input de monto para evitar acumulación
        const paymentAmountInput = document.getElementById('paymentAmount');
        if (paymentAmountInput) {
            paymentAmountInput.removeEventListener('input', validatePaymentAmount);
            paymentAmountInput.removeEventListener('change', validatePaymentAmount);
        }
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
            // Filtrar cuentas no de crédito Y activas
            const nonCreditAccounts = accounts.filter(acc => 
                acc.account_type !== 'credito' && 
                (acc.active === 1 || acc.active === '1' || acc.active === true)
            );
            
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

// Variables globales para el modal de pago
let currentCreditBalance = 0;

// Cargar balance actual de la cuenta de crédito
function loadCreditAccountBalance(accountId) {
    return fetch(`${API_URL}?action=getBankAccountById&id=${accountId}`)
        .then(res => res.json())
        .then(data => {
            const account = data.data || data;
            if (account && account.balance !== undefined) {
                // Para cuentas de crédito, el balance negativo significa deuda
                currentCreditBalance = Math.abs(parseFloat(account.balance));
                updateBalanceDisplay();
                updateMaxAmountIndicator();
                return currentCreditBalance;
            }
            return 0;
        })
        .catch(err => {
            console.error('Error loading credit balance:', err);
            currentCreditBalance = 0;
            updateBalanceDisplay();
            return 0;
        });
}

// Actualizar la visualización del balance
function updateBalanceDisplay() {
    const balanceDisplay = document.getElementById('currentBalanceDisplay');
    const hiddenBalance = document.getElementById('currentCreditBalance');
    
    if (balanceDisplay) {
        if (currentCreditBalance > 0) {
            balanceDisplay.textContent = `$${currentCreditBalance.toLocaleString('es-MX', {minimumFractionDigits: 2})}`;
            balanceDisplay.style.color = 'var(--danger-color)';
        } else {
            balanceDisplay.textContent = '$0.00';
            balanceDisplay.style.color = 'var(--success-color)';
        }
    }
    
    if (hiddenBalance) {
        hiddenBalance.value = currentCreditBalance;
    }
}

// Actualizar indicador de monto máximo
function updateMaxAmountIndicator() {
    const indicator = document.getElementById('maxAmountIndicator');
    if (indicator) {
        if (currentCreditBalance > 0) {
            indicator.textContent = `(máx: $${currentCreditBalance.toLocaleString('es-MX', {minimumFractionDigits: 2})})`;
        } else {
            indicator.textContent = '(cuenta saldada)';
        }
    }
}

// Función para quick actions
function setQuickAmount(percentage) {
    const paymentAmountInput = document.getElementById('paymentAmount');
    if (!paymentAmountInput || currentCreditBalance <= 0) return;
    
    let amount = 0;
    switch(percentage) {
        case '25':
            amount = currentCreditBalance * 0.25;
            break;
        case '50':
            amount = currentCreditBalance * 0.50;
            break;
        case '100':
            amount = currentCreditBalance;
            break;
    }
    
    // Redondear a 2 decimales
    amount = Math.round(amount * 100) / 100;
    paymentAmountInput.value = amount.toFixed(2);
    
    // Validar el monto después de establecerlo
    validatePaymentAmount();
}

// Validar monto del pago
function validatePaymentAmount() {
    const paymentAmountInput = document.getElementById('paymentAmount');
    const feedback = document.getElementById('paymentAmountFeedback');
    const submitButton = document.querySelector('#creditPaymentForm button[type="submit"]');
    
    if (!paymentAmountInput || !feedback) return;
    
    const amount = parseFloat(paymentAmountInput.value) || 0;
    
    feedback.className = 'payment-amount-feedback';
    
    if (amount <= 0) {
        feedback.textContent = 'El monto debe ser mayor a $0.00';
        feedback.classList.add('invalid');
        if (submitButton) submitButton.disabled = true;
        return false;
    }
    
    if (currentCreditBalance <= 0) {
        feedback.textContent = 'Esta cuenta no tiene saldo pendiente de pago';
        feedback.classList.add('warning');
        if (submitButton) submitButton.disabled = true;
        return false;
    }
    
    if (amount > currentCreditBalance) {
        feedback.textContent = `No puedes pagar más de $${currentCreditBalance.toLocaleString('es-MX', {minimumFractionDigits: 2})} (sobrepago no permitido)`;
        feedback.classList.add('invalid');
        if (submitButton) submitButton.disabled = true;
        return false;
    }
    
    // Monto válido
    if (amount === currentCreditBalance) {
        feedback.textContent = '✓ Pago total - La cuenta quedará completamente saldada';
        feedback.classList.add('valid');
    } else {
        const remaining = currentCreditBalance - amount;
        feedback.textContent = `✓ Monto válido - Quedarán $${remaining.toLocaleString('es-MX', {minimumFractionDigits: 2})} pendientes`;
        feedback.classList.add('valid');
    }
    
    if (submitButton) submitButton.disabled = false;
    return true;
}

// Abrir modal de pago de crédito
function openCreditPaymentModal(accountId, accountName) {
    document.getElementById('creditAccountId').value = accountId;
    document.getElementById('creditAccountName').value = accountName;
    
    // Cargar balance actual y cuentas para pago
    Promise.all([
        loadCreditAccountBalance(accountId),
        loadAccountsForPayment()
    ]).then(() => {
        // Configurar event listener para validación en tiempo real
        const paymentAmountInput = document.getElementById('paymentAmount');
        if (paymentAmountInput) {
            paymentAmountInput.addEventListener('input', validatePaymentAmount);
            paymentAmountInput.addEventListener('change', validatePaymentAmount);
        }
        
        openModal('creditPaymentModal');
    });
}

// Formulario de pago de crédito
document.getElementById('creditPaymentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Validar antes de enviar
    if (!validatePaymentAmount()) {
        showToast('Por favor, corrige el monto del pago', 'error');
        return;
    }
    
    const creditAccountId = document.getElementById('creditAccountId').value;
    const fromAccount = document.getElementById('paymentFromAccount').value;
    const amount = parseFloat(document.getElementById('paymentAmount').value);
    const description = document.getElementById('paymentDescription').value;
    
    // Validaciones adicionales
    if (!fromAccount) {
        showToast('Selecciona una cuenta de origen', 'error');
        return;
    }
    
    if (amount > currentCreditBalance) {
        showToast('El monto no puede ser mayor al saldo pendiente', 'error');
        return;
    }
    
    // Deshabilitar botón de envío para evitar doble envío
    const submitButton = document.querySelector('#creditPaymentForm button[type="submit"]');
    const originalText = submitButton.innerHTML;
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
    
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
        if (result.success) {
            showToast('Pago realizado con éxito', 'success');
            closeModal('creditPaymentModal');
            // Recargar la página para mostrar el balance actualizado
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showToast(result.message || 'Error al realizar el pago', 'error');
            // Restaurar botón
            submitButton.disabled = false;
            submitButton.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('Payment error:', error);
        showToast('Error al procesar el pago', 'error');
        // Restaurar botón
        submitButton.disabled = false;
        submitButton.innerHTML = originalText;
    });
});

// Cargar transacciones con paginación
function loadTransactions(page = 1) {
    const accountId = '<?php echo $accountId; ?>';
    currentTransactionsPage = page;
    
    fetch(`${API_URL}?action=getTransactionsByAccount&id=${accountId}&page=${page}&limit=${transactionsPerPage}&sort=${transactionsSortField}&dir=${transactionsSortDir}`)
        .then(res => res.json())
        .then(response => {
            const container = document.getElementById('transactionsContainer');
            
            // Verificar si hay un error en la respuesta
            if (response.error) {
                container.innerHTML = `
                    <div style="text-align: center; padding: 40px 0; color: var(--danger-color);">
                        <i class="fas fa-exclamation-triangle" style="font-size: 24px; margin-bottom: 12px;"></i>
                        <p>Error: ${response.error}</p>
                    </div>
                `;
                document.getElementById('transactionsTableFooter').style.display = 'none';
                return;
            }
            
            // Actualizar variables de paginación
            totalTransactions = response.total || 0;
            totalTransactionsPages = response.pages || 0;
            const transactions = response.data || [];
            
            if (!transactions || transactions.length === 0) {
                container.innerHTML = `
                    <div style="text-align: center; padding: 40px 0; color: var(--text-secondary);">
                        <i class="fas fa-file-alt" style="font-size: 48px; margin-bottom: 12px; opacity: 0.5;"></i>
                        <p>No hay transacciones registradas para esta cuenta</p>
                    </div>
                `;
                document.getElementById('transactionsTableFooter').style.display = 'none';
                return;
            }
            
            let html = `
                <div style="overflow-x: auto;">
                    <table class="data-table sortable-table">
                        <thead>
                            <tr>
                                <th class="sortable" data-sort="transaction_date">
                                    Fecha
                                    <i class="fas fa-sort sort-icon"></i>
                                </th>
                                <th class="sortable" data-sort="type">
                                    Tipo
                                    <i class="fas fa-sort sort-icon"></i>
                                </th>
                                <th class="sortable" data-sort="amount">
                                    Monto
                                    <i class="fas fa-sort sort-icon"></i>
                                </th>
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
            
            // Configurar ordenamiento después de que se crea la tabla
            setupTransactionsTableSorting();
            updateTransactionsSortIcons();
            
            // Mostrar footer con paginación y selector de página
            const footerContainer = document.getElementById('transactionsTableFooter');
            if (totalTransactions > 0) {
                renderTransactionsPageSizeSelector();
                renderTransactionsPagination();
                // Siempre mostrar el footer cuando hay transacciones (para el selector)
                footerContainer.style.display = 'flex';
            } else {
                footerContainer.style.display = 'none';
            }
        })
        .catch(err => {
            console.error('Error loading transactions:', err);
            document.getElementById('transactionsContainer').innerHTML = `
                <div style="text-align: center; padding: 40px 0; color: var(--danger-color);">
                    <i class="fas fa-exclamation-triangle" style="font-size: 24px; margin-bottom: 12px;"></i>
                    <p>Error de conexión al cargar las transacciones</p>
                    <small style="display: block; margin-top: 8px; opacity: 0.7;">
                        ${err.message || 'Error desconocido'}
                    </small>
                </div>
            `;
            document.getElementById('transactionsTableFooter').style.display = 'none';
        });
}

// Renderizar selector de tamaño de página para transacciones
function renderTransactionsPageSizeSelector() {
    const container = document.getElementById('transactionsPageSizeContainer');
    if (!container) return;
    
    container.innerHTML = '';
    
    const label = document.createElement('label');
    label.textContent = 'Mostrar:';
    label.style = 'margin-right: 8px; font-weight: 500; color: var(--text-secondary); font-size: 14px;';
    
    const selector = document.createElement('select');
    selector.id = 'transactionsPageSizeSelector';
    selector.className = 'form-input';
    selector.style = 'width: auto; display: inline-block; min-width: 120px;';
    
    [5, 10, 20, 50].forEach(size => {
        const opt = document.createElement('option');
        opt.value = size;
        opt.textContent = `${size} por página`;
        selector.appendChild(opt);
    });
    
    selector.value = transactionsPerPage;
    selector.onchange = function() {
        transactionsPerPage = parseInt(this.value);
        loadTransactions(1); // Reiniciar a la primera página
    };
    
    container.appendChild(label);
    container.appendChild(selector);
}

// Renderizar paginación de transacciones
function renderTransactionsPagination() {
    const container = document.getElementById('transactionsPagination');
    if (!container) return;
    
    // Ocultar paginación si hay solo una página o menos
    if (totalTransactionsPages <= 1) {
        container.style.display = 'none';
        return;
    }
    
    container.style.display = 'flex';
    let html = '';
    
    // Botón anterior
    if (currentTransactionsPage > 1) {
        html += `<span class="pagination-number" onclick="loadTransactions(${currentTransactionsPage - 1})">
            <i class="fas fa-chevron-left"></i>
        </span>`;
    } else {
        html += `<span class="pagination-number" style="opacity: 0.5; cursor: not-allowed;">
            <i class="fas fa-chevron-left"></i>
        </span>`;
    }
    
    // Números de página
    const maxVisiblePages = 5;
    let startPage = Math.max(1, currentTransactionsPage - Math.floor(maxVisiblePages / 2));
    let endPage = Math.min(totalTransactionsPages, startPage + maxVisiblePages - 1);
    
    if (endPage - startPage + 1 < maxVisiblePages) {
        startPage = Math.max(1, endPage - maxVisiblePages + 1);
    }
    
    // Primera página si no está visible
    if (startPage > 1) {
        html += `<span class="pagination-number" onclick="loadTransactions(1)">1</span>`;
        if (startPage > 2) {
            html += `<span class="pagination-ellipsis">...</span>`;
        }
    }
    
    // Páginas visibles
    for (let i = startPage; i <= endPage; i++) {
        const activeClass = i === currentTransactionsPage ? 'active' : '';
        html += `<span class="pagination-number ${activeClass}" onclick="loadTransactions(${i})">${i}</span>`;
    }
    
    // Última página si no está visible
    if (endPage < totalTransactionsPages) {
        if (endPage < totalTransactionsPages - 1) {
            html += `<span class="pagination-ellipsis">...</span>`;
        }
        html += `<span class="pagination-number" onclick="loadTransactions(${totalTransactionsPages})">${totalTransactionsPages}</span>`;
    }
    
    // Botón siguiente
    if (currentTransactionsPage < totalTransactionsPages) {
        html += `<span class="pagination-number" onclick="loadTransactions(${currentTransactionsPage + 1})">
            <i class="fas fa-chevron-right"></i>
        </span>`;
    } else {
        html += `<span class="pagination-number" style="opacity: 0.5; cursor: not-allowed;">
            <i class="fas fa-chevron-right"></i>
        </span>`;
    }
    
    container.innerHTML = html;
}

// --- Configurar ordenamiento de tabla ---
function setupTransactionsTableSorting() {
    const sortableElements = document.querySelectorAll('#transactionsContainer .sortable');
    
    sortableElements.forEach(th => {
        th.addEventListener('click', function() {
            const field = this.dataset.sort;
            
            if (transactionsSortField === field) {
                transactionsSortDir = transactionsSortDir === 'asc' ? 'desc' : 'asc';
            } else {
                transactionsSortField = field;
                transactionsSortDir = 'asc';
            }
            
            loadTransactions(1); // Reiniciar a la primera página
        });
    });
}

function updateTransactionsSortIcons() {
    document.querySelectorAll('#transactionsContainer .sortable').forEach(th => {
        th.classList.remove('sort-asc', 'sort-desc');
        if (th.dataset.sort === transactionsSortField) {
            th.classList.add(`sort-${transactionsSortDir}`);
        }
    });
}

// Cargar transacciones al iniciar
document.addEventListener('DOMContentLoaded', function() {
    loadTransactions();
});

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
