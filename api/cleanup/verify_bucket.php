<?php
/**
 * Script para verificar la estructura del bucket B2
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/B2FileUploader.php';

try {
    // Inicializar B2
    $uploader = new B2FileUploader();
    
    echo "<h2>🔍 Verificación del Bucket B2</h2>\n";
    echo "<pre>\n";
    
    // Probar conexión
    echo "� Probando conexión a B2...\n";
    $connectionTest = $uploader->testConnection();
    
    if ($connectionTest['success']) {
        echo "✅ Conexión exitosa a B2\n";
        echo "📦 Bucket: " . ($connectionTest['bucket'] ?? 'No disponible') . "\n\n";
    } else {
        echo "❌ Error de conexión: " . $connectionTest['error'] . "\n";
        exit;
    }
    
    // Verificar si la carpeta clean_logs existe
    echo "� Verificando carpeta 'clean_logs/'...\n";
    
    $folderExists = $uploader->folderExists('clean_logs/');
    
    if ($folderExists) {
        echo "✅ La carpeta 'clean_logs/' existe en el bucket.\n";
    } else {
        echo "⚠️  La carpeta 'clean_logs/' no existe o está vacía.\n";
        
        // Intentar crear un archivo de prueba para crear la carpeta
        echo "\n🧪 Intentando crear archivo de prueba para inicializar la carpeta...\n";
        
        $testData = [
            'test' => true,
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => 'Archivo de prueba para verificar la carpeta clean_logs',
            'created_by' => 'bucket_verification_script'
        ];
        
        // Crear archivo temporal
        $tempFile = tempnam(sys_get_temp_dir(), 'b2_test_');
        file_put_contents($tempFile, json_encode($testData, JSON_PRETTY_PRINT));
        
        // Simular un archivo upload
        $_FILES['test'] = [
            'name' => 'test_clean_logs_' . date('Y-m-d_H-i-s') . '.json',
            'type' => 'application/json',
            'tmp_name' => $tempFile,
            'error' => 0,
            'size' => filesize($tempFile)
        ];
        
        // Intentar subir archivo (esto creará la carpeta si no existe)
        $uploadResult = $uploader->uploadFile($_FILES['test'], 'test', date('Y-m-d'));
        
        if ($uploadResult['success']) {
            echo "✅ Archivo de prueba creado exitosamente.\n";
            echo "🔗 URL: " . $uploadResult['url'] . "\n";
            echo "� File Key: " . $uploadResult['file_key'] . "\n";
            
            // Verificar nuevamente si la carpeta existe
            echo "\n🔄 Verificando nuevamente la carpeta...\n";
            $folderExists = $uploader->folderExists('clean_logs/');
            
            if ($folderExists) {
                echo "✅ La carpeta 'clean_logs/' ahora existe.\n";
            }
            
            // Limpiar archivo de prueba
            echo "\n🗑️  Eliminando archivo de prueba...\n";
            $deleteResult = $uploader->deleteFile($uploadResult['file_key']);
            
            if ($deleteResult) {
                echo "✅ Archivo de prueba eliminado exitosamente.\n";
            } else {
                echo "⚠️  No se pudo eliminar el archivo de prueba.\n";
            }
            
        } else {
            echo "❌ Error creando archivo de prueba: " . $uploadResult['error'] . "\n";
        }
        
        // Limpiar archivo temporal
        unlink($tempFile);
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "✅ Verificación del bucket completada.\n";
    
} catch (Exception $e) {
    echo "❌ Error durante la verificación: " . $e->getMessage() . "\n";
    echo "📋 Trace: " . $e->getTraceAsString() . "\n";
}

echo "</pre>\n";
?>
