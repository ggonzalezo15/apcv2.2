<?php
/**
 * Optimizador final del esquema para Hostinger
 * Limpia elementos problemáticos para hosting compartido
 */

$inputFile = 'hostinger_schema_final.sql';
$outputFile = 'hostinger_schema_production.sql';

if (!file_exists($inputFile)) {
    die("❌ Archivo no encontrado: $inputFile\n");
}

$content = file_get_contents($inputFile);

// Optimizaciones para Hostinger
$optimizations = [
    // Remover CHARACTER SET específicos (usar default)
    '/CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci/' => 'COLLATE utf8mb4_unicode_ci',
    
    // Simplificar CONSTRAINT names (algunos hosting no permiten nombres largos)
    '/CONSTRAINT `([^`]+)` FOREIGN KEY/' => 'FOREIGN KEY',
    
    // Remover comentarios de AUTO_INCREMENT específicos
    '/AUTO_INCREMENT=\d+/' => '',
    
    // Asegurar que no hay DEFINER
    '/DEFINER=`[^`]+`@`[^`]+`/' => '',
    
    // Limpiar espacios extra
    '/\s+/' => ' ',
    '/\n\s*\n/' => "\n",
];

foreach ($optimizations as $pattern => $replacement) {
    $content = preg_replace($pattern, $replacement, $content);
}

// Agregar comentarios útiles
$header = "-- ============================================================\n";
$header .= "-- ESQUEMA FINAL PARA HOSTINGER - PRODUCCIÓN\n";
$header .= "-- Generado: " . date('Y-m-d H:i:s') . "\n";
$header .= "-- Optimizado para hosting compartido\n";
$header .= "-- \n";
$header .= "-- INSTRUCCIONES DE INSTALACIÓN:\n";
$header .= "-- 1. Crear base de datos en Hostinger\n";
$header .= "-- 2. Importar este archivo completo\n";
$header .= "-- 3. Verificar que todas las tablas se crearon\n";
$header .= "-- 4. Actualizar config.php con nuevas credenciales\n";
$header .= "-- ============================================================\n\n";

$content = $header . $content;

// Agregar solo usuario administrador
$content .= "\n-- ============================================================\n";
$content .= "-- USUARIO ADMINISTRADOR INICIAL\n";
$content .= "-- ============================================================\n\n";

$content .= "-- Usuario administrador por defecto (CAMBIAR CONTRASEÑA DESPUÉS DEL PRIMER LOGIN)\n";
$content .= "INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `active`, `created_at`) VALUES\n";
$content .= "(1, 'admin', 'admin@apcuadre.com', '$2y$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1, NOW());\n\n";

file_put_contents($outputFile, $content);

echo "✅ Esquema de producción generado: $outputFile\n";
echo "📊 Tamaño final: " . number_format(strlen($content)) . " caracteres\n";
echo "🚀 Listo para importar en Hostinger!\n\n";

echo "📋 PRÓXIMOS PASOS:\n";
echo "1. Descargar el archivo: $outputFile\n";
echo "2. Ir a phpMyAdmin en Hostinger\n";
echo "3. Crear nueva base de datos\n";
echo "4. Importar el archivo SQL\n";
echo "5. Actualizar credenciales en config.php\n";
echo "6. Crear equipos, tipos de gastos y otros datos según necesites\n";
echo "7. Probar la aplicación\n\n";

echo "🔐 CREDENCIALES INICIALES:\n";
echo "Usuario: admin\n";
echo "Contraseña: password\n";
echo "⚠️  CAMBIAR CONTRASEÑA INMEDIATAMENTE DESPUÉS DEL PRIMER LOGIN\n\n";

echo "📊 TABLAS INCLUIDAS:\n";
echo "- Todas las tablas sin datos (estructura únicamente)\n";
echo "- Solo usuario administrador incluido\n";
echo "- Vistas optimizadas para reportes\n";
?>