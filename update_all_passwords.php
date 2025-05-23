<?php
// Actualizar todas las contraseñas del sistema
require_once 'config.php';

echo "<h2>🔐 Actualización de Contraseñas del Sistema</h2>";

try {
    $pdo = getConnection();
    
    // Generar hashes para las contraseñas
    $adminHash = password_hash('admin123', PASSWORD_DEFAULT);
    $userHash = password_hash('user123', PASSWORD_DEFAULT);
    
    echo "<h3>🔄 Actualizando contraseñas...</h3>";
    
    // Actualizar admin
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
    $adminResult = $stmt->execute([$adminHash]);
    
    // Actualizar user
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'user'");
    $userResult = $stmt->execute([$userHash]);
    
    if ($adminResult && $userResult) {
        echo "<p style='color: green;'>✅ Todas las contraseñas actualizadas exitosamente</p>";
        
        // Verificar ambas contraseñas
        echo "<h3>🧪 Verificaciones:</h3>";
        
        // Verificar admin
        $stmt = $pdo->prepare("SELECT password FROM users WHERE username = 'admin'");
        $stmt->execute();
        $admin = $stmt->fetch();
        
        if ($admin && password_verify('admin123', $admin['password'])) {
            echo "<p style='color: green;'>✅ Admin: admin / admin123</p>";
        } else {
            echo "<p style='color: red;'>❌ Error con admin</p>";
        }
        
        // Verificar user
        $stmt = $pdo->prepare("SELECT password FROM users WHERE username = 'user'");
        $stmt->execute();
        $user = $stmt->fetch();
        
        if ($user && password_verify('user123', $user['password'])) {
            echo "<p style='color: green;'>✅ User: user / user123</p>";
        } else {
            echo "<p style='color: red;'>❌ Error con user</p>";
        }
        
        echo "<hr>";
        echo "<h3>🎉 ¡Sistema Listo!</h3>";
        echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; border-left: 4px solid #28a745;'>";
        echo "<h4>Credenciales disponibles:</h4>";
        echo "<p><strong>👑 Administrador:</strong><br>";
        echo "Usuario: <code>admin</code><br>";
        echo "Contraseña: <code>admin123</code></p>";
        echo "<p><strong>👤 Usuario normal:</strong><br>";
        echo "Usuario: <code>user</code><br>";
        echo "Contraseña: <code>user123</code></p>";
        echo "</div>";
        
    } else {
        echo "<p style='color: red;'>❌ Error al actualizar las contraseñas</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<div style='text-align: center;'>";
echo "<a href='login.php' style='background: #007bff; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-size: 18px; margin: 10px;'>🚀 Ir al Login</a>";
echo "<a href='test_connection.php' style='background: #17a2b8; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-size: 18px; margin: 10px;'>🔍 Verificar Sistema</a>";
echo "</div>";
?>

<style>
body {
    font-family: Arial, sans-serif;
    margin: 20px;
    background-color: #f8f9fa;
}
code {
    background: #e9ecef;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: 'Courier New', monospace;
}
h2, h3 {
    color: #333;
}
</style>
