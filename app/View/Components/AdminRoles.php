<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;

/**
 * Qui porte quel rôle, et ce qui cloche.
 *
 * Répondre à « qui est comptable ? » demandait d'ouvrir les quarante fiches.
 * Personne ne le faisait, et le 30/09/2026 les rôles du personnel ont été
 * réécrits sans que l'anomalie se voie avant les plaintes.
 */
final class AdminRoles
{
    /**
     * @param array<int, array<string, mixed>> $roles
     * @param array<int, array<string, mixed>> $sansRole
     * @param array<int, array<string, mixed>> $dormants
     * @param array<int, array<string, mixed>> $administrateurs
     */
    public static function page(array $roles, array $sansRole, array $dormants, array $administrateurs): string
    {
        return '<div class="finea-shell"><div class="finea-container">'
            . Ui::pageHeader(
                'Rôles et anomalies',
                'Qui porte quel rôle, et ce que le logiciel sait repérer de travers.',
                ['eyebrow' => 'Administration et sécurité', 'class' => 'admin-hero']
            )
            . self::alertes($roles, $sansRole, $dormants, $administrateurs)
            . self::tableRoles($roles)
            . '</div></div>'
            . self::styles();
    }

    /**
     * @param array<int, array<string, mixed>> $roles
     * @param array<int, array<string, mixed>> $sansRole
     * @param array<int, array<string, mixed>> $dormants
     * @param array<int, array<string, mixed>> $administrateurs
     */
    private static function alertes(array $roles, array $sansRole, array $dormants, array $administrateurs): string
    {
        $horsCatalogue = array_values(array_filter($roles, static fn (array $r): bool => (bool) $r['hors_catalogue']));
        $sansPorteur = array_values(array_filter(
            $roles,
            static fn (array $r): bool => !$r['hors_catalogue'] && $r['porteurs'] === []
        ));

        return '<div class="lbp-rol-alertes">'
            . self::alerte(
                'Comptes sans aucun rôle',
                count($sansRole),
                $sansRole === []
                    ? 'Chaque compte actif porte au moins un rôle.'
                    : 'Ils peuvent se connecter et ne rien faire : ' . self::noms($sansRole),
                $sansRole !== []
            )
            . self::alerte(
                'Rôles hors catalogue',
                count($horsCatalogue),
                $horsCatalogue === []
                    ? 'Tous les rôles portés figurent au catalogue.'
                    : 'Portés en base mais absents du catalogue : ' . self::codes($horsCatalogue),
                $horsCatalogue !== []
            )
            . self::alerte(
                'Comptes dormants',
                count($dormants),
                $dormants === []
                    ? 'Tous les comptes actifs ont servi récemment.'
                    : 'Aucune connexion depuis 60 jours ou jamais : ' . self::noms($dormants),
                $dormants !== []
            )
            . self::alerte(
                'Administrateurs',
                count($administrateurs),
                'Ils contournent les validations à deux mains : ' . self::noms($administrateurs),
                count($administrateurs) > 2
            )
            . self::alerte(
                'Rôles que personne ne porte',
                count($sansPorteur),
                $sansPorteur === []
                    ? 'Chaque rôle du catalogue est attribué.'
                    : self::codes($sansPorteur),
                false
            )
            . '</div>';
    }

    private static function alerte(string $titre, int $nombre, string $detail, bool $urgent): string
    {
        return '<div class="lbp-rol-alerte' . ($urgent ? ' is-urgent' : '') . '">'
            . '<span class="lbp-rol-alerte-titre">' . View::e($titre) . '</span>'
            . '<strong>' . $nombre . '</strong>'
            . '<span class="lbp-rol-alerte-detail">' . View::e(self::court($detail, 160)) . '</span>'
            . '</div>';
    }

    /** @param array<int, array<string, mixed>> $gens */
    private static function noms(array $gens): string
    {
        $noms = array_map(static fn (array $g): string => (string) ($g['nom'] ?? ''), $gens);
        $noms = array_values(array_filter($noms));

        return $noms === [] ? '—' : implode(', ', $noms);
    }

    /** @param array<int, array<string, mixed>> $roles */
    private static function codes(array $roles): string
    {
        return implode(', ', array_map(static fn (array $r): string => (string) $r['code'], $roles));
    }

    /** @param array<int, array<string, mixed>> $roles */
    private static function tableRoles(array $roles): string
    {
        $corps = '';

        foreach ($roles as $role) {
            $porteurs = $role['porteurs'];
            $nombre = count($porteurs);

            $liste = '';
            foreach ($porteurs as $p) {
                $inactif = (string) $p['statut'] !== 'active';
                $liste .= '<a class="lbp-rol-porteur' . ($inactif ? ' is-inactif' : '') . '"'
                    . ' href="' . View::url('admin/users/' . (int) $p['id']) . '"'
                    . ($inactif ? ' title="Compte inactif"' : '') . '>'
                    . View::e((string) $p['nom']) . '</a>';
            }

            if ($liste === '') {
                $liste = '<span class="lbp-rol-vide">Personne</span>';
            }

            $corps .= '<tr' . ($role['hors_catalogue'] ? ' class="is-hors-catalogue"' : '') . '>'
                . '<td><strong>' . View::e((string) $role['libelle']) . '</strong>'
                . '<span class="lbp-rol-code">' . View::e((string) $role['code']) . '</span></td>'
                . '<td class="lbp-rol-nombre">' . $nombre . '</td>'
                . '<td class="lbp-rol-porteurs">' . $liste . '</td>'
                . '<td>' . ($role['hors_catalogue']
                    ? Ui::badge('Hors catalogue', 'danger')
                    : ($nombre === 0 ? Ui::badge('Non attribué', 'neutral') : Ui::badge('Attribué', 'success')))
                . '</td>'
                . '</tr>';
        }

        return '<section class="finea-section-card">'
            . '<div class="finea-section-heading"><h2 class="finea-section-title">Le catalogue des rôles</h2>'
            . '<span>' . View::e(count($roles) . ' rôles. Cliquez sur un nom pour ouvrir sa fiche.') . '</span></div>'
            . '<div class="lbp-rol-enveloppe"><table class="finea-table lbp-rol-table">'
            . '<thead><tr><th>Rôle</th><th class="lbp-rol-nombre">Combien</th><th>Qui le porte</th><th>État</th></tr></thead>'
            . '<tbody>' . $corps . '</tbody></table></div>'
            . '</section>';
    }

    private static function court(string $texte, int $max): string
    {
        return mb_strlen($texte) > $max ? mb_substr($texte, 0, $max - 1) . '…' : $texte;
    }

    private static function styles(): string
    {
        return '<style>'
            . '.lbp-rol-alertes{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:14px;margin:18px 0 24px}'
            . '.lbp-rol-alerte{background:#fff;border:1px solid #e3e6ea;border-radius:14px;padding:16px 18px;display:flex;flex-direction:column;gap:5px}'
            . '.lbp-rol-alerte.is-urgent{border-color:#fecdca;background:#fef6f5}'
            . '.lbp-rol-alerte strong{font-size:27px;font-weight:700;letter-spacing:-.02em;line-height:1.1}'
            . '.lbp-rol-alerte.is-urgent strong{color:#b42318}'
            . '.lbp-rol-alerte-titre{font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#5b6472}'
            . '.lbp-rol-alerte-detail{font-size:12px;color:#5b6472;line-height:1.45}'
            . '.lbp-rol-enveloppe{overflow-x:auto}'
            . '.lbp-rol-table{width:100%;font-size:13px}'
            . '.lbp-rol-table tr.is-hors-catalogue td{background:#fef6f5}'
            . '.lbp-rol-code{display:block;font-size:11px;color:#8b94a1;font-family:Consolas,"SF Mono",monospace}'
            . '.lbp-rol-nombre{text-align:right;font-variant-numeric:tabular-nums;font-weight:700;width:90px}'
            . '.lbp-rol-porteurs{display:flex;flex-wrap:wrap;gap:6px;padding-top:10px;padding-bottom:10px}'
            . '.lbp-rol-porteur{display:inline-flex;align-items:center;padding:4px 10px;border:1px solid #e3e6ea;border-radius:999px;'
            . 'background:#f8fafc;font-size:12px;font-weight:600;color:#0f172a;text-decoration:none}'
            . '.lbp-rol-porteur:hover{border-color:#cbd5e1;background:#fff}'
            . '.lbp-rol-porteur.is-inactif{color:#94a3b8;text-decoration:line-through}'
            . '.lbp-rol-vide{color:#94a3b8;font-size:12px}'
            . '</style>';
    }
}
