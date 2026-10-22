<?php

declare(strict_types=1);

namespace App\Services\Messagerie;

/**
 * Ce que la messagerie accepte comme fichier, et comment elle le reconnaît.
 *
 * Le nom ne suffit jamais : le type est lu dans le contenu (finfo) et doit
 * correspondre à l'extension. Un script PHP, un exécutable ou une page HTML
 * renommés en « .pdf » sont refusés, comme le SVG (qui peut porter du
 * JavaScript), le HTML et les archives ZIP.
 *
 * Les formats Office récents (DOCX, XLSX, PPTX) sont des archives ZIP : selon
 * la version de la bibliothèque magic, finfo les nomme par leur type Office
 * ou simplement « application/zip ». Dans ce second cas, on vérifie que
 * l'archive porte bien la structure du document annoncé ([Content_Types].xml
 * et le dossier word/, xl/ ou ppt/) : une archive quelconque reste refusée.
 */
final class MessagerieFormats
{
    public const TAILLE_MAX = 25 * 1024 * 1024;

    public const CATEGORIES = ['pdf', 'image', 'word', 'tableur', 'presentation', 'texte'];

    /** Formats que le navigateur affiche lui-même : aperçu plutôt que téléchargement. */
    public const MIMES_EN_LIGNE = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];

    private const OLE = ['application/msword', 'application/vnd.ms-excel', 'application/vnd.ms-powerpoint',
        'application/vnd.ms-office', 'application/x-ole-storage', 'application/CDFV2', 'application/cdfv2'];

    /**
     * Extension => [catégorie, types reconnus par finfo, type servi, dossier OOXML].
     *
     * @var array<string, array{0:string, 1:array<int,string>, 2:string, 3:?string}>
     */
    private const FORMATS = [
        'pdf' => ['pdf', ['application/pdf'], 'application/pdf', null],
        'jpg' => ['image', ['image/jpeg', 'image/pjpeg'], 'image/jpeg', null],
        'png' => ['image', ['image/png'], 'image/png', null],
        'webp' => ['image', ['image/webp'], 'image/webp', null],
        'doc' => ['word', self::OLE, 'application/msword', null],
        'docx' => ['word', ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'word/'],
        'xls' => ['tableur', self::OLE, 'application/vnd.ms-excel', null],
        'xlsx' => ['tableur', ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xl/'],
        'ppt' => ['presentation', self::OLE, 'application/vnd.ms-powerpoint', null],
        'pptx' => ['presentation', ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
            'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'ppt/'],
        'txt' => ['texte', ['text/plain'], 'text/plain', null],
        'csv' => ['tableur', ['text/csv', 'text/plain', 'application/csv', 'text/x-csv', 'application/vnd.ms-excel'], 'text/csv', null],
    ];

    private const NOMS = [
        'pdf' => 'un PDF', 'jpg' => 'une image JPEG', 'png' => 'une image PNG', 'webp' => 'une image WEBP',
        'doc' => 'un document Word', 'docx' => 'un document Word', 'xls' => 'un classeur Excel',
        'xlsx' => 'un classeur Excel', 'ppt' => 'une présentation PowerPoint', 'pptx' => 'une présentation PowerPoint',
        'txt' => 'un fichier texte', 'csv' => 'un fichier CSV',
    ];

    public const MESSAGE_FORMATS = 'Formats acceptés : PDF, JPG, PNG, WEBP, Word (DOC, DOCX), Excel (XLS, XLSX), '
        . 'PowerPoint (PPT, PPTX), TXT et CSV.';

    /**
     * Reconnaît le fichier, ou le refuse avec un message lisible.
     *
     * @return array{extension:string, mime:string, categorie:string}
     */
    public static function reconnaitre(string $chemin, string $nomOrigine): array
    {
        $extension = self::extension($nomOrigine);
        if ($extension === null) {
            throw MessagerieException::invalide('Ce format n’est pas accepté. ' . self::MESSAGE_FORMATS);
        }

        [$categorie, $mimesReconnus, $mimeServi, $dossierOoxml] = self::FORMATS[$extension];
        $typeReel = self::typeReel($chemin);

        $conforme = in_array($typeReel, $mimesReconnus, true)
            || ($dossierOoxml !== null && in_array($typeReel, ['application/zip', 'application/octet-stream'], true)
                && self::estOoxml($chemin, $dossierOoxml));

        if (!$conforme) {
            throw MessagerieException::invalide(sprintf(
                'Le contenu de « %s » n’est pas %s : le fichier est refusé. Envoyez le document d’origine, sans le renommer.',
                self::nomAffichable($nomOrigine),
                self::NOMS[$extension]
            ));
        }

        // Un texte « innocent » qui contient du code serveur n'a rien à faire
        // ici : il est servi en text/plain, mais on ne le garde pas non plus.
        if ($categorie === 'texte' || $extension === 'csv') {
            $debut = (string) file_get_contents($chemin, false, null, 0, 1024 * 1024);
            if (preg_match('/<\?(php|=)/i', $debut) === 1) {
                throw MessagerieException::invalide('Ce fichier texte contient du code de programme : il est refusé.');
            }
        }

        return ['extension' => $extension, 'mime' => $mimeServi, 'categorie' => $categorie];
    }

    /** Extension normalisée (jpeg devient jpg), ou null si le format n'est pas accepté. */
    public static function extension(string $nom): ?string
    {
        $extension = strtolower(self::decouper($nom)[1]);
        if ($extension === 'jpeg' || $extension === 'jpe') {
            $extension = 'jpg';
        }

        return isset(self::FORMATS[$extension]) ? $extension : null;
    }

    public static function mimeDe(string $extension): string
    {
        return self::FORMATS[$extension][2] ?? 'application/octet-stream';
    }

    public static function estImage(string $extension): bool
    {
        return (self::FORMATS[$extension][0] ?? '') === 'image';
    }

    public static function typeReel(string $chemin): string
    {
        if (class_exists(\finfo::class)) {
            $type = (new \finfo(FILEINFO_MIME_TYPE))->file($chemin);
            if (is_string($type) && $type !== '') {
                return $type;
            }
        }

        return function_exists('mime_content_type') ? (string) (mime_content_type($chemin) ?: '') : '';
    }

    /**
     * Nom d'origine nettoyé pour la base et l'affichage : sans chemin, sans
     * caractères de contrôle, 255 caractères au plus, extension gardée.
     */
    public static function nomAffichable(string $nom): string
    {
        // D'abord l'encodage : un client ancien envoie le nom en Windows-1252,
        // et les expressions /u ci-dessous rendraient alors un nom vide.
        if (!mb_check_encoding($nom, 'UTF-8')) {
            $nom = mb_convert_encoding($nom, 'UTF-8', 'Windows-1252');
        }
        // Sans chemin, et sans basename(), qui dépend de la locale du serveur.
        $nom = str_replace('\\', '/', $nom);
        $position = strrpos($nom, '/');
        if ($position !== false) {
            $nom = substr($nom, $position + 1);
        }
        $nom = trim((string) preg_replace('/[\x00-\x1F\x7F"]+/u', '', $nom));
        if ($nom === '' || $nom === '.') {
            $nom = 'document';
        }
        if (mb_strlen($nom) > 255) {
            [$base, $extension] = self::decouper($nom);
            $nom = mb_substr($base, 0, 250 - mb_strlen($extension)) . ($extension !== '' ? '.' . $extension : '');
        }

        return $nom;
    }

    /** Remplace l'extension du nom quand le fichier stocké a changé de format (PNG devenu JPEG). */
    public static function nomAvecExtension(string $nom, string $extension): string
    {
        $actuelle = self::extension($nom);
        if ($actuelle === $extension) {
            return $nom;
        }

        $base = self::decouper($nom)[0];

        return ($base !== '' ? $base : 'document') . '.' . $extension;
    }

    /**
     * « Relevé été.pdf » => ['Relevé été', 'pdf']. À la main plutôt que par
     * pathinfo(), qui dépend de la locale et coupe mal les noms accentués.
     *
     * @return array{0:string, 1:string}
     */
    private static function decouper(string $nom): array
    {
        $point = strrpos($nom, '.');
        if ($point === false || $point === 0 || str_contains(substr($nom, $point), '/')) {
            return [$nom, ''];
        }

        return [substr($nom, 0, $point), substr($nom, $point + 1)];
    }

    private static function estOoxml(string $chemin, string $dossier): bool
    {
        if (class_exists(\ZipArchive::class)) {
            $zip = new \ZipArchive();
            if ($zip->open($chemin, \ZipArchive::RDONLY) !== true) {
                return false;
            }
            $types = $zip->locateName('[Content_Types].xml') !== false;
            $trouve = false;
            for ($i = 0; $i < $zip->numFiles && !$trouve; $i++) {
                $trouve = str_starts_with((string) $zip->getNameIndex($i), $dossier);
            }
            $zip->close();

            return $types && $trouve;
        }

        // Sans l'extension zip : les noms figurent en clair dans l'annuaire
        // central de l'archive.
        $contenu = (string) file_get_contents($chemin);

        return str_starts_with($contenu, "PK\x03\x04")
            && str_contains($contenu, '[Content_Types].xml')
            && str_contains($contenu, $dossier);
    }
}
