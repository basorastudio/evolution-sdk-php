<?php
namespace EvoApi;

use EvoApi\Utils\ResponseHandler;

/**
 * Manejo avanzado de archivos multimedia en Evolution API v2
 * Incluye procesamiento, validación, optimización y gestión completa de medios
 */
class Media {
    private EvoClient $client;
    private array $uploadHistory = [];
    private array $compressionSettings = [];

    public function __construct(EvoClient $client) {
        $this->client = $client;
        $this->initializeCompressionSettings();
    }

    /**
     * Obtiene información de un archivo multimedia
     */
    public function getMedia(string $instance, string $messageId): array {
        return $this->client->get("chat/getMedia/{$instance}", [
            'messageId' => $messageId
        ]);
    }

    /**
     * Obtiene un archivo multimedia en formato base64
     */
    public function getBase64(string $instance, string $messageId): array {
        return $this->client->get("chat/getBase64FromMediaMessage/{$instance}", [
            'messageId' => $messageId
        ]);
    }

    /**
     * Sube un archivo al servidor con opciones avanzadas
     */
    public function uploadFile(string $instance, string $filePath, array $options = []): array {
        $fileName = $options['fileName'] ?? basename($filePath);
        $compress = $options['compress'] ?? true;
        $validate = $options['validate'] ?? true;
        $generateThumbnail = $options['generate_thumbnail'] ?? true;

        // Validar archivo si está habilitado
        if ($validate) {
            $validation = $this->validateMediaType($filePath, $options['allowed_types'] ?? []);
            if (!$validation['valid']) {
                return ResponseHandler::error('Archivo no válido: ' . $validation['mime_type']);
            }
        }

        // Comprimir si está habilitado y es imagen
        $finalPath = $filePath;
        if ($compress && $this->isImage($filePath)) {
            $compressionResult = $this->compressImage($filePath, $options['compression'] ?? []);
            if ($compressionResult['success']) {
                $finalPath = $compressionResult['compressed_path'];
            }
        }

        // Convertir a base64
        $base64Result = $this->fileToBase64($finalPath);
        if (!$base64Result['success']) {
            return $base64Result;
        }

        $uploadData = [
            'media' => $base64Result['base64'],
            'fileName' => $fileName,
            'mimeType' => $base64Result['mime_type']
        ];

        $response = $this->client->post("media/upload/{$instance}", $uploadData);

        if (ResponseHandler::isSuccess($response)) {
            $uploadInfo = [
                'upload_id' => uniqid('upload_'),
                'original_path' => $filePath,
                'final_path' => $finalPath,
                'file_name' => $fileName,
                'mime_type' => $base64Result['mime_type'],
                'file_size' => $base64Result['file_size'],
                'compressed' => $compress && $finalPath !== $filePath,
                'uploaded_at' => date('c')
            ];

            // Generar thumbnail si es imagen
            if ($generateThumbnail && $this->isImage($finalPath)) {
                $thumbnailResult = $this->generateThumbnail($finalPath);
                if ($thumbnailResult['success']) {
                    $uploadInfo['thumbnail'] = $thumbnailResult;
                }
            }

            $this->uploadHistory[] = $uploadInfo;

            return ResponseHandler::success(array_merge(
                ResponseHandler::getData($response),
                ['upload_info' => $uploadInfo]
            ));
        }

        return $response;
    }

    /**
     * Descarga un archivo multimedia con opciones avanzadas
     */
    public function downloadMedia(string $instance, string $messageId, array $options = []): array {
        $savePath = $options['save_path'] ?? '';
        $createThumbnail = $options['create_thumbnail'] ?? false;
        $extractMetadata = $options['extract_metadata'] ?? true;

        $response = $this->getMedia($instance, $messageId);
        
        if (!ResponseHandler::isSuccess($response)) {
            return $response;
        }

        $mediaData = ResponseHandler::getData($response);
        $downloadInfo = [
            'message_id' => $messageId,
            'downloaded_at' => date('c'),
            'metadata' => []
        ];

        if ($savePath && isset($mediaData['media'])) {
            // Decodificar base64 y guardar archivo
            $decodedData = base64_decode($mediaData['media']);
            $fileName = $mediaData['fileName'] ?? 'media_' . time();
            $fullPath = rtrim($savePath, '/') . '/' . $fileName;
            
            // Crear directorio si no existe
            $directory = dirname($fullPath);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            if (file_put_contents($fullPath, $decodedData)) {
                $downloadInfo['saved_path'] = $fullPath;
                $downloadInfo['file_size'] = strlen($decodedData);
                $downloadInfo['file_name'] = $fileName;

                // Extraer metadatos si está habilitado
                if ($extractMetadata) {
                    $downloadInfo['metadata'] = $this->extractMetadata($fullPath);
                }

                // Crear thumbnail si es imagen y está habilitado
                if ($createThumbnail && $this->isImage($fullPath)) {
                    $thumbnailResult = $this->generateThumbnail($fullPath);
                    if ($thumbnailResult['success']) {
                        $downloadInfo['thumbnail'] = $thumbnailResult;
                    }
                }

                return ResponseHandler::success($downloadInfo);
            } else {
                return ResponseHandler::error('No se pudo guardar el archivo');
            }
        }

        return ResponseHandler::success(array_merge($mediaData, $downloadInfo));
    }

    /**
     * Convierte un archivo a base64 con validación mejorada
     */
    public function fileToBase64(string $filePath): array {
        if (!file_exists($filePath)) {
            return ResponseHandler::error('Archivo no encontrado: ' . $filePath);
        }

        if (!is_readable($filePath)) {
            return ResponseHandler::error('Archivo no legible: ' . $filePath);
        }

        $fileData = file_get_contents($filePath);
        if ($fileData === false) {
            return ResponseHandler::error('Error al leer el archivo: ' . $filePath);
        }

        $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
        $base64 = base64_encode($fileData);
        $fileSize = strlen($fileData);

        return ResponseHandler::success([
            'base64' => $base64,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'file_name' => basename($filePath),
            'data_uri' => "data:{$mimeType};base64,{$base64}",
            'encoded_size' => strlen($base64)
        ]);
    }

    /**
     * Valida el tipo de archivo multimedia con verificación avanzada
     */
    public function validateMediaType(string $filePath, array $allowedTypes = []): array {
        $defaultAllowed = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp',
            'video/mp4', 'video/avi', 'video/mov', 'video/webm', 'video/3gp',
            'audio/mp3', 'audio/wav', 'audio/ogg', 'audio/mp4', 'audio/aac',
            'application/pdf', 'application/msword', 'text/plain',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];

        $allowedTypes = $allowedTypes ?: $defaultAllowed;
        
        if (!file_exists($filePath)) {
            return [
                'valid' => false,
                'error' => 'Archivo no encontrado',
                'file_path' => $filePath
            ];
        }

        $mimeType = mime_content_type($filePath);
        $fileSize = filesize($filePath);
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        // Límites de tamaño por tipo de WhatsApp Business API
        $maxSizes = [
            'image' => 16 * 1024 * 1024, // 16MB
            'video' => 64 * 1024 * 1024, // 64MB
            'audio' => 16 * 1024 * 1024, // 16MB
            'document' => 100 * 1024 * 1024 // 100MB
        ];

        $mediaCategory = explode('/', $mimeType)[0];
        $maxSize = $maxSizes[$mediaCategory] ?? $maxSizes['document'];

        // Validaciones adicionales
        $validations = [
            'mime_type_allowed' => in_array($mimeType, $allowedTypes),
            'size_within_limit' => $fileSize <= $maxSize,
            'file_readable' => is_readable($filePath),
            'extension_matches' => $this->extensionMatchesMimeType($extension, $mimeType)
        ];

        $isValid = array_reduce($validations, fn($carry, $item) => $carry && $item, true);

        $result = [
            'valid' => $isValid,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'max_allowed_size' => $maxSize,
            'media_category' => $mediaCategory,
            'extension' => $extension,
            'validations' => $validations
        ];

        if (!$isValid) {
            $errors = [];
            if (!$validations['mime_type_allowed']) $errors[] = 'Tipo de archivo no permitido';
            if (!$validations['size_within_limit']) $errors[] = 'Archivo demasiado grande';
            if (!$validations['file_readable']) $errors[] = 'Archivo no legible';
            if (!$validations['extension_matches']) $errors[] = 'Extensión no coincide con el tipo';
            
            $result['errors'] = $errors;
        }

        return $result;
    }

    /**
     * Comprime una imagen con configuración personalizable
     */
    public function compressImage(string $imagePath, array $options = []): array {
        if (!extension_loaded('gd')) {
            return ResponseHandler::error('Extensión GD requerida para compresión de imágenes');
        }

        if (!$this->isImage($imagePath)) {
            return ResponseHandler::error('El archivo no es una imagen válida');
        }

        $settings = array_merge($this->compressionSettings, $options);
        
        $imageInfo = getimagesize($imagePath);
        if (!$imageInfo) {
            return ResponseHandler::error('No se pudo obtener información de la imagen');
        }

        list($width, $height, $type) = $imageInfo;
        $originalSize = filesize($imagePath);

        // Calcular nuevas dimensiones
        $maxWidth = $settings['max_width'];
        $maxHeight = $settings['max_height'];
        $ratio = min($maxWidth / $width, $maxHeight / $height, 1);
        
        $newWidth = intval($width * $ratio);
        $newHeight = intval($height * $ratio);

        // Crear imagen desde archivo
        $source = $this->createImageFromFile($imagePath, $type);
        if (!$source) {
            return ResponseHandler::error('No se pudo cargar la imagen');
        }

        // Crear imagen redimensionada
        $compressed = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preservar transparencia para PNG
        if ($type == IMAGETYPE_PNG) {
            imagealphablending($compressed, false);
            imagesavealpha($compressed, true);
            $transparent = imagecolorallocatealpha($compressed, 0, 0, 0, 127);
            imagefill($compressed, 0, 0, $transparent);
        }

        imagecopyresampled($compressed, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        // Guardar imagen comprimida
        $compressedPath = $this->getCompressedPath($imagePath);
        $saveResult = $this->saveCompressedImage($compressed, $compressedPath, $type, $settings['quality']);

        imagedestroy($source);
        imagedestroy($compressed);

        if (!$saveResult) {
            return ResponseHandler::error('No se pudo guardar la imagen comprimida');
        }

        $newSize = filesize($compressedPath);
        $compressionRatio = round((1 - $newSize / $originalSize) * 100, 2);

        return ResponseHandler::success([
            'original_path' => $imagePath,
            'compressed_path' => $compressedPath,
            'original_dimensions' => ['width' => $width, 'height' => $height],
            'new_dimensions' => ['width' => $newWidth, 'height' => $newHeight],
            'original_size' => $originalSize,
            'compressed_size' => $newSize,
            'compression_ratio' => $compressionRatio . '%',
            'quality_used' => $settings['quality']
        ]);
    }

    /**
     * Genera thumbnail de una imagen
     */
    public function generateThumbnail(string $imagePath, array $options = []): array {
        if (!$this->isImage($imagePath)) {
            return ResponseHandler::error('El archivo no es una imagen');
        }

        $thumbWidth = $options['width'] ?? 150;
        $thumbHeight = $options['height'] ?? 150;
        $maintainRatio = $options['maintain_ratio'] ?? true;

        $imageInfo = getimagesize($imagePath);
        if (!$imageInfo) {
            return ResponseHandler::error('Imagen inválida');
        }

        list($width, $height, $type) = $imageInfo;
        $source = $this->createImageFromFile($imagePath, $type);

        if (!$source) {
            return ResponseHandler::error('No se pudo cargar la imagen');
        }

        // Calcular dimensiones del thumbnail
        if ($maintainRatio) {
            $ratio = min($thumbWidth / $width, $thumbHeight / $height);
            $thumbWidth = intval($width * $ratio);
            $thumbHeight = intval($height * $ratio);
        }

        $thumbnail = imagecreatetruecolor($thumbWidth, $thumbHeight);
        
        if ($type == IMAGETYPE_PNG) {
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
        }

        imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height);

        $thumbnailPath = $this->getThumbnailPath($imagePath);
        $saveResult = $this->saveCompressedImage($thumbnail, $thumbnailPath, IMAGETYPE_JPEG, 80);

        imagedestroy($source);
        imagedestroy($thumbnail);

        if (!$saveResult) {
            return ResponseHandler::error('No se pudo guardar el thumbnail');
        }

        return ResponseHandler::success([
            'thumbnail_path' => $thumbnailPath,
            'dimensions' => ['width' => $thumbWidth, 'height' => $thumbHeight],
            'file_size' => filesize($thumbnailPath)
        ]);
    }

    /**
     * Extrae metadatos de un archivo multimedia
     */
    public function extractMetadata(string $filePath): array {
        $metadata = [
            'basic' => [
                'file_name' => basename($filePath),
                'file_size' => filesize($filePath),
                'mime_type' => mime_content_type($filePath),
                'modified_time' => filemtime($filePath),
                'created_time' => filectime($filePath)
            ]
        ];

        // Metadatos específicos por tipo
        if ($this->isImage($filePath)) {
            $metadata['image'] = $this->extractImageMetadata($filePath);
        } elseif ($this->isVideo($filePath)) {
            $metadata['video'] = $this->extractVideoMetadata($filePath);
        } elseif ($this->isAudio($filePath)) {
            $metadata['audio'] = $this->extractAudioMetadata($filePath);
        }

        return $metadata;
    }

    /**
     * Procesa múltiples archivos multimedia
     */
    public function batchProcess(array $filePaths, array $operations = [], array $options = []): array {
        $results = [];
        $delay = $options['delay'] ?? 0.1;

        foreach ($filePaths as $index => $filePath) {
            if ($index > 0 && $delay > 0) {
                usleep($delay * 1000000);
            }

            $fileResults = ['file_path' => $filePath];

            foreach ($operations as $operation => $operationOptions) {
                switch ($operation) {
                    case 'validate':
                        $fileResults['validation'] = $this->validateMediaType($filePath, $operationOptions);
                        break;
                    case 'compress':
                        if ($this->isImage($filePath)) {
                            $fileResults['compression'] = $this->compressImage($filePath, $operationOptions);
                        }
                        break;
                    case 'thumbnail':
                        if ($this->isImage($filePath)) {
                            $fileResults['thumbnail'] = $this->generateThumbnail($filePath, $operationOptions);
                        }
                        break;
                    case 'metadata':
                        $fileResults['metadata'] = $this->extractMetadata($filePath);
                        break;
                    case 'base64':
                        $fileResults['base64'] = $this->fileToBase64($filePath);
                        break;
                }
            }

            $results[] = $fileResults;
        }

        return ResponseHandler::success([
            'processed_files' => count($filePaths),
            'operations_performed' => array_keys($operations),
            'results' => $results,
            'processing_summary' => $this->generateProcessingSummary($results)
        ]);
    }

    /**
     * Obtiene estadísticas de uploads
     */
    public function getUploadStatistics(): array {
        if (empty($this->uploadHistory)) {
            return ResponseHandler::success([
                'total_uploads' => 0,
                'statistics' => []
            ]);
        }

        $stats = [
            'total_uploads' => count($this->uploadHistory),
            'total_size' => array_sum(array_column($this->uploadHistory, 'file_size')),
            'by_type' => [],
            'compression_stats' => [
                'compressed_count' => 0,
                'compression_savings' => 0
            ],
            'recent_uploads' => array_slice($this->uploadHistory, -5)
        ];

        foreach ($this->uploadHistory as $upload) {
            $type = explode('/', $upload['mime_type'])[0];
            $stats['by_type'][$type] = ($stats['by_type'][$type] ?? 0) + 1;

            if ($upload['compressed']) {
                $stats['compression_stats']['compressed_count']++;
            }
        }

        return ResponseHandler::success($stats);
    }

    // Métodos auxiliares privados

    private function initializeCompressionSettings(): void {
        $this->compressionSettings = [
            'max_width' => 1920,
            'max_height' => 1080,
            'quality' => 85
        ];
    }

    private function isImage(string $filePath): bool {
        $imageTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP, IMAGETYPE_BMP];
        $imageInfo = getimagesize($filePath);
        return $imageInfo && in_array($imageInfo[2], $imageTypes);
    }

    private function isVideo(string $filePath): bool {
        $mimeType = mime_content_type($filePath);
        return strpos($mimeType, 'video/') === 0;
    }

    private function isAudio(string $filePath): bool {
        $mimeType = mime_content_type($filePath);
        return strpos($mimeType, 'audio/') === 0;
    }

    private function extensionMatchesMimeType(string $extension, string $mimeType): bool {
        $mapping = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png', 'gif' => 'image/gif',
            'pdf' => 'application/pdf', 'doc' => 'application/msword',
            'mp4' => 'video/mp4', 'mp3' => 'audio/mp3'
        ];

        return isset($mapping[$extension]) && $mapping[$extension] === $mimeType;
    }

    private function createImageFromFile(string $filePath, int $type) {
        return match($type) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($filePath),
            IMAGETYPE_PNG => imagecreatefrompng($filePath),
            IMAGETYPE_GIF => imagecreatefromgif($filePath),
            IMAGETYPE_WEBP => imagecreatefromwebp($filePath),
            default => false
        };
    }

    private function saveCompressedImage($image, string $path, int $type, int $quality): bool {
        return match($type) {
            IMAGETYPE_JPEG => imagejpeg($image, $path, $quality),
            IMAGETYPE_PNG => imagepng($image, $path),
            IMAGETYPE_GIF => imagegif($image, $path),
            IMAGETYPE_WEBP => imagewebp($image, $path, $quality),
            default => imagejpeg($image, $path, $quality) // Default to JPEG
        };
    }

    private function getCompressedPath(string $originalPath): string {
        $pathInfo = pathinfo($originalPath);
        return $pathInfo['dirname'] . '/compressed_' . $pathInfo['basename'];
    }

    private function getThumbnailPath(string $originalPath): string {
        $pathInfo = pathinfo($originalPath);
        return $pathInfo['dirname'] . '/thumb_' . $pathInfo['filename'] . '.jpg';
    }

    private function extractImageMetadata(string $imagePath): array {
        $imageInfo = getimagesize($imagePath);
        $metadata = [
            'dimensions' => [
                'width' => $imageInfo[0] ?? 0,
                'height' => $imageInfo[1] ?? 0
            ],
            'type' => $imageInfo[2] ?? 0,
            'bits' => $imageInfo['bits'] ?? 0,
            'channels' => $imageInfo['channels'] ?? 0
        ];

        // Intentar extraer EXIF si es JPEG
        if (function_exists('exif_read_data') && $imageInfo[2] === IMAGETYPE_JPEG) {
            $exif = @exif_read_data($imagePath);
            if ($exif) {
                $metadata['exif'] = array_filter($exif, function($key) {
                    return !is_array($exif[$key]);
                }, ARRAY_FILTER_USE_KEY);
            }
        }

        return $metadata;
    }

    private function extractVideoMetadata(string $videoPath): array {
        // Metadatos básicos (requeriría FFmpeg para información completa)
        return [
            'format' => pathinfo($videoPath, PATHINFO_EXTENSION),
            'note' => 'Metadatos completos requieren FFmpeg'
        ];
    }

    private function extractAudioMetadata(string $audioPath): array {
        // Metadatos básicos (requeriría getID3 o similar para información completa)
        return [
            'format' => pathinfo($audioPath, PATHINFO_EXTENSION),
            'note' => 'Metadatos completos requieren biblioteca adicional'
        ];
    }

    private function generateProcessingSummary(array $results): array {
        $summary = [
            'successful_operations' => 0,
            'failed_operations' => 0,
            'operations_by_type' => []
        ];

        foreach ($results as $result) {
            foreach ($result as $operation => $data) {
                if ($operation === 'file_path') continue;

                $summary['operations_by_type'][$operation] = ($summary['operations_by_type'][$operation] ?? 0) + 1;

                if (isset($data['success']) && $data['success']) {
                    $summary['successful_operations']++;
                } else {
                    $summary['failed_operations']++;
                }
            }
        }

        return $summary;
    }
}