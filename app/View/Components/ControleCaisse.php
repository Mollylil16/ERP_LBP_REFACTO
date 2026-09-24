<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;

/**
 * Finance > Contrôle des caisses : l'écran de la direction.
 *
 * Quatre tableaux, dans l'ordre où la question se pose :
 *
 *   1. les caisses de la journée — qui a compté, qui n'a pas compté ;
 *   2. les journées jamais comptées sur la période ;
 *   3. les points signés avant le dernier encaissement ;
 *   4. les écarts déclarés au comptage, avec leur explication.
 *
 * L'écran ne porte aucun bouton d'action : il constate. Une correction se
 * fait par le point de caisse de l'agence, sous le nom de l'agence.
 */
final class ControleCaisse
{
    private const ICONES = [
        'filtrer' => '<path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"></path>',
        'reinitialiser' => '<path d="M3 2v6h6"></path><path d="M3.51 15a9 9 0 1 0 2.13-9.36L3 8"></path>',
        'imprimer' => '<polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect>',
    ];

    /** L'état du point de caisse, dit en clair. */
    private const ETATS = [
        'SOUMIS' => ['Point soumis', 'success'],
        'CONSOLIDE' => ['Consolidé', 'success'],
        'ROUVERT' => ['Rouvert, à recompter', 'warning'],
        'BROUILLON' => ['Ouvert, jamais soumis', 'danger'],
        'AUCUN_POINT' => ['Aucun point', 'danger'],
    ];

    // ------------------------------------------------------------------
    // L'écran
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $p */
    public static function page(array $p): string
    {
        $f = $p['filtres'];
        $requete = http_build_query(array_filter([
            'jour' => $f['jour'],
            'fenetre' => (string) $f['fenetre'],
            'agence_id' => $f['agence_id'] > 0 ? (string) $f['agence_id'] : null,
        ], static fn (mixed $v): bool => $v !== null && $v !== ''));

        $html = Ui::pageHeader(
            'Contrôle des caisses',
            'Ce qui est entré en caisse, ce qui a été compté, ce qui ne l\'a jamais été.',
            ['eyebrow' => 'Finance · Direction', 'class' => 'rh-hero-white', 'actions' => [
                Ui::button(self::icone('imprimer') . 'Version imprimable', [
                    'href' => 'finance/controle-caisse/pdf?' . $requete,
                    'variant' => 'primary',
                    'target' => '_blank',
                ]),
            ]]
        );

        $html .= self::kpis($p) . self::filtres($p);

        $html .= Ui::section(
            'Les caisses du ' . self::date($f['jour']),
            self::tableauCaisses($p),
            'Une ligne par agence où de l\'argent est entré ou un point a été ouvert.'
        );

        $html .= Ui::section(
            'Journées jamais comptées',
            self::tableauSansPoint($p),
            'Du ' . self::date($f['depuis']) . ' au ' . self::date($f['jour']) . ' : de l\'argent est entré, aucun point n\'a été soumis.'
        );

        $html .= Ui::section(
            'Points signés avant la fin de la journée',
            self::tableauApres($p),
            'L\'agence a continué d\'encaisser après avoir signé : le tiroir contient plus que le chiffre signé.'
        );

        $html .= Ui::section(
            'Écarts déclarés au comptage',
            self::tableauEcarts($p),
            'Écart entre le comptage physique et l\'attendu, l\'explication de l\'agence en regard.'
        );

        return self::styles() . '<div class="finea-shell lbp-controle"><div class="finea-container">' . $html . '</div></div>';
    }

    /** @param array<string, mixed> $p */
    private static function kpis(array $p): string
    {
        $t = $p['totaux'];
        $nonComptes = (float) $t['non_comptes'];
        $apres = (float) $t['apres_soumission'];
        $ecart = (float) $t['ecart'];

        $detailSoumises = (int) $t['soumises'] . ' point(s) soumis sur ' . count($p['caisses']) . ' caisse(s)'
            . ((int) $t['sans_comptage'] > 0 ? ' · ' . (int) $t['sans_comptage'] . ' sans comptage physique' : '');

        return '<div class="lbp-controle-kpis">'
            . self::kpi('Encaissé ce jour', self::montant((float) $t['encaisse']) . ' F', self::montant((float) $t['especes']) . ' F en espèces · ' . (int) $t['agences'] . ' agence(s)', false, true)
            . self::kpi('Caisses comptées', (int) $t['soumises'] . ' / ' . count($p['caisses']), $detailSoumises, (int) $t['soumises'] < count($p['caisses']))
            . self::kpi('Argent non compté', self::montant($nonComptes) . ' F', $nonComptes > 0 ? 'Aucun point soumis pour cet argent' : 'Toutes les caisses ont été comptées', $nonComptes > 0)
            . self::kpi('Encaissé après signature', self::montant($apres) . ' F', $apres > 0 ? 'Entré dans le tiroir après le point' : 'Aucun encaissement après le point', $apres > 0)
            . self::kpi('Écart déclaré', self::signe($ecart) . ' F', $ecart > 0 ? 'Compté en plus de l\'attendu' : ($ecart < 0 ? 'Compté en moins que l\'attendu' : 'Comptages conformes'), $ecart !== 0.0)
            . '</div>';
    }

    private static function kpi(string $libelle, string $valeur, string $detail = '', bool $alerte = false, bool $sombre = false): string
    {
        return '<div class="lbp-controle-kpi' . ($alerte ? ' is-alerte' : '') . ($sombre ? ' is-sombre' : '') . '">'
            . '<span class="lbp-controle-kpi-libelle">' . View::e($libelle) . '</span>'
            . '<strong>' . View::e($valeur) . '</strong>'
            . ($detail !== '' ? '<span class="lbp-controle-kpi-detail">' . View::e($detail) . '</span>' : '')
            . '</div>';
    }

    /** @param array<string, mixed> $p */
    private static function filtres(array $p): string
    {
        $f = $p['filtres'];

        $agences = [['value' => '', 'label' => 'Toutes les agences']];
        foreach ($p['agences'] as $a) {
            $agences[] = ['value' => (string) $a['id'], 'label' => (string) $a['name']];
        }

        $fenetres = [
            ['value' => '7', 'label' => '7 derniers jours'],
            ['value' => '30', 'label' => '30 derniers jours'],
            ['value' => '90', 'label' => '90 derniers jours'],
        ];

        $action = View::e(View::url('finance/controle-caisse'));

        return '<form method="get" action="' . $action . '" class="rh-personnel-filters lbp-controle-filtres">'
            . '<div class="lbp-controle-filtres-grille">'
            . Form::input('jour', ['label' => 'Journée', 'type' => 'date', 'value' => (string) $f['jour'], 'id' => 'controle-jour'])
            . Form::select('fenetre', $fenetres, (string) $f['fenetre'], ['label' => 'Période de contrôle', 'id' => 'controle-fenetre'])
            . Form::select('agence_id', $agences, $f['agence_id'] > 0 ? (string) $f['agence_id'] : '', ['label' => 'Agence', 'id' => 'controle-agence'])
            . '</div>'
            . '<div class="rh-personnel-filter-actions">'
            . '<button type="submit" class="rh-filter-btn rh-filter-btn--primary">' . self::icone('filtrer') . 'Afficher</button>'
            . '<a href="' . $action . '" class="rh-filter-btn rh-filter-btn--reset">' . self::icone('reinitialiser') . 'Aujourd\'hui</a>'
            . '</div></form>';
    }

    // ------------------------------------------------------------------
    // 1. Les caisses de la journée
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $p */
    private static function tableauCaisses(array $p): string
    {
        if ($p['caisses'] === []) {
            return Ui::emptyState('Aucun mouvement ce jour-là', 'Aucune agence n\'a encaissé et aucun point n\'a été ouvert. Choisissez une autre journée.');
        }

        $corps = '';
        foreach ($p['caisses'] as $c) {
            $alerte = in_array($c['etat'], ['AUCUN_POINT', 'BROUILLON'], true) && $c['encaisse'] > 0;
            $attention = $c['apres_soumission'] > 0 || $c['etat'] === 'ROUVERT' || !empty($c['compte_sans_comptage']);

            $classe = 'lbp-controle-ligne';
            if ($alerte) {
                $classe .= ' is-alerte';
            } elseif ($attention) {
                $classe .= ' is-attention';
            }

            $corps .= '<tr class="' . $classe . '">'
                . '<td><strong>' . View::e((string) $c['agence']) . '</strong>' . self::sous(self::detailEncaissement($c)) . '</td>'
                . '<td class="lbp-controle-droite lbp-controle-mono">' . (int) $c['operations'] . '</td>'
                . '<td class="lbp-controle-droite lbp-controle-mono">' . self::montant((float) $c['encaisse']) . self::sous(self::montant((float) $c['especes']) . ' en espèces') . '</td>'
                . '<td>' . self::etatBadge((string) $c['etat'], $c) . '</td>'
                . '<td class="lbp-controle-droite lbp-controle-mono">' . self::montantOuTiret($c['theorique']) . '</td>'
                . '<td class="lbp-controle-droite lbp-controle-mono">' . self::montantOuTiret($c['compte']) . '</td>'
                . '<td class="lbp-controle-droite">' . self::ecartBadge($c['ecart']) . '</td>'
                . '<td>' . self::observation($c) . '</td>'
                . '</tr>';
        }

        $t = $p['totaux'];

        return '<div class="lbp-controle-table-enveloppe"><table class="finea-table lbp-controle-table">'
            . '<thead><tr>'
            . '<th>Agence</th><th class="lbp-controle-droite">Opér.</th><th class="lbp-controle-droite">Encaissé</th>'
            . '<th>Point de caisse</th><th class="lbp-controle-droite">Attendu en caisse</th>'
            . '<th class="lbp-controle-droite">Compté</th><th class="lbp-controle-droite">Écart</th><th>Observation</th>'
            . '</tr></thead><tbody>' . $corps . '</tbody>'
            . '<tfoot><tr>'
            . '<td>' . count($p['caisses']) . ' caisse(s)</td>'
            . '<td class="lbp-controle-droite lbp-controle-mono">' . array_sum(array_column($p['caisses'], 'operations')) . '</td>'
            . '<td class="lbp-controle-droite lbp-controle-mono">' . self::montant((float) $t['encaisse']) . '</td>'
            . '<td colspan="3">' . View::e((int) $t['soumises'] . ' point(s) soumis') . '</td>'
            . '<td class="lbp-controle-droite lbp-controle-mono">' . self::signe((float) $t['ecart']) . '</td>'
            . '<td>' . View::e(self::montant((float) $t['non_comptes']) . ' F non comptés') . '</td>'
            . '</tr></tfoot></table></div>';
    }

    /** @param array<string, mixed> $c */
    private static function detailEncaissement(array $c): string
    {
        if ((int) $c['operations'] === 0) {
            return 'Aucun encaissement';
        }

        $dernier = (string) ($c['dernier_encaissement'] ?? '');

        return $dernier === '' ? '' : 'Dernier encaissement à ' . substr($dernier, 11, 5);
    }

    /** @param array<string, mixed> $c */
    private static function etatBadge(string $etat, array $c): string
    {
        [$libelle, $ton] = self::ETATS[$etat] ?? [$etat, 'neutral'];

        $heure = (string) ($c['date_soumission'] ?? '');
        $sous = $heure !== '' && in_array($etat, ['SOUMIS', 'CONSOLIDE'], true)
            ? 'Signé à ' . substr($heure, 11, 5)
            : '';

        return Ui::badge($libelle, $ton) . self::sous($sous);
    }

    /**
     * Ce que la direction doit lire en un coup d'œil, en une phrase.
     *
     * @param array<string, mixed> $c
     */
    private static function observation(array $c): string
    {
        $notes = [];

        if ($c['apres_soumission'] > 0) {
            $notes[] = self::montant((float) $c['apres_soumission']) . ' F encaissés après la signature ('
                . (int) $c['operations_apres'] . ' opération(s)) : le tiroir en contient autant de plus.';
        }

        if (!empty($c['compte_sans_comptage'])) {
            $notes[] = 'Point soumis sans comptage physique.';
        }

        if (in_array($c['etat'], ['AUCUN_POINT', 'BROUILLON'], true) && $c['encaisse'] > 0) {
            $notes[] = self::montant((float) $c['encaisse']) . ' F n\'ont été comptés par personne.';
        }

        if ($c['explication'] !== '') {
            $notes[] = '« ' . $c['explication'] . ' »';
        }

        if ($notes === []) {
            return '<span class="lbp-controle-vide">—</span>';
        }

        return '<span class="lbp-controle-note">' . View::e(implode(' ', $notes)) . '</span>';
    }

    // ------------------------------------------------------------------
    // 2. Les journées jamais comptées
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $p */
    private static function tableauSansPoint(array $p): string
    {
        if ($p['sansPoint'] === []) {
            return Ui::emptyState('Toutes les journées ont leur point', 'Sur cette période, chaque journée encaissée a été comptée et soumise.');
        }

        $corps = '';
        $total = 0.0;

        foreach ($p['sansPoint'] as $j) {
            $total += (float) $j['total'];
            $ouvert = (string) ($j['ouvert_le'] ?? '');

            $corps .= '<tr class="lbp-controle-ligne is-alerte">'
                . '<td class="lbp-controle-mono">' . View::e(self::date((string) $j['jour'])) . '</td>'
                . '<td>' . View::e((string) $j['agence']) . '</td>'
                . '<td class="lbp-controle-droite lbp-controle-mono">' . (int) $j['nb'] . '</td>'
                . '<td class="lbp-controle-droite lbp-controle-mono">' . self::montant((float) $j['total']) . '</td>'
                . '<td>' . ((string) $j['etat'] === 'AUCUN_POINT'
                    ? Ui::badge('Aucun point ouvert', 'danger')
                    : Ui::badge('Ouvert, jamais soumis', 'warning') . self::sous($ouvert === '' ? '' : 'Ouvert le ' . self::dateHeure($ouvert)))
                . '</td></tr>';
        }

        return '<div class="lbp-controle-table-enveloppe"><table class="finea-table lbp-controle-table">'
            . '<thead><tr><th>Jour</th><th>Agence</th><th class="lbp-controle-droite">Opér.</th>'
            . '<th class="lbp-controle-droite">Encaissé</th><th>État</th></tr></thead>'
            . '<tbody>' . $corps . '</tbody>'
            . '<tfoot><tr><td colspan="3">' . count($p['sansPoint']) . ' journée(s)</td>'
            . '<td class="lbp-controle-droite lbp-controle-mono">' . self::montant($total) . '</td>'
            . '<td>jamais comptés</td></tr></tfoot></table></div>';
    }

    // ------------------------------------------------------------------
    // 3. Les points signés trop tôt
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $p */
    private static function tableauApres(array $p): string
    {
        if ($p['apresSoumission'] === []) {
            return Ui::emptyState('Aucun point signé trop tôt', 'Chaque journée de la période a été soumise après son dernier encaissement.');
        }

        $corps = '';
        $total = 0.0;

        foreach ($p['apresSoumission'] as $a) {
            $total += (float) $a['montant_apres'];

            $corps .= '<tr class="lbp-controle-ligne is-attention">'
                . '<td class="lbp-controle-mono">' . View::e(self::date((string) $a['date_jour'])) . '</td>'
                . '<td>' . View::e((string) ($a['agence'] ?? '—')) . '</td>'
                . '<td class="lbp-controle-mono">' . View::e(substr((string) $a['date_soumission'], 11, 5)) . '</td>'
                . '<td class="lbp-controle-mono">' . View::e(substr((string) $a['dernier_encaissement'], 11, 5)) . '</td>'
                . '<td class="lbp-controle-droite lbp-controle-mono">' . (int) $a['nb_apres'] . '</td>'
                . '<td class="lbp-controle-droite lbp-controle-mono">' . self::montant((float) $a['montant_apres']) . '</td>'
                . '<td class="lbp-controle-droite lbp-controle-mono">' . self::montant((float) $a['solde_soumis']) . '</td>'
                . '</tr>';
        }

        return '<div class="lbp-controle-table-enveloppe"><table class="finea-table lbp-controle-table">'
            . '<thead><tr><th>Jour</th><th>Agence</th><th>Signé à</th><th>Dernier encaissement</th>'
            . '<th class="lbp-controle-droite">Opér. après</th><th class="lbp-controle-droite">Encaissé après</th>'
            . '<th class="lbp-controle-droite">Solde signé</th></tr></thead>'
            . '<tbody>' . $corps . '</tbody>'
            . '<tfoot><tr><td colspan="5">' . count($p['apresSoumission']) . ' journée(s)</td>'
            . '<td class="lbp-controle-droite lbp-controle-mono">' . self::montant($total) . '</td><td></td></tr></tfoot></table></div>';
    }

    // ------------------------------------------------------------------
    // 4. Les écarts déclarés
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $p */
    private static function tableauEcarts(array $p): string
    {
        if ($p['ecarts'] === []) {
            return Ui::emptyState('Aucun écart déclaré', 'Sur cette période, chaque comptage physique tombe sur l\'attendu.');
        }

        $corps = '';
        foreach ($p['ecarts'] as $e) {
            $explication = trim((string) ($e['explication_ecart'] ?? ''));

            $corps .= '<tr class="lbp-controle-ligne' . ($explication === '' ? ' is-alerte' : '') . '">'
                . '<td class="lbp-controle-mono">' . View::e(self::date((string) $e['date_jour'])) . '</td>'
                . '<td>' . View::e((string) ($e['agence'] ?? '—')) . '</td>'
                . '<td class="lbp-controle-droite lbp-controle-mono">' . self::montant((float) $e['theorique']) . '</td>'
                . '<td class="lbp-controle-droite lbp-controle-mono">' . self::montant((float) $e['compte']) . '</td>'
                . '<td class="lbp-controle-droite">' . self::ecartBadge((float) $e['ecart']) . '</td>'
                . '<td>' . ($explication === ''
                    ? '<span class="lbp-controle-manque">Aucune explication</span>'
                    : '<span class="lbp-controle-note">' . View::e('« ' . $explication . ' »') . '</span>')
                . '</td></tr>';
        }

        return '<div class="lbp-controle-table-enveloppe"><table class="finea-table lbp-controle-table">'
            . '<thead><tr><th>Jour</th><th>Agence</th><th class="lbp-controle-droite">Attendu</th>'
            . '<th class="lbp-controle-droite">Compté</th><th class="lbp-controle-droite">Écart</th>'
            . '<th>Explication de l\'agence</th></tr></thead>'
            . '<tbody>' . $corps . '</tbody></table></div>'
            . '<p class="lbp-controle-legende">Un écart positif veut dire que l\'agence a compté plus que l\'attendu : '
            . 'l\'argent est là, il manque dans le calcul. L\'attendu ne retient que les espèces — un encaissement en '
            . 'espèces enregistré sous un autre mode fait baisser l\'attendu sans toucher au tiroir.</p>';
    }

    // ------------------------------------------------------------------
    // La version imprimable
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $p */
    public static function exportPdf(array $p): string
    {
        $f = $p['filtres'];
        $t = $p['totaux'];

        $lignes = '';
        foreach ($p['caisses'] as $c) {
            [$libelle] = self::ETATS[(string) $c['etat']] ?? [(string) $c['etat'], 'neutral'];
            $alerte = in_array($c['etat'], ['AUCUN_POINT', 'BROUILLON'], true) && $c['encaisse'] > 0;

            $lignes .= '<tr>'
                . '<td>' . View::e((string) $c['agence']) . '</td>'
                . '<td class="num">' . (int) $c['operations'] . '</td>'
                . '<td class="num">' . View::e(self::montant((float) $c['encaisse'])) . '</td>'
                . '<td class="num">' . View::e(self::montant((float) $c['especes'])) . '</td>'
                . '<td' . ($alerte ? ' class="alerte"' : '') . '>' . View::e($libelle) . '</td>'
                . '<td class="num">' . View::e(self::texteOuTiret($c['theorique'])) . '</td>'
                . '<td class="num">' . View::e(self::texteOuTiret($c['compte'])) . '</td>'
                . '<td class="num">' . View::e($c['ecart'] === null ? '—' : self::signe((float) $c['ecart'])) . '</td>'
                . '<td>' . View::e(self::observationTexte($c)) . '</td>'
                . '</tr>';
        }

        if ($lignes === '') {
            $lignes = '<tr><td colspan="9" class="vide">Aucun mouvement ce jour-là.</td></tr>';
        }

        $sansPoint = '';
        foreach ($p['sansPoint'] as $j) {
            $sansPoint .= '<tr>'
                . '<td>' . View::e(self::date((string) $j['jour'])) . '</td>'
                . '<td>' . View::e((string) $j['agence']) . '</td>'
                . '<td class="num">' . (int) $j['nb'] . '</td>'
                . '<td class="num">' . View::e(self::montant((float) $j['total'])) . '</td>'
                . '<td>' . View::e((string) $j['etat'] === 'AUCUN_POINT' ? 'Aucun point ouvert' : 'Ouvert, jamais soumis') . '</td>'
                . '</tr>';
        }

        $blocSansPoint = $sansPoint === ''
            ? '<p class="note">Toutes les journées encaissées de la période ont leur point de caisse.</p>'
            : '<table><thead><tr><th>Jour</th><th>Agence</th><th>Opér.</th><th>Encaissé</th><th>État</th></tr></thead>'
                . '<tbody>' . $sansPoint . '</tbody></table>';

        $entetes = ['Agence', 'Opér.', 'Encaissé', 'Dont espèces', 'Point de caisse', 'Attendu', 'Compté', 'Écart', 'Observation'];

        return '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">'
            . '<title>Contrôle des caisses du ' . View::e(self::date($f['jour'])) . '</title>'
            . '<style>'
            . '@page{size:A4 landscape;margin:12mm}'
            . 'body{font-family:"Segoe UI",Arial,sans-serif;color:#0f172a;margin:0;font-size:11px}'
            . '.entete{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #0f172a;padding-bottom:8px;margin-bottom:12px}'
            . '.sur-titre{margin:0;font-size:10px;letter-spacing:.08em;text-transform:uppercase;color:#64748b}'
            . 'h1{margin:2px 0 0;font-size:19px}'
            . 'h2{margin:18px 0 6px;font-size:13px;text-transform:uppercase;letter-spacing:.05em;color:#475569}'
            . '.meta{text-align:right;font-size:10px;color:#475569;line-height:1.5}'
            . '.resume{display:flex;gap:10px;margin-bottom:12px}'
            . '.resume div{flex:1;border:1px solid #e2e8f0;border-radius:6px;padding:7px 10px}'
            . '.resume span{display:block;font-size:9px;text-transform:uppercase;letter-spacing:.05em;color:#64748b}'
            . '.resume strong{font-size:14px}'
            . 'table{width:100%;border-collapse:collapse;page-break-inside:auto}'
            . 'thead{display:table-header-group}'
            . 'tr{page-break-inside:avoid}'
            . 'th{background:#0f172a;color:#fff;padding:6px 5px;font-size:9px;text-transform:uppercase;letter-spacing:.04em;text-align:left}'
            . 'td{padding:5px;border-bottom:1px solid #e2e8f0;vertical-align:top}'
            . '.num{text-align:right;font-variant-numeric:tabular-nums}'
            . '.alerte{color:#b42318;font-weight:700}'
            . '.vide{text-align:center;color:#64748b;padding:18px}'
            . '.note{margin-top:10px;font-size:9px;color:#64748b}'
            . '.impression{margin:14px 0;text-align:center}'
            . 'button{padding:9px 18px;border:0;border-radius:6px;background:#0f172a;color:#fff;font-size:12px;font-weight:700;cursor:pointer}'
            . '@media print{.impression{display:none}}'
            . '</style></head><body>'
            . '<div class="impression"><button type="button" onclick="window.print()">Imprimer ou enregistrer en PDF</button></div>'
            . '<header class="entete"><div><p class="sur-titre">LBP · Finance · Direction</p><h1>Contrôle des caisses</h1></div>'
            . '<div class="meta">Journée du ' . View::e(self::date($f['jour'])) . '<br>'
            . 'Contrôle sur ' . (int) $f['fenetre'] . ' jours, depuis le ' . View::e(self::date($f['depuis'])) . '<br>'
            . 'Édité le ' . View::e(date('d/m/Y à H:i')) . '<br>' . View::e((string) ($p['edite_par'] ?? '')) . '</div></header>'
            . '<div class="resume">'
            . '<div><span>Encaissé ce jour</span><strong>' . View::e(self::montant((float) $t['encaisse'])) . ' F</strong></div>'
            . '<div><span>Caisses comptées</span><strong>' . (int) $t['soumises'] . ' / ' . count($p['caisses']) . '</strong></div>'
            . '<div><span>Argent non compté</span><strong>' . View::e(self::montant((float) $t['non_comptes'])) . ' F</strong></div>'
            . '<div><span>Encaissé après signature</span><strong>' . View::e(self::montant((float) $t['apres_soumission'])) . ' F</strong></div>'
            . '<div><span>Écart déclaré</span><strong>' . View::e(self::signe((float) $t['ecart'])) . ' F</strong></div>'
            . '</div>'
            . '<table><thead><tr>' . implode('', array_map(static fn (string $e): string => '<th>' . View::e($e) . '</th>', $entetes)) . '</tr></thead>'
            . '<tbody>' . $lignes . '</tbody></table>'
            . '<h2>Journées jamais comptées, depuis le ' . View::e(self::date($f['depuis'])) . '</h2>'
            . $blocSansPoint
            . '<p class="note">L\'argent est rattaché à l\'agence de celui qui l\'a encaissé, jamais à celle de la facture : '
            . 'le billet est dans le tiroir où il a été reçu. L\'attendu en caisse ne retient que les espèces.</p>'
            . '</body></html>';
    }

    /** @param array<string, mixed> $c */
    private static function observationTexte(array $c): string
    {
        $notes = [];

        if ($c['apres_soumission'] > 0) {
            $notes[] = self::montant((float) $c['apres_soumission']) . ' F encaissés après la signature.';
        }
        if (!empty($c['compte_sans_comptage'])) {
            $notes[] = 'Soumis sans comptage physique.';
        }
        if (in_array($c['etat'], ['AUCUN_POINT', 'BROUILLON'], true) && $c['encaisse'] > 0) {
            $notes[] = 'Jamais compté.';
        }
        if ($c['explication'] !== '') {
            $notes[] = '« ' . $c['explication'] . ' »';
        }

        return $notes === [] ? '—' : implode(' ', $notes);
    }

    // ------------------------------------------------------------------
    // Mise en forme
    // ------------------------------------------------------------------

    private static function montant(float $valeur): string
    {
        return number_format($valeur, 0, ',', ' ');
    }

    private static function signe(float $valeur): string
    {
        return ($valeur > 0 ? '+' : '') . self::montant($valeur);
    }

    private static function montantOuTiret(mixed $valeur): string
    {
        return $valeur === null ? '<span class="lbp-controle-vide">—</span>' : View::e(self::montant((float) $valeur));
    }

    private static function texteOuTiret(mixed $valeur): string
    {
        return $valeur === null ? '—' : self::montant((float) $valeur);
    }

    private static function ecartBadge(mixed $ecart): string
    {
        if ($ecart === null) {
            return '<span class="lbp-controle-vide">—</span>';
        }

        $valeur = (float) $ecart;

        if ($valeur === 0.0) {
            return Ui::badge('Conforme', 'success');
        }

        return Ui::badge(self::signe($valeur), $valeur > 0 ? 'info' : 'danger');
    }

    private static function sous(string $texte): string
    {
        return $texte === '' ? '' : '<span class="lbp-controle-sous">' . View::e($texte) . '</span>';
    }

    private static function date(string $date): string
    {
        $objet = date_create($date);

        return $objet === false ? $date : $objet->format('d/m/Y');
    }

    private static function dateHeure(string $date): string
    {
        $objet = date_create($date);

        return $objet === false ? $date : $objet->format('d/m à H\hi');
    }

    private static function icone(string $nom): string
    {
        return '<svg class="lbp-controle-icone" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . (self::ICONES[$nom] ?? '') . '</svg>';
    }

    private static function styles(): string
    {
        return '<style>'
            . '.lbp-controle{--controle-alerte:#b42318;--controle-attention:#b54708;--controle-ok:#027a48;--controle-trait:#e3e6ea}'
            . '.lbp-controle-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin:18px 0}'
            . '.lbp-controle-kpi{background:#fff;border:1px solid var(--controle-trait);border-radius:14px;padding:16px 18px;display:flex;flex-direction:column;gap:6px}'
            . '.lbp-controle-kpi strong{font-size:25px;font-weight:700;letter-spacing:-.02em;line-height:1.1}'
            . '.lbp-controle-kpi-libelle{font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#5b6472}'
            . '.lbp-controle-kpi-detail{font-size:12px;color:#5b6472}'
            . '.lbp-controle-kpi.is-alerte strong{color:var(--controle-alerte)}'
            . '.lbp-controle-kpi.is-sombre{background:#0f172a;border-color:#0f172a}'
            . '.lbp-controle-kpi.is-sombre strong{color:#fff}'
            . '.lbp-controle-kpi.is-sombre .lbp-controle-kpi-libelle,.lbp-controle-kpi.is-sombre .lbp-controle-kpi-detail{color:#94a3b8}'
            . '.lbp-controle-filtres-grille{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}'
            . '.lbp-controle-table-enveloppe{overflow-x:auto}'
            . '.lbp-controle-table{width:100%;font-size:13px}'
            . '.lbp-controle-table th{white-space:nowrap}'
            . '.lbp-controle-table td:first-child{min-width:150px}'
            . '.lbp-controle-table .lbp-controle-droite{white-space:nowrap}'
            . '.lbp-controle-droite{text-align:right}'
            . '.lbp-controle-mono{font-family:Consolas,"SF Mono",monospace;font-variant-numeric:tabular-nums}'
            . '.lbp-controle-sous{display:block;font-size:11px;color:#8b94a1;font-family:inherit;font-weight:400}'
            . '.lbp-controle-vide{color:#8b94a1}'
            . '.lbp-controle-manque{font-weight:700;color:var(--controle-attention)}'
            . '.lbp-controle-note{font-size:12px;color:#475569;display:block;max-width:34ch}'
            . '.lbp-controle-ligne.is-alerte{background:#fef6f5;box-shadow:inset 3px 0 0 var(--controle-alerte)}'
            . '.lbp-controle-ligne.is-attention{background:#fffbf5;box-shadow:inset 3px 0 0 var(--controle-attention)}'
            . '.lbp-controle-table tfoot td{border-top:2px solid #0f172a;font-weight:700;background:#f8fafc}'
            . '.lbp-controle-legende{margin:12px 0 0;font-size:12px;color:#5b6472;max-width:90ch}'
            . '.lbp-controle-icone{flex-shrink:0}'
            . '@media (max-width:850px){.lbp-controle-note{max-width:none}}'
            . '</style>';
    }
}
