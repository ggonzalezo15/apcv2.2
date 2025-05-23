<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Incluir configuración
require_once 'config.php';

echo "<h2>🔍 Verificación del Sistema APCUADRE</h2>";

try {
    // Probar conexión a base de datos
    $pdo = getConnection();
    echo "<p style='color: green;'>✅ Conexión a base de datos exitosa</p>";
    
    // Verificar que las tablas existen
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h3>📋 Tablas en la base de datos:</h3>";
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li>$table</li>";
    }
    echo "</ul>";
    
    // Verificar usuarios
    if (in_array('users', $tables)) {
        $stmt = $pdo->query("SELECT username, email, role, active FROM users");
        $users = $stmt->fetchAll();
        
        echo "<h3>👥 Usuarios registrados:</h3>";
        if (count($users) > 0) {
            echo "<table border='1' style='border-collapse: collapse;'>";
            echo "<tr><th>Usuario</th><th>Email</th><th>Rol</th><th>Activo</th></tr>";
            foreach ($users as $user) {
                $status = $user['active'] ? '✅' : '❌';
                echo "<tr>";
                echo "<td>{$user['username']}</td>";
                echo "<td>{$user['email']}</td>";
                echo "<td>{$user['role']}</td>";
                echo "<td>$status</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p style='color: orange;'>⚠️ No hay usuarios registrados</p>";
        }
        
        // Verificar contraseña del admin
        $stmt = $pdo->prepare("SELECT password FROM users WHERE username = 'admin'");
        $stmt->execute();
        $admin = $stmt->fetch();
        
        if ($admin) {
            $test_password = 'admin123';
            if (password_verify($test_password, $admin['password'])) {
                echo "<p style='color: green;'>✅ Contraseña del admin verificada: <strong>admin123</strong></p>";
            } else {
                echo "<p style='color: red;'>❌ Error en la contraseña del admin</p>";
            }
        } else {
            echo "<p style='color: red;'>❌ Usuario admin no encontrado</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Tabla 'users' no existe. Ejecuta el script SQL primero.</p>";
    }
    
    // Verificar otras tablas importantes
    $important_tables = ['teams', 'contractors', 'vendors', 'incomes', 'expenses'];
    echo "<h3>🏢 Tablas del sistema APCUADRE:</h3>";
    foreach ($important_tables as $table) {
        if (in_array($table, $tables)) {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
            $count = $stmt->fetch()['count'];
            echo "<p style='color: green;'>✅ $table ($count registros)</p>";
        } else {
            echo "<p style='color: orange;'>⚠️ $table (no existe)</p>";
        }
    }
    
} catch(PDOException $e) {
    echo "<p style='color: red;'>❌ Error de conexión: " . $e->getMessage() . "</p>";
    echo "<h3>🔧 Posibles soluciones:</h3>";
    echo "<ul>";
    echo "<li>Verificar que MySQL esté ejecutándose</li>";
    echo "<li>Verificar usuario y contraseña en config.php</li>";
    echo "<li>Ejecutar el script SQL para crear la base de datos</li>";
    echo "</ul>";
}

echo "<hr>";
echo "<h3>🚀 Próximos pasos:</h3>";
echo "<ol>";
echo "<li>Si ves ✅ en todo, ve a: <a href='login.php'>login.php</a></li>";
echo "<li>Si hay errores, ejecuta primero el script SQL en MySQL</li>";
echo "<li>Credenciales de prueba: admin / admin123</li>";
echo "</ol>";
?>
