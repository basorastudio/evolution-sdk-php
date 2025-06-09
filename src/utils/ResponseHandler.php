<?php
namespace EvoApi\Utils;

/**
 * Utilidad para manejar respuestas de la API Evolution
 */
class ResponseHandler {
    
    /**
     * Verificar si una respuesta es exitosa
     */
    public static function isSuccess(array $response): bool {
        return isset($response['success']) && $response['success'] === true;
    }

    /**
     * Obtener datos de la respuesta
     */
    public static function getData(array $response): array {
        return $response['data'] ?? [];
    }

    /**
     * Obtener mensaje de error
     */
    public static function getError(array $response): string {
        return $response['error'] ?? $response['message'] ?? 'Error desconocido';
    }

    /**
     * Obtener código de estado HTTP
     */
    public static function getStatusCode(array $response): int {
        return $response['status_code'] ?? 0;
    }

    /**
     * Verificar si hay código QR en la respuesta
     */
    public static function hasQrCode(array $response): bool {
        $data = self::getData($response);
        return isset($data['qrcode']) || isset($data['base64']) || isset($response['qr_code']);
    }

    /**
     * Obtener código QR de la respuesta
     */
    public static function getQrCode(array $response): ?string {
        if (isset($response['qr_code'])) {
            return $response['qr_code'];
        }

        $data = self::getData($response);
        return $data['qrcode'] ?? $data['base64'] ?? null;
    }

    /**
     * Generar ID único para operaciones
     */
    public static function generateOperationId(string $operation, array $data = []): string {
        $timestamp = microtime(true);
        $hash = md5($operation . json_encode($data) . $timestamp);
        return substr($operation, 0, 8) . '_' . substr($hash, 0, 8) . '_' . date('His');
    }

    /**
     * Validar número de teléfono
     */
    public static function validatePhoneNumber(string $phone): array {
        $issues = [];
        $originalPhone = $phone;
        
        // Limpiar número
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        if (empty($phone)) {
            $issues[] = 'Número vacío';
        }

        // Verificar longitud mínima
        if (strlen($phone) < 8) {
            $issues[] = 'Número muy corto (mínimo 8 dígitos)';
        }

        // Verificar longitud máxima
        if (strlen($phone) > 15) {
            $issues[] = 'Número muy largo (máximo 15 dígitos)';
        }

        // Si no tiene código de país, agregar formato por defecto
        if (!str_starts_with($phone, '+')) {
            // Si no empieza con +, verificar si empieza con código de país común
            if (preg_match('/^(55|521|51|57|54|56|58|595|598|507|502|503|504|505|506|511)/', $phone)) {
                $phone = '+' . $phone;
            } else {
                // Asumir código de país por defecto (ej. Brasil +55)
                $phone = '+55' . $phone;
            }
        }

        // Formatear para WhatsApp (remover + y agregar @s.whatsapp.net si no es grupo)
        $formatted = str_replace('+', '', $phone);
        if (!str_contains($formatted, '@')) {
            $formatted .= '@s.whatsapp.net';
        }

        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'original' => $originalPhone,
            'cleaned' => $phone,
            'formatted' => $formatted
        ];
    }

    /**
     * Combinar múltiples respuestas en una sola
     */
    public static function combineResponses(array $responses, string $operation = 'batch'): array {
        $successful = 0;
        $failed = 0;
        $errors = [];
        $results = [];

        foreach ($responses as $response) {
            if (self::isSuccess($response)) {
                $successful++;
            } else {
                $failed++;
                $errors[] = self::getError($response);
            }
            $results[] = $response;
        }

        return [
            'success' => $failed === 0,
            'operation' => $operation,
            'summary' => [
                'total' => count($responses),
                'successful' => $successful,
                'failed' => $failed,
                'success_rate' => count($responses) > 0 ? round(($successful / count($responses)) * 100, 2) : 0
            ],
            'errors' => $errors,
            'results' => $results,
            'timestamp' => date('c')
        ];
    }

    /**
     * Manejar errores comunes de la API
     */
    public static function handleCommonErrors(array $response): array {
        $statusCode = self::getStatusCode($response);
        $error = self::getError($response);

        // Mapear códigos de error comunes a mensajes amigables
        $errorMappings = [
            400 => 'Solicitud inválida: Verifique los datos enviados',
            401 => 'No autorizado: Verifique su API Key',
            403 => 'Acceso prohibido: Sin permisos para esta operación',
            404 => 'Recurso no encontrado: La instancia o endpoint no existe',
            429 => 'Demasiadas solicitudes: Espere antes de intentar nuevamente',
            500 => 'Error interno del servidor: Intente más tarde',
            502 => 'Gateway inválido: Problema de conectividad',
            503 => 'Servicio no disponible: El servidor está temporalmente fuera de servicio'
        ];

        if (isset($errorMappings[$statusCode])) {
            $response['friendly_error'] = $errorMappings[$statusCode];
        }

        // Agregar sugerencias de solución
        $response['suggestions'] = self::getErrorSuggestions($statusCode, $error);

        return $response;
    }

    /**
     * Obtener sugerencias para solucionar errores
     */
    public static function getErrorSuggestions(int $statusCode, string $error): array {
        $suggestions = [];

        switch ($statusCode) {
            case 401:
                $suggestions[] = 'Verifique que su API Key esté configurada correctamente';
                $suggestions[] = 'Asegúrese de que la API Key no haya expirado';
                break;
            
            case 404:
                $suggestions[] = 'Verifique que el nombre de la instancia sea correcto';
                $suggestions[] = 'Asegúrese de que la instancia esté creada y activa';
                break;
            
            case 429:
                $suggestions[] = 'Implemente un sistema de reintentos con backoff exponencial';
                $suggestions[] = 'Reduzca la frecuencia de las solicitudes';
                break;
            
            case 500:
            case 502:
            case 503:
                $suggestions[] = 'Intente la operación nuevamente en unos minutos';
                $suggestions[] = 'Verifique el estado del servicio de Evolution API';
                break;
        }

        // Sugerencias basadas en el mensaje de error
        if (stripos($error, 'instance') !== false && stripos($error, 'not found') !== false) {
            $suggestions[] = 'Cree la instancia antes de usar este endpoint';
            $suggestions[] = 'Verifique la ortografía del nombre de la instancia';
        }

        if (stripos($error, 'phone') !== false || stripos($error, 'number') !== false) {
            $suggestions[] = 'Verifique el formato del número de teléfono';
            $suggestions[] = 'Incluya el código de país en el número';
        }

        return $suggestions;
    }

    /**
     * Formatear respuesta para logging
     */
    public static function formatForLog(array $response, string $level = 'INFO'): string {
        $timestamp = date('Y-m-d H:i:s');
        $success = self::isSuccess($response) ? 'SUCCESS' : 'FAILED';
        $statusCode = self::getStatusCode($response);
        $operationId = $response['operation_id'] ?? 'unknown';
        
        $logEntry = "[{$timestamp}] [{$level}] [{$success}] Operation: {$operationId}";
        
        if ($statusCode > 0) {
            $logEntry .= " | Status: {$statusCode}";
        }
        
        if (!self::isSuccess($response)) {
            $error = self::getError($response);
            $logEntry .= " | Error: {$error}";
        }
        
        return $logEntry;
    }

    /**
     * Convertir respuesta a CSV
     */
    public static function convertToCSV(array $data, array $headers = []): string {
        if (empty($data)) {
            return '';
        }

        $output = fopen('php://temp', 'r+');
        
        // Si no se proporcionan headers, usar las claves del primer elemento
        if (empty($headers) && !empty($data[0])) {
            $headers = array_keys($data[0]);
        }
        
        // Escribir headers
        if (!empty($headers)) {
            fputcsv($output, $headers);
        }
        
        // Escribir datos
        foreach ($data as $row) {
            // Aplanar arrays anidados
            $flatRow = self::flattenArray($row);
            fputcsv($output, $flatRow);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }

    /**
     * Aplanar array multidimensional
     */
    public static function flattenArray(array $array, string $prefix = ''): array {
        $result = [];
        
        foreach ($array as $key => $value) {
            $newKey = $prefix === '' ? $key : $prefix . '.' . $key;
            
            if (is_array($value)) {
                $result = array_merge($result, self::flattenArray($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }
        
        return $result;
    }

    /**
     * Validar estructura de webhook
     */
    public static function validateWebhookData(array $webhookData): array {
        $issues = [];
        
        if (!isset($webhookData['event'])) {
            $issues[] = 'Campo "event" requerido';
        }
        
        if (!isset($webhookData['instance'])) {
            $issues[] = 'Campo "instance" requerido';
        }
        
        if (!isset($webhookData['data'])) {
            $issues[] = 'Campo "data" requerido';
        }
        
        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'webhook_data' => $webhookData
        ];
    }

    /**
     * Extraer información del mensaje desde webhook
     */
    public static function extractMessageFromWebhook(array $webhookData): array {
        $data = $webhookData['data'] ?? [];
        
        return [
            'message_id' => $data['key']['id'] ?? null,
            'from' => $data['key']['remoteJid'] ?? null,
            'message_type' => $data['messageType'] ?? 'unknown',
            'text' => $data['message']['conversation'] ?? 
                     $data['message']['extendedTextMessage']['text'] ?? 
                     $data['message']['imageMessage']['caption'] ?? '',
            'timestamp' => $data['messageTimestamp'] ?? null,
            'is_from_me' => $data['key']['fromMe'] ?? false,
            'is_group' => isset($data['key']['participant']),
            'participant' => $data['key']['participant'] ?? null,
            'media_url' => $data['message']['imageMessage']['url'] ?? 
                         $data['message']['videoMessage']['url'] ?? 
                         $data['message']['audioMessage']['url'] ?? 
                         $data['message']['documentMessage']['url'] ?? null,
            'media_type' => $data['message']['imageMessage'] ? 'image' :
                          ($data['message']['videoMessage'] ? 'video' :
                          ($data['message']['audioMessage'] ? 'audio' :
                          ($data['message']['documentMessage'] ? 'document' : null))),
            'raw_data' => $data
        ];
    }

    /**
     * Generar estadísticas de rendimiento
     */
    public static function generatePerformanceStats(array $responses): array {
        if (empty($responses)) {
            return [
                'total_requests' => 0,
                'avg_response_time' => 0,
                'success_rate' => 0,
                'error_rate' => 0
            ];
        }

        $responseTimes = [];
        $successful = 0;
        $failed = 0;

        foreach ($responses as $response) {
            if (isset($response['response_time'])) {
                $responseTimes[] = $response['response_time'];
            }
            
            if (self::isSuccess($response)) {
                $successful++;
            } else {
                $failed++;
            }
        }

        $totalRequests = count($responses);
        $avgResponseTime = !empty($responseTimes) ? array_sum($responseTimes) / count($responseTimes) : 0;
        $successRate = $totalRequests > 0 ? ($successful / $totalRequests) * 100 : 0;
        $errorRate = $totalRequests > 0 ? ($failed / $totalRequests) * 100 : 0;

        return [
            'total_requests' => $totalRequests,
            'successful_requests' => $successful,
            'failed_requests' => $failed,
            'avg_response_time' => round($avgResponseTime, 3),
            'min_response_time' => !empty($responseTimes) ? min($responseTimes) : 0,
            'max_response_time' => !empty($responseTimes) ? max($responseTimes) : 0,
            'success_rate' => round($successRate, 2),
            'error_rate' => round($errorRate, 2),
            'performance_grade' => self::calculatePerformanceGrade($avgResponseTime, $successRate)
        ];
    }

    /**
     * Calcular grado de rendimiento
     */
    private static function calculatePerformanceGrade(float $avgResponseTime, float $successRate): string {
        if ($successRate >= 95 && $avgResponseTime <= 1.0) {
            return 'A+';
        } elseif ($successRate >= 90 && $avgResponseTime <= 2.0) {
            return 'A';
        } elseif ($successRate >= 85 && $avgResponseTime <= 3.0) {
            return 'B+';
        } elseif ($successRate >= 80 && $avgResponseTime <= 5.0) {
            return 'B';
        } elseif ($successRate >= 70 && $avgResponseTime <= 10.0) {
            return 'C';
        } else {
            return 'D';
        }
    }

    /**
     * Sanitizar datos sensibles para logging
     */
    public static function sanitizeForLog(array $data): array {
        $sensitiveFields = ['apikey', 'api_key', 'token', 'password', 'secret', 'authorization'];
        
        return self::sanitizeArrayRecursive($data, $sensitiveFields);
    }

    /**
     * Sanitizar array recursivamente
     */
    private static function sanitizeArrayRecursive(array $data, array $sensitiveFields): array {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::sanitizeArrayRecursive($value, $sensitiveFields);
            } elseif (is_string($value) && in_array(strtolower($key), $sensitiveFields)) {
                $data[$key] = '***REDACTED***';
            }
        }
        
        return $data;
    }

    /**
     * Verificar límites de rate limiting
     */
    public static function checkRateLimit(array $response): array {
        $headers = $response['headers'] ?? [];
        
        return [
            'limit' => $headers['X-RateLimit-Limit'] ?? null,
            'remaining' => $headers['X-RateLimit-Remaining'] ?? null,
            'reset' => $headers['X-RateLimit-Reset'] ?? null,
            'retry_after' => $headers['Retry-After'] ?? null,
            'is_rate_limited' => self::getStatusCode($response) === 429
        ];
    }

    /**
     * Generar reporte de errores
     */
    public static function generateErrorReport(array $responses): array {
        $errors = [];
        $errorsByType = [];
        $errorsByCode = [];

        foreach ($responses as $response) {
            if (!self::isSuccess($response)) {
                $error = self::getError($response);
                $statusCode = self::getStatusCode($response);
                
                $errors[] = [
                    'error' => $error,
                    'status_code' => $statusCode,
                    'timestamp' => $response['timestamp'] ?? date('c'),
                    'operation_id' => $response['operation_id'] ?? 'unknown'
                ];

                // Agrupar por tipo de error
                $errorType = self::getErrorType($error);
                $errorsByType[$errorType] = ($errorsByType[$errorType] ?? 0) + 1;

                // Agrupar por código de estado
                $errorsByCode[$statusCode] = ($errorsByCode[$statusCode] ?? 0) + 1;
            }
        }

        return [
            'total_errors' => count($errors),
            'errors' => $errors,
            'errors_by_type' => $errorsByType,
            'errors_by_status_code' => $errorsByCode,
            'most_common_error' => !empty($errorsByType) ? array_keys($errorsByType, max($errorsByType))[0] : null,
            'generated_at' => date('c')
        ];
    }

    /**
     * Determinar tipo de error
     */
    private static function getErrorType(string $error): string {
        $error = strtolower($error);
        
        if (strpos($error, 'network') !== false || strpos($error, 'connection') !== false) {
            return 'network';
        } elseif (strpos($error, 'auth') !== false || strpos($error, 'unauthorized') !== false) {
            return 'authentication';
        } elseif (strpos($error, 'not found') !== false) {
            return 'not_found';
        } elseif (strpos($error, 'validation') !== false || strpos($error, 'invalid') !== false) {
            return 'validation';
        } elseif (strpos($error, 'rate') !== false || strpos($error, 'limit') !== false) {
            return 'rate_limit';
        } else {
            return 'unknown';
        }
    }
}