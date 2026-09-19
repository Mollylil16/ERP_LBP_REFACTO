<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\Csrf;
use App\Helpers\View;
use App\Services\Finance\RapprochementEnvoisRegles as Regles;

/**
 * Finance > Rapprochement des envois : l'écran, et ses deux exports.
 *
 * Une ligne par départ, la saisie des agences face au document de la
 * compagnie. Le panneau de saisie du comptable est replié sous sa ligne : il
 * s'ouvre sans quitter le tableau, pour garder les autres départs sous les
 * yeux pendant la comparaison.
 *
 * Aucune bibliothèque PDF ou Excel n'est installée : le PDF est une page
 * imprimable, l'Excel un tableau HTML que le tableur ouvre, avec x:num sur les
 * nombres pour que les sommes fonctionnent quelle que soit sa langue.
 */
final class RapprochementEnvois
{
    private const ICONES = [
        'filtrer' => '<path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"></path>',
        'reinitialiser' => '<path d="M3 2v6h6"></path><path d="M3.51 15a9 9 0 1 0 2.13-9.36L3 8"></path>',
        'imprimer' => '<polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect>',
        'telecharger' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line>',
        'alerte' => '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line>',
    ];

    /** @var array<string, string> */
    private const TONS_ETAT = [
        'RAPPROCHE' => 'success',
        'ECART_JUSTIFIE' => 'info',
        'ECART_A_JUSTIFIER' => 'danger',
        'LTA_A_COMPLETER' => 'warning',
        'EN_ATTENTE_LTA' => 'neutral',
    ];

    // ------------------------------------------------------------------
    // L'écran
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $p */
    public static function page(array $p): string
    {
        $f = $p['filtres'];
        $t = $p['totaux'];
        $requete = http_build_query(array_filter([
            'du' => $f['du'], 'au' => $f['au'],
            'transporteur_id' => $f['transporteur_id'] ?: null,
            'agence_id' => $f['agence_id'] ?: null,
            'reglement' => $f['reglement'] ?: null,
            'q' => $f['q'] ?: null,
            'ecarts_seulement' => $f['ecarts_seulement'] ? '1' : null,
        ], static fn (mixed $v): bool => $v !== null && $v !== ''));

        $html = Ui::pageHeader(
            'Rapprochement des envois',
            'Ce que les agences ont enregistré, face au document de la compagnie. Un départ dont la LTA n\'est pas encore arrivée n\'entre pas dans les écarts.',
            ['eyebrow' => 'Finance', 'class' => 'rh-hero-white', 'actions' => [
                Ui::button(self::icone('imprimer') . 'Exporter en PDF', ['href' => 'finance/rapprochement-envois/pdf?' . $requete, 'variant' => 'primary', 'target' => '_blank']),
                Ui::button(self::icone('telecharger') . 'Exporter en Excel', ['href' => 'finance/rapprochement-envois/excel?' . $requete, 'variant' => 'secondary']),
            ]]
        );

        $html .= self::kpis($t) . self::filtres($p);

        $html .= Ui::section(
            'Envois du ' . self::date($f['du']) . ' au ' . self::date($f['au']),
            self::tableau($p),
            'Écarts calculés sur les départs documentés. Seuil d\'alerte ' . Regles::nombre(Regles::SEUIL_POURCENT, 0) . ' %.'
        );

        return self::styles() . '<div class="finea-shell lbp-rappro"><div class="finea-container">' . $html . '</div></div>' . self::script();
    }

    /** @param array<string, mixed> $t */
    private static function kpis(array $t): string
    {
        $ecartColis = (int) $t['ecart_colis'];
        $ecartPoids = (float) $t['ecart_poids'];

        $detailColis = (int) $t['documentes'] . ' départ(s) documenté(s)'
            . ((int) $t['a_completer'] > 0 ? ' · ' . (int) $t['a_completer'] . ' LTA à compléter' : '');

        return '<div class="lbp-rappro-kpis">'
            . self::kpi('Envois de la période', Regles::nombre((float) $t['envois']), (int) $t['documentes'] . ' parti(s) · ' . (int) $t['en_attente'] . ' en attente de LTA')
            . self::kpi('Écart colis', Regles::signe((float) $ecartColis), $detailColis, $ecartColis !== 0)
            . self::kpi('Écart poids', Regles::signe($ecartPoids, 1) . ' kg', (int) $t['a_justifier'] . ' départ(s) à justifier', abs($ecartPoids) > 0)
            . self::kpi('Reste à régler aux compagnies', Regles::nombre((float) $t['reste_a_regler']) . ' F', (int) $t['regles'] . ' règlement(s) enregistré(s)', false, true)
            . '</div>';
    }

    private static function kpi(string $libelle, string $valeur, string $detail = '', bool $alerte = false, bool $sombre = false): string
    {
        return '<div class="lbp-rappro-kpi' . ($alerte ? ' is-alerte' : '') . ($sombre ? ' is-sombre' : '') . '">'
            . '<span class="lbp-rappro-kpi-libelle">' . View::e($libelle) . '</span>'
            . '<strong>' . View::e($valeur) . '</strong>'
            . ($detail !== '' ? '<span class="lbp-rappro-kpi-detail">' . View::e($detail) . '</span>' : '')
            . '</div>';
    }

    /** @param array<string, mixed> $p */
    private static function filtres(array $p): string
    {
        $f = $p['filtres'];

        $compagnies = [['value' => '', 'label' => 'Toutes les compagnies']];
        foreach ($p['compagnies'] as $c) {
            $compagnies[] = ['value' => (string) $c['id'], 'label' => (string) $c['name']];
        }

        $agences = [['value' => '', 'label' => 'Toutes les agences']];
        foreach ($p['agences'] as $a) {
            $agences[] = ['value' => (string) $a['id'], 'label' => (string) $a['name']];
        }

        $reglements = [
            ['value' => '', 'label' => 'Tous'],
            ['value' => 'NON_REGLE', 'label' => 'Non réglé'],
            ['value' => 'REGLE', 'label' => 'Réglé'],
        ];

        $action = View::e(View::url('finance/rapprochement-envois'));

        return '<form method="get" action="' . $action . '" class="rh-personnel-filters lbp-rappro-filtres">'
            . '<div class="lbp-rappro-filtres-grille">'
            . Form::input('du', ['label' => 'Du', 'type' => 'date', 'value' => (string) $f['du'], 'id' => 'rappro-du'])
            . Form::input('au', ['label' => 'Au', 'type' => 'date', 'value' => (string) $f['au'], 'id' => 'rappro-au'])
            . Form::select('transporteur_id', $compagnies, (string) ($f['transporteur_id'] ?: ''), ['label' => 'Compagnie', 'id' => 'rappro-compagnie'])
            . Form::select('agence_id', $agences, (string) ($f['agence_id'] ?: ''), ['label' => 'Agence de départ', 'id' => 'rappro-agence'])
            . Form::select('reglement', $reglements, (string) $f['reglement'], ['label' => 'Règlement', 'id' => 'rappro-reglement'])
            . Form::input('q', ['label' => 'N° de LTA ou de départ', 'value' => (string) $f['q'], 'id' => 'rappro-q', 'placeholder' => '483-20428520'])
            . '</div>'
            . '<div class="rh-personnel-filter-actions">'
            . '<label class="lbp-rappro-bascule"><input type="checkbox" name="ecarts_seulement" value="1"' . ($f['ecarts_seulement'] ? ' checked' : '') . '> Écarts seulement</label>'
            . '<button type="submit" class="rh-filter-btn rh-filter-btn--primary">' . self::icone('filtrer') . 'Filtrer</button>'
            . '<a href="' . $action . '" class="rh-filter-btn rh-filter-btn--reset">' . self::icone('reinitialiser') . 'Réinitialiser</a>'
            . '</div></form>';
    }

    /** @param array<string, mixed> $p */
    private static function tableau(array $p): string
    {
        $lignes = $p['lignes'];

        if ($lignes === []) {
            return Ui::emptyState('Aucun envoi sur cette période', 'Élargissez les dates, ou retirez le filtre « écarts seulement ».');
        }

        $corps = '';
        foreach ($lignes as $ligne) {
            $corps .= self::ligne($ligne, $p) . self::panneau($ligne, $p);
        }

        return '<div class="lbp-rappro-table-enveloppe"><table class="finea-table lbp-rappro-table">'
            . '<thead><tr>'
            . '<th>Date</th><th>Compagnie</th><th>N° envoi / LTA</th>'
            . '<th class="lbp-rappro-droite">Colis agence</th><th class="lbp-rappro-droite">Colis LTA</th><th class="lbp-rappro-droite">Écart</th>'
            . '<th class="lbp-rappro-droite">Poids agence</th><th class="lbp-rappro-droite">Poids LTA</th><th class="lbp-rappro-droite">Écart poids</th>'
            . '<th class="lbp-rappro-droite">Montant compagnie</th><th>Règlement</th><th>État</th>'
            . '</tr></thead>'
            . '<tbody>' . $corps . '</tbody>'
            . self::pied($p['totaux'])
            . '</table></div>';
    }

    /**
     * @param array<string, mixed> $ligne
     * @param array<string, mixed> $p
     */
    private static function ligne(array $ligne, array $p): string
    {
        $etat = (string) $ligne['etat'];
        $id = (int) $ligne['dossier_id'];

        $classe = 'lbp-rappro-ligne';
        if ($etat === 'ECART_A_JUSTIFIER') {
            $classe .= ' is-alerte';
        } elseif ($etat === 'LTA_A_COMPLETER') {
            $classe .= ' is-attente';
        } elseif ($etat === 'EN_ATTENTE_LTA') {
            $classe .= ' is-grise';
        }

        $bouton = '<button type="button" class="lbp-rappro-declencheur" data-rappro-cible="rappro-panneau-' . $id . '" aria-expanded="false" aria-controls="rappro-panneau-' . $id . '">'
            . View::e(!empty($p['peutSaisir']) ? 'Rapprocher' : 'Détail') . '</button>';

        return '<tr class="' . $classe . '">'
            . '<td class="lbp-rappro-mono">' . View::e(self::date($ligne['date'])) . '</td>'
            . '<td>' . self::valeur($ligne['compagnie']) . '</td>'
            . '<td class="lbp-rappro-mono">' . self::valeur($ligne['document']) . '<span class="lbp-rappro-sous">' . View::e((string) $ligne['numero']) . '</span></td>'
            . '<td class="lbp-rappro-droite lbp-rappro-mono">' . self::nombreOuTiret($ligne['colis_agence']) . '</td>'
            . '<td class="lbp-rappro-droite lbp-rappro-mono">' . self::colisLta($ligne) . '</td>'
            . '<td class="lbp-rappro-droite">' . self::badgeEcart($ligne['ecart_colis'], '') . '</td>'
            . '<td class="lbp-rappro-droite lbp-rappro-mono">' . self::nombreOuTiret($ligne['poids_agence'], 1) . '</td>'
            . '<td class="lbp-rappro-droite lbp-rappro-mono">' . self::nombreOuTiret($ligne['poids_lta'], 1) . '</td>'
            . '<td class="lbp-rappro-droite">' . self::badgeEcart($ligne['ecart_poids'], ' kg', 1) . '</td>'
            . '<td class="lbp-rappro-droite lbp-rappro-mono">' . self::montant($ligne) . '</td>'
            . '<td>' . self::reglement($ligne) . '</td>'
            . '<td>' . Ui::badge(Regles::ETATS[$etat] ?? $etat, self::TONS_ETAT[$etat] ?? 'neutral') . ' ' . $bouton . '</td>'
            . '</tr>';
    }

    /** @param array<string, mixed> $ligne */
    private static function colisLta(array $ligne): string
    {
        if ($ligne['colis_lta'] === null) {
            return '<span class="lbp-rappro-manque">à saisir</span>';
        }

        return View::e(Regles::nombre((float) $ligne['colis_lta']))
            . ($ligne['colis_corrige'] ? '<span class="lbp-rappro-sous">corrigé</span>' : '');
    }

    /** @param array<string, mixed> $ligne */
    private static function montant(array $ligne): string
    {
        if ($ligne['montant_xof'] === null) {
            return '<span class="lbp-rappro-manque">à saisir</span>';
        }

        $sousLigne = (string) $ligne['devise'] === 'EUR'
            ? '<span class="lbp-rappro-sous">' . View::e(Regles::nombre((float) $ligne['montant'], 2)) . ' EUR</span>'
            : '';

        return View::e(Regles::nombre((float) $ligne['montant_xof']) . ' F') . $sousLigne;
    }

    /** @param array<string, mixed> $ligne */
    private static function reglement(array $ligne): string
    {
        if (empty($ligne['regle'])) {
            return '<span class="lbp-rappro-manque">Non réglé</span>';
        }

        $moyen = Regles::MODES_REGLEMENT[(string) $ligne['mode_reglement']] ?? (string) $ligne['mode_reglement'];
        $piece = trim((string) ($ligne['numero_cheque'] ?? ''));

        return '<span class="lbp-rappro-regle">' . View::e($moyen . ($piece !== '' ? ' ' . $piece : '')) . '</span>'
            . '<span class="lbp-rappro-sous">' . View::e(self::date($ligne['date_reglement'])) . '</span>';
    }

    /**
     * Panneau de saisie, replié sous sa ligne.
     *
     * @param array<string, mixed> $ligne
     * @param array<string, mixed> $p
     */
    private static function panneau(array $ligne, array $p): string
    {
        $id = (int) $ligne['dossier_id'];
        $f = $p['filtres'];
        $peutSaisir = !empty($p['peutSaisir']);

        $alerte = '';
        $ecartPoids = $ligne['ecart_poids'];
        if (is_array($ecartPoids) && $ecartPoids['depasse']) {
            $alerte = '<p class="lbp-rappro-alerte">' . self::icone('alerte')
                . View::e('Écart de poids de ' . Regles::signe((float) $ecartPoids['valeur'], 1) . ' kg'
                    . ($ecartPoids['pourcent'] === null ? '' : ', soit ' . Regles::nombre(abs((float) $ecartPoids['pourcent']), 1) . ' %')
                    . ' — au-delà du seuil de ' . Regles::nombre(Regles::SEUIL_POURCENT, 0) . ' %. Une justification est attendue.')
                . '</p>';
        }

        $rappel = '<div class="lbp-rappro-rappel">'
            . '<div><span>Saisie des agences</span><strong>' . View::e(self::nombreBrut($ligne['colis_agence']) . ' colis · ' . self::nombreBrut($ligne['poids_agence'], 1) . ' kg') . '</strong></div>'
            . '<div><span>Lu sur le document par l\'agent export</span><strong>' . View::e(self::nombreBrut($ligne['colis_declare']) . ' colis · ' . self::nombreBrut($ligne['poids_declare'], 1) . ' kg') . '</strong></div>'
            . ($ligne['rapproche_par'] ? '<div><span>Dernière saisie</span><strong>' . View::e((string) $ligne['rapproche_par'] . ' · ' . self::date($ligne['rapproche_le'])) . '</strong></div>' : '')
            . '</div>';

        if (!$peutSaisir) {
            $lecture = $rappel
                . '<div class="lbp-rappro-lecture">'
                . '<p><strong>Motif de la correction :</strong> ' . self::valeur($ligne['motif_correction']) . '</p>'
                . '<p><strong>Observation :</strong> ' . self::valeur($ligne['observation']) . '</p>'
                . '</div>';

            return '<tr class="lbp-rappro-panneau" id="rappro-panneau-' . $id . '" hidden><td colspan="12">' . $alerte . $lecture . '</td></tr>';
        }

        $devises = [];
        foreach (Regles::DEVISES as $code => $libelle) {
            $devises[] = ['value' => $code, 'label' => $libelle];
        }

        $modes = [['value' => '', 'label' => 'Aucun']];
        foreach (Regles::MODES_REGLEMENT as $code => $libelle) {
            $modes[] = ['value' => $code, 'label' => $libelle];
        }

        $champs = '<div class="lbp-rappro-champs">'
            . Form::input('colis_lta', ['label' => 'Colis sur la LTA', 'value' => self::champ($ligne['colis_lta']), 'id' => 'colis-lta-' . $id, 'inputmode' => 'numeric'])
            . Form::input('poids_lta_kg', ['label' => 'Poids final (kg)', 'value' => self::champ($ligne['poids_lta'], 1), 'id' => 'poids-lta-' . $id, 'inputmode' => 'decimal'])
            . Form::input('poids_divers_kg', ['label' => 'Dont poids divers (kg)', 'value' => self::champ($ligne['poids_divers'], 1), 'id' => 'divers-' . $id, 'inputmode' => 'decimal'])
            . Form::input('poids_perissable_kg', ['label' => 'Dont poids périssable (kg)', 'value' => self::champ($ligne['poids_perissable'], 1), 'id' => 'perissable-' . $id, 'inputmode' => 'decimal'])
            . Form::input('montant_compagnie', ['label' => 'Montant facturé', 'value' => self::champ($ligne['montant'], 2), 'id' => 'montant-' . $id, 'inputmode' => 'decimal'])
            . Form::select('devise_compagnie', $devises, (string) $ligne['devise'], ['label' => 'Devise', 'id' => 'devise-' . $id])
            . Form::select('mode_reglement', $modes, (string) ($ligne['mode_reglement'] ?? ''), ['label' => 'Moyen de règlement', 'id' => 'mode-' . $id])
            . Form::input('numero_cheque', ['label' => 'N° de chèque', 'value' => (string) ($ligne['numero_cheque'] ?? ''), 'id' => 'cheque-' . $id])
            . Form::input('date_reglement', ['label' => 'Date de règlement', 'type' => 'date', 'value' => (string) ($ligne['date_reglement'] ?? ''), 'id' => 'date-reglement-' . $id])
            . '</div>'
            . Form::input('motif_correction', ['label' => 'Motif de la correction — exigé si vous changez les colis ou le poids', 'value' => (string) ($ligne['motif_correction'] ?? ''), 'id' => 'motif-' . $id, 'placeholder' => 'LTA reçue par mail, poids relevé par la compagnie'])
            . Form::textarea('observation', ['label' => 'Observation', 'value' => (string) ($ligne['observation'] ?? ''), 'id' => 'observation-' . $id, 'rows' => 2]);

        $caches = '';
        foreach (['du', 'au', 'transporteur_id', 'agence_id', 'reglement', 'q'] as $champ) {
            $valeur = (string) ($f[$champ] ?? '');
            if ($valeur !== '' && $valeur !== '0') {
                $caches .= '<input type="hidden" name="f_' . $champ . '" value="' . View::e($valeur) . '">';
            }
        }

        $formulaire = '<form method="post" action="' . View::e(View::url('finance/rapprochement-envois/' . $id . '/enregistrer')) . '" class="lbp-rappro-formulaire">'
            . Csrf::field() . $caches . $champs
            . '<div class="lbp-rappro-actions">'
            . '<span class="lbp-rappro-note">La saisie des agences n\'est jamais écrasée : elle reste affichée à côté.</span>'
            . '<button type="submit" class="rh-filter-btn rh-filter-btn--primary">Enregistrer le rapprochement</button>'
            . '</div></form>';

        return '<tr class="lbp-rappro-panneau" id="rappro-panneau-' . $id . '" hidden><td colspan="12">' . $alerte . $rappel . $formulaire . '</td></tr>';
    }

    /** @param array<string, mixed> $t */
    private static function pied(array $t): string
    {
        return '<tfoot><tr>'
            . '<td colspan="3">' . View::e((int) $t['envois'] . ' envoi(s) · ' . (int) $t['documentes'] . ' documenté(s)') . '</td>'
            . '<td class="lbp-rappro-droite">' . View::e(Regles::nombre((float) $t['colis_agence'])) . '</td>'
            . '<td class="lbp-rappro-droite">' . View::e(Regles::nombre((float) $t['colis_lta'])) . '</td>'
            . '<td class="lbp-rappro-droite">' . View::e(Regles::signe((float) $t['ecart_colis'])) . '</td>'
            . '<td class="lbp-rappro-droite">' . View::e(Regles::nombre((float) $t['poids_agence'], 1)) . '</td>'
            . '<td class="lbp-rappro-droite">' . View::e(Regles::nombre((float) $t['poids_lta'], 1)) . '</td>'
            . '<td class="lbp-rappro-droite">' . View::e(Regles::signe((float) $t['ecart_poids'], 1) . ' kg') . '</td>'
            . '<td class="lbp-rappro-droite">' . View::e(Regles::nombre((float) $t['montant_xof']) . ' F') . '</td>'
            . '<td colspan="2">' . View::e('Reste à régler : ' . Regles::nombre((float) $t['reste_a_regler']) . ' F') . '</td>'
            . '</tr></tfoot>';
    }

    // ------------------------------------------------------------------
    // Exports
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $p */
    public static function exportPdf(array $p): string
    {
        $f = $p['filtres'];
        $t = $p['totaux'];

        $lignes = '';
        foreach ($p['lignes'] as $l) {
            $lignes .= '<tr>'
                . '<td>' . View::e(self::date($l['date'])) . '</td>'
                . '<td>' . self::valeur($l['compagnie']) . '</td>'
                . '<td class="mono">' . self::valeur($l['document']) . '</td>'
                . '<td class="num">' . self::nombreOuTiret($l['colis_agence']) . '</td>'
                . '<td class="num">' . self::nombreOuTiret($l['colis_lta']) . '</td>'
                . '<td class="num' . (($l['ecart_colis']['depasse'] ?? false) ? ' alerte' : '') . '">' . self::ecartTexte($l['ecart_colis']) . '</td>'
                . '<td class="num">' . self::nombreOuTiret($l['poids_agence'], 1) . '</td>'
                . '<td class="num">' . self::nombreOuTiret($l['poids_lta'], 1) . '</td>'
                . '<td class="num' . (($l['ecart_poids']['depasse'] ?? false) ? ' alerte' : '') . '">' . self::ecartTexte($l['ecart_poids'], 1) . '</td>'
                . '<td class="num">' . ($l['montant_xof'] === null ? '—' : View::e(Regles::nombre((float) $l['montant_xof']))) . '</td>'
                . '<td>' . ($l['regle'] ? View::e(self::date($l['date_reglement'])) : 'Non réglé') . '</td>'
                . '<td>' . View::e(Regles::ETATS[(string) $l['etat']] ?? (string) $l['etat']) . '</td>'
                . '</tr>';
        }

        if ($lignes === '') {
            $lignes = '<tr><td colspan="12" class="vide">Aucun envoi sur cette période.</td></tr>';
        }

        $pied = '<td colspan="3">' . (int) $t['envois'] . ' envoi(s)</td>'
            . '<td class="num">' . View::e(Regles::nombre((float) $t['colis_agence'])) . '</td>'
            . '<td class="num">' . View::e(Regles::nombre((float) $t['colis_lta'])) . '</td>'
            . '<td class="num">' . View::e(Regles::signe((float) $t['ecart_colis'])) . '</td>'
            . '<td class="num">' . View::e(Regles::nombre((float) $t['poids_agence'], 1)) . '</td>'
            . '<td class="num">' . View::e(Regles::nombre((float) $t['poids_lta'], 1)) . '</td>'
            . '<td class="num">' . View::e(Regles::signe((float) $t['ecart_poids'], 1)) . '</td>'
            . '<td class="num">' . View::e(Regles::nombre((float) $t['montant_xof'])) . '</td>'
            . '<td colspan="2">Reste à régler : ' . View::e(Regles::nombre((float) $t['reste_a_regler'])) . ' F</td>';

        $entetes = ['Date', 'Compagnie', 'N° envoi / LTA', 'Colis agence', 'Colis LTA', 'Écart colis',
            'Poids agence', 'Poids LTA', 'Écart poids', 'Montant compagnie', 'Règlement', 'État'];

        return '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">'
            . '<title>Rapprochement des envois du ' . View::e(self::date($f['du'])) . ' au ' . View::e(self::date($f['au'])) . '</title>'
            . '<style>'
            . '@page{size:A4 landscape;margin:12mm}'
            . 'body{font-family:"Segoe UI",Arial,sans-serif;color:#0f172a;margin:0;font-size:11px}'
            . '.entete{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #0f172a;padding-bottom:8px;margin-bottom:12px}'
            . '.sur-titre{margin:0;font-size:10px;letter-spacing:.08em;text-transform:uppercase;color:#64748b}'
            . 'h1{margin:2px 0 0;font-size:19px}'
            . '.meta{text-align:right;font-size:10px;color:#475569;line-height:1.5}'
            . 'table{width:100%;border-collapse:collapse}'
            . 'th{background:#0f172a;color:#fff;padding:6px 5px;font-size:9px;text-transform:uppercase;letter-spacing:.04em;text-align:left}'
            . 'td{padding:5px;border-bottom:1px solid #e2e8f0}'
            . '.num{text-align:right;font-variant-numeric:tabular-nums}'
            . '.mono{font-family:Consolas,monospace}'
            . '.alerte{color:#b42318;font-weight:700}'
            . '.vide{text-align:center;color:#64748b;padding:18px}'
            . 'tfoot td{border-top:2px solid #0f172a;font-weight:700;background:#f8fafc}'
            . '.note{margin-top:10px;font-size:9px;color:#64748b}'
            . '.impression{margin:14px 0;text-align:center}'
            . 'button{padding:9px 18px;border:0;border-radius:6px;background:#0f172a;color:#fff;font-size:12px;font-weight:700;cursor:pointer}'
            . '@media print{.impression{display:none}}'
            . '</style></head><body>'
            . '<div class="impression"><button type="button" onclick="window.print()">Imprimer ou enregistrer en PDF</button></div>'
            . '<header class="entete"><div><p class="sur-titre">LBP · Finance</p><h1>Rapprochement des envois</h1></div>'
            . '<div class="meta">Période du ' . View::e(self::date($f['du'])) . ' au ' . View::e(self::date($f['au'])) . '<br>'
            . 'Édité le ' . View::e(date('d/m/Y à H:i')) . '<br>' . View::e((string) ($p['edite_par'] ?? '')) . '</div></header>'
            . '<table><thead><tr>' . implode('', array_map(static fn (string $e): string => '<th>' . View::e($e) . '</th>', $entetes)) . '</tr></thead>'
            . '<tbody>' . $lignes . '</tbody><tfoot><tr>' . $pied . '</tr></tfoot></table>'
            . '<p class="note">Les écarts ne portent que sur les départs dont le document de la compagnie est renseigné. '
            . 'Seuil d\'alerte ' . View::e(Regles::nombre(Regles::SEUIL_POURCENT, 0)) . ' %. Montants convertis au taux figé de chaque départ.</p>'
            . '</body></html>';
    }

    /** @param array<string, mixed> $p */
    public static function exportExcel(array $p): string
    {
        $entetes = ['Date', 'Compagnie', 'N° envoi / LTA', 'N° de départ', 'Agence de départ', 'Destination',
            'Colis agence', 'Colis LTA', 'Écart colis', 'Poids agence kg', 'Poids LTA kg', 'Écart poids kg', 'Écart poids %',
            'Poids divers kg', 'Poids périssable kg',
            'Montant compagnie', 'Devise', 'Montant XOF', 'Taux',
            'Moyen de règlement', 'N° de chèque', 'Date de règlement', 'Reste à régler XOF',
            'État', 'Motif de la correction', 'Observation', 'Rapproché par', 'Rapproché le'];

        $lignes = '';
        foreach ($p['lignes'] as $l) {
            $lignes .= '<tr>'
                . self::xDate($l['date'])
                . self::xTexte((string) $l['compagnie'])
                . self::xTexte((string) $l['document'])
                . self::xTexte((string) $l['numero'])
                . self::xTexte((string) $l['agence_depart'])
                . self::xTexte((string) $l['agence_arrivee'])
                . self::xNombre($l['colis_agence'])
                . self::xNombre($l['colis_lta'])
                . self::xNombre($l['ecart_colis']['valeur'] ?? null)
                . self::xNombre($l['poids_agence'], 1)
                . self::xNombre($l['poids_lta'], 1)
                . self::xNombre($l['ecart_poids']['valeur'] ?? null, 1)
                . self::xNombre($l['ecart_poids']['pourcent'] ?? null, 1)
                . self::xNombre($l['poids_divers'], 1)
                . self::xNombre($l['poids_perissable'], 1)
                . self::xNombre($l['montant'], 2)
                . self::xTexte((string) $l['devise'])
                . self::xNombre($l['montant_xof'], 2)
                . self::xNombre($l['taux'], 3)
                . self::xTexte(Regles::MODES_REGLEMENT[(string) $l['mode_reglement']] ?? '')
                . self::xTexte((string) ($l['numero_cheque'] ?? ''))
                . self::xDate($l['date_reglement'])
                . self::xNombre($l['reste_a_regler'], 2)
                . self::xTexte(Regles::ETATS[(string) $l['etat']] ?? (string) $l['etat'])
                . self::xTexte((string) ($l['motif_correction'] ?? ''))
                . self::xTexte((string) ($l['observation'] ?? ''))
                . self::xTexte((string) ($l['rapproche_par'] ?? ''))
                . self::xTexte((string) ($l['rapproche_le'] ?? ''))
                . '</tr>';
        }

        $f = $p['filtres'];

        return '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="UTF-8">'
            . '<style>td{mso-number-format:"\@"}th{background:#0f172a;color:#fff;font-weight:bold}</style></head><body>'
            . '<table border="1">'
            . '<tr><td colspan="6"><b>Rapprochement des envois — LBP</b></td></tr>'
            . '<tr><td colspan="6">Période du ' . View::e(self::date($f['du'])) . ' au ' . View::e(self::date($f['au']))
            . ' · édité le ' . View::e(date('d/m/Y H:i')) . ' par ' . View::e((string) ($p['edite_par'] ?? '')) . '</td></tr>'
            . '<tr></tr>'
            . '<tr>' . implode('', array_map(static fn (string $e): string => '<th>' . View::e($e) . '</th>', $entetes)) . '</tr>'
            . $lignes
            . '</table></body></html>';
    }

    // ------------------------------------------------------------------
    // Petits rendus
    // ------------------------------------------------------------------

    /** @param array{valeur:float, pourcent:?float, depasse:bool}|null $ecart */
    private static function badgeEcart(?array $ecart, string $unite, int $decimales = 0): string
    {
        if ($ecart === null) {
            return '<span class="lbp-rappro-vide">—</span>';
        }

        $texte = Regles::signe((float) $ecart['valeur'], $decimales) . $unite;
        if ($ecart['pourcent'] !== null && abs((float) $ecart['pourcent']) > 0) {
            $texte .= ' · ' . Regles::nombre(abs((float) $ecart['pourcent']), 1) . ' %';
        }

        $ton = $ecart['depasse'] ? 'danger' : (abs((float) $ecart['valeur']) > 0 ? 'warning' : 'success');

        return Ui::badge($texte, $ton);
    }

    /** @param array{valeur:float, pourcent:?float, depasse:bool}|null $ecart */
    private static function ecartTexte(?array $ecart, int $decimales = 0): string
    {
        return $ecart === null ? '—' : View::e(Regles::signe((float) $ecart['valeur'], $decimales));
    }

    private static function nombreOuTiret(mixed $valeur, int $decimales = 0): string
    {
        return $valeur === null ? '<span class="lbp-rappro-vide">—</span>' : View::e(Regles::nombre((float) $valeur, $decimales));
    }

    private static function nombreBrut(mixed $valeur, int $decimales = 0): string
    {
        return $valeur === null ? '—' : Regles::nombre((float) $valeur, $decimales);
    }

    private static function champ(mixed $valeur, int $decimales = 0): string
    {
        if ($valeur === null) {
            return '';
        }

        return $decimales > 0 ? rtrim(rtrim(number_format((float) $valeur, $decimales, '.', ''), '0'), '.') : (string) (int) $valeur;
    }

    private static function valeur(mixed $valeur): string
    {
        $texte = trim((string) ($valeur ?? ''));

        return $texte === '' ? '—' : View::e($texte);
    }

    private static function date(mixed $valeur): string
    {
        $texte = trim((string) ($valeur ?? ''));

        if ($texte === '') {
            return '—';
        }

        $date = date_create($texte);

        return $date === false ? $texte : $date->format('d/m/Y');
    }

    private static function icone(string $nom, int $taille = 15): string
    {
        return '<svg class="lbp-rappro-icone" width="' . $taille . '" height="' . $taille . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . (self::ICONES[$nom] ?? '') . '</svg>';
    }

    private static function xTexte(string $valeur): string
    {
        return '<td>' . View::e($valeur) . '</td>';
    }

    private static function xNombre(mixed $valeur, int $decimales = 0): string
    {
        if ($valeur === null) {
            return '<td></td>';
        }

        $brut = number_format((float) $valeur, $decimales, '.', '');

        return '<td x:num="' . View::e($brut) . '" style="mso-number-format:General">' . View::e($brut) . '</td>';
    }

    private static function xDate(mixed $valeur): string
    {
        return '<td>' . View::e(self::date($valeur) === '—' ? '' : self::date($valeur)) . '</td>';
    }

    // ------------------------------------------------------------------
    // Habillage
    // ------------------------------------------------------------------

    private static function styles(): string
    {
        return '<style>'
            . '.lbp-rappro{--rappro-alerte:#b42318;--rappro-attente:#b54708;--rappro-ok:#027a48;--rappro-trait:#e3e6ea}'
            . '.lbp-rappro-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;margin:18px 0}'
            . '.lbp-rappro-kpi{background:#fff;border:1px solid var(--rappro-trait);border-radius:14px;padding:16px 18px;display:flex;flex-direction:column;gap:6px}'
            . '.lbp-rappro-kpi strong{font-size:27px;font-weight:700;letter-spacing:-.02em;line-height:1.1}'
            . '.lbp-rappro-kpi-libelle{font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#5b6472}'
            . '.lbp-rappro-kpi-detail{font-size:12px;color:#5b6472}'
            . '.lbp-rappro-kpi.is-alerte strong{color:var(--rappro-alerte)}'
            . '.lbp-rappro-kpi.is-sombre{background:#0f172a;border-color:#0f172a}'
            . '.lbp-rappro-kpi.is-sombre strong{color:#fff}'
            . '.lbp-rappro-kpi.is-sombre .lbp-rappro-kpi-libelle,.lbp-rappro-kpi.is-sombre .lbp-rappro-kpi-detail{color:#94a3b8}'
            . '.lbp-rappro-filtres-grille{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}'
            . '.lbp-rappro-bascule{display:inline-flex;align-items:center;gap:8px;font-size:13px;font-weight:700;color:#0f766e;padding:0 14px;min-height:42px;border:1px solid #0f766e;border-radius:10px;background:#f0fdfa}'
            . '.lbp-rappro-table-enveloppe{overflow-x:auto}'
            . '.lbp-rappro-table{width:100%;font-size:13px}'
            . '.lbp-rappro-table th{white-space:nowrap}'
            . '.lbp-rappro-droite{text-align:right}'
            . '.lbp-rappro-mono{font-family:Consolas,"SF Mono",monospace;font-variant-numeric:tabular-nums}'
            . '.lbp-rappro-sous{display:block;font-size:11px;color:#8b94a1;font-family:inherit}'
            . '.lbp-rappro-vide,.lbp-rappro-manque{color:#8b94a1}'
            . '.lbp-rappro-manque{font-weight:700;color:var(--rappro-attente)}'
            . '.lbp-rappro-regle{font-weight:700;color:var(--rappro-ok)}'
            . '.lbp-rappro-ligne.is-alerte{background:#fef6f5;box-shadow:inset 3px 0 0 var(--rappro-alerte)}'
            . '.lbp-rappro-ligne.is-attente{background:#fffbf5}'
            . '.lbp-rappro-ligne.is-grise td{color:#5b6472}'
            . '.lbp-rappro-declencheur{margin-left:6px;padding:3px 10px;border:1px solid #cbd5e1;border-radius:999px;background:#fff;font-size:11px;font-weight:700;cursor:pointer}'
            . '.lbp-rappro-declencheur[aria-expanded="true"]{background:#0f172a;color:#fff;border-color:#0f172a}'
            . '.lbp-rappro-panneau>td{background:#f8fafb;padding:18px 20px}'
            . '.lbp-rappro-alerte{display:flex;align-items:center;gap:8px;margin:0 0 14px;padding:10px 14px;border:1px solid #fecdca;border-radius:10px;background:#fef3f2;color:var(--rappro-alerte);font-size:13px;font-weight:700}'
            . '.lbp-rappro-rappel{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-bottom:14px}'
            . '.lbp-rappro-rappel div{background:#fff;border:1px solid var(--rappro-trait);border-radius:10px;padding:10px 14px}'
            . '.lbp-rappro-rappel span{display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#5b6472}'
            . '.lbp-rappro-rappel strong{font-size:15px}'
            . '.lbp-rappro-champs{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px;margin-bottom:12px}'
            . '.lbp-rappro-actions{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-top:12px;flex-wrap:wrap}'
            . '.lbp-rappro-note{font-size:12px;color:#5b6472}'
            . '.lbp-rappro-lecture p{margin:4px 0;font-size:13px}'
            . '.lbp-rappro-icone{flex-shrink:0}'
            . '</style>';
    }

    private static function script(): string
    {
        return '<script>(function(){'
            . 'document.querySelectorAll("[data-rappro-cible]").forEach(function(bouton){'
            . 'bouton.addEventListener("click",function(){'
            . 'var panneau=document.getElementById(bouton.getAttribute("data-rappro-cible"));'
            . 'if(!panneau){return;}'
            . 'var ouvert=panneau.hasAttribute("hidden");'
            . 'if(ouvert){panneau.removeAttribute("hidden");}else{panneau.setAttribute("hidden","hidden");}'
            . 'bouton.setAttribute("aria-expanded",ouvert?"true":"false");'
            . '});});})();</script>';
    }
}
