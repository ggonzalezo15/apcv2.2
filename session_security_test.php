<?php
require_once 'config.php';

// Solo accesible para administradores o en desarrollo
if (!isLoggedIn() && $_SERVER['HTTP_HOST'] !== 'localhost') {
    header('Location: auth/login.php');
    exit;
}

$securityStatus = getSessionSecurityStatus();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test de Seguridad de Sesiones</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
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
        .high-security { color: #28a745; }
        .medium-security { color: #ffc107; }
        .low-security { color: #dc3545; }
        .enabled { color: #28a745; }
        .disabled { color: #dc3545; }
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
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Test de Seguridad de Sesiones</h1>
        
        <div class="status-item">
            <span>Nombre de Sesión:</span>
            <span class="status-value"><?php echo $securityStatus['session_name']; ?></span>
        </div>
        
        <div class="status-item">
            <span>Secure Cookie:</span>
            <span class="status-value <?php echo $securityStatus['secure'] ? 'enabled' : 'disabled'; ?>">
                <?php echo $securityStatus['secure'] ? 'HABILITADO' : 'DESHABILITADO'; ?>
            </span>
        </div>
        
        <div class="status-item">
            <span>HttpOnly Cookie:</span>
            <span class="status-value <?php echo $securityStatus['httponly'] ? 'enabled' : 'disabled'; ?>">
                <?php echo $securityStatus['httponly'] ? 'HABILITADO' : 'DESHABILITADO'; ?>
            </span>
        </div>
        
        <div class="status-item">
            <span>SameSite:</span>
            <span class="status-value"><?php echo $securityStatus['samesite']; ?></span>
        </div>
        
        <div class="status-item">
            <span>Lifetime:</span>
            <span class="status-value"><?php echo $securityStatus['lifetime']; ?> segundos</span>
        </div>
        
        <div class="status-item">
            <span>HTTPS Detectado:</span>
            <span class="status-value <?php echo $securityStatus['is_https'] ? 'enabled' : 'disabled'; ?>">
                <?php echo $securityStatus['is_https'] ? 'SÍ' : 'NO'; ?>
            </span>
        </div>
        
        <div class="status-item">
            <span>ID de Sesión:</span>
            <span class="status-value"><?php echo substr($securityStatus['session_id'], 0, 16) . '...'; ?></span>
        </div>
        
        <div class="status-item">
            <span>Nivel de Seguridad:</span>
            <span class="status-value <?php echo strtolower($securityStatus['security_level']); ?>-security">
                <?php echo $securityStatus['security_level']; ?>
            </span>
        </div>
        
        <?php if (!$securityStatus['is_https'] && $securityStatus['secure']): ?>
            <div class="warning">
                <strong>⚠️ Advertencia:</strong> El flag 'secure' está habilitado pero no se detectó HTTPS. 
                Las cookies podrían no funcionar correctamente.
            </div>
        <?php endif; ?>
        
        <?php if ($securityStatus['security_level'] === 'HIGH'): ?>
            <div class="success">
                <strong>✅ Excelente:</strong> Todas las configuraciones de seguridad están habilitadas correctamente.
            </div>
        <?php endif; ?>
        
        <h3>🛡️ Configuraciones de Seguridad Activas</h3>
        <ul>
            <li><strong>Secure:</strong> <?php echo $securityStatus['secure'] ? '✅' : '❌'; ?> Las cookies solo se envían por HTTPS</li>
            <li><strong>HttpOnly:</strong> <?php echo $securityStatus['httponly'] ? '✅' : '❌'; ?> Las cookies no son accesibles desde JavaScript</li>
            <li><strong>SameSite:</strong> <?php echo $securityStatus['samesite'] ? '✅' : '❌'; ?> Previene ataques CSRF</li>
            <li><strong>Strict Mode:</strong> ✅ Solo acepta IDs de sesión válidos</li>
            <li><strong>Use Only Cookies:</strong> ✅ No permite IDs de sesión por URL</li>
        </ul>
        
        <div style="margin-top: 30px; text-align: center;">
            <a href="dashboard.php" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
                Volver al Dashboard
            </a>
        </div>
    </div>
</body>
</html> 