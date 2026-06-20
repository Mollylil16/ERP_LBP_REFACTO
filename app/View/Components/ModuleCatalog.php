<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\ModuleIcon;
use App\Helpers\View;

/**
 * Composants de catalogue de modules.
 *
 * Chaque méthode reçoit des données simples afin de pouvoir être réutilisée
 * dans le portail principal, un dashboard ou une autre vue de l’application.
 */
final class ModuleCatalog
{
    public static function hero(string $userName, int $moduleCount): string
    {
        return '<section class="finea-portal-hero"><div class="finea-hero-main">'
            . '<p class="finea-eyebrow">ERP transit · Portail modules</p><h2>Bonjour '
            . View::e($userName) . ', choisissez votre espace de travail.</h2>'
            . '<p>Une entrée unique, propre et évolutive pour gérer les opérations transit sans mélanger le portail avec les tableaux de bord métier.</p>'
            . '</div><div class="finea-hero-side"><span class="finea-version-chip">LBP ERP</span><strong>'
            . $moduleCount . ' modules</strong><small>Transit, douane, tracking, CRM, tickets, site, RH et finance</small>'
            . '</div></section>';
    }

    /** @param array<int,array{value:string,label:string}> $options */
    public static function moduleFilter(array $options, int $moduleCount): string
    {
        $selector = Form::selectSearch('portal_modules', $options, [], [
            'label' => 'Rechercher et sélectionner plusieurs modules',
            'multiple' => true,
            'id' => 'portalModuleSelect',
            'placeholder' => 'Saisissez un nom, un code ou sélectionnez plusieurs modules…',
            'fieldClass' => 'portal-module-filter-field',
            'data-portal-module-filter' => '1',
        ]);

        return '<section class="finea-module-toolbar portal-module-toolbar" aria-label="Recherche de modules">'
            . '<div class="portal-module-filter">' . $selector . '</div>'
            . '<div class="portal-module-filter-meta"><span id="moduleSearchCount">'
            . $moduleCount . ' modules disponibles</span>'
            . Ui::button('Tout afficher', [
                'variant' => 'plain',
                'type' => 'button',
                'id' => 'moduleFilterReset',
                'class' => 'portal-filter-reset',
                'hidden' => true,
            ])
            . '</div></section>';
    }

    /** @param array<int,array<string,mixed>> $modules */
    public static function moduleGrid(array $modules): string
    {
        return '<section class="finea-module-grid" id="moduleGrid" aria-label="Modules ERP disponibles">'
            . implode('', array_map(self::moduleCard(...), $modules))
            . '</section><p class="finea-empty-state" id="moduleEmptyState" hidden>'
            . 'Aucun module ne correspond à votre sélection.</p>';
    }

    /** @param array<string,mixed> $module */
    public static function moduleCard(array $module): string
    {
        $maintenance = (bool) ($module['is_maintenance'] ?? false);
        $label = (string) ($module['label'] ?? 'Module');
        $class = Html::classes([
            'finea-module-card',
            (string) ($module['class'] ?? ''),
            'is-maintenance' => $maintenance,
        ]);
        $content = '<span class="finea-module-glow" aria-hidden="true"></span>'
            . '<span class="finea-module-topline"><span class="finea-module-icon">'
            . ModuleIcon::svg((string) ($module['icon'] ?? 'admin'))
            . '</span><span class="finea-module-code">' . View::e((string) ($module['code'] ?? '')) . '</span></span>'
            . '<span class="finea-module-title">' . View::e($label) . '</span>'
            . '<span class="finea-module-description">' . View::e((string) ($module['description'] ?? '')) . '</span>'
            . ($maintenance ? '<span class="finea-module-maintenance-reason">'
                . View::e((string) ($module['maintenance_reason'] ?? '')) . '</span>' : '')
            . '<span class="finea-module-footer"><span class="finea-module-status">'
            . View::e((string) ($module['status'] ?? 'Disponible'))
            . '</span><span class="finea-module-open">' . ($maintenance ? 'Indisponible' : 'Ouvrir') . '</span></span>';

        $body = $maintenance
            ? '<div class="finea-module-link" aria-disabled="true">' . $content . '</div>'
            : '<a class="finea-module-link" href="' . View::url(ltrim((string) ($module['url'] ?? ''), '/'))
                . '" aria-label="Ouvrir le module ' . View::e($label) . '">' . $content . '</a>';

        return '<article class="' . View::e($class) . '" data-module-card data-module-key="'
            . View::e((string) ($module['key'] ?? '')) . '">' . $body . '</article>';
    }

    public static function footerNote(
        string $eyebrow = 'Navigation centralisée',
        string $message = 'Le portail reste l’accueil privé, chaque module conserve son propre tableau de bord.',
        string $actionLabel = 'Déconnexion',
        string $actionHref = 'logout',
    ): string {
        return '<section class="finea-portal-note"><div><p class="finea-eyebrow">'
            . View::e($eyebrow) . '</p><h3>' . View::e($message) . '</h3></div>'
            . Ui::button($actionLabel, ['href' => $actionHref, 'variant' => 'secondary']) . '</section>';
    }
}
