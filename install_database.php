<?php
/**
 * Instalador de Base de Datos Limpia
 * Utiliza el esquema generado para crear la base de datos
 */

echo "🔧 INSTALADOR DE BASE DE DATOS LIMPIA\n";
echo str_repeat("=", 45) . "\n\n";

// Configuración (modificar según necesidades)
$config = [
    "host" => "localhost",
    "username" => "root", 
    "password" => "",
    "database" => "cloude_apcuadre"
];

echo "📋 Configuración:\n";
echo "   Host: {$config["host"]}\n";
echo "   Base de datos: {$config["database"]}\n\n";

try {
    echo "🔌 Conectando a MySQL...\n";
    $pdo = new PDO("mysql:host={$config["host"]};charset=utf8mb4", $config["username"], $config["password"]);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Conexión exitosa\n\n";
    
    echo "📄 Ejecutando esquema: clean_schema_2025_07_04_21_11_09.sql\n";
    $schema = file_get_contents("clean_schema_2025_07_04_21_11_09.sql");
    
    if (!$schema) {
        throw new Exception("No se pudo leer el archivo de esquema");
    }
    
    // Ejecutar esquema
    $pdo->exec($schema);
    
    echo "✅ Esquema ejecutado correctamente\n\n";
    
    // Verificar instalación
    echo "🔍 Verificando instalación...\n";
    $pdo->exec("USE `{$config["database"]}`");
    
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "📊 Tablas creadas: " . count($tables) . "\n";
    
    // Verificar usuario admin
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = \"admin\"");
    $adminCount = $stmt->fetchColumn();
    echo "👤 Usuarios admin: $adminCount\n\n";
    
    echo "🎉 INSTALACIÓN COMPLETADA EXITOSAMENTE\n";
    echo "\n📋 PRÓXIMOS PASOS:\n";
    echo "1. Cambiar contraseña del usuario admin\n";
    echo "2. Configurar archivo config.php\n";
    echo "3. Verificar funcionamiento del sistema\n";
    echo "4. Configurar BackBlaze B2 si es necesario\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "🔄 Verifique la configuración y vuelva a intentar\n";
}
?>