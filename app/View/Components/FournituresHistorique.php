<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;

/**
 * Le journal des décisions prises sur les demandes de fournitures.
 *
 * Même besoin que pour les demandes de fonds : savoir qui a approuvé, qui a
 * confirmé, quand et pourquoi, sans rouvrir les demandes une à une — et le
 * sortir en PDF ou en tableur quand une agence conteste.
 */
final class FournituresHistorique
{
    /** Les quatre moments d'une demande, dans les mots de la maison. */
    public const ACTIONS = [
        'DEMANDE' => 'Demandée',
        'APPROBATION' => 'Approuvée',
        'CONFIRMATION' => 'Confirmée',
        'REJET' => 'Refusée',
        'LIVRAISON' => 'Livrée',
    ];

    private const TONS = [
        'DEMANDE' => 'neutral',
        'APPROBATION' => 'info',
        'CONFIRMATION' => 'success',
        'REJET' => 'danger',
        'LIVRAISON' => 'primary',
    ];

    /**
     * @param array<int, array<string, mixed>> $lignes
     * @param array<string, mixed> $filtres
     * @param array<int, array{id: int, name: string}> $agences
     * @param array<int, array{id: int, name: string}> $decideurs
     */
    public static function page(array $lignes, array $filtres, array $agences, array $decideurs): string
    {
        $entete = Ui::pageHeader(
            'Historique des fournitures',
            'Qui a approuvé, qui a confirmé, quand et pourquoi. Chaque ligne est une décision prise sur une demande de fournitures.',
            [
                'eyebrow' => 'Ressources internes',
                'class' => 'rh-hero-white',
                'actions' => [
                    Ui::button('Exporter en PDF', [
                        'href' => 'colisage/exploitation/fournitures/historique/pdf' . self::requete($filtres),
                        'variant' => 'primary',
                        'target' => '_blank',
                    ]),
                    Ui::button('Exporter en Excel', [
                        'href' => 'colisage/exploitation/fournitures/historique/excel' . self::requete($filtres),
                        'variant' => 'secondary',
                    ]),
                ],
            ]
        );

        return '<div class="finea-shell"><div class="finea-container">'
            . $entete
            . self::compteurs($lignes)
            . self::filtres($filtres, $agences, $decideurs)
            . '<section class="finea-section-card">'
            . '<div class="finea-section-heading"><h2 class="finea-section-title">' . View::e(self::titre($filtres)) . '</h2>'
            . '<span>' . View::e(count($lignes) . ' décision(s) sur la période affichée.') . '</span></div>'
            . self::tableau($lignes)
            . '</section></div></div>'
            . self::styles();
    }

    /** @param array<int, array<string, mixed>> $lignes */
    private static function compteurs(array $lignes): string
    {
        $compte = ['APPROBATION' => 0, 'CONFIRMATION' => 0, 'REJET' => 0];
        $montantConfirme = 0.0;

        foreach ($lignes as $l) {
            $action = (string) $l['action'];
            if (isset($compte[$action])) {
                $compte[$action]++;
            }
            if ($action === 'CONFIRMATION') {
                $montantConfirme += (float) ($l['montant'] ?? 0);
            }
        }

        return '<div class="lbp-fhist-kpis">'
            . self::kpi('Approuvées', (string) $compte['APPROBATION'], 'Par le superviseur ou la direction')
            . self::kpi('Confirmées', (string) $compte['CONFIRMATION'], number_format($montantConfirme, 0, ',', ' ') . ' F engagés')
            . self::kpi('Refusées', (string) $compte['REJET'], 'Avec leur motif')
            . '</div>';
    }

    private static function kpi(string $libelle, string $valeur, string $detail): string
    {
        return '<div class="lbp-fhist-kpi">'
            . '<span class="lbp-fhist-kpi-libelle">' . View::e($libelle) . '</span>'
            . '<strong>' . View::e($valeur) . '</strong>'
            . '<span class="lbp-fhist-kpi-detail">' . View::e($detail) . '</span>'
            . '</div>';
    }

    /**
     * @param array<string, mixed> $f
     * @param array<int, array{id: int, name: string}> $agences
     * @param array<int, array{id: int, name: string}> $decideurs
     */
    private static function filtres(array $f, array $agences, array $decideurs): string
    {
        $actions = [['value' => '', 'label' => 'Toutes les décisions']];
        foreach (self::ACTIONS as $code => $libelle) {
            $actions[] = ['value' => $code, 'label' => $libelle];
        }

        $listeAgences = [['value' => '', 'label' => 'Toutes les agences']];
        foreach ($agences as $a) {
            $listeAgences[] = ['value' => (string) $a['name'], 'label' => (string) $a['name']];
        }

        $listeDecideurs = [['value' => '', 'label' => 'Tout le monde']];
        foreach ($decideurs as $d) {
            $listeDecideurs[] = ['value' => (string) $d['name'], 'label' => (string) $d['name']];
        }

        return '<form method="get" action="' . View::url('colisage/exploitation/fournitures/historique') . '" class="rh-personnel-filters">'
            . '<div class="lbp-fhist-filtres">'
            . Form::input('du', ['label' => 'Du', 'type' => 'date', 'value' => (string) ($f['du'] ?? ''), 'id' => 'fh-du'])
            . Form::input('au', ['label' => 'Au', 'type' => 'date', 'value' => (string) ($f['au'] ?? ''), 'id' => 'fh-au'])
            . Form::select('action', $actions, (string) ($f['action'] ?? ''), ['label' => 'Décision', 'id' => 'fh-action'])
            . Form::select('agence', $listeAgences, (string) ($f['agence'] ?? ''), ['label' => 'Agence', 'id' => 'fh-agence'])
            . Form::select('par', $listeDecideurs, (string) ($f['par'] ?? ''), ['label' => 'Par qui', 'id' => 'fh-par'])
            . Form::input('q', ['label' => 'Recherche', 'value' => (string) ($f['q'] ?? ''), 'id' => 'fh-q', 'placeholder' => 'Fourniture, motif, nom'])
            . '</div>'
            . '<div class="rh-personnel-filter-actions">'
            . '<button type="submit" class="rh-filter-btn rh-filter-btn--primary">Filtrer</button>'
            . '<a href="' . View::url('colisage/exploitation/fournitures/historique') . '" class="rh-filter-btn rh-filter-btn--reset">Réinitialiser</a>'
            . '</div></form>';
    }

    /** @param array<int, array<string, mixed>> $lignes */
    private static function tableau(array $lignes): string
    {
        if ($lignes === []) {
            return Ui::emptyState(
                'Aucune décision sur cette période',
                'Élargissez les dates, ou retirez les filtres.'
            );
        }

        $corps = '';
        foreach ($lignes as $l) {
            $action = (string) $l['action'];

            $corps .= '<tr>'
                . '<td class="lbp-fhist-mono">' . View::e(self::horodatage($l['quand'])) . '</td>'
                . '<td>' . Ui::badge(self::ACTIONS[$action] ?? $action, self::TONS[$action] ?? 'neutral') . '</td>'
                . '<td>' . View::e(self::court((string) $l['objet'], 70)) . '</td>'
                . '<td class="lbp-fhist-droite lbp-fhist-mono">' . View::e(self::montant($l)) . '</td>'
                . '<td>' . View::e((string) ($l['agence_nom'] ?: '—')) . '</td>'
                . '<td>' . View::e((string) ($l['demandeur_nom'] ?: '—')) . '</td>'
                . '<td><strong>' . View::e(self::auteur($l)) . '</strong></td>'
                . '<td class="lbp-fhist-motif">' . self::motif($l) . '</td>'
                . '</tr>';
        }

        return '<div class="lbp-fhist-enveloppe"><table class="finea-table lbp-fhist-table">'
            . '<thead><tr>'
            . '<th>Date et heure</th><th>Décision</th><th>Fourniture</th>'
            . '<th class="lbp-fhist-droite">Montant</th><th>Agence</th><th>Demandeur</th>'
            . '<th>Par qui</th><th>Motif</th>'
            . '</tr></thead><tbody>' . $corps . '</tbody></table></div>';
    }

    /** @param array<string, mixed> $l */
    private static function auteur(array $l): string
    {
        $par = trim((string) ($l['par'] ?? ''));

        if ($par !== '') {
            return $par;
        }

        // La livraison se déclare à réception, sans que la colonne retienne qui
        // a cliqué : le dire vaut mieux que d'afficher un tiret ambigu.
        return (string) $l['action'] === 'LIVRAISON' ? "L'agence, à réception" : '—';
    }

    /** @param array<string, mixed> $l */
    private static function motif(array $l): string
    {
        $motif = trim((string) ($l['motif'] ?? ''));

        if ($motif !== '') {
            return View::e($motif);
        }

        return (string) $l['action'] === 'REJET'
            ? '<span class="lbp-fhist-manque">Motif non enregistré</span>'
            : '<span class="lbp-fhist-vide">—</span>';
    }

    /** @param array<string, mixed> $l */
    private static function montant(array $l): string
    {
        $montant = $l['montant'] ?? null;

        if ($montant === null || (float) $montant <= 0.0) {
            return '—';
        }

        return number_format((float) $montant, 0, ',', ' ') . ' XOF';
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
        foreach (['du', 'au', 'action', 'agence', 'par', 'q'] as $champ) {
            $valeur = trim((string) ($f[$champ] ?? ''));
            if ($valeur !== '') {
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
            $action = (string) $l['action'];

            $corps .= '<tr>'
                . '<td>' . View::e(self::horodatage($l['quand'])) . '</td>'
                . '<td>' . View::e(self::ACTIONS[$action] ?? $action) . '</td>'
                . '<td>' . View::e(self::court((string) $l['objet'], 55)) . '</td>'
                . '<td class="num">' . View::e(self::montant($l)) . '</td>'
                . '<td>' . View::e((string) $l['agence_nom']) . '</td>'
                . '<td>' . View::e((string) $l['demandeur_nom']) . '</td>'
                . '<td>' . View::e(self::auteur($l)) . '</td>'
                . '<td>' . View::e((string) $l['motif']) . '</td>'
                . '</tr>';
        }

        return '<!doctype html><html lang="fr"><head><meta charset="utf-8">'
            . '<title>Historique des fournitures</title>'
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
            . '<h1>Historique des demandes de fournitures</h1>'
            . '<p class="sous">' . View::e(self::titre($filtres) . ' · ' . count($lignes) . ' décision(s) · édité le '
                . date('d/m/Y à H:i') . ($editePar === '' ? '' : ' par ' . $editePar)) . '</p>'
            . '<table><thead><tr>'
            . '<th>Date et heure</th><th>Décision</th><th>Fourniture</th><th>Montant</th>'
            . '<th>Agence</th><th>Demandeur</th><th>Par qui</th><th>Motif</th>'
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
                . self::xTexte(self::horodatage($l['quand']))
                . self::xTexte(self::ACTIONS[$action] ?? $action)
                . self::xTexte((string) $l['demande_id'])
                . self::xTexte((string) $l['objet'])
                . self::xNombre($l['quantite'] ?? null)
                . self::xNombre($l['montant'] ?? null)
                . self::xTexte((string) $l['agence_nom'])
                . self::xTexte((string) $l['demandeur_nom'])
                . self::xTexte(self::auteur($l))
                . self::xTexte((string) $l['motif'])
                . self::xTexte((string) $l['statut_actuel'])
                . '</tr>';
        }

        $entetes = ['Date et heure', 'Décision', 'N° de demande', 'Fourniture', 'Quantité', 'Montant',
            'Agence', 'Demandeur', 'Par qui', 'Motif', 'Statut actuel'];

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

    private static function xNombre(mixed $valeur): string
    {
        return $valeur === null ? '<td></td>' : '<td>' . number_format((float) $valeur, 2, '.', '') . '</td>';
    }

    private static function styles(): string
    {
        return '<style>'
            . '.lbp-fhist-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;margin:18px 0}'
            . '.lbp-fhist-kpi{background:#fff;border:1px solid #e3e6ea;border-radius:14px;padding:16px 18px;display:flex;flex-direction:column;gap:6px}'
            . '.lbp-fhist-kpi strong{font-size:27px;font-weight:700;letter-spacing:-.02em;line-height:1.1}'
            . '.lbp-fhist-kpi-libelle{font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#5b6472}'
            . '.lbp-fhist-kpi-detail{font-size:12px;color:#5b6472}'
            . '.lbp-fhist-filtres{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}'
            . '.lbp-fhist-enveloppe{overflow-x:auto}'
            . '.lbp-fhist-table{width:100%;font-size:13px}'
            . '.lbp-fhist-table th{white-space:normal;line-height:1.25}'
            . '.lbp-fhist-droite{text-align:right}'
            . '.lbp-fhist-mono{font-family:Consolas,"SF Mono",monospace;font-variant-numeric:tabular-nums;white-space:nowrap}'
            . '.lbp-fhist-motif{max-width:260px;color:#334155}'
            . '.lbp-fhist-vide{color:#94a3b8}'
            . '.lbp-fhist-manque{color:#b42318;font-weight:700}'
            . '</style>';
    }
}
