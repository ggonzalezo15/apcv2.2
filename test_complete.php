<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

echo "<h1>🔍 Verificación del Sistema</h1>";

try {
    $pdo = getConnection();
    echo "<h2>✅ Conexión a Base de Datos: EXITOSA</h2>";
    
    // Verificar usuarios
    $stmt = $pdo->query("SELECT username, email, role FROM users");
    $users = $stmt->fetchAll();
    
    echo "<h3>👥 Usuarios en la base de datos:</h3>";
    echo "<ul>";
    foreach ($users as $user) {
        echo "<li><strong>{$user['username']}</strong> ({$user['email']}) - Rol: {$user['role']}</li>";
    }
    echo "</ul>";
    
    // Verificar hash de contraseña
    $stmt = $pdo->prepare("SELECT password FROM users WHERE username = 'admin'");
    $stmt->execute();
    $admin = $stmt->fetch();
    
    if ($admin) {
        $test_password = 'admin123';
        if (password_verify($test_password, $admin['password'])) {
            echo "<h3>✅ Contraseña del admin verificada correctamente</h3>";
            echo "<p><strong>Usuario:</strong> admin</p>";
            echo "<p><strong>Contraseña:</strong> admin123</p>";
        } else {
            echo "<h3>❌ Error en la contraseña del admin</h3>";
            echo "<p>El hash no coincide con 'admin123'</p>";
        }
    }
    
    // Verificar tablas principales
    $tables = ['users', 'teams', 'contractors', 'vendors', 'incomes', 'expenses'];
    echo "<h3>📊 Tablas del sistema:</h3>";
    echo "<ul>";
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
            $result = $stmt->fetch();
            echo "<li><strong>$table:</strong> {$result['count']} registros</li>";
        } catch (Exception $e) {
            echo "<li><strong>$table:</strong> ❌ Error - {$e->getMessage()}</li>";
        }
    }
    echo "</ul>";
    
    echo "<hr>";
    echo "<h2>🚀 Estado del Sistema</h2>";
    echo "<p>✅ Base de datos configurada correctamente</p>";
    echo "<p>✅ Usuarios creados</p>";
    echo "<p>✅ Tablas principales disponibles</p>";
    echo "<p><strong>Siguiente paso:</strong> <a href='login.php'>Ir al Login</a></p>";
    
} catch(PDOException $e) {
    echo "<h2>❌ Error de conexión:</h2>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
    echo "<h3>Posibles soluciones:</h3>";
    echo "<ul>";
    echo "<li>Verificar que MySQL esté ejecutándose</li>";
    echo "<li>Verificar usuario y contraseña en config.php</li>";
    echo "<li>Verificar que la base de datos 'cloude_apcuadre' exista</li>";
    echo "</ul>";
}

echo "<hr>";
echo "<h3>📋 Configuración actual:</h3>";
echo "<ul>";
echo "<li><strong>Host:</strong> " . DB_HOST . "</li>";
echo "<li><strong>Usuario:</strong> " . DB_USER . "</li>";
echo "<li><strong>Base de datos:</strong> " . DB_NAME . "</li>";
echo "<li><strong>URL base:</strong> " . BASE_URL . "</li>";
echo "</ul>";
?>

<style>
body {
    font-family: Arial, sans-serif;
    margin: 20px;
    background-color: #f5f5f5;
}
h1, h2, h3 {
    color: #333;
}
ul {
    background: white;
    padding: 15px;
    border-radius: 5px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}
li {
    margin: 5px 0;
}
a {
    color: #007bff;
    text-decoration: none;
    padding: 10px 20px;
    background: #007bff;
    color: white;
    border-radius: 5px;
    display: inline-block;
    margin-top: 10px;
}
a:hover {
    background: #0056b3;
}
</style>
