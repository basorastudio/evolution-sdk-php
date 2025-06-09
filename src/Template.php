<?php
namespace EvoApi;

use EvoApi\Utils\ResponseHandler;

/**
 * Sistema avanzado de plantillas para Evolution API v2
 */
class Template {
    private EvoClient $client;
    private array $templates = [];

    public function __construct(EvoClient $client) {
        $this->client = $client;
    }

    /**
     * Crear nueva plantilla
     */
    public function create(string $name, string $content, array $metadata = []): array {
        $template = [
            'id' => 'tpl_' . uniqid(),
            'name' => $name,
            'content' => $content,
            'variables' => $this->extractVariables($content),
            'metadata' => $metadata,
            'created_at' => date('c'),
            'updated_at' => date('c'),
            'usage_count' => 0,
            'category' => $metadata['category'] ?? 'general',
            'language' => $metadata['language'] ?? 'es',
            'tags' => $metadata['tags'] ?? []
        ];

        $this->templates[$template['id']] = $template;

        return [
            'success' => true,
            'message' => 'Plantilla creada exitosamente',
            'template' => $template
        ];
    }

    /**
     * Obtener plantilla por ID
     */
    public function getTemplate(string $templateId): array {
        if (!isset($this->templates[$templateId])) {
            return [
                'success' => false,
                'error' => 'Plantilla no encontrada',
                'template_id' => $templateId
            ];
        }

        return [
            'success' => true,
            'template' => $this->templates[$templateId]
        ];
    }

    /**
     * Listar todas las plantillas
     */
    public function listTemplates(array $filters = []): array {
        $templates = $this->templates;

        // Aplicar filtros
        if (!empty($filters['category'])) {
            $templates = array_filter($templates, fn($t) => $t['category'] === $filters['category']);
        }

        if (!empty($filters['language'])) {
            $templates = array_filter($templates, fn($t) => $t['language'] === $filters['language']);
        }

        if (!empty($filters['tags'])) {
            $tags = is_array($filters['tags']) ? $filters['tags'] : [$filters['tags']];
            $templates = array_filter($templates, function($t) use ($tags) {
                return !empty(array_intersect($t['tags'], $tags));
            });
        }

        return [
            'success' => true,
            'templates' => array_values($templates),
            'total' => count($templates),
            'filters_applied' => $filters
        ];
    }

    /**
     * Actualizar plantilla
     */
    public function updateTemplate(string $templateId, array $updates): array {
        if (!isset($this->templates[$templateId])) {
            return [
                'success' => false,
                'error' => 'Plantilla no encontrada',
                'template_id' => $templateId
            ];
        }

        $template = $this->templates[$templateId];
        
        // Actualizar campos permitidos
        $allowedFields = ['name', 'content', 'metadata', 'category', 'language', 'tags'];
        foreach ($allowedFields as $field) {
            if (isset($updates[$field])) {
                $template[$field] = $updates[$field];
            }
        }

        // Actualizar variables si el contenido cambió
        if (isset($updates['content'])) {
            $template['variables'] = $this->extractVariables($updates['content']);
        }

        $template['updated_at'] = date('c');
        $this->templates[$templateId] = $template;

        return [
            'success' => true,
            'message' => 'Plantilla actualizada exitosamente',
            'template' => $template
        ];
    }

    /**
     * Eliminar plantilla
     */
    public function deleteTemplate(string $templateId): array {
        if (!isset($this->templates[$templateId])) {
            return [
                'success' => false,
                'error' => 'Plantilla no encontrada',
                'template_id' => $templateId
            ];
        }

        $template = $this->templates[$templateId];
        unset($this->templates[$templateId]);

        return [
            'success' => true,
            'message' => 'Plantilla eliminada exitosamente',
            'deleted_template' => $template
        ];
    }

    /**
     * Procesar plantilla con variables
     */
    public function processTemplate(string $templateId, array $variables = []): array {
        if (!isset($this->templates[$templateId])) {
            return [
                'success' => false,
                'error' => 'Plantilla no encontrada',
                'template_id' => $templateId
            ];
        }

        $template = $this->templates[$templateId];
        $content = $template['content'];

        // Reemplazar variables
        foreach ($variables as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
            $content = str_replace('{' . $key . '}', $value, $content);
        }

        // Verificar variables no reemplazadas
        $unreplacedVars = $this->findUnreplacedVariables($content);

        // Incrementar contador de uso
        $this->templates[$templateId]['usage_count']++;

        return [
            'success' => true,
            'processed_content' => $content,
            'original_template' => $template,
            'variables_used' => $variables,
            'unreplaced_variables' => $unreplacedVars,
            'usage_count' => $this->templates[$templateId]['usage_count']
        ];
    }

    /**
     * Enviar mensaje usando plantilla
     */
    public function sendWithTemplate(string $instanceName, string $number, string $templateId, array $variables = []): array {
        $processed = $this->processTemplate($templateId, $variables);
        
        if (!$processed['success']) {
            return $processed;
        }

        $message = new Message($this->client);
        $response = $message->sendText($instanceName, $number, $processed['processed_content']);

        return [
            'success' => $response['success'],
            'message_response' => $response,
            'template_info' => [
                'template_id' => $templateId,
                'variables_used' => $variables,
                'processed_content' => $processed['processed_content']
            ]
        ];
    }

    /**
     * Crear plantilla desde mensaje existente
     */
    public function createFromMessage(string $messageContent, string $templateName, array $metadata = []): array {
        // Detectar automáticamente variables en el contenido
        $detectedVars = $this->extractVariables($messageContent);
        
        return $this->create($templateName, $messageContent, array_merge([
            'created_from' => 'existing_message',
            'auto_detected_vars' => count($detectedVars)
        ], $metadata));
    }

    /**
     * Duplicar plantilla
     */
    public function duplicateTemplate(string $templateId, string $newName = null): array {
        if (!isset($this->templates[$templateId])) {
            return [
                'success' => false,
                'error' => 'Plantilla original no encontrada',
                'template_id' => $templateId
            ];
        }

        $originalTemplate = $this->templates[$templateId];
        $newName = $newName ?: $originalTemplate['name'] . ' (Copia)';

        $duplicatedTemplate = $originalTemplate;
        $duplicatedTemplate['id'] = 'tpl_' . uniqid();
        $duplicatedTemplate['name'] = $newName;
        $duplicatedTemplate['created_at'] = date('c');
        $duplicatedTemplate['updated_at'] = date('c');
        $duplicatedTemplate['usage_count'] = 0;
        $duplicatedTemplate['metadata']['duplicated_from'] = $templateId;

        $this->templates[$duplicatedTemplate['id']] = $duplicatedTemplate;

        return [
            'success' => true,
            'message' => 'Plantilla duplicada exitosamente',
            'original_template' => $originalTemplate,
            'duplicated_template' => $duplicatedTemplate
        ];
    }

    /**
     * Buscar plantillas por contenido
     */
    public function searchTemplates(string $query, array $options = []): array {
        $results = [];
        $searchIn = $options['search_in'] ?? ['name', 'content', 'tags'];
        $caseSensitive = $options['case_sensitive'] ?? false;

        if (!$caseSensitive) {
            $query = strtolower($query);
        }

        foreach ($this->templates as $template) {
            $matches = [];

            if (in_array('name', $searchIn)) {
                $haystack = $caseSensitive ? $template['name'] : strtolower($template['name']);
                if (strpos($haystack, $query) !== false) {
                    $matches[] = 'name';
                }
            }

            if (in_array('content', $searchIn)) {
                $haystack = $caseSensitive ? $template['content'] : strtolower($template['content']);
                if (strpos($haystack, $query) !== false) {
                    $matches[] = 'content';
                }
            }

            if (in_array('tags', $searchIn)) {
                foreach ($template['tags'] as $tag) {
                    $haystack = $caseSensitive ? $tag : strtolower($tag);
                    if (strpos($haystack, $query) !== false) {
                        $matches[] = 'tags';
                        break;
                    }
                }
            }

            if (!empty($matches)) {
                $results[] = [
                    'template' => $template,
                    'matches_in' => array_unique($matches),
                    'relevance_score' => count($matches)
                ];
            }
        }

        // Ordenar por relevancia
        usort($results, fn($a, $b) => $b['relevance_score'] <=> $a['relevance_score']);

        return [
            'success' => true,
            'query' => $query,
            'results' => $results,
            'total_found' => count($results),
            'search_options' => $options
        ];
    }

    /**
     * Exportar plantillas
     */
    public function exportTemplates(array $templateIds = [], string $format = 'json'): array {
        $templatesToExport = empty($templateIds) ? 
            $this->templates : 
            array_intersect_key($this->templates, array_flip($templateIds));

        $exportData = [
            'export_date' => date('c'),
            'sdk_version' => '1.0.0',
            'total_templates' => count($templatesToExport),
            'templates' => array_values($templatesToExport)
        ];

        switch ($format) {
            case 'csv':
                return [
                    'success' => true,
                    'format' => 'csv',
                    'data' => $this->convertToCSV($templatesToExport)
                ];

            case 'xml':
                return [
                    'success' => true,
                    'format' => 'xml',
                    'data' => $this->convertToXML($exportData)
                ];

            case 'json':
            default:
                return [
                    'success' => true,
                    'format' => 'json',
                    'data' => json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                ];
        }
    }

    /**
     * Importar plantillas
     */
    public function importTemplates(string $data, string $format = 'json'): array {
        try {
            $importedTemplates = [];
            $errors = [];

            switch ($format) {
                case 'json':
                    $decodedData = json_decode($data, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        return [
                            'success' => false,
                            'error' => 'JSON inválido: ' . json_last_error_msg()
                        ];
                    }
                    $templates = $decodedData['templates'] ?? $decodedData;
                    break;

                default:
                    return [
                        'success' => false,
                        'error' => 'Formato de importación no soportado: ' . $format
                    ];
            }

            foreach ($templates as $templateData) {
                if (!isset($templateData['name']) || !isset($templateData['content'])) {
                    $errors[] = 'Plantilla incompleta: faltan campos requeridos';
                    continue;
                }

                // Generar nuevo ID para evitar conflictos
                $templateData['id'] = 'tpl_' . uniqid();
                $templateData['imported_at'] = date('c');
                $templateData['usage_count'] = 0;

                $this->templates[$templateData['id']] = $templateData;
                $importedTemplates[] = $templateData;
            }

            return [
                'success' => true,
                'imported_count' => count($importedTemplates),
                'total_attempted' => count($templates),
                'errors' => $errors,
                'imported_templates' => $importedTemplates
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Error durante la importación: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener estadísticas de plantillas
     */
    public function getTemplateStats(): array {
        $totalTemplates = count($this->templates);
        $categories = [];
        $languages = [];
        $totalUsage = 0;
        $mostUsed = null;
        $leastUsed = null;

        foreach ($this->templates as $template) {
            // Contar categorías
            $category = $template['category'];
            $categories[$category] = ($categories[$category] ?? 0) + 1;

            // Contar idiomas
            $language = $template['language'];
            $languages[$language] = ($languages[$language] ?? 0) + 1;

            // Calcular uso total
            $usage = $template['usage_count'];
            $totalUsage += $usage;

            // Encontrar más y menos usadas
            if ($mostUsed === null || $usage > $mostUsed['usage_count']) {
                $mostUsed = $template;
            }
            if ($leastUsed === null || $usage < $leastUsed['usage_count']) {
                $leastUsed = $template;
            }
        }

        return [
            'success' => true,
            'stats' => [
                'total_templates' => $totalTemplates,
                'total_usage' => $totalUsage,
                'average_usage' => $totalTemplates > 0 ? round($totalUsage / $totalTemplates, 2) : 0,
                'categories' => $categories,
                'languages' => $languages,
                'most_used_template' => $mostUsed,
                'least_used_template' => $leastUsed,
                'templates_without_usage' => count(array_filter($this->templates, fn($t) => $t['usage_count'] === 0))
            ]
        };
    }

    /**
     * Extraer variables de un texto
     */
    private function extractVariables(string $content): array {
        $variables = [];
        
        // Buscar patrones {{variable}} y {variable}
        preg_match_all('/\{\{([^}]+)\}\}/', $content, $matches1);
        preg_match_all('/\{([^}]+)\}/', $content, $matches2);
        
        $allMatches = array_merge($matches1[1], $matches2[1]);
        
        foreach ($allMatches as $variable) {
            $variable = trim($variable);
            if (!empty($variable) && !in_array($variable, $variables)) {
                $variables[] = $variable;
            }
        }
        
        return $variables;
    }

    /**
     * Encontrar variables no reemplazadas
     */
    private function findUnreplacedVariables(string $content): array {
        return $this->extractVariables($content);
    }

    /**
     * Convertir plantillas a CSV
     */
    private function convertToCSV(array $templates): string {
        if (empty($templates)) {
            return "id,name,content,category,language,usage_count,created_at\n";
        }

        $csv = "id,name,content,category,language,usage_count,created_at\n";
        
        foreach ($templates as $template) {
            $row = [
                $template['id'],
                '"' . str_replace('"', '""', $template['name']) . '"',
                '"' . str_replace('"', '""', $template['content']) . '"',
                $template['category'],
                $template['language'],
                $template['usage_count'],
                $template['created_at']
            ];
            $csv .= implode(',', $row) . "\n";
        }
        
        return $csv;
    }

    /**
     * Convertir datos a XML
     */
    private function convertToXML(array $data): string {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><template_export></template_export>');
        $this->arrayToXML($data, $xml);
        return $xml->asXML();
    }

    /**
     * Función recursiva para convertir array a XML
     */
    private function arrayToXML(array $data, \SimpleXMLElement $xml): void {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (is_numeric($key)) {
                    $key = 'template';
                }
                $subnode = $xml->addChild($key);
                $this->arrayToXML($value, $subnode);
            } else {
                $xml->addChild($key, htmlspecialchars($value));
            }
        }
    }

    /**
     * Limpiar todas las plantillas
     */
    public function clearAllTemplates(): array {
        $count = count($this->templates);
        $this->templates = [];

        return [
            'success' => true,
            'message' => "Se eliminaron {$count} plantillas",
            'cleared_count' => $count
        ];
    }

    /**
     * Validar estructura de plantilla
     */
    public function validateTemplate(array $templateData): array {
        $errors = [];

        // Validar campos requeridos
        $requiredFields = ['name', 'content'];
        foreach ($requiredFields as $field) {
            if (!isset($templateData[$field]) || empty($templateData[$field])) {
                $errors[] = "Campo requerido faltante: {$field}";
            }
        }

        // Validar longitud del nombre
        if (isset($templateData['name']) && strlen($templateData['name']) > 100) {
            $errors[] = "Nombre demasiado largo (máximo 100 caracteres)";
        }

        // Validar longitud del contenido
        if (isset($templateData['content']) && strlen($templateData['content']) > 4096) {
            $errors[] = "Contenido demasiado largo (máximo 4096 caracteres)";
        }

        // Validar categoría
        $validCategories = ['general', 'marketing', 'support', 'notification', 'greeting', 'farewell'];
        if (isset($templateData['category']) && !in_array($templateData['category'], $validCategories)) {
            $errors[] = "Categoría inválida. Válidas: " . implode(', ', $validCategories);
        }

        // Validar idioma
        $validLanguages = ['es', 'en', 'pt', 'fr', 'de', 'it'];
        if (isset($templateData['language']) && !in_array($templateData['language'], $validLanguages)) {
            $errors[] = "Idioma inválido. Válidos: " . implode(', ', $validLanguages);
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'template_data' => $templateData
        ];
    }
}