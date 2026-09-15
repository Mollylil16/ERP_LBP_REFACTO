<?php

declare(strict_types=1);

namespace App\Services\Colisage;

use DateTimeImmutable;

/**
 * Règles du dossier d'envoi, sans base de données.
 *
 * Arrêtées dans le cahier des charges CDC-ENV-01, validé le 15/09/2026 :
 * - un dossier par document de transport reçu par LBP, pour tous les modes
 *   (aérien, maritime, routier, express) ;
 * - numéro ENV-{code agence}-{AAMM}-{rang}, séquence par code et par mois ;
 * - montant prévu pendant l'envoi, montant facturé au dépôt de la facture ;
 * - l'agent export soumet au Directeur général, seul à valider.
 *
 * Tout ce qui se décide sans lire la base vit ici, pour être testé.
 */
final class DossierEnvoiRegles
{
    public const MODES = [
        'AERIEN' => 'Aérien',
        'MARITIME' => 'Maritime',
        'ROUTIER' => 'Routier',
        'EXPRESS' => 'Express (DHL)',
    ];

    /** Type de prestataire attendu comme transporteur, pour chaque mode. */
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

    /** Documents de transport possibles, par mode. */
    public const DOCUMENTS_DU_MODE = [
        'AERIEN' => ['LTA_DIRECTE' => 'LTA directe', 'LTA_FILLE' => 'LTA fille (HAWB)'],
        'MARITIME' => ['BL' => 'Connaissement (BL)', 'BL_FILS' => 'BL fils (HBL)'],
        'ROUTIER' => ['LETTRE_VOITURE' => 'Lettre de voiture'],
        'EXPRESS' => ['AWB_EXPRESS' => 'AWB DHL'],
    ];

    /** Documents remis par un transitaire en groupage : leur émetteur n'est pas le transporteur. */
    public const DOCUMENTS_FILS = ['LTA_FILLE', 'BL_FILS'];

    public const TRANCHE_DU_MODE = [
        'AERIEN' => 'VOL',
        'MARITIME' => 'CONTENEUR',
        'ROUTIER' => 'VEHICULE',
        'EXPRESS' => 'REMISE',
    ];

    public const LIBELLES_TRANCHE = [
        'VOL' => 'Vol',
        'CONTENEUR' => 'Conteneur',
        'VEHICULE' => 'Véhicule',
        'REMISE' => 'Remise',
    ];

    public const TYPES_CONTENEUR = ['20' => "20'", '40' => "40'", '40HC' => "40' HC"];

    public const EMBALLAGES = ['Carton', 'Bôrô', 'Valise', 'Sac', 'Palette', 'Fût', 'Autre'];

    public const DEVISES = ['XOF', 'EUR'];

    /** Postes de frais présents dans tout dossier, dans l'ordre d'affichage. */
    public const POSTES = [
        'FRET' => 'Fret transporteur',
        'TRANSIT_DEPART' => 'Transitaire au départ',
        'TRANSIT_ARRIVEE' => 'Transitaire à destination',
        'LIVRAISON_DEPART' => 'Livraison au départ',
        'LIVRAISON_ARRIVEE' => "Livraison à l'arrivée",
    ];

    public const POSTE_AUTRE = 'AUTRE';

    public const STATUTS = [
        'BROUILLON' => 'Brouillon',
        'RESERVE' => 'Réservé',
        'PARTI' => 'Parti',
        'ARRIVE' => 'Arrivé',
        'LIVRE' => 'Livré',
        'SOUMIS' => 'Soumis',
        'A_CORRIGER' => 'À corriger',
        'VALIDE' => 'Validé',
        'ANNULE' => 'Annulé',
        'REPRIS' => 'Repris',
    ];

    /** Statuts dans lesquels l'agent export peut encore modifier son dossier. */
    public const STATUTS_MODIFIABLES = ['BROUILLON', 'RESERVE', 'PARTI', 'ARRIVE', 'LIVRE', 'A_CORRIGER'];

    /** Un dossier ne s'annule que tant que la marchandise n'est pas partie. */
    public const STATUTS_ANNULABLES = ['BROUILLON', 'RESERVE'];

    /** Statuts dont on attend les pièces de départ. */
    public const STATUTS_PIECES_ATTENDUES = ['PARTI', 'ARRIVE', 'LIVRE', 'A_CORRIGER'];

    public const PIECES = [
        'RESERVATION' => 'Confirmation de réservation',
        'PIECE_TRANSPORT' => 'Pièce de transport',
        'MANIFESTE' => 'Manifeste',
        'FACTURE_FRET' => 'Facture du transporteur (fret)',
        'FACTURE_TRANSIT_DEPART' => 'Facture du transitaire au départ',
        'DOUANE_EXPORT' => 'Documents de douane export',
        'FACTURE_TRANSIT_ARRIVEE' => 'Facture du transitaire à destination',
        'BON_LIVRAISON_DEPART' => 'Bon ou facture de livraison au départ',
        'BON_LIVRAISON_ARRIVEE' => "Bon, facture ou preuve de livraison à l'arrivée",
        'PHOTO' => 'Photo (colis, emballages, avaries)',
        'AUTRE' => 'Autre document',
    ];

    /** Pièce qui porte le montant facturé de chaque poste. */
    public const PIECE_DU_POSTE = [
        'FRET' => 'FACTURE_FRET',
        'TRANSIT_DEPART' => 'FACTURE_TRANSIT_DEPART',
        'TRANSIT_ARRIVEE' => 'FACTURE_TRANSIT_ARRIVEE',
        'LIVRAISON_DEPART' => 'BON_LIVRAISON_DEPART',
        'LIVRAISON_ARRIVEE' => 'BON_LIVRAISON_ARRIVEE',
    ];

    /** Au-delà, un écart entre montant prévu et montant facturé doit être commenté. */
    public const SEUIL_ECART_FACTURE_POURCENT = 5.0;

    /** Parité fixe du franc CFA, utilisée si la table des taux est vide. */
    public const TAUX_EUR_XOF_DEFAUT = 655.957;

    public const TAILLE_MAX_DOCUMENT = 10 * 1024 * 1024;

    /** Types réels acceptés pour une pièce jointe. */
    private const EXTENSIONS = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
    ];

    /**
     * Code IATA de la ville, retenu pour le numéro de dossier : les sites d'une
     * même ville partagent le code et la séquence, car leurs envois partent du
     * même aéroport ou port. FRA est écarté : c'est aussi Francfort.
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

    /**
     * @param array<string, mixed> $agence ligne de company_sites
     */
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

    /**
     * Nom donné au fichier téléchargé : ENV-ABJ-2609-0042_PIECE_TRANSPORT_1.
     */
    public static function nomFichier(string $numeroDossier, string $typePiece, int $rang): string
    {
        return $numeroDossier . '_' . $typePiece . '_' . $rang;
    }

    // ------------------------------------------------------------------
    // Clés de contrôle
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
                'message' => 'Une LTA de compagnie compte 11 chiffres : 3 de préfixe et 8 de série (par exemple 057-30215463).',
            ];
        }

        $numero = substr($chiffres, 0, 3) . '-' . substr($chiffres, 3);
        $attendue = self::cleLta($chiffres);

        if ((int) $chiffres[10] !== $attendue) {
            return [
                'ok' => false,
                'numero' => $numero,
                'message' => 'Le dernier chiffre de la LTA ' . $numero . ' ne correspond pas : attendu ' . $attendue . '.',
            ];
        }

        return ['ok' => true, 'numero' => $numero, 'message' => null];
    }

    public static function cleLta(string $onzeChiffres): int
    {
        return (int) substr($onzeChiffres, 3, 7) % 7;
    }

    /**
     * Conteneur maritime, norme ISO 6346 : 3 lettres de propriétaire, une lettre
     * de catégorie (U, J ou Z), 6 chiffres et un chiffre de contrôle.
     *
     * @return array{ok:bool, numero:?string, message:?string}
     */
    public static function verifierConteneur(string $saisie): array
    {
        $code = strtoupper(preg_replace('/[\s.\-]/', '', $saisie) ?? '');

        if (preg_match('/^[A-Z]{3}[UJZ]\d{7}$/', $code) !== 1) {
            return [
                'ok' => false,
                'numero' => null,
                'message' => 'Un numéro de conteneur compte 4 lettres, 6 chiffres et 1 chiffre de contrôle (par exemple CSQU3054383).',
            ];
        }

        $attendu = self::cleConteneur(substr($code, 0, 10));

        if ((int) $code[10] !== $attendu) {
            return [
                'ok' => false,
                'numero' => $code,
                'message' => 'Le dernier chiffre du conteneur ' . $code . ' ne correspond pas : attendu ' . $attendu . '.',
            ];
        }

        return ['ok' => true, 'numero' => $code, 'message' => null];
    }

    /**
     * Chaque lettre vaut de 10 à 38 en sautant les multiples de 11 ; chaque
     * caractère est pondéré par 2 puissance sa position.
     */
    public static function cleConteneur(string $dixCaracteres): int
    {
        $valeurs = [];
        $valeur = 10;
        foreach (range('A', 'Z') as $lettre) {
            if ($valeur % 11 === 0) {
                $valeur++;
            }
            $valeurs[$lettre] = $valeur++;
        }

        $somme = 0;
        for ($i = 0; $i < 10; $i++) {
            $caractere = $dixCaracteres[$i];
            $somme += (ctype_digit($caractere) ? (int) $caractere : $valeurs[$caractere]) * (2 ** $i);
        }

        return $somme % 11 % 10;
    }

    // ------------------------------------------------------------------
    // Statuts
    // ------------------------------------------------------------------

    /**
     * Le statut suit ce que l'agent a saisi : une date de livraison fait un
     * dossier livré, une date d'arrivée un dossier arrivé, et ainsi de suite.
     *
     * @param array<string, mixed> $dossier
     */
    public static function statutProgression(array $dossier): string
    {
        return match (true) {
            !empty($dossier['date_livraison']) => 'LIVRE',
            !empty($dossier['date_arrivee']) => 'ARRIVE',
            !empty($dossier['date_depart_effective']) => 'PARTI',
            !empty($dossier['transporteur_id']) && !empty($dossier['numero_document']) => 'RESERVE',
            default => 'BROUILLON',
        };
    }

    /**
     * Un dossier renvoyé reste « à corriger » jusqu'à sa nouvelle soumission.
     *
     * @param array<string, mixed> $dossier
     */
    public static function statutApresEnregistrement(string $actuel, array $dossier): string
    {
        if ($actuel === 'A_CORRIGER') {
            return 'A_CORRIGER';
        }

        return in_array($actuel, self::STATUTS_MODIFIABLES, true) ? self::statutProgression($dossier) : $actuel;
    }

    // ------------------------------------------------------------------
    // Contrôles à l'enregistrement
    // ------------------------------------------------------------------

    /**
     * Contrôles bloquants à l'enregistrement. Les numéros sont remis en forme
     * au passage (057-30215463, CSQU3054383).
     *
     * @param array<string, mixed> $dossier
     * @param array<int, array<string, mixed>> $tranches
     * @param array<string, mixed>|null $transporteur ligne de lbp_prestataires
     * @return array{erreurs:array<int, string>, dossier:array<string, mixed>, tranches:array<int, array<string, mixed>>}
     */
    public static function controlerSaisie(array $dossier, array $tranches, ?array $transporteur, string $aujourdhui): array
    {
        $erreurs = [];
        $mode = (string) ($dossier['mode_transport'] ?? '');

        if (!isset(self::MODES[$mode])) {
            $erreurs[] = 'Choisissez le mode de transport.';
            $mode = 'AERIEN';
            $dossier['mode_transport'] = $mode;
        }

        if ((int) ($dossier['agence_depart_id'] ?? 0) <= 0) {
            $erreurs[] = "Choisissez l'agence de départ.";
        }

        $documents = self::DOCUMENTS_DU_MODE[$mode];
        $type = (string) ($dossier['type_document'] ?? '');
        if (!isset($documents[$type])) {
            $type = (string) array_key_first($documents);
        }
        $dossier['type_document'] = $type;

        if ($transporteur !== null) {
            $typeTransporteur = (string) ($transporteur['type'] ?? '');
            $attendu = self::TRANSPORTEUR_DU_MODE[$mode];
            if ($typeTransporteur !== '' && $typeTransporteur !== $attendu) {
                $erreurs[] = (string) $transporteur['name'] . ' est enregistré comme « '
                    . (self::TYPES_PRESTATAIRE[$typeTransporteur] ?? $typeTransporteur)
                    . ' » : choisissez un transporteur du mode ' . mb_strtolower(self::MODES[$mode]) . '.';
            }
        }

        $numero = trim((string) ($dossier['numero_document'] ?? ''));
        $dossier['numero_document'] = $numero !== '' ? $numero : null;

        if ($numero !== '' && $type === 'LTA_DIRECTE') {
            $lta = self::verifierLta($numero);
            if ($lta['ok']) {
                $dossier['numero_document'] = $lta['numero'];
                $prefixe = trim((string) ($transporteur['prefixe_lta'] ?? ''));
                if ($prefixe !== '' && substr((string) $lta['numero'], 0, 3) !== $prefixe) {
                    $erreurs[] = 'Le préfixe ' . substr((string) $lta['numero'], 0, 3) . ' ne correspond pas à '
                        . (string) $transporteur['name'] . ' (préfixe ' . $prefixe . ').';
                }
            } else {
                $erreurs[] = (string) $lta['message'];
            }
        }

        if ($numero !== '' && in_array($type, self::DOCUMENTS_FILS, true) && (int) ($dossier['emetteur_document_id'] ?? 0) <= 0) {
            $erreurs[] = 'Choisissez le transitaire qui a émis la ' . ($type === 'LTA_FILLE' ? 'LTA fille.' : 'BL fils.');
        }
        if (!in_array($type, self::DOCUMENTS_FILS, true)) {
            $dossier['emetteur_document_id'] = null;
        }

        $principal = trim((string) ($dossier['document_principal'] ?? ''));
        $dossier['document_principal'] = $principal !== '' ? $principal : null;
        if ($principal !== '' && $mode === 'AERIEN') {
            $lta = self::verifierLta($principal);
            if ($lta['ok']) {
                $dossier['document_principal'] = $lta['numero'];
            } else {
                $erreurs[] = 'LTA principale : ' . lcfirst((string) $lta['message']);
            }
        }

        foreach (['nb_colis_declare' => 'Le nombre de colis', 'poids_brut_kg' => 'Le poids brut', 'poids_taxable_kg' => 'Le poids taxable', 'volume_m3' => 'Le volume'] as $champ => $libelle) {
            if (($dossier[$champ] ?? null) !== null && (float) $dossier[$champ] <= 0) {
                $erreurs[] = $libelle . ' doit être supérieur à zéro.';
            }
        }

        if (($dossier['poids_brut_kg'] ?? null) !== null && ($dossier['poids_taxable_kg'] ?? null) !== null
            && (float) $dossier['poids_taxable_kg'] < (float) $dossier['poids_brut_kg']) {
            $erreurs[] = 'Le poids taxable ne peut pas être inférieur au poids brut.';
        }

        $depart = $dossier['date_depart_effective'] ?? null;
        if ($depart !== null && $depart > $aujourdhui) {
            $erreurs[] = 'La date de départ effective ne peut pas être dans le futur : utilisez la date prévue.';
        }
        if ($depart !== null && ($dossier['date_arrivee'] ?? null) !== null && $dossier['date_arrivee'] < $depart) {
            $erreurs[] = "La date d'arrivée ne peut pas précéder la date de départ.";
        }
        if (($dossier['date_livraison'] ?? null) !== null && ($dossier['date_arrivee'] ?? null) === null) {
            $erreurs[] = "Saisissez la date d'arrivée avant la date de livraison.";
        } elseif (($dossier['date_livraison'] ?? null) !== null && $dossier['date_livraison'] < $dossier['date_arrivee']) {
            $erreurs[] = "La date de livraison ne peut pas précéder la date d'arrivée.";
        }
        if (($dossier['date_arrivee'] ?? null) !== null && $depart === null) {
            $erreurs[] = "Saisissez la date de départ effective avant la date d'arrivée.";
        }

        $typeTranche = self::TRANCHE_DU_MODE[$mode];
        $totalTranches = 0;
        $tranchesCompletes = $tranches !== [];

        foreach ($tranches as $i => $tranche) {
            $tranches[$i]['type'] = $typeTranche;
            $rang = $i + 1;
            $reference = trim((string) ($tranche['reference'] ?? ''));
            $tranches[$i]['reference'] = $reference !== '' ? $reference : null;

            if ($reference !== '' && $typeTranche === 'CONTENEUR') {
                $conteneur = self::verifierConteneur($reference);
                if ($conteneur['ok']) {
                    $tranches[$i]['reference'] = $conteneur['numero'];
                } else {
                    $erreurs[] = 'Tranche ' . $rang . ' : ' . lcfirst((string) $conteneur['message']);
                }
            }

            if (($tranche['nb_colis'] ?? null) === null) {
                $tranchesCompletes = false;
            } elseif ((int) $tranche['nb_colis'] <= 0) {
                $erreurs[] = 'Tranche ' . $rang . ' : le nombre de colis doit être supérieur à zéro.';
            } else {
                $totalTranches += (int) $tranche['nb_colis'];
            }

            if (($tranche['date_depart'] ?? null) !== null && ($tranche['date_arrivee'] ?? null) !== null
                && $tranche['date_arrivee'] < $tranche['date_depart']) {
                $erreurs[] = 'Tranche ' . $rang . " : l'arrivée ne peut pas précéder le départ.";
            }
        }

        if ($tranchesCompletes && ($dossier['nb_colis_declare'] ?? null) !== null && $totalTranches !== (int) $dossier['nb_colis_declare']) {
            $erreurs[] = 'Les tranches totalisent ' . $totalTranches . ' colis alors que le document en déclare '
                . (int) $dossier['nb_colis_declare'] . '.';
        }

        return ['erreurs' => $erreurs, 'dossier' => $dossier, 'tranches' => array_values($tranches)];
    }

    /**
     * @param array<string, array<string, mixed>> $fixes par poste
     * @param array<int, array<string, mixed>> $autres
     * @return array<int, string>
     */
    public static function controlerFrais(array $fixes, array $autres): array
    {
        $erreurs = [];

        foreach ($fixes as $poste => $ligne) {
            $libelle = self::POSTES[$poste] ?? $poste;
            if (($ligne['montant_prevu'] ?? null) !== null && (float) $ligne['montant_prevu'] < 0) {
                $erreurs[] = $libelle . ' : le montant ne peut pas être négatif.';
            }
            if (!empty($ligne['sans_frais']) && (float) ($ligne['montant_prevu'] ?? 0) > 0) {
                $erreurs[] = $libelle . ' : « sans frais » est coché alors qu\'un montant est saisi.';
            }
        }

        foreach ($autres as $i => $ligne) {
            if (trim((string) ($ligne['libelle'] ?? '')) === '') {
                $erreurs[] = 'Autre frais n° ' . ($i + 1) . ' : indiquez son libellé (douane, manutention, stockage…).';
            }
            foreach (['montant_prevu' => 'prévu', 'montant_facture' => 'facturé'] as $champ => $nom) {
                if (($ligne[$champ] ?? null) !== null && (float) $ligne[$champ] < 0) {
                    $erreurs[] = 'Autre frais n° ' . ($i + 1) . ' : le montant ' . $nom . ' ne peut pas être négatif.';
                }
            }
        }

        return $erreurs;
    }

    // ------------------------------------------------------------------
    // Pièces, écarts et soumission
    // ------------------------------------------------------------------

    /**
     * Pièces qui bloquent la soumission : la pièce de transport, le manifeste,
     * et la facture de chaque poste payant.
     *
     * @param array<int, array<string, mixed>> $frais
     * @return array<int, string>
     */
    public static function piecesAttendues(array $frais): array
    {
        $attendues = ['PIECE_TRANSPORT', 'MANIFESTE'];

        foreach ($frais as $ligne) {
            $piece = self::PIECE_DU_POSTE[(string) ($ligne['poste'] ?? '')] ?? null;
            $payant = (float) ($ligne['montant_prevu'] ?? 0) > 0 || (float) ($ligne['montant_facture'] ?? 0) > 0;
            if ($piece !== null && $payant && !in_array($piece, $attendues, true)) {
                $attendues[] = $piece;
            }
        }

        return $attendues;
    }

    /**
     * @param array<int, array<string, mixed>> $frais
     * @param array<int, string> $typesPresents
     * @return array<string, string> type => libellé
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

    public static function enXof(?float $montant, ?string $devise, float $taux): ?float
    {
        if ($montant === null) {
            return null;
        }

        return $devise === 'EUR' ? round($montant * $taux, 2) : $montant;
    }

    /**
     * Écart entre le montant facturé et le montant prévu d'une ligne de frais,
     * en XOF et en pourcentage du prévu.
     *
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
        $depasse = $prevu > 0 ? abs($ecart * 100 / $prevu) > self::SEUIL_ECART_FACTURE_POURCENT : $facture > 0;

        return ['montant_xof' => $ecart, 'pourcent' => $pourcent, 'depasse' => $depasse];
    }

    /**
     * Ce qui manque encore pour soumettre le dossier au Directeur général.
     *
     * @param array<string, mixed> $dossier
     * @param array<int, array<string, mixed>> $frais
     * @param array<int, array<string, mixed>> $emballages
     * @param array<int, string> $typesPresents
     * @return array<int, string>
     */
    public static function controlerSoumission(array $dossier, array $frais, array $emballages, array $typesPresents, ?int $colisPointes): array
    {
        $manques = [];
        $statut = (string) ($dossier['statut'] ?? '');

        if (!in_array($statut, ['LIVRE', 'A_CORRIGER'], true)) {
            $manques[] = "Le dossier doit être livré avant d'être soumis : saisissez la date de livraison.";
        }

        $obligatoires = [
            'transporteur_id' => 'le transporteur',
            'numero_document' => 'le numéro du document de transport',
            'date_depart_effective' => 'la date de départ effective',
            'nb_colis_declare' => 'le nombre de colis',
            'poids_brut_kg' => 'le poids brut',
            'date_arrivee' => "la date d'arrivée",
            'date_livraison' => 'la date de livraison',
        ];
        foreach ($obligatoires as $champ => $libelle) {
            if (($dossier[$champ] ?? null) === null || $dossier[$champ] === '') {
                $manques[] = 'Renseignez ' . $libelle . '.';
            }
        }

        if ($emballages === []) {
            $manques[] = "Indiquez le type et le nombre d'emballages.";
        }

        $parPoste = [];
        foreach ($frais as $ligne) {
            $parPoste[(string) ($ligne['poste'] ?? '')] ??= $ligne;
        }
        foreach (self::POSTES as $poste => $libelle) {
            $ligne = $parPoste[$poste] ?? [];
            if (($ligne['montant_prevu'] ?? null) === null && empty($ligne['sans_frais'])) {
                $manques[] = $libelle . ' : saisissez un montant, ou cochez « sans frais ».';
            }
        }

        foreach (self::piecesManquantes($frais, $typesPresents) as $libelle) {
            $manques[] = 'Pièce manquante : ' . mb_strtolower($libelle) . '.';
        }

        $declare = $dossier['nb_colis_declare'] ?? null;
        if ($colisPointes !== null && $declare !== null && (int) $declare !== $colisPointes
            && trim((string) ($dossier['commentaire_ecart'] ?? '')) === '') {
            $manques[] = 'Écart de ' . abs((int) $declare - $colisPointes) . ' colis entre le document ('
                . (int) $declare . ') et le pointage (' . $colisPointes . ') : commentez-le.';
        }

        $taux = (float) ($dossier['taux_eur_xof'] ?? 0) > 0 ? (float) $dossier['taux_eur_xof'] : self::TAUX_EUR_XOF_DEFAUT;
        foreach ($frais as $ligne) {
            $ecart = self::ecartFacture($ligne, $taux);
            if ($ecart !== null && $ecart['depasse'] && trim((string) ($ligne['commentaire_ecart'] ?? '')) === '') {
                $libelle = (string) ($ligne['poste'] ?? '') === self::POSTE_AUTRE
                    ? (string) ($ligne['libelle'] ?? 'Autre frais')
                    : (self::POSTES[(string) ($ligne['poste'] ?? '')] ?? 'Frais');
                $manques[] = $libelle . ' : écart de facture de '
                    . ($ecart['pourcent'] !== null ? self::nombre($ecart['pourcent'], 1) . ' %' : self::nombre($ecart['montant_xof'], 0) . ' XOF')
                    . ', au-delà de ' . self::nombre(self::SEUIL_ECART_FACTURE_POURCENT, 0) . ' % : ajoutez un commentaire.';
            }
        }

        return $manques;
    }

    /**
     * Chiffres calculés d'un dossier, pour les listes, la fiche et les exports.
     *
     * @param array<string, mixed> $dossier
     * @param array<int, array<string, mixed>> $frais
     * @param array<int, array<string, mixed>> $emballages
     * @param array<int, string> $typesPresents
     * @return array<string, mixed>
     */
    public static function synthese(array $dossier, array $frais, array $emballages, array $typesPresents, ?int $colisPointes): array
    {
        $taux = (float) ($dossier['taux_eur_xof'] ?? 0) > 0 ? (float) $dossier['taux_eur_xof'] : self::TAUX_EUR_XOF_DEFAUT;
        $prevu = 0.0;
        $facture = 0.0;
        $retenu = 0.0;
        $ecartTotal = 0.0;
        $depasse = false;
        $parPoste = [];

        foreach ($frais as $ligne) {
            $devise = (string) ($ligne['devise'] ?? 'XOF');
            $montantPrevu = self::enXof(isset($ligne['montant_prevu']) ? (float) $ligne['montant_prevu'] : null, $devise, $taux);
            $montantFacture = self::enXof(
                isset($ligne['montant_facture']) ? (float) $ligne['montant_facture'] : null,
                (string) ($ligne['devise_facture'] ?? $devise),
                $taux
            );

            $prevu += (float) $montantPrevu;
            $facture += (float) $montantFacture;
            $retenu += (float) ($montantFacture ?? $montantPrevu);

            $ecart = self::ecartFacture($ligne, $taux);
            if ($ecart !== null) {
                $ecartTotal += $ecart['montant_xof'];
                $depasse = $depasse || $ecart['depasse'];
            }

            $poste = (string) ($ligne['poste'] ?? '');
            if ($poste !== self::POSTE_AUTRE) {
                $parPoste[$poste] ??= $ligne + ['ecart' => $ecart];
            }
        }

        $attendues = self::piecesAttendues($frais);
        $manquantes = self::piecesManquantes($frais, $typesPresents);
        $poidsFacture = (float) ($dossier['poids_taxable_kg'] ?? 0) > 0 ? (float) $dossier['poids_taxable_kg'] : (float) ($dossier['poids_brut_kg'] ?? 0);
        $declare = $dossier['nb_colis_declare'] ?? null;

        $morceaux = [];
        foreach ($emballages as $emballage) {
            $morceaux[] = (int) $emballage['quantite'] . ' × ' . (string) $emballage['type'];
        }

        return [
            'taux' => $taux,
            'cout_prevu_xof' => round($prevu, 2),
            'cout_facture_xof' => round($facture, 2),
            'cout_retenu_xof' => round($retenu, 2),
            'ecart_facture_xof' => round($ecartTotal, 2),
            'ecart_facture_depasse' => $depasse,
            'cout_par_kg' => $poidsFacture > 0 && $retenu > 0 ? round($retenu / $poidsFacture) : null,
            'pieces_attendues' => count($attendues),
            'pieces_presentes' => count($attendues) - count($manquantes),
            'pieces_manquantes' => $manquantes,
            'colis_pointes' => $colisPointes,
            'ecart_colis' => $colisPointes !== null && $declare !== null ? (int) $declare - $colisPointes : null,
            'emballages_texte' => implode(', ', $morceaux),
            'frais_par_poste' => $parPoste,
        ];
    }

    // ------------------------------------------------------------------
    // Historique
    // ------------------------------------------------------------------

    public const PERIODES = [
        'ce_mois' => 'Ce mois',
        'mois_dernier' => 'Mois dernier',
        'trimestre' => 'Ce trimestre',
        'annee' => 'Cette année',
        'libre' => 'Dates libres',
    ];

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

        return match ($periode) {
            'mois_dernier' => [
                'periode' => 'mois_dernier',
                'du' => $aujourdhui->modify('first day of last month')->format('Y-m-d'),
                'au' => $aujourdhui->modify('last day of last month')->format('Y-m-d'),
            ],
            'trimestre' => (static function () use ($aujourdhui): array {
                $mois = (int) $aujourdhui->format('n');
                $debut = (int) (floor(($mois - 1) / 3) * 3 + 1);
                $premier = $aujourdhui->setDate((int) $aujourdhui->format('Y'), $debut, 1);

                return ['periode' => 'trimestre', 'du' => $premier->format('Y-m-d'), 'au' => $premier->modify('+2 months')->format('Y-m-t')];
            })(),
            'annee' => ['periode' => 'annee', 'du' => $aujourdhui->format('Y-01-01'), 'au' => $aujourdhui->format('Y-12-31')],
            default => ['periode' => 'ce_mois', 'du' => $aujourdhui->format('Y-m-01'), 'au' => $aujourdhui->format('Y-m-t')],
        };
    }

    // ------------------------------------------------------------------
    // Pièces jointes et libellés
    // ------------------------------------------------------------------

    /**
     * Extension à donner au fichier, d'après son type réel. Un .exe renommé en
     * .pdf garde son type réel et reste refusé.
     */
    public static function extensionDocument(string $typeReel, string $nomOriginal): ?string
    {
        if (isset(self::EXTENSIONS[$typeReel])) {
            return self::EXTENSIONS[$typeReel];
        }

        $extension = strtolower(pathinfo($nomOriginal, PATHINFO_EXTENSION));

        // Un classeur Excel récent est une archive zip, un ancien un conteneur OLE.
        if ($extension === 'xlsx' && in_array($typeReel, ['application/zip', 'application/octet-stream'], true)) {
            return 'xlsx';
        }
        if ($extension === 'xls' && in_array($typeReel, ['application/x-ole-storage', 'application/CDFV2', 'application/vnd.ms-office', 'application/octet-stream'], true)) {
            return 'xls';
        }

        return null;
    }

    public static function libelleDocument(string $mode): string
    {
        return match ($mode) {
            'MARITIME' => 'Connaissement (BL)',
            'ROUTIER' => 'Lettre de voiture',
            'EXPRESS' => 'AWB DHL',
            default => 'LTA',
        };
    }

    public static function libelleTransporteur(string $mode): string
    {
        return in_array($mode, ['AERIEN', 'MARITIME'], true) ? 'Compagnie' : 'Transporteur';
    }

    public static function nombre(float $valeur, int $decimales = 0): string
    {
        return number_format($valeur, $decimales, ',', ' ');
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
