# Evolution API v2 - SDK PHP

SDK PHP completo para consumir Evolution API v2 de manera fácil y eficiente.

## 🚀 Características

- ✅ **Soporte completo** para Evolution API v2
- 🔧 **Fácil configuración** con Composer
- 📱 **Manejo de instancias** (crear, conectar, estado)
- 💬 **Envío de mensajes** (texto, multimedia, ubicación, contactos)
- 👥 **Gestión de grupos** (crear, administrar participantes)
- 💬 **Manejo de chats** (archivar, silenciar, bloquear)
- 🔗 **Webhooks** (configurar, probar, manejar eventos)
- 📁 **Archivos multimedia** (subir, descargar, validar)
- 🛠️ **Utilidades** (manejo de respuestas, logging)
- ✨ **Tipado estricto** y documentación PHPDoc

## 📦 Instalación

```bash
# Clonar el repositorio
git clone https://github.com/tu-usuario/evolution-sdk-php.git
cd evolution-sdk-php

# Instalar dependencias
composer install
```

O añadir a tu `composer.json`:

```json
{
    "require": {
        "evolution/sdk-php": "^1.0"
    }
}
```

## ⚡ Inicio Rápido

```php
<?php
require_once 'vendor/autoload.php';

use EvoApi\EvoClient;
use EvoApi\Message;
use EvoApi\Instance;

// Configurar cliente
$client = new EvoClient('https://your-api.com/', 'YOUR_API_KEY');

// Enviar mensaje
$message = new Message($client);
$response = $message->sendText('mi-instancia', '5511999999999', '¡Hola desde PHP!');

if ($response['success']) {
    echo "✅ Mensaje enviado: " . $response['data']['messageId'];
} else {
    echo "❌ Error: " . $response['message'];
}
```

## 📖 Documentación de API

### 🔧 Cliente Base

```php
use EvoApi\EvoClient;

$client = new EvoClient('https://your-api.com/', 'API_KEY');
$client->setInstance('nombre-instancia'); // Opcional
```

### 📱 Instancias

```php
use EvoApi\Instance;

$instance = new Instance($client);

// Listar instancias
$instances = $instance->listInstances();

// Crear instancia
$newInstance = $instance->create([
    'instanceName' => 'mi-bot',
    'qrcode' => true
]);

// Conectar instancia
$qr = $instance->connect('mi-instancia');

// Estado de conexión
$status = $instance->getConnectionState('mi-instancia');

// Reiniciar instancia
$restart = $instance->restart('mi-instancia');

// Desconectar instancia (logout temporal)
$logout = $instance->logout('mi-instancia');

// Limpiar datos de instancia (mantiene la configuración)
$clear = $instance->clearInstanceData('mi-instancia');

// ELIMINAR INSTANCIA (PERMANENTE E IRREVERSIBLE)
// ⚠️ ADVERTENCIA: Esta operación elimina completamente la instancia
$delete = $instance->delete('mi-instancia');

// Verificar si instancia está lista
$ready = $instance->isReady('mi-instancia');

// Generar reporte de estado
$report = $instance->generateStatusReport('mi-instancia');

// Monitorear conexión
$monitoring = $instance->monitorConnection('mi-instancia', function($state, $attempt) {
    echo "Intento {$attempt}: Estado {$state}\n";
    return $state !== 'open'; // Continuar hasta que esté abierto
});
```

### 💬 Mensajes

```php
use EvoApi\Message;

$message = new Message($client);

// Mensaje de texto
$message->sendText('instancia', '5511999999999', 'Hola mundo');

// Imagen con caption
$message->sendImage('instancia', '5511999999999', 'https://example.com/imagen.jpg', 'Mi foto');

// Documento
$message->sendDocument('instancia', '5511999999999', 'data:application/pdf;base64,...', 'archivo.pdf');

// Ubicación
$message->sendLocation('instancia', '5511999999999', -23.5505, -46.6333, 'São Paulo');

// Audio
$message->sendAudio('instancia', '5511999999999', 'data:audio/mp3;base64,...');

// Reacción
$message->sendReaction('instancia', '5511999999999', 'messageId', '👍');
```

### 👥 Grupos

```php
use EvoApi\Group;

$group = new Group($client);

// Crear grupo
$newGroup = $group->createGroup('instancia', 'Mi Grupo', ['5511999999999', '5511888888888']);

// Añadir participante
$group->addParticipant('instancia', 'groupId', ['5511777777777']);

// Promover a admin
$group->promote('instancia', 'groupId', ['5511999999999']);

// Obtener enlace de invitación
$invite = $group->getInviteCode('instancia', 'groupId');
```

### 💬 Chats

```php
use EvoApi\Chat;

$chat = new Chat($client);

// Listar chats
$chats = $chat->getChats('instancia');

// Archivar chat
$chat->archive('instancia', '5511999999999');

// Marcar como leído
$chat->markAsRead('instancia', '5511999999999');

// Silenciar chat
$chat->mute('instancia', '5511999999999', 3600); // 1 hora
```

### 🔗 Webhooks

```php
use EvoApi\Webhook;

$webhook = new Webhook($client);

// Configurar webhook
$webhook->setWebhook('instancia', 'https://mi-servidor.com/webhook', [
    'MESSAGE_RECEIVED',
    'MESSAGE_SENT',
    'CONNECTION_UPDATE'
]);

// Probar webhook
$webhook->test('instancia');

// Listar webhooks
$webhooks = $webhook->listWebhooks();
```

### 📁 Multimedia

```php
use EvoApi\Media;

$media = new Media($client);

// Convertir archivo a base64
$base64 = $media->fileToBase64('/path/to/image.jpg');

// Validar tipo de archivo
$validation = $media->validateMediaType('/path/to/file.pdf');

// Redimensionar imagen
$resized = $media->resizeImage('/path/to/large-image.jpg', 1920, 1080);

// Descargar multimedia
$media->downloadMedia('instancia', 'messageId', '/download/path/');
```

### 🛠️ Utilidades

```php
use EvoApi\Utils\ResponseHandler;

// Verificar si fue exitoso
if (ResponseHandler::isSuccess($response)) {
    $data = ResponseHandler::getData($response);
}

// Obtener error
$error = ResponseHandler::getError($response);

// Formatear para log
$logMessage = ResponseHandler::formatForLog($response, 'Envío mensaje');

// Manejar errores comunes
$response = ResponseHandler::handleCommonErrors($response);
```

## 📋 Ejemplos Completos

### Bot de Respuesta Automática

```php
<?php
// webhook_handler.php
require_once 'vendor/autoload.php';

use EvoApi\EvoClient;
use EvoApi\Message;

$client = new EvoClient('https://your-api.com/', 'API_KEY');
$message = new Message($client);

$payload = json_decode(file_get_contents('php://input'), true);

if ($payload['event'] === 'MESSAGE_RECEIVED') {
    $instance = $payload['instance'];
    $from = $payload['data']['from'];
    $text = $payload['data']['textMessage']['text'] ?? '';
    
    // Respuestas automáticas
    switch (strtolower($text)) {
        case 'hola':
            $message->sendText($instance, $from, '¡Hola! ¿En qué puedo ayudarte?');
            break;
        case 'horario':
            $message->sendText($instance, $from, 'Atendemos de 9:00 a 18:00 hrs');
            break;
        case 'ubicacion':
            $message->sendLocation($instance, $from, -23.5505, -46.6333, 'Nuestra oficina');
            break;
        default:
            $message->sendText($instance, $from, 'No entiendo. Escribe "hola" para comenzar.');
    }
}
```

### Envío Masivo

```php
<?php
require_once 'vendor/autoload.php';

use EvoApi\EvoClient;
use EvoApi\Message;

$client = new EvoClient('https://your-api.com/', 'API_KEY');
$message = new Message($client);

$instance = 'mi-instancia';
$contacts = ['5511999999999', '5511888888888', '5511777777777'];
$text = 'Mensaje promocional para todos!';

foreach ($contacts as $contact) {
    $response = $message->sendText($instance, $contact, $text);
    
    if ($response['success']) {
        echo "✅ Enviado a {$contact}\n";
    } else {
        echo "❌ Error enviando a {$contact}: {$response['message']}\n";
    }
    
    // Esperar 1 segundo entre envíos
    sleep(1);
}
```

## 🧪 Pruebas

```bash
# Ejecutar pruebas
composer test

# Con cobertura
composer test-coverage
```

## 📚 Estructura del Proyecto

```
evolution-sdk-php/
├── src/
│   ├── EvoClient.php          # Cliente principal
│   ├── Instance.php           # Manejo de instancias
│   ├── Message.php            # Envío de mensajes
│   ├── Chat.php               # Gestión de chats
│   ├── Group.php              # Manejo de grupos
│   ├── Webhook.php            # Configuración de webhooks
│   ├── Media.php              # Archivos multimedia
│   └── utils/
│       └── ResponseHandler.php # Utilidades de respuesta
├── examples/                  # Ejemplos de uso
├── tests/                     # Pruebas unitarias
└── composer.json             # Dependencias
```

## 🔐 Configuración de Seguridad

### Variables de Entorno

```bash
# .env
EVOLUTION_API_URL=https://your-api.com/
EVOLUTION_API_KEY=your-secret-key
EVOLUTION_INSTANCE=default-instance
```

```php
// Usar variables de entorno
$client = new EvoClient(
    $_ENV['EVOLUTION_API_URL'],
    $_ENV['EVOLUTION_API_KEY']
);
```

## ⚠️ Limitaciones y Consideraciones

- **Rate Limiting**: Evolution API tiene límites de envío por minuto
- **Tamaño de archivos**: WhatsApp limita el tamaño de multimedia
- **Formato de números**: Usar formato internacional (código país + número)
- **Instancias**: Una instancia = una sesión de WhatsApp

## 🐛 Solución de Problemas

### Error 401 - No autorizado
```php
// Verificar API Key
$client = new EvoClient($baseUri, $correctApiKey);
```

### Error 404 - Instancia no encontrada
```php
// Verificar que la instancia existe
$instance = new Instance($client);
$instances = $instance->listInstances();
```

### Error de conexión
```php
// Verificar estado de la instancia
$status = $instance->status('mi-instancia');
if ($status['data']['state'] === 'close') {
    $instance->connect('mi-instancia');
}
```

## 🤝 Contribuir

1. Fork del proyecto
2. Crear rama para feature (`git checkout -b feature/nueva-funcionalidad`)
3. Commit cambios (`git commit -am 'Añadir nueva funcionalidad'`)
4. Push a la rama (`git push origin feature/nueva-funcionalidad`)
5. Crear Pull Request

## 📄 Licencia

MIT License - ver archivo [LICENSE](LICENSE) para detalles.

## 🔗 Enlaces Útiles

- [Documentación Evolution API v2](https://doc.evolution-api.com/v2/)
- [WhatsApp Business API](https://developers.facebook.com/docs/whatsapp)
- [Guzzle HTTP](https://docs.guzzlephp.org/)

## 📞 Soporte

- 📧 Email: soporte@evolution-api.com
- 💬 Telegram: @evolution-api
- 🐛 Issues: [GitHub Issues](https://github.com/tu-usuario/evolution-sdk-php/issues)

---

⭐ Si este SDK te ayuda, ¡dale una estrella al repo!