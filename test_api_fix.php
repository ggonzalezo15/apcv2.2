<?php
require_once 'config.php';

if (!isLoggedIn() || !checkSessionTimeout()) {
    header('Location: auth/login.php');
    exit;
}

$pageTitle = 'Test API Fix';
?>
<?php include 'includes/header.php'; ?>

<div class="main-layout">
    <?php include 'includes/sidebar.php'; ?>
    <main class="content">
        <div class="content-header">
            <h1 class="content-title">
                <i class="fas fa-bug"></i>
                Test API Fix
            </h1>
            <p class="content-subtitle">Verificar que las APIs funcionan después de las mejoras de seguridad</p>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-check-circle"></i>
                    Tests de APIs
                </h3>
            </div>
            <div style="padding: 20px;">
                <div id="testResults"></div>
                
                <div style="margin-top: 20px;">
                    <button onclick="runTests()" class="btn" style="background: var(--primary-color); color: white;">
                        <i class="fas fa-play"></i>
                        Ejecutar Tests
                    </button>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
async function runTests() {
    const resultsDiv = document.getElementById('testResults');
    resultsDiv.innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Ejecutando tests...</div>';
    
    const tests = [
        { name: 'Incomes API', url: 'api/income/IncomesController.php?action=getIncomes&limit=5' },
        { name: 'Contractors API', url: 'api/contractor/ContractorController.php?action=getAllContractors&limit=5' },
        { name: 'Vendor API', url: 'api/vendor/VendorController.php?action=getAllVendors&limit=5' },
        { name: 'Expenses API', url: 'api/expense/ExpenseController.php?action=getAllExpenses&limit=5' },
        { name: 'Teams API', url: 'api/income/IncomesController.php?action=getTeams' },
        { name: 'Contractor Details API', url: 'api/contractor/ContractorPaymentController.php?action=getBankAccountsForPayments' }
    ];
    
    let results = '';
    
    for (const test of tests) {
        try {
            const response = await fetch(test.url);
            const data = await response.json();
            
            if (response.ok && !data.error) {
                results += `
                    <div style="background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; padding: 10px; margin-bottom: 10px; color: #155724;">
                        <strong>✅ ${test.name}</strong> - OK
                        <div style="font-size: 12px; margin-top: 5px;">
                            Data count: ${data.data ? data.data.length : (data.total || 'N/A')}
                        </div>
                    </div>
                `;
            } else {
                results += `
                    <div style="background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; padding: 10px; margin-bottom: 10px; color: #721c24;">
                        <strong>❌ ${test.name}</strong> - Error
                        <div style="font-size: 12px; margin-top: 5px;">
                            ${data.error || 'Error desconocido'}
                        </div>
                    </div>
                `;
            }
        } catch (error) {
            results += `
                <div style="background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; padding: 10px; margin-bottom: 10px; color: #721c24;">
                    <strong>❌ ${test.name}</strong> - Error de conexión
                    <div style="font-size: 12px; margin-top: 5px;">
                        ${error.message}
                    </div>
                </div>
            `;
        }
    }
    
    resultsDiv.innerHTML = results;
}
</script>

<?php include 'includes/footer.php'; ?> 