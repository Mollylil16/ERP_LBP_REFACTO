<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Database;
use App\Repositories\Finance\MouvementsCaisseRepository;
use App\Security\MouvementsCaisseAcces;
use App\Services\Shared\AuditLogService;

/**
 * Les mouvements de caisse du jour : ce qui entre, ce qui sort, ce qui reste.
 *
 * La direction retrouve ici l'écran qu'elle avait dans son ancien logiciel.
 * Il n'ajoute aucune ressaisie : les règlements de factures, les
 * approvisionnements validés et les décaissements de demandes de fonds sont
 * déjà dans LBP, cet écran les rassemble. La saisie manuelle ne sert qu'à ce
 * que LBP ne connaît pas — et c'est la seule chose qui s'y corrige, le reste
 * se reprend à sa source.
 *
 * Les cumuls et les indicateurs sont tenus en FCFA : additionner un euro et un
 * franc ne donnerait un total juste dans aucune des deux monnaies. Les lignes,
 * elles, portent chacune leur devise et restent toutes affichées.
 */
final class MouvementsCaisseService
{
    /**
     * D'où vient une entrée de caisse, dans le vocabulaire de la direction.
     *
     * Une facture rattachée à un dossier d'envoi est classée au dossier, les
     * autres au colis : c'est la coupure que la direction lit, entre le transit
     * monté en dossier et la messagerie au guichet.
     *
     * @var array<string, string>
     */
    public const CADRES_ENTREE = [
        'FACTURE_DOSSIER' => 'Règlements de factures Dossier',
        'FACTURE_COLIS' => 'Règlements de factures Colis',
        'APPRO' => 'Approvisionnement de caisse',
        'AUTRE' => 'Autres versements',
    ];

    /**
     * D'où vient une sortie de caisse.
     *
     * @var array<string, string>
     */
    public const CADRES_SORTIE = [
        'TRAITEMENT_DOSSIER' => 'Traitement de dossier',
        'TRAITEMENT_COLIS' => 'Traitement de colis',
        'FONCTIONNEMENT' => 'Fonctionnement',
    ];

    /**
     * Les cadres qu'une saisie de versement a le droit de choisir.
     *
     * 'APPRO' n'y est pas : un approvisionnement se saisit sur son propre écran
     * et entre ici tout seul. L'offrir au clavier ouvrirait une seconde porte
     * sur la même somme.
     *
     * @var array<int, string>
     */
    public const CADRES_SAISIE_VERSEMENT = ['FACTURE_DOSSIER', 'FACTURE_COLIS', 'AUTRE'];

    /**
     * Ce qui s'écrit ailleurs et doit être relu ici sous un seul nom.
     *
     * Une demande de fonds écrit son cadre en minuscules ('traitement_dossier',
     * 'fonctionnement') et ne connaît pas le colis. Le formulaire, lui, poste
     * le mot court de la maquette ('DOSSIER', 'COLIS'). Trois vocabulaires pour
     * une même colonne : ils sont ramenés ici aux codes de CADRES_SORTIE, sans
     * quoi la ventilation des cartes dépendrait de la source de la ligne.
     *
     * @var array<string, string>
     */
    private const ALIAS_SORTIE = [
        'DOSSIER' => 'TRAITEMENT_DOSSIER',
        'COLIS' => 'TRAITEMENT_COLIS',
        'TRAITEMENT_FONCTIONNEMENT' => 'FONCTIONNEMENT',
        // Un décaissement d'autrefois, sans cadre : il relève du fonctionnement.
        'AUTRE' => 'FONCTIONNEMENT',
    ];

    /**
     * Les statuts d'une demande de fonds déjà sortie du tiroir.
     *
     * L'imputation vient souvent le lendemain et fait passer la demande de
     * 'decaissee' à 'imputee'. S'arrêter au premier statut ferait disparaître
     * la sortie de la journée où l'argent est réellement parti.
     *
     * @var array<int, string>
     */
    public const STATUTS_SORTIE = ['decaissee', 'imputee'];

    /** @var array<string, string> */
    public const MODES = [
        'ESPECES' => 'Espèces',
        'CHEQUE' => 'Chèque',
        'VIREMENT' => 'Virement',
        'MOBILE_MONEY' => 'Mobile money',
        'CARTE' => 'Carte',
        'AUTRE' => 'Autre',
    ];

    /**
     * Les opérateurs et les libellés que le formulaire poste, ramenés au
     * catalogue. Wave, Orange Money et MTN MoMo sont trois portefeuilles
     * mobiles : ils ne laissent pas un billet dans le tiroir.
     *
     * @var array<string, string>
     */
    private const ALIAS_MODES = [
        'WAVE' => 'MOBILE_MONEY',
        'ORANGE_MONEY' => 'MOBILE_MONEY',
        'MTN_MOMO' => 'MOBILE_MONEY',
        'MOOV_MONEY' => 'MOBILE_MONEY',
        'VIREMENT_BANCAIRE' => 'VIREMENT',
        'CARTE_BANCAIRE' => 'CARTE',
    ];

    /** Les monnaies d'un tiroir. Paris compte en euros. */
    public const DEVISES = ['XOF' => 'FCFA', 'EUR' => 'EUR'];

    /** La monnaie des cumuls et des indicateurs. */
    public const DEVISE_PIVOT = 'XOF';

    /**
     * Le mode tel qu'il est écrit par l'encaissement. Seule la colonne `mode`
     * de lbp_paiements est alimentée ; `mode_paiement` reste à 'ESPECES' pour
     * tout le monde et ne dit rien.
     *
     * @var array<string, string>
     */
    private const MODES_PAIEMENT = [
        'especes' => 'Espèces',
        'mobile_money' => 'Mobile money',
        'carte' => 'Carte',
        'virement' => 'Virement',
        'cheque' => 'Chèque',
        'portefeuille' => 'Portefeuille client',
    ];

    /**
     * À quelle colonne d'indicateur chaque cadre appartient.
     *
     * @var array<string, string>
     */
    private const COLONNES = [
        'FACTURE_DOSSIER' => 'dossiers',
        'FACTURE_COLIS' => 'colis',
        'APPRO' => 'autres',
        'AUTRE' => 'autres',
        'TRAITEMENT_DOSSIER' => 'dossiers',
        'TRAITEMENT_COLIS' => 'colis',
        'FONCTIONNEMENT' => 'autres',
    ];

    public function __construct(
        private MouvementsCaisseRepository $repo,
        private MouvementsCaisseAcces $acces
    ) {
    }

    public static function creer(): self
    {
        return new self(
            new MouvementsCaisseRepository(Database::getConnection()),
            MouvementsCaisseAcces::courant()
        );
    }

    // ------------------------------------------------------------------
    // L'écran
    // ------------------------------------------------------------------

    /**
     * Le tableau d'une journée : les caisses et leur solde, les indicateurs
     * comparés à la veille, et les deux historiques.
     *
     * @param array<string, mixed> $filtres
     * @return array<string, mixed>
     */
    public function tableauDuJour(array $filtres): array
    {
        $filtres = $this->filtres($filtres);
        $caisses = $this->repo->caisses($filtres['agence_id']);
        $pivots = $this->pivots($caisses);
        $portee = $this->portee($filtres, $caisses, $pivots);

        /*
         * Une portee bloquee ne propose rien non plus : le depot rend toutes
         * les caisses quand aucune agence ne borne la requete, et la liste
         * deroulante nommait donc les caisses des autres agences a un compte
         * qui n a le droit d en lire aucune.
         */
        if ($portee['bloquee']) {
            $caisses = [];
            $pivots = [];
        }

        $jour = $filtres['date'];
        $veille = $this->veille($jour);

        $versements = $this->versements($jour, $portee, $pivots);
        $retraits = $this->retraits($jour, $portee, $pivots);

        /*
         * La veille est relue par les mêmes requêtes que le jour, avec les mêmes
         * filtres : un total de comparaison calculé autrement finirait par ne
         * plus parler de la même chose que celui qu'on affiche.
         */
        $versementsVeille = $this->versements($veille, $portee, $pivots);
        $retraitsVeille = $this->retraits($veille, $portee, $pivots);

        $entrees = $this->ventiler($versements);
        $sorties = $this->ventiler($retraits);
        $entreesVeille = $this->ventiler($versementsVeille);
        $sortiesVeille = $this->ventiler($retraitsVeille);

        /*
         * Les deux monnaies ne se rencontrent jamais : aucune conversion, aucun
         * taux. Paris compte en euros, les autres agences en francs, et le
         * solde de chacune se lit dans la sienne — comme le point de caisse
         * tient déjà solde_caisse_agence_xof et _eur côte à côte.
         */
        $solde = $entrees['total'] - $sorties['total'];
        $soldeVeille = $entreesVeille['total'] - $sortiesVeille['total'];
        $soldeEur = $entrees['total_eur'] - $sorties['total_eur'];
        $soldeVeilleEur = $entreesVeille['total_eur'] - $sortiesVeille['total_eur'];

        return [
            'date' => $jour,
            'filtres' => $filtres,
            'caisses' => $this->caisses($caisses),
            'agences' => $this->repo->agences(),
            'kpis' => [
                'entrees' => $this->indicateur($entrees, $entreesVeille),
                'sorties' => $this->indicateur($sorties, $sortiesVeille),
                'solde' => [
                    'total' => $solde,
                    'veille' => $soldeVeille,
                    'variation' => self::variation($solde, $soldeVeille),
                    'total_eur' => $soldeEur,
                    'veille_eur' => $soldeVeilleEur,
                    'variation_eur' => self::variation($soldeEur, $soldeVeilleEur),
                ],
            ],
            /*
             * Le cumul depuis l origine, et non celui de la journee.
             *
             * Ce bloc affichait exactement les chiffres des trois cartes : le
             * meme nombre deux fois sur le meme ecran, sous deux libelles dont
             * l un disait « toutes journees confondues ». Sur l ancien
             * logiciel il portait 6,4 milliards.
             */
            'cumuls' => $this->cumuls($portee),
            'versements' => $versements,
            'retraits' => $retraits,
            'peutSaisir' => $this->acces->peutSaisir(),
        ];
    }

    /**
     * Filtres normalisés. Par défaut aujourd'hui : c'est la journée que la
     * caissière a sous les yeux.
     *
     * @param array<string, mixed> $requete
     * @return array{date: string, caisse_id: int, agence_id: int, q: string}
     */
    public function filtres(array $requete): array
    {
        $date = trim((string) ($requete['date'] ?? ''));

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            $date = date('Y-m-d');
        }

        $agence = max(0, (int) ($requete['agence_id'] ?? 0));

        // Une caissière ne voit que son tiroir : le filtre ne lui sert pas à
        // regarder la caisse d'à côté.
        if (!$this->acces->voitToutesLesAgences()) {
            $agence = (int) ($this->acces->agenceId() ?? 0);
        }

        return [
            'date' => $date,
            'caisse_id' => max(0, (int) ($requete['caisse_id'] ?? 0)),
            'agence_id' => $agence,
            'q' => trim((string) ($requete['q'] ?? '')),
        ];
    }

    /**
     * L'écart en pourcentage entre le jour et la veille.
     *
     * Rend null quand la veille est à zéro : « -100 % » depuis rien ne veut
     * rien dire, et la division n'a pas de sens. La valeur absolue au
     * dénominateur évite qu'un solde de veille négatif inverse le signe de
     * l'écart.
     */
    public static function variation(float $jour, float $veille): ?float
    {
        if (abs($veille) < 0.005) {
            return null;
        }

        return round((($jour - $veille) / abs($veille)) * 100, 1);
    }

    // ------------------------------------------------------------------
    // Saisie
    // ------------------------------------------------------------------

    /**
     * Enregistre une entrée que LBP ne connaît pas.
     *
     * @param array<string, mixed> $saisie
     * @return array{0: string, 1: array<int, string>} message, erreurs
     */
    public function enregistrerVersement(array $saisie, int $userId): array
    {
        return $this->enregistrer($saisie, $userId, 'ENTREE');
    }

    /**
     * Enregistre une sortie que LBP ne connaît pas.
     *
     * @param array<string, mixed> $saisie
     * @return array{0: string, 1: array<int, string>} message, erreurs
     */
    public function enregistrerRetrait(array $saisie, int $userId): array
    {
        return $this->enregistrer($saisie, $userId, 'DECAISSEMENT');
    }

    /**
     * Retire un mouvement de la journée.
     *
     * Seule une ligne saisie à la main se retire ici. Un règlement, un appro ou
     * un décaissement de demande de fonds se corrige là où il a été créé :
     * l'effacer de cet écran laisserait la caisse et la facture en désaccord.
     *
     * @return array{0: string, 1: array<int, string>} message, erreurs
     */
    public function supprimer(int $mouvementId, int $userId): array
    {
        if (!$this->acces->peutSaisir()) {
            return ['', ['Votre profil consulte les mouvements de caisse sans les modifier.']];
        }

        $mouvement = $this->repo->mouvementSaisi($mouvementId);

        if ($mouvement === null) {
            return ['', [$this->refusDeSource()]];
        }

        if (!$this->porteeAutorisee((int) ($mouvement['agence_id'] ?? 0))) {
            return ['', ["Ce mouvement appartient à la caisse d'une autre agence."]];
        }

        if (!$this->repo->annulerMouvement($mouvementId, $userId)) {
            return ['', ['Ce mouvement a déjà été retiré de la journée.']];
        }

        AuditLogService::log(
            'ANNULATION',
            'mouvement_caisse',
            $mouvementId,
            [
                'type' => (string) ($mouvement['type'] ?? ''),
                'montant' => (float) ($mouvement['montant'] ?? 0),
                'devise' => (string) ($mouvement['devise'] ?? self::DEVISE_PIVOT),
                'libelle' => (string) ($mouvement['libelle'] ?? ''),
                'date_mouvement' => (string) ($mouvement['date_mouvement'] ?? ''),
            ],
            null
        );

        return ['Mouvement retiré de la journée. La ligne reste au journal, à votre nom.', []];
    }

    // ------------------------------------------------------------------
    // Composition des lignes
    // ------------------------------------------------------------------

    /**
     * @param array{agence_id: int, caisse_id: int, q: string, auto: bool, bloquee: bool} $portee
     * @param array<int, array{id: int, nom: string}> $pivots
     * @return array<int, array<string, mixed>>
     */
    private function versements(string $jour, array $portee, array $pivots): array
    {
        if ($portee['bloquee']) {
            return [];
        }

        $lignes = [];

        /*
         * Les trois sources d'une entrée, lues séparément. Un paiement n'est
         * repris qu'ici ; les mouvements saisis de type 'APPRO' ne sont jamais
         * relus, sans quoi chaque remise du siège compterait double.
         */
        if ($portee['auto']) {
            foreach ($this->repo->paiements($jour, $portee) as $ligne) {
                $lignes[] = $this->ligneDePaiement($ligne, $pivots);
            }

            foreach ($this->repo->approsValides($jour, $portee) as $ligne) {
                $lignes[] = $this->ligneDAppro($ligne, $pivots);
            }
        }

        foreach ($this->repo->mouvementsSaisis($jour, 'ENTREE', $portee) as $ligne) {
            $lignes[] = $this->ligneSaisie($ligne, self::CADRES_ENTREE, $pivots);
        }

        return $this->trier($lignes);
    }

    /**
     * @param array{agence_id: int, caisse_id: int, q: string, auto: bool, bloquee: bool} $portee
     * @param array<int, array{id: int, nom: string}> $pivots
     * @return array<int, array<string, mixed>>
     */
    private function retraits(string $jour, array $portee, array $pivots): array
    {
        if ($portee['bloquee']) {
            return [];
        }

        $lignes = [];

        if ($portee['auto']) {
            foreach ($this->repo->demandesDecaissees($jour, $portee, self::STATUTS_SORTIE) as $ligne) {
                $lignes[] = $this->ligneDeDemande($ligne, $pivots);
            }
        }

        foreach ($this->repo->mouvementsSaisis($jour, 'DECAISSEMENT', $portee) as $ligne) {
            $lignes[] = $this->ligneSaisie($ligne, self::CADRES_SORTIE, $pivots);
        }

        return $this->trier($lignes);
    }

    /**
     * @param array<string, mixed> $ligne
     * @param array<int, array{id: int, nom: string}> $pivots
     * @return array<string, mixed>
     */
    private function ligneDePaiement(array $ligne, array $pivots): array
    {
        $dossier = trim((string) ($ligne['dossier'] ?? ''));
        $cadre = $dossier === '' ? 'FACTURE_COLIS' : 'FACTURE_DOSSIER';
        $mode = self::MODES_PAIEMENT[(string) ($ligne['mode'] ?? '')] ?? 'Mode non précisé';
        $facture = trim((string) ($ligne['numero_facture'] ?? ''));

        return [
            'id' => (int) $ligne['id'],
            'source' => 'auto',
            'date' => $this->horodatage((string) ($ligne['date_paiement'] ?? '')),
            'cadre' => $cadre,
            'cadre_libelle' => self::CADRES_ENTREE[$cadre],
            'dossier' => $dossier === '' ? null : $dossier,
            'libelle' => trim('Règlement facture ' . $facture) . ' (' . $mode . ')',
            'reference' => $facture === '' ? null : $facture,
            'montant' => (float) $ligne['montant'],
            'devise' => $this->deviseLue((string) ($ligne['devise'] ?? '')),
            'tiers' => (string) ($ligne['tiers'] ?? ''),
            'caisse' => $this->nomDeCaisse($ligne, $pivots),
            'caissier' => (string) ($ligne['caissier'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $ligne
     * @param array<int, array{id: int, nom: string}> $pivots
     * @return array<string, mixed>
     */
    private function ligneDAppro(array $ligne, array $pivots): array
    {
        $source = (string) ($ligne['source'] ?? '');
        $motif = trim((string) ($ligne['motif'] ?? ''));

        return [
            'id' => (int) $ligne['id'],
            'source' => 'auto',
            // Un appro n'a pas d'heure : c'est sa date de remise qui compte.
            'date' => (string) ($ligne['date_effet'] ?? ''),
            'cadre' => 'APPRO',
            'cadre_libelle' => self::CADRES_ENTREE['APPRO'],
            'dossier' => null,
            'libelle' => $motif === '' ? 'Approvisionnement de caisse' : $motif,
            'reference' => ((string) ($ligne['numero'] ?? '')) ?: null,
            'montant' => (float) $ligne['montant'],
            'devise' => $this->deviseLue((string) ($ligne['devise'] ?? '')),
            'tiers' => ApproCaisseService::SOURCES[$source] ?? 'Siège',
            'caisse' => $this->nomDeCaisse($ligne, $pivots),
            'caissier' => (string) ($ligne['caissier'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $ligne
     * @param array<int, array{id: int, nom: string}> $pivots
     * @return array<string, mixed>
     */
    private function ligneDeDemande(array $ligne, array $pivots): array
    {
        $cadre = $this->cadreSortie((string) ($ligne['cadre'] ?? ''));
        $dossier = trim((string) ($ligne['dossier_num'] ?? ''));
        $motif = trim((string) ($ligne['motif'] ?? ''));

        return [
            'id' => (int) $ligne['id'],
            'source' => 'auto',
            'date' => $this->horodatage((string) ($ligne['date_decaissement'] ?? '')),
            'cadre' => $cadre,
            'cadre_libelle' => self::CADRES_SORTIE[$cadre],
            'dossier' => $dossier === '' ? null : $dossier,
            'libelle' => $motif === '' ? 'Décaissement de caisse' : $motif,
            'reference' => ((string) ($ligne['numero_demande'] ?? '')) ?: null,
            'montant' => (float) $ligne['montant'],
            'devise' => $this->deviseLue((string) ($ligne['devise'] ?? '')),
            'tiers' => (string) ($ligne['tiers'] ?? ''),
            'caisse' => $this->nomDeCaisse($ligne, $pivots),
            'caissier' => (string) ($ligne['caissier'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $ligne
     * @param array<string, string> $cadres
     * @param array<int, array{id: int, nom: string}> $pivots
     * @return array<string, mixed>
     */
    private function ligneSaisie(array $ligne, array $cadres, array $pivots): array
    {
        $brut = (string) ($ligne['cadre'] ?? '');
        $cadre = $cadres === self::CADRES_SORTIE
            ? $this->cadreSortie($brut)
            : $this->cadreEntree($brut);

        $dossier = trim((string) ($ligne['dossier_numero'] ?? ''));
        $libelle = trim((string) ($ligne['libelle'] ?? ''));
        $caisse = trim((string) ($ligne['caisse'] ?? ''));

        return [
            'id' => (int) $ligne['id'],
            'source' => 'saisie',
            'date' => $this->horodatage((string) ($ligne['created_at'] ?? ''), (string) ($ligne['date_mouvement'] ?? '')),
            'cadre' => $cadre,
            'cadre_libelle' => $cadres[$cadre],
            'dossier' => $dossier === '' ? null : $dossier,
            'libelle' => $libelle === '' ? 'Mouvement de caisse' : $libelle,
            'reference' => ((string) ($ligne['reference'] ?? '')) ?: null,
            'montant' => (float) $ligne['montant'],
            'devise' => $this->deviseLue((string) ($ligne['devise'] ?? '')),
            'tiers' => (string) ($ligne['tiers'] ?? ''),
            'caisse' => $caisse !== '' ? $caisse : $this->nomDeCaisse($ligne, $pivots),
            'caissier' => (string) ($ligne['caissier'] ?? ''),
        ];
    }

    // ------------------------------------------------------------------
    // Cumuls
    // ------------------------------------------------------------------

    /**
     * Ventile des lignes par colonne d'indicateur, une monnaie à la fois.
     *
     * Les francs et les euros sont comptés séparément, jamais additionnés : un
     * total mêlant les deux ne serait juste dans aucune des deux. Une ligne
     * dans une monnaie que la caisse ne tient pas n'entre nulle part — elle
     * reste visible dans l'historique, avec sa devise.
     *
     * @param array<int, array<string, mixed>> $lignes
     * @return array<string, float>
     */
    private function ventiler(array $lignes): array
    {
        $cumuls = [
            'total' => 0.0, 'dossiers' => 0.0, 'colis' => 0.0, 'autres' => 0.0,
            'total_eur' => 0.0, 'dossiers_eur' => 0.0, 'colis_eur' => 0.0, 'autres_eur' => 0.0,
        ];

        foreach ($lignes as $ligne) {
            $devise = (string) $ligne['devise'];

            if ($devise !== self::DEVISE_PIVOT && $devise !== 'EUR') {
                continue;
            }

            $suffixe = $devise === 'EUR' ? '_eur' : '';
            $montant = (float) $ligne['montant'];
            $colonne = self::COLONNES[(string) $ligne['cadre']] ?? 'autres';

            $cumuls['total' . $suffixe] += $montant;
            $cumuls[$colonne . $suffixe] += $montant;
        }

        return $cumuls;
    }

    /**
     * Une carte du jour : la ventilation, la veille et l'écart, dans les deux
     * monnaies. L'ordre des clés est celui du contrat de l'écran.
     *
     * @param array<string, float> $jour
     * @param array<string, float> $veille
     * @return array<string, float|null>
     */
    private function indicateur(array $jour, array $veille): array
    {
        return [
            'total' => $jour['total'],
            'dossiers' => $jour['dossiers'],
            'colis' => $jour['colis'],
            'autres' => $jour['autres'],
            'veille' => $veille['total'],
            'variation' => self::variation($jour['total'], $veille['total']),
            'total_eur' => $jour['total_eur'],
            'dossiers_eur' => $jour['dossiers_eur'],
            'colis_eur' => $jour['colis_eur'],
            'autres_eur' => $jour['autres_eur'],
            'veille_eur' => $veille['total_eur'],
            'variation_eur' => self::variation($jour['total_eur'], $veille['total_eur']),
        ];
    }

    // ------------------------------------------------------------------
    // Saisie, détail
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $saisie
     * @return array{0: string, 1: array<int, string>}
     */
    private function enregistrer(array $saisie, int $userId, string $type): array
    {
        $entree = $type === 'ENTREE';

        if (!$this->acces->peutSaisir()) {
            return ['', ['Votre profil consulte les mouvements de caisse sans les modifier.']];
        }

        $erreurs = [];

        /*
         * L unite de cet ecran est l AGENCE, comme partout ailleurs dans le
         * logiciel — Points de Caisse, les etats journaliers, les demandes de
         * fonds raisonnent tous ainsi.
         *
         * La caisse nommee reste possible, mais facultative : elle venait de
         * l ancien logiciel d une autre maison, ou plusieurs tiroirs
         * coexistaient dans une meme agence. Chez LBP la table est vide, et
         * l exiger rendait la saisie impossible dans les cinq agences.
         * Constate sur la base du 10/10/2026.
         */
        $caisseId = (int) ($saisie['caisse_id'] ?? 0);
        $caisse = $caisseId > 0 ? $this->repo->caisse($caisseId) : null;

        if ($caisseId > 0 && $caisse === null) {
            $erreurs[] = "Cette caisse n'existe pas ou a été retirée.";
        } elseif ($caisse !== null && (int) ($caisse['actif'] ?? 1) !== 1) {
            $erreurs[] = 'Cette caisse est fermée : choisissez-en une autre.';
        }

        // L agence vient de la caisse quand il y en a une, du formulaire sinon.
        $agenceId = $caisse !== null
            ? (int) ($caisse['agence_id'] ?? 0)
            : (int) ($saisie['agence_id'] ?? 0);

        if ($agenceId <= 0) {
            $erreurs[] = "Choisissez l'agence concernée : un mouvement sans agence ne se compte nulle part.";
        } elseif (!$this->porteeAutorisee($agenceId)) {
            $erreurs[] = 'Cette agence n\'est pas la vôtre.';
        }

        $montant = $this->montant($saisie['montant'] ?? null);

        if ($montant <= 0.0) {
            $erreurs[] = 'Le montant doit être supérieur à zéro.';
        }

        $libelle = trim((string) ($saisie['libelle'] ?? ''));

        if ($libelle === '') {
            $erreurs[] = $entree
                ? "Dites d'où vient cet argent : la ligne sera relue au comptage du soir."
                : 'Dites à quoi sert cette sortie : la ligne sera relue au comptage du soir.';
        }

        $date = trim((string) ($saisie['date_mouvement'] ?? ''));

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            $erreurs[] = 'La date du mouvement est obligatoire.';
        } elseif ($date > date('Y-m-d')) {
            // Un tiroir ne contient pas l'argent de demain.
            $erreurs[] = "La date du mouvement ne peut pas être dans l'avenir.";
        }

        $reference = trim((string) ($saisie['reference'] ?? ''));

        if ($reference !== '') {
            $deja = $this->repo->referenceDejaConnue($reference);

            if ($deja !== null) {
                $erreurs[] = 'La référence ' . $reference . ' est celle d\'' . $deja
                    . ' que LBP connaît déjà : elle entre en caisse toute seule. La ressaisir ici'
                    . ' compterait la somme deux fois ; corrigez-la à sa source.';
            }
        }

        if ($erreurs !== []) {
            return ['', $erreurs];
        }

        $valeurs = [
            // Nulle quand l agence ne tient pas de caisse nommee : la colonne
            // accepte le vide, l agence porte l information.
            'caisse_id' => $caisseId > 0 ? $caisseId : null,
            'type' => $type,
            'montant' => $montant,
            'devise' => $this->devise((string) ($saisie['devise'] ?? self::DEVISE_PIVOT)),
            /*
             * Le cadre choisi au formulaire est respecté : c'est lui qui range
             * la somme dans l'une des trois cartes du jour. Le forcer à
             * « autres » ferait mentir la ventilation dès la première saisie,
             * et le sélecteur de l'écran ne serait qu'un ornement.
             *
             * La liste blanche dépend du sens : un versement ne peut pas être
             * un frais de fonctionnement, un décaissement ne règle pas une
             * facture client.
             */
            'cadre' => $entree
                ? $this->cadreSaisiVersement((string) ($saisie['cadre'] ?? ''))
                : $this->cadreSortie((string) ($saisie['cadre'] ?? '')),
            'dossier_numero' => $this->ouNull((string) ($saisie['dossier_numero'] ?? ''), 60),
            'libelle' => mb_substr($libelle, 0, 255),
            'reference' => $this->ouNull($reference, 80),
            'mode_reglement' => $this->mode((string) ($saisie['mode_reglement'] ?? '')),
            'tiers' => $this->ouNull((string) ($saisie['tiers'] ?? ''), 180),
            'agence_id' => $agenceId,
            'date_mouvement' => $date,
            'recorded_by' => $userId,
        ];

        $id = $this->repo->enregistrerMouvement($valeurs);

        AuditLogService::log('CREATION', 'mouvement_caisse', $id, null, $valeurs);

        $nom = trim((string) ($caisse['nom'] ?? '')) ?: $this->nomAgence($agenceId);

        return [
            ($entree ? 'Versement' : 'Retrait') . ' enregistré sur ' . $nom . ' au ' . $this->jour($date) . '.',
            [],
        ];
    }

    /**
     * Le refus opposé à la suppression d'une ligne qui ne vient pas d'une
     * saisie : il doit dire où aller, sinon l'agent recommence.
     */
    private function refusDeSource(): string
    {
        return "Cette ligne ne vient pas d'une saisie de cet écran : elle est reprise de LBP."
            . ' Un règlement se corrige sur sa facture (Finance > Factures Clients),'
            . ' un approvisionnement sur Finance > Appro Caisse,'
            . ' un décaissement sur Finance > Demandes de Fonds. Rien n\'a été modifié ici.';
    }

    // ------------------------------------------------------------------
    // Outils
    // ------------------------------------------------------------------

    /**
     * Les caisses, telles que l'écran les attend.
     *
     * @param array<int, array<string, mixed>> $brutes
     * @return array<int, array{id: int, nom: string, agence: string, type: string, solde: float}>
     */
    private function caisses(array $brutes): array
    {
        return array_values(array_map(
            function (array $caisse): array {
                $agence = (string) ($caisse['agence'] ?? '');
                $nom = trim((string) ($caisse['nom'] ?? ''));

                return [
                    'id' => (int) $caisse['id'],
                    // Les caisses d'avant la nomination n'ont pas de nom : les
                    // afficher vides ne dirait pas de quel tiroir on parle.
                    'nom' => $nom !== '' ? $nom : ('Caisse ' . ($agence !== '' ? $agence : (string) $caisse['id'])),
                    'agence' => $agence,
                    'type' => (string) ($caisse['type'] ?? 'EXPLOITATION'),
                    'solde' => (float) ($caisse['solde'] ?? 0),
                ];
            },
            $brutes
        ));
    }

    /**
     * La caisse à laquelle rattacher ce que LBP ne range pas lui-même.
     *
     * Un règlement, un appro et un décaissement ne connaissent que l'agence :
     * LBP ne sait pas lequel de ses tiroirs a reçu le billet. On les rattache
     * donc à la première caisse d'exploitation ouverte de l'agence — celle du
     * guichet — et à défaut on n'affiche que l'agence.
     *
     * @param array<int, array<string, mixed>> $caisses
     * @return array<int, array{id: int, nom: string}>
     */
    private function pivots(array $caisses): array
    {
        $pivots = [];

        foreach ($caisses as $caisse) {
            $agence = (int) ($caisse['agence_id'] ?? 0);

            if ($agence <= 0 || isset($pivots[$agence])) {
                continue;
            }

            if ((string) ($caisse['type'] ?? '') !== 'EXPLOITATION') {
                continue;
            }

            $pivots[$agence] = [
                'id' => (int) $caisse['id'],
                'nom' => trim((string) ($caisse['nom'] ?? '')),
            ];
        }

        return $pivots;
    }

    /**
     * @param array<string, mixed> $ligne
     * @param array<int, array{id: int, nom: string}> $pivots
     */
    private function nomDeCaisse(array $ligne, array $pivots): string
    {
        $agence = (int) ($ligne['agence_id'] ?? 0);
        $nom = (string) ($pivots[$agence]['nom'] ?? '');

        return $nom !== '' ? $nom : (string) ($ligne['agence'] ?? '');
    }

    /**
     * La portée des requêtes.
     *
     * Une caisse choisie vaut aussi son agence, sans quoi les règlements de
     * cette agence disparaîtraient du tableau. Mais les sources reprises de LBP
     * ne sont rattachées qu'à l'agence : elles ne s'affichent que si la caisse
     * choisie est bien celle du guichet, sinon on laisserait croire que le
     * billet est passé par un autre tiroir.
     *
     * Une portée bloquée ne lit rien : un compte sans agence et sans vue réseau
     * ne doit pas se retrouver avec la caisse de tout le monde sous les yeux.
     *
     * @param array{date: string, caisse_id: int, agence_id: int, q: string} $filtres
     * @param array<int, array<string, mixed>> $caisses
     * @param array<int, array{id: int, nom: string}> $pivots
     * @return array{agence_id: int, caisse_id: int, q: string, auto: bool, bloquee: bool}
     */
    /**
     * Le cumul depuis l origine, borne a la portee mais pas a la date.
     *
     * @param array<string, mixed> $portee
     * @return array<string, float>
     */
    private function cumuls(array $portee): array
    {
        if (!empty($portee['bloquee'])) {
            return [
                'versements' => 0.0, 'retraits' => 0.0, 'solde' => 0.0,
                'versements_eur' => 0.0, 'retraits_eur' => 0.0, 'solde_eur' => 0.0,
            ];
        }

        $brut = $this->repo->cumuls($portee);

        return [
            'versements' => (float) ($brut['versements'] ?? 0),
            'retraits' => (float) ($brut['retraits'] ?? 0),
            'solde' => (float) ($brut['versements'] ?? 0) - (float) ($brut['retraits'] ?? 0),
            'versements_eur' => (float) ($brut['versements_eur'] ?? 0),
            'retraits_eur' => (float) ($brut['retraits_eur'] ?? 0),
            'solde_eur' => (float) ($brut['versements_eur'] ?? 0) - (float) ($brut['retraits_eur'] ?? 0),
        ];
    }

    /** Le nom de l agence, pour que le message de confirmation dise où. */
    private function nomAgence(int $agenceId): string
    {
        foreach ($this->repo->agences() as $agence) {
            if ((int) ($agence['id'] ?? 0) === $agenceId) {
                return (string) ($agence['name'] ?? 'la caisse');
            }
        }

        return 'la caisse';
    }

    private function portee(array $filtres, array $caisses, array $pivots): array
    {
        /*
         * La portee se decide AVANT que la caisse demandee ne reecrive quoi que
         * ce soit, et c est tout l objet de cette methode.
         *
         * Elle le faisait apres : un compte sans agence voyait sa portee passer
         * de « bloquee » a « l agence de la caisse demandee » simplement parce
         * qu il avait ajoute ?caisse_id=42 a l adresse. La caisse d Adjame
         * sortait alors en entier — nom du client et montant — sur l ecran et
         * sur les quatre exports. Demontre le 10/10/2026.
         */
        $reseau = $this->acces->voitToutesLesAgences();
        $bloquee = $filtres['agence_id'] <= 0 && !$reseau;

        if ($bloquee) {
            // Rien a lire, et surtout pas la caisse que l adresse reclamait.
            return [
                'agence_id' => 0,
                'caisse_id' => 0,
                'q' => $filtres['q'],
                'auto' => true,
                'bloquee' => true,
            ];
        }

        $agence = $filtres['agence_id'];
        $caisseDemandee = 0;

        /*
         * Une caisse qui n est pas dans la liste autorisee est ignoree, et non
         * honoree : la liste est la seule chose qui dise ce que ce compte a le
         * droit de voir.
         */
        if ($filtres['caisse_id'] > 0) {
            foreach ($caisses as $caisse) {
                if ((int) $caisse['id'] === $filtres['caisse_id']) {
                    $caisseDemandee = $filtres['caisse_id'];
                    $agence = (int) ($caisse['agence_id'] ?? 0);
                    break;
                }
            }
        }

        $auto = $caisseDemandee <= 0
            || $caisseDemandee === (int) ($pivots[$agence]['id'] ?? 0);

        return [
            'agence_id' => $agence,
            'caisse_id' => $caisseDemandee,
            'q' => $filtres['q'],
            'auto' => $auto,
            'bloquee' => false,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $lignes
     * @return array<int, array<string, mixed>>
     */
    private function trier(array $lignes): array
    {
        usort($lignes, static function (array $a, array $b): int {
            return [$a['date'], $a['source'], $a['id']] <=> [$b['date'], $b['source'], $b['id']];
        });

        return array_values($lignes);
    }

    private function horodatage(string $brut, string $repli = ''): string
    {
        $brut = trim($brut);

        if ($brut === '') {
            return trim($repli);
        }

        $objet = date_create($brut);

        if ($objet === false) {
            return $brut;
        }

        /*
         * La date du mouvement prime sur l'heure d'enregistrement : une entrée
         * de la veille saisie le lendemain matin appartient à la veille.
         */
        if (trim($repli) !== '' && $objet->format('Y-m-d') !== trim($repli)) {
            return trim($repli);
        }

        return $objet->format('Y-m-d H:i');
    }

    private function jour(string $date): string
    {
        $objet = date_create($date);

        return $objet === false ? $date : $objet->format('d/m/Y');
    }

    /**
     * Le cadre d'une entrée, ramené au catalogue.
     *
     * Tout ce qui n'est pas reconnu retombe sur 'AUTRE' : une valeur forgée
     * dans la requête ne doit pas créer une colonne de plus, ni gonfler les
     * dossiers sans qu'aucune facture ne le justifie.
     */
    private function cadreEntree(string $brut): string
    {
        $code = strtoupper(trim($brut));

        return isset(self::CADRES_ENTREE[$code]) ? $code : 'AUTRE';
    }

    /**
     * Le cadre qu'une saisie de versement a le droit de choisir.
     *
     * 'APPRO' est écarté même s'il appartient au catalogue d'affichage : un
     * approvisionnement se saisit sur son écran et entre ici tout seul. Le
     * laisser passer ouvrirait une seconde porte sur la même somme.
     */
    private function cadreSaisiVersement(string $brut): string
    {
        $code = $this->cadreEntree($brut);

        return in_array($code, self::CADRES_SAISIE_VERSEMENT, true) ? $code : 'AUTRE';
    }

    /**
     * Le cadre d'une sortie, ramené au catalogue.
     *
     * Trois vocabulaires arrivent ici — celui des demandes de fonds en
     * minuscules, le mot court du formulaire, et les codes du catalogue. Ils
     * désignent la même chose et doivent ranger la somme dans la même colonne.
     * Le repli est 'FONCTIONNEMENT' : une sortie dont on ne sait rien n'est
     * pas un frais de dossier, et la compter comme tel fausserait la marge
     * d'un dossier que personne n'a facturé.
     */
    private function cadreSortie(string $brut): string
    {
        $code = strtoupper(trim($brut));
        $code = self::ALIAS_SORTIE[$code] ?? $code;

        return isset(self::CADRES_SORTIE[$code]) ? $code : 'FONCTIONNEMENT';
    }

    private function devise(string $devise): string
    {
        $devise = strtoupper(trim($devise));

        return isset(self::DEVISES[$devise]) ? $devise : self::DEVISE_PIVOT;
    }

    /**
     * La devise d une ligne deja enregistree, telle qu elle est.
     *
     * A la lecture, ramener au catalogue ce qu on ne connait pas est un
     * mensonge : 250 USD devenaient 250 FCFA, entraient dans le total en
     * francs, et s affichaient « 250 FCFA » dans l historique. La garde de
     * ventiler() qui promet d ecarter ces lignes etait du code mort, puisque
     * plus rien n arrivait avec une devise etrangere.
     *
     * Les colonnes sont libres en base — char(3) et varchar(10), pas des
     * enumerations : la porte est ouverte, et c est ici qu on la tient.
     * Constate le 10/10/2026.
     */
    private function deviseLue(string $devise): string
    {
        $devise = strtoupper(trim($devise));

        return $devise === '' ? self::DEVISE_PIVOT : mb_substr($devise, 0, 10);
    }

    /**
     * Le mode de règlement, ramené au catalogue.
     *
     * Le formulaire poste le libellé lui-même — « Wave », « Virement bancaire »
     * —, comme le reste du module Finance le fait dans mode_paiement. Sans
     * cette traduction, tout ce qui n'était pas « Espèces » retombait sur
     * ESPECES : un règlement Wave se retrouvait compté comme du liquide en
     * tiroir, et le comptage du soir ne tombait plus juste.
     */
    private function mode(string $mode): string
    {
        $code = strtr(mb_strtoupper(trim($mode)), [
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'À' => 'A', 'Â' => 'A', 'Ç' => 'C', 'Ô' => 'O', 'Û' => 'U', 'Î' => 'I',
        ]);
        $code = str_replace([' ', '-', "'"], '_', $code);
        $code = self::ALIAS_MODES[$code] ?? $code;

        return isset(self::MODES[$code]) ? $code : 'ESPECES';
    }

    /**
     * Lit un montant saisi au clavier : les espaces des milliers et la virgule
     * décimale sont ceux de la saisie française.
     */
    private function montant(mixed $brut): float
    {
        // Les espaces des milliers, insécables compris, puis la virgule décimale.
        $texte = (string) preg_replace('/\s/u', '', (string) ($brut ?? ''));
        $texte = str_replace(',', '.', $texte);

        return round((float) $texte, 2);
    }

    private function ouNull(string $valeur, int $longueur): ?string
    {
        $valeur = trim($valeur);

        return $valeur === '' ? null : mb_substr($valeur, 0, $longueur);
    }

    private function porteeAutorisee(int $agenceId): bool
    {
        if ($this->acces->voitToutesLesAgences()) {
            return true;
        }

        $sienne = (int) ($this->acces->agenceId() ?? 0);

        return $sienne > 0 && $sienne === $agenceId;
    }

    private function veille(string $jour): string
    {
        $objet = date_create($jour);

        return $objet === false ? $jour : $objet->modify('-1 day')->format('Y-m-d');
    }
}
