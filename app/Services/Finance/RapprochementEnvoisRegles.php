<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Services\Colisage\DossierEnvoiRegles as Envois;

/**
 * Rapprochement des envois : ce que les agences ont enregistré, face au
 * document de la compagnie.
 *
 * Deux règles portent tout le reste, et viennent de la lecture du fichier Excel
 * que la direction tenait à la main :
 *
 * 1. Un départ dont la LTA n'est pas encore arrivée n'est pas un écart. Dans le
 *    tableur, la soustraction d'une cellule vide affichait « −230 colis » ou
 *    « −4 454 kg » et gonflait les totaux ; ici la ligne est en attente, et
 *    sort des cumuls.
 * 2. La valeur retenue par le comptable ne remplace jamais la saisie des
 *    agences : les deux restent côte à côte, sinon l'écart que la direction
 *    contrôle disparaît.
 *
 * Classe sans état ni base : elle se teste seule.
 */
final class RapprochementEnvoisRegles
{
    /** Au-delà, l'écart doit être justifié. Décidé avec la direction le 15/09/2026. */
    public const SEUIL_POURCENT = Envois::SEUIL_ECART_SAISIE_POURCENT;

    public const TAUX_DEFAUT = Envois::TAUX_EUR_XOF_DEFAUT;

    /** @var array<string, string> */
    public const ETATS = [
        'EN_ATTENTE_LTA' => 'En attente de LTA',
        'LTA_A_COMPLETER' => 'LTA à compléter',
        'ECART_A_JUSTIFIER' => 'Écart à justifier',
        'ECART_JUSTIFIE' => 'Écart justifié',
        'RAPPROCHE' => 'Rapproché',
    ];

    /** États dont les chiffres entrent dans les cumuls : le départ est documenté. */
    public const ETATS_COMPTES = ['ECART_A_JUSTIFIER', 'ECART_JUSTIFIE', 'RAPPROCHE'];

    /** @var array<string, string> */
    public const MODES_REGLEMENT = [
        'CHEQUE' => 'Chèque',
        'VIREMENT' => 'Virement',
        'ESPECES' => 'Espèces',
    ];

    /** @var array<string, string> */
    public const DEVISES = ['EUR' => 'EUR', 'XOF' => 'FCFA'];

    // ------------------------------------------------------------------
    // Une ligne du tableau
    // ------------------------------------------------------------------

    /**
     * Compose une ligne d'écran à partir du départ et de son rapprochement.
     *
     * @param array<string, mixed> $ligne départ et colonnes r_* du rapprochement
     * @return array<string, mixed>
     */
    public static function composer(array $ligne): array
    {
        $colisAgence = self::entier($ligne['colis_erp'] ?? null);
        $poidsAgence = self::decimal($ligne['poids_erp_kg'] ?? null);

        // La valeur du comptable prime sur celle que l'agent export a lue sur
        // le document, sans l'effacer : les deux voyagent dans la ligne.
        $colisDeclare = self::entier($ligne['nb_colis_declare'] ?? null);
        $poidsDeclare = self::decimal($ligne['poids_brut_kg'] ?? null);
        $colisCorrige = self::entier($ligne['r_colis_lta'] ?? null);
        $poidsCorrige = self::decimal($ligne['r_poids_lta_kg'] ?? null);

        $colisLta = $colisCorrige ?? $colisDeclare;
        $poidsLta = $poidsCorrige ?? $poidsDeclare;

        $taux = self::taux($ligne);
        $montant = self::decimal($ligne['r_montant_compagnie'] ?? null);
        $devise = (string) ($ligne['r_devise_compagnie'] ?? 'EUR');
        $montantXof = $montant === null ? null : ($devise === 'EUR' ? round($montant * $taux, 2) : $montant);

        $ecartColis = $colisLta === null || $colisAgence === null ? null : self::ecart((float) $colisLta, (float) $colisAgence);
        $ecartPoids = $poidsLta === null || $poidsAgence === null ? null : self::ecart($poidsLta, $poidsAgence, 1);

        $regle = !empty($ligne['r_date_reglement']);
        $justifie = trim((string) ($ligne['r_observation'] ?? '')) !== ''
            || trim((string) ($ligne['r_motif_correction'] ?? '')) !== '';

        return [
            'dossier_id' => (int) $ligne['id'],
            'numero' => (string) ($ligne['numero'] ?? ''),
            'date' => $ligne['date_reference'] ?? $ligne['date_depart_effective'] ?? null,
            'compagnie' => (string) ($ligne['transporteur'] ?? ''),
            'document' => (string) ($ligne['numero_document'] ?? ''),
            'agence_depart' => (string) ($ligne['agence_depart'] ?? ''),
            'agence_arrivee' => (string) ($ligne['agence_arrivee'] ?? ''),

            'colis_agence' => $colisAgence,
            'colis_lta' => $colisLta,
            'colis_declare' => $colisDeclare,
            'colis_corrige' => $colisCorrige !== null && $colisCorrige !== $colisDeclare,
            'ecart_colis' => $ecartColis,

            'poids_agence' => $poidsAgence,
            'poids_lta' => $poidsLta,
            'poids_declare' => $poidsDeclare,
            'poids_corrige' => $poidsCorrige !== null && $poidsCorrige !== $poidsDeclare,
            'ecart_poids' => $ecartPoids,

            'poids_divers' => self::decimal($ligne['r_poids_divers_kg'] ?? null),
            'poids_perissable' => self::decimal($ligne['r_poids_perissable_kg'] ?? null),

            'montant' => $montant,
            'devise' => $devise,
            'montant_xof' => $montantXof,
            'taux' => $taux,

            'mode_reglement' => $ligne['r_mode_reglement'] ?? null,
            'numero_cheque' => $ligne['r_numero_cheque'] ?? null,
            'date_reglement' => $ligne['r_date_reglement'] ?? null,
            'regle' => $regle,
            'reste_a_regler' => $regle ? 0.0 : (float) ($montantXof ?? 0),

            'motif_correction' => $ligne['r_motif_correction'] ?? null,
            'observation' => $ligne['r_observation'] ?? null,
            'rapproche_par' => $ligne['r_rapproche_par_nom'] ?? null,
            'rapproche_le' => $ligne['r_rapproche_le'] ?? null,

            'etat' => self::etat($colisLta, $poidsLta, $ecartColis, $ecartPoids, $justifie),
        ];
    }

    /**
     * @param array{valeur:float, pourcent:?float, depasse:bool}|null $ecartColis
     * @param array{valeur:float, pourcent:?float, depasse:bool}|null $ecartPoids
     */
    private static function etat(?int $colisLta, ?float $poidsLta, ?array $ecartColis, ?array $ecartPoids, bool $justifie): string
    {
        if ($colisLta === null && $poidsLta === null) {
            return 'EN_ATTENTE_LTA';
        }

        if ($colisLta === null || $poidsLta === null) {
            return 'LTA_A_COMPLETER';
        }

        $depasse = (bool) ($ecartColis['depasse'] ?? false) || (bool) ($ecartPoids['depasse'] ?? false);

        if (!$depasse) {
            return 'RAPPROCHE';
        }

        return $justifie ? 'ECART_JUSTIFIE' : 'ECART_A_JUSTIFIER';
    }

    /**
     * Écart entre le document de la compagnie et la saisie des agences.
     *
     * @return array{valeur:float, pourcent:?float, depasse:bool}
     */
    public static function ecart(float $lta, float $agence, int $decimales = 0): array
    {
        $valeur = round($lta - $agence, $decimales);
        $pourcent = $agence > 0 ? round($valeur * 100 / $agence, 1) : null;

        return [
            'valeur' => $valeur,
            'pourcent' => $pourcent,
            // Sans base de comparaison, tout écart compte : rien n'a été
            // enregistré alors que la compagnie a bien emporté quelque chose.
            'depasse' => $pourcent === null ? abs($valeur) > 0 : abs($pourcent) > self::SEUIL_POURCENT,
        ];
    }

    // ------------------------------------------------------------------
    // Cumuls
    // ------------------------------------------------------------------

    /**
     * Totaux de la période. Les départs sans document n'y entrent pas : c'est
     * ce qui faussait le tableur.
     *
     * @param array<int, array<string, mixed>> $lignes
     * @return array<string, mixed>
     */
    public static function totaux(array $lignes): array
    {
        $t = [
            'envois' => count($lignes),
            'documentes' => 0,
            'en_attente' => 0,
            'a_completer' => 0,
            'a_justifier' => 0,
            'colis_agence' => 0,
            'colis_lta' => 0,
            'ecart_colis' => 0,
            'poids_agence' => 0.0,
            'poids_lta' => 0.0,
            'ecart_poids' => 0.0,
            'montant_xof' => 0.0,
            'reste_a_regler' => 0.0,
            'regles' => 0,
        ];

        foreach ($lignes as $ligne) {
            $etat = (string) $ligne['etat'];

            if ($etat === 'EN_ATTENTE_LTA') {
                $t['en_attente']++;
            }
            if ($etat === 'LTA_A_COMPLETER') {
                $t['a_completer']++;
            }
            if ($etat === 'ECART_A_JUSTIFIER') {
                $t['a_justifier']++;
            }

            $t['montant_xof'] += (float) ($ligne['montant_xof'] ?? 0);
            $t['reste_a_regler'] += (float) $ligne['reste_a_regler'];
            $t['regles'] += $ligne['regle'] ? 1 : 0;

            if (!in_array($etat, self::ETATS_COMPTES, true)) {
                continue;
            }

            $t['documentes']++;
            $t['colis_agence'] += (int) ($ligne['colis_agence'] ?? 0);
            $t['colis_lta'] += (int) ($ligne['colis_lta'] ?? 0);
            $t['ecart_colis'] += (int) ($ligne['ecart_colis']['valeur'] ?? 0);
            $t['poids_agence'] += (float) ($ligne['poids_agence'] ?? 0);
            $t['poids_lta'] += (float) ($ligne['poids_lta'] ?? 0);
            $t['ecart_poids'] += (float) ($ligne['ecart_poids']['valeur'] ?? 0);
        }

        $t['poids_agence'] = round($t['poids_agence'], 1);
        $t['poids_lta'] = round($t['poids_lta'], 1);
        $t['ecart_poids'] = round($t['ecart_poids'], 1);

        return $t;
    }

    // ------------------------------------------------------------------
    // Lecture d'une saisie
    // ------------------------------------------------------------------

    /**
     * Contrôle la saisie du comptable et la met en forme pour l'écriture.
     *
     * @param array<string, mixed> $saisie
     * @param array<string, mixed> $ligne ligne composée avant la saisie
     * @return array{valeurs:array<string, mixed>, erreurs:array<int, string>}
     */
    public static function lireSaisie(array $saisie, array $ligne): array
    {
        $erreurs = [];

        $colis = self::entierSaisi($saisie['colis_lta'] ?? null);
        $poids = self::decimalSaisi($saisie['poids_lta_kg'] ?? null);
        $montant = self::decimalSaisi($saisie['montant_compagnie'] ?? null);
        $devise = (string) ($saisie['devise_compagnie'] ?? 'EUR');
        $devise = isset(self::DEVISES[$devise]) ? $devise : 'EUR';

        foreach ([['colis_lta', $colis, 'Le nombre de colis'], ['poids_lta_kg', $poids, 'Le poids'], ['montant_compagnie', $montant, 'Le montant']] as [$champ, $valeur, $libelle]) {
            if ($valeur !== null && $valeur < 0) {
                $erreurs[] = $libelle . ' de la compagnie ne peut pas être négatif.';
            }
        }

        // Corriger sans dire pourquoi reviendrait à effacer l'écart en silence.
        $motif = trim((string) ($saisie['motif_correction'] ?? ''));
        $corrige = ($colis !== null && $colis !== self::entier($ligne['colis_declare'] ?? null))
            || ($poids !== null && $poids !== self::decimal($ligne['poids_declare'] ?? null));

        if ($corrige && $motif === '') {
            $erreurs[] = "Corriger les colis ou le poids du document exige un motif : c'est lui que la direction lira.";
        }

        $mode = (string) ($saisie['mode_reglement'] ?? '');
        $mode = isset(self::MODES_REGLEMENT[$mode]) ? $mode : null;
        $dateReglement = self::date($saisie['date_reglement'] ?? null);
        $cheque = trim((string) ($saisie['numero_cheque'] ?? ''));

        if ($dateReglement !== null && $mode === null) {
            $erreurs[] = 'Un règlement daté doit indiquer son moyen : chèque, virement ou espèces.';
        }
        if ($dateReglement !== null && $montant === null && ($ligne['montant'] ?? null) === null) {
            $erreurs[] = "Un règlement sans montant facturé ne veut rien dire : saisissez d'abord le montant de la compagnie.";
        }
        if ($mode === 'CHEQUE' && $dateReglement !== null && $cheque === '') {
            $erreurs[] = 'Un règlement par chèque doit porter son numéro.';
        }

        return [
            'valeurs' => [
                'colis_lta' => $colis,
                'poids_lta_kg' => $poids,
                'motif_correction' => $motif === '' ? null : $motif,
                'poids_divers_kg' => self::decimalSaisi($saisie['poids_divers_kg'] ?? null),
                'poids_perissable_kg' => self::decimalSaisi($saisie['poids_perissable_kg'] ?? null),
                'montant_compagnie' => $montant,
                'devise_compagnie' => $devise,
                'taux_eur_xof' => self::taux($ligne),
                'mode_reglement' => $mode,
                'numero_cheque' => $cheque === '' ? null : $cheque,
                'date_reglement' => $dateReglement,
                'observation' => trim((string) ($saisie['observation'] ?? '')) ?: null,
            ],
            'erreurs' => $erreurs,
        ];
    }

    // ------------------------------------------------------------------
    // Petits utilitaires de lecture
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $ligne */
    private static function taux(array $ligne): float
    {
        foreach (['r_taux_eur_xof', 'taux_eur_xof', 'taux'] as $champ) {
            if ((float) ($ligne[$champ] ?? 0) > 0) {
                return (float) $ligne[$champ];
            }
        }

        return self::TAUX_DEFAUT;
    }

    private static function entier(mixed $valeur): ?int
    {
        return $valeur === null || $valeur === '' ? null : (int) $valeur;
    }

    private static function decimal(mixed $valeur): ?float
    {
        return $valeur === null || $valeur === '' ? null : (float) $valeur;
    }

    /** Une case laissée vide n'est pas un zéro : elle reste inconnue. */
    private static function entierSaisi(mixed $valeur): ?int
    {
        $texte = str_replace([' ', "\u{202f}", "\u{a0}"], '', (string) ($valeur ?? ''));

        return $texte === '' ? null : (int) $texte;
    }

    private static function decimalSaisi(mixed $valeur): ?float
    {
        $texte = str_replace([' ', "\u{202f}", "\u{a0}", ','], ['', '', '', '.'], (string) ($valeur ?? ''));

        return $texte === '' ? null : (float) $texte;
    }

    private static function date(mixed $valeur): ?string
    {
        $texte = trim((string) ($valeur ?? ''));

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $texte) === 1 ? $texte : null;
    }

    // ------------------------------------------------------------------
    // Mise en forme
    // ------------------------------------------------------------------

    public static function nombre(float $valeur, int $decimales = 0): string
    {
        return Envois::nombre($valeur, $decimales);
    }

    public static function signe(?float $valeur, int $decimales = 0): string
    {
        if ($valeur === null) {
            return '—';
        }

        return ($valeur > 0 ? '+' : '') . self::nombre($valeur, $decimales);
    }
}
