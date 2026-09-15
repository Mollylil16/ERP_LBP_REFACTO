<?php

declare(strict_types=1);

namespace App\Services\Colisage;

use App\Models\Database;
use App\Repositories\Colisage\DossierEnvoiRepository;
use App\Security\DossierEnvoiAcces;
use DateTimeImmutable;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Dossiers d'envoi : saisie par l'agent export, validation par le Directeur
 * général, pièces jointes et historique.
 *
 * Les règles pures vivent dans DossierEnvoiRegles, les droits dans
 * DossierEnvoiAcces. Ce service lit la saisie, enchaîne les contrôles, écrit en
 * transaction et journalise chaque changement : on doit toujours pouvoir dire
 * qui a modifié un montant et quand.
 */
final class DossierEnvoiService
{
    /** Correspondance entre lbp_expeditions.type_transport et le mode du dossier. */
    private const MODE_DU_DEPART = ['AÉRIEN' => 'AERIEN', 'MARITIME' => 'MARITIME', 'TERRESTRE' => 'ROUTIER'];

    /**
     * Champs suivis au journal : libellé, et colonne lue pour l'afficher (le nom
     * du transporteur plutôt que son identifiant).
     */
    private const CHAMPS_JOURNAL = [
        'mode_transport' => ['Mode de transport', 'mode_transport'],
        'agence_depart_id' => ['Agence de départ', 'agence_depart'],
        'agence_arrivee_id' => ["Agence d'arrivée", 'agence_arrivee'],
        'destination' => ['Destination', 'destination'],
        'expedition_id' => ['Départ du pointage', 'expedition_reference'],
        'transporteur_id' => ['Transporteur', 'transporteur'],
        'type_document' => ['Type de document', 'type_document'],
        'numero_document' => ['Document de transport', 'numero_document'],
        'emetteur_document_id' => ['Émetteur du document', 'emetteur_document'],
        'document_principal' => ['Document principal', 'document_principal'],
        'lieu_depart' => ['Lieu de départ', 'lieu_depart'],
        'lieu_arrivee' => ["Lieu d'arrivée", 'lieu_arrivee'],
        'date_depart_prevue' => ['Départ prévu', 'date_depart_prevue'],
        'date_depart_effective' => ['Départ effectif', 'date_depart_effective'],
        'date_arrivee_estimee' => ['Arrivée estimée', 'date_arrivee_estimee'],
        'date_arrivee' => ['Arrivée', 'date_arrivee'],
        'date_livraison' => ['Livraison', 'date_livraison'],
        'nb_colis_declare' => ['Nombre de colis', 'nb_colis_declare'],
        'poids_brut_kg' => ['Poids brut (kg)', 'poids_brut_kg'],
        'poids_taxable_kg' => ['Poids taxable (kg)', 'poids_taxable_kg'],
        'volume_m3' => ['Volume (m³)', 'volume_m3'],
        'commentaire_ecart' => ["Commentaire d'écart", 'commentaire_ecart'],
        'statut' => ['Statut', 'statut'],
    ];

    /** @var array<int, string> */
    private array $erreursLecture = [];

    public function __construct(
        private PDO $pdo,
        private DossierEnvoiRepository $repo,
        private DossierEnvoiStockage $stockage,
    ) {
    }

    public static function creer(): self
    {
        $pdo = Database::getConnection();

        return new self($pdo, new DossierEnvoiRepository($pdo), DossierEnvoiStockage::creer());
    }

    // ------------------------------------------------------------------
    // Formulaire
    // ------------------------------------------------------------------

    /**
     * Listes proposées dans le formulaire.
     *
     * @return array{agences:array<int, array<string, mixed>>, prestataires:array<int, array<string, mixed>>, departs:array<int, array<string, mixed>>}
     */
    public function referentiels(?int $expeditionLiee): array
    {
        return [
            'agences' => $this->repo->agences(),
            'prestataires' => $this->repo->prestataires(),
            'departs' => $this->repo->departsRecents($expeditionLiee),
        ];
    }

    /**
     * Dossier vierge, prérempli depuis un départ du pointage quand on part de lui.
     *
     * @return array{dossier:array<string, mixed>, tranches:array<int, mixed>, emballages:array<int, mixed>, frais:array<int, mixed>}
     */
    public function nouveau(?int $expeditionId): array
    {
        $dossier = array_fill_keys(array_keys(self::CHAMPS_JOURNAL), null);
        $dossier['id'] = null;
        $dossier['numero'] = null;
        $dossier['statut'] = 'BROUILLON';
        $dossier['mode_transport'] = 'AERIEN';

        $depart = $expeditionId !== null ? $this->repo->depart($expeditionId) : null;

        if ($depart !== null) {
            $dossier['expedition_id'] = (int) $depart['id'];
            $dossier['agence_depart_id'] = $depart['agence_depart_id'] !== null ? (int) $depart['agence_depart_id'] : null;
            $dossier['agence_arrivee_id'] = $depart['agence_arrivee_id'] !== null ? (int) $depart['agence_arrivee_id'] : null;
            $dossier['mode_transport'] = self::MODE_DU_DEPART[(string) $depart['type_transport']] ?? 'AERIEN';
            $dossier['date_depart_prevue'] = substr((string) $depart['date_depart'], 0, 10);
            $dossier['nb_colis_declare'] = (int) $depart['nb_colis'] > 0 ? (int) $depart['nb_colis'] : null;
        }

        return ['dossier' => $dossier, 'tranches' => [], 'emballages' => [], 'frais' => []];
    }

    /**
     * @return array{dossier:array<string, mixed>, tranches:array<int, mixed>, emballages:array<int, mixed>, frais:array<int, mixed>}|null
     */
    public function formulaire(int $id, DossierEnvoiAcces $acces): ?array
    {
        $dossier = $this->repo->trouver($id);

        if ($dossier === null || !$acces->peutModifier($dossier)) {
            return null;
        }

        return [
            'dossier' => $dossier,
            'tranches' => $this->repo->tranches($id),
            'emballages' => $this->repo->emballages($id),
            'frais' => $this->repo->frais($id),
        ];
    }

    /**
     * Ce que l'agent vient de saisir, remis en forme pour réafficher le
     * formulaire après un refus, sans rien perdre.
     *
     * @param array<string, mixed> $saisie
     * @return array{dossier:array<string, mixed>, tranches:array<int, mixed>, emballages:array<int, mixed>, frais:array<int, mixed>}
     */
    public function brouillonDepuisSaisie(array $saisie, ?int $id): array
    {
        $this->erreursLecture = [];
        $existant = $id !== null ? $this->repo->trouver($id) : null;

        $dossier = $this->lireDossier($saisie) + [
            'id' => $id,
            'numero' => $existant['numero'] ?? null,
            'statut' => $existant['statut'] ?? 'BROUILLON',
        ];
        $mode = $dossier['mode_transport'] !== '' ? (string) $dossier['mode_transport'] : 'AERIEN';

        $facturesDuPoste = [];
        foreach ($id !== null ? $this->repo->frais($id) : [] as $ligne) {
            $facturesDuPoste[(string) $ligne['poste']] ??= $ligne;
        }

        [$fixes, $autres] = $this->lireFrais($saisie);
        $frais = [];
        foreach ($fixes as $poste => $ligne) {
            $existe = $facturesDuPoste[$poste] ?? [];
            $frais[] = $ligne + [
                'poste' => $poste,
                'montant_facture' => $existe['montant_facture'] ?? null,
                'devise_facture' => $existe['devise_facture'] ?? null,
                'numero_facture' => $existe['numero_facture'] ?? null,
            ];
        }
        foreach ($autres as $ligne) {
            $frais[] = $ligne + ['poste' => DossierEnvoiRegles::POSTE_AUTRE];
        }

        $emballages = [];
        foreach ((array) ($saisie['emballages'] ?? []) as $ligne) {
            if (is_array($ligne) && trim((string) ($ligne['type'] ?? '')) !== '') {
                $emballages[] = ['type' => (string) $ligne['type'], 'quantite' => (string) ($ligne['quantite'] ?? '')];
            }
        }

        return [
            'dossier' => $dossier,
            'tranches' => $this->lireTranches($saisie, $mode),
            'emballages' => $emballages,
            'frais' => $frais,
        ];
    }

    /**
     * Crée ou met à jour un dossier.
     *
     * @param array<string, mixed> $saisie
     * @throws DossierEnvoiInvalide avec la liste complète des corrections
     */
    public function enregistrer(?int $id, array $saisie, DossierEnvoiAcces $acces): int
    {
        $this->erreursLecture = [];
        $userId = $acces->userId();
        $existant = null;

        if ($id !== null) {
            $existant = $this->repo->trouver($id);
            if ($existant === null || !$acces->peutModifier($existant)) {
                throw new DossierEnvoiInvalide(["Ce dossier n'est plus modifiable : il a été soumis, validé, ou n'est pas le vôtre."]);
            }
        } elseif (!$acces->peutCreer()) {
            throw new DossierEnvoiInvalide(["Seul l'agent export peut ouvrir un dossier d'envoi."]);
        }

        $dossier = $this->lireDossier($saisie);
        $tranches = $this->lireTranches($saisie, $dossier['mode_transport'] !== '' ? (string) $dossier['mode_transport'] : 'AERIEN');
        $emballages = $this->lireEmballages($saisie);
        [$fixes, $autres] = $this->lireFrais($saisie);

        $transporteur = $dossier['transporteur_id'] !== null ? $this->repo->prestataire((int) $dossier['transporteur_id']) : null;
        $controle = DossierEnvoiRegles::controlerSaisie($dossier, $tranches, $transporteur, $this->aujourdhui());
        $dossier = $controle['dossier'];
        $tranches = $controle['tranches'];

        $erreurs = array_merge($this->erreursLecture, $controle['erreurs'], DossierEnvoiRegles::controlerFrais($fixes, $autres));

        if ($dossier['agence_depart_id'] !== null && $this->repo->agence((int) $dossier['agence_depart_id']) === null) {
            $erreurs[] = "L'agence de départ choisie n'existe plus.";
        }
        if ($dossier['transporteur_id'] !== null && $transporteur === null) {
            $erreurs[] = "Le transporteur choisi n'existe plus.";
        }
        if ($dossier['expedition_id'] !== null && $this->repo->depart((int) $dossier['expedition_id']) === null) {
            $erreurs[] = "Le départ du pointage choisi n'existe plus.";
        }

        if ($dossier['numero_document'] !== null) {
            $fils = in_array($dossier['type_document'], DossierEnvoiRegles::DOCUMENTS_FILS, true);
            $tousLesTypes = array_merge(...array_map('array_keys', array_values(DossierEnvoiRegles::DOCUMENTS_DU_MODE)));
            $types = $fils ? DossierEnvoiRegles::DOCUMENTS_FILS : array_values(array_diff($tousLesTypes, DossierEnvoiRegles::DOCUMENTS_FILS));
            $doublon = $this->repo->documentDejaUtilise(
                (string) $dossier['numero_document'],
                $types,
                $fils ? 'emetteur_document_id' : 'transporteur_id',
                $fils ? $dossier['emetteur_document_id'] : $dossier['transporteur_id'],
                $id ?? 0
            );
            if ($doublon !== null) {
                $erreurs[] = 'Le document ' . $dossier['numero_document'] . ' est déjà utilisé par le dossier ' . $doublon . '.';
            }
        }

        if ($erreurs !== []) {
            throw new DossierEnvoiInvalide(array_values(array_unique($erreurs)));
        }

        $this->pdo->beginTransaction();

        try {
            if ($existant === null) {
                $agence = $this->repo->agence((int) $dossier['agence_depart_id']) ?? [];
                $prefixe = DossierEnvoiRegles::prefixeNumero(
                    DossierEnvoiRegles::codeAgence($agence),
                    new DateTimeImmutable($this->repo->maintenant())
                );

                $dossier['numero'] = DossierEnvoiRegles::numeroDossier($prefixe, $this->repo->prochainRang($prefixe));
                $dossier['statut'] = DossierEnvoiRegles::statutApresEnregistrement('BROUILLON', $dossier);
                $dossier['responsable_id'] = $userId;
                $dossier['created_by'] = $userId;
                $dossier['taux_eur_xof'] = $this->repo->tauxEurXof() ?? DossierEnvoiRegles::TAUX_EUR_XOF_DEFAUT;

                $id = $this->repo->inserer($dossier);
                $this->repo->journaliser($id, $userId, 'CREATION', null, null, (string) $dossier['numero'], null);

                $avant = ['tranches' => '', 'emballages' => '', 'frais' => ''];
            } else {
                $dossier['statut'] = DossierEnvoiRegles::statutApresEnregistrement((string) $existant['statut'], $dossier);
                $avant = [
                    'tranches' => $this->resumeTranches($this->repo->tranches($id)),
                    'emballages' => $this->resumeEmballages($this->repo->emballages($id)),
                    'frais' => $this->resumeFrais($this->repo->frais($id)),
                ];
                $this->repo->mettreAJour($id, $dossier);
            }

            $this->repo->remplacerTranches($id, $tranches);
            $this->repo->remplacerEmballages($id, $emballages);
            foreach ($fixes as $poste => $ligne) {
                $this->repo->enregistrerPosteFixe($id, $poste, $ligne);
            }
            $this->repo->remplacerAutresFrais($id, $autres);

            if ($existant !== null) {
                $apres = $this->repo->trouver($id) ?? [];
                foreach ($this->differences($existant, $apres) as [$champ, $ancien, $nouveau]) {
                    $this->repo->journaliser($id, $userId, 'MODIFICATION', $champ, $ancien, $nouveau, null);
                }

                $maintenant = [
                    'tranches' => $this->resumeTranches($this->repo->tranches($id)),
                    'emballages' => $this->resumeEmballages($this->repo->emballages($id)),
                    'frais' => $this->resumeFrais($this->repo->frais($id)),
                ];
                foreach (['tranches' => 'Tranches', 'emballages' => 'Emballages', 'frais' => 'Frais'] as $cle => $libelle) {
                    if ($avant[$cle] !== $maintenant[$cle]) {
                        $this->repo->journaliser($id, $userId, 'MODIFICATION', $libelle, $avant[$cle] ?: '—', $maintenant[$cle] ?: '—', null);
                    }
                }
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $id;
    }

    // ------------------------------------------------------------------
    // Fiche et pièces jointes
    // ------------------------------------------------------------------

    /** @return array<string, mixed>|null */
    public function fiche(int $id, DossierEnvoiAcces $acces): ?array
    {
        $dossier = $this->repo->trouver($id);

        if ($dossier === null || !$acces->peutVoir($dossier)) {
            return null;
        }

        $frais = $this->repo->frais($id);
        $documents = $this->repo->documents($id);
        $types = array_values(array_unique(array_map('strval', array_column($documents, 'type_document'))));
        $emballages = $this->repo->emballages($id);
        $pointes = $this->colisPointes($dossier);

        return [
            'dossier' => $dossier,
            'tranches' => $this->repo->tranches($id),
            'emballages' => $emballages,
            'frais' => $frais,
            'documents' => $documents,
            'synthese' => DossierEnvoiRegles::synthese($dossier, $frais, $emballages, $types, $pointes),
            'manques' => DossierEnvoiRegles::controlerSoumission($dossier, $frais, $emballages, $types, $pointes),
            'journal' => $this->repo->journal($id),
            'responsables' => $acces->peutReaffecter($dossier) ? $this->repo->utilisateursDuRole(DossierEnvoiAcces::ROLE_AGENT) : [],
            'droits' => [
                'modifier' => $acces->peutModifier($dossier),
                'soumettre' => $acces->peutSoumettre($dossier),
                'annuler' => $acces->peutAnnuler($dossier),
                'valider' => $acces->peutValider($dossier),
                'renvoyer' => $acces->peutRenvoyer($dossier),
                'rouvrir' => $acces->peutRouvrir($dossier),
                'reaffecter' => $acces->peutReaffecter($dossier),
            ],
        ];
    }

    /**
     * Joint une pièce. Une facture porte son montant : c'est le seul endroit où
     * le montant facturé se saisit.
     *
     * @param array<string, mixed> $saisie
     * @param array<string, mixed> $fichier
     */
    public function deposerDocument(int $id, array $saisie, array $fichier, DossierEnvoiAcces $acces): string
    {
        $this->erreursLecture = [];
        $dossier = $this->repo->trouver($id);

        if ($dossier === null || !$acces->peutModifier($dossier)) {
            throw new DossierEnvoiInvalide(["Les pièces de ce dossier ne peuvent plus être modifiées : il a été soumis, validé, ou n'est pas le vôtre."]);
        }

        $type = strtoupper(trim((string) ($saisie['type_document'] ?? '')));
        if (!isset(DossierEnvoiRegles::PIECES[$type])) {
            throw new DossierEnvoiInvalide(['Choisissez le type de document.']);
        }

        $poste = array_search($type, DossierEnvoiRegles::PIECE_DU_POSTE, true);
        $poste = is_string($poste) ? $poste : null;
        $montant = null;
        $devise = 'XOF';
        $numeroFacture = null;

        if ($poste !== null) {
            $montant = $this->nombre($saisie['montant_facture'] ?? null, 'Montant de la facture');
            $devise = $this->devise($saisie['devise_facture'] ?? null);
            $numeroFacture = $this->texte($saisie['numero_facture'] ?? null, 60);

            if ($montant === null && $this->erreursLecture === []) {
                $this->erreursLecture[] = 'Indiquez le montant de la facture : il sera comparé au montant prévu.';
            } elseif ($montant !== null && $montant < 0) {
                $this->erreursLecture[] = 'Le montant de la facture ne peut pas être négatif.';
            }
        }

        if ($this->erreursLecture !== []) {
            throw new DossierEnvoiInvalide($this->erreursLecture);
        }

        $base = DossierEnvoiRegles::nomFichier((string) $dossier['numero'], $type, $this->repo->compterDocuments($id, $type) + 1);

        try {
            $range = $this->stockage->ranger($fichier, $base);
        } catch (RuntimeException $e) {
            throw new DossierEnvoiInvalide([$e->getMessage()]);
        }

        $nom = $base . '.' . $range['extension'];
        $userId = $acces->userId();

        $this->pdo->beginTransaction();

        try {
            $documentId = $this->repo->insererDocument([
                'dossier_id' => $id,
                'type_document' => $type,
                'nom_fichier' => $nom,
                'original_name' => $range['original_name'],
                'stored_path' => $range['stored_path'],
                'mime_type' => $range['mime_type'],
                'size_bytes' => $range['size_bytes'],
                'uploaded_by' => $userId,
            ]);
            $this->repo->journaliser($id, $userId, 'DOCUMENT_AJOUT', DossierEnvoiRegles::PIECES[$type], null, $nom, null);

            if ($poste !== null) {
                $this->repo->enregistrerFacture($id, $poste, (float) $montant, $devise, $numeroFacture, $documentId);
                $this->repo->journaliser(
                    $id,
                    $userId,
                    'FACTURE',
                    DossierEnvoiRegles::POSTES[$poste],
                    null,
                    DossierEnvoiRegles::nombre((float) $montant, 2) . ' ' . $devise . ($numeroFacture !== null ? ' (facture ' . $numeroFacture . ')' : ''),
                    null
                );
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            $this->stockage->effacer($range['stored_path']);
            throw $e;
        }

        return DossierEnvoiRegles::PIECES[$type] . ' jointe au dossier : ' . $nom . '.';
    }

    public function retirerDocument(int $id, int $documentId, DossierEnvoiAcces $acces): string
    {
        $dossier = $this->repo->trouver($id);

        if ($dossier === null || !$acces->peutModifier($dossier)) {
            throw new DossierEnvoiInvalide(["Les pièces de ce dossier ne peuvent plus être modifiées : il a été soumis, validé, ou n'est pas le vôtre."]);
        }

        $document = $this->repo->document($id, $documentId);
        if ($document === null) {
            throw new DossierEnvoiInvalide(['Cette pièce a déjà été retirée.']);
        }

        $this->pdo->beginTransaction();

        try {
            $this->repo->retirerDocument($documentId, $acces->userId());
            $this->repo->effacerFactureDuDocument($documentId);
            $this->repo->journaliser(
                $id,
                $acces->userId(),
                'DOCUMENT_RETRAIT',
                DossierEnvoiRegles::PIECES[(string) $document['type_document']] ?? (string) $document['type_document'],
                (string) $document['nom_fichier'],
                null,
                null
            );
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return 'Pièce retirée : ' . $document['nom_fichier'] . '.';
    }

    /** @return array{chemin:string, nom:string, mime:string}|null */
    public function telechargement(int $id, int $documentId, DossierEnvoiAcces $acces): ?array
    {
        $dossier = $this->repo->trouver($id);
        if ($dossier === null || !$acces->peutVoir($dossier)) {
            return null;
        }

        $document = $this->repo->document($id, $documentId);
        $chemin = $document !== null ? $this->stockage->cheminAbsolu((string) $document['stored_path']) : null;

        return $chemin === null ? null : [
            'chemin' => $chemin,
            'nom' => (string) $document['nom_fichier'],
            'mime' => (string) $document['mime_type'],
        ];
    }

    // ------------------------------------------------------------------
    // Circuit : soumettre, valider, renvoyer, rouvrir, réaffecter, annuler
    // ------------------------------------------------------------------

    public function soumettre(int $id, DossierEnvoiAcces $acces): string
    {
        $dossier = $this->exiger($id, $acces->peutModifier(...), "Ce dossier ne peut plus être soumis : il a déjà été soumis, validé, ou n'est pas le vôtre.");

        $frais = $this->repo->frais($id);
        $types = array_values(array_unique(array_map('strval', array_column($this->repo->documents($id), 'type_document'))));
        $manques = DossierEnvoiRegles::controlerSoumission($dossier, $frais, $this->repo->emballages($id), $types, $this->colisPointes($dossier));

        if ($manques !== []) {
            throw new DossierEnvoiInvalide($manques);
        }

        $this->circuit($id, ['statut' => 'SOUMIS', 'soumis_le' => $this->repo->maintenant()], $acces, 'SOUMISSION', (string) $dossier['statut'], null);

        return 'Dossier ' . $dossier['numero'] . ' soumis au Directeur général.';
    }

    public function valider(int $id, DossierEnvoiAcces $acces): string
    {
        $dossier = $this->exiger($id, $acces->peutValider(...), 'Seul un dossier soumis peut être validé, et seulement par le Directeur général.');

        $this->circuit($id, [
            'statut' => 'VALIDE',
            'valide_par_id' => $acces->userId(),
            'valide_le' => $this->repo->maintenant(),
        ], $acces, 'VALIDATION', (string) $dossier['statut'], null);

        return 'Dossier ' . $dossier['numero'] . ' validé : il est désormais verrouillé.';
    }

    public function renvoyer(int $id, mixed $motif, DossierEnvoiAcces $acces): string
    {
        $dossier = $this->exiger($id, $acces->peutRenvoyer(...), 'Seul un dossier soumis peut être renvoyé, et seulement par le Directeur général.');
        $motif = $this->motif($motif, "Indiquez le motif du renvoi : l'agent export le verra sur son dossier.");

        $this->circuit($id, ['statut' => 'A_CORRIGER', 'motif_renvoi' => $motif], $acces, 'RENVOI', (string) $dossier['statut'], $motif);

        return 'Dossier ' . $dossier['numero'] . " renvoyé à l'agent export pour correction.";
    }

    public function rouvrir(int $id, mixed $motif, DossierEnvoiAcces $acces): string
    {
        $dossier = $this->exiger($id, $acces->peutRouvrir(...), 'Seul un dossier validé peut être rouvert, et seulement par le Directeur général.');
        $motif = $this->motif($motif, 'Indiquez le motif de la réouverture : il reste inscrit au journal du dossier.');

        $this->circuit($id, [
            'statut' => 'A_CORRIGER',
            'valide_par_id' => null,
            'valide_le' => null,
            'motif_renvoi' => $motif,
        ], $acces, 'REOUVERTURE', (string) $dossier['statut'], $motif);

        return 'Dossier ' . $dossier['numero'] . " rouvert : l'agent export peut le corriger.";
    }

    public function reaffecter(int $id, mixed $nouveauResponsable, DossierEnvoiAcces $acces): string
    {
        $dossier = $this->exiger($id, $acces->peutReaffecter(...), 'Ce dossier ne peut plus changer de responsable.');
        $nouveau = (int) $nouveauResponsable;

        $cible = null;
        foreach ($this->repo->utilisateursDuRole(DossierEnvoiAcces::ROLE_AGENT) as $agent) {
            if ($agent['id'] === $nouveau) {
                $cible = $agent;
            }
        }

        if ($cible === null) {
            throw new DossierEnvoiInvalide(['Choisissez un agent export actif.']);
        }

        if ((int) ($dossier['responsable_id'] ?? 0) === $nouveau) {
            return $cible['full_name'] . ' est déjà responsable de ce dossier.';
        }

        $this->pdo->beginTransaction();
        try {
            $this->repo->changerCircuit($id, ['responsable_id' => $nouveau]);
            $this->repo->journaliser($id, $acces->userId(), 'REAFFECTATION', 'Responsable', (string) ($dossier['responsable'] ?? '—'), $cible['full_name'], null);
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return 'Dossier ' . $dossier['numero'] . ' confié à ' . $cible['full_name'] . '.';
    }

    public function annuler(int $id, mixed $motif, DossierEnvoiAcces $acces): string
    {
        $dossier = $this->exiger($id, $acces->peutAnnuler(...), "Un dossier ne s'annule que tant que la marchandise n'est pas partie.");
        $motif = $this->motif($motif, "Indiquez le motif de l'annulation.");

        $this->circuit($id, ['statut' => 'ANNULE', 'motif_annulation' => $motif], $acces, 'ANNULATION', (string) $dossier['statut'], $motif);

        return 'Dossier ' . $dossier['numero'] . ' annulé. Son numéro ne sera pas réutilisé.';
    }

    // ------------------------------------------------------------------
    // Listes
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $requete
     * @return array<string, mixed>
     */
    public function liste(array $requete, DossierEnvoiAcces $acces): array
    {
        $statut = (string) ($requete['statut'] ?? 'en_cours');
        $filtres = ['responsable_id' => $acces->responsableImpose()];
        $responsable = null;

        if ($acces->voitTout() && (int) ($requete['responsable'] ?? 0) > 0) {
            $responsable = (int) $requete['responsable'];
            $filtres['responsable_id'] = $responsable;
        }

        if ($statut === 'en_cours') {
            $filtres['statuts_exclus'] = ['VALIDE', 'ANNULE', 'REPRIS'];
        } elseif (isset(DossierEnvoiRegles::STATUTS[$statut])) {
            $filtres['statuts'] = [$statut];
        } else {
            $statut = 'tous';
        }

        $mode = strtoupper((string) ($requete['mode'] ?? ''));
        if (isset(DossierEnvoiRegles::MODES[$mode])) {
            $filtres['mode'] = $mode;
        } else {
            $mode = '';
        }

        $filtres['q'] = trim((string) ($requete['q'] ?? ''));

        return [
            'filtres' => ['statut' => $statut, 'responsable' => $responsable, 'mode' => $mode, 'q' => $filtres['q']],
            'dossiers' => $this->enrichir($this->repo->lister($filtres, 300)),
            'departs_sans_dossier' => $this->repo->departsSansDossier(),
            'responsables' => $acces->voitTout() ? $this->repo->utilisateursDuRole(DossierEnvoiAcces::ROLE_AGENT) : [],
        ];
    }

    /**
     * Dossiers soumis, du plus ancien au plus récent.
     *
     * @return array<int, array<string, mixed>>
     */
    public function aValider(DossierEnvoiAcces $acces): array
    {
        if (!$acces->estValideur()) {
            return [];
        }

        $dossiers = $this->enrichir($this->repo->lister(['statuts' => ['SOUMIS']], 300));
        usort($dossiers, static fn (array $a, array $b): int => strcmp((string) $a['soumis_le'], (string) $b['soumis_le']));

        return $dossiers;
    }

    /**
     * Une ligne par pièce attendue et absente.
     *
     * @return array<int, array{dossier:array<string, mixed>, type:string, libelle:string, peut_deposer:bool}>
     */
    public function piecesManquantes(DossierEnvoiAcces $acces): array
    {
        $dossiers = $this->enrichir($this->repo->lister([
            'responsable_id' => $acces->responsableImpose(),
            'statuts' => DossierEnvoiRegles::STATUTS_PIECES_ATTENDUES,
        ], 500));

        $lignes = [];
        foreach ($dossiers as $dossier) {
            foreach ($dossier['synthese']['pieces_manquantes'] as $type => $libelle) {
                $lignes[] = ['dossier' => $dossier, 'type' => (string) $type, 'libelle' => $libelle, 'peut_deposer' => $acces->peutModifier($dossier)];
            }
        }

        return $lignes;
    }

    /**
     * Historique filtré : ce qui s'affiche est exactement ce qui s'exporte.
     *
     * @param array<string, mixed> $requete
     * @return array<string, mixed>
     */
    public function historique(array $requete, DossierEnvoiAcces $acces): array
    {
        $aujourdhui = new DateTimeImmutable($this->aujourdhui());
        $periode = DossierEnvoiRegles::resoudrePeriode(
            (string) ($requete['periode'] ?? 'ce_mois'),
            (string) ($requete['du'] ?? ''),
            (string) ($requete['au'] ?? ''),
            $aujourdhui
        );

        $responsable = $acces->responsableImpose() ?? ((int) ($requete['responsable'] ?? 0) ?: null);
        $mode = strtoupper((string) ($requete['mode'] ?? ''));
        $mode = isset(DossierEnvoiRegles::MODES[$mode]) ? $mode : '';
        $statut = strtoupper((string) ($requete['statut'] ?? ''));
        $statut = isset(DossierEnvoiRegles::STATUTS[$statut]) ? $statut : '';

        $filtres = [
            'periode' => $periode['periode'],
            'du' => $periode['du'],
            'au' => $periode['au'],
            'responsable' => $responsable,
            'mode' => $mode,
            'agence' => (int) ($requete['agence'] ?? 0) ?: null,
            'transporteur' => (int) ($requete['transporteur'] ?? 0) ?: null,
            'transitaire' => (int) ($requete['transitaire'] ?? 0) ?: null,
            'statut' => $statut,
            'pieces' => !empty($requete['pieces']),
            'ecart_colis' => !empty($requete['ecart_colis']),
            'ecart_facture' => !empty($requete['ecart_facture']),
            'q' => trim((string) ($requete['q'] ?? '')),
        ];

        $criteres = [
            'responsable_id' => $responsable,
            'du' => $filtres['du'],
            'au' => $filtres['au'],
            'mode' => $mode,
            'agence_depart_id' => $filtres['agence'],
            'transporteur_id' => $filtres['transporteur'],
            'transitaire_id' => $filtres['transitaire'],
            'q' => $filtres['q'],
        ];
        if ($statut !== '') {
            $criteres['statuts'] = [$statut];
        } else {
            $criteres['statuts_exclus'] = ['ANNULE'];
        }

        $dossiers = array_values(array_filter(
            $this->enrichir($this->repo->lister($criteres, 2000)),
            static function (array $d) use ($filtres): bool {
                $s = $d['synthese'];

                return (!$filtres['pieces'] || $s['pieces_manquantes'] !== [])
                    && (!$filtres['ecart_colis'] || (int) ($s['ecart_colis'] ?? 0) !== 0)
                    && (!$filtres['ecart_facture'] || $s['ecart_facture_depasse']);
            }
        ));

        $prestataires = $this->repo->prestataires();
        $agences = $this->repo->agences();
        $responsables = $this->repo->utilisateursDuRole(DossierEnvoiAcces::ROLE_AGENT);

        return [
            'filtres' => $filtres,
            'dossiers' => $dossiers,
            'totaux' => $this->totaux($dossiers),
            'agences' => $agences,
            'prestataires' => $prestataires,
            'responsables' => $acces->voitTout() ? $responsables : [],
            'voit_tout' => $acces->voitTout(),
            'libelles_filtres' => $this->libellesFiltres($filtres, $agences, $prestataires, $responsables),
            'edite_le' => $this->repo->maintenant(),
        ];
    }

    /**
     * Dossiers rattachés à des départs du pointage.
     *
     * @param array<int, int> $expeditionIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function dossiersDesDeparts(array $expeditionIds): array
    {
        return $this->repo->dossiersDesDeparts(array_values(array_unique(array_filter(array_map('intval', $expeditionIds)))));
    }

    // ------------------------------------------------------------------
    // Prestataires
    // ------------------------------------------------------------------

    /** @return array<int, array<string, mixed>> */
    public function prestataires(): array
    {
        return $this->repo->prestataires();
    }

    /** @param array<string, mixed> $saisie */
    public function enregistrerPrestataire(array $saisie, DossierEnvoiAcces $acces): string
    {
        if (!$acces->peutOuvrir()) {
            throw new DossierEnvoiInvalide(["Vous n'avez pas l'habilitation requise pour gérer les prestataires."]);
        }

        $erreurs = [];
        $id = $this->identifiant($saisie['id'] ?? null);
        $type = strtoupper(trim((string) ($saisie['type'] ?? '')));
        $prefixe = preg_replace('/\D/', '', (string) ($saisie['prefixe_lta'] ?? '')) ?? '';
        $pays = $this->texte($saisie['country'] ?? null, 100);

        if (!isset(DossierEnvoiRegles::TYPES_PRESTATAIRE[$type])) {
            $erreurs[] = 'Choisissez le type du prestataire.';
        }
        if ($prefixe !== '' && strlen($prefixe) !== 3) {
            $erreurs[] = 'Le préfixe LTA compte 3 chiffres (057 pour Air France).';
        }

        if ($id === null) {
            $nom = $this->texte($saisie['name'] ?? null, 150);
            if ($nom === null) {
                $erreurs[] = 'Indiquez le nom du prestataire.';
            } elseif ($this->repo->prestataireNomme($nom, 0) !== null) {
                $erreurs[] = '« ' . $nom . ' » existe déjà : modifiez-le dans la liste plutôt que de le créer deux fois.';
            }
            if ($erreurs !== []) {
                throw new DossierEnvoiInvalide($erreurs);
            }

            $this->repo->creerPrestataire($type, (string) $nom, $pays, $prefixe !== '' ? $prefixe : null);

            return 'Prestataire « ' . $nom . ' » ajouté.';
        }

        $existant = $this->repo->prestataire($id);
        if ($existant === null) {
            $erreurs[] = 'Ce prestataire n\'existe plus.';
        }
        if ($erreurs !== []) {
            throw new DossierEnvoiInvalide($erreurs);
        }

        $this->repo->modifierPrestataire($id, $type, $pays, $prefixe !== '' ? $prefixe : null, !empty($saisie['is_active']));

        return 'Prestataire « ' . $existant['name'] . ' » mis à jour.';
    }

    // ------------------------------------------------------------------
    // Lecture de la saisie
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $s
     * @return array<string, mixed>
     */
    private function lireDossier(array $s): array
    {
        $mode = strtoupper(trim((string) ($s['mode_transport'] ?? '')));

        return [
            'mode_transport' => isset(DossierEnvoiRegles::MODES[$mode]) ? $mode : '',
            'agence_depart_id' => $this->identifiant($s['agence_depart_id'] ?? null),
            'agence_arrivee_id' => $this->identifiant($s['agence_arrivee_id'] ?? null),
            'destination' => $this->texte($s['destination'] ?? null, 150),
            'expedition_id' => $this->identifiant($s['expedition_id'] ?? null),
            'transporteur_id' => $this->identifiant($s['transporteur_id'] ?? null),
            'type_document' => strtoupper(trim((string) ($s['type_document'] ?? ''))),
            'numero_document' => $this->texte(mb_strtoupper((string) ($s['numero_document'] ?? '')), 60),
            'emetteur_document_id' => $this->identifiant($s['emetteur_document_id'] ?? null),
            'document_principal' => $this->texte(mb_strtoupper((string) ($s['document_principal'] ?? '')), 60),
            'lieu_depart' => $this->texte(mb_strtoupper((string) ($s['lieu_depart'] ?? '')), 100),
            'lieu_arrivee' => $this->texte(mb_strtoupper((string) ($s['lieu_arrivee'] ?? '')), 100),
            'date_depart_prevue' => $this->date($s['date_depart_prevue'] ?? null, 'Date de départ prévue'),
            'date_depart_effective' => $this->date($s['date_depart_effective'] ?? null, 'Date de départ effective'),
            'date_arrivee_estimee' => $this->date($s['date_arrivee_estimee'] ?? null, "Date d'arrivée estimée"),
            'date_arrivee' => $this->date($s['date_arrivee'] ?? null, "Date d'arrivée"),
            'date_livraison' => $this->date($s['date_livraison'] ?? null, 'Date de livraison'),
            'nb_colis_declare' => $this->nombre($s['nb_colis_declare'] ?? null, 'Nombre de colis', true),
            'poids_brut_kg' => $this->nombre($s['poids_brut_kg'] ?? null, 'Poids brut'),
            'poids_taxable_kg' => $this->nombre($s['poids_taxable_kg'] ?? null, 'Poids taxable'),
            'volume_m3' => $this->nombre($s['volume_m3'] ?? null, 'Volume'),
            'commentaire_ecart' => $this->texte($s['commentaire_ecart'] ?? null, 2000),
        ];
    }

    /**
     * @param array<string, mixed> $s
     * @return array<int, array<string, mixed>>
     */
    private function lireTranches(array $s, string $mode): array
    {
        $tranches = [];

        foreach ((array) ($s['tranches'] ?? []) as $t) {
            if (!is_array($t)) {
                continue;
            }

            $conteneur = (string) ($t['type_conteneur'] ?? '');
            $ligne = [
                'reference' => $this->texte(mb_strtoupper((string) ($t['reference'] ?? '')), 60),
                'type_conteneur' => $mode === 'MARITIME' && isset(DossierEnvoiRegles::TYPES_CONTENEUR[$conteneur]) ? $conteneur : null,
                'chauffeur' => $mode === 'ROUTIER' ? $this->texte($t['chauffeur'] ?? null, 120) : null,
                'date_depart' => $this->date($t['date_depart'] ?? null, 'Tranche : date de départ'),
                'date_arrivee' => $this->date($t['date_arrivee'] ?? null, "Tranche : date d'arrivée"),
                'nb_colis' => $this->nombre($t['nb_colis'] ?? null, 'Tranche : nombre de colis', true),
                'poids_kg' => $this->nombre($t['poids_kg'] ?? null, 'Tranche : poids'),
            ];

            if (array_filter($ligne, static fn (mixed $v): bool => $v !== null) === []) {
                continue;
            }

            $ligne['type'] = DossierEnvoiRegles::TRANCHE_DU_MODE[$mode] ?? 'VOL';
            $tranches[] = $ligne;
        }

        return $tranches;
    }

    /**
     * @param array<string, mixed> $s
     * @return array<int, array{type:string, quantite:int}>
     */
    private function lireEmballages(array $s): array
    {
        $parType = [];

        foreach ((array) ($s['emballages'] ?? []) as $e) {
            if (!is_array($e)) {
                continue;
            }

            $type = trim((string) ($e['type'] ?? ''));
            $quantite = $this->nombre($e['quantite'] ?? null, "Nombre d'emballages", true);

            if ($type === '' && $quantite === null) {
                continue;
            }
            if (!in_array($type, DossierEnvoiRegles::EMBALLAGES, true)) {
                $this->erreursLecture[] = "Choisissez le type de chaque ligne d'emballages.";
                continue;
            }
            if ($quantite === null || $quantite <= 0) {
                $this->erreursLecture[] = 'Emballages « ' . $type . ' » : le nombre doit être supérieur à zéro.';
                continue;
            }

            $parType[$type] = ($parType[$type] ?? 0) + (int) $quantite;
        }

        $emballages = [];
        foreach ($parType as $type => $quantite) {
            $emballages[] = ['type' => $type, 'quantite' => $quantite];
        }

        return $emballages;
    }

    /**
     * @param array<string, mixed> $s
     * @return array{0:array<string, array<string, mixed>>, 1:array<int, array<string, mixed>>}
     */
    private function lireFrais(array $s): array
    {
        $fixes = [];
        foreach (DossierEnvoiRegles::POSTES as $poste => $libelle) {
            $l = is_array($s['frais'][$poste] ?? null) ? $s['frais'][$poste] : [];
            $fixes[$poste] = [
                'prestataire_id' => $this->identifiant($l['prestataire_id'] ?? null),
                'prestataire_libre' => $this->texte($l['prestataire_libre'] ?? null, 150),
                'montant_prevu' => $this->nombre($l['montant_prevu'] ?? null, $libelle . ' : montant prévu'),
                'devise' => $this->devise($l['devise'] ?? null),
                'sans_frais' => !empty($l['sans_frais']),
                'commentaire_ecart' => $this->texte($l['commentaire_ecart'] ?? null, 1000),
            ];
        }

        $autres = [];
        foreach ((array) ($s['autres'] ?? []) as $l) {
            if (!is_array($l)) {
                continue;
            }

            $ligne = [
                'libelle' => $this->texte($l['libelle'] ?? null, 150),
                'prestataire_id' => $this->identifiant($l['prestataire_id'] ?? null),
                'prestataire_libre' => $this->texte($l['prestataire_libre'] ?? null, 150),
                'montant_prevu' => $this->nombre($l['montant_prevu'] ?? null, 'Autre frais : montant prévu'),
                'montant_facture' => $this->nombre($l['montant_facture'] ?? null, 'Autre frais : montant facturé'),
                'numero_facture' => $this->texte($l['numero_facture'] ?? null, 60),
                'devise' => $this->devise($l['devise'] ?? null),
                'commentaire_ecart' => $this->texte($l['commentaire_ecart'] ?? null, 1000),
            ];

            if ($ligne['libelle'] === null && $ligne['montant_prevu'] === null && $ligne['montant_facture'] === null) {
                continue;
            }

            $autres[] = $ligne;
        }

        return [$fixes, $autres];
    }

    private function texte(mixed $valeur, int $longueur): ?string
    {
        $texte = trim((string) ($valeur ?? ''));

        return $texte === '' ? null : mb_substr($texte, 0, $longueur);
    }

    private function identifiant(mixed $valeur): ?int
    {
        $id = (int) ($valeur ?? 0);

        return $id > 0 ? $id : null;
    }

    private function devise(mixed $valeur): string
    {
        $devise = strtoupper(trim((string) ($valeur ?? '')));

        return in_array($devise, DossierEnvoiRegles::DEVISES, true) ? $devise : 'XOF';
    }

    private function nombre(mixed $valeur, string $libelle, bool $entier = false): int|float|null
    {
        $texte = str_replace([' ', "\u{00A0}", "\u{202F}"], '', trim((string) ($valeur ?? '')));

        if ($texte === '') {
            return null;
        }

        $texte = str_replace(',', '.', $texte);

        if (!is_numeric($texte) || ($entier && preg_match('/^-?\d+$/', $texte) !== 1)) {
            $this->erreursLecture[] = $libelle . ' : saisissez ' . ($entier ? 'un nombre entier.' : 'un nombre.');

            return null;
        }

        return $entier ? (int) $texte : (float) $texte;
    }

    private function date(mixed $valeur, string $libelle): ?string
    {
        $texte = trim((string) ($valeur ?? ''));

        if ($texte === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $texte) !== 1
            || !checkdate((int) substr($texte, 5, 2), (int) substr($texte, 8, 2), (int) substr($texte, 0, 4))) {
            $this->erreursLecture[] = $libelle . ' : date invalide.';

            return null;
        }

        return $texte;
    }

    private function motif(mixed $valeur, string $messageSiVide): string
    {
        $motif = $this->texte($valeur, 1000);

        if ($motif === null) {
            throw new DossierEnvoiInvalide([$messageSiVide]);
        }

        return $motif;
    }

    // ------------------------------------------------------------------
    // Outils
    // ------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function exiger(int $id, callable $droit, string $refus): array
    {
        $dossier = $this->repo->trouver($id);

        if ($dossier === null || !$droit($dossier)) {
            throw new DossierEnvoiInvalide([$refus]);
        }

        return $dossier;
    }

    /** @param array<string, mixed> $valeurs */
    private function circuit(int $id, array $valeurs, DossierEnvoiAcces $acces, string $action, string $ancienStatut, ?string $motif): void
    {
        $this->pdo->beginTransaction();

        try {
            $this->repo->changerCircuit($id, $valeurs);
            $this->repo->journaliser(
                $id,
                $acces->userId(),
                $action,
                'Statut',
                DossierEnvoiRegles::STATUTS[$ancienStatut] ?? $ancienStatut,
                DossierEnvoiRegles::STATUTS[(string) $valeurs['statut']] ?? (string) $valeurs['statut'],
                $motif
            );
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /** @param array<string, mixed> $dossier */
    private function colisPointes(array $dossier): ?int
    {
        if (($dossier['expedition_id'] ?? null) === null) {
            return null;
        }

        $expedition = (int) $dossier['expedition_id'];

        return $this->repo->colisPointesDes([$expedition])[$expedition] ?? 0;
    }

    /**
     * Ajoute à chaque dossier ses lignes et ses chiffres calculés, en cinq
     * requêtes pour toute la liste.
     *
     * @param array<int, array<string, mixed>> $dossiers
     * @return array<int, array<string, mixed>>
     */
    private function enrichir(array $dossiers): array
    {
        if ($dossiers === []) {
            return [];
        }

        $ids = array_map(static fn (array $d): int => (int) $d['id'], $dossiers);
        $expeditions = array_values(array_unique(array_filter(array_map(
            static fn (array $d): int => (int) ($d['expedition_id'] ?? 0),
            $dossiers
        ))));

        $frais = $this->repo->fraisDes($ids);
        $emballages = $this->repo->emballagesDes($ids);
        $tranches = $this->repo->tranchesDes($ids);
        $types = $this->repo->typesDocumentsDes($ids);
        $pointes = $this->repo->colisPointesDes($expeditions);

        $enrichis = [];
        foreach ($dossiers as $dossier) {
            $id = (int) $dossier['id'];
            $dossier['frais'] = $frais[$id] ?? [];
            $dossier['emballages'] = $emballages[$id] ?? [];
            $dossier['tranches'] = $tranches[$id] ?? [];
            $colisPointes = ($dossier['expedition_id'] ?? null) !== null ? ($pointes[(int) $dossier['expedition_id']] ?? 0) : null;
            $dossier['synthese'] = DossierEnvoiRegles::synthese($dossier, $dossier['frais'], $dossier['emballages'], $types[$id] ?? [], $colisPointes);
            $enrichis[] = $dossier;
        }

        return $enrichis;
    }

    /**
     * @param array<int, array<string, mixed>> $dossiers
     * @return array<string, mixed>
     */
    private function totaux(array $dossiers): array
    {
        $totaux = [
            'dossiers' => count($dossiers),
            'colis' => 0,
            'poids' => 0.0,
            'cout_prevu' => 0.0,
            'cout_retenu' => 0.0,
            'ecart_facture' => 0.0,
            'postes' => array_fill_keys(array_keys(DossierEnvoiRegles::POSTES), ['prevu' => 0.0, 'facture' => 0.0]),
        ];

        foreach ($dossiers as $d) {
            $s = $d['synthese'];
            $totaux['colis'] += (int) ($d['nb_colis_declare'] ?? 0);
            $totaux['poids'] += (float) ($d['poids_brut_kg'] ?? 0);
            $totaux['cout_prevu'] += (float) $s['cout_prevu_xof'];
            $totaux['cout_retenu'] += (float) $s['cout_retenu_xof'];
            $totaux['ecart_facture'] += (float) $s['ecart_facture_xof'];

            foreach ($s['frais_par_poste'] as $poste => $ligne) {
                if (!isset($totaux['postes'][$poste])) {
                    continue;
                }
                $devise = (string) ($ligne['devise'] ?? 'XOF');
                $totaux['postes'][$poste]['prevu'] += (float) DossierEnvoiRegles::enXof(
                    isset($ligne['montant_prevu']) ? (float) $ligne['montant_prevu'] : null, $devise, (float) $s['taux']
                );
                $totaux['postes'][$poste]['facture'] += (float) DossierEnvoiRegles::enXof(
                    isset($ligne['montant_facture']) ? (float) $ligne['montant_facture'] : null, (string) ($ligne['devise_facture'] ?? $devise), (float) $s['taux']
                );
            }
        }

        return $totaux;
    }

    /**
     * Filtres en clair, pour l'en-tête des exports.
     *
     * @param array<string, mixed> $filtres
     * @param array<int, array<string, mixed>> $agences
     * @param array<int, array<string, mixed>> $prestataires
     * @param array<int, array{id:int, full_name:string}> $responsables
     * @return array<int, string>
     */
    private function libellesFiltres(array $filtres, array $agences, array $prestataires, array $responsables): array
    {
        $nom = static function (array $lignes, ?int $id, string $cle): ?string {
            foreach ($lignes as $ligne) {
                if ((int) $ligne['id'] === $id) {
                    return (string) $ligne[$cle];
                }
            }

            return null;
        };

        $libelles = [
            'Période du ' . date('d/m/Y', (int) strtotime((string) $filtres['du'])) . ' au ' . date('d/m/Y', (int) strtotime((string) $filtres['au'])),
            'Responsable : ' . ($filtres['responsable'] !== null ? ($nom($responsables, $filtres['responsable'], 'full_name') ?? '#' . $filtres['responsable']) : 'tous'),
            'Mode : ' . ($filtres['mode'] !== '' ? DossierEnvoiRegles::MODES[$filtres['mode']] : 'tous'),
            'Statut : ' . ($filtres['statut'] !== '' ? DossierEnvoiRegles::STATUTS[$filtres['statut']] : 'tous sauf annulés'),
        ];

        if ($filtres['agence'] !== null) {
            $libelles[] = 'Agence de départ : ' . ($nom($agences, $filtres['agence'], 'name') ?? '—');
        }
        if ($filtres['transporteur'] !== null) {
            $libelles[] = 'Transporteur : ' . ($nom($prestataires, $filtres['transporteur'], 'name') ?? '—');
        }
        if ($filtres['transitaire'] !== null) {
            $libelles[] = 'Transitaire : ' . ($nom($prestataires, $filtres['transitaire'], 'name') ?? '—');
        }
        if ($filtres['pieces']) {
            $libelles[] = 'Pièces manquantes uniquement';
        }
        if ($filtres['ecart_colis']) {
            $libelles[] = 'Écarts de colis uniquement';
        }
        if ($filtres['ecart_facture']) {
            $libelles[] = 'Écarts de facture uniquement';
        }
        if ($filtres['q'] !== '') {
            $libelles[] = 'Recherche : ' . $filtres['q'];
        }

        return $libelles;
    }

    /**
     * @param array<string, mixed> $avant
     * @param array<string, mixed> $apres
     * @return array<int, array{0:string, 1:string, 2:string}>
     */
    private function differences(array $avant, array $apres): array
    {
        $changements = [];

        foreach (self::CHAMPS_JOURNAL as $colonne => [$libelle, $affichage]) {
            if ($this->comparable($avant[$colonne] ?? null) === $this->comparable($apres[$colonne] ?? null)) {
                continue;
            }

            $changements[] = [$libelle, $this->affichage($colonne, $avant[$affichage] ?? null), $this->affichage($colonne, $apres[$affichage] ?? null)];
        }

        return $changements;
    }

    private function comparable(mixed $valeur): string
    {
        if ($valeur === null || $valeur === '') {
            return '';
        }

        if (is_numeric($valeur)) {
            return rtrim(rtrim(number_format((float) $valeur, 6, '.', ''), '0'), '.');
        }

        return (string) $valeur;
    }

    private function affichage(string $colonne, mixed $valeur): string
    {
        $texte = $this->comparable($valeur);

        return match (true) {
            $texte === '' => '—',
            $colonne === 'statut' => DossierEnvoiRegles::STATUTS[$texte] ?? $texte,
            $colonne === 'mode_transport' => DossierEnvoiRegles::MODES[$texte] ?? $texte,
            default => $texte,
        };
    }

    /** @param array<int, array<string, mixed>> $lignes */
    private function resumeTranches(array $lignes): string
    {
        return implode(' | ', array_map(
            fn (array $t): string => trim(($t['reference'] ?? '') . ' ' . ($t['date_depart'] ?? '') . ' ' . $this->comparable($t['nb_colis'] ?? null)),
            $lignes
        ));
    }

    /** @param array<int, array<string, mixed>> $lignes */
    private function resumeEmballages(array $lignes): string
    {
        $morceaux = array_map(static fn (array $e): string => (int) $e['quantite'] . ' × ' . $e['type'], $lignes);
        sort($morceaux);

        return implode(', ', $morceaux);
    }

    /** @param array<int, array<string, mixed>> $lignes */
    private function resumeFrais(array $lignes): string
    {
        $morceaux = [];

        foreach ($lignes as $f) {
            $poste = (string) $f['poste'];
            $morceau = ($poste === DossierEnvoiRegles::POSTE_AUTRE ? (string) ($f['libelle'] ?? 'Autre') : (DossierEnvoiRegles::POSTES[$poste] ?? $poste))
                . ' : ' . ($this->comparable($f['montant_prevu'] ?? null) ?: '—') . ' ' . ($f['devise'] ?? 'XOF')
                . (!empty($f['sans_frais']) ? ' (sans frais)' : '');

            if ($poste === DossierEnvoiRegles::POSTE_AUTRE && ($f['montant_facture'] ?? null) !== null) {
                $morceau .= ', facturé ' . $this->comparable($f['montant_facture']);
            }

            $prestataire = (string) ($f['prestataire_id'] ?? '') . (string) ($f['prestataire_libre'] ?? '');
            $commentaire = trim((string) ($f['commentaire_ecart'] ?? ''));
            $morceaux[] = $morceau . ($prestataire !== '' ? ' [' . $prestataire . ']' : '')
                . ($commentaire !== '' ? ' « ' . $commentaire . ' »' : '');
        }

        return implode(' | ', $morceaux);
    }

    private function aujourdhui(): string
    {
        return substr($this->repo->maintenant(), 0, 10);
    }
}
