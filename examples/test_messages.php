<?php
require_once __DIR__ . '/../vendor/autoload.php';

use EvoApi\EvoClient;
use EvoApi\Message;
use EvoApi\Media;
use EvoApi\Utils\ResponseHandler;

// Configuración
$baseUri = 'https://your-evolution-api.com/';
$apiKey = 'YOUR_API_KEY_HERE';
$instanceName = 'mi-instancia';
$phoneNumber = '5511999999999'; // Número con código de país

try {
    // Inicializar el cliente
    $client = new EvoClient($baseUri, $apiKey);
    $message = new Message($client);
    $media = new Media($client);
    
    echo "=== Evolution API v2 - Prueba de Mensajes ===\n\n";
    
    // 1. Enviar mensaje de texto simple
    echo "1. Enviando mensaje de texto...\n";
    $response = $message->sendText($instanceName, $phoneNumber, "¡Hola! Este es un mensaje de prueba desde el SDK PHP de Evolution API v2 🚀");
    
    if (ResponseHandler::isSuccess($response)) {
        $data = ResponseHandler::getData($response);
        echo "✅ Mensaje enviado exitosamente\n";
        echo "   - ID: {$data['messageId']}\n";
        echo "   - Timestamp: {$data['timestamp']}\n";
    } else {
        echo "❌ Error: " . ResponseHandler::getError($response) . "\n";
    }
    
    echo "\n";
    
    // 2. Enviar mensaje con opciones avanzadas
    echo "2. Enviando mensaje con quote...\n";
    $response = $message->sendText($instanceName, $phoneNumber, "Este mensaje cita al anterior", [
        'quoted' => [
            'messageId' => $data['messageId'] ?? null
        ]
    ]);
    
    if (ResponseHandler::isSuccess($response)) {
        echo "✅ Mensaje con quote enviado\n";
    } else {
        echo "❌ Error: " . ResponseHandler::getError($response) . "\n";
    }
    
    echo "\n";
    
    // 3. Enviar ubicación
    echo "3. Enviando ubicación...\n";
    $response = $message->sendLocation(
        $instanceName, 
        $phoneNumber, 
        -23.5505, // Latitud (São Paulo)
        -46.6333, // Longitud (São Paulo)
        "São Paulo, Brasil"
    );
    
    if (ResponseHandler::isSuccess($response)) {
        echo "✅ Ubicación enviada\n";
    } else {
        echo "❌ Error: " . ResponseHandler::getError($response) . "\n";
    }
    
    echo "\n";
    
    // 4. Ejemplo de envío de imagen (si tienes una imagen)
    $imagePath = __DIR__ . '/sample_image.jpg';
    if (file_exists($imagePath)) {
        echo "4. Enviando imagen...\n";
        
        // Convertir imagen a base64
        $base64Response = $media->fileToBase64($imagePath);
        
        if ($base64Response['success']) {
            $response = $message->sendImage(
                $instanceName, 
                $phoneNumber, 
                $base64Response['data_uri'],
                "Esta es una imagen de prueba 📸"
            );
            
            if (ResponseHandler::isSuccess($response)) {
                echo "✅ Imagen enviada\n";
            } else {
                echo "❌ Error enviando imagen: " . ResponseHandler::getError($response) . "\n";
            }
        } else {
            echo "❌ Error convirtiendo imagen: {$base64Response['error']}\n";
        }
    } else {
        echo "4. ⏭️ Saltando envío de imagen (archivo no encontrado)\n";
    }
    
    echo "\n";
    
    // 5. Listar mensajes recientes
    echo "5. Listando mensajes recientes...\n";
    $response = $message->listMessages($instanceName, $phoneNumber, 10);
    
    if (ResponseHandler::isSuccess($response)) {
        $messages = ResponseHandler::getData($response);
        echo "✅ Encontrados " . count($messages) . " mensajes\n";
        
        foreach (array_slice($messages, 0, 3) as $msg) {
            $type = $msg['messageType'] ?? 'unknown';
            $text = $msg['textMessage']['text'] ?? '[' . $type . ']';
            $time = date('H:i:s', $msg['messageTimestamp'] ?? time());
            echo "   - [{$time}] {$text}\n";
        }
    } else {
        echo "❌ Error: " . ResponseHandler::getError($response) . "\n";
    }
    
    echo "\n";
    
    // 6. Enviar reacción
    if (isset($data['messageId'])) {
        echo "6. Enviando reacción...\n";
        $response = $message->sendReaction($instanceName, $phoneNumber, $data['messageId'], "👍");
        
        if (ResponseHandler::isSuccess($response)) {
            echo "✅ Reacción enviada\n";
        } else {
            echo "❌ Error: " . ResponseHandler::getError($response) . "\n";
        }
    }
    
    echo "\n=== Prueba de mensajes completada ===\n";
    
} catch (Exception $e) {
    echo "❌ Error fatal: " . $e->getMessage() . "\n";
}