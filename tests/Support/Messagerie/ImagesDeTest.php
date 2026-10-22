<?php

declare(strict_types=1);

namespace Tests\Support\Messagerie;

use GdImage;

/**
 * Images fabriquées pour éprouver le traitement des photos : une vraie
 * photo de téléphone (dégradés, détails fins, grain de capteur) avec son
 * bloc EXIF — orientation et position GPS —, et un PNG transparent.
 */
final class ImagesDeTest
{
    /**
     * Une « photo de téléphone » en JPEG : ciel en dégradé, sol texturé,
     * grain de capteur, et un bloc EXIF qui dit « tournée de 90° » et porte
     * une latitude.
     */
    public static function photoDeTelephone(int $largeur, int $hauteur, int $qualite = 92, int $orientation = 6): string
    {
        $image = imagecreatetruecolor($largeur, $hauteur);

        // Dégradé de ciel puis de sol, une ligne à la fois.
        for ($y = 0; $y < $hauteur; $y++) {
            $t = $y / max(1, $hauteur - 1);
            $couleur = $t < 0.55
                ? imagecolorallocate($image, (int) (90 + 80 * $t), (int) (140 + 70 * $t), 230)
                : imagecolorallocate($image, (int) (120 - 40 * $t), (int) (100 + 30 * $t), (int) (50 + 10 * $t));
            imageline($image, 0, $y, $largeur - 1, $y, $couleur);
        }

        // Des « objets » : quelques centaines de formes de toutes tailles.
        mt_srand(20261022);
        for ($i = 0; $i < 400; $i++) {
            $c = imagecolorallocate($image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
            $x = mt_rand(0, $largeur);
            $y = mt_rand((int) ($hauteur * 0.4), $hauteur);
            $r = mt_rand(4, (int) max(8, $largeur / 25));
            imagefilledellipse($image, $x, $y, $r, (int) ($r * 0.7), $c);
        }

        // Grain de capteur : une tuile de bruit fin, posée en mosaïque et
        // fondue sur l'image. Le JPEG code chaque bloc 8 x 8 à part : la
        // mosaïque pèse autant qu'un bruit tiré pixel par pixel.
        $tuile = imagecreatetruecolor(128, 128);
        imagealphablending($tuile, false);
        imagesavealpha($tuile, true);
        for ($ty = 0; $ty < 128; $ty++) {
            for ($tx = 0; $tx < 128; $tx++) {
                $v = mt_rand(0, 255);
                imagesetpixel($tuile, $tx, $ty, imagecolorallocatealpha($tuile, $v, $v, $v, 100));
            }
        }
        imagealphablending($image, true);
        for ($y = 0; $y < $hauteur; $y += 128) {
            for ($x = 0; $x < $largeur; $x += 128) {
                imagecopy($image, $tuile, $x, $y, 0, 0, 128, 128);
            }
        }

        ob_start();
        imagejpeg($image, null, $qualite);
        $jpeg = (string) ob_get_clean();

        return self::avecExif($jpeg, $orientation);
    }

    /** PNG 300 x 200, fond transparent, rectangle rouge opaque au centre. */
    public static function pngTransparent(): string
    {
        $image = imagecreatetruecolor(300, 200);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagefilledrectangle($image, 100, 50, 200, 150, imagecolorallocatealpha($image, 220, 20, 20, 0));

        return self::png($image);
    }

    /** PNG opaque, comme une capture d'écran. */
    public static function pngOpaque(int $largeur = 640, int $hauteur = 480): string
    {
        $image = imagecreatetruecolor($largeur, $hauteur);
        imagefill($image, 0, 0, imagecolorallocate($image, 240, 240, 240));
        imagefilledrectangle($image, 20, 20, $largeur - 20, 60, imagecolorallocate($image, 15, 118, 110));

        return self::png($image);
    }

    /**
     * Insère un bloc APP1 « Exif » après l'en-tête JFIF : IFD0 avec
     * Orientation et le pointeur GPS, IFD GPS avec GPSLatitudeRef « N ».
     */
    public static function avecExif(string $jpeg, int $orientation): string
    {
        $tiff = "II*\x00" . pack('V', 8);
        // IFD0 : 2 entrées, puis l'IFD suivant (aucun).
        $gpsOffset = 8 + 2 + 2 * 12 + 4;
        $tiff .= pack('v', 2)
            . pack('vvV', 0x0112, 3, 1) . pack('vv', $orientation, 0)
            . pack('vvV', 0x8825, 4, 1) . pack('V', $gpsOffset)
            . pack('V', 0);
        // IFD GPS : GPSLatitudeRef = « N ».
        $tiff .= pack('v', 1)
            . pack('vvV', 0x0001, 2, 2) . "N\x00\x00\x00"
            . pack('V', 0);

        $charge = "Exif\x00\x00" . $tiff;
        $app1 = "\xFF\xE1" . pack('n', strlen($charge) + 2) . $charge;

        // Après SOI, et après le JFIF s'il est là.
        $position = 2;
        if (substr($jpeg, 2, 2) === "\xFF\xE0") {
            $position = 4 + unpack('n', substr($jpeg, 4, 2))[1];
        }

        return substr($jpeg, 0, $position) . $app1 . substr($jpeg, $position);
    }

    private static function png(GdImage $image): string
    {
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
