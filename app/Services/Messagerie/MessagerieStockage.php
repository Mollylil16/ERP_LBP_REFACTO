<?php

declare(strict_types=1);

namespace App\Services\Messagerie;

use GdImage;

/**
 * Le disque de la messagerie : réception, photos, rangement, relecture.
 *
 * En production la racine du dépôt est la racine web, et Apache sert tout
 * fichier qui existe. Les fichiers sont donc rangés sous storage/messagerie,
 * que le .htaccess racine et storage/messagerie/.htaccess interdisent tous
 * deux, sous un nom aléatoire et jamais sous leur nom d'origine : ils ne se
 * téléchargent que par l'ERP, après contrôle des droits.
 *
 * Les photos (JPG, PNG, WEBP) sont redressées selon leur orientation EXIF,
 * ramenées à 2 000 px de côté au plus et réencodées : le réencodage par GD
 * n'écrit aucune métadonnée, la position GPS d'une photo de téléphone ne
 * quitte donc jamais le poste qui l'a prise. L'original n'est pas gardé. Un
 * PNG sans transparence devient un JPEG (une capture d'écran de 3 Mo en
 * pèse alors quelques centaines de Ko) ; un PNG transparent reste un PNG, un
 * WEBP reste un WEBP, transparence comprise.
 *
 * La racine est injectable, et la vérification « vient bien d'un
 * téléversement » peut être levée : c'est ce qui permet aux tests de
 * travailler dans un dossier temporaire avec de vrais fichiers.
 */
final class MessagerieStockage
{
    public const DOSSIER = 'messagerie';
    public const COTE_MAX = 2000;
    public const COTE_MINIATURE = 400;
    public const COTE_PHOTO_GROUPE = 256;
    public const QUALITE = 82;

    /** Au-delà, décoder l'image coûterait plus de 240 Mo de mémoire. */
    public const PIXELS_MAX = 60_000_000;

    public function __construct(
        private string $racine,
        private bool $televersementsSeulement = true,
    ) {
        $this->racine = rtrim(str_replace('\\', '/', $racine), '/');
    }

    public static function creer(): self
    {
        return new self(BASE_PATH . '/storage');
    }

    /**
     * Contrôle une entrée de $_FILES avant toute lecture.
     *
     * @param array<string, mixed> $fichier
     * @return array{temporaire:string, nom:string, taille:int, empreinte:string}
     */
    public function recevoir(array $fichier): array
    {
        if (is_array($fichier['error'] ?? null)) {
            throw MessagerieException::invalide('Un seul fichier par envoi : l’écran les envoie l’un après l’autre.');
        }

        $erreur = (int) ($fichier['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($erreur === UPLOAD_ERR_NO_FILE) {
            throw MessagerieException::invalide('Choisissez le fichier à envoyer.');
        }
        if ($erreur === UPLOAD_ERR_INI_SIZE || $erreur === UPLOAD_ERR_FORM_SIZE) {
            throw MessagerieException::tropLourd(self::messageTaille());
        }
        if ($erreur === UPLOAD_ERR_PARTIAL) {
            throw MessagerieException::invalide('L’envoi a été interrompu avant la fin. Vérifiez la connexion et recommencez.');
        }
        if ($erreur !== UPLOAD_ERR_OK) {
            // Dossier temporaire absent, disque plein, extension PHP : la
            // faute est au serveur, pas au fichier.
            error_log('[Messagerie] téléversement refusé par PHP, code ' . $erreur);
            throw MessagerieException::serveur('Le serveur n’a pas pu recevoir le fichier. Réessayez dans un instant.');
        }

        $temporaire = (string) ($fichier['tmp_name'] ?? '');
        if ($temporaire === '' || !is_file($temporaire)
            || ($this->televersementsSeulement && !is_uploaded_file($temporaire))) {
            throw MessagerieException::invalide('Le fichier transmis est invalide.');
        }

        $taille = (int) filesize($temporaire);
        if ($taille > MessagerieFormats::TAILLE_MAX) {
            throw MessagerieException::tropLourd(self::messageTaille());
        }
        if ($taille <= 0) {
            throw MessagerieException::invalide('Le fichier transmis est vide.');
        }

        return [
            'temporaire' => $temporaire,
            'nom' => MessagerieFormats::nomAffichable((string) ($fichier['name'] ?? 'document')),
            'taille' => $taille,
            'empreinte' => (string) hash_file('sha256', $temporaire),
        ];
    }

    /**
     * Range un fichier reconnu. Les photos sont traitées, les autres copiées
     * telles quelles.
     *
     * @param array{extension:string, mime:string, categorie:string} $format
     * @return array{chemin:string, chemin_miniature:?string, mime:string, extension:string, taille:int, taille_miniature:int, largeur:?int, hauteur:?int}
     */
    public function ranger(string $temporaire, array $format, string $maintenant): array
    {
        $sousDossier = self::DOSSIER . '/' . substr($maintenant, 0, 4) . '/' . substr($maintenant, 5, 2);
        $this->preparer($sousDossier);
        $base = $sousDossier . '/' . bin2hex(random_bytes(16));

        if ($format['categorie'] !== 'image') {
            $chemin = $base . '.' . $format['extension'];
            $this->deposer($temporaire, $this->racine . '/' . $chemin);

            return [
                'chemin' => $chemin,
                'chemin_miniature' => null,
                'mime' => $format['mime'],
                'extension' => $format['extension'],
                'taille' => (int) filesize($this->racine . '/' . $chemin),
                'taille_miniature' => 0,
                'largeur' => null,
                'hauteur' => null,
            ];
        }

        $image = $this->ouvrirImage($temporaire, $format['extension']);
        $transparente = $format['extension'] !== 'jpg' && self::aDeLaTransparence($image);
        $extension = match (true) {
            $format['extension'] === 'webp' => 'webp',
            $transparente => 'png',
            default => 'jpg',
        };

        $photo = self::reduire($image, self::COTE_MAX, $transparente);
        $miniature = self::reduire($photo, self::COTE_MINIATURE, $transparente);

        $chemin = $base . '.' . $extension;
        $cheminMiniature = $base . '_m.' . $extension;
        $this->encoder($photo, $this->racine . '/' . $chemin, $extension);
        $this->encoder($miniature, $this->racine . '/' . $cheminMiniature, $extension);

        return [
            'chemin' => $chemin,
            'chemin_miniature' => $cheminMiniature,
            'mime' => MessagerieFormats::mimeDe($extension),
            'extension' => $extension,
            'taille' => (int) filesize($this->racine . '/' . $chemin),
            'taille_miniature' => (int) filesize($this->racine . '/' . $cheminMiniature),
            'largeur' => imagesx($photo),
            'hauteur' => imagesy($photo),
        ];
    }

    /** Photo de groupe : carré centré de 256 px, sans métadonnées. Rend le chemin stocké. */
    public function rangerPhotoGroupe(string $temporaire, string $extension): string
    {
        $image = $this->ouvrirImage($temporaire, $extension);
        $transparente = $extension !== 'jpg' && self::aDeLaTransparence($image);

        $l = imagesx($image);
        $h = imagesy($image);
        $cote = min($l, $h);
        $carre = self::toile(self::COTE_PHOTO_GROUPE, self::COTE_PHOTO_GROUPE, $transparente);
        imagecopyresampled($carre, $image, 0, 0, intdiv($l - $cote, 2), intdiv($h - $cote, 2),
            self::COTE_PHOTO_GROUPE, self::COTE_PHOTO_GROUPE, $cote, $cote);

        $sortie = $transparente ? 'png' : 'jpg';
        $sousDossier = self::DOSSIER . '/photos';
        $this->preparer($sousDossier);
        $chemin = $sousDossier . '/' . bin2hex(random_bytes(16)) . '.' . $sortie;
        $this->encoder($carre, $this->racine . '/' . $chemin, $sortie);

        return $chemin;
    }

    /** Chemin disque d'un fichier stocké, ou null s'il sort du dossier prévu ou a disparu. */
    public function cheminAbsolu(?string $cheminStocke): ?string
    {
        if ($cheminStocke === null || !str_starts_with($cheminStocke, self::DOSSIER . '/')
            || str_contains($cheminStocke, '..') || str_contains($cheminStocke, "\0")) {
            return null;
        }

        $chemin = $this->racine . '/' . $cheminStocke;

        return is_file($chemin) ? $chemin : null;
    }

    public function effacer(?string $cheminStocke): void
    {
        $chemin = $this->cheminAbsolu($cheminStocke);
        if ($chemin !== null) {
            @unlink($chemin);
        }
    }

    public static function messageTaille(): string
    {
        return 'Le fichier dépasse ' . intdiv(MessagerieFormats::TAILLE_MAX, 1024 * 1024)
            . ' Mo : réduisez-le, ou partagez-le en plusieurs parties.';
    }

    // ------------------------------------------------------------------

    private function ouvrirImage(string $chemin, string $extension): GdImage
    {
        if (!function_exists('imagecreatetruecolor')) {
            throw MessagerieException::serveur('Le traitement des photos est indisponible sur le serveur.');
        }

        $dimensions = @getimagesize($chemin);
        if ($dimensions === false || $dimensions[0] <= 0 || $dimensions[1] <= 0) {
            throw MessagerieException::invalide('Cette image est illisible ou abîmée.');
        }
        if ($dimensions[0] * $dimensions[1] > self::PIXELS_MAX) {
            throw MessagerieException::invalide('Cette image est trop grande (plus de 60 millions de pixels).');
        }

        $image = match ($extension) {
            'jpg' => @imagecreatefromjpeg($chemin),
            'png' => @imagecreatefrompng($chemin),
            'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($chemin) : false,
            default => false,
        };
        if (!$image instanceof GdImage) {
            throw MessagerieException::invalide('Cette image est illisible ou abîmée.');
        }

        if (!imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        return $extension === 'jpg' ? self::redresser($image, $chemin) : $image;
    }

    /** Applique l'orientation EXIF : la photo s'affiche dans le bon sens une fois les métadonnées retirées. */
    private static function redresser(GdImage $image, string $chemin): GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($chemin);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        // imagerotate tourne dans le sens inverse des aiguilles d'une montre.
        $tourner = static fn(GdImage $i, int $angle): GdImage => imagerotate($i, $angle, 0) ?: $i;

        switch ($orientation) {
            case 2:
                imageflip($image, IMG_FLIP_HORIZONTAL);
                break;
            case 3:
                $image = $tourner($image, 180);
                break;
            case 4:
                imageflip($image, IMG_FLIP_VERTICAL);
                break;
            case 5:
                $image = $tourner($image, -90);
                imageflip($image, IMG_FLIP_HORIZONTAL);
                break;
            case 6:
                $image = $tourner($image, -90);
                break;
            case 7:
                $image = $tourner($image, 90);
                imageflip($image, IMG_FLIP_HORIZONTAL);
                break;
            case 8:
                $image = $tourner($image, 90);
                break;
        }

        return $image;
    }

    /**
     * Un pixel non opaque suffit. Les bords sont lus en entier (c'est là
     * qu'est la transparence d'un logo détouré), l'intérieur par échantillons :
     * au plus 250 000 lectures quelle que soit la taille de l'image.
     */
    private static function aDeLaTransparence(GdImage $image): bool
    {
        $l = imagesx($image);
        $h = imagesy($image);
        $opaque = static fn(int $x, int $y): bool => ((imagecolorat($image, $x, $y) >> 24) & 0x7F) === 0;

        for ($x = 0; $x < $l; $x++) {
            if (!$opaque($x, 0) || !$opaque($x, $h - 1)) {
                return true;
            }
        }
        for ($y = 0; $y < $h; $y++) {
            if (!$opaque(0, $y) || !$opaque($l - 1, $y)) {
                return true;
            }
        }

        $pas = max(1, (int) ceil(sqrt($l * $h / 250000)));
        for ($y = 0; $y < $h; $y += $pas) {
            for ($x = 0; $x < $l; $x += $pas) {
                if (!$opaque($x, $y)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Copie réduite à $cote de côté au plus (la même image si elle est déjà assez petite). */
    private static function reduire(GdImage $image, int $cote, bool $transparente): GdImage
    {
        $l = imagesx($image);
        $h = imagesy($image);
        $facteur = min(1, $cote / max($l, $h));
        $nl = max(1, (int) round($l * $facteur));
        $nh = max(1, (int) round($h * $facteur));

        // Même sans réduction, on recopie sur une toile neuve : un PNG opaque
        // devenu JPEG doit perdre son canal alpha sur fond blanc.
        $toile = self::toile($nl, $nh, $transparente);
        imagecopyresampled($toile, $image, 0, 0, 0, 0, $nl, $nh, $l, $h);

        return $toile;
    }

    private static function toile(int $l, int $h, bool $transparente): GdImage
    {
        $toile = imagecreatetruecolor($l, $h);
        if ($transparente) {
            imagealphablending($toile, false);
            imagesavealpha($toile, true);
            imagefill($toile, 0, 0, (int) imagecolorallocatealpha($toile, 0, 0, 0, 127));
        } else {
            imagefill($toile, 0, 0, (int) imagecolorallocate($toile, 255, 255, 255));
        }

        return $toile;
    }

    private function encoder(GdImage $image, string $cible, string $extension): void
    {
        if ($extension === 'jpg') {
            // JPEG progressif : un peu plus léger, et il s'affiche dès les
            // premiers octets sur une connexion lente.
            imageinterlace($image, true);
        }

        $ok = match ($extension) {
            'jpg' => imagejpeg($image, $cible, self::QUALITE),
            'png' => imagepng($image, $cible, 9),
            'webp' => imagewebp($image, $cible, self::QUALITE),
            default => false,
        };

        if (!$ok || !is_file($cible)) {
            throw MessagerieException::serveur('La photo n’a pas pu être enregistrée sur le serveur. Réessayez.');
        }
        @chmod($cible, 0644);
    }

    private function deposer(string $source, string $cible): void
    {
        $ok = $this->televersementsSeulement ? move_uploaded_file($source, $cible) : copy($source, $cible);
        if (!$ok) {
            throw MessagerieException::serveur('Le fichier n’a pas pu être enregistré sur le serveur. Réessayez.');
        }
        @chmod($cible, 0644);
    }

    private function preparer(string $sousDossier): void
    {
        $repertoire = $this->racine . '/' . $sousDossier;
        if (!is_dir($repertoire) && !mkdir($repertoire, 0775, true) && !is_dir($repertoire)) {
            throw MessagerieException::serveur('Le dossier de stockage de la messagerie n’a pas pu être créé.');
        }

        // Seconde barrière, si le fichier du dépôt manquait : le .htaccess
        // racine interdit déjà storage/.
        $garde = $this->racine . '/' . self::DOSSIER . '/.htaccess';
        if (!is_file($garde)) {
            @file_put_contents($garde, self::GARDE);
        }
    }

    public const GARDE = "# Fichiers de la messagerie : téléchargement uniquement par l'ERP, qui vérifie les droits.\n"
        . "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
        . "<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n";
}
