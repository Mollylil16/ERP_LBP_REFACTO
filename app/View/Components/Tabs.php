<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;

final class Tabs
{
    /**
     * @param array<int,array{key:string,label:string,href:string,description?:string,count?:int}> $items
     * @param array<string,mixed> $attrs
     */
    public static function render(array $items, string $activeKey, array $attrs = []): string
    {
        $class = Html::classes(['finea-tabs', (string) ($attrs['class'] ?? '')]);
        $label = (string) ($attrs['aria-label'] ?? 'Navigation par onglets');
        $baseItemClass = (bool) ($attrs['base_item_class'] ?? true) ? 'finea-tab' : '';
        $itemClass = (string) ($attrs['item_class'] ?? '');
        $wrapLabel = (bool) ($attrs['wrap_label'] ?? true);
        $html = '<nav class="' . View::e($class) . '" aria-label="' . View::e($label) . '">';

        foreach ($items as $item) {
            $isActive = $item['key'] === $activeKey;
            $tabClass = Html::classes([$baseItemClass, $itemClass, 'is-active' => $isActive]);
            $description = (string) ($item['description'] ?? '');
            $count = isset($item['count'])
                ? '<span class="finea-tab-count">' . (int) $item['count'] . '</span>'
                : '';

            $labelHtml = '<strong>' . View::e($item['label']) . '</strong>'
                . ($description !== '' ? '<small>' . View::e($description) . '</small>' : '');

            $html .= '<a class="' . View::e($tabClass) . '" href="' . View::url(ltrim($item['href'], '/')) . '"'
                . ($isActive ? ' aria-current="page"' : '') . '>'
                . ($wrapLabel ? '<span>' . $labelHtml . '</span>' : $labelHtml)
                . $count . '</a>';
        }

        return $html . '</nav>';
    }

    /**
     * Onglets dont le contenu est déjà dans la page, basculés sans rechargement.
     *
     * À utiliser quand les panneaux tiennent dans une seule page et n'ont pas
     * d'URL propre : un guide, une fiche à plusieurs volets. Pour des écrans
     * distincts qui méritent chacun leur adresse, c'est render() qu'il faut.
     *
     * Le premier panneau est visible avant que le script ne s'exécute : si le
     * JavaScript échoue, la page reste lisible au lieu de s'afficher vide.
     *
     * @param array<int, array{cle:string, label:string, contenu:string}> $onglets
     */
    public static function panneaux(array $onglets, string $prefixe = 'tab'): string
    {
        if ($onglets === []) {
            return '';
        }

        $barre = '<div class="finea-inline-tabs" role="tablist">';
        $corps = '';

        foreach (array_values($onglets) as $index => $onglet) {
            $id = $prefixe . '-' . preg_replace('/[^a-z0-9_-]/i', '', $onglet['cle']);
            $actif = $index === 0;

            $barre .= '<button type="button" role="tab"'
                . ' class="finea-inline-tab' . ($actif ? ' is-active' : '') . '"'
                . ' aria-selected="' . ($actif ? 'true' : 'false') . '"'
                . ' aria-controls="' . View::e($id) . '"'
                . ' data-finea-tab="' . View::e($id) . '">'
                . View::e($onglet['label'])
                . '</button>';

            $corps .= '<section role="tabpanel" id="' . View::e($id) . '"'
                . ' class="finea-inline-pane' . ($actif ? ' is-active' : '') . '"'
                . ($actif ? '' : ' hidden')
                . '>' . $onglet['contenu'] . '</section>';
        }

        return $barre . '</div>' . $corps . self::stylePanneaux() . self::scriptPanneaux();
    }

    private static function stylePanneaux(): string
    {
        return '<style>'
            . '.finea-inline-tabs{display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:1.5rem;'
            . 'border-bottom:2px solid #e2e8f0;padding-bottom:.5rem;}'
            . '.finea-inline-tab{background:none;border:none;padding:.75rem 1.25rem;font-weight:600;'
            . 'color:#64748b;cursor:pointer;border-radius:6px;font-size:.95rem;transition:background .2s,color .2s;}'
            . '.finea-inline-tab:hover{background:#f1f5f9;color:#1e293b;}'
            . '.finea-inline-tab.is-active{background:#1e3a5f;color:#fff;}'
            . '.finea-inline-tab:focus-visible{outline:2px solid #2563eb;outline-offset:2px;}'
            . '@media (prefers-reduced-motion:reduce){.finea-inline-tab{transition:none;}}'
            . '</style>';
    }

    private static function scriptPanneaux(): string
    {
        // Délégation sur le document : plusieurs jeux d'onglets peuvent coexister
        // sur une même page, et le script n'est alors branché qu'une seule fois.
        return '<script>'
            . '(function(){'
            . 'if(window.__fineaTabsPanneaux)return;window.__fineaTabsPanneaux=true;'
            . 'document.addEventListener("click",function(e){'
            . 'var b=e.target.closest("[data-finea-tab]");if(!b)return;'
            . 'var barre=b.closest(".finea-inline-tabs");if(!barre)return;'
            . 'barre.querySelectorAll("[data-finea-tab]").forEach(function(t){'
            . 't.classList.remove("is-active");t.setAttribute("aria-selected","false");'
            . 'var p=document.getElementById(t.dataset.fineaTab);'
            . 'if(p){p.classList.remove("is-active");p.hidden=true;}});'
            . 'b.classList.add("is-active");b.setAttribute("aria-selected","true");'
            . 'var cible=document.getElementById(b.dataset.fineaTab);'
            . 'if(cible){cible.classList.add("is-active");cible.hidden=false;}'
            . '});'
            . '})();'
            . '</script>';
    }
}
