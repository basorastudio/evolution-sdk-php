<?php
/**
 * Archivo de configuración para Evolution SDK PHP
 * Configurado para https://whatsapp.ltd.do/
 */

return [
    // Configuración del servidor Evolution API
    'api' => [
        'base_url' => 'https://whatsapp.ltd.do/',
        'api_key' => '015359bd95a15617dba9a1434834a4ce',
        'manager_url' => 'https://whatsapp.ltd.do/manager',
        'timeout' => 30,
        'verify_ssl' => true,
        'debug' => false // Cambiar a true para debugging detallado
    ],
    
    // Configuración de instancia por defecto
    'instance' => [
        'default_name' => 'mi-instancia-' . date('Y-m-d'),
        'qrcode' => true,
        'integration' => 'WHATSAPP-BAILEYS',
        'webhook_by_events' => false,
        'reject_call' => false,
        'msg_retry_count' => 3
    ],
    
    // Configuración de webhooks
    'webhook' => [
        'url' => 'https://tu-servidor.com/webhook', // Actualiza con tu URL de webhook
        'events' => [
            'APPLICATION_STARTUP',
            'QRCODE_UPDATED',
            'MESSAGES_UPSERT',
            'MESSAGES_UPDATE',
            'MESSAGES_DELETE',
            'SEND_MESSAGE',
            'CONTACTS_UPDATE',
            'PRESENCE_UPDATE',
            'CHATS_UPDATE',
            'CHATS_DELETE',
            'GROUPS_UPSERT',
            'GROUP_UPDATE',
            'GROUP_PARTICIPANTS_UPDATE',
            'CONNECTION_UPDATE'
        ],
        'webhook_by_events' => false,
        'webhook_base64' => false
    ],
    
    // Configuración de envío masivo
    'bulk' => [
        'rate_limit' => 5, // mensajes por minuto
        'batch_size' => 10,
        'retry_attempts' => 3,
        'delay_between_messages' => 2, // segundos
        'max_concurrent' => 3
    ],
    
    // Configuración de logging
    'logging' => [
        'enabled' => true,
        'level' => 'info', // debug, info, warning, error
        'file' => __DIR__ . '/logs/evolution-sdk.log',
        'max_files' => 5,
        'max_size' => '10MB'
    ],
    
    // Configuración de reintentos
    'retry' => [
        'max_attempts' => 3,
        'delay' => 1000, // milisegundos
        'multiplier' => 2.0,
        'max_delay' => 30000 // milisegundos
    ],
    
    // Configuración de números de prueba
    'test' => [
        'phone_numbers' => [
            '18297934075', // Número principal de prueba
            '18093024075'  // Número secundario
        ],
        'instance_prefix' => 'test-' . date('Ymd'),
        'auto_cleanup' => true // Limpiar instancias de prueba automáticamente
    ]
];