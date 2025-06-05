<?php
require_once 'config.php';

if (!isLoggedIn() || !checkSessionTimeout()) {
    header('Location: login.php');
    exit;
}

$pageTitle = 'Pruebas - Módulo de Ingresos';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .test-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        .test-section {
            background: white;
            margin-bottom: 30px;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .test-title {
            color: #2563eb;
            font-size: 20px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .test-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .test-btn {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .test-btn:hover {
            background: #2563eb;
        }
        .test-btn.success {
            background: #059669;
        }
        .test-btn.success:hover {
            background: #047857;
        }
        .test-btn.warning {
            background: #d97706;
        }
        .test-btn.warning:hover {
            background: #b45309;
        }
        .test-output {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 15px;
            margin-top: 15px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            max-height: 400px;
            overflow-y: auto;
        }
        .loading {
            color: #6b7280;
            font-style: italic;
        }
        .error {
            color: #dc2626;
            background: #fef2f2;
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #fecaca;
        }
        .success-msg {
            color: #059669;
            background: #ecfdf5;
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #a7f3d0;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .data-table th,
        .data-table td {
            border: 1px solid #d1d5db;
            padding: 8px 12px;
            text-align: left;
            font-size: 13px;
        }
        .data-table th {
            background: #f3f4f6;
            font-weight: 600;
            color: #374151;
        }
        .data-table tr:nth-child(even) {
            background: #f9fafb;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            color: white;
        }
        .status-pending { background: #f59e0b; }
        .status-in_progress { background: #3b82f6; }
        .status-completed { background: #059669; }
        .status-cancelled { background: #dc2626; }
        .progress-bar {
            width: 100px;
            height: 8px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background: #059669;
            transition: width 0.3s;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .stat-card {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-label {
            font-size: 12px;
            opacity: 0.9;
            margin-bottom: 5px;
        }
        .stat-value {
            font-size: 20px;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <h1 style="text-align: center; color: #1f2937; margin-bottom: 30px;">
            🧪 Panel de Pruebas - Módulo de Ingresos
        </h1>
        
        <!-- Sección 1: Pruebas de API -->
        <div class="test-section">
            <div class="test-title">
                <i class="fas fa-flask"></i>
                Pruebas de API
            </div>
            <div class="test-buttons">
                <button class="test-btn" onclick="testAPI('getAllIncomes')">
                    <i class="fas fa-list"></i> Obtener Todos los Ingresos
                </button>
                <button class="test-btn" onclick="testAPI('getTeams')">
                    <i class="fas fa-users"></i> Obtener Equipos
                </button>
                <button class="test-btn" onclick="testAPI('getJobTypes')">
                    <i class="fas fa-briefcase"></i> Obtener Tipos de Trabajo
                </button>
                <button class="test-btn success" onclick="testAPI('createTestIncome')">
                    <i class="fas fa-plus"></i> Crear Proyecto de Prueba
                </button>
            </div>
            <div id="apiOutput" class="test-output">
                Haz clic en cualquier botón para probar la API...
            </div>
        </div>

        <!-- Sección 2: Vista de Datos -->
        <div class="test-section">
            <div class="test-title">
                <i class="fas fa-table"></i>
                Lista de Proyectos/Ingresos
            </div>
            <div class="test-buttons">
                <button class="test-btn" onclick="loadIncomes()">
                    <i class="fas fa-sync"></i> Cargar Proyectos
                </button>
                <button class="test-btn warning" onclick="clearTable()">
                    <i class="fas fa-trash"></i> Limpiar Tabla
                </button>
            </div>
            <div id="incomesTable">
                <!-- Se carga dinámicamente -->
            </div>
        </div>

        <!-- Sección 3: Detalles del Proyecto -->
        <div class="test-section">
            <div class="test-title">
                <i class="fas fa-info-circle"></i>
                Detalles del Proyecto
            </div>
            <div class="test-buttons">
                <input type="text" id="incomeIdInput" placeholder="ID del proyecto..." style="padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; margin-right: 10px;">
                <button class="test-btn" onclick="loadIncomeDetails()">
                    <i class="fas fa-search"></i> Cargar Detalles
                </button>
            </div>
            <div id="incomeDetails">
                Ingresa un ID de proyecto para ver sus detalles...
            </div>
        </div>

        <!-- Sección 4: Estadísticas -->
        <div class="test-section">
            <div class="test-title">
                <i class="fas fa-chart-bar"></i>
                Estadísticas Generales
            </div>
            <div id="statisticsContainer">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-label">Total Proyectos</div>
                        <div class="stat-value" id="totalProjects">-</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Ingresos Totales</div>
                        <div class="stat-value" id="totalIncome">$-</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Pagos Recibidos</div>
                        <div class="stat-value" id="totalPaid">$-</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Por Cobrar</div>
                        <div class="stat-value" id="totalPending">$-</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const API_URL = 'api/income/IncomeController.php';
        
        // Función para probar endpoints de la API
        async function testAPI(action, params = {}) {
            const output = document.getElementById('apiOutput');
            output.innerHTML = '<div class="loading">⏳ Cargando...</div>';
            
            try {
                const url = new URL(API_URL, window.location.origin);
                url.searchParams.set('action', action);
                
                Object.keys(params).forEach(key => {
                    url.searchParams.set(key, params[key]);
                });
                
                const response = await fetch(url);
                const data = await response.json();
                
                output.innerHTML = `
                    <div class="success-msg">✅ Respuesta recibida</div>
                    <pre>${JSON.stringify(data, null, 2)}</pre>
                `;
                
                // Si es getAllIncomes, actualizar estadísticas
                if (action === 'getAllIncomes' && data.success) {
                    updateStatistics(data.data);
                }
                
            } catch (error) {
                output.innerHTML = `
                    <div class="error">❌ Error: ${error.message}</div>
                `;
            }
        }
        
        // Cargar y mostrar lista de ingresos
        async function loadIncomes() {
            const container = document.getElementById('incomesTable');
            container.innerHTML = '<div class="loading">⏳ Cargando proyectos...</div>';
            
            try {
                const response = await fetch(`${API_URL}?action=getAllIncomes`);
                const result = await response.json();
                
                if (result.success) {
                    const incomes = result.data;
                    
                    let html = `
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Equipo</th>
                                    <th>Tipo</th>
                                    <th>Monto Total</th>
                                    <th>Pagado</th>
                                    <th>Progreso</th>
                                    <th>Estado</th>
                                    <th>Fechas</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                    `;
                    
                    incomes.forEach(income => {
                        html += `
                            <tr>
                                <td><strong>${income.name}</strong></td>
                                <td>${income.team_name || 'N/A'}</td>
                                <td>${income.job_type_name || 'N/A'}</td>
                                <td>$${parseFloat(income.total_amount).toFixed(2)}</td>
                                <td>$${parseFloat(income.total_paid).toFixed(2)}</td>
                                <td>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: ${income.payment_progress}%"></div>
                                    </div>
                                    ${income.payment_progress}%
                                </td>
                                <td>
                                    <span class="status-badge status-${income.status}">
                                        ${income.status}
                                    </span>
                                </td>
                                <td>
                                    <div style="font-size: 11px;">
                                        <div>Inicio: ${income.start_date || 'N/A'}</div>
                                        <div>Fin: ${income.end_date || 'N/A'}</div>
                                    </div>
                                </td>
                                <td>
                                    <button class="test-btn" style="padding: 5px 10px; font-size: 12px;" 
                                            onclick="document.getElementById('incomeIdInput').value='${income.id}'; loadIncomeDetails();">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                    
                    html += '</tbody></table>';
                    container.innerHTML = html;
                    
                    // Actualizar estadísticas
                    updateStatistics(incomes);
                    
                } else {
                    container.innerHTML = `<div class="error">Error: ${result.error}</div>`;
                }
            } catch (error) {
                container.innerHTML = `<div class="error">Error de conexión: ${error.message}</div>`;
            }
        }
        
        // Cargar detalles de un proyecto específico
        async function loadIncomeDetails() {
            const incomeId = document.getElementById('incomeIdInput').value.trim();
            const container = document.getElementById('incomeDetails');
            
            if (!incomeId) {
                container.innerHTML = '<div class="error">Por favor, ingresa un ID de proyecto</div>';
                return;
            }
            
            container.innerHTML = '<div class="loading">⏳ Cargando detalles...</div>';
            
            try {
                const response = await fetch(`${API_URL}?action=getIncomeWithDetails&id=${incomeId}`);
                const result = await response.json();
                
                if (result.success) {
                    const data = result.data;
                    const income = data.income;
                    const payments = data.payments;
                    const lines = data.lines;
                    const stats = data.statistics;
                    
                    let html = `
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                            <div>
                                <h4>📋 Información del Proyecto</h4>
                                <p><strong>Nombre:</strong> ${income.name}</p>
                                <p><strong>Descripción:</strong> ${income.description || 'N/A'}</p>
                                <p><strong>Equipo:</strong> ${income.team_name || 'N/A'}</p>
                                <p><strong>Tipo:</strong> ${income.job_type_name || 'N/A'}</p>
                                <p><strong>Estado:</strong> <span class="status-badge status-${income.status}">${income.status}</span></p>
                            </div>
                            <div>
                                <h4>💰 Información Financiera</h4>
                                <p><strong>Monto Total:</strong> $${parseFloat(income.total_amount).toFixed(2)}</p>
                                <p><strong>Total Pagado:</strong> $${stats.total_paid.toFixed(2)}</p>
                                <p><strong>Por Cobrar:</strong> $${stats.remaining_amount.toFixed(2)}</p>
                                <p><strong>Progreso:</strong> ${stats.payment_progress}%</p>
                                <div class="progress-bar" style="width: 200px;">
                                    <div class="progress-fill" style="width: ${stats.payment_progress}%"></div>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    // Mostrar pagos
                    if (payments.length > 0) {
                        html += `
                            <h4>💳 Pagos Recibidos (${payments.length})</h4>
                            <table class="data-table">
                                <thead>
                                    <tr><th>Fecha</th><th>Monto</th><th>Tipo</th><th>Cuenta</th><th>Referencia</th><th>Notas</th></tr>
                                </thead>
                                <tbody>
                        `;
                        payments.forEach(payment => {
                            html += `
                                <tr>
                                    <td>${payment.payment_date}</td>
                                    <td>$${parseFloat(payment.amount).toFixed(2)}</td>
                                    <td>${payment.payment_type_name || 'N/A'}</td>
                                    <td>${payment.bank_account_name || 'N/A'}</td>
                                    <td>${payment.reference_number || 'N/A'}</td>
                                    <td>${payment.notes || 'N/A'}</td>
                                </tr>
                            `;
                        });
                        html += '</tbody></table>';
                    }
                    
                    // Mostrar líneas
                    if (lines.length > 0) {
                        html += `
                            <h4>📝 Líneas de Detalle (${lines.length})</h4>
                            <table class="data-table">
                                <thead>
                                    <tr><th>Descripción</th><th>Cantidad</th><th>Precio Unitario</th><th>Total</th></tr>
                                </thead>
                                <tbody>
                        `;
                        lines.forEach(line => {
                            html += `
                                <tr>
                                    <td>${line.description}</td>
                                    <td>${parseFloat(line.quantity).toFixed(2)}</td>
                                    <td>$${parseFloat(line.unit_price).toFixed(2)}</td>
                                    <td>$${parseFloat(line.total_amount).toFixed(2)}</td>
                                </tr>
                            `;
                        });
                        html += '</tbody></table>';
                    }
                    
                    container.innerHTML = html;
                    
                } else {
                    container.innerHTML = `<div class="error">Error: ${result.error}</div>`;
                }
            } catch (error) {
                container.innerHTML = `<div class="error">Error de conexión: ${error.message}</div>`;
            }
        }
        
        // Actualizar estadísticas generales
        function updateStatistics(incomes) {
            const totalProjects = incomes.length;
            const totalIncome = incomes.reduce((sum, income) => sum + parseFloat(income.total_amount), 0);
            const totalPaid = incomes.reduce((sum, income) => sum + parseFloat(income.total_paid), 0);
            const totalPending = totalIncome - totalPaid;
            
            document.getElementById('totalProjects').textContent = totalProjects;
            document.getElementById('totalIncome').textContent = '$' + totalIncome.toFixed(2);
            document.getElementById('totalPaid').textContent = '$' + totalPaid.toFixed(2);
            document.getElementById('totalPending').textContent = '$' + totalPending.toFixed(2);
        }
        
        // Limpiar tabla
        function clearTable() {
            document.getElementById('incomesTable').innerHTML = 'Tabla limpiada.';
        }
        
        // Cargar datos iniciales
        document.addEventListener('DOMContentLoaded', function() {
            loadIncomes();
        });
    </script>
</body>
</html> 