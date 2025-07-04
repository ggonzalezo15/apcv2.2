<?php
require_once 'config.php';

// Solo accesible para usuarios logueados
if (!isLoggedIn()) {
    header('Location: auth/login.php');
    exit;
}

// Verificar timeout (esto debería redirigir si ha expirado)
if (!checkSessionTimeout()) {
    header('Location: auth/login.php?timeout=1');
    exit;
}

$timeoutInfo = getSessionTimeoutInfo();
$securityStatus = getSessionSecurityStatus();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test de Timeout Automático</title>
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
        .auto-refresh {
            text-align: center;
            margin: 20px 0;
            padding: 10px;
            background-color: #e7f3ff;
            border-radius: 5px;
        }
        .countdown {
            font-size: 24px;
            font-weight: bold;
            color: #2563eb;
            margin: 20px 0;
            text-align: center;
        }
    </style>
    <script>
        // Función para obtener información actualizada vía AJAX
        function fetchSessionInfo() {
            fetch('session_timeout_ajax.php')
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
                    } else if (data.time_until_expiry <= 300) {
                        statusDiv.innerHTML = '<div class="warning"><strong>⚠️ Advertencia:</strong> La sesión expirará en menos de 5 minutos.</div>';
                    } else {
                        statusDiv.innerHTML = '<div class="success"><strong>✅ Sesión Activa:</strong> El timeout automático está funcionando correctamente.</div>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        }
        
        // Countdown timer basado en último fetch
        let timeLeft = <?php echo $timeoutInfo['time_until_expiry']; ?>;
        function updateCountdown() {
            const countdownElement = document.getElementById('countdown');
            if (timeLeft > 0) {
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                countdownElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
                timeLeft--;
            } else {
                countdownElement.textContent = 'SESIÓN EXPIRADA';
                countdownElement.style.color = '#dc3545';
            }
        }
        
        // Actualizar countdown cada segundo
        setInterval(updateCountdown, 1000);
        updateCountdown();
        
        // Obtener información actualizada cada 10 segundos (SIN recargar página)
        setInterval(fetchSessionInfo, 10000);
    </script>
</head>
<body>
    <div class="container">
        <h1>⏰ Test de Timeout Automático de Sesión</h1>
        
        <div class="auto-refresh">
            <strong>📡 Actualización AJAX:</strong> Los datos se actualizan cada 10 segundos SIN recargar la página
        </div>
        
        <div class="countdown">
            <div>Tiempo restante: <span id="countdown"></span></div>
        </div>
        
        <?php if ($timeoutInfo): ?>
            <div class="status-item">
                <span>Usuario:</span>
                <span class="status-value"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
            </div>
            
            <div class="status-item">
                <span>Última Actividad:</span>
                <span class="status-value"><?php echo date('H:i:s', $timeoutInfo['last_activity']); ?></span>
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
                <span>Duración de Sesión:</span>
                <span class="status-value"><?php echo $timeoutInfo['session_duration']; ?> segundos</span>
            </div>
            
            <div class="status-item">
                <span>Límite de Timeout:</span>
                <span class="status-value"><?php echo $timeoutInfo['timeout_limit']; ?> segundos</span>
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
                <?php elseif ($timeoutInfo['time_until_expiry'] <= 300): ?>
                    <div class="warning">
                        <strong>⚠️ Advertencia:</strong> La sesión expirará en menos de 5 minutos.
                    </div>
                <?php else: ?>
                    <div class="success">
                        <strong>✅ Sesión Activa:</strong> El timeout automático está funcionando correctamente.
                    </div>
                <?php endif; ?>
            </div>
            
        <?php else: ?>
            <div class="danger">
                <strong>❌ Error:</strong> No se pudo obtener información del timeout.
            </div>
        <?php endif; ?>
        
        <h3>🔧 Configuración de Timeout</h3>
        <ul>
            <li><strong>Timeout configurado:</strong> <?php echo SESSION_TIMEOUT; ?> segundos (<?php echo SESSION_TIMEOUT/60; ?> minutos)</li>
            <li><strong>Verificación automática:</strong> ✅ En cada página protegida</li>
            <li><strong>Registro de logout:</strong> ✅ Se registra en audit log</li>
            <li><strong>Limpieza de sesión:</strong> ✅ session_unset() y session_destroy()</li>
        </ul>
        
        <h3>🧪 Cómo Probar</h3>
        <ol>
            <li><strong>Deja esta página abierta</strong> y observa el countdown</li>
            <li><strong>NO navegues a otras páginas</strong> para evitar actualizar la sesión</li>
            <li><strong>Espera hasta que el tiempo llegue a 0</strong> (60 minutos)</li>
            <li><strong>Los datos se actualizan cada 10 segundos</strong> vía AJAX sin recargar</li>
            <li><strong>Cuando expire</strong>, serás redirigido automáticamente al login</li>
            <li><strong>O navega a otra página protegida</strong> después de la expiración para probar</li>
        </ol>
        
        <div style="text-align: center; margin: 20px 0;">
            <button onclick="fetchSessionInfo()" style="background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">
                🔄 Actualizar Información Manualmente
            </button>
        </div>
        
        <div class="warning">
            <strong>⚠️ Importante:</strong> Estos archivos son solo para pruebas. 
            <strong>Elimínalos en producción</strong> por razones de seguridad:
            <ul style="margin-top: 10px;">
                <li><code>session_timeout_test.php</code></li>
                <li><code>session_timeout_ajax.php</code></li>
                <li><code>session_timeout_quick_test.php</code></li>
                <li><code>session_timeout_quick_ajax.php</code></li>
                <li><code>session_security_test.php</code></li>
            </ul>
        </div>
        
        <div style="margin-top: 30px; text-align: center;">
            <a href="session_timeout_quick_test.php" style="background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;">
                ⚡ Test Rápido (30s)
            </a>
            <a href="dashboard.php" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;">
                Volver al Dashboard
            </a>
            <a href="auth/logout.php" style="background: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
                Cerrar Sesión Manual
            </a>
        </div>
    </div>
</body>
</html> 