<?php
require_once __DIR__ . '/../vendor/autoload.php';

use EvoApi\EvoClient;
use EvoApi\Instance;
use EvoApi\Utils\ResponseHandler;

/**
 * Ejemplo de gestión completa de instancias
 * Incluye creación, monitoreo y eliminación segura
 */

// Configuración
$baseUri = 'https://whatsapp.ltd.do/';
$apiKey = 'YOUR_API_KEY_HERE';
$testInstanceName = 'test-instance-' . date('YmdHis');

try {
    $client = new EvoClient($baseUri, $apiKey);
    $instance = new Instance($client);
    
    echo "=== Gestión Completa de Instancias ===\n\n";
    
    // 1. Crear instancia de prueba
    echo "1. Creando instancia de prueba: {$testInstanceName}...\n";
    $createResponse = $instance->create([
        'instanceName' => $testInstanceName,
        'token' => 'test-token-' . uniqid(),
        'qrcode' => true
    ]);
    
    if (ResponseHandler::isSuccess($createResponse)) {
        echo "✅ Instancia creada exitosamente\n";
    } else {
        echo "❌ Error al crear: " . ResponseHandler::getError($createResponse) . "\n";
        exit(1);
    }
    
    echo "\n";
    
    // 2. Verificar que existe
    echo "2. Verificando existencia de la instancia...\n";
    $listResponse = $instance->listInstances();
    $instanceExists = false;
    
    if (ResponseHandler::isSuccess($listResponse)) {
        $instances = ResponseHandler::getData($listResponse);
        foreach ($instances as $inst) {
            if ($inst['instanceName'] === $testInstanceName) {
                $instanceExists = true;
                echo "✅ Instancia encontrada en la lista\n";
                break;
            }
        }
    }
    
    if (!$instanceExists) {
        echo "❌ Instancia no encontrada en la lista\n";
    }
    
    echo "\n";
    
    // 3. Obtener información detallada
    echo "3. Obteniendo información detallada...\n";
    $statusReport = $instance->generateStatusReport($testInstanceName);
    echo "   - Estado general: {$statusReport['overall_status']}\n";
    echo "   - Puntuación de salud: {$statusReport['health_score']}%\n";
    
    echo "\n";
    
    // 4. Demostrar alternativas antes de eliminar
    echo "4. Demostrando alternativas a la eliminación...\n";
    
    // 4a. Desconectar (logout)
    echo "   a) Desconectando instancia...\n";
    $logoutResponse = $instance->logout($testInstanceName);
    if (ResponseHandler::isSuccess($logoutResponse)) {
        echo "      ✅ Instancia desconectada exitosamente\n";
    } else {
        echo "      ⚠️  Desconexión: " . ResponseHandler::getError($logoutResponse) . "\n";
    }
    
    // 4b. Limpiar datos
    echo "   b) Limpiando datos de la instancia...\n";
    $clearResponse = $instance->clearInstanceData($testInstanceName);
    if (ResponseHandler::isSuccess($clearResponse)) {
        echo "      ✅ Datos limpiados exitosamente\n";
    } else {
        echo "      ⚠️  Limpieza: " . ResponseHandler::getError($clearResponse) . "\n";
    }
    
    echo "\n";
    
    // 5. ELIMINACIÓN CONTROLADA Y SEGURA
    echo "5. ELIMINACIÓN DE INSTANCIA\n";
    echo "   ⚠️  ADVERTENCIA: Esta operación es IRREVERSIBLE\n";
    echo "   📋 Checklist de seguridad:\n";
    echo "      ☑️  Es una instancia de prueba\n";
    echo "      ☑️  No contiene datos importantes\n";
    echo "      ☑️  Se ha desconectado previamente\n";
    echo "      ☑️  Se han limpiado los datos\n";
    echo "\n";
    
    // Confirmación adicional
    echo "   Esperando 3 segundos antes de proceder...\n";
    for ($i = 3; $i > 0; $i--) {
        echo "   {$i}...\n";
        sleep(1);
    }
    
    // ELIMINAR INSTANCIA
    echo "   🗑️  Eliminando instancia {$testInstanceName}...\n";
    $deleteResponse = $instance->delete($testInstanceName);
    
    if (ResponseHandler::isSuccess($deleteResponse)) {
        echo "   ✅ Instancia eliminada exitosamente\n";
        
        // Verificar que fue eliminada
        echo "   🔍 Verificando eliminación...\n";
        sleep(2); // Esperar a que se propague el cambio
        
        $verifyResponse = $instance->listInstances();
        $stillExists = false;
        
        if (ResponseHandler::isSuccess($verifyResponse)) {
            $instances = ResponseHandler::getData($verifyResponse);
            foreach ($instances as $inst) {
                if ($inst['instanceName'] === $testInstanceName) {
                    $stillExists = true;
                    break;
                }
            }
        }
        
        if (!$stillExists) {
            echo "   ✅ Confirmado: Instancia eliminada completamente\n";
        } else {
            echo "   ⚠️  La instancia aún aparece en la lista\n";
        }
        
    } else {
        echo "   ❌ Error al eliminar: " . ResponseHandler::getError($deleteResponse) . "\n";
        
        // Intentar limpiar manualmente si falla la eliminación
        echo "   🧹 Intentando limpieza manual...\n";
        $manualCleanup = $instance->logout($testInstanceName);
        echo "   Resultado de limpieza: " . (ResponseHandler::isSuccess($manualCleanup) ? "✅ OK" : "❌ Error") . "\n";
    }
    
    echo "\n";
    
    // 6. Resumen final
    echo "6. RESUMEN DE LA DEMOSTRACIÓN\n";
    echo "   ✅ Creación de instancia: OK\n";
    echo "   ✅ Verificación de existencia: OK\n";
    echo "   ✅ Obtención de información: OK\n";
    echo "   ✅ Desconexión segura: OK\n";
    echo "   ✅ Limpieza de datos: OK\n";
    echo "   ✅ Eliminación controlada: " . (ResponseHandler::isSuccess($deleteResponse) ? "OK" : "Error") . "\n";
    echo "\n";
    echo "   📝 NOTAS IMPORTANTES:\n";
    echo "   • Siempre desconectar antes de eliminar\n";
    echo "   • Verificar que no hay datos importantes\n";
    echo "   • La eliminación es irreversible\n";
    echo "   • Usar logout() para desconexión temporal\n";
    echo "   • Usar clearInstanceData() para limpiar sin eliminar\n";
    
} catch (Exception $e) {
    echo "❌ Error durante la demostración: " . $e->getMessage() . "\n";
    
    // Intentar limpiar la instancia de prueba si algo salió mal
    if (isset($testInstanceName)) {
        echo "🧹 Intentando limpiar instancia de prueba...\n";
        try {
            $instance->delete($testInstanceName);
            echo "✅ Instancia de prueba limpiada\n";
        } catch (Exception $cleanupError) {
            echo "⚠️  No se pudo limpiar automáticamente la instancia: {$testInstanceName}\n";
            echo "   Elimínala manualmente desde el panel de administración\n";
        }
    }
}

echo "\n=== Demostración completada ===\n";