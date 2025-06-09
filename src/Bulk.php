<?php
namespace EvoApi;

use EvoApi\Utils\ResponseHandler;

/**
 * Manejo de operaciones masivas/bulk en Evolution API v2
 * Permite enviar múltiples mensajes, crear múltiples instancias, etc.
 */
class Bulk {
    private EvoClient $client;
    private Message $message;
    private Instance $instance;
    private Group $group;

    public function __construct(EvoClient $client) {
        $this->client = $client;
        $this->message = new Message($client);
        $this->instance = new Instance($client);
        $this->group = new Group($client);
    }

    /**
     * Envía múltiples mensajes de texto
     */
    public function sendTextMessages(string $instance, array $messages, array $options = []): array {
        $responses = [];
        $delay = $options['delay'] ?? 1; // Delay entre mensajes en segundos
        $maxRetries = $options['max_retries'] ?? 0;
        
        foreach ($messages as $index => $messageData) {
            if ($index > 0 && $delay > 0) {
                sleep($delay);
            }

            $operation = function() use ($instance, $messageData) {
                return $this->message->sendText(
                    $instance,
                    $messageData['number'],
                    $messageData['text'],
                    $messageData['options'] ?? []
                );
            };

            if ($maxRetries > 0) {
                $response = ResponseHandler::retryOperation($operation, $maxRetries);
            } else {
                $response = $operation();
            }

            $responses[] = array_merge($response, [
                'original_data' => $messageData,
                'index' => $index
            ]);
        }

        return ResponseHandler::combineResponses($responses, 'bulk_text_messages');
    }

    /**
     * Envía múltiples archivos multimedia
     */
    public function sendMediaMessages(string $instance, array $messages, array $options = []): array {
        $responses = [];
        $delay = $options['delay'] ?? 2; // Delay mayor para archivos
        $maxRetries = $options['max_retries'] ?? 0;
        
        foreach ($messages as $index => $messageData) {
            if ($index > 0 && $delay > 0) {
                sleep($delay);
            }

            $operation = function() use ($instance, $messageData) {
                return $this->message->sendMedia(
                    $instance,
                    $messageData['number'],
                    $messageData['media'],
                    $messageData['mediaType'],
                    $messageData['options'] ?? []
                );
            };

            if ($maxRetries > 0) {
                $response = ResponseHandler::retryOperation($operation, $maxRetries);
            } else {
                $response = $operation();
            }

            $responses[] = array_merge($response, [
                'original_data' => $messageData,
                'index' => $index
            ]);
        }

        return ResponseHandler::combineResponses($responses, 'bulk_media_messages');
    }

    /**
     * Crea múltiples instancias
     */
    public function createInstances(array $instancesData, array $options = []): array {
        $responses = [];
        $delay = $options['delay'] ?? 3; // Delay entre creación de instancias
        $maxRetries = $options['max_retries'] ?? 1;
        
        foreach ($instancesData as $index => $instanceData) {
            if ($index > 0 && $delay > 0) {
                sleep($delay);
            }

            $operation = function() use ($instanceData) {
                return $this->instance->create(
                    $instanceData['instanceName'],
                    $instanceData['token'] ?? '',
                    $instanceData['webhook'] ?? '',
                    $instanceData['options'] ?? []
                );
            };

            if ($maxRetries > 0) {
                $response = ResponseHandler::retryOperation($operation, $maxRetries);
            } else {
                $response = $operation();
            }

            $responses[] = array_merge($response, [
                'original_data' => $instanceData,
                'index' => $index
            ]);
        }

        return ResponseHandler::combineResponses($responses, 'bulk_create_instances');
    }

    /**
     * Agrega participantes a múltiples grupos
     */
    public function addParticipantsToGroups(string $instance, array $groupOperations, array $options = []): array {
        $responses = [];
        $delay = $options['delay'] ?? 1;
        $maxRetries = $options['max_retries'] ?? 0;
        
        foreach ($groupOperations as $index => $operation) {
            if ($index > 0 && $delay > 0) {
                sleep($delay);
            }

            $operationFunc = function() use ($instance, $operation) {
                return $this->group->addParticipant(
                    $instance,
                    $operation['groupId'],
                    $operation['participants']
                );
            };

            if ($maxRetries > 0) {
                $response = ResponseHandler::retryOperation($operationFunc, $maxRetries);
            } else {
                $response = $operationFunc();
            }

            $responses[] = array_merge($response, [
                'original_data' => $operation,
                'index' => $index
            ]);
        }

        return ResponseHandler::combineResponses($responses, 'bulk_add_group_participants');
    }

    /**
     * Valida múltiples números de teléfono
     */
    public function validatePhoneNumbers(array $numbers): array {
        $results = [];
        
        foreach ($numbers as $index => $number) {
            $validation = ResponseHandler::validatePhoneNumber($number);
            $results[] = array_merge($validation, ['index' => $index]);
        }

        $summary = [
            'total' => count($numbers),
            'valid' => count(array_filter($results, fn($r) => $r['valid'])),
            'invalid' => count(array_filter($results, fn($r) => !$r['valid']))
        ];

        return [
            'results' => $results,
            'summary' => $summary,
            'valid_numbers' => array_values(array_filter(array_map(fn($r) => $r['valid'] ? $r['formatted'] : null, $results))),
            'invalid_numbers' => array_values(array_filter(array_map(fn($r) => !$r['valid'] ? $r['original'] : null, $results)))
        ];
    }

    /**
     * Procesa una lista de contactos desde CSV
     */
    public function processContactsFromCSV(string $filePath, array $options = []): array {
        if (!file_exists($filePath)) {
            return [
                'success' => false,
                'error' => 'Archivo CSV no encontrado: ' . $filePath
            ];
        }

        $delimiter = $options['delimiter'] ?? ',';
        $enclosure = $options['enclosure'] ?? '"';
        $escape = $options['escape'] ?? '\\';
        $skipHeader = $options['skip_header'] ?? true;
        
        $contacts = [];
        $errors = [];
        $lineNumber = 0;

        if (($handle = fopen($filePath, 'r')) !== false) {
            while (($data = fgetcsv($handle, 1000, $delimiter, $enclosure, $escape)) !== false) {
                $lineNumber++;
                
                // Saltar header si está configurado
                if ($skipHeader && $lineNumber === 1) {
                    continue;
                }

                try {
                    // Estructura esperada: nombre, telefono, mensaje_personalizado (opcional)
                    $contact = [
                        'name' => $data[0] ?? '',
                        'phone' => $data[1] ?? '',
                        'custom_message' => $data[2] ?? '',
                        'line_number' => $lineNumber
                    ];

                    // Validar datos básicos
                    if (empty($contact['name']) || empty($contact['phone'])) {
                        $errors[] = [
                            'line' => $lineNumber,
                            'error' => 'Nombre o teléfono vacío',
                            'data' => $data
                        ];
                        continue;
                    }

                    // Validar número de teléfono
                    $phoneValidation = ResponseHandler::validatePhoneNumber($contact['phone']);
                    if (!$phoneValidation['valid']) {
                        $errors[] = [
                            'line' => $lineNumber,
                            'error' => 'Número de teléfono inválido: ' . implode(', ', $phoneValidation['issues']),
                            'data' => $data
                        ];
                        continue;
                    }

                    $contact['phone'] = $phoneValidation['formatted'];
                    $contacts[] = $contact;

                } catch (\Exception $e) {
                    $errors[] = [
                        'line' => $lineNumber,
                        'error' => 'Error procesando línea: ' . $e->getMessage(),
                        'data' => $data
                    ];
                }
            }
            fclose($handle);
        }

        return [
            'success' => true,
            'total_lines' => $lineNumber,
            'processed_contacts' => count($contacts),
            'errors_count' => count($errors),
            'contacts' => $contacts,
            'errors' => $errors,
            'summary' => [
                'total_lines' => $lineNumber,
                'valid_contacts' => count($contacts),
                'invalid_lines' => count($errors),
                'success_rate' => $lineNumber > 0 ? round((count($contacts) / $lineNumber) * 100, 2) : 0
            ]
        ];
    }

    /**
     * Envía mensajes personalizados desde CSV
     */
    public function sendMessagesFromCSV(string $instance, string $csvPath, string $messageTemplate, array $options = []): array {
        // Procesar CSV
        $csvResult = $this->processContactsFromCSV($csvPath, $options);
        
        if (!$csvResult['success']) {
            return $csvResult;
        }

        $contacts = $csvResult['contacts'];
        $messages = [];

        // Preparar mensajes personalizados
        foreach ($contacts as $contact) {
            $personalizedMessage = $messageTemplate;
            
            // Reemplazar placeholders
            $personalizedMessage = str_replace('{name}', $contact['name'], $personalizedMessage);
            $personalizedMessage = str_replace('{phone}', $contact['phone'], $personalizedMessage);
            
            // Si hay mensaje personalizado, usarlo
            if (!empty($contact['custom_message'])) {
                $personalizedMessage = $contact['custom_message'];
                $personalizedMessage = str_replace('{name}', $contact['name'], $personalizedMessage);
            }

            $messages[] = [
                'number' => $contact['phone'],
                'text' => $personalizedMessage,
                'options' => $options['message_options'] ?? []
            ];
        }

        // Enviar mensajes
        $sendResult = $this->sendTextMessages($instance, $messages, $options);

        return [
            'csv_processing' => $csvResult,
            'message_sending' => $sendResult,
            'overall_summary' => [
                'contacts_processed' => count($contacts),
                'messages_sent' => $sendResult['successful'] ?? 0,
                'messages_failed' => $sendResult['failed'] ?? 0,
                'total_success_rate' => $sendResult['success_rate'] ?? 0
            ]
        ];
    }

    /**
     * Programa múltiples mensajes para envío posterior
     */
    public function scheduleMultipleMessages(string $instance, array $scheduledMessages, array $options = []): array {
        $responses = [];
        $delay = $options['delay'] ?? 0.5;
        
        foreach ($scheduledMessages as $index => $messageData) {
            if ($index > 0 && $delay > 0) {
                usleep($delay * 1000000); // Convertir a microsegundos
            }

            $response = $this->message->scheduleMessage(
                $instance,
                $messageData['number'],
                $messageData['text'],
                $messageData['dateTime'],
                $messageData['options'] ?? []
            );

            $responses[] = array_merge($response, [
                'original_data' => $messageData,
                'index' => $index
            ]);
        }

        return ResponseHandler::combineResponses($responses, 'bulk_schedule_messages');
    }

    /**
     * Exporta resultados a CSV
     */
    public function exportResultsToCSV(array $results, string $filePath, array $options = []): array {
        $delimiter = $options['delimiter'] ?? ',';
        $enclosure = $options['enclosure'] ?? '"';
        $includeHeaders = $options['include_headers'] ?? true;

        try {
            $handle = fopen($filePath, 'w');
            
            if (!$handle) {
                return [
                    'success' => false,
                    'error' => 'No se pudo crear el archivo: ' . $filePath
                ];
            }

            $rowCount = 0;

            // Escribir headers si está habilitado
            if ($includeHeaders && !empty($results)) {
                $headers = array_keys($results[0]);
                fputcsv($handle, $headers, $delimiter, $enclosure);
                $rowCount++;
            }

            // Escribir datos
            foreach ($results as $result) {
                fputcsv($handle, array_values($result), $delimiter, $enclosure);
                $rowCount++;
            }

            fclose($handle);

            return [
                'success' => true,
                'file_path' => $filePath,
                'rows_written' => $rowCount,
                'file_size' => filesize($filePath)
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Error exportando a CSV: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Monitorea el progreso de operaciones masivas
     */
    public function monitorBulkOperation(array $operationResults): array {
        $monitoring = [
            'timestamp' => date('Y-m-d H:i:s'),
            'operation_type' => $operationResults['operation'] ?? 'unknown',
            'total_operations' => $operationResults['total_requests'] ?? 0,
            'completed' => $operationResults['successful'] ?? 0,
            'failed' => $operationResults['failed'] ?? 0,
            'success_rate' => $operationResults['success_rate'] ?? 0,
            'status' => 'completed'
        ];

        // Clasificar tipos de errores
        if (!empty($operationResults['errors'])) {
            $errorTypes = [];
            foreach ($operationResults['errors'] as $error) {
                $statusCode = $error['status_code'] ?? 'unknown';
                $errorTypes[$statusCode] = ($errorTypes[$statusCode] ?? 0) + 1;
            }
            $monitoring['error_breakdown'] = $errorTypes;
        }

        // Recomendaciones basadas en resultados
        $recommendations = [];
        
        if ($monitoring['success_rate'] < 50) {
            $recommendations[] = 'Tasa de éxito muy baja. Revisar configuración y conectividad.';
        } elseif ($monitoring['success_rate'] < 80) {
            $recommendations[] = 'Tasa de éxito moderada. Considerar implementar retry logic.';
        }

        if (isset($monitoring['error_breakdown'][429])) {
            $recommendations[] = 'Rate limiting detectado. Aumentar delay entre operaciones.';
        }

        if (isset($monitoring['error_breakdown'][401])) {
            $recommendations[] = 'Errores de autenticación. Verificar API Key.';
        }

        $monitoring['recommendations'] = $recommendations;

        return $monitoring;
    }

    /**
     * Genera reporte de rendimiento de operaciones bulk
     */
    public function generatePerformanceReport(array $operationResults, array $timings = []): array {
        $report = [
            'report_timestamp' => date('Y-m-d H:i:s'),
            'operation_summary' => $operationResults['summary'] ?? [],
            'performance_metrics' => ResponseHandler::calculatePerformanceStats(
                $operationResults['results'] ?? [], 
                $timings
            ),
            'error_analysis' => ResponseHandler::generateErrorReport(
                $operationResults['results'] ?? []
            ),
            'monitoring_data' => $this->monitorBulkOperation($operationResults)
        ];

        // Métricas adicionales específicas para operaciones bulk
        $report['bulk_metrics'] = [
            'average_operations_per_minute' => 0,
            'estimated_total_time' => 0,
            'throughput_score' => 'N/A'
        ];

        if (!empty($timings)) {
            $totalTime = array_sum($timings);
            $avgTime = $totalTime / count($timings);
            $operationsPerMinute = $avgTime > 0 ? round(60 / $avgTime, 2) : 0;
            
            $report['bulk_metrics'] = [
                'average_operations_per_minute' => $operationsPerMinute,
                'estimated_total_time' => round($totalTime, 2) . ' seconds',
                'throughput_score' => $operationsPerMinute > 30 ? 'High' : ($operationsPerMinute > 10 ? 'Medium' : 'Low')
            ];
        }

        return $report;
    }
}