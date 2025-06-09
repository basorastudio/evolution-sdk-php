<?php
/**
 * Ejemplo rápido de uso del SDK Evolution API
 * Configurado para https://whatsapp.ltd.do/
 */

require_once __DIR__ . '/vendor/autoload.php';

use EvoApi\EvoClient;
use EvoApi\Instance;
use EvoApi\Message;
use EvoApi\Utils\ResponseHandler;

// CONFIGURACIÓN - Actualiza estos valores
$config = [
    'base_url' => 'https://whatsapp.ltd.do/',
    'api_key' => '015359bd95a15617dba9a1434834a4ce', // ✅ API KEY REAL CONFIGURADA
    'manager_url' => 'https://whatsapp.ltd.do/manager',
    'instance_name' => 'sdk-test-' . date('Ymd-His'),
    'test_number' => '18297934075' // Número para pruebas (formato internacional)
];

echo "🚀 Evolution API SDK - Test Rápido\n";
echo "Servidor: {$config['base_url']}\n";
echo "Instancia: {$config['instance_name']}\n\n";

try {
    // 1. Inicializar cliente
    echo "1️⃣ Conectando al servidor...\n";
    $client = new EvoClient($config['base_url'], $config['api_key'], [
        'debug' => true,
        'timeout' => 30
    ]);
    
    // Verificar conexión
    $health = $client->healthCheck();
    if ($health['success']) {
        echo "✅ Servidor conectado - Version: {$health['version']}\n";
    } else {
        echo "❌ Error de conexión: {$health['error']}\n";
        exit(1);
    }
    
    // 2. Crear instancia
    echo "\n2️⃣ Creando instancia...\n";
    $instance = new Instance($client);
    
    $createResponse = $instance->create([
        'instanceName' => $config['instance_name'],
        'token' => 'test-token-' . uniqid(),
        'qrcode' => true
    ]);
    
    if (ResponseHandler::isSuccess($createResponse)) {
        echo "✅ Instancia creada: {$config['instance_name']}\n";
        
        // Obtener QR Code
        $connectResponse = $instance->connect($config['instance_name']);
        if (ResponseHandler::isSuccess($connectResponse)) {
            $qrData = ResponseHandler::getData($connectResponse);
            if (isset($qrData['qrcode'])) {
                echo "📱 QR Code generado (base64)\n";
                echo "   Longitud: " . strlen($qrData['qrcode']) . " caracteres\n";
                echo "   Usa este QR para conectar WhatsApp\n";
            }
        }
        
    } else {
        echo "❌ Error creando instancia: " . ResponseHandler::getError($createResponse) . "\n";
        exit(1);
    }
    
    // 3. Verificar estado
    echo "\n3️⃣ Verificando estado de la instancia...\n";
    $statusResponse = $instance->getConnectionState($config['instance_name']);
    if (ResponseHandler::isSuccess($statusResponse)) {
        $status = ResponseHandler::getData($statusResponse);
        echo "📊 Estado: {$status['state']}\n";
        
        if ($status['state'] === 'close') {
            echo "⚠️  Instancia desconectada - Necesita escanear QR\n";
            echo "   1. Abre WhatsApp en tu teléfono\n";
            echo "   2. Ve a 'Dispositivos vinculados'\n";
            echo "   3. Escanea el QR generado arriba\n";
        }
    }
    
    // 4. Listar todas las instancias
    echo "\n4️⃣ Listando todas las instancias...\n";
    $listResponse = $instance->listInstances();
    if (ResponseHandler::isSuccess($listResponse)) {
        $instances = ResponseHandler::getData($listResponse);
        echo "📋 Total de instancias: " . count($instances) . "\n";
        
        foreach ($instances as $inst) {
            $name = $inst['instanceName'];
            $state = $inst['connectionState'] ?? 'unknown';
            $emoji = $state === 'open' ? '🟢' : ($state === 'close' ? '🔴' : '🟡');
            echo "   {$emoji} {$name} ({$state})\n";
        }
    }
    
    // 5. Ejemplo de mensaje (solo si hay una instancia conectada)
    echo "\n5️⃣ Preparando para envío de mensaje...\n";
    $connectedInstances = array_filter($instances, fn($i) => ($i['connectionState'] ?? '') === 'open');
    
    if (!empty($connectedInstances)) {
        $connectedInstance = $connectedInstances[0]['instanceName'];
        echo "✅ Instancia conectada encontrada: {$connectedInstance}\n";
        
        $message = new Message($client);
        $sendResponse = $message->sendText(
            $connectedInstance, 
            $config['test_number'], 
            "🧪 Test desde Evolution SDK PHP\n\nServidor: {$config['base_url']}\nHora: " . date('Y-m-d H:i:s')
        );
        
        if (ResponseHandler::isSuccess($sendResponse)) {
            $msgData = ResponseHandler::getData($sendResponse);
            echo "✅ Mensaje enviado exitosamente\n";
            echo "   ID: {$msgData['messageId']}\n";
        } else {
            echo "❌ Error enviando mensaje: " . ResponseHandler::getError($sendResponse) . "\n";
        }
    } else {
        echo "⚠️  No hay instancias conectadas para enviar mensaje\n";
        echo "   Conecta una instancia escaneando el QR y ejecuta este script nuevamente\n";
    }
    
    // 6. Cleanup (opcional - descomenta si quieres limpiar la instancia de prueba)
    echo "\n6️⃣ Gestión de la instancia de prueba...\n";
    echo "💡 Para limpiar la instancia de prueba, descomenta las siguientes líneas:\n";
    echo "   // \$instance->logout('{$config['instance_name']}');\n";
    echo "   // \$instance->delete('{$config['instance_name']}');\n";
    echo "\n";
    
    /*
    // DESCOMENTA ESTAS LÍNEAS PARA ELIMINAR LA INSTANCIA DE PRUEBA
    echo "🧹 Limpiando instancia de prueba...\n";
    $logoutResponse = $instance->logout($config['instance_name']);
    $deleteResponse = $instance->delete($config['instance_name']);
    
    if (ResponseHandler::isSuccess($deleteResponse)) {
        echo "✅ Instancia de prueba eliminada\n";
    } else {
        echo "⚠️  No se pudo eliminar automáticamente. Elimínala manualmente: {$config['instance_name']}\n";
    }
    */
    
    echo "🎉 Test completado exitosamente!\n\n";
    echo "📝 Próximos pasos:\n";
    echo "   1. Actualiza 'YOUR_API_KEY_HERE' con tu API key real\n";
    echo "   2. Conecta una instancia escaneando el QR\n";
    echo "   3. Prueba enviar mensajes reales\n";
    echo "   4. Revisa los ejemplos en la carpeta /examples/\n";
    echo "   5. Lee la documentación en README.md\n";
    
} catch (Exception $e) {
    echo "❌ Error fatal: " . $e->getMessage() . "\n";
    echo "🔧 Verifica:\n";
    echo "   - URL del servidor: {$config['base_url']}\n";
    echo "   - API Key válida\n";
    echo "   - Conexión a internet\n";
    echo "   - Permisos de escritura\n";
}