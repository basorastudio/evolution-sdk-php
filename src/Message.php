<?php
namespace EvoApi;

use EvoApi\Utils\ResponseHandler;

/**
 * Gestión de mensajes para Evolution API v2
 */
class Message {
    private EvoClient $client;

    public function __construct(EvoClient $client) {
        $this->client = $client;
    }

    /**
     * Enviar mensaje de texto
     */
    public function sendText(string $instanceName, string $number, string $text, array $options = []): array {
        // Validar número de teléfono
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
            'text' => $text,
            'delay' => $options['delay'] ?? 1200,
            'quoted' => $options['quoted'] ?? null,
            'mentions' => $options['mentions'] ?? null
        ];

        return $this->client->post("/message/sendText/{$instanceName}", $data);
    }

    /**
     * Enviar mensaje con imagen
     */
    public function sendMedia(string $instanceName, string $number, string $mediaUrl, string $mediaType = 'image', array $options = []): array {
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
            'mediatype' => $mediaType,
            'caption' => $options['caption'] ?? '',
            'delay' => $options['delay'] ?? 1200,
            'quoted' => $options['quoted'] ?? null,
            'mentions' => $options['mentions'] ?? null
        ];

        return $this->client->post("/message/sendMedia/{$instanceName}", $data);
    }

    /**
     * Subir y enviar archivo local
     */
    public function sendFile(string $instanceName, string $number, string $filePath, array $options = []): array {
        $phoneValidation = ResponseHandler::validatePhoneNumber($number);
        if (!$phoneValidation['valid']) {
            return [
                'success' => false,
                'error' => 'Número de teléfono inválido: ' . implode(', ', $phoneValidation['issues']),
                'phone_validation' => $phoneValidation
            ];
        }

        if (!file_exists($filePath)) {
            return [
                'success' => false,
                'error' => 'Archivo no encontrado: ' . $filePath
            ];
        }

        // Determinar tipo de archivo
        $fileInfo = pathinfo($filePath);
        $mimeType = mime_content_type($filePath);
        $mediaType = $this->getMediaTypeFromMime($mimeType);

        $data = [
            'number' => $phoneValidation['formatted'],
            'caption' => $options['caption'] ?? '',
            'delay' => $options['delay'] ?? 1200,
            'quoted' => $options['quoted'] ?? null,
            'mentions' => $options['mentions'] ?? null
        ];

        // Usar cURL para subir archivo
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->client->getBaseUrl() . "/message/sendMedia/{$instanceName}",
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $this->client->getApiKey(),
                'Content-Type: multipart/form-data'
            ],
            CURLOPT_POSTFIELDS => array_merge($data, [
                'media' => new \CURLFile($filePath, $mimeType, $fileInfo['basename'])
            ])
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        return json_decode($response, true) ?? ['success' => false, 'error' => 'Respuesta inválida'];
    }

    /**
     * Enviar mensaje de audio
     */
    public function sendAudio(string $instanceName, string $number, string $audioUrl, array $options = []): array {
        return $this->sendMedia($instanceName, $number, $audioUrl, 'audio', $options);
    }

    /**
     * Enviar mensaje de video
     */
    public function sendVideo(string $instanceName, string $number, string $videoUrl, array $options = []): array {
        return $this->sendMedia($instanceName, $number, $videoUrl, 'video', $options);
    }

    /**
     * Enviar documento
     */
    public function sendDocument(string $instanceName, string $number, string $documentUrl, array $options = []): array {
        return $this->sendMedia($instanceName, $number, $documentUrl, 'document', $options);
    }

    /**
     * Enviar ubicación
     */
    public function sendLocation(string $instanceName, string $number, float $latitude, float $longitude, array $options = []): array {
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
            'latitude' => $latitude,
            'longitude' => $longitude,
            'name' => $options['name'] ?? '',
            'address' => $options['address'] ?? '',
            'delay' => $options['delay'] ?? 1200
        ];

        return $this->client->post("/message/sendLocation/{$instanceName}", $data);
    }

    /**
     * Enviar contacto
     */
    public function sendContact(string $instanceName, string $number, array $contact, array $options = []): array {
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
            'contact' => $contact,
            'delay' => $options['delay'] ?? 1200
        ];

        return $this->client->post("/message/sendContact/{$instanceName}", $data);
    }

    /**
     * Enviar reacción a mensaje
     */
    public function sendReaction(string $instanceName, string $messageId, string $reaction): array {
        $data = [
            'key' => ['id' => $messageId],
            'reaction' => $reaction
        ];

        return $this->client->post("/message/sendReaction/{$instanceName}", $data);
    }

    /**
     * Marcar mensaje como leído
     */
    public function markAsRead(string $instanceName, string $messageId): array {
        $data = [
            'readMessages' => [
                ['id' => $messageId]
            ]
        ];

        return $this->client->post("/chat/markMessageAsRead/{$instanceName}", $data);
    }

    /**
     * Eliminar mensaje
     */
    public function deleteMessage(string $instanceName, string $messageId): array {
        $data = [
            'key' => ['id' => $messageId]
        ];

        return $this->client->delete("/message/deleteMessage/{$instanceName}", $data);
    }

    /**
     * Obtener información de mensaje
     */
    public function getMessageInfo(string $instanceName, string $messageId): array {
        return $this->client->get("/message/{$instanceName}/{$messageId}");
    }

    /**
     * Buscar mensajes
     */
    public function searchMessages(string $instanceName, array $filters = []): array {
        $queryParams = http_build_query($filters);
        return $this->client->get("/message/search/{$instanceName}?" . $queryParams);
    }

    /**
     * Obtener historial de mensajes
     */
    public function getMessages(string $instanceName, string $number, array $options = []): array {
        $phoneValidation = ResponseHandler::validatePhoneNumber($number);
        if (!$phoneValidation['valid']) {
            return [
                'success' => false,
                'error' => 'Número de teléfono inválido: ' . implode(', ', $phoneValidation['issues']),
                'phone_validation' => $phoneValidation
            ];
        }

        $params = [
            'number' => $phoneValidation['formatted'],
            'limit' => $options['limit'] ?? 50,
            'offset' => $options['offset'] ?? 0
        ];

        $queryParams = http_build_query($params);
        return $this->client->get("/chat/findMessages/{$instanceName}?" . $queryParams);
    }

    /**
     * Reenviar mensaje
     */
    public function forwardMessage(string $instanceName, string $messageId, array $numbers, array $options = []): array {
        $validatedNumbers = [];
        foreach ($numbers as $number) {
            $phoneValidation = ResponseHandler::validatePhoneNumber($number);
            if ($phoneValidation['valid']) {
                $validatedNumbers[] = $phoneValidation['formatted'];
            }
        }

        if (empty($validatedNumbers)) {
            return [
                'success' => false,
                'error' => 'No hay números válidos para reenviar'
            ];
        }

        $data = [
            'messageId' => $messageId,
            'numbers' => $validatedNumbers,
            'delay' => $options['delay'] ?? 1200
        ];

        return $this->client->post("/message/forward/{$instanceName}", $data);
    }

    /**
     * Enviar mensaje programado
     */
    public function scheduleMessage(string $instanceName, string $number, string $text, \DateTime $scheduledTime, array $options = []): array {
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
            'text' => $text,
            'scheduledTime' => $scheduledTime->format('Y-m-d H:i:s'),
            'timezone' => $options['timezone'] ?? 'America/Sao_Paulo',
            'delay' => $options['delay'] ?? 1200
        ];

        return $this->client->post("/message/schedule/{$instanceName}", $data);
    }

    /**
     * Obtener mensajes programados
     */
    public function getScheduledMessages(string $instanceName): array {
        return $this->client->get("/message/scheduled/{$instanceName}");
    }

    /**
     * Cancelar mensaje programado
     */
    public function cancelScheduledMessage(string $instanceName, string $scheduleId): array {
        return $this->client->delete("/message/scheduled/{$instanceName}/{$scheduleId}");
    }

    /**
     * Enviar mensaje con botones interactivos
     */
    public function sendButtons(string $instanceName, string $number, string $text, array $buttons, array $options = []): array {
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
            'text' => $text,
            'buttons' => $buttons,
            'footer' => $options['footer'] ?? '',
            'delay' => $options['delay'] ?? 1200
        ];

        return $this->client->post("/message/sendButtons/{$instanceName}", $data);
    }

    /**
     * Enviar lista interactiva
     */
    public function sendList(string $instanceName, string $number, string $text, array $sections, array $options = []): array {
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
            'text' => $text,
            'buttonText' => $options['buttonText'] ?? 'Ver opciones',
            'sections' => $sections,
            'footer' => $options['footer'] ?? '',
            'delay' => $options['delay'] ?? 1200
        ];

        return $this->client->post("/message/sendList/{$instanceName}", $data);
    }

    /**
     * Obtener estadísticas de mensajes
     */
    public function getMessageStats(string $instanceName, array $filters = []): array {
        $queryParams = http_build_query($filters);
        return $this->client->get("/message/stats/{$instanceName}?" . $queryParams);
    }

    /**
     * Validar formato de mensaje
     */
    public function validateMessageFormat(array $messageData): array {
        $errors = [];

        // Validar texto
        if (isset($messageData['text']) && strlen($messageData['text']) > 4096) {
            $errors[] = 'El texto del mensaje excede los 4096 caracteres';
        }

        // Validar número
        if (isset($messageData['number'])) {
            $phoneValidation = ResponseHandler::validatePhoneNumber($messageData['number']);
            if (!$phoneValidation['valid']) {
                $errors = array_merge($errors, $phoneValidation['issues']);
            }
        }

        // Validar botones
        if (isset($messageData['buttons']) && count($messageData['buttons']) > 3) {
            $errors[] = 'Solo se permiten máximo 3 botones';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Determinar tipo de media desde MIME type
     */
    private function getMediaTypeFromMime(string $mimeType): string {
        if (strpos($mimeType, 'image/') === 0) return 'image';
        if (strpos($mimeType, 'video/') === 0) return 'video';
        if (strpos($mimeType, 'audio/') === 0) return 'audio';
        return 'document';
    }

    /**
     * Generar vista previa de mensaje
     */
    public function previewMessage(array $messageData): array {
        $preview = [
            'type' => $messageData['type'] ?? 'text',
            'length' => isset($messageData['text']) ? strlen($messageData['text']) : 0,
            'estimated_time' => $this->estimateDeliveryTime($messageData),
            'size_estimate' => $this->estimateMessageSize($messageData)
        ];

        if (isset($messageData['media'])) {
            $preview['media_info'] = [
                'url' => $messageData['media'],
                'type' => $messageData['mediatype'] ?? 'unknown'
            ];
        }

        return $preview;
    }

    /**
     * Estimar tiempo de entrega
     */
    private function estimateDeliveryTime(array $messageData): int {
        $baseTime = 1200; // 1.2 segundos base

        if (isset($messageData['media'])) {
            $baseTime += 3000; // +3 segundos para media
        }

        if (isset($messageData['text']) && strlen($messageData['text']) > 1000) {
            $baseTime += 1000; // +1 segundo para textos largos
        }

        return $baseTime;
    }

    /**
     * Estimar tamaño del mensaje
     */
    private function estimateMessageSize(array $messageData): array {
        $size = 0;

        if (isset($messageData['text'])) {
            $size += strlen($messageData['text']);
        }

        return [
            'text_bytes' => $size,
            'estimated_total_kb' => round($size / 1024, 2)
        ];
    }
}