<?php
namespace EvoApi;

use EvoApi\Utils\ResponseHandler;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Cliente principal para Evolution API
 */
class EvoClient {
    private string $baseUrl;
    private string $apiKey;
    private Client $httpClient;
    private array $defaultHeaders;
    private bool $debugMode;
    private array $performanceMetrics;

    public function __construct(string $baseUrl, string $apiKey, array $options = []) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
        $this->debugMode = $options['debug'] ?? false;
        $this->performanceMetrics = [];

        $this->defaultHeaders = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'apikey' => $this->apiKey,
            'User-Agent' => 'Evolution-SDK-PHP/1.0.0'
        ];

        $httpOptions = [
            'timeout' => $options['timeout'] ?? 30,
            'connect_timeout' => $options['connect_timeout'] ?? 10,
            'headers' => $this->defaultHeaders,
            'verify' => $options['verify_ssl'] ?? true
        ];

        $this->httpClient = new Client($httpOptions);
    }

    /**
     * Realizar solicitud HTTP
     */
    private function makeRequest(string $method, string $endpoint, array $data = []): array {
        $startTime = microtime(true);
        $operationId = ResponseHandler::generateOperationId($method . '_' . $endpoint, $data);

        try {
            $url = $this->baseUrl . '/' . ltrim($endpoint, '/');
            
            $options = [
                'headers' => $this->defaultHeaders
            ];

            if (!empty($data)) {
                if (strtoupper($method) === 'GET') {
                    $options['query'] = $data;
                } else {
                    $options['json'] = $data;
                }
            }

            if ($this->debugMode) {
                error_log("Evolution API Request: {$method} {$url} " . json_encode($data));
            }

            $response = $this->httpClient->request($method, $url, $options);
            
            $responseTime = microtime(true) - $startTime;
            $statusCode = $response->getStatusCode();
            $body = json_decode($response->getBody()->getContents(), true) ?? [];
            
            $result = [
                'success' => $statusCode >= 200 && $statusCode < 300,
                'status_code' => $statusCode,
                'data' => $body,
                'operation_id' => $operationId,
                'response_time' => $responseTime,
                'timestamp' => date('c'),
                'headers' => $response->getHeaders()
            ];

            // Agregar métricas de rendimiento
            $this->addPerformanceMetric($operationId, $responseTime, $statusCode >= 200 && $statusCode < 300);

            if ($this->debugMode) {
                error_log("Evolution API Response: " . json_encode(ResponseHandler::sanitizeForLog($result)));
            }

            return $result;

        } catch (RequestException $e) {
            $responseTime = microtime(true) - $startTime;
            $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 0;
            
            $result = [
                'success' => false,
                'status_code' => $statusCode,
                'error' => $e->getMessage(),
                'operation_id' => $operationId,
                'response_time' => $responseTime,
                'timestamp' => date('c')
            ];

            $this->addPerformanceMetric($operationId, $responseTime, false);

            if ($this->debugMode) {
                error_log("Evolution API Error: " . json_encode($result));
            }

            return ResponseHandler::handleCommonErrors($result);
        }
    }

    /**
     * Agregar métrica de rendimiento
     */
    private function addPerformanceMetric(string $operationId, float $responseTime, bool $success): void {
        $this->performanceMetrics[] = [
            'operation_id' => $operationId,
            'response_time' => $responseTime,
            'success' => $success,
            'timestamp' => microtime(true)
        ];

        // Mantener solo las últimas 1000 métricas
        if (count($this->performanceMetrics) > 1000) {
            $this->performanceMetrics = array_slice($this->performanceMetrics, -1000);
        }
    }

    /**
     * Obtener información de la API
     */
    public function getInformation(): array {
        return $this->makeRequest('GET', '/');
    }

    /**
     * Verificar salud de la API
     */
    public function healthCheck(): array {
        $response = $this->getInformation();
        
        if (ResponseHandler::isSuccess($response)) {
            $data = ResponseHandler::getData($response);
            return [
                'success' => true,
                'status' => 'healthy',
                'version' => $data['version'] ?? 'unknown',
                'manager' => $data['manager'] ?? null,
                'response_time' => $response['response_time'] ?? 0,
                'timestamp' => date('c')
            ];
        }

        return [
            'success' => false,
            'status' => 'unhealthy',
            'error' => ResponseHandler::getError($response),
            'timestamp' => date('c')
        ];
    }

    /**
     * Obtener versión de la API
     */
    public function getVersion(): array {
        $response = $this->getInformation();
        $data = ResponseHandler::getData($response);
        
        return [
            'version' => $data['version'] ?? 'unknown',
            'swagger' => $data['swagger'] ?? null,
            'documentation' => $data['documentation'] ?? null
        ];
    }

    /**
     * Listar todas las instancias
     */
    public function listInstances(): array {
        return $this->makeRequest('GET', '/instance/fetchInstances');
    }

    /**
     * Crear nueva instancia
     */
    public function createInstance(string $instanceName, array $config = []): array {
        $data = array_merge([
            'instanceName' => $instanceName,
            'token' => $config['token'] ?? null,
            'qrcode' => $config['qrcode'] ?? true,
            'integration' => $config['integration'] ?? 'WHATSAPP-BAILEYS'
        ], $config);

        return $this->makeRequest('POST', '/instance/create', $data);
    }

    /**
     * Conectar instancia
     */
    public function connectInstance(string $instanceName): array {
        return $this->makeRequest('GET', "/instance/connect/{$instanceName}");
    }

    /**
     * Desconectar instancia
     */
    public function disconnectInstance(string $instanceName): array {
        return $this->makeRequest('DELETE', "/instance/logout/{$instanceName}");
    }

    /**
     * Eliminar instancia
     */
    public function deleteInstance(string $instanceName): array {
        return $this->makeRequest('DELETE', "/instance/delete/{$instanceName}");
    }

    /**
     * Obtener estado de instancia
     */
    public function getInstanceStatus(string $instanceName): array {
        return $this->makeRequest('GET', "/instance/connectionState/{$instanceName}");
    }

    /**
     * Enviar mensaje de texto
     */
    public function sendTextMessage(string $instanceName, string $number, string $text, array $options = []): array {
        $phoneValidation = ResponseHandler::validatePhoneNumber($number);
        
        if (!$phoneValidation['valid']) {
            return [
                'success' => false,
                'error' => 'Número de teléfono inválido: ' . implode(', ', $phoneValidation['issues']),
                'phone_validation' => $phoneValidation
            ];
        }

        $data = [
            'number' => $phoneValidation['formatted'],
            'text' => $text
        ];

        if (!empty($options)) {
            $data = array_merge($data, $options);
        }

        return $this->makeRequest('POST', "/message/sendText/{$instanceName}", $data);
    }

    /**
     * Enviar mensaje multimedia
     */
    public function sendMediaMessage(string $instanceName, string $number, string $mediaUrl, string $mediaType, array $options = []): array {
        $phoneValidation = ResponseHandler::validatePhoneNumber($number);
        
        if (!$phoneValidation['valid']) {
            return [
                'success' => false,
                'error' => 'Número de teléfono inválido: ' . implode(', ', $phoneValidation['issues']),
                'phone_validation' => $phoneValidation
            ];
        }

        $data = [
            'number' => $phoneValidation['formatted'],
            'media' => $mediaUrl,
            'mediatype' => $mediaType
        ];

        if (!empty($options['caption'])) {
            $data['caption'] = $options['caption'];
        }

        return $this->makeRequest('POST', "/message/sendMedia/{$instanceName}", $data);
    }

    /**
     * Enviar mensajes en lote
     */
    public function sendBulkMessages(string $instanceName, array $messages, array $options = []): array {
        $responses = [];
        $delay = $options['delay'] ?? 1; // Retraso entre mensajes en segundos
        $maxRetries = $options['max_retries'] ?? 3;

        foreach ($messages as $index => $message) {
            $attempt = 0;
            $success = false;

            while ($attempt < $maxRetries && !$success) {
                if (isset($message['text'])) {
                    $response = $this->sendTextMessage(
                        $instanceName,
                        $message['number'],
                        $message['text'],
                        $message['options'] ?? []
                    );
                } elseif (isset($message['media'])) {
                    $response = $this->sendMediaMessage(
                        $instanceName,
                        $message['number'],
                        $message['media'],
                        $message['mediatype'] ?? 'image',
                        $message['options'] ?? []
                    );
                } else {
                    $response = [
                        'success' => false,
                        'error' => 'Mensaje inválido: debe contener "text" o "media"'
                    ];
                }

                $response['message_index'] = $index;
                $response['attempt'] = $attempt + 1;
                $responses[] = $response;

                if (ResponseHandler::isSuccess($response)) {
                    $success = true;
                } else {
                    $attempt++;
                    if ($attempt < $maxRetries) {
                        sleep($delay * $attempt); // Backoff exponencial
                    }
                }
            }

            // Retraso entre mensajes
            if ($index < count($messages) - 1) {
                sleep($delay);
            }
        }

        return ResponseHandler::combineResponses($responses, 'bulk_messages');
    }

    /**
     * Configurar webhook
     */
    public function setWebhook(string $instanceName, string $webhookUrl, array $events = [], array $options = []): array {
        $data = [
            'webhook' => $webhookUrl,
            'events' => !empty($events) ? $events : [
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
            ]
        ];

        if (!empty($options)) {
            $data = array_merge($data, $options);
        }

        return $this->makeRequest('POST', "/webhook/set/{$instanceName}", $data);
    }

    /**
     * Obtener configuración de webhook
     */
    public function getWebhook(string $instanceName): array {
        return $this->makeRequest('GET', "/webhook/find/{$instanceName}");
    }

    /**
     * Procesar webhook recibido
     */
    public function processWebhook(array $webhookData): array {
        $validation = ResponseHandler::validateWebhookData($webhookData);
        
        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => 'Webhook inválido: ' . implode(', ', $validation['issues']),
                'validation' => $validation
            ];
        }

        $processedData = [
            'event_type' => $webhookData['event'],
            'instance' => $webhookData['instance'],
            'timestamp' => $webhookData['date_time'] ?? date('c'),
            'data' => $webhookData['data']
        ];

        // Extraer información específica según el tipo de evento
        switch ($webhookData['event']) {
            case 'MESSAGES_UPSERT':
                $processedData['message_info'] = ResponseHandler::extractMessageFromWebhook($webhookData);
                break;
            
            case 'QRCODE_UPDATED':
                $processedData['qr_code'] = $webhookData['data']['qrcode'] ?? null;
                break;
            
            case 'CONNECTION_UPDATE':
                $processedData['connection_status'] = $webhookData['data']['state'] ?? 'unknown';
                break;
        }

        return [
            'success' => true,
            'processed_data' => $processedData,
            'raw_webhook' => $webhookData
        ];
    }

    /**
     * Obtener métricas de rendimiento
     */
    public function getPerformanceMetrics(): array {
        if (empty($this->performanceMetrics)) {
            return [
                'message' => 'No hay métricas disponibles',
                'metrics' => []
            ];
        }

        return ResponseHandler::generatePerformanceStats($this->performanceMetrics);
    }

    /**
     * Limpiar métricas de rendimiento
     */
    public function clearPerformanceMetrics(): void {
        $this->performanceMetrics = [];
    }

    /**
     * Generar reporte de errores
     */
    public function getErrorReport(): array {
        return ResponseHandler::generateErrorReport($this->performanceMetrics);
    }

    /**
     * Configurar modo debug
     */
    public function setDebugMode(bool $enabled): void {
        $this->debugMode = $enabled;
    }

    /**
     * Obtener configuración actual
     */
    public function getConfig(): array {
        return [
            'base_url' => $this->baseUrl,
            'debug_mode' => $this->debugMode,
            'metrics_count' => count($this->performanceMetrics),
            'default_headers' => ResponseHandler::sanitizeForLog($this->defaultHeaders)
        ];
    }

    /**
     * Validar conectividad con la API
     */
    public function validateConnection(): array {
        $startTime = microtime(true);
        $response = $this->healthCheck();
        $endTime = microtime(true);

        return [
            'connected' => $response['success'],
            'response_time' => round($endTime - $startTime, 3),
            'api_version' => $response['version'] ?? 'unknown',
            'timestamp' => date('c'),
            'details' => $response
        ];
    }

    /**
     * Obtener estadísticas de uso
     */
    public function getUsageStats(): array {
        $totalRequests = count($this->performanceMetrics);
        $successful = array_filter($this->performanceMetrics, fn($m) => $m['success']);
        $failed = array_filter($this->performanceMetrics, fn($m) => !$m['success']);

        $avgResponseTime = $totalRequests > 0 ? 
            array_sum(array_column($this->performanceMetrics, 'response_time')) / $totalRequests : 0;

        return [
            'total_requests' => $totalRequests,
            'successful_requests' => count($successful),
            'failed_requests' => count($failed),
            'success_rate' => $totalRequests > 0 ? (count($successful) / $totalRequests) * 100 : 0,
            'average_response_time' => round($avgResponseTime, 3),
            'uptime_start' => $this->performanceMetrics[0]['timestamp'] ?? null,
            'last_request' => end($this->performanceMetrics)['timestamp'] ?? null
        ];
    }

    /**
     * Reiniciar instancia
     */
    public function restartInstance(string $instanceName): array {
        return $this->makeRequest('PUT', "/instance/restart/{$instanceName}");
    }

    /**
     * Obtener perfil de WhatsApp
     */
    public function getProfile(string $instanceName): array {
        return $this->makeRequest('GET', "/chat/fetchProfile/{$instanceName}");
    }

    /**
     * Actualizar perfil de WhatsApp
     */
    public function updateProfile(string $instanceName, array $profileData): array {
        return $this->makeRequest('PUT', "/chat/updateProfileName/{$instanceName}", $profileData);
    }

    /**
     * Obtener chats
     */
    public function getChats(string $instanceName): array {
        return $this->makeRequest('GET', "/chat/findChats/{$instanceName}");
    }

    /**
     * Obtener contactos
     */
    public function getContacts(string $instanceName): array {
        return $this->makeRequest('GET', "/chat/findContacts/{$instanceName}");
    }

    /**
     * Verificar si un número existe en WhatsApp
     */
    public function checkWhatsAppNumber(string $instanceName, array $numbers): array {
        $data = ['numbers' => $numbers];
        return $this->makeRequest('POST', "/chat/whatsappNumbers/{$instanceName}", $data);
    }

    /**
     * Obtener mensajes de un chat
     */
    public function getChatMessages(string $instanceName, string $remoteJid, array $options = []): array {
        $data = array_merge(['remoteJid' => $remoteJid], $options);
        return $this->makeRequest('POST', "/chat/findMessages/{$instanceName}", $data);
    }

    /**
     * Marcar mensajes como leídos
     */
    public function markAsRead(string $instanceName, string $remoteJid, array $messageIds = []): array {
        $data = [
            'readMessages' => [
                'remoteJid' => $remoteJid,
                'id' => $messageIds
            ]
        ];
        return $this->makeRequest('PUT', "/chat/markMessageAsRead/{$instanceName}", $data);
    }

    /**
     * Realizar petición GET
     */
    public function get(string $endpoint, array $params = []): array {
        return $this->makeRequest('GET', $endpoint, $params);
    }

    /**
     * Realizar petición POST
     */
    public function post(string $endpoint, array $data = []): array {
        return $this->makeRequest('POST', $endpoint, $data);
    }

    /**
     * Realizar petición PUT
     */
    public function put(string $endpoint, array $data = []): array {
        return $this->makeRequest('PUT', $endpoint, $data);
    }

    /**
     * Realizar petición DELETE
     */
    public function delete(string $endpoint, array $data = []): array {
        return $this->makeRequest('DELETE', $endpoint, $data);
    }

    /**
     * Subir archivo
     */
    public function uploadFile(string $endpoint, string $fieldName, string $filePath, array $additionalData = []): array {
        $startTime = microtime(true);
        $operationId = ResponseHandler::generateOperationId('UPLOAD_' . $endpoint, ['file' => basename($filePath)]);

        try {
            if (!file_exists($filePath)) {
                return [
                    'success' => false,
                    'error' => 'Archivo no encontrado: ' . $filePath,
                    'operation_id' => $operationId
                ];
            }

            $url = $this->baseUrl . '/' . ltrim($endpoint, '/');
            
            $multipart = [
                [
                    'name' => $fieldName,
                    'contents' => fopen($filePath, 'r'),
                    'filename' => basename($filePath)
                ]
            ];

            // Añadir datos adicionales
            foreach ($additionalData as $key => $value) {
                $multipart[] = [
                    'name' => $key,
                    'contents' => is_array($value) ? json_encode($value) : $value
                ];
            }

            $options = [
                'headers' => array_merge($this->defaultHeaders, ['Content-Type' => 'multipart/form-data']),
                'multipart' => $multipart
            ];

            unset($options['headers']['Content-Type']); // Guzzle maneja esto automáticamente

            if ($this->debugMode) {
                error_log("Evolution API File Upload: POST {$url} " . basename($filePath));
            }

            $response = $this->httpClient->request('POST', $url, $options);
            
            $responseTime = microtime(true) - $startTime;
            $statusCode = $response->getStatusCode();
            $body = json_decode($response->getBody()->getContents(), true) ?? [];
            
            $result = [
                'success' => $statusCode >= 200 && $statusCode < 300,
                'status_code' => $statusCode,
                'data' => $body,
                'operation_id' => $operationId,
                'response_time' => $responseTime,
                'timestamp' => date('c'),
                'file_uploaded' => basename($filePath)
            ];

            $this->addPerformanceMetric($operationId, $responseTime, $statusCode >= 200 && $statusCode < 300);

            return $result;

        } catch (RequestException $e) {
            $responseTime = microtime(true) - $startTime;
            $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 0;
            
            $result = [
                'success' => false,
                'status_code' => $statusCode,
                'error' => $e->getMessage(),
                'operation_id' => $operationId,
                'response_time' => $responseTime,
                'timestamp' => date('c'),
                'file_attempted' => basename($filePath)
            ];

            $this->addPerformanceMetric($operationId, $responseTime, false);

            return ResponseHandler::handleCommonErrors($result);
        }
    }
}