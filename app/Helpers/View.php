<?php

namespace App\Helpers;

class View
{
    public static function e(mixed $value): string
    {
        if ($value instanceof HtmlString) {
            return $value->toHtml();
        }
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    public static function html(string $html): HtmlString
    {
        return new HtmlString($html);
    }

    public static function asset(string $path): string
    {
        $path = (string) preg_replace('#^(?:public/)?(?:assets/)?#i', '', trim($path));

        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';

        $servesFromPublic = str_contains($scriptName, '/public/')
            || (realpath($docRoot) && realpath(BASE_PATH . '/public') && realpath($docRoot) === realpath(BASE_PATH . '/public'));

        $baseDir = rtrim(dirname($scriptName), '/\\');
        if (str_ends_with($baseDir, '/public') || str_ends_with($baseDir, '\\public')) {
            $baseDir = substr($baseDir, 0, -7);
        }

        $prefix = $servesFromPublic ? ($baseDir . '/assets/') : ($baseDir . '/public/assets/');

        $url = '/' . ltrim($prefix . ltrim($path, '/'), '/');

        $filePath = BASE_PATH . '/public/assets/' . ltrim($path, '/');
        if (is_file($filePath)) {
            $url .= '?v=' . filemtime($filePath);
        }

        return $url;
    }

    public static function url(string $path = ''): string
    {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $baseDir = rtrim(dirname($scriptName), '/\\');
        if (str_ends_with($baseDir, '/public') || str_ends_with($baseDir, '\\public')) {
            $baseDir = substr($baseDir, 0, -7);
        }

        $target = '/' . ltrim($baseDir . '/' . ltrim($path, '/'), '/');
        return $target === '//' ? '/' : $target;
    }

    /**
     * URL absolue, schéma et domaine compris.
     *
     * url() renvoie un chemin, ce qui suffit à un lien dans une page : le
     * navigateur connaît déjà le domaine. Cela ne suffit pas dès que l'adresse
     * quitte la page — un QR code imprimé sur une facture, un lien dans un
     * courriel ou un SMS. Quatre QR codes de l'ERP encodaient ainsi un chemin
     * relatif : scannés, ils n'ouvraient rien.
     *
     * La base vient de config/app.php, qui respecte APP_URL quand il est défini
     * et la déduit de la requête sinon.
     */
    public static function absoluteUrl(string $path = ''): string
    {
        static $base = null;

        if ($base === null) {
            $config = require BASE_PATH . '/config/app.php';
            $base = rtrim((string) $config['url'], '/');
        }

        return $base . '/' . ltrim($path, '/');
    }

    /**
     * Image de QR code pour une adresse de l'application.
     *
     * Passe systématiquement par une URL absolue : c'est tout l'intérêt d'un QR
     * code que d'être lu par un appareil qui ne sait rien du contexte.
     */
    public static function qrCodeFor(string $path, int $size = 160): string
    {
        return 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size
            . '&data=' . urlencode(self::absoluteUrl($path));
    }

    /**
     * @return array<int, string>
     */
    public static function monthNames(): array
    {
        return [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
        ];
    }
}

class HtmlString
{
    public function __construct(private string $html) {}
    public function toHtml(): string { return $this->html; }
    public function __toString(): string { return $this->html; }
}
