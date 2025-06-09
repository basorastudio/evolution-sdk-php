<?php
namespace EvoApi;

use EvoApi\Utils\ResponseHandler;

/**
 * Sistema de análisis de sentimientos y métricas para Evolution API v2
 */
class Analytics {
    private EvoClient $client;
    private array $metrics = [];
    private array $sentimentData = [];

    public function __construct(EvoClient $client) {
        $this->client = $client;
    }

    /**
     * Analizar sentimiento de un mensaje
     */
    public function analyzeSentiment(string $text, string $language = 'es'): array {
        // Palabras positivas y negativas básicas en español
        $positiveWords = [
            'bien', 'bueno', 'excelente', 'perfecto', 'genial', 'fantástico', 
            'maravilloso', 'increíble', 'gracias', 'feliz', 'contento', 
            'satisfecho', 'amor', 'alegría', 'éxito', 'victoria', 'ganador',
            'hermoso', 'brillante', 'awesome', 'great', 'good', 'excellent',
            'perfect', 'amazing', 'wonderful', 'fantastic', 'happy', 'love'
        ];

        $negativeWords = [
            'mal', 'malo', 'terrible', 'horrible', 'pésimo', 'odio', 'triste',
            'enojado', 'frustrado', 'molesto', 'problema', 'error', 'falla',
            'fracaso', 'perdedor', 'imposible', 'difícil', 'complicado',
            'bad', 'terrible', 'awful', 'hate', 'angry', 'sad', 'problem',
            'error', 'fail', 'difficult', 'impossible', 'frustrated'
        ];

        $intensifiers = [
            'muy' => 1.5, 'súper' => 2.0, 'extremadamente' => 2.5, 'bastante' => 1.3,
            'really' => 1.5, 'very' => 1.5, 'extremely' => 2.5, 'super' => 2.0
        ];

        $text = strtolower($text);
        $words = preg_split('/\s+/', $text);
        
        $positiveScore = 0;
        $negativeScore = 0;
        $foundWords = [];

        for ($i = 0; $i < count($words); $i++) {
            $word = trim($words[$i], '.,!?;:"()[]{}');
            $multiplier = 1.0;

            // Verificar intensificadores
            if ($i > 0) {
                $prevWord = trim($words[$i-1], '.,!?;:"()[]{}');
                if (isset($intensifiers[$prevWord])) {
                    $multiplier = $intensifiers[$prevWord];
                }
            }

            // Verificar negaciones
            $negated = false;
            if ($i > 0) {
                $prevWord = trim($words[$i-1], '.,!?;:"()[]{}');
                if (in_array($prevWord, ['no', 'not', 'never', 'ningún', 'ninguna', 'jamás'])) {
                    $negated = true;
                }
            }

            if (in_array($word, $positiveWords)) {
                $score = $multiplier;
                if ($negated) {
                    $negativeScore += $score;
                    $foundWords[] = ['word' => $word, 'type' => 'positive_negated', 'score' => $score];
                } else {
                    $positiveScore += $score;
                    $foundWords[] = ['word' => $word, 'type' => 'positive', 'score' => $score];
                }
            } elseif (in_array($word, $negativeWords)) {
                $score = $multiplier;
                if ($negated) {
                    $positiveScore += $score;
                    $foundWords[] = ['word' => $word, 'type' => 'negative_negated', 'score' => $score];
                } else {
                    $negativeScore += $score;
                    $foundWords[] = ['word' => $word, 'type' => 'negative', 'score' => $score];
                }
            }
        }

        // Calcular puntuación final
        $totalScore = $positiveScore - $negativeScore;
        $normalizedScore = max(-1, min(1, $totalScore / max(1, abs($totalScore))));

        // Determinar sentimiento
        $sentiment = 'neutral';
        $confidence = abs($normalizedScore);

        if ($normalizedScore > 0.1) {
            $sentiment = 'positive';
        } elseif ($normalizedScore < -0.1) {
            $sentiment = 'negative';
        }

        // Ajustar confianza basada en cantidad de palabras encontradas
        if (count($foundWords) === 0) {
            $confidence = 0;
        } elseif (count($foundWords) === 1) {
            $confidence *= 0.7;
        }

        $analysis = [
            'text' => $text,
            'sentiment' => $sentiment,
            'score' => $normalizedScore,
            'confidence' => $confidence,
            'positive_score' => $positiveScore,
            'negative_score' => $negativeScore,
            'found_words' => $foundWords,
            'word_count' => count($words),
            'analyzed_at' => date('c'),
            'language' => $language
        ];

        // Guardar para análisis posterior
        $this->sentimentData[] = $analysis;

        return [
            'success' => true,
            'analysis' => $analysis
        ];
    }

    /**
     * Analizar conversación completa
     */
    public function analyzeConversation(string $instanceName, string $number, array $options = []): array {
        $message = new Message($this->client);
        $messagesResult = $message->getMessages($instanceName, $number, $options);

        if (!$messagesResult['success']) {
            return $messagesResult;
        }

        $messages = $messagesResult['messages'] ?? [];
        $conversationAnalysis = [
            'total_messages' => count($messages),
            'sentiment_distribution' => ['positive' => 0, 'neutral' => 0, 'negative' => 0],
            'average_sentiment' => 0,
            'sentiment_trend' => [],
            'emotional_peaks' => [],
            'message_analyses' => []
        ];

        $totalScore = 0;
        $validAnalyses = 0;

        foreach ($messages as $msg) {
            if (isset($msg['message']['conversation']) && !empty($msg['message']['conversation'])) {
                $analysis = $this->analyzeSentiment($msg['message']['conversation']);
                
                if ($analysis['success']) {
                    $sentiment = $analysis['analysis'];
                    $conversationAnalysis['message_analyses'][] = array_merge($sentiment, [
                        'message_id' => $msg['key']['id'] ?? null,
                        'timestamp' => $msg['messageTimestamp'] ?? null
                    ]);

                    $conversationAnalysis['sentiment_distribution'][$sentiment['sentiment']]++;
                    $totalScore += $sentiment['score'];
                    $validAnalyses++;

                    // Detectar picos emocionales
                    if (abs($sentiment['score']) > 0.7) {
                        $conversationAnalysis['emotional_peaks'][] = [
                            'message_id' => $msg['key']['id'] ?? null,
                            'sentiment' => $sentiment['sentiment'],
                            'score' => $sentiment['score'],
                            'text' => substr($msg['message']['conversation'], 0, 100) . '...'
                        ];
                    }

                    // Tendencia de sentimientos
                    $conversationAnalysis['sentiment_trend'][] = [
                        'timestamp' => $msg['messageTimestamp'] ?? null,
                        'score' => $sentiment['score']
                    ];
                }
            }
        }

        if ($validAnalyses > 0) {
            $conversationAnalysis['average_sentiment'] = $totalScore / $validAnalyses;
        }

        // Ordenar tendencia por timestamp
        usort($conversationAnalysis['sentiment_trend'], function($a, $b) {
            return ($a['timestamp'] ?? 0) - ($b['timestamp'] ?? 0);
        });

        return [
            'success' => true,
            'conversation_analysis' => $conversationAnalysis,
            'number' => $number,
            'analyzed_at' => date('c')
        ];
    }

    /**
     * Registrar métrica personalizada
     */
    public function recordMetric(string $name, $value, array $metadata = []): array {
        $metric = [
            'id' => 'metric_' . uniqid(),
            'name' => $name,
            'value' => $value,
            'metadata' => $metadata,
            'timestamp' => time(),
            'date' => date('c')
        ];

        if (!isset($this->metrics[$name])) {
            $this->metrics[$name] = [];
        }

        $this->metrics[$name][] = $metric;

        return [
            'success' => true,
            'metric' => $metric
        ];
    }

    /**
     * Obtener estadísticas de métricas
     */
    public function getMetricStats(string $name, array $options = []): array {
        if (!isset($this->metrics[$name])) {
            return [
                'success' => false,
                'error' => 'Métrica no encontrada: ' . $name
            ];
        }

        $metrics = $this->metrics[$name];
        $timeRange = $options['time_range'] ?? 3600; // 1 hora por defecto
        $now = time();

        // Filtrar por rango de tiempo si se especifica
        if (isset($options['time_range'])) {
            $metrics = array_filter($metrics, function($metric) use ($now, $timeRange) {
                return ($now - $metric['timestamp']) <= $timeRange;
            });
        }

        if (empty($metrics)) {
            return [
                'success' => true,
                'stats' => [
                    'count' => 0,
                    'values' => []
                ]
            ];
        }

        $values = array_column($metrics, 'value');
        $numericValues = array_filter($values, 'is_numeric');

        $stats = [
            'count' => count($metrics),
            'values' => $values,
            'first_recorded' => min(array_column($metrics, 'timestamp')),
            'last_recorded' => max(array_column($metrics, 'timestamp'))
        ];

        if (!empty($numericValues)) {
            $stats['numeric_stats'] = [
                'min' => min($numericValues),
                'max' => max($numericValues),
                'average' => array_sum($numericValues) / count($numericValues),
                'sum' => array_sum($numericValues),
                'median' => $this->calculateMedian($numericValues)
            ];
        }

        return [
            'success' => true,
            'metric_name' => $name,
            'stats' => $stats,
            'time_range_seconds' => $timeRange
        ];
    }

    /**
     * Generar reporte de actividad
     */
    public function generateActivityReport(string $instanceName, array $options = []): array {
        $timeRange = $options['time_range'] ?? 86400; // 24 horas por defecto
        $includeChats = $options['include_chats'] ?? true;
        $includeSentiment = $options['include_sentiment'] ?? true;

        $report = [
            'instance' => $instanceName,
            'time_range_hours' => $timeRange / 3600,
            'generated_at' => date('c'),
            'summary' => [
                'total_messages_sent' => 0,
                'total_messages_received' => 0,
                'unique_contacts' => 0,
                'active_conversations' => 0
            ]
        ];

        // Obtener métricas de mensajes si están disponibles
        if (isset($this->metrics['messages_sent'])) {
            $sentStats = $this->getMetricStats('messages_sent', ['time_range' => $timeRange]);
            if ($sentStats['success']) {
                $report['summary']['total_messages_sent'] = $sentStats['stats']['count'];
            }
        }

        if (isset($this->metrics['messages_received'])) {
            $receivedStats = $this->getMetricStats('messages_received', ['time_range' => $timeRange]);
            if ($receivedStats['success']) {
                $report['summary']['total_messages_received'] = $receivedStats['stats']['count'];
            }
        }

        // Análisis de sentimientos si está habilitado
        if ($includeSentiment && !empty($this->sentimentData)) {
            $recentSentiments = array_filter($this->sentimentData, function($data) use ($timeRange) {
                $timestamp = strtotime($data['analyzed_at']);
                return (time() - $timestamp) <= $timeRange;
            });

            if (!empty($recentSentiments)) {
                $sentimentStats = [
                    'total_analyzed' => count($recentSentiments),
                    'distribution' => ['positive' => 0, 'neutral' => 0, 'negative' => 0],
                    'average_score' => 0
                ];

                $totalScore = 0;
                foreach ($recentSentiments as $sentiment) {
                    $sentimentStats['distribution'][$sentiment['sentiment']]++;
                    $totalScore += $sentiment['score'];
                }

                $sentimentStats['average_score'] = $totalScore / count($recentSentiments);
                $report['sentiment_analysis'] = $sentimentStats;
            }
        }

        // Métricas personalizadas
        $report['custom_metrics'] = [];
        foreach ($this->metrics as $metricName => $metricData) {
            $stats = $this->getMetricStats($metricName, ['time_range' => $timeRange]);
            if ($stats['success'] && $stats['stats']['count'] > 0) {
                $report['custom_metrics'][$metricName] = $stats['stats'];
            }
        }

        return [
            'success' => true,
            'report' => $report
        ];
    }

    /**
     * Obtener métricas en tiempo real
     */
    public function getRealTimeMetrics(array $metricNames = []): array {
        $realTimeData = [
            'timestamp' => time(),
            'date' => date('c'),
            'metrics' => []
        ];

        $metricsToCheck = empty($metricNames) ? array_keys($this->metrics) : $metricNames;

        foreach ($metricsToCheck as $metricName) {
            if (isset($this->metrics[$metricName])) {
                $recentMetrics = array_filter($this->metrics[$metricName], function($metric) {
                    return (time() - $metric['timestamp']) <= 300; // Últimos 5 minutos
                });

                if (!empty($recentMetrics)) {
                    $values = array_column($recentMetrics, 'value');
                    $lastValue = end($values);
                    
                    $realTimeData['metrics'][$metricName] = [
                        'current_value' => $lastValue,
                        'count_last_5min' => count($recentMetrics),
                        'values_last_5min' => $values
                    ];

                    if (is_numeric($lastValue)) {
                        $numericValues = array_filter($values, 'is_numeric');
                        if (!empty($numericValues)) {
                            $realTimeData['metrics'][$metricName]['average_last_5min'] = 
                                array_sum($numericValues) / count($numericValues);
                        }
                    }
                }
            }
        }

        return [
            'success' => true,
            'real_time_data' => $realTimeData
        ];
    }

    /**
     * Configurar alertas de métricas
     */
    public function setupAlert(string $metricName, array $conditions, array $actions = []): array {
        $alert = [
            'id' => 'alert_' . uniqid(),
            'metric_name' => $metricName,
            'conditions' => $conditions,
            'actions' => $actions,
            'created_at' => date('c'),
            'active' => true,
            'triggered_count' => 0,
            'last_triggered' => null
        ];

        // Validar condiciones
        $validOperators = ['>', '<', '>=', '<=', '==', '!='];
        foreach ($conditions as $condition) {
            if (!isset($condition['operator']) || !in_array($condition['operator'], $validOperators)) {
                return [
                    'success' => false,
                    'error' => 'Operador inválido en condición'
                ];
            }
        }

        if (!isset($this->metrics['_alerts'])) {
            $this->metrics['_alerts'] = [];
        }

        $this->metrics['_alerts'][] = $alert;

        return [
            'success' => true,
            'alert' => $alert
        ];
    }

    /**
     * Evaluar alertas
     */
    public function evaluateAlerts(): array {
        $triggeredAlerts = [];

        if (!isset($this->metrics['_alerts'])) {
            return [
                'success' => true,
                'triggered_alerts' => []
            ];
        }

        foreach ($this->metrics['_alerts'] as &$alert) {
            if (!$alert['active']) {
                continue;
            }

            $metricName = $alert['metric_name'];
            if (!isset($this->metrics[$metricName]) || empty($this->metrics[$metricName])) {
                continue;
            }

            $latestMetric = end($this->metrics[$metricName]);
            $value = $latestMetric['value'];

            $allConditionsMet = true;
            foreach ($alert['conditions'] as $condition) {
                $conditionMet = false;
                $threshold = $condition['value'];
                
                switch ($condition['operator']) {
                    case '>':
                        $conditionMet = $value > $threshold;
                        break;
                    case '<':
                        $conditionMet = $value < $threshold;
                        break;
                    case '>=':
                        $conditionMet = $value >= $threshold;
                        break;
                    case '<=':
                        $conditionMet = $value <= $threshold;
                        break;
                    case '==':
                        $conditionMet = $value == $threshold;
                        break;
                    case '!=':
                        $conditionMet = $value != $threshold;
                        break;
                }

                if (!$conditionMet) {
                    $allConditionsMet = false;
                    break;
                }
            }

            if ($allConditionsMet) {
                $alert['triggered_count']++;
                $alert['last_triggered'] = date('c');
                
                $triggeredAlert = array_merge($alert, [
                    'current_value' => $value,
                    'triggered_at' => date('c')
                ]);
                
                $triggeredAlerts[] = $triggeredAlert;
            }
        }

        return [
            'success' => true,
            'triggered_alerts' => $triggeredAlerts,
            'total_alerts_checked' => count($this->metrics['_alerts'] ?? [])
        ];
    }

    /**
     * Exportar datos de análisis
     */
    public function exportAnalytics(array $options = []): array {
        $format = $options['format'] ?? 'json';
        $includeMetrics = $options['include_metrics'] ?? true;
        $includeSentiment = $options['include_sentiment'] ?? true;
        
        $exportData = [
            'export_info' => [
                'generated_at' => date('c'),
                'format' => $format,
                'includes' => [
                    'metrics' => $includeMetrics,
                    'sentiment' => $includeSentiment
                ]
            ]
        ];

        if ($includeMetrics) {
            $exportData['metrics'] = $this->metrics;
        }

        if ($includeSentiment) {
            $exportData['sentiment_data'] = $this->sentimentData;
        }

        $exportedContent = '';
        
        switch ($format) {
            case 'csv':
                $exportedContent = $this->exportToCSV($exportData);
                break;
            
            case 'json':
            default:
                $exportedContent = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                break;
        }

        return [
            'success' => true,
            'exported_content' => $exportedContent,
            'content_length' => strlen($exportedContent),
            'format' => $format
        ];
    }

    /**
     * Calcular mediana
     */
    private function calculateMedian(array $values): float {
        sort($values);
        $count = count($values);
        
        if ($count % 2 === 0) {
            return ($values[$count / 2 - 1] + $values[$count / 2]) / 2;
        } else {
            return $values[floor($count / 2)];
        }
    }

    /**
     * Exportar a CSV
     */
    private function exportToCSV(array $data): string {
        $csv = "Tipo,Nombre,Valor,Timestamp,Metadatos\n";
        
        if (isset($data['metrics'])) {
            foreach ($data['metrics'] as $metricName => $metrics) {
                if ($metricName === '_alerts') continue;
                
                foreach ($metrics as $metric) {
                    $csv .= sprintf(
                        '"%s","%s","%s","%s","%s"' . "\n",
                        'metric',
                        $metricName,
                        $metric['value'],
                        $metric['date'],
                        json_encode($metric['metadata'])
                    );
                }
            }
        }

        if (isset($data['sentiment_data'])) {
            foreach ($data['sentiment_data'] as $sentiment) {
                $csv .= sprintf(
                    '"%s","%s","%s","%s","%s"' . "\n",
                    'sentiment',
                    $sentiment['sentiment'],
                    $sentiment['score'],
                    $sentiment['analyzed_at'],
                    json_encode(['confidence' => $sentiment['confidence'], 'text' => substr($sentiment['text'], 0, 50)])
                );
            }
        }

        return $csv;
    }
}