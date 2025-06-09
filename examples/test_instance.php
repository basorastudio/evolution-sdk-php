<?php
require_once __DIR__ . '/../vendor/autoload.php';

use EvoApi\EvoClient;
use EvoApi\Instance;
use EvoApi\Utils\ResponseHandler;

// Configuración
$baseUri = 'https://your-evolution-api.com/';
$apiKey = 'YOUR_API_KEY_HERE';
$instanceName = 'mi-instancia';

try {
    // Inicializar el cliente
    $client = new EvoClient($baseUri, $apiKey);
    $instance = new Instance($client);
    
    echo "=== Evolution API v2 - Prueba de Instancia ===\n\n";
    
    // 1. Listar todas las instancias
    echo "1. Listando instancias...\n";
    $response = $instance->listInstances();
    
    if (ResponseHandler::isSuccess($response)) {
        $instances = ResponseHandler::getData($response);
        echo "✅ Encontradas " . count($instances) . " instancias\n";
        
        foreach ($instances as $inst) {
            echo "   - {$inst['instanceName']} ({$inst['connectionState']})\n";
        }
    } else {
        echo "❌ Error: " . ResponseHandler::getError($response) . "\n";
    }
    
    echo "\n";
    
    // 2. Obtener información de una instancia específica
    echo "2. Obteniendo información de la instancia '{$instanceName}'...\n";
    $response = $instance->getInfo($instanceName);
    
    if (ResponseHandler::isSuccess($response)) {
        $data = ResponseHandler::getData($response);
        echo "✅ Instancia encontrada:\n";
        echo "   - Nombre: {$data['instanceName']}\n";
        echo "   - Estado: {$data['connectionState']}\n";
        echo "   - Propietario: {$data['ownerJid']}\n";
    } else {
        echo "❌ Error: " . ResponseHandler::getError($response) . "\n";
    }
    
    echo "\n";
    
    // 3. Verificar estado de conexión
    echo "3. Verificando estado de conexión...\n";
    $response = $instance->status($instanceName);
    
    if (ResponseHandler::isSuccess($response)) {
        $status = ResponseHandler::getData($response);
        echo "✅ Estado: {$status['state']}\n";
        
        if ($status['state'] === 'close') {
            echo "⚠️  La instancia está desconectada. Generando QR...\n";
            
            $qrResponse = $instance->connect($instanceName);
            if (ResponseHandler::isSuccess($qrResponse)) {
                echo "✅ QR generado exitosamente\n";
            }
        }
    } else {
        echo "❌ Error: " . ResponseHandler::getError($response) . "\n";
    }
    
    echo "\n";
    
    // 7. EJEMPLO DE ELIMINACIÓN DE INSTANCIA (COMENTADO POR SEGURIDAD)
    echo "7. Ejemplo de eliminación de instancia...\n";
    echo "⚠️  IMPORTANTE: La eliminación es PERMANENTE e IRREVERSIBLE\n";
    echo "   Descomenta las siguientes líneas solo si realmente quieres eliminar la instancia:\n";
    echo "   \n";
    echo "   // \$response = \$instance->delete(\$instanceName);\n";
    echo "   // if (ResponseHandler::isSuccess(\$response)) {\n";
    echo "   //     echo \"✅ Instancia eliminada exitosamente\\n\";\n";
    echo "   // } else {\n";
    echo "   //     echo \"❌ Error al eliminar: \" . ResponseHandler::getError(\$response) . \"\\n\";\n";
    echo "   // }\n";
    echo "\n";
    
    // Alternativas más seguras
    echo "   Alternativas más seguras:\n";
    echo "   - Desconectar: \$instance->logout(\$instanceName)\n";
    echo "   - Limpiar datos: \$instance->clearInstanceData(\$instanceName)\n";
    echo "   - Reiniciar: \$instance->restart(\$instanceName)\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}