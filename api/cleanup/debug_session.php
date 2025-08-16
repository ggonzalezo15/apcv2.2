<?php
/**
 * Script para debugear el estado de la sesión y autorización
 */

// Iniciar o reanudar sesión
session_start();

// Verificar si se ejecuta desde CLI o web
$isCLI = (php_sapi_name() === 'cli');

if (!$isCLI) {
    echo "<h2>🔍 Debug de Sesión y Autorización</h2>\n";
    echo "<pre>\n";
} else {
    echo "🔍 Debug de Sesión y Autorización\n";
    echo str_repeat("=", 40) . "\n";
}

echo "📅 Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo "🆔 Session ID: " . session_id() . "\n";
echo "🖥️  Entorno: " . ($isCLI ? "CLI" : "Web") . "\n\n";

echo "👤 DATOS DE SESIÓN:\n";
echo str_repeat("-", 30) . "\n";

if (empty($_SESSION)) {
    echo "❌ No hay datos de sesión activos.\n";
} else {
    foreach ($_SESSION as $key => $value) {
        if (is_array($value) || is_object($value)) {
            echo "{$key}: " . json_encode($value, JSON_PRETTY_PRINT) . "\n";
        } else {
            echo "{$key}: {$value}\n";
        }
    }
}

echo "\n🔑 VERIFICACIONES DE AUTORIZACIÓN:\n";
echo str_repeat("-", 35) . "\n";

// Verificar si el usuario está logueado
$isLoggedIn = isset($_SESSION['user_id']);
echo "¿Usuario logueado?: " . ($isLoggedIn ? "✅ SÍ" : "❌ NO") . "\n";

if ($isLoggedIn) {
    echo "User ID: " . $_SESSION['user_id'] . "\n";
    echo "Username: " . ($_SESSION['username'] ?? 'No definido') . "\n";
    echo "Role: " . ($_SESSION['role'] ?? 'No definido') . "\n";
    
    // Verificar si es admin
    $isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    echo "¿Es Admin?: " . ($isAdmin ? "✅ SÍ" : "❌ NO") . "\n";
    
    if (!$isAdmin) {
        echo "⚠️  ACCESO DENEGADO: El usuario no tiene rol de administrador.\n";
    }
    
} else {
    echo "⚠️  ACCESO DENEGADO: No hay usuario logueado.\n";
}

if (!$isCLI) {
    echo "\n🌐 INFORMACIÓN DEL REQUEST:\n";
    echo str_repeat("-", 30) . "\n";
    echo "Método: " . ($_SERVER['REQUEST_METHOD'] ?? 'No definido') . "\n";
    echo "URI: " . ($_SERVER['REQUEST_URI'] ?? 'No definido') . "\n";
    echo "User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'No definido') . "\n";
    echo "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'No definido') . "\n";

    echo "\n🍪 COOKIES:\n";
    echo str_repeat("-", 15) . "\n";
    if (empty($_COOKIE)) {
        echo "No hay cookies disponibles.\n";
    } else {
        foreach ($_COOKIE as $name => $value) {
            // No mostrar valores de cookies por seguridad, solo nombres
            echo "{$name}: [PRESENTE]\n";
        }
    }

    echo "\n📋 HEADERS HTTP:\n";
    echo str_repeat("-", 20) . "\n";
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if ($headers) {
            foreach ($headers as $name => $value) {
                // No mostrar headers sensibles
                if (stripos($name, 'authorization') !== false || stripos($name, 'cookie') !== false) {
                    echo "{$name}: [OCULTO POR SEGURIDAD]\n";
                } else {
                    echo "{$name}: {$value}\n";
                }
            }
        } else {
            echo "No se pudieron obtener headers.\n";
        }
    } else {
        echo "Función getallheaders() no disponible en este entorno.\n";
    }
} else {
    echo "\n📝 NOTA: Información de request no disponible en CLI.\n";
}

echo "\n🧪 SIMULACIÓN DE VERIFICACIÓN API:\n";
echo str_repeat("-", 35) . "\n";

// Simular la lógica de verificación de la API de logs
if (!isset($_SESSION['user_id'])) {
    echo "❌ FALLO: No hay user_id en sesión\n";
    echo "   → La API retornaría: 'Acceso no autorizado'\n";
} elseif (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo "❌ FALLO: Usuario no es admin\n";
    echo "   → Role actual: " . ($_SESSION['role'] ?? 'undefined') . "\n";
    echo "   → La API retornaría: 'Acceso no autorizado'\n";
} else {
    echo "✅ ÉXITO: Usuario autorizado para acceder a logs\n";
    echo "   → La API debería funcionar correctamente\n";
}

echo "\n💡 RECOMENDACIONES:\n";
echo str_repeat("-", 20) . "\n";

if (!$isLoggedIn) {
    echo "1. Asegúrate de estar logueado en el sistema.\n";
    echo "2. Verifica que la sesión se esté manteniendo correctamente.\n";
    echo "3. Revisa las cookies de sesión en el navegador.\n";
} elseif (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo "1. Verifica que tu usuario tenga rol 'admin' en la base de datos.\n";
    echo "2. Cierra sesión y vuelve a iniciar sesión.\n";
    echo "3. Contacta al administrador del sistema si el problema persiste.\n";
} else {
    echo "✅ Todo parece estar correcto. Si hay errores, revisa:\n";
    echo "1. La configuración de la base de datos en la API.\n";
    echo "2. Los permisos de archivos PHP.\n";
    echo "3. Los logs del servidor web.\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "✅ Debug de sesión completado.\n";

if (!$isCLI) {
    echo "</pre>\n";
}
?>
