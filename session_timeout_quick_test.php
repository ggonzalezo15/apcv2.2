<?php
// ARCHIVO SOLO PARA PRUEBAS RÁPIDAS - ELIMINAR EN PRODUCCIÓN
// Este archivo temporalmente cambia el SESSION_TIMEOUT a 30 segundos

session_start();

// Temporalmente cambiar el timeout a 30 segundos para pruebas rápidas
$_SESSION['quick_test_timeout'] = 30;

// Solo accesible para usuarios logueados
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

// Función especial para test rápido
function checkQuickSessionTimeout() {
    if (!isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    $timeout = $_SESSION['quick_test_timeout'] ?? 30;
    
    if (time() - $_SESSION['last_activity'] > $timeout) {
        return false;
    }
    
    // NO actualizar last_activity para poder probar el timeout
    return true;
}

// Verificar timeout (esto debería redirigir si ha expirado)
if (!checkQuickSessionTimeout()) {
    header('Location: auth/login.php?timeout=1');
    exit;
}

// Calcular información de timeout
$lastActivity = $_SESSION['last_activity'] ?? null;
$currentTime = time();
$timeSinceActivity = $currentTime - $lastActivity;
$timeUntilExpiry = 30 - $timeSinceActivity;
$expiryPercentage = min(100, ($timeSinceActivity / 30) * 100);

$timeoutInfo = [
    'time_since_activity' => $timeSinceActivity,
    'time_until_expiry' => $timeUntilExpiry,
    'expiry_percentage' => $expiryPercentage
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Rápido de Timeout (30s)</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .status-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .status-value {
            font-weight: bold;
        }
        .progress-bar {
            width: 100%;
            height: 20px;
            background-color: #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #ffc107, #dc3545);
            transition: width 0.3s ease;
        }
        .warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .danger {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .countdown {
            font-size: 24px;
            font-weight: bold;
            color: #2563eb;
            margin: 20px 0;
            text-align: center;
        }
        .ajax-info {
            text-align: center;
            margin: 20px 0;
            padding: 10px;
            background-color: #e7f3ff;
            border-radius: 5px;
        }
    </style>
    <script>
        // Función para obtener información actualizada vía AJAX
        function fetchQuickSessionInfo() {
            fetch('session_timeout_quick_ajax.php')
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        // Sesión expirada, redirigir
                        window.location.href = 'auth/login.php?timeout=1';
                        return;
                    }
                    
                    // Actualizar información en la página
                    document.getElementById('time-since-activity').textContent = data.time_since_activity + ' segundos';
                    document.getElementById('time-until-expiry').textContent = Math.max(0, data.time_until_expiry) + ' segundos';
                    document.getElementById('expiry-percentage').textContent = Math.round(data.expiry_percentage * 10) / 10 + '%';
                    
                    // Actualizar barra de progreso
                    document.querySelector('.progress-fill').style.width = data.expiry_percentage + '%';
                    
                    // Actualizar estado
                    const statusDiv = document.getElementById('status-message');
                    if (data.time_until_expiry <= 0) {
                        statusDiv.innerHTML = '<div class="danger"><strong>⚠️ SESIÓN EXPIRADA:</strong> La sesión debería cerrarse automáticamente.</div>';
                    } else if (data.time_until_expiry <= 10) {
                        statusDiv.innerHTML = '<div class="warning"><strong>⚠️ Advertencia:</strong> La sesión expirará en menos de 10 segundos.</div>';
                    } else {
                        statusDiv.innerHTML = '<div class="success"><strong>✅ Sesión Activa:</strong> El timeout automático está funcionando correctamente.</div>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        }
        
        // Countdown timer
        let timeLeft = <?php echo max(0, $timeoutInfo['time_until_expiry']); ?>;
        function updateCountdown() {
            const countdownElement = document.getElementById('countdown');
            if (timeLeft > 0) {
                countdownElement.textContent = timeLeft + 's';
                timeLeft--;
            } else {
                countdownElement.textContent = 'EXPIRADO';
                countdownElement.style.color = '#dc3545';
            }
        }
        
        // Actualizar countdown cada segundo
        setInterval(updateCountdown, 1000);
        updateCountdown();
        
        // Obtener información actualizada cada 2 segundos
        setInterval(fetchQuickSessionInfo, 2000);
    </script>
</head>
<body>
    <div class="container">
        <h1>⚡ Test Rápido de Timeout (30 segundos)</h1>
        
        <div class="ajax-info">
            <strong>📡 Actualización AJAX:</strong> Los datos se actualizan cada 2 segundos SIN recargar la página
        </div>
        
        <div class="countdown">
            <div>Tiempo restante: <span id="countdown"></span></div>
        </div>
        
        <div class="status-item">
            <span>Usuario:</span>
            <span class="status-value"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
        </div>
        
        <div class="status-item">
            <span>Tiempo Desde Última Actividad:</span>
            <span class="status-value" id="time-since-activity"><?php echo $timeoutInfo['time_since_activity']; ?> segundos</span>
        </div>
        
        <div class="status-item">
            <span>Tiempo Hasta Expiración:</span>
            <span class="status-value" id="time-until-expiry"><?php echo max(0, $timeoutInfo['time_until_expiry']); ?> segundos</span>
        </div>
        
        <div class="status-item">
            <span>Progreso de Expiración:</span>
            <span class="status-value" id="expiry-percentage"><?php echo round($timeoutInfo['expiry_percentage'], 1); ?>%</span>
        </div>
        
        <div class="progress-bar">
            <div class="progress-fill" style="width: <?php echo $timeoutInfo['expiry_percentage']; ?>%;"></div>
        </div>
        
        <div id="status-message">
            <?php if ($timeoutInfo['time_until_expiry'] <= 0): ?>
                <div class="danger">
                    <strong>⚠️ SESIÓN EXPIRADA:</strong> La sesión debería cerrarse automáticamente.
                </div>
            <?php elseif ($timeoutInfo['time_until_expiry'] <= 10): ?>
                <div class="warning">
                    <strong>⚠️ Advertencia:</strong> La sesión expirará en menos de 10 segundos.
                </div>
            <?php else: ?>
                <div class="success">
                    <strong>✅ Sesión Activa:</strong> El timeout automático está funcionando correctamente.
                </div>
            <?php endif; ?>
        </div>
        
        <h3>⚡ Test Rápido (30 segundos)</h3>
        <ul>
            <li><strong>Timeout configurado:</strong> 30 segundos (para pruebas rápidas)</li>
            <li><strong>Actualización AJAX:</strong> Cada 2 segundos</li>
            <li><strong>NO actualiza sesión:</strong> Permite que expire realmente</li>
        </ul>
        
        <div style="text-align: center; margin: 20px 0;">
            <button onclick="fetchQuickSessionInfo()" style="background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">
                🔄 Actualizar Información
            </button>
        </div>
        
        <div class="danger">
            <strong>⚠️ ARCHIVO DE PRUEBA:</strong> Este archivo es solo para pruebas rápidas. 
            <strong>Elimínalo en producción</strong> junto con <code>session_timeout_quick_ajax.php</code>
        </div>
        
        <div style="margin-top: 30px; text-align: center;">
            <a href="session_timeout_test.php" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;">
                Test Normal (60 min)
            </a>
            <a href="dashboard.php" style="background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;">
                Dashboard
            </a>
            <a href="auth/logout.php" style="background: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
                Cerrar Sesión
            </a>
        </div>
    </div>
</body>
</html> 