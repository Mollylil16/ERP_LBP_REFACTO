<?php

declare(strict_types=1);

namespace App\Services\Site;

use RuntimeException;

final class SiteMediaUploadService
{
    public function storeSlide(?array $file): ?string
    {
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        $stored = $this->store($file, 'slides', [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ], 8 * 1024 * 1024);

        $size = @getimagesize(BASE_PATH . '/public/' . $stored['path']);
        if ($size === false || $size[0] < 1600 || $size[1] < 600) {
            @unlink(BASE_PATH . '/public/' . $stored['path']);
            throw new RuntimeException('L’image du slide doit mesurer au minimum 1600 × 600 px. Format recommandé : 1920 × 760 px.');
        }

        return $stored['path'];
    }

    /** @return array{path:string,original_name:string,mime_type:string,size_bytes:int}|null */
    public function storeMessageAttachment(?array $file): ?array
    {
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return $this->store($file, 'messages', [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'audio/mpeg' => 'mp3',
            'audio/ogg' => 'ogg',
            'audio/webm' => 'webm',
            'audio/mp4' => 'm4a',
        ], 20 * 1024 * 1024);
    }

    /**
     * @param array<string,string> $allowed
     * @return array{path:string,original_name:string,mime_type:string,size_bytes:int}
     */
    private function store(array $file, string $folder, array $allowed, int $maxBytes): array
    {
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Le fichier n’a pas pu être transmis.');
        }
        if ((int) ($file['size'] ?? 0) > $maxBytes) {
            throw new RuntimeException('Le fichier dépasse la taille maximale autorisée.');
        }
        $temporary = (string) ($file['tmp_name'] ?? '');
        if ($temporary === '' || !is_uploaded_file($temporary)) {
            throw new RuntimeException('Le fichier transmis est invalide.');
        }
        $mime = mime_content_type($temporary) ?: (string) ($file['type'] ?? '');
        if (!isset($allowed[$mime])) {
            throw new RuntimeException('Format de fichier non autorisé.');
        }

        $relativeDirectory = 'uploads/site/' . $folder . '/' . date('Y/m');
        $directory = BASE_PATH . '/public/' . $relativeDirectory;
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Impossible de préparer le dossier de stockage.');
        }
        $filename = $folder . '_' . bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($temporary, $directory . '/' . $filename)) {
            throw new RuntimeException('Impossible d’enregistrer le fichier.');
        }

        return [
            'path' => $relativeDirectory . '/' . $filename,
            'original_name' => (string) ($file['name'] ?? $filename),
            'mime_type' => $mime,
            'size_bytes' => (int) ($file['size'] ?? 0),
        ];
    }
}
