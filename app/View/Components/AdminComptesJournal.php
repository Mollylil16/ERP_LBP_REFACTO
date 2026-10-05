<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;
use App\Repositories\Admin\ComptesAuditRepository as Audit;

/**
 * Le journal des comptes : qui a touché à quel compte, et quoi exactement.
 *
 * L'avant et l'après sont montrés côte à côte. C'est ce qui distingue un
 * journal utile d'un journal décoratif : savoir qu'un rôle a changé ne sert à
 * rien, savoir qu'il est passé de « caissiere, agent_saisie » à « agent_saisie »
 * permet de le rétablir.
 */
final class AdminComptesJournal
{
    private const TONS = [
        'create_user' => 'success',
        'update_user' => 'info',
        'set_roles' => 'warning',
        'set_permissions' => 'warning',
        'activate_user' => 'success',
        'deactivate_user' => 'danger',
        'reset_password' => 'danger',
        'login' => 'neutral',
    ];

    /**
     * @param array<int, array<string, mixed>> $lignes
     * @param array<string, mixed> $filtres
     * @param array<int, array{id: int, name: string}> $comptes
     * @param array<int, array{id: int, name: string}> $acteurs
     */
    public static function page(array $lignes, array $filtres, array $comptes, array $acteurs): string
    {
        $entete = Ui::pageHeader(
            'Journal des comptes',
            'Qui a créé, modifié, désactivé ou réinitialisé un compte — et ce qui a changé exactement.',
            [
                'eyebrow' => 'Administration et sécurité',
                'class' => 'admin-hero',
                'actions' => [
                    Ui::button('Exporter en PDF', [
                        'href' => 'admin/journal/pdf' . self::requete($filtres),
                        'variant' => 'secondary',
                        'target' => '_blank',
                    ]),
                    Ui::button('Exporter en Excel', [
                        'href' => 'admin/journal/excel' . self::requete($filtres),
                        'variant' => 'secondary',
                    ]),
                ],
            ]
        );

        return '<div class="finea-shell"><div class="finea-container">'
            . $entete
            . self::compteurs($lignes)
            . self::filtres($filtres, $comptes, $acteurs)
            . '<section class="finea-section-card">'
            . '<div class="finea-section-heading"><h2 class="finea-section-title">' . View::e(self::titre($filtres)) . '</h2>'
            . '<span>' . View::e(count($lignes) . ' geste(s). Les lignes sont chaînées en SHA-256 : une modification directe en base casse la chaîne et se voit.') . '</span></div>'
            . self::tableau($lignes)
            . '</section></div></div>'
            . self::styles();
    }

    /** @param array<int, array<string, mixed>> $lignes */
    private static function compteurs(array $lignes): string
    {
        $compte = ['set_roles' => 0, 'reset_password' => 0, 'deactivate_user' => 0, 'create_user' => 0];

        foreach ($lignes as $l) {
            $action = (string) $l['action'];
            if (isset($compte[$action])) {
                $compte[$action]++;
            }
        }

        return '<div class="lbp-jrn-kpis">'
            . self::kpi('Comptes créés', (string) $compte['create_user'], 'Sur la période affichée')
            . self::kpi('Rôles changés', (string) $compte['set_roles'], "L'avant est conservé")
            . self::kpi('Mots de passe réinitialisés', (string) $compte['reset_password'], 'Par un administrateur')
            . self::kpi('Comptes désactivés', (string) $compte['deactivate_user'], 'Accès coupés')
            . '</div>';
    }

    private static function kpi(string $libelle, string $valeur, string $detail): string
    {
        return '<div class="lbp-jrn-kpi">'
            . '<span class="lbp-jrn-kpi-libelle">' . View::e($libelle) . '</span>'
            . '<strong>' . View::e($valeur) . '</strong>'
            . '<span class="lbp-jrn-kpi-detail">' . View::e($detail) . '</span>'
            . '</div>';
    }

    /**
     * @param array<string, mixed> $f
     * @param array<int, array{id: int, name: string}> $comptes
     * @param array<int, array{id: int, name: string}> $acteurs
     */
    private static function filtres(array $f, array $comptes, array $acteurs): string
    {
        $actions = [['value' => '', 'label' => 'Tous les gestes']];
        foreach (Audit::ACTIONS as $code => $libelle) {
            $actions[] = ['value' => $code, 'label' => $libelle];
        }

        $listeComptes = [['value' => '', 'label' => 'Tous les comptes']];
        foreach ($comptes as $c) {
            $listeComptes[] = ['value' => (string) $c['id'], 'label' => (string) $c['name']];
        }

        $listeActeurs = [['value' => '', 'label' => 'Tout le monde']];
        foreach ($acteurs as $a) {
            $listeActeurs[] = ['value' => (string) $a['id'], 'label' => (string) $a['name']];
        }

        return '<form method="get" action="' . View::url('admin/journal') . '" class="rh-personnel-filters">'
            . '<div class="lbp-jrn-filtres">'
            . Form::input('du', ['label' => 'Du', 'type' => 'date', 'value' => (string) ($f['du'] ?? ''), 'id' => 'jrn-du'])
            . Form::input('au', ['label' => 'Au', 'type' => 'date', 'value' => (string) ($f['au'] ?? ''), 'id' => 'jrn-au'])
            . Form::select('action', $actions, (string) ($f['action'] ?? ''), ['label' => 'Geste', 'id' => 'jrn-action'])
            . Form::select('cible_id', $listeComptes, (string) ($f['cible_id'] ?? ''), ['label' => 'Compte concerné', 'id' => 'jrn-cible'])
            . Form::select('acteur_id', $listeActeurs, (string) ($f['acteur_id'] ?? ''), ['label' => 'Par qui', 'id' => 'jrn-acteur'])
            . Form::input('q', ['label' => 'Recherche', 'value' => (string) ($f['q'] ?? ''), 'id' => 'jrn-q', 'placeholder' => 'Nom, courriel, rôle'])
            . '</div>'
            . '<div class="rh-personnel-filter-actions">'
            . '<button type="submit" class="rh-filter-btn rh-filter-btn--primary">Filtrer</button>'
            . '<a href="' . View::url('admin/journal') . '" class="rh-filter-btn rh-filter-btn--reset">Réinitialiser</a>'
            . '</div></form>';
    }

    /** @param array<int, array<string, mixed>> $lignes */
    private static function tableau(array $lignes): string
    {
        if ($lignes === []) {
            return Ui::emptyState(
                'Aucun geste sur cette période',
                "Élargissez les dates, ou retirez les filtres. Le journal ne remonte qu'au jour où il a été mis en place."
            );
        }

        $corps = '';
        foreach ($lignes as $l) {
            $action = (string) $l['action'];

            $corps .= '<tr>'
                . '<td class="lbp-jrn-mono">' . View::e(self::horodatage($l['created_at'])) . '</td>'
                . '<td>' . Ui::badge((string) $l['action_libelle'], self::TONS[$action] ?? 'neutral') . '</td>'
                . '<td>' . self::compte($l) . '</td>'
                . '<td><strong>' . View::e((string) ($l['acteur_nom'] ?: 'Système')) . '</strong></td>'
                . '<td class="lbp-jrn-avant">' . self::etat($l['avant_texte'] ?? '') . '</td>'
                . '<td class="lbp-jrn-apres">' . self::etat($l['apres_texte'] ?? '') . '</td>'
                . '<td class="lbp-jrn-mono lbp-jrn-ip">' . View::e((string) ($l['ip_address'] ?? '—')) . '</td>'
                . '</tr>';
        }

        return '<div class="lbp-jrn-enveloppe"><table class="finea-table lbp-jrn-table">'
            . '<thead><tr>'
            . '<th>Date et heure</th><th>Geste</th><th>Compte concerné</th><th>Par qui</th>'
            . '<th>Avant</th><th>Après</th><th>Adresse</th>'
            . '</tr></thead><tbody>' . $corps . '</tbody></table></div>';
    }

    /** @param array<string, mixed> $l */
    private static function compte(array $l): string
    {
        $nom = trim((string) ($l['cible_nom'] ?? ''));
        $id = (int) ($l['entity_id'] ?? 0);

        if ($nom === '') {
            // Le compte a pu être supprimé depuis : son identifiant reste la
            // seule prise pour remonter la piste.
            return '<span class="lbp-jrn-vide">' . View::e('Compte #' . $id) . '</span>';
        }

        return '<a href="' . View::url('admin/users/' . $id) . '"><strong>' . View::e($nom) . '</strong></a>'
            . '<span class="lbp-jrn-sous">' . View::e((string) ($l['cible_email'] ?? '')) . '</span>';
    }

    private static function etat(mixed $texte): string
    {
        $texte = trim((string) $texte);

        return $texte === '' ? '<span class="lbp-jrn-vide">—</span>' : View::e($texte);
    }

    /** @param array<string, mixed> $filtres */
    private static function titre(array $filtres): string
    {
        $du = trim((string) ($filtres['du'] ?? ''));
        $au = trim((string) ($filtres['au'] ?? ''));

        if ($du === '' && $au === '') {
            return 'Tous les gestes enregistrés';
        }

        return 'Gestes du ' . self::jour($du) . ' au ' . self::jour($au);
    }

    private static function jour(string $date): string
    {
        return $date === '' ? '…' : date('d/m/Y', (int) strtotime($date));
    }

    private static function horodatage(mixed $valeur): string
    {
        $valeur = (string) $valeur;

        return $valeur === '' ? '—' : date('d/m/Y à H:i', (int) strtotime($valeur));
    }

    /** @param array<string, mixed> $f */
    private static function requete(array $f): string
    {
        $params = [];
        foreach (['du', 'au', 'action', 'cible_id', 'acteur_id', 'q'] as $champ) {
            $valeur = trim((string) ($f[$champ] ?? ''));
            if ($valeur !== '' && $valeur !== '0') {
                $params[$champ] = $valeur;
            }
        }

        return $params === [] ? '' : '?' . http_build_query($params);
    }

    // ------------------------------------------------------------------
    // Les deux documents
    // ------------------------------------------------------------------

    /**
     * @param array<int, array<string, mixed>> $lignes
     * @param array<string, mixed> $filtres
     */
    public static function exportPdf(array $lignes, array $filtres, string $editePar): string
    {
        $corps = '';
        foreach ($lignes as $l) {
            $corps .= '<tr>'
                . '<td>' . View::e(self::horodatage($l['created_at'])) . '</td>'
                . '<td>' . View::e((string) $l['action_libelle']) . '</td>'
                . '<td>' . View::e((string) ($l['cible_nom'] ?: 'Compte #' . (int) $l['entity_id'])) . '</td>'
                . '<td>' . View::e((string) ($l['acteur_nom'] ?: 'Système')) . '</td>'
                . '<td>' . View::e((string) ($l['avant_texte'] ?? '')) . '</td>'
                . '<td>' . View::e((string) ($l['apres_texte'] ?? '')) . '</td>'
                . '</tr>';
        }

        return '<!doctype html><html lang="fr"><head><meta charset="utf-8">'
            . '<title>Journal des comptes</title>'
            . '<style>'
            . 'body{font-family:Arial,Helvetica,sans-serif;font-size:10px;color:#111;margin:14mm}'
            . 'h1{font-size:16px;margin:0 0 2px}'
            . '.sous{color:#555;margin:0 0 12px;font-size:10px}'
            . 'table{width:100%;border-collapse:collapse}'
            . 'th,td{border:1px solid #bbb;padding:4px 5px;text-align:left;vertical-align:top}'
            . 'th{background:#0f172a;color:#fff;font-size:9px;text-transform:uppercase;letter-spacing:.04em}'
            . 'tr:nth-child(even) td{background:#f6f7f9}'
            . '@media print{body{margin:8mm}thead{display:table-header-group}}'
            . '</style></head><body>'
            . '<h1>Journal des comptes — Administration</h1>'
            . '<p class="sous">' . View::e(self::titre($filtres) . ' · ' . count($lignes) . ' geste(s) · édité le '
                . date('d/m/Y à H:i') . ($editePar === '' ? '' : ' par ' . $editePar)) . '</p>'
            . '<table><thead><tr>'
            . '<th>Date et heure</th><th>Geste</th><th>Compte concerné</th><th>Par qui</th>'
            . '<th>Avant</th><th>Après</th>'
            . '</tr></thead><tbody>' . $corps . '</tbody></table>'
            . '<script>window.onload=function(){window.print();};</script>'
            . '</body></html>';
    }

    /**
     * @param array<int, array<string, mixed>> $lignes
     * @param array<string, mixed> $filtres
     */
    public static function exportExcel(array $lignes, array $filtres): string
    {
        $corps = '';
        foreach ($lignes as $l) {
            $corps .= '<tr>'
                . self::xTexte(self::horodatage($l['created_at']))
                . self::xTexte((string) $l['action_libelle'])
                . self::xTexte((string) $l['action'])
                . self::xTexte((string) ($l['entity_id'] ?? ''))
                . self::xTexte((string) ($l['cible_nom'] ?? ''))
                . self::xTexte((string) ($l['cible_email'] ?? ''))
                . self::xTexte((string) ($l['acteur_nom'] ?? ''))
                . self::xTexte((string) ($l['avant_texte'] ?? ''))
                . self::xTexte((string) ($l['apres_texte'] ?? ''))
                . self::xTexte((string) ($l['ip_address'] ?? ''))
                . '</tr>';
        }

        $entetes = ['Date et heure', 'Geste', 'Code du geste', 'N° de compte', 'Compte concerné',
            'Courriel', 'Par qui', 'Avant', 'Après', 'Adresse'];

        $ligneEntetes = '';
        foreach ($entetes as $titre) {
            $ligneEntetes .= '<th>' . View::e($titre) . '</th>';
        }

        return '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="utf-8">'
            . '<style>th{background:#0f172a;color:#fff}td,th{border:1px solid #999}</style></head><body>'
            . '<p>' . View::e(self::titre($filtres)) . '</p>'
            . '<table><thead><tr>' . $ligneEntetes . '</tr></thead><tbody>' . $corps . '</tbody></table>'
            . '</body></html>';
    }

    private static function xTexte(string $valeur): string
    {
        return '<td style="mso-number-format:\'\\@\'">' . View::e($valeur) . '</td>';
    }

    private static function styles(): string
    {
        return '<style>'
            . '.lbp-jrn-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:14px;margin:18px 0}'
            . '.lbp-jrn-kpi{background:#fff;border:1px solid #e3e6ea;border-radius:14px;padding:16px 18px;display:flex;flex-direction:column;gap:6px}'
            . '.lbp-jrn-kpi strong{font-size:27px;font-weight:700;letter-spacing:-.02em;line-height:1.1}'
            . '.lbp-jrn-kpi-libelle{font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#5b6472}'
            . '.lbp-jrn-kpi-detail{font-size:12px;color:#5b6472}'
            . '.lbp-jrn-filtres{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}'
            . '.lbp-jrn-enveloppe{overflow-x:auto}'
            . '.lbp-jrn-table{width:100%;font-size:13px}'
            . '.lbp-jrn-table th{white-space:normal;line-height:1.25}'
            . '.lbp-jrn-mono{font-family:Consolas,"SF Mono",monospace;font-variant-numeric:tabular-nums;white-space:nowrap}'
            . '.lbp-jrn-sous{display:block;font-size:11px;color:#8b94a1}'
            . '.lbp-jrn-avant{max-width:220px;color:#b42318}'
            . '.lbp-jrn-apres{max-width:220px;color:#027a48;font-weight:600}'
            . '.lbp-jrn-ip{color:#8b94a1;font-size:11px}'
            . '.lbp-jrn-vide{color:#94a3b8}'
            . '</style>';
    }
}
