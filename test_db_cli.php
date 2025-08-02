<?php
// Script simple para probar conexión
try {
    $pdo = new PDO("mysql:host=168.231.68.229;port=3306;dbname=cloude_apcuadre;charset=utf8mb4", 'workbench_user', 'Mysql2025#');
    echo "✅ Conexión exitosa a la base de datos\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM expense_attachments");
    $result = $stmt->fetch();
    echo "📊 Total attachments en BD: " . $result['count'] . "\n";
    
} catch (PDOException $e) {
    echo "❌ Error de conexión: " . $e->getMessage() . "\n";
}
?>
