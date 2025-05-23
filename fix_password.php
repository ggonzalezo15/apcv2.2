<?php
// Generar hash correcto para admin123
$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "<h2>🔐 Generador de Hash de Contraseña</h2>";
echo "<p><strong>Contraseña:</strong> $password</p>";
echo "<p><strong>Hash generado:</strong> $hash</p>";

// Verificar que el hash funciona
if (password_verify($password, $hash)) {
    echo "<p style='color: green;'>✅ Hash verificado correctamente</p>";
} else {
    echo "<p style='color: red;'>❌ Error en la verificación del hash</p>";
}

echo "<hr>";
echo "<h3>SQL para actualizar la base de datos:</h3>";
echo "<pre>";
echo "UPDATE users SET password = '$hash' WHERE username = 'admin';";
echo "</pre>";

// Ahora actualizar directamente en la base de datos
require_once 'config.php';

try {
    $pdo = getConnection();
    
    echo "<h3>🔄 Actualizando contraseña en la base de datos...</h3>";
    
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
    $result = $stmt->execute([$hash]);
    
    if ($result) {
        echo "<p style='color: green;'>✅ Contraseña actualizada exitosamente</p>";
        
        // Verificar la actualización
        $stmt = $pdo->prepare("SELECT password FROM users WHERE username = 'admin'");
        $stmt->execute();
        $admin = $stmt->fetch();
        
        if ($admin && password_verify($password, $admin['password'])) {
            echo "<p style='color: green;'>✅ Verificación final: La contraseña funciona correctamente</p>";
            echo "<p><strong>Ahora puedes usar:</strong></p>";
            echo "<ul>";
            echo "<li><strong>Usuario:</strong> admin</li>";
            echo "<li><strong>Contraseña:</strong> admin123</li>";
            echo "</ul>";
        } else {
            echo "<p style='color: red;'>❌ Error en la verificación final</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Error al actualizar la contraseña</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='test_connection.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🔍 Verificar Sistema</a></p>";
echo "<p><a href='login.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🔐 Ir al Login</a></p>";
?>

<style>
body {
    font-family: Arial, sans-serif;
    margin: 20px;
    background-color: #f5f5f5;
}
pre {
    background: #333;
    color: #fff;
    padding: 15px;
    border-radius: 5px;
    overflow-x: auto;
}
</style>
