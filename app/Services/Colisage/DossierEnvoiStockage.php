<?php

declare(strict_types=1);

namespace App\Services\Colisage;

use RuntimeException;

/**
 * Pièces jointes des dossiers d'envoi.
 *
 * En production, la racine du projet est la racine web et Apache sert tout
 * fichier qui existe. Les pièces sont donc rangées sous storage/, avec une règle
 * qui en interdit la lecture directe, et sous un nom aléatoire : elles ne se
 * téléchargent que par l'ERP, qui vérifie les droits de l'utilisateur.
 */
final class DossierEnvoiStockage
{
    private const DOSSIER = 'dossiers_envoi';

    public function __construct(private string $racine)
    {
    }

    public static function creer(): self
    {
        return new self(BASE_PATH . '/storage');
    }

    /**
     * Vérifie le fichier transmis et le range.
     *
     * @param array<string, mixed> $fichier entrée de $_FILES
     * @return array{stored_path:string, original_name:string, mime_type:string, size_bytes:int, extension:string}
     */
    public function ranger(array $fichier, string $nomBase): array
    {
        $erreur = (int) ($fichier['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($erreur === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('Choisissez le fichier à joindre.');
        }
        if (in_array($erreur, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            throw new RuntimeException($this->messageTaille());
        }
        if ($erreur !== UPLOAD_ERR_OK) {
            throw new RuntimeException("Le fichier n'a pas pu être transmis. Réessayez.");
        }

        $taille = (int) ($fichier['size'] ?? 0);
        if ($taille > DossierEnvoiRegles::TAILLE_MAX_DOCUMENT) {
            throw new RuntimeException($this->messageTaille());
        }
        if ($taille <= 0) {
            throw new RuntimeException('Le fichier transmis est vide.');
        }

        $temporaire = (string) ($fichier['tmp_name'] ?? '');
        if ($temporaire === '' || !is_uploaded_file($temporaire)) {
            throw new RuntimeException('Le fichier transmis est invalide.');
        }

        $nomOriginal = (string) ($fichier['name'] ?? 'document');
        $typeReel = $this->typeReel($temporaire);
        $extension = DossierEnvoiRegles::extensionDocument($typeReel, $nomOriginal);

        if ($extension === null) {
            throw new RuntimeException('Format refusé : joignez un PDF, une image (JPG, PNG, WEBP) ou un fichier Excel (XLS, XLSX).');
        }

        $sousDossier = self::DOSSIER . '/' . date('Y/m');
        $repertoire = $this->racine . '/' . $sousDossier;
        $this->preparer($repertoire);

        $nomStocke = (preg_replace('/[^A-Za-z0-9_\-]/', '_', $nomBase) ?? 'piece') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;

        if (!move_uploaded_file($temporaire, $repertoire . '/' . $nomStocke)) {
            throw new RuntimeException("Le fichier n'a pas pu être enregistré sur le serveur. Réessayez.");
        }

        return [
            'stored_path' => $sousDossier . '/' . $nomStocke,
            'original_name' => mb_substr($nomOriginal, 0, 255),
            'mime_type' => $typeReel,
            'size_bytes' => $taille,
            'extension' => $extension,
        ];
    }

    /** Chemin disque d'une pièce, ou null si le chemin enregistré sort du dossier prévu. */
    public function cheminAbsolu(string $cheminStocke): ?string
    {
        if (!str_starts_with($cheminStocke, self::DOSSIER . '/') || str_contains($cheminStocke, '..')) {
            return null;
        }

        $chemin = $this->racine . '/' . $cheminStocke;

        return is_file($chemin) ? $chemin : null;
    }

    public function effacer(string $cheminStocke): void
    {
        $chemin = $this->cheminAbsolu($cheminStocke);
        if ($chemin !== null) {
            @unlink($chemin);
        }
    }

    private function typeReel(string $fichier): string
    {
        if (class_exists(\finfo::class)) {
            $type = (new \finfo(FILEINFO_MIME_TYPE))->file($fichier);
            if (is_string($type) && $type !== '') {
                return $type;
            }
        }

        return function_exists('mime_content_type') ? (string) (mime_content_type($fichier) ?: '') : '';
    }

    private function preparer(string $repertoire): void
    {
        if (!is_dir($repertoire) && !mkdir($repertoire, 0775, true) && !is_dir($repertoire)) {
            throw new RuntimeException("Le dossier de stockage des pièces n'a pas pu être créé.");
        }

        $garde = $this->racine . '/' . self::DOSSIER . '/.htaccess';
        if (!is_file($garde)) {
            file_put_contents(
                $garde,
                "# Pièces des dossiers d'envoi : téléchargement uniquement par l'ERP, qui vérifie les droits.\n"
                . "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
                . "<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n"
            );
        }
    }

    private function messageTaille(): string
    {
        return 'Le fichier dépasse ' . (int) (DossierEnvoiRegles::TAILLE_MAX_DOCUMENT / 1024 / 1024)
            . ' Mo : réduisez-le ou scannez-le en qualité moindre.';
    }
}
