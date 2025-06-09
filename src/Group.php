<?php
namespace EvoApi;

/**
 * Manejo de grupos en Evolution API v2
 */
class Group {
    private EvoClient $client;

    public function __construct(EvoClient $client) {
        $this->client = $client;
    }

    /**
     * Crea un nuevo grupo
     */
    public function createGroup(string $instance, string $groupName, array $participants): array {
        return $this->client->post("group/create/{$instance}", [
            'subject' => $groupName,
            'participants' => $participants
        ]);
    }

    /**
     * Obtiene información de un grupo
     */
    public function getGroupInfo(string $instance, string $groupId): array {
        return $this->client->get("group/findGroup/{$instance}", [
            'groupJid' => $groupId
        ]);
    }

    /**
     * Lista todos los grupos
     */
    public function listGroups(string $instance): array {
        return $this->client->get("group/findGroups/{$instance}");
    }

    /**
     * Añade participantes a un grupo
     */
    public function addParticipant(string $instance, string $groupId, array $participants): array {
        return $this->client->put("group/updateParticipant/{$instance}", [
            'groupJid' => $groupId,
            'action' => 'add',
            'participants' => $participants
        ]);
    }

    /**
5     * Remover participantes de un grupo
     */
    public function removeParticipant(string $instance, string $groupId, array $participants): array {
        return $this->client->put("group/updateParticipant/{$instance}", [
            'groupJid' => $groupId,
            'action' => 'remove',
            'participants' => $participants
        ]);
    }

    /**
     * Promover participantes a administradores
     */
    public function promoteParticipant(string $instance, string $groupId, array $participants): array {
        return $this->client->put("group/updateParticipant/{$instance}", [
            'groupJid' => $groupId,
            'action' => 'promote',
            'participants' => $participants
        ]);
    }

    /**
     * Degradar administradores a participantes
     */
    public function demoteParticipant(string $instance, string $groupId, array $participants): array {
        return $this->client->put("group/updateParticipant/{$instance}", [
            'groupJid' => $groupId,
            'action' => 'demote',
            'participants' => $participants
        ]);
    }

    /**
     * Actualizar configuración del grupo
     */
    public function updateGroupSetting(string $instance, string $groupId, string $setting, string $value): array {
        return $this->client->put("group/updateSetting/{$instance}", [
            'groupJid' => $groupId,
            'setting' => $setting,
            'value' => $value
        ]);
    }

    /**
     * Cambiar nombre del grupo
     */
    public function updateGroupName(string $instance, string $groupId, string $newName): array {
        return $this->client->put("group/updateGroupPicture/{$instance}", [
            'groupJid' => $groupId,
            'subject' => $newName
        ]);
    }

    /**
     * Cambiar descripción del grupo
     */
    public function updateGroupDescription(string $instance, string $groupId, string $description): array {
        return $this->client->put("group/updateGroupPicture/{$instance}", [
            'groupJid' => $groupId,
            'description' => $description
        ]);
    }

    /**
     * Actualizar foto del grupo
     */
    public function updateGroupPicture(string $instance, string $groupId, string $imagePath): array {
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

        return $this->client->uploadFile("group/updateGroupPicture/{$instance}", 'picture', $imagePath, [
            'groupJid' => $groupId
        ]);
    }

    /**
     * Obtener enlace de invitación del grupo
     */
    public function getInviteCode(string $instance, string $groupId): array {
        return $this->client->get("group/inviteCode/{$instance}", [
            'groupJid' => $groupId
        ]);
    }

    /**
     * Revocar enlace de invitación del grupo
     */
    public function revokeInviteCode(string $instance, string $groupId): array {
        return $this->client->put("group/revokeInviteCode/{$instance}", [
            'groupJid' => $groupId
        ]);
    }

    /**
     * Salir del grupo
     */
    public function leaveGroup(string $instance, string $groupId): array {
        return $this->client->delete("group/leaveGroup/{$instance}", [
            'groupJid' => $groupId
        ]);
    }

    /**
     * Obtener participantes del grupo
     */
    public function getGroupParticipants(string $instance, string $groupId): array {
        return $this->client->get("group/participants/{$instance}", [
            'groupJid' => $groupId
        ]);
    }

    /**
     * Unirse a un grupo por enlace de invitación
     */
    public function joinGroupByInvite(string $instance, string $inviteCode): array {
        return $this->client->put("group/joinGroupByInvite/{$instance}", [
            'inviteCode' => $inviteCode
        ]);
    }

    /**
     * Crear grupo con configuración avanzada
     */
    public function createAdvancedGroup(string $instance, array $groupData): array {
        $defaultData = [
            'subject' => '',
            'participants' => [],
            'description' => '',
            'groupSettings' => [
                'sendMessages' => 'all', // 'all' o 'admins'
                'editGroupInfo' => 'all', // 'all' o 'admins'
                'approve' => false,
                'disappearingMessages' => '0'
            ]
        ];

        $data = array_merge($defaultData, $groupData);

        if (empty($data['subject'])) {
            return [
                'success' => false,
                'error' => 'El nombre del grupo (subject) es requerido'
            ];
        }

        if (empty($data['participants'])) {
            return [
                'success' => false,
                'error' => 'Al menos un participante es requerido'
            ];
        }

        // Crear el grupo básico primero
        $createResponse = $this->createGroup($instance, $data['subject'], $data['participants']);

        if (!ResponseHandler::isSuccess($createResponse)) {
            return $createResponse;
        }

        $groupData = ResponseHandler::getData($createResponse);
        $groupId = $groupData['groupJid'] ?? null;

        if (!$groupId) {
            return [
                'success' => false,
                'error' => 'No se pudo obtener el ID del grupo creado'
            ];
        }

        $responses = ['create' => $createResponse];

        // Configurar descripción si se proporciona
        if (!empty($data['description'])) {
            $responses['description'] = $this->updateGroupDescription($instance, $groupId, $data['description']);
        }

        // Aplicar configuraciones del grupo
        foreach ($data['groupSettings'] as $setting => $value) {
            $responses["setting_{$setting}"] = $this->updateGroupSetting($instance, $groupId, $setting, $value);
        }

        return [
            'success' => true,
            'group_id' => $groupId,
            'responses' => $responses
        ];
    }

    /**
     * Obtener información completa del grupo
     */
    public function getCompleteGroupInfo(string $instance, string $groupId): array {
        $responses = [];

        // Información básica del grupo
        $responses['info'] = $this->getGroupInfo($instance, $groupId);

        // Participantes del grupo
        $responses['participants'] = $this->getGroupParticipants($instance, $groupId);

        // Enlace de invitación
        $responses['invite_code'] = $this->getInviteCode($instance, $groupId);

        return ResponseHandler::combineResponses($responses, 'complete_group_info');
    }

    /**
     * Gestión masiva de participantes
     */
    public function bulkParticipantAction(string $instance, string $groupId, string $action, array $participants): array {
        if (empty($participants)) {
            return [
                'success' => false,
                'error' => 'Lista de participantes vacía'
            ];
        }

        $validActions = ['add', 'remove', 'promote', 'demote'];
        if (!in_array($action, $validActions)) {
            return [
                'success' => false,
                'error' => 'Acción inválida. Válidas: ' . implode(', ', $validActions)
            ];
        }

        // Dividir en lotes para evitar sobrecargar la API
        $batchSize = 10;
        $batches = array_chunk($participants, $batchSize);
        $results = [];

        foreach ($batches as $index => $batch) {
            $response = $this->client->put("group/updateParticipant/{$instance}", [
                'groupJid' => $groupId,
                'action' => $action,
                'participants' => $batch
            ]);

            $results[] = [
                'batch' => $index + 1,
                'participants' => $batch,
                'response' => $response,
                'success' => ResponseHandler::isSuccess($response)
            ];

            // Pausa entre lotes para evitar rate limiting
            if ($index < count($batches) - 1) {
                sleep(1);
            }
        }

        $successfulBatches = array_filter($results, fn($r) => $r['success']);

        return [
            'success' => count($successfulBatches) > 0,
            'total_batches' => count($batches),
            'successful_batches' => count($successfulBatches),
            'failed_batches' => count($batches) - count($successfulBatches),
            'results' => $results
        ];
    }

    /**
     * Buscar grupos por criterios
     */
    public function searchGroups(string $instance, array $criteria = []): array {
        $allGroupsResponse = $this->listGroups($instance);

        if (!ResponseHandler::isSuccess($allGroupsResponse)) {
            return $allGroupsResponse;
        }

        $groups = ResponseHandler::getData($allGroupsResponse);
        $filteredGroups = [];

        foreach ($groups as $group) {
            $matches = true;

            // Filtrar por nombre
            if (isset($criteria['name']) && !empty($criteria['name'])) {
                $matches = $matches && (stripos($group['subject'] ?? '', $criteria['name']) !== false);
            }

            // Filtrar por cantidad de participantes
            if (isset($criteria['min_participants'])) {
                $participantCount = count($group['participants'] ?? []);
                $matches = $matches && ($participantCount >= $criteria['min_participants']);
            }

            if (isset($criteria['max_participants'])) {
                $participantCount = count($group['participants'] ?? []);
                $matches = $matches && ($participantCount <= $criteria['max_participants']);
            }

            // Filtrar por si soy admin
            if (isset($criteria['is_admin']) && $criteria['is_admin']) {
                $myNumber = $this->getMyNumber($instance);
                $admins = array_filter($group['participants'] ?? [], fn($p) => $p['admin'] ?? false);
                $adminNumbers = array_column($admins, 'id');
                $matches = $matches && in_array($myNumber, $adminNumbers);
            }

            if ($matches) {
                $filteredGroups[] = $group;
            }
        }

        return [
            'success' => true,
            'total_groups' => count($groups),
            'filtered_groups' => count($filteredGroups),
            'criteria' => $criteria,
            'groups' => $filteredGroups
        ];
    }

    /**
     * Clonar configuración de grupo
     */
    public function cloneGroup(string $instance, string $sourceGroupId, string $newGroupName, array $participants = []): array {
        // Obtener información del grupo origen
        $sourceInfo = $this->getCompleteGroupInfo($instance, $sourceGroupId);

        if (!ResponseHandler::isSuccess($sourceInfo)) {
            return [
                'success' => false,
                'error' => 'No se pudo obtener información del grupo origen',
                'source_response' => $sourceInfo
            ];
        }

        $sourceData = ResponseHandler::getData($sourceInfo);
        $sourceGroupInfo = $sourceData['complete_group_info']['info'] ?? [];

        // Usar participantes especificados o los del grupo origen
        if (empty($participants)) {
            $sourceParticipants = $sourceData['complete_group_info']['participants'] ?? [];
            $participants = array_column($sourceParticipants, 'id');
        }

        // Configuración para el nuevo grupo
        $newGroupData = [
            'subject' => $newGroupName,
            'participants' => $participants,
            'description' => $sourceGroupInfo['description'] ?? '',
            'groupSettings' => $sourceGroupInfo['groupSettings'] ?? []
        ];

        return $this->createAdvancedGroup($instance, $newGroupData);
    }

    /**
     * Generar reporte de actividad del grupo
     */
    public function generateGroupReport(string $instance, string $groupId): array {
        $groupInfo = $this->getCompleteGroupInfo($instance, $groupId);

        if (!ResponseHandler::isSuccess($groupInfo)) {
            return $groupInfo;
        }

        $data = ResponseHandler::getData($groupInfo);
        $info = $data['complete_group_info']['info'] ?? [];
        $participants = $data['complete_group_info']['participants'] ?? [];

        $report = [
            'group_id' => $groupId,
            'report_generated_at' => date('c'),
            'basic_info' => [
                'name' => $info['subject'] ?? 'Sin nombre',
                'description' => $info['description'] ?? 'Sin descripción',
                'creation_date' => $info['creation'] ?? null,
                'owner' => $info['owner'] ?? 'Desconocido'
            ],
            'participants_summary' => [
                'total_participants' => count($participants),
                'admins' => count(array_filter($participants, fn($p) => $p['admin'] ?? false)),
                'regular_members' => count(array_filter($participants, fn($p) => !($p['admin'] ?? false)))
            ],
            'participants_list' => $participants,
            'settings' => $info['groupSettings'] ?? [],
            'invite_info' => $data['complete_group_info']['invite_code'] ?? []
        ];

        return [
            'success' => true,
            'report' => $report
        ];
    }

    /**
     * Obtener mi número de teléfono (helper para filtros)
     */
    private function getMyNumber(string $instance): string {
        $profileResponse = $this->client->get("/chat/fetchProfile/{$instance}");
        
        if (ResponseHandler::isSuccess($profileResponse)) {
            $data = ResponseHandler::getData($profileResponse);
            return $data['wuid'] ?? '';
        }
        
        return '';
    }

    /**
     * Validar datos de grupo antes de crear
     */
    public function validateGroupData(array $groupData): array {
        $errors = [];
        $warnings = [];

        // Validar campos requeridos
        if (!isset($groupData['subject']) || empty($groupData['subject'])) {
            $errors[] = "Nombre del grupo (subject) es requerido";
        }

        if (!isset($groupData['participants']) || empty($groupData['participants'])) {
            $errors[] = "Al menos un participante es requerido";
        }

        // Validar longitud del nombre
        if (isset($groupData['subject']) && strlen($groupData['subject']) > 25) {
            $warnings[] = "Nombre del grupo muy largo (máximo recomendado: 25 caracteres)";
        }

        // Validar cantidad de participantes
        if (isset($groupData['participants'])) {
            $participantCount = count($groupData['participants']);
            
            if ($participantCount > 256) {
                $errors[] = "Demasiados participantes (máximo 256)";
            }
            
            if ($participantCount < 1) {
                $errors[] = "Al menos un participante es requerido";
            }
        }

        // Validar números de teléfono
        if (isset($groupData['participants'])) {
            foreach ($groupData['participants'] as $participant) {
                if (!preg_match('/^\d{10,15}$/', $participant)) {
                    $warnings[] = "Formato de número sospechoso: {$participant}";
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'group_data' => $groupData
        ];
    }

    /**
     * Exportar datos del grupo
     */
    public function exportGroupData(string $instance, string $groupId, string $format = 'json'): array {
        $groupReport = $this->generateGroupReport($instance, $groupId);

        if (!ResponseHandler::isSuccess($groupReport)) {
            return $groupReport;
        }

        $data = ResponseHandler::getData($groupReport);
        $exportData = [
            'export_date' => date('c'),
            'sdk_version' => '1.0.0',
            'group_data' => $data['report']
        ];

        switch ($format) {
            case 'json':
                return [
                    'success' => true,
                    'format' => 'json',
                    'data' => json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                ];

            case 'csv':
                // CSV de participantes
                $csv = "numero,nombre,admin,fecha_union\n";
                foreach ($data['report']['participants_list'] as $participant) {
                    $csv .= sprintf(
                        "%s,%s,%s,%s\n",
                        $participant['id'] ?? '',
                        '"' . str_replace('"', '""', $participant['name'] ?? 'Sin nombre') . '"',
                        ($participant['admin'] ?? false) ? 'Si' : 'No',
                        $participant['joinedDate'] ?? 'Desconocido'
                    );
                }

                return [
                    'success' => true,
                    'format' => 'csv',
                    'data' => $csv
                ];

            default:
                return [
                    'success' => false,
                    'error' => 'Formato no soportado: ' . $format
                ];
        }
    }
}