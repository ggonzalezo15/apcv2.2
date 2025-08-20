<?php
/**
 * SCRIPT DE PRUEBA DE CONEXIÓN PARA HOSTINGER
 * Usar para verificar que la configuración es correcta
 */

// Configuración temporal para pruebas
$db_host = 'localhost';
$db_name = 'u496363305_apc2';
$db_user = 'u496363305_apc2';  // Normalmente igual al nombre de BD
$db_pass = 'TU_CONTRASEÑA_AQUI'; // ⚠️ CAMBIAR por tu contraseña real

echo "<h2>🔧 Prueba de Conexión a Hostinger</h2>";
echo "<hr>";

echo "<h3>📋 Configuración:</h3>";
echo "<ul>";
echo "<li><strong>Host:</strong> $db_host</li>";
echo "<li><strong>Base de datos:</strong> $db_name</li>";
echo "<li><strong>Usuario:</strong> $db_user</li>";
echo "<li><strong>Contraseña:</strong> " . (strlen($db_pass) > 5 ? str_repeat('*', strlen($db_pass)) : '⚠️ NO CONFIGURADA') . "</li>";
echo "</ul>";

echo "<h3>🔗 Probando conexión...</h3>";

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<div style='color: green; font-weight: bold;'>✅ CONEXIÓN EXITOSA</div>";
    
    // Probar consulta básica
    $stmt = $pdo->query("SELECT COUNT(*) as total_tables FROM information_schema.tables WHERE table_schema = '$db_name'");
    $result = $stmt->fetch();
    
    echo "<p><strong>Tablas encontradas:</strong> " . $result['total_tables'] . "</p>";
    
    // Verificar si existe la tabla users
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        echo "<div style='color: green;'>✅ Tabla 'users' encontrada</div>";
        
        // Verificar usuario admin
        $stmt = $pdo->query("SELECT username, email, role FROM users WHERE username = 'admin'");
        if ($user = $stmt->fetch()) {
            echo "<div style='color: green;'>✅ Usuario administrador encontrado:</div>";
            echo "<ul>";
            echo "<li><strong>Usuario:</strong> " . $user['username'] . "</li>";
            echo "<li><strong>Email:</strong> " . $user['email'] . "</li>";
            echo "<li><strong>Rol:</strong> " . $user['role'] . "</li>";
            echo "</ul>";
        } else {
            echo "<div style='color: orange;'>⚠️ Usuario administrador no encontrado</div>";
        }
    } else {
        echo "<div style='color: red;'>❌ Tabla 'users' no encontrada - ¿Importaste el esquema?</div>";
    }
    
    // Listar todas las tablas
    echo "<h3>📊 Tablas en la base de datos:</h3>";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($tables) > 0) {
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>$table</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color: red;'>❌ No se encontraron tablas. Necesitas importar el esquema.</p>";
    }
    
} catch (PDOException $e) {
    echo "<div style='color: red; font-weight: bold;'>❌ ERROR DE CONEXIÓN</div>";
    echo "<p><strong>Mensaje:</strong> " . $e->getMessage() . "</p>";
    
    echo "<h3>🔧 Posibles soluciones:</h3>";
    echo "<ul>";
    echo "<li>Verificar que la contraseña sea correcta</li>";
    echo "<li>Verificar que el nombre de usuario sea correcto (normalmente igual al nombre de BD)</li>";
    echo "<li>Verificar que la base de datos existe en tu panel de Hostinger</li>";
    echo "<li>Verificar que el host sea 'localhost' (estándar en Hostinger)</li>";
    echo "</ul>";
}

echo "<hr>";
echo "<h3>📋 Próximos pasos:</h3>";
echo "<ol>";
echo "<li>Si la conexión es exitosa, actualiza tu config.php con estas credenciales</li>";
echo "<li>Si no hay tablas, importa el archivo hostinger_schema_production.sql</li>";
echo "<li>Accede a /auth/login.php con admin/password</li>";
echo "<li>Cambia la contraseña inmediatamente</li>";
echo "<li>¡Elimina este archivo de prueba por seguridad!</li>";
echo "</ol>";

echo "<div style='background: #fff3cd; padding: 10px; border: 1px solid #ffeaa7; margin: 10px 0;'>";
echo "<strong>⚠️ IMPORTANTE:</strong> Elimina este archivo después de la prueba por seguridad.";
echo "</div>";
?>