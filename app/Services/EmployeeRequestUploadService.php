<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class EmployeeRequestUploadService
{
    /** @return array<string,mixed>|null */
    public function store(?array $file): ?array
    {
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) throw new RuntimeException('Le justificatif n’a pas pu être transmis.');
        if ((int) ($file['size'] ?? 0) > 5 * 1024 * 1024) throw new RuntimeException('Le justificatif doit faire 5 Mo maximum.');
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) throw new RuntimeException('Le fichier transmis est invalide.');
        $mime = mime_content_type($tmp) ?: (string) ($file['type'] ?? '');
        $extensions = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($extensions[$mime])) throw new RuntimeException('Format non autorisé. Utilisez PDF, JPG, PNG ou WEBP.');
        $directory = BASE_PATH . '/public/uploads/employee/requests/' . date('Y/m');
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Impossible de préparer le stockage du justificatif.');
        }
        $filename = 'request_' . bin2hex(random_bytes(12)) . '.' . $extensions[$mime];
        if (!move_uploaded_file($tmp, $directory . '/' . $filename)) throw new RuntimeException('Impossible d’enregistrer le justificatif.');
        return [
            'path' => 'uploads/employee/requests/' . date('Y/m') . '/' . $filename,
            'original_name' => (string) ($file['name'] ?? $filename),
            'mime_type' => $mime,
            'size_bytes' => (int) ($file['size'] ?? 0),
        ];
    }
}
