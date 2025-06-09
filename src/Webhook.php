<?php
namespace EvoApi;

use EvoApi\Utils\ResponseHandler;

/**
 * Manejo avanzado de webhooks en Evolution API v2
 */
class Webhook {
    private EvoClient $client;
    private array $eventValidators;

    public function __construct(EvoClient $client) {
        $this->client = $client;
        $this->eventValidators = $this->initializeEventValidators();
    }

    /**
     * Configura un webhook para una instancia con validación avanzada
     */
    public function setWebhook(string $instance, string $url, array $events = [], array $options = []): array {
        // Validar URL
        $urlValidation = $this->validateWebhookUrl($url);
        if (!$urlValidation['valid']) {
            return [
                'success' => false,
                'error' => 'URL inválida: ' . $urlValidation['error'],
                'validation' => $urlValidation
            ];
        }

        // Validar eventos
        $eventValidation = $this->validateEvents($events);
        if (!$eventValidation['valid']) {
            return [
                'success' => false,
                'error' => 'Eventos inválidos: ' . implode(', ', $eventValidation['invalid_events']),
                'validation' => $eventValidation
            ];
        }

        $defaultData = [
            'url' => $url,
            'events' => $events ?: ['MESSAGE_RECEIVED', 'MESSAGE_SENT', 'CONNECTION_UPDATE'],
            'webhook_by_events' => true,
            'webhook_base64' => false,
            'webhook_headers' => [],
            'auth' => null
        ];

        $data = array_merge($defaultData, $options);

        // Probar conectividad antes de configurar
        if ($options['test_before_set'] ?? true) {
            $connectivityTest = $this->testWebhookConnectivity($url, $options['webhook_headers'] ?? []);
            if (!$connectivityTest['reachable']) {
                return [
                    'success' => false,
                    'error' => 'No se puede alcanzar la URL del webhook',
                    'connectivity_test' => $connectivityTest,
                    'recommendation' => 'Verifique que la URL esté activa y accesible'
                ];
            }
        }

        $response = $this->client->post("webhook/set/{$instance}", $data);

        // Si la configuración fue exitosa, realizar test automático
        if (ResponseHandler::isSuccess($response) && ($options['auto_test'] ?? true)) {
            $testResponse = $this->test($instance);
            $response['test_result'] = $testResponse;
        }

        return $response;
    }

    /**
     * Obtiene la configuración actual del webhook con estado detallado
     */
    public function getWebhook(string $instance): array {
        $response = $this->client->get("webhook/find/{$instance}");
        
        if (ResponseHandler::isSuccess($response)) {
            $data = ResponseHandler::getData($response);
            
            // Enriquecer con información adicional
            $response['webhook_info'] = [
                'is_configured' => !empty($data['url']),
                'is_active' => $data['enabled'] ?? false,
                'event_count' => count($data['events'] ?? []),
                'last_updated' => $data['updated_at'] ?? null,
                'status_summary' => $this->generateWebhookStatusSummary($data)
            ];
        }
        
        return $response;
    }

    /**
     * Remueve el webhook de una instancia con confirmación
     */
    public function removeWebhook(string $instance, bool $force = false): array {
        if (!$force) {
            // Verificar que el webhook existe antes de eliminar
            $currentWebhook = $this->getWebhook($instance);
            if (!ResponseHandler::isSuccess($currentWebhook)) {
                return [
                    'success' => false,
                    'error' => 'No se pudo verificar el webhook existente',
                    'current_webhook_response' => $currentWebhook
                ];
            }
            
            $webhookData = ResponseHandler::getData($currentWebhook);
            if (empty($webhookData['url'])) {
                return [
                    'success' => true,
                    'message' => 'No hay webhook configurado para eliminar',
                    'action_taken' => 'none'
                ];
            }
        }

        return $this->client->delete("webhook/remove/{$instance}");
    }

    /**
     * Lista todos los webhooks configurados con análisis
     */
    public function listWebhooks(): array {
        $response = $this->client->get("webhook/findAll");
        
        if (ResponseHandler::isSuccess($response)) {
            $webhooks = ResponseHandler::getData($response);
            
            // Análisis de webhooks
            $analysis = [
                'total_webhooks' => count($webhooks),
                'active_webhooks' => count(array_filter($webhooks, fn($w) => $w['enabled'] ?? false)),
                'inactive_webhooks' => count(array_filter($webhooks, fn($w) => !($w['enabled'] ?? false))),
                'unique_urls' => count(array_unique(array_column($webhooks, 'url'))),
                'most_common_events' => $this->analyzeCommonEvents($webhooks),
                'status_distribution' => $this->analyzeWebhookStatuses($webhooks)
            ];
            
            $response['analysis'] = $analysis;
        }
        
        return $response;
    }

    /**
     * Activa el webhook de una instancia con verificación
     */
    public function activate(string $instance): array {
        // Verificar que hay webhook configurado
        $currentWebhook = $this->getWebhook($instance);
        if (!ResponseHandler::isSuccess($currentWebhook)) {
            return [
                'success' => false,
                'error' => 'No se pudo verificar la configuración del webhook'
            ];
        }
        
        $webhookData = ResponseHandler::getData($currentWebhook);
        if (empty($webhookData['url'])) {
            return [
                'success' => false,
                'error' => 'No hay webhook configurado para activar'
            ];
        }

        $response = $this->client->put("webhook/activate/{$instance}");
        
        // Test automático después de activar
        if (ResponseHandler::isSuccess($response)) {
            $testResponse = $this->test($instance);
            $response['activation_test'] = $testResponse;
        }
        
        return $response;
    }

    /**
     * Desactiva el webhook de una instancia
     */
    public function deactivate(string $instance): array {
        return $this->client->put("webhook/deactivate/{$instance}");
    }

    /**
     * Prueba el webhook enviando un evento de prueba con análisis detallado
     */
    public function test(string $instance): array {
        $startTime = microtime(true);
        $response = $this->client->post("webhook/test/{$instance}");
        $endTime = microtime(true);
        
        $response['test_metrics'] = [
            'response_time_ms' => round(($endTime - $startTime) * 1000, 2),
            'test_timestamp' => date('c'),
            'test_successful' => ResponseHandler::isSuccess($response)
        ];
        
        return $response;
    }

    /**
     * Actualiza eventos del webhook con validación
     */
    public function updateEvents(string $instance, array $events): array {
        $validation = $this->validateEvents($events);
        
        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => 'Eventos inválidos',
                'validation' => $validation
            ];
        }

        return $this->client->put("webhook/updateEvents/{$instance}", [
            'events' => $events
        ]);
    }

    /**
     * Obtiene eventos disponibles para webhooks con descripción
     */
    public function getAvailableEvents(): array {
        return [
            'MESSAGE_RECEIVED' => [
                'event' => 'MESSAGE_RECEIVED',
                'description' => 'Se recibe un nuevo mensaje',
                'data_fields' => ['message', 'key', 'pushName', 'participant']
            ],
            'MESSAGE_SENT' => [
                'event' => 'MESSAGE_SENT',
                'description' => 'Se envía un mensaje',
                'data_fields' => ['message', 'key', 'status']
            ],
            'MESSAGE_ACK' => [
                'event' => 'MESSAGE_ACK',
                'description' => 'Cambio en el estado de entrega del mensaje',
                'data_fields' => ['key', 'ack', 'chatId']
            ],
            'MESSAGE_DELETED' => [
                'event' => 'MESSAGE_DELETED',
                'description' => 'Un mensaje fue eliminado',
                'data_fields' => ['key', 'message']
            ],
            'CONNECTION_UPDATE' => [
                'event' => 'CONNECTION_UPDATE',
                'description' => 'Cambio en el estado de conexión',
                'data_fields' => ['state', 'statusReason']
            ],
            'QRCODE_UPDATED' => [
                'event' => 'QRCODE_UPDATED',
                'description' => 'Se genera un nuevo código QR',
                'data_fields' => ['qrcode', 'base64']
            ],
            'GROUP_PARTICIPANTS_UPDATE' => [
                'event' => 'GROUP_PARTICIPANTS_UPDATE',
                'description' => 'Cambios en participantes de grupo',
                'data_fields' => ['groupJid', 'participants', 'action']
            ],
            'GROUP_UPDATE' => [
                'event' => 'GROUP_UPDATE',
                'description' => 'Actualización de información del grupo',
                'data_fields' => ['groupJid', 'groupUpdate']
            ],
            'PRESENCE_UPDATE' => [
                'event' => 'PRESENCE_UPDATE',
                'description' => 'Cambio en el estado de presencia',
                'data_fields' => ['presence', 'chatId']
            ],
            'CHATS_SET' => [
                'event' => 'CHATS_SET',
                'description' => 'Lista de chats actualizada',
                'data_fields' => ['chats']
            ],
            'CONTACTS_SET' => [
                'event' => 'CONTACTS_SET',
                'description' => 'Lista de contactos actualizada',
                'data_fields' => ['contacts']
            ],
            'CHAT_UPDATE' => [
                'event' => 'CHAT_UPDATE',
                'description' => 'Actualización de un chat',
                'data_fields' => ['chatId', 'chatUpdate']
            ],
            'CHAT_DELETE' => [
                'event' => 'CHAT_DELETE',
                'description' => 'Un chat fue eliminado',
                'data_fields' => ['chatId']
            ]
        ];
    }

    /**
     * Configurar webhook con retry automático
     */
    public function setWebhookWithRetry(string $instance, string $url, array $events = [], array $options = [], int $maxRetries = 3): array {
        $attempts = 0;
        $lastError = null;
        
        while ($attempts < $maxRetries) {
            $response = $this->setWebhook($instance, $url, $events, $options);
            
            if (ResponseHandler::isSuccess($response)) {
                $response['retry_info'] = [
                    'attempts_made' => $attempts + 1,
                    'max_retries' => $maxRetries,
                    'success_on_attempt' => $attempts + 1
                ];
                return $response;
            }
            
            $lastError = $response;
            $attempts++;
            
            if ($attempts < $maxRetries) {
                sleep(pow(2, $attempts)); // Backoff exponencial
            }
        }
        
        return [
            'success' => false,
            'error' => 'Falló después de todos los reintentos',
            'attempts_made' => $attempts,
            'max_retries' => $maxRetries,
            'last_error' => $lastError
        ];
    }

    /**
     * Monitorear estado del webhook
     */
    public function monitorWebhook(string $instance, int $intervalSeconds = 60, int $duration = 300, callable $callback = null): array {
        $startTime = time();
        $endTime = $startTime + $duration;
        $checks = [];
        
        while (time() < $endTime) {
            $checkTime = time();
            $status = $this->getWebhook($instance);
            $testResult = null;
            
            if (ResponseHandler::isSuccess($status)) {
                $webhookData = ResponseHandler::getData($status);
                if ($webhookData['enabled'] ?? false) {
                    $testResult = $this->test($instance);
                }
            }
            
            $check = [
                'timestamp' => date('c', $checkTime),
                'elapsed_seconds' => $checkTime - $startTime,
                'status_check' => $status,
                'test_result' => $testResult,
                'webhook_healthy' => ResponseHandler::isSuccess($status) && 
                                   ($testResult ? ResponseHandler::isSuccess($testResult) : true)
            ];
            
            $checks[] = $check;
            
            // Llamar callback si se proporciona
            if ($callback && is_callable($callback)) {
                $continue = $callback($check, count($checks));
                if ($continue === false) {
                    break;
                }
            }
            
            sleep($intervalSeconds);
        }
        
        $healthyChecks = array_filter($checks, fn($c) => $c['webhook_healthy']);
        
        return [
            'monitoring_summary' => [
                'start_time' => date('c', $startTime),
                'end_time' => date('c'),
                'duration_seconds' => time() - $startTime,
                'total_checks' => count($checks),
                'healthy_checks' => count($healthyChecks),
                'health_percentage' => count($checks) > 0 ? round((count($healthyChecks) / count($checks)) * 100, 2) : 0
            ],
            'checks' => $checks
        ];
    }

    /**
     * Analizar logs de webhook (simulado - requeriría integración con logs reales)
     */
    public function analyzeWebhookLogs(string $instance, array $options = []): array {
        // Esta función simula análisis de logs
        // En implementación real, se conectaría con sistema de logs
        
        $webhookInfo = $this->getWebhook($instance);
        
        if (!ResponseHandler::isSuccess($webhookInfo)) {
            return [
                'success' => false,
                'error' => 'No se pudo obtener información del webhook'
            ];
        }
        
        // Simulación de análisis de logs
        $analysis = [
            'log_analysis_period' => $options['period'] ?? '24h',
            'total_events_sent' => rand(100, 1000),
            'successful_deliveries' => rand(90, 99),
            'failed_deliveries' => rand(1, 10),
            'average_response_time' => rand(50, 200) . 'ms',
            'most_frequent_events' => ['MESSAGE_RECEIVED', 'MESSAGE_SENT', 'CONNECTION_UPDATE'],
            'error_distribution' => [
                'timeout' => rand(0, 5),
                'connection_refused' => rand(0, 3),
                'http_error' => rand(0, 2)
            ],
            'recommendations' => []
        ];
        
        // Generar recomendaciones basadas en análisis
        if ($analysis['failed_deliveries'] > 5) {
            $analysis['recommendations'][] = 'Alto número de fallos - revisar conectividad del endpoint';
        }
        
        if ($analysis['average_response_time'] > 150) {
            $analysis['recommendations'][] = 'Tiempo de respuesta alto - optimizar endpoint webhook';
        }
        
        return [
            'success' => true,
            'instance' => $instance,
            'analysis' => $analysis
        ];
    }

    /**
     * Configurar webhook con autenticación avanzada
     */
    public function setSecureWebhook(string $instance, string $url, array $auth, array $events = [], array $options = []): array {
        $securityValidation = $this->validateWebhookSecurity($auth);
        
        if (!$securityValidation['valid']) {
            return [
                'success' => false,
                'error' => 'Configuración de seguridad inválida',
                'validation' => $securityValidation
            ];
        }
        
        $secureOptions = array_merge($options, [
            'auth' => $auth,
            'webhook_headers' => $this->generateSecurityHeaders($auth)
        ]);
        
        return $this->setWebhook($instance, $url, $events, $secureOptions);
    }

    /**
     * Exportar configuración de webhook
     */
    public function exportWebhookConfig(string $instance, string $format = 'json'): array {
        $webhookInfo = $this->getWebhook($instance);
        
        if (!ResponseHandler::isSuccess($webhookInfo)) {
            return $webhookInfo;
        }
        
        $data = ResponseHandler::getData($webhookInfo);
        $exportData = [
            'export_date' => date('c'),
            'instance' => $instance,
            'sdk_version' => '1.0.0',
            'webhook_config' => $data
        ];
        
        switch ($format) {
            case 'json':
                return [
                    'success' => true,
                    'format' => 'json',
                    'data' => json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                ];
                
            case 'env':
                $envData = "# Webhook Configuration for {$instance}\n";
                $envData .= "WEBHOOK_URL=\"{$data['url']}\"\n";
                $envData .= "WEBHOOK_EVENTS=\"" . implode(',', $data['events'] ?? []) . "\"\n";
                $envData .= "WEBHOOK_ENABLED=" . (($data['enabled'] ?? false) ? 'true' : 'false') . "\n";
                
                return [
                    'success' => true,
                    'format' => 'env',
                    'data' => $envData
                ];
                
            default:
                return [
                    'success' => false,
                    'error' => 'Formato no soportado: ' . $format
                ];
        }
    }

    // Métodos auxiliares privados

    private function validateWebhookUrl(string $url): array {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return ['valid' => false, 'error' => 'Formato de URL inválido'];
        }
        
        $parsedUrl = parse_url($url);
        
        if (!in_array($parsedUrl['scheme'] ?? '', ['http', 'https'])) {
            return ['valid' => false, 'error' => 'Solo se permiten esquemas HTTP/HTTPS'];
        }
        
        if (($parsedUrl['scheme'] ?? '') === 'http') {
            return [
                'valid' => true,
                'warning' => 'Se recomienda usar HTTPS para mayor seguridad'
            ];
        }
        
        return ['valid' => true];
    }

    private function validateEvents(array $events): array {
        $availableEvents = array_keys($this->getAvailableEvents());
        $invalidEvents = array_diff($events, $availableEvents);
        
        return [
            'valid' => empty($invalidEvents),
            'invalid_events' => $invalidEvents,
            'valid_events' => array_intersect($events, $availableEvents),
            'total_events' => count($events)
        ];
    }

    private function testWebhookConnectivity(string $url, array $headers = []): array {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => 'HEAD',
            CURLOPT_NOBODY => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        return [
            'reachable' => $response !== false && $httpCode < 500,
            'http_code' => $httpCode,
            'error' => $error,
            'response_received' => $response !== false
        ];
    }

    private function generateWebhookStatusSummary(array $webhookData): string {
        if (empty($webhookData['url'])) {
            return 'No configurado';
        }
        
        if (!($webhookData['enabled'] ?? false)) {
            return 'Configurado pero inactivo';
        }
        
        $eventCount = count($webhookData['events'] ?? []);
        return "Activo con {$eventCount} eventos configurados";
    }

    private function analyzeCommonEvents(array $webhooks): array {
        $eventCount = [];
        
        foreach ($webhooks as $webhook) {
            foreach ($webhook['events'] ?? [] as $event) {
                $eventCount[$event] = ($eventCount[$event] ?? 0) + 1;
            }
        }
        
        arsort($eventCount);
        return array_slice($eventCount, 0, 5, true);
    }

    private function analyzeWebhookStatuses(array $webhooks): array {
        $statuses = [];
        
        foreach ($webhooks as $webhook) {
            $status = empty($webhook['url']) ? 'not_configured' : 
                     (($webhook['enabled'] ?? false) ? 'active' : 'inactive');
            $statuses[$status] = ($statuses[$status] ?? 0) + 1;
        }
        
        return $statuses;
    }

    private function validateWebhookSecurity(array $auth): array {
        $supportedTypes = ['bearer', 'basic', 'api_key', 'custom'];
        
        if (!isset($auth['type']) || !in_array($auth['type'], $supportedTypes)) {
            return [
                'valid' => false,
                'error' => 'Tipo de autenticación no soportado',
                'supported_types' => $supportedTypes
            ];
        }
        
        switch ($auth['type']) {
            case 'bearer':
                if (empty($auth['token'])) {
                    return ['valid' => false, 'error' => 'Token Bearer requerido'];
                }
                break;
                
            case 'basic':
                if (empty($auth['username']) || empty($auth['password'])) {
                    return ['valid' => false, 'error' => 'Usuario y contraseña requeridos para auth basic'];
                }
                break;
                
            case 'api_key':
                if (empty($auth['key']) || empty($auth['value'])) {
                    return ['valid' => false, 'error' => 'Clave y valor de API key requeridos'];
                }
                break;
        }
        
        return ['valid' => true];
    }

    private function generateSecurityHeaders(array $auth): array {
        $headers = [];
        
        switch ($auth['type']) {
            case 'bearer':
                $headers[] = 'Authorization: Bearer ' . $auth['token'];
                break;
                
            case 'basic':
                $headers[] = 'Authorization: Basic ' . base64_encode($auth['username'] . ':' . $auth['password']);
                break;
                
            case 'api_key':
                $headers[] = $auth['key'] . ': ' . $auth['value'];
                break;
                
            case 'custom':
                foreach ($auth['headers'] ?? [] as $key => $value) {
                    $headers[] = $key . ': ' . $value;
                }
                break;
        }
        
        return $headers;
    }

    private function initializeEventValidators(): array {
        // Podría expandirse para validaciones específicas por evento
        return [];
    }
}