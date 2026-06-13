<?php

namespace App\Modules\Uploads\Controllers;

use App\Controllers\BaseController;
use App\Core\Response;

class UploadsController extends BaseController
{
    public function upload(): void
    {
        $this->authenticate();

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            Response::error('Aucun fichier reçu ou erreur lors du téléchargement.', 400);
        }

        $file = $_FILES['file'];
        $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
        $maxSize = 5 * 1024 * 1024; // 5 MB

        if (!in_array($file['type'], $allowedTypes)) {
            Response::error('Type de fichier non autorisé. Seuls JPG, PNG et PDF sont acceptés.', 400);
        }

        if ($file['size'] > $maxSize) {
            Response::error('Le fichier est trop volumineux (Max: 5MB).', 400);
        }

        $uploadDir = BASE_PATH . '/public/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Generer un nom unique sécurisé
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = uniqid('upload_', true) . '.' . $extension;
        $destination = $uploadDir . $fileName;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            $fileUrl = '/uploads/' . $fileName; // L'URL accessible publiquement
            Response::success(['url' => $fileUrl], 'Fichier téléchargé avec succès', 201);
        } else {
            Response::error('Erreur lors de la sauvegarde du fichier.', 500);
        }
    }
}
