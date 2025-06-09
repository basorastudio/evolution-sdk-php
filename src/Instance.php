<?php
namespace EvoApi;

use EvoApi\Utils\ResponseHandler;

/**
 * Gestión de instancias de WhatsApp para Evolution API v2
 */
class Instance {
    private EvoClient $client;

    public function __construct(EvoClient $client) {
        $this->client = $client;
    }

    /**
     * Crear nueva instancia
     */
    public function create(array $data): array {
        $defaultData = [
            'instanceName' => '',
            'token' => '',
            'qrcode' => true,
            'integration' => 'WHATSAPP-BAILEYS',
            'chatwoot_account_id' => null,
            'chatwoot_token' => null,
            'chatwoot_url' => null,
            'chatwoot_sign_msg' => false,
            'chatwoot_reopen_conversation' => false,
            'chatwoot_conversation_pending' => false
        ];

        $instanceData = array_merge($defaultData, $data);
        
        // Validar datos requeridos
        if (empty($instanceData['instanceName'])) {
            return [
                'success' => false,
                'error' => 'El nombre de la instancia es requerido',
                'data' => []
            ];
        }

        $response = $this->client->post('/instance/create', $instanceData);
        
        if (ResponseHandler::isSuccess($response)) {
            // Si la creación fue exitosa y hay QR, extraerlo
            $data = ResponseHandler::getData($response);
            if (isset($data['qrcode'])) {
                $response['qr_code'] = $data['qrcode'];
                $response['qr_available'] = true;
            }
        }

        return $response;
    }

    /**
     * Conectar instancia y obtener QR
     */
    public function connect(string $instanceName): array {
        $response = $this->client->get("/instance/connect/{$instanceName}");
        
        if (ResponseHandler::isSuccess($response)) {
            $data = ResponseHandler::getData($response);
            
            // Extraer información del QR si está disponible
            if (isset($data['base64'])) {
                $response['qr_code'] = $data['base64'];
                $response['qr_format'] = 'base64';
            }
        }

        return $response;
    }

    /**
     * Obtener estado de la instancia
     */
    public function getConnectionState(string $instanceName): array {
        return $this->client->get("/instance/connectionState/{$instanceName}");
    }

    /**
     * Listar todas las instancias
     */
    public function listInstances(): array {
        return $this->client->get('/instance/fetchInstances');
    }

    /**
     * Eliminar instancia
     */
    public function delete(string $instanceName): array {
        return $this->client->delete("/instance/delete/{$instanceName}");
    }

    /**
     * Desconectar instancia (logout)
     */
    public function logout(string $instanceName): array {
        return $this->client->delete("/instance/logout/{$instanceName}");
    }

    /**
     * Reiniciar instancia
     */
    public function restart(string $instanceName): array {
        return $this->client->put("/instance/restart/{$instanceName}");
    }

    /**
     * Configurar configuración de la instancia
     */
    public function setSettings(string $instanceName, array $settings): array {
        return $this->client->put("/instance/settings/{$instanceName}", $settings);
    }

    /**
     * Obtener configuración de la instancia
     */
    public function getSettings(string $instanceName): array {
        return $this->client->get("/instance/settings/{$instanceName}");
    }

    /**
     * Configurar perfil de WhatsApp
     */
    public function setProfile(string $instanceName, array $profileData): array {
        $data = [
            'name' => $profileData['name'] ?? null,
            'status' => $profileData['status'] ?? null,
            'picture' => $profileData['picture'] ?? null
        ];

        // Filtrar valores null
        $data = array_filter($data, fn($value) => $value !== null);

        return $this->client->put("/chat/updateProfileName/{$instanceName}", $data);
    }

    /**
     * Obtener perfil de WhatsApp
     */
    public function getProfile(string $instanceName): array {
        return $this->client->get("/chat/fetchProfile/{$instanceName}");
    }

    /**
     * Configurar foto de perfil
     */
    public function setProfilePicture(string $instanceName, string $imagePath): array {
        if (!file_exists($imagePath)) {
            return [
                'success' => false,
                'error' => 'Archivo de imagen no encontrado: ' . $imagePath
            ];
        }

        // Validar que sea una imagen
        $imageInfo = getimagesize($imagePath);
        if (!$imageInfo) {
            return [
                'success' => false,
                'error' => 'El archivo no es una imagen válida'
            ];
        }

        return $this->client->uploadFile("/chat/updateProfilePicture/{$instanceName}", 'picture', $imagePath);
    }

    /**
     * Obtener información completa de la instancia
     */
    public function getInstanceInfo(string $instanceName): array {
        $responses = [];
        
        // Obtener estado de conexión
        $responses['connection_state'] = $this->getConnectionState($instanceName);
        
        // Obtener perfil si está conectado
        if (ResponseHandler::isSuccess($responses['connection_state'])) {
            $connectionData = ResponseHandler::getData($responses['connection_state']);
            if ($connectionData['instance']['state'] === 'open') {
                $responses['profile'] = $this->getProfile($instanceName);
                $responses['settings'] = $this->getSettings($instanceName);
            }
        }

        return ResponseHandler::combineResponses($responses, 'instance_info');
    }

    /**
     * Monitorear estado de conexión de instancia
     */
    public function monitorConnection(string $instanceName, callable $callback = null, int $maxAttempts = 30): array {
        $attempts = 0;
        $states = [];
        
        while ($attempts < $maxAttempts) {
            $response = $this->getConnectionState($instanceName);
            $state = null;
            
            if (ResponseHandler::isSuccess($response)) {
                $data = ResponseHandler::getData($response);
                $state = $data['instance']['state'] ?? 'unknown';
            }
            
            $states[] = [
                'attempt' => $attempts + 1,
                'timestamp' => date('c'),
                'state' => $state,
                'response' => $response
            ];
            
            // Llamar callback si se proporciona
            if ($callback && is_callable($callback)) {
                $continue = $callback($state, $attempts + 1, $response);
                if ($continue === false) {
                    break;
                }
            }
            
            // Si está conectado, terminar monitoreo
            if ($state === 'open') {
                break;
            }
            
            $attempts++;
            if ($attempts < $maxAttempts) {
                sleep(2); // Esperar 2 segundos entre intentos
            }
        }
        
        return [
            'success' => !empty($states),
            'final_state' => end($states)['state'],
            'total_attempts' => count($states),
            'monitoring_time' => count($states) * 2, // aprox. en segundos
            'state_history' => $states
        ];
    }

    /**
     * Esperar a que la instancia se conecte
     */
    public function waitForConnection(string $instanceName, int $timeoutSeconds = 60): array {
        $startTime = time();
        $endTime = $startTime + $timeoutSeconds;
        
        while (time() < $endTime) {
            $response = $this->getConnectionState($instanceName);
            
            if (ResponseHandler::isSuccess($response)) {
                $data = ResponseHandler::getData($response);
                $state = $data['instance']['state'] ?? 'unknown';
                
                if ($state === 'open') {
                    return [
                        'success' => true,
                        'connected' => true,
                        'wait_time' => time() - $startTime,
                        'final_state' => $state
                    ];
                }
            }
            
            sleep(2);
        }
        
        return [
            'success' => false,
            'connected' => false,
            'timeout' => true,
            'wait_time' => $timeoutSeconds,
            'error' => 'Timeout esperando conexión de la instancia'
        ];
    }

    /**
     * Configurar webhook para la instancia
     */
    public function setWebhook(string $instanceName, string $webhookUrl, array $events = []): array {
        $webhook = new Webhook($this->client);
        return $webhook->setWebhook($instanceName, $webhookUrl, $events);
    }

    /**
     * Verificar si la instancia está lista para enviar mensajes
     */
    public function isReady(string $instanceName): array {
        $connectionResponse = $this->getConnectionState($instanceName);
        
        if (!ResponseHandler::isSuccess($connectionResponse)) {
            return [
                'ready' => false,
                'reason' => 'No se pudo obtener el estado de conexión',
                'connection_response' => $connectionResponse
            ];
        }
        
        $data = ResponseHandler::getData($connectionResponse);
        $state = $data['instance']['state'] ?? 'unknown';
        
        return [
            'ready' => $state === 'open',
            'state' => $state,
            'reason' => $state === 'open' ? 'Instancia conectada y lista' : 'Instancia no conectada: ' . $state
        ];
    }

    /**
     * Obtener código QR actual
     */
    public function getQRCode(string $instanceName): array {
        $response = $this->connect($instanceName);
        
        if (ResponseHandler::isSuccess($response)) {
            $data = ResponseHandler::getData($response);
            
            if (isset($data['base64'])) {
                return [
                    'success' => true,
                    'qr_code' => $data['base64'],
                    'format' => 'base64',
                    'timestamp' => date('c')
                ];
            }
        }
        
        return [
            'success' => false,
            'error' => 'No se pudo obtener el código QR',
            'response' => $response
        ];
    }

    /**
     * Generar reporte de estado de instancia
     */
    public function generateStatusReport(string $instanceName): array {
        $report = [
            'instance_name' => $instanceName,
            'report_generated_at' => date('c'),
            'checks' => []
        ];
        
        // Check 1: Estado de conexión
        $connectionResponse = $this->getConnectionState($instanceName);
        $report['checks']['connection'] = [
            'name' => 'Estado de Conexión',
            'status' => ResponseHandler::isSuccess($connectionResponse) ? 'pass' : 'fail',
            'details' => $connectionResponse
        ];
        
        // Check 2: Perfil (solo si está conectado)
        if (ResponseHandler::isSuccess($connectionResponse)) {
            $data = ResponseHandler::getData($connectionResponse);
            if (($data['instance']['state'] ?? 'unknown') === 'open') {
                $profileResponse = $this->getProfile($instanceName);
                $report['checks']['profile'] = [
                    'name' => 'Perfil de WhatsApp',
                    'status' => ResponseHandler::isSuccess($profileResponse) ? 'pass' : 'fail',
                    'details' => $profileResponse
                ];
            }
        }
        
        // Check 3: Configuración
        $settingsResponse = $this->getSettings($instanceName);
        $report['checks']['settings'] = [
            'name' => 'Configuración de Instancia',
            'status' => ResponseHandler::isSuccess($settingsResponse) ? 'pass' : 'fail',
            'details' => $settingsResponse
        ];
        
        // Calcular estado general
        $passedChecks = array_filter($report['checks'], fn($check) => $check['status'] === 'pass');
        $report['overall_status'] = count($passedChecks) === count($report['checks']) ? 'healthy' : 'issues';
        $report['health_score'] = round((count($passedChecks) / count($report['checks'])) * 100, 2);
        
        return $report;
    }

    /**
     * Limpiar datos de instancia
     */
    public function clearInstanceData(string $instanceName): array {
        // Esta operación elimina chats, mensajes y datos locales de la instancia
        return $this->client->delete("/instance/clearData/{$instanceName}");
    }

    /**
     * Clonar configuración de instancia
     */
    public function cloneInstance(string $sourceInstanceName, string $newInstanceName, array $overrides = []): array {
        // Obtener configuración de la instancia origen
        $settingsResponse = $this->getSettings($sourceInstanceName);
        
        if (!ResponseHandler::isSuccess($settingsResponse)) {
            return [
                'success' => false,
                'error' => 'No se pudo obtener la configuración de la instancia origen',
                'source_response' => $settingsResponse
            ];
        }
        
        $settings = ResponseHandler::getData($settingsResponse);
        
        // Aplicar sobrescrituras
        $newSettings = array_merge($settings, $overrides, [
            'instanceName' => $newInstanceName
        ]);
        
        // Crear nueva instancia
        $createResponse = $this->create($newSettings);
        
        return [
            'success' => ResponseHandler::isSuccess($createResponse),
            'source_instance' => $sourceInstanceName,
            'new_instance' => $newInstanceName,
            'cloned_settings' => $newSettings,
            'create_response' => $createResponse
        ];
    }

    /**
     * Exportar configuración de instancia
     */
    public function exportConfiguration(string $instanceName, string $format = 'json'): array {
        $responses = [];
        
        // Obtener toda la información de la instancia
        $responses['connection'] = $this->getConnectionState($instanceName);
        $responses['settings'] = $this->getSettings($instanceName);
        
        if (ResponseHandler::isSuccess($responses['connection'])) {
            $data = ResponseHandler::getData($responses['connection']);
            if (($data['instance']['state'] ?? 'unknown') === 'open') {
                $responses['profile'] = $this->getProfile($instanceName);
            }
        }
        
        $exportData = [
            'export_date' => date('c'),
            'instance_name' => $instanceName,
            'sdk_version' => '1.0.0',
            'data' => $responses
        ];
        
        switch ($format) {
            case 'json':
                return [
                    'success' => true,
                    'format' => 'json',
                    'data' => json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                ];
                
            case 'yaml':
                if (function_exists('yaml_emit')) {
                    return [
                        'success' => true,
                        'format' => 'yaml',
                        'data' => yaml_emit($exportData)
                    ];
                } else {
                    return [
                        'success' => false,
                        'error' => 'Extensión YAML no disponible'
                    ];
                }
                
            default:
                return [
                    'success' => false,
                    'error' => 'Formato no soportado: ' . $format
                ];
        }
    }

    /**
     * Validar configuración de instancia
     */
    public function validateConfiguration(array $config): array {
        $errors = [];
        $warnings = [];
        
        // Validar campos requeridos
        $requiredFields = ['instanceName'];
        foreach ($requiredFields as $field) {
            if (!isset($config[$field]) || empty($config[$field])) {
                $errors[] = "Campo requerido faltante: {$field}";
            }
        }
        
        // Validar nombre de instancia
        if (isset($config['instanceName'])) {
            if (strlen($config['instanceName']) < 3) {
                $errors[] = "Nombre de instancia demasiado corto (mínimo 3 caracteres)";
            }
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $config['instanceName'])) {
                $errors[] = "Nombre de instancia contiene caracteres inválidos (solo a-z, A-Z, 0-9, _, -)";
            }
        }
        
        // Validar integración
        $validIntegrations = ['WHATSAPP-BAILEYS', 'WHATSAPP-BUSINESS'];
        if (isset($config['integration']) && !in_array($config['integration'], $validIntegrations)) {
            $errors[] = "Integración inválida. Válidas: " . implode(', ', $validIntegrations);
        }
        
        // Validar configuración de Chatwoot si está presente
        if (isset($config['chatwoot_account_id']) && !empty($config['chatwoot_account_id'])) {
            if (empty($config['chatwoot_token'])) {
                $errors[] = "chatwoot_token requerido cuando se especifica chatwoot_account_id";
            }
            if (empty($config['chatwoot_url'])) {
                $errors[] = "chatwoot_url requerido cuando se especifica chatwoot_account_id";
            }
        }
        
        // Warnings
        if (!isset($config['qrcode'])) {
            $warnings[] = "Campo 'qrcode' no especificado, se usará valor por defecto (true)";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'config' => $config
        ];
    }
}