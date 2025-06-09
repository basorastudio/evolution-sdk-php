# 🚀 EVOLUTION API SDK PHP - CONFIGURACIÓN COMPLETA

## 📋 Tu Configuración Actual

**🌐 Servidor Evolution API**
- **URL Base**: `https://whatsapp.ltd.do/`
- **Manager**: `https://whatsapp.ltd.do/manager`
- **API Key**: ``

## ⚡ Test Rápido

```bash
# Ejecutar test inmediato con tu configuración
cd /root/evolution-sdk-php
php quick_test.php
```

## 🔧 Ejemplos Listos para Usar

Todos los archivos de ejemplo ya están configurados con tu API Key:

### 1. Test de Instancias
```bash
php examples/test_instance.php
```

### 2. Test de Mensajes
```bash
php examples/test_messages.php
```

### 3. Test de Webhooks
```bash
php examples/test_webhooks.php
```

## 📱 Crear tu Primera Instancia

```php
<?php
require_once 'vendor/autoload.php';

use EvoApi\EvoClient;
use EvoApi\Instance;

// Tu configuración real
$client = new EvoClient('https://whatsapp.ltd.do/', '015359bd95a15617dba9a1434834a4ce');
$instance = new Instance($client);

// Crear instancia
$response = $instance->create([
    'instanceName' => 'mi-primera-instancia',
    'qrcode' => true
]);

if ($response['success']) {
    // Conectar y obtener QR
    $qr = $instance->connect('mi-primera-instancia');
    
    if ($qr['success'] && isset($qr['data']['qrcode'])) {
        echo "📱 QR Code generado!\n";
        echo "Escanea este código con WhatsApp:\n";
        // El QR está en base64: $qr['data']['qrcode']
    }
}
```

## 💬 Enviar tu Primer Mensaje

```php
<?php
use EvoApi\Message;

$message = new Message($client);

// Enviar mensaje
$response = $message->sendText(
    'mi-primera-instancia',
    '18297934075', // Tu número de prueba
    '¡Hola! Mi primer mensaje desde Evolution API 🎉'
);

if ($response['success']) {
    echo "✅ Mensaje enviado: " . $response['data']['messageId'];
}
```

## 🔗 Configurar Webhooks

```php
<?php
use EvoApi\Webhook;

$webhook = new Webhook($client);

// Configurar webhook para recibir mensajes
$webhook->setWebhook('mi-primera-instancia', 'https://tu-servidor.com/webhook', [
    'MESSAGES_UPSERT',
    'CONNECTION_UPDATE',
    'QRCODE_UPDATED'
]);
```

## 🎯 Próximos Pasos

1. **Ejecutar test rápido**:
   ```bash
   php quick_test.php
   ```

2. **Crear tu primera instancia**:
   - Usa el código de ejemplo arriba
   - Escanea el QR con WhatsApp
   - ¡Listo para enviar mensajes!

3. **Configurar webhooks**:
   - Actualiza la URL del webhook en `config.example.php`
   - Usa los ejemplos para configurar eventos

4. **Explorar funcionalidades avanzadas**:
   - Envío masivo con `EvoApi\Bulk`
   - Analytics con `EvoApi\Analytics`
   - Gestión de grupos con `EvoApi\Group`

## 🛡️ Seguridad

- ✅ Tu API Key ya está configurada
- ⚠️ **NO** compartas tu API Key públicamente
- 🔒 Usa HTTPS para todos los webhooks
- 📝 Mantén logs de las operaciones importantes

## 🆘 Soporte

Si necesitas ayuda:
1. Revisa los logs en `/logs/`
2. Verifica el estado del servidor: `https://whatsapp.ltd.do/manager`
3. Consulta la documentación oficial: [Evolution API Docs](https://doc.evolution-api.com/v2/)

---

🎉 **¡Tu SDK está completamente configurado y listo para usar!**
