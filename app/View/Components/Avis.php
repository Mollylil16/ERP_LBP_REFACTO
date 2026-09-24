<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;
use App\Services\Shared\AvisPortail;

/**
 * L'avis de la direction, en haut du portail.
 *
 * Il est posé avant les tuiles, là où le regard tombe en entrant. Il ne se
 * ferme pas : un avis que l'on peut renvoyer d'un clic n'est pas lu, et
 * celui-ci ne dure que quelques jours.
 */
final class Avis
{
    private const ICONE = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>'
        . '<line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>';

    public static function portail(?string $aujourdhui = null): string
    {
        $avis = AvisPortail::actifs($aujourdhui);

        if ($avis === []) {
            return '';
        }

        $html = '';
        foreach ($avis as $a) {
            $paragraphes = '';
            foreach ($a['paragraphes'] as $paragraphe) {
                $paragraphes .= '<p>' . View::e($paragraphe) . '</p>';
            }

            $html .= '<section class="lbp-avis lbp-avis--' . View::e($a['ton']) . '" role="note" aria-label="Avis de la direction">'
                . '<div class="lbp-avis-marque">' . self::ICONE . '<span>Avis de la direction</span></div>'
                . '<h2>' . View::e($a['titre']) . '</h2>'
                . '<div class="lbp-avis-texte">' . $paragraphes . '</div>'
                . '</section>';
        }

        return self::styles() . $html;
    }

    private static function styles(): string
    {
        return '<style>'
            . '.lbp-avis{border-radius:18px;padding:24px 28px;margin:0 0 24px;border:1px solid #fcd9a4;background:linear-gradient(135deg,#fffaf2,#fff4e2);box-shadow:0 12px 28px -18px rgba(180,119,7,.55)}'
            . '.lbp-avis-marque{display:inline-flex;align-items:center;gap:8px;color:#b54708;font-size:.78rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;margin-bottom:10px}'
            . '.lbp-avis h2{margin:0 0 12px;font-size:1.28rem;font-weight:800;color:#7a3d02;line-height:1.3}'
            . '.lbp-avis-texte p{margin:0 0 10px;font-size:1rem;line-height:1.62;color:#4a3419;max-width:92ch}'
            . '.lbp-avis-texte p:last-child{margin-bottom:0;font-weight:600}'
            . '@media (max-width:640px){.lbp-avis{padding:18px 16px;border-radius:14px}.lbp-avis h2{font-size:1.1rem}.lbp-avis-texte p{font-size:.95rem}}'
            . '</style>';
    }
}
