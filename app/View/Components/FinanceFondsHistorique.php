<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;

/**
 * Le journal des décisions prises sur les demandes de fonds.
 *
 * Chaque validation, chaque rejet, chaque décaissement laisse déjà sa trace en
 * base — avec son auteur, son horodatage et son motif. Mais cette trace ne se
 * lisait que demande par demande : pour savoir qui avait rejeté quoi en
 * septembre, il fallait ouvrir les demandes une à une.
 *
 * Cet écran la donne d'un bloc, filtrable, et la sort en PDF ou en tableur —
 * c'est avec ces deux documents que la direction répond à une contestation.
 */
final class FinanceFondsHistorique
{
    /** Les actions tracées, et le mot que la direction emploie pour chacune. */
    public const ACTIONS = [
        'CREATION' => 'Demande créée',
        'MODIFICATION' => 'Demande modifiée',
        'VALIDATION' => 'Validée',
        'REJET' => 'Rejetée',
        'DECAISSEMENT' => 'Décaissée',
        'IMPUTATION' => 'Imputée',
    ];

    private const TONS = [
        'CREATION' => 'neutral',
        'MODIFICATION' => 'info',
        'VALIDATION' => 'success',
        'REJET' => 'danger',
        'DECAISSEMENT' => 'primary',
        'IMPUTATION' => 'warning',
    ];

    /**
     * @param array<int, array<string, mixed>> $lignes
     * @param array<string, mixed> $filtres
     * @param array<int, array{id: int, name: string}> $agences
     * @param array<int, array{id: int, name: string}> $auteurs
     */
    public static function page(array $lignes, array $filtres, array $agences, array $auteurs): string
    {
        $entete = Ui::pageHeader(
            'Historique des décisions',
            'Qui a validé, qui a rejeté, quand et pourquoi. Chaque ligne est une décision prise sur une demande de fonds.',
            [
                'eyebrow' => 'Gestion des fonds',
                'class' => 'rh-hero-white',
                'actions' => [
                    Ui::button('Exporter en PDF', [
                        'href' => 'finance/fonds/historique/pdf' . self::requete($filtres),
                        'variant' => 'primary',
                        'target' => '_blank',
                    ]),
                    Ui::button('Exporter en Excel', [
                        'href' => 'finance/fonds/historique/excel' . self::requete($filtres),
                        'variant' => 'secondary',
                    ]),
                ],
            ]
        );

        return '<div class="finea-shell"><div class="finea-container">'
            . $entete
            . self::compteurs($lignes)
            . self::filtres($filtres, $agences, $auteurs)
            . '<section class="finea-section-card">'
            . '<div class="finea-section-heading"><h2 class="finea-section-title">' . View::e(self::titre($filtres)) . '</h2>'
            . '<span>' . View::e(count($lignes) . ' décision(s). Les 2 000 plus récentes au maximum : resserrez les dates pour voir au-delà.') . '</span></div>'
            . self::tableau($lignes)
            . '</section></div></div>'
            . self::styles();
    }

    /** @param array<int, array<string, mixed>> $lignes */
    private static function compteurs(array $lignes): string
    {
        $compte = ['VALIDATION' => 0, 'REJET' => 0, 'DECAISSEMENT' => 0];
        $montantValide = 0.0;

        foreach ($lignes as $l) {
            $action = (string) $l['action'];
            if (isset($compte[$action])) {
                $compte[$action]++;
            }
            if ($action === 'VALIDATION') {
                $montantValide += (float) ($l['montant'] ?? 0);
            }
        }

        return '<div class="lbp-hist-kpis">'
            . self::kpi('Validées', (string) $compte['VALIDATION'], number_format($montantValide, 0, ',', ' ') . ' F autorisés')
            . self::kpi('Rejetées', (string) $compte['REJET'], 'Avec leur motif')
            . self::kpi('Décaissées', (string) $compte['DECAISSEMENT'], 'Argent sorti de la caisse')
            . '</div>';
    }

    private static function kpi(string $libelle, string $valeur, string $detail): string
    {
        return '<div class="lbp-hist-kpi">'
            . '<span class="lbp-hist-kpi-libelle">' . View::e($libelle) . '</span>'
            . '<strong>' . View::e($valeur) . '</strong>'
            . '<span class="lbp-hist-kpi-detail">' . View::e($detail) . '</span>'
            . '</div>';
    }

    /**
     * @param array<string, mixed> $f
     * @param array<int, array{id: int, name: string}> $agences
     * @param array<int, array{id: int, name: string}> $auteurs
     */
    private static function filtres(array $f, array $agences, array $auteurs): string
    {
        $actions = [['value' => '', 'label' => 'Toutes les décisions']];
        foreach (self::ACTIONS as $code => $libelle) {
            $actions[] = ['value' => $code, 'label' => $libelle];
        }

        $listeAgences = [['value' => '', 'label' => 'Toutes les agences']];
        foreach ($agences as $a) {
            $listeAgences[] = ['value' => (string) $a['id'], 'label' => (string) $a['name']];
        }

        $listeAuteurs = [['value' => '', 'label' => 'Tout le monde']];
        foreach ($auteurs as $a) {
            $listeAuteurs[] = ['value' => (string) $a['id'], 'label' => (string) $a['name']];
        }

        return '<form method="get" action="' . View::url('finance/fonds/historique') . '" class="rh-personnel-filters">'
            . '<div class="lbp-hist-filtres">'
            . Form::input('du', ['label' => 'Du', 'type' => 'date', 'value' => (string) ($f['du'] ?? ''), 'id' => 'hist-du'])
            . Form::input('au', ['label' => 'Au', 'type' => 'date', 'value' => (string) ($f['au'] ?? ''), 'id' => 'hist-au'])
            . Form::select('action', $actions, (string) ($f['action'] ?? ''), ['label' => 'Décision', 'id' => 'hist-action'])
            . Form::select('agence_id', $listeAgences, (string) ($f['agence_id'] ?? ''), ['label' => 'Agence', 'id' => 'hist-agence'])
            . Form::select('user_id', $listeAuteurs, (string) ($f['user_id'] ?? ''), ['label' => 'Par qui', 'id' => 'hist-auteur'])
            . Form::input('q', ['label' => 'Recherche', 'value' => (string) ($f['q'] ?? ''), 'id' => 'hist-q', 'placeholder' => 'N° de demande, motif, commentaire'])
            . '</div>'
            . '<div class="rh-personnel-filter-actions">'
            . '<button type="submit" class="rh-filter-btn rh-filter-btn--primary">Filtrer</button>'
            . '<a href="' . View::url('finance/fonds/historique') . '" class="rh-filter-btn rh-filter-btn--reset">Réinitialiser</a>'
            . '</div></form>';
    }

    /** @param array<int, array<string, mixed>> $lignes */
    private static function tableau(array $lignes): string
    {
        if ($lignes === []) {
            return Ui::emptyState(
                'Aucune décision sur cette période',
                "Élargissez les dates, ou retirez les filtres. Une demande qui n'a jamais été ouverte n'apparaît pas ici."
            );
        }

        $corps = '';
        foreach ($lignes as $l) {
            $action = (string) $l['action'];

            $corps .= '<tr>'
                . '<td class="lbp-hist-mono">' . View::e(self::horodatage($l['created_at'])) . '</td>'
                . '<td>' . Ui::badge(self::ACTIONS[$action] ?? $action, self::TONS[$action] ?? 'neutral') . '</td>'
                . '<td><a href="' . View::url('finance/fonds/' . (int) $l['demande_fonds_id']) . '"><strong>'
                    . View::e((string) ($l['numero_demande'] ?? '—')) . '</strong></a>'
                . '<span class="lbp-hist-sous">' . View::e(self::court((string) ($l['motif'] ?? ''), 60)) . '</span></td>'
                . '<td class="lbp-hist-droite lbp-hist-mono">' . View::e(self::montant($l)) . '</td>'
                . '<td>' . View::e((string) ($l['agence_nom'] ?? '—')) . '</td>'
                . '<td>' . View::e((string) ($l['demandeur_nom'] ?? '—')) . '</td>'
                . '<td><strong>' . View::e((string) ($l['auteur_nom'] ?? 'Compte supprimé')) . '</strong></td>'
                . '<td class="lbp-hist-motif">' . self::motif($l) . '</td>'
                . '</tr>';
        }

        return '<div class="lbp-hist-enveloppe"><table class="finea-table lbp-hist-table">'
            . '<thead><tr>'
            . '<th>Date et heure</th><th>Décision</th><th>Demande</th>'
            . '<th class="lbp-hist-droite">Montant</th><th>Agence</th><th>Demandeur</th>'
            . '<th>Par qui</th><th>Motif / commentaire</th>'
            . '</tr></thead><tbody>' . $corps . '</tbody></table></div>';
    }

    /** @param array<string, mixed> $l */
    private static function motif(array $l): string
    {
        $commentaire = trim((string) ($l['commentaire'] ?? ''));

        if ($commentaire === '') {
            // Un rejet sans motif est une anomalie : le formulaire en exige un.
            return (string) $l['action'] === 'REJET'
                ? '<span class="lbp-hist-manque">Motif non enregistré</span>'
                : '<span class="lbp-hist-vide">—</span>';
        }

        return View::e($commentaire);
    }

    /** @param array<string, mixed> $l */
    private static function montant(array $l): string
    {
        $montant = $l['montant'] ?? null;

        if ($montant === null) {
            return '—';
        }

        return number_format((float) $montant, 0, ',', ' ') . ' ' . (string) ($l['devise'] ?? 'XOF');
    }

    /** @param array<string, mixed> $filtres */
    private static function titre(array $filtres): string
    {
        $du = trim((string) ($filtres['du'] ?? ''));
        $au = trim((string) ($filtres['au'] ?? ''));

        if ($du === '' && $au === '') {
            return 'Toutes les décisions enregistrées';
        }

        return 'Décisions du ' . self::jour($du) . ' au ' . self::jour($au);
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

    private static function court(string $texte, int $max): string
    {
        return mb_strlen($texte) > $max ? mb_substr($texte, 0, $max - 1) . '…' : $texte;
    }

    /** @param array<string, mixed> $f */
    private static function requete(array $f): string
    {
        $params = [];
        foreach (['du', 'au', 'action', 'agence_id', 'user_id', 'q'] as $champ) {
            $valeur = trim((string) ($f[$champ] ?? ''));
            if ($valeur !== '' && $valeur !== '0') {
                $params[$champ] = $valeur;
            }
        }

        return $params === [] ? '' : '?' . http_build_query($params);
    }

    // ------------------------------------------------------------------
    // Les deux documents que la direction sort du journal
    // ------------------------------------------------------------------

    /**
     * @param array<int, array<string, mixed>> $lignes
     * @param array<string, mixed> $filtres
     */
    public static function exportPdf(array $lignes, array $filtres, string $editePar): string
    {
        $corps = '';
        foreach ($lignes as $l) {
            $action = (string) $l['action'];

            $corps .= '<tr>'
                . '<td>' . View::e(self::horodatage($l['created_at'])) . '</td>'
                . '<td>' . View::e(self::ACTIONS[$action] ?? $action) . '</td>'
                . '<td>' . View::e((string) ($l['numero_demande'] ?? '—')) . '</td>'
                . '<td>' . View::e(self::court((string) ($l['motif'] ?? ''), 48)) . '</td>'
                . '<td class="num">' . View::e(self::montant($l)) . '</td>'
                . '<td>' . View::e((string) ($l['agence_nom'] ?? '—')) . '</td>'
                . '<td>' . View::e((string) ($l['auteur_nom'] ?? '—')) . '</td>'
                . '<td>' . View::e((string) ($l['commentaire'] ?? '')) . '</td>'
                . '</tr>';
        }

        return '<!doctype html><html lang="fr"><head><meta charset="utf-8">'
            . '<title>Historique des décisions — Gestion des fonds</title>'
            . '<style>'
            . 'body{font-family:Arial,Helvetica,sans-serif;font-size:10px;color:#111;margin:14mm}'
            . 'h1{font-size:16px;margin:0 0 2px}'
            . '.sous{color:#555;margin:0 0 12px;font-size:10px}'
            . 'table{width:100%;border-collapse:collapse}'
            . 'th,td{border:1px solid #bbb;padding:4px 5px;text-align:left;vertical-align:top}'
            . 'th{background:#0f172a;color:#fff;font-size:9px;text-transform:uppercase;letter-spacing:.04em}'
            . '.num{text-align:right;white-space:nowrap}'
            . 'tr:nth-child(even) td{background:#f6f7f9}'
            . '@media print{body{margin:8mm}thead{display:table-header-group}}'
            . '</style></head><body>'
            . '<h1>Historique des décisions — Gestion des fonds</h1>'
            . '<p class="sous">' . View::e(self::titre($filtres) . ' · ' . count($lignes) . ' décision(s) · édité le '
                . date('d/m/Y à H:i') . ($editePar === '' ? '' : ' par ' . $editePar)) . '</p>'
            . '<table><thead><tr>'
            . '<th>Date et heure</th><th>Décision</th><th>Demande</th><th>Objet</th>'
            . '<th>Montant</th><th>Agence</th><th>Par qui</th><th>Motif / commentaire</th>'
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
            $action = (string) $l['action'];

            $corps .= '<tr>'
                . self::xTexte(self::horodatage($l['created_at']))
                . self::xTexte(self::ACTIONS[$action] ?? $action)
                . self::xTexte((string) ($l['statut_avant'] ?? ''))
                . self::xTexte((string) ($l['statut_apres'] ?? ''))
                . self::xTexte((string) ($l['numero_demande'] ?? ''))
                . self::xTexte((string) ($l['motif'] ?? ''))
                . self::xNombre($l['montant'] ?? null)
                . self::xTexte((string) ($l['devise'] ?? ''))
                . self::xTexte((string) ($l['agence_nom'] ?? ''))
                . self::xTexte((string) ($l['demandeur_nom'] ?? ''))
                . self::xTexte((string) ($l['auteur_nom'] ?? ''))
                . self::xTexte((string) ($l['auteur_email'] ?? ''))
                . self::xTexte((string) ($l['commentaire'] ?? ''))
                . self::xTexte((string) ($l['statut_actuel'] ?? ''))
                . '</tr>';
        }

        $entetes = ['Date et heure', 'Décision', 'Statut avant', 'Statut après', 'N° de demande', 'Objet',
            'Montant', 'Devise', 'Agence', 'Demandeur', 'Décidé par', 'Courriel', 'Motif / commentaire',
            'Statut actuel de la demande'];

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
        // Sans ce format, le tableur transforme « 483-20428520 » en date.
        return '<td style="mso-number-format:\'\\@\'">' . View::e($valeur) . '</td>';
    }

    private static function xNombre(mixed $valeur): string
    {
        return $valeur === null ? '<td></td>' : '<td>' . number_format((float) $valeur, 2, '.', '') . '</td>';
    }

    private static function styles(): string
    {
        return '<style>'
            . '.lbp-hist-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;margin:18px 0}'
            . '.lbp-hist-kpi{background:#fff;border:1px solid #e3e6ea;border-radius:14px;padding:16px 18px;display:flex;flex-direction:column;gap:6px}'
            . '.lbp-hist-kpi strong{font-size:27px;font-weight:700;letter-spacing:-.02em;line-height:1.1}'
            . '.lbp-hist-kpi-libelle{font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#5b6472}'
            . '.lbp-hist-kpi-detail{font-size:12px;color:#5b6472}'
            . '.lbp-hist-filtres{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}'
            . '.lbp-hist-enveloppe{overflow-x:auto}'
            . '.lbp-hist-table{width:100%;font-size:13px}'
            . '.lbp-hist-table th{white-space:normal;line-height:1.25}'
            . '.lbp-hist-droite{text-align:right}'
            . '.lbp-hist-mono{font-family:Consolas,"SF Mono",monospace;font-variant-numeric:tabular-nums;white-space:nowrap}'
            . '.lbp-hist-sous{display:block;font-size:11px;color:#8b94a1;font-weight:400}'
            . '.lbp-hist-motif{max-width:280px;color:#334155}'
            . '.lbp-hist-vide{color:#94a3b8}'
            . '.lbp-hist-manque{color:#b42318;font-weight:700}'
            . '</style>';
    }
}
