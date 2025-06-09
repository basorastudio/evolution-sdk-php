# Evolution API v2 - SDK PHP

SDK PHP completo para consumir Evolution API v2 de manera fácil y eficiente.

## 🚀 Características

- ✅ **Soporte completo** para Evolution API v2
- 🔧 **Fácil configuración** con Composer
- 📱 **Manejo completo de instancias** (crear, conectar, eliminar, monitorear)
- 💬 **Envío de mensajes** (texto, multimedia, ubicación, contactos)
- 👥 **Gestión de grupos** (crear, administrar participantes)
- 💬 **Manejo de chats** (archivar, silenciar, bloquear)
- 🔗 **Webhooks** (configurar, probar, manejar eventos)
- 📁 **Archivos multimedia** (subir, descargar, validar)
- 📊 **Analytics y reportes** (estadísticas de uso)
- 📤 **Envío masivo** (bulk messages con control de rate limiting)
- 🛠️ **Utilidades** (manejo de respuestas, logging)
- ✨ **Tipado estricto** y documentación PHPDoc completa

## 📦 Instalación

### Método 1: Clonar repositorio
```bash
# Clonar el repositorio
git clone https://github.com/basorastudio/evolution-sdk-php.git
cd evolution-sdk-php

# Instalar dependencias
composer install
```

### Método 2: Composer (próximamente)
```bash
# Una vez publicado en Packagist
composer require basorastudio/evolution-sdk-php
```

### Método 3: Añadir a composer.json
```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/basorastudio/evolution-sdk-php"
        }
    ],
    "require": {
        "basorastudio/evolution-sdk-php": "^1.0"
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

// Configurar cliente - Actualiza con tu URL y API Key
$client = new EvoClient('https://whatsapp.ltd.do/', 'YOUR_API_KEY');

// Crear y conectar instancia
$instance = new Instance($client);
$newInstance = $instance->create([
    'instanceName' => 'mi-bot',
    'qrcode' => true
]);

// Enviar mensaje
$message = new Message($client);
$response = $message->sendText('mi-bot', '18297934075', '¡Hola desde PHP!');

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

// Usar tu servidor Evolution API
$client = new EvoClient('https://whatsapp.ltd.do/', 'API_KEY');
$client->setInstance('nombre-instancia'); // Opcional: instancia por defecto
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
$message->sendText('instancia', '18297934075', 'Hola mundo');

// Imagen con caption
$message->sendImage('instancia', '18297934075', 'https://example.com/imagen.jpg', 'Mi foto');

// Documento
$message->sendDocument('instancia', '18297934075', 'data:application/pdf;base64,...', 'archivo.pdf');

// Ubicación
$message->sendLocation('instancia', '18297934075', -23.5505, -46.6333, 'São Paulo');

// Audio
$message->sendAudio('instancia', '18297934075', 'data:audio/mp3;base64,...');

// Reacción
$message->sendReaction('instancia', '18297934075', 'messageId', '👍');
```

### 👥 Grupos

```php
use EvoApi\Group;

$group = new Group($client);

// Crear grupo
$newGroup = $group->createGroup('instancia', 'Mi Grupo', ['18297934075', '18093024075']);

// Añadir participante
$group->addParticipant('instancia', 'groupId', ['5511777777777']);

// Promover a admin
$group->promote('instancia', 'groupId', ['18297934075']);

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
$chat->archive('instancia', '18297934075');

// Marcar como leído
$chat->markAsRead('instancia', '18297934075');

// Silenciar chat
$chat->mute('instancia', '18297934075', 3600); // 1 hora
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

## 🔄 Gestión Completa de Instancias

### Ejemplo de Gestión Segura de Instancias

```php
<?php
use EvoApi\Instance;
use EvoApi\Utils\ResponseHandler;

$instance = new Instance($client);

// 1. Crear instancia
$response = $instance->create([
    'instanceName' => 'mi-instancia',
    'qrcode' => true,
    'webhook' => 'https://mi-servidor.com/webhook'
]);

// 2. Monitorear conexión
$instance->monitorConnection('mi-instancia', function($state, $attempt) {
    echo "Estado: {$state} (Intento: {$attempt})\n";
    return $state === 'open'; // Detener cuando esté conectado
});

// 3. Verificar estado
if ($instance->isReady('mi-instancia')) {
    echo "✅ Instancia lista para usar\n";
}

// 4. Obtener reporte completo
$report = $instance->generateStatusReport('mi-instancia');
echo "Estado general: {$report['overall_status']}\n";
echo "Puntuación de salud: {$report['health_score']}%\n";

// 5. Gestión segura de eliminación
// SIEMPRE seguir este proceso antes de eliminar:
$instance->logout('mi-instancia');           // 1. Desconectar
$instance->clearInstanceData('mi-instancia'); // 2. Limpiar datos
// Confirmar que no hay datos importantes
$instance->delete('mi-instancia');            // 3. ELIMINAR (irreversible)
```

## 📊 Analytics y Reportes

```php
use EvoApi\Analytics;

$analytics = new Analytics($client);

// Estadísticas de mensajes
$stats = $analytics->getMessageStats('mi-instancia', '2024-01-01', '2024-01-31');
echo "Mensajes enviados: {$stats['sent']}\n";
echo "Mensajes recibidos: {$stats['received']}\n";

// Reporte de uso de instancia
$usage = $analytics->getInstanceUsage('mi-instancia');
echo "Tiempo de actividad: {$usage['uptime']}\n";
echo "Conexiones exitosas: {$usage['successful_connections']}\n";
```

## 📤 Envío Masivo Controlado

```php
use EvoApi\Bulk;

$bulk = new Bulk($client);

// Configurar envío masivo con control de velocidad
$bulk->configure([
    'rate_limit' => 5,      // 5 mensajes por minuto
    'retry_failed' => true, // Reintentar fallidos
    'batch_size' => 10      // Procesar en lotes de 10
]);

$contacts = [
    ['phone' => '18297934075', 'name' => 'Cliente 1'],
    ['phone' => '5511888888888', 'name' => 'Cliente 2'],
    // ... más contactos
];

$template = "Hola {{name}}, tenemos una oferta especial para ti!";

$result = $bulk->sendBulkMessages('mi-instancia', $contacts, $template);
echo "Enviados: {$result['successful']}\n";
echo "Fallidos: {$result['failed']}\n";
```

## 📋 Ejemplos Completos

### Bot Avanzado con Comandos

```php
<?php
// bot_avanzado.php
require_once 'vendor/autoload.php';

use EvoApi\EvoClient;
use EvoApi\Message;
use EvoApi\Analytics;

$client = new EvoClient('https://your-evolution-api.com/', 'API_KEY');
$message = new Message($client);
$analytics = new Analytics($client);

$payload = json_decode(file_get_contents('php://input'), true);

if ($payload['event'] === 'MESSAGE_RECEIVED') {
    $instance = $payload['instance'];
    $from = $payload['data']['from'];
    $text = strtolower(trim($payload['data']['textMessage']['text'] ?? ''));
    
    // Sistema de comandos
    switch ($text) {
        case '/start':
        case 'hola':
            $message->sendText($instance, $from, 
                "¡Hola! 👋\n\n" .
                "Comandos disponibles:\n" .
                "• /horario - Ver horarios\n" .
                "• /ubicacion - Nuestra ubicación\n" .
                "• /contacto - Información de contacto\n" .
                "• /stats - Estadísticas del bot"
            );
            break;
            
        case '/horario':
            $message->sendText($instance, $from, 
                "🕒 *Horarios de Atención*\n\n" .
                "Lunes a Viernes: 9:00 - 18:00\n" .
                "Sábados: 9:00 - 14:00\n" .
                "Domingos: Cerrado"
            );
            break;
            
        case '/ubicacion':
            $message->sendLocation($instance, $from, -23.5505, -46.6333, 'Nuestra Oficina');
            break;
            
        case '/contacto':
            $contact = [
                'name' => 'Soporte Técnico',
                'phone' => '18297934075',
                'organization' => 'Mi Empresa'
            ];
            $message->sendContact($instance, $from, $contact);
            break;
            
        case '/stats':
            $stats = $analytics->getMessageStats($instance, date('Y-m-01'), date('Y-m-d'));
            $message->sendText($instance, $from,
                "📊 *Estadísticas del Bot*\n\n" .
                "Mensajes enviados hoy: {$stats['sent_today']}\n" .
                "Mensajes del mes: {$stats['sent_month']}\n" .
                "Usuarios activos: {$stats['active_users']}"
            );
            break;
            
        default:
            $message->sendText($instance, $from,
                "❓ No entiendo ese comando.\n" .
                "Escribe 'hola' o '/start' para ver los comandos disponibles."
            );
    }
    
    // Registrar interacción para analytics
    $analytics->logInteraction($instance, $from, $text);
}
```

## 🧪 Pruebas

```bash
# Ejecutar todas las pruebas
composer test

# Pruebas con cobertura
composer test-coverage

# Pruebas específicas
./vendor/bin/phpunit tests/InstanceTest.php
./vendor/bin/phpunit tests/MessageTest.php
```

## 📚 Estructura del Proyecto

```
evolution-sdk-php/
├── src/
│   ├── EvoClient.php          # Cliente principal
│   ├── Instance.php           # ✨ Manejo completo de instancias
│   ├── Message.php            # Envío de mensajes
│   ├── Chat.php               # Gestión de chats
│   ├── Group.php              # Manejo de grupos
│   ├── Webhook.php            # Configuración de webhooks
│   ├── Media.php              # Archivos multimedia
│   ├── Analytics.php          # 📊 Estadísticas y reportes
│   ├── Bulk.php               # 📤 Envío masivo
│   ├── Template.php           # 📄 Plantillas de mensajes
│   └── utils/
│       └── ResponseHandler.php # Utilidades de respuesta
├── examples/                  # 🔧 Ejemplos de uso
│   ├── test_instance.php      # Pruebas de instancia
│   ├── test_messages.php      # Pruebas de mensajes
│   ├── test_webhooks.php      # Pruebas de webhooks
│   └── test_instance_management.php # ✨ Gestión completa
├── tests/                     # 🧪 Pruebas unitarias
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

- **Rate Limiting**: Evolution API tiene límites de envío (respetados automáticamente por el SDK)
- **Tamaño de archivos**: WhatsApp limita multimedia a 16MB para documentos, 64MB para videos
- **Formato de números**: Usar formato internacional sin espacios ni caracteres especiales
- **Instancias**: Una instancia = una sesión de WhatsApp (máximo 1 por número)
- **Eliminación**: La eliminación de instancias es **IRREVERSIBLE** - usar con precaución

## 🐛 Solución de Problemas

### Error 401 - No autorizado
```php
// Verificar API Key y URL base
$client = new EvoClient('https://your-correct-api.com/', 'CORRECT_API_KEY');
```

### Error 404 - Instancia no encontrada
```php
// Verificar que la instancia existe y está activa
$instance = new Instance($client);
$instances = $instance->listInstances();
var_dump($instances); // Ver todas las instancias disponibles
```

### Error de conexión
```php
// Verificar y reconectar instancia
$status = $instance->getConnectionState('mi-instancia');
if ($status['data']['state'] !== 'open') {
    $instance->connect('mi-instancia');
    // Esperar a que se conecte
    $instance->monitorConnection('mi-instancia');
}
```

### Problemas con Webhooks
```php
// Verificar configuración de webhook
$webhook = new Webhook($client);
$config = $webhook->getWebhookConfig('mi-instancia');
if (!$config['success']) {
    $webhook->setWebhook('mi-instancia', 'https://tu-servidor.com/webhook');
}
```

## 🤝 Contribuir

1. **Fork** del proyecto desde [GitHub](https://github.com/basorastudio/evolution-sdk-php)
2. **Clonar** tu fork: `git clone https://github.com/tu-usuario/evolution-sdk-php.git`
3. **Crear rama** para feature: `git checkout -b feature/nueva-funcionalidad`
4. **Desarrollar** y agregar pruebas
5. **Commit** cambios: `git commit -am 'Añadir nueva funcionalidad'`
6. **Push** a la rama: `git push origin feature/nueva-funcionalidad`
7. **Crear Pull Request** en GitHub

### Pautas para Contribuir
- Seguir PSR-12 para el estilo de código
- Agregar pruebas unitarias para nuevas funcionalidades
- Documentar todas las funciones públicas con PHPDoc
- Mantener compatibilidad con PHP 7.4+

## 📄 Licencia

MIT License - ver archivo [LICENSE](LICENSE) para detalles.

## 🔗 Enlaces Útiles

- 📖 [Documentación Evolution API v2](https://doc.evolution-api.com/v2/)
- 🔗 [Repositorio GitHub](https://github.com/basorastudio/evolution-sdk-php)
- 💬 [WhatsApp Business API](https://developers.facebook.com/docs/whatsapp)
- 🛠️ [Guzzle HTTP Client](https://docs.guzzlephp.org/)
- 🧪 [PHPUnit Testing](https://phpunit.de/)

## 📞 Soporte y Comunidad

- 🐛 **Issues**: [GitHub Issues](https://github.com/basorastudio/evolution-sdk-php/issues)
- 💬 **Discusiones**: [GitHub Discussions](https://github.com/basorastudio/evolution-sdk-php/discussions)
- 📧 **Email**: [Contacto Basora Studio](mailto:contact@basorastudio.com)
- 🌐 **Website**: [Basora Studio](https://basorastudio.com)

## 🏆 Créditos

Desarrollado con ❤️ por [**Basora Studio**](https://basorastudio.com)

### Contribuidores
- Agradecimientos especiales a todos los contribuidores del proyecto

---

⭐ **¡Si este SDK te ayuda, dale una estrella al repositorio!**

[![GitHub stars](https://img.shields.io/github/stars/basorastudio/evolution-sdk-php.svg?style=social&label=Star)](https://github.com/basorastudio/evolution-sdk-php)
[![GitHub forks](https://img.shields.io/github/forks/basorastudio/evolution-sdk-php.svg?style=social&label=Fork)](https://github.com/basorastudio/evolution-sdk-php/fork)
[![GitHub issues](https://img.shields.io/github/issues/basorastudio/evolution-sdk-php.svg)](https://github.com/basorastudio/evolution-sdk-php/issues)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D7.4-blue.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)