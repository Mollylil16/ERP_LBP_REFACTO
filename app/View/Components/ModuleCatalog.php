<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\ModuleIcon;
use App\Helpers\View;

final class ModuleCatalog
{
    public static function hero(string $userName, int $moduleCount): string
    {
        return '<style>
        .portal-modern-hero {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            border-radius: 20px;
            padding: 38px 42px;
            color: #ffffff;
            margin-bottom: 28px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 24px;
        }
        .portal-modern-hero__glow {
            position: absolute;
            top: -60px;
            right: -60px;
            width: 250px;
            height: 250px;
            background: radial-gradient(circle, rgba(37, 99, 235, 0.35) 0%, rgba(0,0,0,0) 70%);
            border-radius: 50%;
            pointer-events: none;
        }
        .portal-modern-hero__badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(37, 99, 235, 0.2);
            color: #60a5fa;
            border: 1px solid rgba(96, 165, 250, 0.3);
            padding: 5px 16px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 12px;
        }
        .portal-modern-hero__title {
            font-size: 2.1rem;
            font-weight: 800;
            margin: 0 0 8px 0;
            letter-spacing: -0.5px;
            color: #ffffff;
        }
        .portal-modern-hero__text {
            font-size: 1.02rem;
            color: #94a3b8;
            max-width: 620px;
            margin: 0;
            line-height: 1.6;
        }
        .portal-modern-hero__chip {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 16px;
            padding: 24px 30px;
            text-align: center;
            min-width: 200px;
        }
        .portal-modern-hero__count {
            font-size: 2.4rem;
            font-weight: 800;
            color: #ffffff;
            display: block;
            line-height: 1;
            margin-bottom: 4px;
        }
        .portal-modern-hero__sub {
            font-size: 0.85rem;
            color: #94a3b8;
            font-weight: 600;
        }

        /* Improved Module Grid */
        .finea-module-grid {
            display: grid !important;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)) !important;
            gap: 22px !important;
        }
        .finea-module-card {
            min-height: 200px !important;
            border-radius: 16px !important;
            border: 1px solid #e2e8f0 !important;
            box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.05) !important;
            transition: all 0.25s ease !important;
        }
        .finea-module-card:hover {
            transform: translateY(-5px) !important;
            box-shadow: 0 20px 35px -10px rgba(15, 23, 42, 0.15) !important;
            border-color: #2563eb !important;
        }
        .finea-module-link {
            padding: 24px !important;
            display: flex !important;
            flex-direction: column !important;
            justify-content: space-between !important;
        }
        .finea-module-icon {
            width: 48px !important;
            height: 48px !important;
            border-radius: 14px !important;
            box-shadow: 0 8px 18px rgba(0,0,0,0.15) !important;
        }
        .finea-module-code {
            font-size: 0.78rem !important;
            font-weight: 800 !important;
            padding: 4px 10px !important;
            border-radius: 20px !important;
            background: #f1f5f9 !important;
            color: #1e293b !important;
        }
        .finea-module-title {
            font-size: 1.15rem !important;
            font-weight: 800 !important;
            color: #0f172a !important;
            margin-top: 14px !important;
            margin-bottom: 6px !important;
        }
        .finea-module-description {
            font-size: 0.88rem !important;
            color: #64748b !important;
            line-height: 1.45 !important;
        }
        .finea-module-footer {
            margin-top: 18px !important;
            padding-top: 14px !important;
            border-top: 1px solid #f1f5f9 !important;
            display: flex !items: center !justify-content: space-between !important;
        }
        .finea-module-open {
            background: #1d2b57 !important;
            color: #ffffff !important;
            padding: 6px 14px !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
            font-size: 0.82rem !important;
        }
        .finea-module-card:hover .finea-module-open {
            background: #2563eb !important;
        }
        </style>

        <section class="portal-modern-hero">
            <div class="portal-modern-hero__glow"></div>
            <div>
                <span class="portal-modern-hero__badge">
                    <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" stroke-width="2.5" fill="none"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                    LBP ERP Transit • Portail Central
                </span>
                <h1 class="portal-modern-hero__title">Bonjour ' . View::e($userName) . ', choisissez votre espace.</h1>
                <p class="portal-modern-hero__text">Sélectionnez le module de gestion métier pour accéder à vos tableaux de bord, outils de transit, suivi colis et comptabilité.</p>
            </div>
            <div class="portal-modern-hero__chip">
                <span class="portal-modern-hero__count">' . $moduleCount . '</span>
                <span class="portal-modern-hero__sub">Modules de gestion disponibles</span>
            </div>
        </section>';
    }

    /** @param array<int,array{value:string,label:string}> $options */
    public static function moduleFilter(array $options, int $moduleCount): string
    {
        $selector = Form::selectSearch('portal_modules', $options, [], [
            'label' => 'Rechercher et filtrer les modules métier',
            'multiple' => true,
            'id' => 'portalModuleSelect',
            'placeholder' => 'Rechercher un module par nom ou code (ex: Colisage, CRM, RH, Finance)...',
            'fieldClass' => 'portal-module-filter-field',
            'data-portal-module-filter' => '1',
        ]);

        return '<section class="finea-module-toolbar portal-module-toolbar" aria-label="Recherche de modules" style="background: #ffffff; padding: 20px; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.05); margin-bottom: 26px;">'
            . '<div class="portal-module-filter">' . $selector . '</div>'
            . '<div class="portal-module-filter-meta" style="margin-top: 12px; display: flex; align-items: center; justify-content: space-between;"><span id="moduleSearchCount" style="font-weight: 700; color: #1d2b57; font-size: 0.95rem;">'
            . $moduleCount . ' modules accessibles</span>'
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
            . '</section><p class="finea-empty-state" id="moduleEmptyState" hidden style="background: #ffffff; padding: 40px; border-radius: 16px; border: 1px solid #e2e8f0; text-align: center; color: #64748b; font-size: 1rem;">'
            . 'Aucun module ne correspond à votre recherche.</p>';
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
            . '<span class="finea-module-footer"><span class="finea-module-status" style="font-size: 0.8rem; font-weight: 700; color: #059669;">'
            . View::e((string) ($module['status'] ?? 'Disponible'))
            . '</span><span class="finea-module-open">' . ($maintenance ? 'Indisponible' : 'Ouvrir ➔') . '</span></span>';

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
        return '<section class="finea-portal-note" style="margin-top: 35px; background: #ffffff; padding: 24px 30px; border-radius: 16px; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;"><div><p class="finea-eyebrow" style="color: #64748b; font-size: 0.78rem; text-transform: uppercase; font-weight: 700; margin-bottom: 4px;">'
            . View::e($eyebrow) . '</p><h3 style="font-size: 1rem; font-weight: 700; color: #0f172a; margin: 0;">' . View::e($message) . '</h3></div>'
            . Ui::button($actionLabel, ['href' => $actionHref, 'variant' => 'secondary', 'style' => 'background: #f1f5f9; color: #0f172a; font-weight: 700; border-radius: 10px; padding: 10px 20px;']) . '</section>';
    }
}
