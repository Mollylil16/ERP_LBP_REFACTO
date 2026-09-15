<?php

declare(strict_types=1);

namespace App\Services\Colisage;

use DateTimeImmutable;

/**
 * Règles du dossier d'envoi, sans base de données.
 *
 * Arrêtées le 15/09/2026 avec la direction, après une première version jugée
 * trop lourde :
 * - « Préparer un départ » ne coche plus de colis. L'agent export saisit ce
 *   que dit le document de la compagnie, en dix colonnes ; les colis
 *   enregistrés pour la destination et pas encore partis partent d'office ;
 * - le Directeur général compare ce document à la saisie des colis. Au-delà de
 *   2 % d'écart sur le nombre de colis ou le poids, il ne valide qu'avec un
 *   commentaire : c'est ainsi que se voit un colis parti sans être enregistré ;
 * - l'agent export ne voit jamais les chiffres de la saisie, pour ne pas les
 *   recopier à la place de ceux du document.
 */
final class DossierEnvoiRegles
{
    /** Les dix colonnes demandées par LBP, dans leur ordre. */
    public const COLONNES = [
        1 => 'Date de départ',
        2 => 'Compagnie',
        3 => 'LTA',
        4 => 'Nombre de colis',
        5 => 'Poids total',
        6 => 'Transitaire au départ',
        7 => 'Transitaire à destination',
        8 => 'Livraison au départ',
        9 => "Livraison à l'arrivée",
        10 => 'Emballages',
    ];

    public const MODES = [
        'AERIEN' => 'Aérien',
        'MARITIME' => 'Maritime',
        'ROUTIER' => 'Routier',
        'EXPRESS' => 'Express (DHL)',
    ];

    /** Nom du document de la compagnie, selon le mode : la colonne 3. */
    public const DOCUMENT_DU_MODE = [
        'AERIEN' => 'LTA',
        'MARITIME' => 'Connaissement (BL)',
        'ROUTIER' => 'Lettre de voiture',
        'EXPRESS' => 'AWB DHL',
    ];

    public const TYPE_DOCUMENT_DU_MODE = [
        'AERIEN' => 'LTA_DIRECTE',
        'MARITIME' => 'BL',
        'ROUTIER' => 'LETTRE_VOITURE',
        'EXPRESS' => 'AWB_EXPRESS',
    ];

    /** Valeur de lbp_expeditions.type_transport donnée au départ du pointage. */
    public const TRANSPORT_DU_MODE = [
        'AERIEN' => 'AÉRIEN',
        'MARITIME' => 'MARITIME',
        'ROUTIER' => 'TERRESTRE',
        'EXPRESS' => 'AÉRIEN',
    ];

    /** Type de prestataire attendu comme compagnie, pour chaque mode. */
    public const TRANSPORTEUR_DU_MODE = [
        'AERIEN' => 'COMPAGNIE_AERIENNE',
        'MARITIME' => 'COMPAGNIE_MARITIME',
        'ROUTIER' => 'TRANSPORTEUR_ROUTIER',
        'EXPRESS' => 'EXPRESS',
    ];

    /** Valeurs de lbp_prestataires.type, avec leur libellé. */
    public const TYPES_PRESTATAIRE = [
        'COMPAGNIE_AERIENNE' => 'Compagnie aérienne',
        'COMPAGNIE_MARITIME' => 'Compagnie maritime',
        'TRANSPORTEUR_ROUTIER' => 'Transporteur routier',
        'EXPRESS' => 'Express (DHL)',
        'TRANSITAIRE' => 'Transitaire',
        'LIVREUR' => 'Livreur',
        'DOUANE' => 'Douane',
        'FOURNISSEUR_MATERIEL' => 'Fournisseur de matériel',
        'AUTRE' => 'Autre',
    ];

    /** Les quatre postes de frais : colonnes 6 à 9. */
    public const POSTES = [
        'TRANSIT_DEPART' => 'Transitaire au départ',
        'TRANSIT_ARRIVEE' => 'Transitaire à destination',
        'LIVRAISON_DEPART' => 'Livraison au départ',
        'LIVRAISON_ARRIVEE' => "Livraison à l'arrivée",
    ];

    public const COLONNE_DU_POSTE = [
        'TRANSIT_DEPART' => 6,
        'TRANSIT_ARRIVEE' => 7,
        'LIVRAISON_DEPART' => 8,
        'LIVRAISON_ARRIVEE' => 9,
    ];

    public const EMBALLAGES = ['Carton', 'Bôrô', 'Valise', 'Sac', 'Palette', 'Fût', 'Autre'];

    public const DEVISES = ['XOF', 'EUR'];

    public const STATUTS = [
        'EN_COURS' => 'En cours',
        'SOUMIS' => 'Soumis au DG',
        'A_CORRIGER' => 'À corriger',
        'VALIDE' => 'Validé',
        'REPRIS' => 'Repris',
    ];

    /** Statuts dans lesquels l'agent export complète encore son départ. */
    public const STATUTS_MODIFIABLES = ['EN_COURS', 'A_CORRIGER'];

    public const PIECES = [
        'PIECE_TRANSPORT' => 'Document de la compagnie (LTA, BL…)',
        'FACTURE_TRANSIT_DEPART' => 'Facture du transitaire au départ',
        'FACTURE_TRANSIT_ARRIVEE' => 'Facture du transitaire à destination',
        'BON_LIVRAISON_DEPART' => 'Facture de livraison au départ',
        'BON_LIVRAISON_ARRIVEE' => "Facture de livraison à l'arrivée",
        'MANIFESTE' => 'Manifeste',
        'AUTRE' => 'Autre document',
    ];

    /** Pièce qui porte le montant facturé de chaque poste. */
    public const PIECE_DU_POSTE = [
        'TRANSIT_DEPART' => 'FACTURE_TRANSIT_DEPART',
        'TRANSIT_ARRIVEE' => 'FACTURE_TRANSIT_ARRIVEE',
        'LIVRAISON_DEPART' => 'BON_LIVRAISON_DEPART',
        'LIVRAISON_ARRIVEE' => 'BON_LIVRAISON_ARRIVEE',
    ];

    /** Au-delà, l'écart entre le document de la compagnie et la saisie des colis exige un commentaire du DG. */
    public const SEUIL_ECART_SAISIE_POURCENT = 2.0;

    /** Au-delà, l'écart entre montant prévu et montant facturé est signalé. */
    public const SEUIL_ECART_FACTURE_POURCENT = 5.0;

    /** Parité fixe du franc CFA, utilisée si la table des taux est vide. */
    public const TAUX_EUR_XOF_DEFAUT = 655.957;

    public const TAILLE_MAX_DOCUMENT = 10 * 1024 * 1024;

    public const PERIODES = [
        'ce_mois' => 'Ce mois',
        'mois_dernier' => 'Mois dernier',
        'trimestre' => 'Ce trimestre',
        'annee' => 'Cette année',
        'libre' => 'Dates libres',
    ];

    private const EXTENSIONS = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
    ];

    /**
     * Code IATA de la ville, retenu pour le numéro : les sites d'une même ville
     * partagent le code et la séquence. FRA est écarté : c'est aussi Francfort.
     */
    private const CODE_DE_LA_VILLE = [
        'abidjan' => 'ABJ',
        'san pedro' => 'SPY',
        'san-pedro' => 'SPY',
        'paris' => 'PAR',
        'bobigny' => 'PAR',
        'dakar' => 'DKR',
    ];

    // ------------------------------------------------------------------
    // Numéros
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $agence ligne de company_sites */
    public static function codeAgence(array $agence): string
    {
        $parametre = strtoupper(trim((string) ($agence['code_dossier'] ?? '')));
        if (preg_match('/^[A-Z]{3}$/', $parametre) === 1) {
            return $parametre;
        }

        $ville = self::sansAccents(mb_strtolower(trim((string) ($agence['city'] ?? ''))));
        if (isset(self::CODE_DE_LA_VILLE[$ville])) {
            return self::CODE_DE_LA_VILLE[$ville];
        }

        $lettres = preg_replace('/[^A-Z]/', '', strtoupper(self::sansAccents((string) ($agence['code'] ?? '')))) ?? '';

        return strlen($lettres) >= 3 ? substr($lettres, 0, 3) : 'LBP';
    }

    public static function prefixeNumero(string $codeAgence, DateTimeImmutable $creation): string
    {
        return 'ENV-' . $codeAgence . '-' . $creation->format('ym');
    }

    public static function numeroDossier(string $prefixe, int $rang): string
    {
        return $prefixe . '-' . str_pad((string) $rang, 4, '0', STR_PAD_LEFT);
    }

    public static function nomFichier(string $numeroDossier, string $typePiece, int $rang): string
    {
        return $numeroDossier . '_' . $typePiece . '_' . $rang;
    }

    // ------------------------------------------------------------------
    // LTA
    // ------------------------------------------------------------------

    /**
     * LTA de compagnie : 3 chiffres de préfixe, 8 de série. Le dernier chiffre
     * est le reste de la division par 7 des 7 chiffres qui le précèdent.
     *
     * @return array{ok:bool, numero:?string, message:?string}
     */
    public static function verifierLta(string $saisie): array
    {
        $chiffres = preg_replace('/\D/', '', $saisie) ?? '';

        if (strlen($chiffres) !== 11 || preg_match('/[^\d\s.\-]/', $saisie) === 1) {
            return [
                'ok' => false,
                'numero' => null,
                'message' => 'une LTA compte 11 chiffres : 3 de préfixe et 8 de série (par exemple 057-30215463).',
            ];
        }

        $numero = substr($chiffres, 0, 3) . '-' . substr($chiffres, 3);
        $attendue = self::cleLta($chiffres);

        if ((int) $chiffres[10] !== $attendue) {
            return [
                'ok' => false,
                'numero' => $numero,
                'message' => 'le dernier chiffre de la LTA ' . $numero . ' ne correspond pas : attendu ' . $attendue . '.',
            ];
        }

        return ['ok' => true, 'numero' => $numero, 'message' => null];
    }

    public static function cleLta(string $onzeChiffres): int
    {
        return (int) substr($onzeChiffres, 3, 7) % 7;
    }

    // ------------------------------------------------------------------
    // Contrôles
    // ------------------------------------------------------------------

    /**
     * Contrôles des colonnes 1 à 5 et du trajet, à chaque enregistrement. Ces
     * informations viennent du document de la compagnie, connu dès le départ.
     *
     * @param array<string, mixed> $dossier
     * @param array<string, mixed>|null $compagnie ligne de lbp_prestataires
     * @return array{erreurs:array<int, string>, dossier:array<string, mixed>}
     */
    public static function controlerDossier(array $dossier, ?array $compagnie, string $aujourdhui): array
    {
        $erreurs = [];
        $mode = (string) ($dossier['mode_transport'] ?? '');

        if (!isset(self::MODES[$mode])) {
            $erreurs[] = 'Choisissez le mode de transport.';
            $mode = 'AERIEN';
        }

        $dossier['mode_transport'] = $mode;
        $dossier['type_document'] = self::TYPE_DOCUMENT_DU_MODE[$mode];
        $document = self::DOCUMENT_DU_MODE[$mode];

        $depart = (int) ($dossier['agence_depart_id'] ?? 0);
        $arrivee = (int) ($dossier['agence_arrivee_id'] ?? 0);
        if ($depart <= 0) {
            $erreurs[] = "Choisissez l'agence de départ.";
        }
        if ($arrivee <= 0) {
            $erreurs[] = 'Choisissez la destination.';
        } elseif ($arrivee === $depart) {
            $erreurs[] = "La destination doit être différente de l'agence de départ.";
        }

        $date = $dossier['date_depart_effective'] ?? null;
        if ($date === null || $date === '') {
            $erreurs[] = self::colonne(1) . 'saisissez la date de départ.';
        } elseif ($date > $aujourdhui) {
            $erreurs[] = self::colonne(1) . 'la date de départ ne peut pas être dans le futur.';
        }

        if ($compagnie === null) {
            $erreurs[] = self::colonne(2) . 'choisissez la compagnie.';
        } else {
            $type = (string) ($compagnie['type'] ?? '');
            if ($type !== '' && $type !== self::TRANSPORTEUR_DU_MODE[$mode]) {
                $erreurs[] = self::colonne(2) . (string) $compagnie['name'] . ' est enregistrée comme « '
                    . (self::TYPES_PRESTATAIRE[$type] ?? $type) . ' » : choisissez une compagnie du mode ' . mb_strtolower(self::MODES[$mode]) . '.';
            }
        }

        $numero = trim((string) ($dossier['numero_document'] ?? ''));
        $dossier['numero_document'] = $numero !== '' ? mb_strtoupper($numero) : null;

        if ($numero === '') {
            $erreurs[] = self::colonne(3, $document) . 'saisissez son numéro.';
        } elseif ($mode === 'AERIEN') {
            $lta = self::verifierLta($numero);
            if (!$lta['ok']) {
                $erreurs[] = self::colonne(3, $document) . (string) $lta['message'];
            } else {
                $dossier['numero_document'] = $lta['numero'];
                $prefixe = trim((string) ($compagnie['prefixe_lta'] ?? ''));
                if ($prefixe !== '' && substr((string) $lta['numero'], 0, 3) !== $prefixe) {
                    $erreurs[] = self::colonne(3, $document) . 'le préfixe ' . substr((string) $lta['numero'], 0, 3)
                        . ' ne correspond pas à ' . (string) $compagnie['name'] . ' (préfixe ' . $prefixe . ').';
                }
            }
        }

        if (($dossier['nb_colis_declare'] ?? null) === null) {
            $erreurs[] = self::colonne(4) . 'saisissez le nombre de colis du document.';
        } elseif ((int) $dossier['nb_colis_declare'] <= 0) {
            $erreurs[] = self::colonne(4) . 'le nombre de colis doit être supérieur à zéro.';
        }

        if (($dossier['poids_brut_kg'] ?? null) === null) {
            $erreurs[] = self::colonne(5) . 'saisissez le poids total du document.';
        } elseif ((float) $dossier['poids_brut_kg'] <= 0) {
            $erreurs[] = self::colonne(5) . 'le poids doit être supérieur à zéro.';
        }

        return ['erreurs' => $erreurs, 'dossier' => $dossier];
    }

    /**
     * @param array<string, array<string, mixed>> $fixes par poste
     * @return array<int, string>
     */
    public static function controlerFrais(array $fixes): array
    {
        $erreurs = [];

        foreach ($fixes as $poste => $ligne) {
            if (($ligne['montant_prevu'] ?? null) !== null && (float) $ligne['montant_prevu'] < 0) {
                $erreurs[] = self::colonne(self::COLONNE_DU_POSTE[$poste] ?? 0) . 'le montant ne peut pas être négatif.';
            }
        }

        return $erreurs;
    }

    /**
     * Ce qui manque encore pour soumettre le départ au Directeur général.
     *
     * @param array<string, mixed> $dossier
     * @param array<int, array<string, mixed>> $frais
     * @param array<int, array<string, mixed>> $emballages
     * @param array<int, string> $typesPresents
     * @return array<int, string>
     */
    public static function controlerSoumission(array $dossier, array $frais, array $emballages, array $typesPresents): array
    {
        $manques = [];

        if (!in_array((string) ($dossier['statut'] ?? ''), self::STATUTS_MODIFIABLES, true)) {
            $manques[] = 'Ce départ a déjà été soumis au Directeur général.';
        }

        $parPoste = self::fraisParPoste($frais);
        foreach (self::POSTES as $poste => $libelle) {
            $ligne = $parPoste[$poste] ?? [];
            if (($ligne['montant_prevu'] ?? null) === null) {
                $manques[] = self::colonne(self::COLONNE_DU_POSTE[$poste]) . "saisissez le montant (0 s'il n'y a pas de frais).";
            } elseif ((float) $ligne['montant_prevu'] > 0 && !self::aUnPrestataire($ligne)) {
                $manques[] = self::colonne(self::COLONNE_DU_POSTE[$poste]) . 'indiquez le prestataire.';
            }
        }

        if ($emballages === []) {
            $manques[] = self::colonne(10) . 'indiquez le type et le nombre.';
        }

        foreach (self::piecesManquantes($frais, $typesPresents) as $libelle) {
            $manques[] = 'Pièce à joindre : ' . mb_strtolower($libelle) . '.';
        }

        return $manques;
    }

    /**
     * La pièce de transport, et la facture de chaque poste payant.
     *
     * @param array<int, array<string, mixed>> $frais
     * @return array<int, string>
     */
    public static function piecesAttendues(array $frais): array
    {
        $attendues = ['PIECE_TRANSPORT'];

        foreach (self::fraisParPoste($frais) as $poste => $ligne) {
            $piece = self::PIECE_DU_POSTE[$poste] ?? null;
            if ($piece !== null && ((float) ($ligne['montant_prevu'] ?? 0) > 0 || (float) ($ligne['montant_facture'] ?? 0) > 0)) {
                $attendues[] = $piece;
            }
        }

        return $attendues;
    }

    /**
     * @param array<int, array<string, mixed>> $frais
     * @param array<int, string> $typesPresents
     * @return array<string, string>
     */
    public static function piecesManquantes(array $frais, array $typesPresents): array
    {
        $manquantes = [];
        foreach (self::piecesAttendues($frais) as $type) {
            if (!in_array($type, $typesPresents, true)) {
                $manquantes[$type] = self::PIECES[$type];
            }
        }

        return $manquantes;
    }

    // ------------------------------------------------------------------
    // Écarts
    // ------------------------------------------------------------------

    /**
     * Écart entre le document de la compagnie et la saisie des colis partis
     * avec le départ, en valeur et en pourcentage de la saisie.
     *
     * Une saisie vide face à un document qui annonce des colis est toujours
     * hors tolérance : tout est parti sans être enregistré.
     *
     * @param array<string, mixed> $dossier
     * @return array{colis:int, poids:float, pourcent_colis:?float, pourcent_poids:?float, depasse:bool}|null
     */
    public static function ecartSaisie(array $dossier): ?array
    {
        if (($dossier['colis_erp'] ?? null) === null && ($dossier['poids_erp_kg'] ?? null) === null) {
            return null;
        }

        $colisSaisis = (int) ($dossier['colis_erp'] ?? 0);
        $poidsSaisi = (float) ($dossier['poids_erp_kg'] ?? 0);
        $colis = (int) ($dossier['nb_colis_declare'] ?? 0) - $colisSaisis;
        $poids = round((float) ($dossier['poids_brut_kg'] ?? 0) - $poidsSaisi, 1);
        $pourcentColis = $colisSaisis > 0 ? round($colis * 100 / $colisSaisis, 1) : null;
        $pourcentPoids = $poidsSaisi > 0 ? round($poids * 100 / $poidsSaisi, 1) : null;

        $horsTolerance = static fn (?float $pourcent, float $ecart): bool => $pourcent === null
            ? abs($ecart) > 0
            : abs($pourcent) > self::SEUIL_ECART_SAISIE_POURCENT;

        return [
            'colis' => $colis,
            'poids' => $poids,
            'pourcent_colis' => $pourcentColis,
            'pourcent_poids' => $pourcentPoids,
            'depasse' => $horsTolerance($pourcentColis, (float) $colis) || $horsTolerance($pourcentPoids, $poids),
        ];
    }

    public static function enXof(?float $montant, ?string $devise, float $taux): ?float
    {
        if ($montant === null) {
            return null;
        }

        return $devise === 'EUR' ? round($montant * $taux, 2) : $montant;
    }

    /**
     * @param array<string, mixed> $ligne
     * @return array{montant_xof:float, pourcent:?float, depasse:bool}|null
     */
    public static function ecartFacture(array $ligne, float $taux): ?array
    {
        if (($ligne['montant_facture'] ?? null) === null || ($ligne['montant_prevu'] ?? null) === null) {
            return null;
        }

        $prevu = (float) self::enXof((float) $ligne['montant_prevu'], (string) ($ligne['devise'] ?? 'XOF'), $taux);
        $facture = (float) self::enXof((float) $ligne['montant_facture'], (string) ($ligne['devise_facture'] ?? $ligne['devise'] ?? 'XOF'), $taux);
        $ecart = round($facture - $prevu, 2);
        $pourcent = $prevu > 0 ? round($ecart * 100 / $prevu, 1) : null;

        return [
            'montant_xof' => $ecart,
            'pourcent' => $pourcent,
            'depasse' => $prevu > 0 ? abs($ecart * 100 / $prevu) > self::SEUIL_ECART_FACTURE_POURCENT : $facture > 0,
        ];
    }

    /**
     * Chiffres calculés d'un départ, pour les listes, la fiche et les exports.
     *
     * @param array<string, mixed> $dossier
     * @param array<int, array<string, mixed>> $frais
     * @param array<int, array<string, mixed>> $emballages
     * @param array<int, string> $typesPresents
     * @return array<string, mixed>
     */
    public static function synthese(array $dossier, array $frais, array $emballages, array $typesPresents): array
    {
        $taux = (float) ($dossier['taux_eur_xof'] ?? 0) > 0 ? (float) $dossier['taux_eur_xof'] : self::TAUX_EUR_XOF_DEFAUT;
        $prevu = 0.0;
        $facture = 0.0;
        $retenu = 0.0;
        $ecartTotal = 0.0;
        $depasse = false;
        $parPoste = [];

        foreach (self::fraisParPoste($frais) as $poste => $ligne) {
            $devise = (string) ($ligne['devise'] ?? 'XOF');
            $montantPrevu = self::enXof(isset($ligne['montant_prevu']) ? (float) $ligne['montant_prevu'] : null, $devise, $taux);
            $montantFacture = self::enXof(isset($ligne['montant_facture']) ? (float) $ligne['montant_facture'] : null, (string) ($ligne['devise_facture'] ?? $devise), $taux);

            $prevu += (float) $montantPrevu;
            $facture += (float) $montantFacture;
            $retenu += (float) ($montantFacture ?? $montantPrevu);

            $ecart = self::ecartFacture($ligne, $taux);
            if ($ecart !== null) {
                $ecartTotal += $ecart['montant_xof'];
                $depasse = $depasse || $ecart['depasse'];
            }

            $parPoste[$poste] = $ligne + ['ecart' => $ecart];
        }

        $attendues = self::piecesAttendues($frais);
        $manquantes = self::piecesManquantes($frais, $typesPresents);

        $morceaux = [];
        foreach ($emballages as $emballage) {
            $morceaux[] = (int) $emballage['quantite'] . ' ' . (string) $emballage['type'];
        }

        return [
            'taux' => $taux,
            'cout_prevu_xof' => round($prevu, 2),
            'cout_facture_xof' => round($facture, 2),
            'cout_retenu_xof' => round($retenu, 2),
            'ecart_facture_xof' => round($ecartTotal, 2),
            'ecart_facture_depasse' => $depasse,
            'pieces_attendues' => count($attendues),
            'pieces_presentes' => count($attendues) - count($manquantes),
            'pieces_manquantes' => $manquantes,
            'emballages_texte' => implode(', ', $morceaux),
            'frais_par_poste' => $parPoste,
            'colonnes_renseignees' => self::colonnesRenseignees($dossier, $frais, $emballages),
            'ecart_saisie' => self::ecartSaisie($dossier),
        ];
    }

    /**
     * Nombre des dix colonnes remplies.
     *
     * @param array<string, mixed> $dossier
     * @param array<int, array<string, mixed>> $frais
     * @param array<int, array<string, mixed>> $emballages
     */
    public static function colonnesRenseignees(array $dossier, array $frais, array $emballages): int
    {
        $remplies = 0;
        foreach (['date_depart_effective', 'transporteur_id', 'numero_document', 'nb_colis_declare', 'poids_brut_kg'] as $champ) {
            if (($dossier[$champ] ?? null) !== null && $dossier[$champ] !== '') {
                $remplies++;
            }
        }

        $parPoste = self::fraisParPoste($frais);
        foreach (array_keys(self::POSTES) as $poste) {
            if (($parPoste[$poste]['montant_prevu'] ?? null) !== null) {
                $remplies++;
            }
        }

        return $remplies + ($emballages !== [] ? 1 : 0);
    }

    // ------------------------------------------------------------------
    // Historique et pièces jointes
    // ------------------------------------------------------------------

    /**
     * @return array{periode:string, du:string, au:string}
     */
    public static function resoudrePeriode(string $periode, string $du, string $au, DateTimeImmutable $aujourdhui): array
    {
        $valide = static fn (string $d): bool => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1
            && checkdate((int) substr($d, 5, 2), (int) substr($d, 8, 2), (int) substr($d, 0, 4));

        if ($periode === 'libre' && $valide($du) && $valide($au)) {
            return $du <= $au ? ['periode' => 'libre', 'du' => $du, 'au' => $au] : ['periode' => 'libre', 'du' => $au, 'au' => $du];
        }

        if ($periode === 'trimestre') {
            $mois = (int) $aujourdhui->format('n');
            $premier = $aujourdhui->setDate((int) $aujourdhui->format('Y'), intdiv($mois - 1, 3) * 3 + 1, 1);

            return ['periode' => 'trimestre', 'du' => $premier->format('Y-m-d'), 'au' => $premier->modify('+2 months')->format('Y-m-t')];
        }

        return match ($periode) {
            'mois_dernier' => [
                'periode' => 'mois_dernier',
                'du' => $aujourdhui->modify('first day of last month')->format('Y-m-d'),
                'au' => $aujourdhui->modify('last day of last month')->format('Y-m-d'),
            ],
            'annee' => ['periode' => 'annee', 'du' => $aujourdhui->format('Y-01-01'), 'au' => $aujourdhui->format('Y-12-31')],
            default => ['periode' => 'ce_mois', 'du' => $aujourdhui->format('Y-m-01'), 'au' => $aujourdhui->format('Y-m-t')],
        };
    }

    /** Extension d'après le type réel du fichier : un .exe renommé en .pdf reste refusé. */
    public static function extensionDocument(string $typeReel, string $nomOriginal): ?string
    {
        if (isset(self::EXTENSIONS[$typeReel])) {
            return self::EXTENSIONS[$typeReel];
        }

        $extension = strtolower(pathinfo($nomOriginal, PATHINFO_EXTENSION));

        if ($extension === 'xlsx' && in_array($typeReel, ['application/zip', 'application/octet-stream'], true)) {
            return 'xlsx';
        }
        if ($extension === 'xls' && in_array($typeReel, ['application/x-ole-storage', 'application/CDFV2', 'application/vnd.ms-office', 'application/octet-stream'], true)) {
            return 'xls';
        }

        return null;
    }

    public static function nombre(float $valeur, int $decimales = 0): string
    {
        return number_format($valeur, $decimales, ',', ' ');
    }

    /**
     * @param array<int, array<string, mixed>> $frais
     * @return array<string, array<string, mixed>>
     */
    public static function fraisParPoste(array $frais): array
    {
        $parPoste = [];
        foreach ($frais as $ligne) {
            $poste = (string) ($ligne['poste'] ?? '');
            if (isset(self::POSTES[$poste])) {
                $parPoste[$poste] ??= $ligne;
            }
        }

        return $parPoste;
    }

    /** @param array<string, mixed> $ligne */
    private static function aUnPrestataire(array $ligne): bool
    {
        return (int) ($ligne['prestataire_id'] ?? 0) > 0 || trim((string) ($ligne['prestataire_libre'] ?? '')) !== '';
    }

    private static function colonne(int $numero, ?string $libelle = null): string
    {
        return 'Colonne ' . $numero . ' — ' . ($libelle ?? self::COLONNES[$numero] ?? '') . ' : ';
    }

    private static function sansAccents(string $texte): string
    {
        return strtr($texte, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c',
            'À' => 'A', 'Â' => 'A', 'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Î' => 'I', 'Ô' => 'O', 'Ù' => 'U', 'Û' => 'U', 'Ç' => 'C',
        ]);
    }
}
