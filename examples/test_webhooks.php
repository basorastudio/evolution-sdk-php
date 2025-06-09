<?php
require_once __DIR__ . '/../vendor/autoload.php';

use EvoApi\EvoClient;
use EvoApi\Webhook;
use EvoApi\Utils\ResponseHandler;

// Configuración
$baseUri = 'https://whatsapp.ltd.do/';
$apiKey = 'YOUR_API_KEY_HERE';
$instanceName = 'mi-instancia';
$webhookUrl = 'https://your-webhook-endpoint.com/webhook';

try {
    // Inicializar el cliente
    $client = new EvoClient($baseUri, $apiKey);
    $webhook = new Webhook($client);
    
    echo "=== Evolution API v2 - Prueba de Webhooks ===\n\n";
    
    // 1. Mostrar eventos disponibles
    echo "1. Eventos disponibles para webhooks:\n";
    $events = $webhook->getAvailableEvents();
    foreach ($events as $event) {
        echo "   - {$event}\n";
    }
    
    echo "\n";
    
    // 2. Configurar webhook
    echo "2. Configurando webhook...\n";
    $response = $webhook->setWebhook($instanceName, $webhookUrl, [
        'MESSAGE_RECEIVED',
        'MESSAGE_SENT',
        'CONNECTION_UPDATE',
        'QRCODE_UPDATED'
    ]);
    
    if (ResponseHandler::isSuccess($response)) {
        echo "✅ Webhook configurado exitosamente\n";
        $data = ResponseHandler::getData($response);
        echo "   - URL: {$data['url']}\n";
        echo "   - Eventos: " . implode(', ', $data['events']) . "\n";
    } else {
        echo "❌ Error: " . ResponseHandler::getError($response) . "\n";
    }
    
    echo "\n";
    
    // 3. Obtener configuración actual
    echo "3. Obteniendo configuración actual del webhook...\n";
    $response = $webhook->getWebhook($instanceName);
    
    if (ResponseHandler::isSuccess($response)) {
        $data = ResponseHandler::getData($response);
        echo "✅ Configuración actual:\n";
        echo "   - URL: {$data['url']}\n";
        echo "   - Estado: " . ($data['enabled'] ? 'Activo' : 'Inactivo') . "\n";
        echo "   - Eventos: " . implode(', ', $data['events']) . "\n";
    } else {
        echo "❌ Error: " . ResponseHandler::getError($response) . "\n";
    }
    
    echo "\n";
    
    // 4. Probar webhook
    echo "4. Probando webhook...\n";
    $response = $webhook->test($instanceName);
    
    if (ResponseHandler::isSuccess($response)) {
        echo "✅ Webhook probado exitosamente\n";
    } else {
        echo "❌ Error: " . ResponseHandler::getError($response) . "\n";
    }
    
    echo "\n";
    
    // 5. Listar todos los webhooks
    echo "5. Listando todos los webhooks configurados...\n";
    $response = $webhook->listWebhooks();
    
    if (ResponseHandler::isSuccess($response)) {
        $webhooks = ResponseHandler::getData($response);
        echo "✅ Encontrados " . count($webhooks) . " webhooks\n";
        
        foreach ($webhooks as $wh) {
            echo "   - Instancia: {$wh['instanceName']}\n";
            echo "     URL: {$wh['url']}\n";
            echo "     Estado: " . ($wh['enabled'] ? 'Activo' : 'Inactivo') . "\n";
        }
    } else {
        echo "❌ Error: " . ResponseHandler::getError($response) . "\n";
    }
    
    echo "\n=== Prueba de webhooks completada ===\n";
    
    // Ejemplo de manejo de webhook recibido
    echo "\n=== Ejemplo de procesamiento de webhook ===\n";
    echo "// Para procesar webhooks entrantes en tu servidor:\n\n";
    
    $exampleWebhookPayload = [
        'event' => 'MESSAGE_RECEIVED',
        'instance' => $instanceName,
        'data' => [
            'messageId' => 'msg_123456',
            'from' => '5511999999999',
            'messageType' => 'textMessage',
            'textMessage' => ['text' => 'Hola desde WhatsApp'],
            'timestamp' => time()
        ]
    ];
    
    echo "/*\n";
    echo "// webhook_handler.php\n";
    echo "<?php\n";
    echo "require_once 'vendor/autoload.php';\n\n";
    echo "use EvoApi\\Utils\\ResponseHandler;\n\n";
    echo "\$payload = json_decode(file_get_contents('php://input'), true);\n\n";
    echo "if (\$payload['event'] === 'MESSAGE_RECEIVED') {\n";
    echo "    \$from = \$payload['data']['from'];\n";
    echo "    \$text = \$payload['data']['textMessage']['text'];\n";
    echo "    \n";
    echo "    // Procesar mensaje recibido\n";
    echo "    error_log(\"Mensaje de {\$from}: {\$text}\");\n";
    echo "    \n";
    echo "    // Responder automáticamente si es necesario\n";
    echo "    // \$message->sendText(\$instance, \$from, \"Recibido: {\$text}\");\n";
    echo "}\n";
    echo "*/\n\n";
    
    echo "Payload de ejemplo:\n";
    echo ResponseHandler::toJson($exampleWebhookPayload);
    
} catch (Exception $e) {
    echo "❌ Error fatal: " . $e->getMessage() . "\n";
}