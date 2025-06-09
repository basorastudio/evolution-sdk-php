<?php
namespace EvoApi;

use EvoApi\Utils\ResponseHandler;

/**
 * Manejo avanzado de chats en Evolution API v2
 * Incluye funcionalidades básicas y avanzadas para gestión completa de chats
 */
class Chat {
    private EvoClient $client;
    private array $chatCache = [];
    private array $chatLabels = [];

    public function __construct(EvoClient $client) {
        $this->client = $client;
    }

    /**
     * Obtiene todos los chats
     */
    public function getChats(string $instance): array {
        return $this->client->get("chat/findChats/{$instance}");
    }

    /**
     * Busca un chat específico
     */
    public function findChat(string $instance, string $number): array {
        return $this->client->get("chat/findChat/{$instance}", [
            'number' => $number
        ]);
    }

    /**
     * Archiva un chat
     */
    public function archive(string $instance, string $number): array {
        return $this->client->put("chat/archive/{$instance}", [
            'number' => $number,
            'archive' => true
        ]);
    }

    /**
     * Desarchivar un chat
     */
    public function unarchive(string $instance, string $number): array {
        return $this->client->put("chat/archive/{$instance}", [
            'number' => $number,
            'archive' => false
        ]);
    }

    /**
     * Marca un chat como leído
     */
    public function markAsRead(string $instance, string $number): array {
        return $this->client->put("chat/markAsRead/{$instance}", [
            'number' => $number
        ]);
    }

    /**
     * Silencia un chat
     */
    public function mute(string $instance, string $number, int $duration = 0): array {
        return $this->client->put("chat/mute/{$instance}", [
            'number' => $number,
            'duration' => $duration
        ]);
    }

    /**
     * Remueve el silencio de un chat
     */
    public function unmute(string $instance, string $number): array {
        return $this->client->put("chat/unmute/{$instance}", [
            'number' => $number
        ]);
    }

    /**
     * Bloquea un contacto
     */
    public function block(string $instance, string $number): array {
        return $this->client->put("chat/block/{$instance}", [
            'number' => $number
        ]);
    }

    /**
     * Desbloquea un contacto
     */
    public function unblock(string $instance, string $number): array {
        return $this->client->put("chat/unblock/{$instance}", [
            'number' => $number
        ]);
    }

    /**
     * Obtiene la presencia de un contacto
     */
    public function getPresence(string $instance, string $number): array {
        return $this->client->get("chat/presence/{$instance}", [
            'number' => $number
        ]);
    }

    /**
     * Establece la presencia de la instancia
     */
    public function setPresence(string $instance, string $presence = 'available'): array {
        return $this->client->put("chat/presence/{$instance}", [
            'presence' => $presence
        ]);
    }

    /**
     * Obtiene información del perfil de un contacto
     */
    public function getProfile(string $instance, string $number): array {
        return $this->client->get("chat/profile/{$instance}", [
            'number' => $number
        ]);
    }

    /**
     * Obtiene el estado del contacto (último visto)
     */
    public function getStatus(string $instance, string $number): array {
        return $this->client->get("chat/status/{$instance}", [
            'number' => $number
        ]);
    }

    /**
     * Actualiza el estado de la instancia
     */
    public function updateStatus(string $instance, string $status): array {
        return $this->client->put("chat/updateStatus/{$instance}", [
            'status' => $status
        ]);
    }

    /**
     * Obtiene los chats archivados
     */
    public function getArchivedChats(string $instance): array {
        return $this->client->get("chat/findChats/{$instance}", [
            'archived' => true
        ]);
    }

    /**
     * Busca mensajes en un chat con filtros avanzados
     */
    public function searchMessages(string $instance, string $number, string $query, array $options = []): array {
        $data = array_merge([
            'number' => $number,
            'query' => $query,
            'limit' => $options['limit'] ?? 50,
            'offset' => $options['offset'] ?? 0,
            'date_from' => $options['date_from'] ?? null,
            'date_to' => $options['date_to'] ?? null,
            'message_type' => $options['message_type'] ?? null, // text, image, video, audio, document
            'sender_only' => $options['sender_only'] ?? false
        ], $options);

        $response = $this->client->get("chat/searchMessages/{$instance}", $data);
        
        // Procesar y enriquecer resultados
        if (ResponseHandler::isSuccess($response)) {
            $messages = ResponseHandler::getData($response);
            $enrichedMessages = $this->enrichMessages($messages, $options);
            
            return ResponseHandler::success([
                'messages' => $enrichedMessages,
                'search_query' => $query,
                'filters_applied' => $data,
                'total_found' => count($enrichedMessages)
            ]);
        }

        return $response;
    }

    /**
     * Obtiene el historial de mensajes con paginación y filtros
     */
    public function getMessages(string $instance, string $number, array $options = []): array {
        $data = array_merge([
            'number' => $number,
            'limit' => $options['limit'] ?? 50,
            'offset' => $options['offset'] ?? 0,
            'before_message_id' => $options['before_message_id'] ?? null,
            'after_message_id' => $options['after_message_id'] ?? null
        ], $options);

        $response = $this->client->get("chat/messages/{$instance}", $data);
        
        if (ResponseHandler::isSuccess($response)) {
            $messages = ResponseHandler::getData($response);
            return ResponseHandler::success([
                'messages' => $messages,
                'pagination' => [
                    'limit' => $data['limit'],
                    'offset' => $data['offset'],
                    'has_more' => count($messages) === $data['limit']
                ],
                'chat_number' => $number
            ]);
        }

        return $response;
    }

    /**
     * Limpia el historial de un chat con opciones
     */
    public function clearHistory(string $instance, string $number, array $options = []): array {
        $data = array_merge([
            'number' => $number,
            'clear_media' => $options['clear_media'] ?? true,
            'keep_starred' => $options['keep_starred'] ?? false,
            'date_before' => $options['date_before'] ?? null
        ], $options);

        return $this->client->delete("chat/clear/{$instance}", $data);
    }

    /**
     * Exporta un chat a diferentes formatos
     */
    public function exportChat(string $instance, string $number, array $options = []): array {
        $format = $options['format'] ?? 'pdf';
        $includeMedia = $options['include_media'] ?? false;
        $dateRange = $options['date_range'] ?? null;

        $data = [
            'number' => $number,
            'format' => $format, // pdf, json, csv, txt
            'include_media' => $includeMedia,
            'include_metadata' => $options['include_metadata'] ?? true,
            'date_from' => $dateRange['from'] ?? null,
            'date_to' => $dateRange['to'] ?? null,
            'max_messages' => $options['max_messages'] ?? 1000
        ];

        $response = $this->client->post("chat/export/{$instance}", $data);
        
        if (ResponseHandler::isSuccess($response)) {
            $exportData = ResponseHandler::getData($response);
            return ResponseHandler::success(array_merge($exportData, [
                'export_options' => $data,
                'generated_at' => date('c')
            ]));
        }

        return $response;
    }

    /**
     * Obtiene estadísticas detalladas de un chat
     */
    public function getStatistics(string $instance, string $number, array $options = []): array {
        $response = $this->client->get("chat/statistics/{$instance}", [
            'number' => $number
        ]);

        if (ResponseHandler::isSuccess($response)) {
            $basicStats = ResponseHandler::getData($response);
            
            // Enriquecer con estadísticas adicionales
            $enhancedStats = $this->calculateEnhancedStats($instance, $number, $basicStats, $options);
            
            return ResponseHandler::success($enhancedStats);
        }

        return $response;
    }

    /**
     * Asigna etiquetas a un chat
     */
    public function assignLabels(string $instance, string $number, array $labels): array {
        $chatKey = "{$instance}:{$number}";
        
        if (!isset($this->chatLabels[$chatKey])) {
            $this->chatLabels[$chatKey] = [];
        }

        foreach ($labels as $label) {
            if (!in_array($label, $this->chatLabels[$chatKey])) {
                $this->chatLabels[$chatKey][] = $label;
            }
        }

        return ResponseHandler::success([
            'chat_number' => $number,
            'labels_assigned' => $labels,
            'total_labels' => count($this->chatLabels[$chatKey]),
            'all_labels' => $this->chatLabels[$chatKey]
        ]);
    }

    /**
     * Remueve etiquetas de un chat
     */
    public function removeLabels(string $instance, string $number, array $labels): array {
        $chatKey = "{$instance}:{$number}";
        
        if (isset($this->chatLabels[$chatKey])) {
            foreach ($labels as $label) {
                $index = array_search($label, $this->chatLabels[$chatKey]);
                if ($index !== false) {
                    unset($this->chatLabels[$chatKey][$index]);
                }
            }
            $this->chatLabels[$chatKey] = array_values($this->chatLabels[$chatKey]);
        }

        return ResponseHandler::success([
            'chat_number' => $number,
            'labels_removed' => $labels,
            'remaining_labels' => $this->chatLabels[$chatKey] ?? []
        ]);
    }

    /**
     * Busca chats por etiquetas
     */
    public function findChatsByLabels(string $instance, array $labels, string $operator = 'AND'): array {
        $matchingChats = [];

        foreach ($this->chatLabels as $chatKey => $chatLabels) {
            if (strpos($chatKey, $instance . ':') !== 0) {
                continue;
            }

            $matches = array_intersect($labels, $chatLabels);
            
            if ($operator === 'AND' && count($matches) === count($labels)) {
                $matchingChats[] = [
                    'number' => substr($chatKey, strlen($instance) + 1),
                    'matched_labels' => $matches,
                    'all_labels' => $chatLabels
                ];
            } elseif ($operator === 'OR' && count($matches) > 0) {
                $matchingChats[] = [
                    'number' => substr($chatKey, strlen($instance) + 1),
                    'matched_labels' => $matches,
                    'all_labels' => $chatLabels
                ];
            }
        }

        return ResponseHandler::success([
            'matching_chats' => $matchingChats,
            'search_labels' => $labels,
            'operator' => $operator,
            'total_found' => count($matchingChats)
        ]);
    }

    /**
     * Operaciones batch en múltiples chats
     */
    public function batchOperation(string $instance, array $chatNumbers, string $operation, array $options = []): array {
        $results = [];
        $delay = $options['delay'] ?? 0.5;

        foreach ($chatNumbers as $index => $number) {
            if ($index > 0 && $delay > 0) {
                usleep($delay * 1000000);
            }

            $result = match($operation) {
                'archive' => $this->archive($instance, $number),
                'unarchive' => $this->unarchive($instance, $number),
                'mute' => $this->mute($instance, $number, $options['mute_duration'] ?? 0),
                'unmute' => $this->unmute($instance, $number),
                'mark_read' => $this->markAsRead($instance, $number),
                'block' => $this->block($instance, $number),
                'unblock' => $this->unblock($instance, $number),
                default => ResponseHandler::error('Operación no soportada: ' . $operation)
            };

            $results[] = array_merge($result, [
                'number' => $number,
                'operation' => $operation,
                'index' => $index
            ]);
        }

        return ResponseHandler::combineResponses($results, 'batch_chat_operation');
    }

    /**
     * Analiza patrones de conversación
     */
    public function analyzeConversationPatterns(string $instance, string $number, array $options = []): array {
        $messagesResponse = $this->getMessages($instance, $number, [
            'limit' => $options['message_limit'] ?? 100
        ]);

        if (!ResponseHandler::isSuccess($messagesResponse)) {
            return $messagesResponse;
        }

        $messages = ResponseHandler::getData($messagesResponse)['messages'];
        
        $patterns = [
            'response_times' => [],
            'message_frequency' => [],
            'active_hours' => array_fill(0, 24, 0),
            'message_types' => [],
            'conversation_starters' => [],
            'common_words' => []
        ];

        $previousTimestamp = null;
        $allWords = [];

        foreach ($messages as $message) {
            $timestamp = $message['messageTimestamp'] ?? null;
            $content = $message['message']['conversation'] ?? '';
            $messageType = $this->detectMessageType($message);
            $hour = date('H', $timestamp);

            // Análisis de tiempos de respuesta
            if ($previousTimestamp && $timestamp) {
                $responseTime = $timestamp - $previousTimestamp;
                if ($responseTime > 0 && $responseTime < 3600) { // Menos de 1 hora
                    $patterns['response_times'][] = $responseTime;
                }
            }

            // Horas activas
            if ($hour !== false) {
                $patterns['active_hours'][$hour]++;
            }

            // Tipos de mensaje
            $patterns['message_types'][$messageType] = ($patterns['message_types'][$messageType] ?? 0) + 1;

            // Palabras comunes
            if (!empty($content)) {
                $words = str_word_count(strtolower($content), 1, 'áéíóúñ');
                $allWords = array_merge($allWords, $words);
            }

            $previousTimestamp = $timestamp;
        }

        // Calcular estadísticas finales
        if (!empty($patterns['response_times'])) {
            $patterns['avg_response_time'] = array_sum($patterns['response_times']) / count($patterns['response_times']);
            $patterns['median_response_time'] = $this->calculateMedian($patterns['response_times']);
        }

        // Palabras más comunes
        $wordCounts = array_count_values($allWords);
        arsort($wordCounts);
        $patterns['common_words'] = array_slice($wordCounts, 0, 20, true);

        // Hora más activa
        $patterns['most_active_hour'] = array_search(max($patterns['active_hours']), $patterns['active_hours']);

        return ResponseHandler::success([
            'patterns' => $patterns,
            'analysis_summary' => [
                'total_messages_analyzed' => count($messages),
                'conversation_span_hours' => $timestamp && isset($messages[0]['messageTimestamp']) ? 
                    round(($timestamp - $messages[0]['messageTimestamp']) / 3600, 2) : 0,
                'most_active_hour' => $patterns['most_active_hour'] . ':00',
                'primary_message_type' => array_search(max($patterns['message_types']), $patterns['message_types'])
            ],
            'analyzed_at' => date('c')
        ]);
    }

    /**
     * Configura notificaciones personalizadas para un chat
     */
    public function configureNotifications(string $instance, string $number, array $settings): array {
        $defaultSettings = [
            'enabled' => true,
            'sound' => 'default',
            'vibration' => true,
            'preview' => true,
            'keywords' => [],
            'quiet_hours' => null,
            'priority' => 'normal'
        ];

        $chatSettings = array_merge($defaultSettings, $settings);
        
        // Validar configuración
        $validation = $this->validateNotificationSettings($chatSettings);
        if (!$validation['valid']) {
            return ResponseHandler::error('Configuración inválida: ' . implode(', ', $validation['errors']));
        }

        // Guardar configuración (en un sistema real, esto iría a base de datos)
        $configKey = "{$instance}:{$number}:notifications";
        $this->chatCache[$configKey] = $chatSettings;

        return ResponseHandler::success([
            'chat_number' => $number,
            'notification_settings' => $chatSettings,
            'configured_at' => date('c')
        ]);
    }

    /**
     * Obtiene métricas de engagement del chat
     */
    public function getEngagementMetrics(string $instance, string $number, array $options = []): array {
        $days = $options['days'] ?? 7;
        $messagesResponse = $this->getMessages($instance, $number, [
            'limit' => $options['message_limit'] ?? 200
        ]);

        if (!ResponseHandler::isSuccess($messagesResponse)) {
            return $messagesResponse;
        }

        $messages = ResponseHandler::getData($messagesResponse)['messages'];
        $cutoffTime = time() - ($days * 24 * 3600);
        
        $metrics = [
            'total_messages' => 0,
            'messages_sent' => 0,
            'messages_received' => 0,
            'avg_message_length' => 0,
            'response_rate' => 0,
            'conversation_threads' => 0,
            'media_messages' => 0,
            'engagement_score' => 0
        ];

        $messageLengths = [];
        $dailyActivity = [];

        foreach ($messages as $message) {
            $timestamp = $message['messageTimestamp'] ?? 0;
            if ($timestamp < $cutoffTime) continue;

            $metrics['total_messages']++;
            $content = $message['message']['conversation'] ?? '';
            $day = date('Y-m-d', $timestamp);
            
            // Longitud de mensajes
            if (!empty($content)) {
                $messageLengths[] = strlen($content);
            }

            // Actividad diaria
            $dailyActivity[$day] = ($dailyActivity[$day] ?? 0) + 1;

            // Detectar tipo de mensaje
            if ($this->detectMessageType($message) !== 'text') {
                $metrics['media_messages']++;
            }
        }

        // Calcular métricas finales
        if (!empty($messageLengths)) {
            $metrics['avg_message_length'] = array_sum($messageLengths) / count($messageLengths);
        }

        $metrics['daily_average'] = count($dailyActivity) > 0 ? 
            array_sum($dailyActivity) / count($dailyActivity) : 0;

        // Score de engagement (algoritmo simple)
        $metrics['engagement_score'] = min(100, (
            ($metrics['daily_average'] * 10) +
            (min($metrics['avg_message_length'] / 10, 10)) +
            ($metrics['media_messages'] / max($metrics['total_messages'], 1) * 20)
        ));

        return ResponseHandler::success([
            'metrics' => $metrics,
            'daily_activity' => $dailyActivity,
            'period_days' => $days,
            'calculated_at' => date('c')
        ]);
    }

    /**
     * Crea un resumen automático de conversación
     */
    public function generateConversationSummary(string $instance, string $number, array $options = []): array {
        $messagesResponse = $this->getMessages($instance, $number, [
            'limit' => $options['message_limit'] ?? 50
        ]);

        if (!ResponseHandler::isSuccess($messagesResponse)) {
            return $messagesResponse;
        }

        $messages = ResponseHandler::getData($messagesResponse)['messages'];
        
        $summary = [
            'total_messages' => count($messages),
            'date_range' => [],
            'participants' => [],
            'key_topics' => [],
            'summary_text' => '',
            'action_items' => [],
            'sentiment_overview' => 'neutral'
        ];

        if (empty($messages)) {
            return ResponseHandler::success($summary);
        }

        // Rango de fechas
        $timestamps = array_filter(array_column($messages, 'messageTimestamp'));
        if (!empty($timestamps)) {
            $summary['date_range'] = [
                'start' => date('Y-m-d H:i:s', min($timestamps)),
                'end' => date('Y-m-d H:i:s', max($timestamps))
            ];
        }

        // Análisis de contenido
        $allContent = [];
        foreach ($messages as $message) {
            $content = $message['message']['conversation'] ?? '';
            if (!empty($content)) {
                $allContent[] = $content;
            }
        }

        // Generar resumen básico
        $summary['summary_text'] = $this->generateBasicSummary($allContent);
        $summary['key_topics'] = $this->extractKeyTopics($allContent);
        $summary['action_items'] = $this->extractActionItems($allContent);

        return ResponseHandler::success([
            'summary' => $summary,
            'generated_at' => date('c'),
            'options_used' => $options
        ]);
    }

    // Métodos auxiliares privados

    private function enrichMessages(array $messages, array $options): array {
        $enriched = [];
        
        foreach ($messages as $message) {
            $enrichedMessage = $message;
            
            // Agregar metadatos adicionales
            $enrichedMessage['message_type'] = $this->detectMessageType($message);
            $enrichedMessage['content_length'] = strlen($message['message']['conversation'] ?? '');
            $enrichedMessage['has_media'] = $this->hasMedia($message);
            
            if ($options['include_sentiment'] ?? false) {
                $enrichedMessage['sentiment'] = $this->analyzeMessageSentiment($message);
            }
            
            $enriched[] = $enrichedMessage;
        }
        
        return $enriched;
    }

    private function calculateEnhancedStats(string $instance, string $number, array $basicStats, array $options): array {
        $enhanced = $basicStats;
        
        // Agregar estadísticas calculadas localmente
        $enhanced['labels'] = $this->chatLabels["{$instance}:{$number}"] ?? [];
        $enhanced['label_count'] = count($enhanced['labels']);
        
        // Métricas de tiempo
        $enhanced['last_activity'] = $this->getLastActivityTime($instance, $number);
        $enhanced['activity_status'] = $this->determineActivityStatus($enhanced['last_activity']);
        
        return $enhanced;
    }

    private function detectMessageType(array $message): string {
        $messageContent = $message['message'] ?? [];
        
        if (isset($messageContent['imageMessage'])) return 'image';
        if (isset($messageContent['videoMessage'])) return 'video';
        if (isset($messageContent['audioMessage'])) return 'audio';
        if (isset($messageContent['documentMessage'])) return 'document';
        if (isset($messageContent['locationMessage'])) return 'location';
        if (isset($messageContent['contactMessage'])) return 'contact';
        
        return 'text';
    }

    private function hasMedia(array $message): bool {
        return $this->detectMessageType($message) !== 'text';
    }

    private function analyzeMessageSentiment(array $message): string {
        $content = $message['message']['conversation'] ?? '';
        if (empty($content)) return 'neutral';
        
        // Análisis básico de sentimiento
        $positiveWords = ['bien', 'bueno', 'excelente', 'gracias', 'perfecto'];
        $negativeWords = ['mal', 'malo', 'problema', 'error', 'molesto'];
        
        $content = strtolower($content);
        $positiveCount = 0;
        $negativeCount = 0;
        
        foreach ($positiveWords as $word) {
            $positiveCount += substr_count($content, $word);
        }
        
        foreach ($negativeWords as $word) {
            $negativeCount += substr_count($content, $word);
        }
        
        if ($positiveCount > $negativeCount) return 'positive';
        if ($negativeCount > $positiveCount) return 'negative';
        
        return 'neutral';
    }

    private function calculateMedian(array $values): float {
        sort($values);
        $count = count($values);
        
        if ($count % 2 === 0) {
            return ($values[$count / 2 - 1] + $values[$count / 2]) / 2;
        } else {
            return $values[floor($count / 2)];
        }
    }

    private function validateNotificationSettings(array $settings): array {
        $errors = [];
        
        if (!is_bool($settings['enabled'])) {
            $errors[] = 'enabled debe ser un valor booleano';
        }
        
        $validSounds = ['default', 'silent', 'custom'];
        if (!in_array($settings['sound'], $validSounds)) {
            $errors[] = 'sound debe ser uno de: ' . implode(', ', $validSounds);
        }
        
        $validPriorities = ['low', 'normal', 'high'];
        if (!in_array($settings['priority'], $validPriorities)) {
            $errors[] = 'priority debe ser uno de: ' . implode(', ', $validPriorities);
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    private function getLastActivityTime(string $instance, string $number): ?int {
        // En un sistema real, esto consultaría la base de datos
        return time() - 3600; // Simulado: hace 1 hora
    }

    private function determineActivityStatus(int $lastActivity): string {
        $hoursSince = (time() - $lastActivity) / 3600;
        
        if ($hoursSince < 1) return 'active';
        if ($hoursSince < 24) return 'recent';
        if ($hoursSince < 168) return 'weekly';
        
        return 'inactive';
    }

    private function generateBasicSummary(array $contents): string {
        if (empty($contents)) return 'No hay mensajes para resumir.';
        
        $totalMessages = count($contents);
        $avgLength = array_sum(array_map('strlen', $contents)) / $totalMessages;
        
        return "Conversación con {$totalMessages} mensajes. Longitud promedio: " . round($avgLength) . " caracteres.";
    }

    private function extractKeyTopics(array $contents): array {
        $allWords = [];
        
        foreach ($contents as $content) {
            $words = str_word_count(strtolower($content), 1, 'áéíóúñ');
            $allWords = array_merge($allWords, $words);
        }
        
        $wordCounts = array_count_values($allWords);
        arsort($wordCounts);
        
        return array_slice(array_keys($wordCounts), 0, 5);
    }

    private function extractActionItems(array $contents): array {
        $actionWords = ['hacer', 'enviar', 'llamar', 'revisar', 'confirmar', 'programar'];
        $actionItems = [];
        
        foreach ($contents as $content) {
            foreach ($actionWords as $word) {
                if (stripos($content, $word) !== false) {
                    $actionItems[] = substr($content, 0, 100) . '...';
                    break;
                }
            }
        }
        
        return array_unique($actionItems);
    }
}