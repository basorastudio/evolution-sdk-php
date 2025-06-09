<?php
/**
 * Archivo de configuración de ejemplo para Evolution SDK PHP
 * 
 * Copia este archivo como config.php y actualiza los valores
 */

return [
    // Configuración del servidor Evolution API
    'api' => [
        'base_url' => 'https://whatsapp.ltd.do/',
        'api_key' => 'YOUR_API_KEY_HERE', // Reemplaza con tu API Key real
        'timeout' => 30,
        'verify_ssl' => true,
        'debug' => false // Cambiar a true para debugging
    ],
    
    // Configuración de instancia por defecto
    'instance' => [
        'default_name' => 'mi-instancia',
        'qrcode' => true,
        'integration' => 'WHATSAPP-BAILEYS'
    ],
    
    // Configuración de webhooks
    'webhook' => [
        'url' => 'https://tu-servidor.com/webhook',
        'events' => [
            'MESSAGES_UPSERT',
            'MESSAGES_UPDATE', 
            'CONNECTION_UPDATE',
            'QRCODE_UPDATED'
        ]
    ],
    
    // Configuración de envío masivo
    'bulk' => [
        'rate_limit' => 5, // mensajes por minuto
        'batch_size' => 10,
        'retry_attempts' => 3,
        'delay_between_messages' => 1 // segundos
    ],
    
    // Configuración de logging
    'logging' => [
        'enabled' => true,
        'level' => 'info', // debug, info, warning, error
        'file' => __DIR__ . '/logs/evolution-sdk.log'
    ]
];