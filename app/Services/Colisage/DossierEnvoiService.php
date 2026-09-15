<?php

declare(strict_types=1);

namespace App\Services\Colisage;

use App\Models\Database;
use App\Repositories\Colisage\DossierEnvoiRepository;
use App\Repositories\Colisage\PointageColisRepository;
use App\Security\DossierEnvoiAcces;
use App\Services\Colisage\DossierEnvoiRegles as Regles;
use DateTimeImmutable;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Départs préparés par l'agent export, et leur contrôle par le Directeur général.
 *
 * Préparer un départ ne coche plus de colis : l'agent saisit le document de la
 * compagnie, et tous les colis enregistrés pour la destination et pas encore
 * partis partent avec lui. La saisie qu'ils représentent est gardée dans le
 * dossier, pour que le Directeur général la compare au document.
 *
 * Chaque changement est journalisé : on doit toujours pouvoir dire qui a
 * modifié un poids ou un montant, et quand.
 */
final class DossierEnvoiService
{
    /** Champs suivis au journal : libellé, et colonne lue pour l'afficher. */
    private const CHAMPS_JOURNAL = [
        'mode_transport' => ['Mode de transport', 'mode_transport'],
        'date_depart_effective' => ['1. Date de départ', 'date_depart_effective'],
        'transporteur_id' => ['2. Compagnie', 'transporteur'],
        'numero_document' => ['3. Document de la compagnie', 'numero_document'],
        'nb_colis_declare' => ['4. Nombre de colis', 'nb_colis_declare'],
        'poids_brut_kg' => ['5. Poids total (kg)', 'poids_brut_kg'],
    ];

    /** @var array<int, string> */
    private array $erreursLecture = [];

    public function __construct(
        private PDO $pdo,
        private DossierEnvoiRepository $repo,
        private PointageColisRepository $pointage,
        private DossierEnvoiStockage $stockage,
    ) {
    }

    public static function creer(): self
    {
        $pdo = Database::getConnection();

        return new self($pdo, new DossierEnvoiRepository($pdo), new PointageColisRepository($pdo), DossierEnvoiStockage::creer());
    }

    // ------------------------------------------------------------------
    // Préparer un départ
    // ------------------------------------------------------------------

    /** @return array{agences:array<int, array<string, mixed>>, prestataires:array<int, array<string, mixed>>} */
    public function referentiels(): array
    {
        return ['agences' => $this->repo->agences(), 'prestataires' => $this->repo->prestataires()];
    }

    /**
     * Écran « Préparer un départ » : les listes du formulaire et les départs
     * encore ouverts.
     *
     * @return array<string, mixed>
     */
    public function preparation(DossierEnvoiAcces $acces): array
    {
        return $this->referentiels() + [
            'en_cours' => $this->enrichir($this->repo->lister([
                'responsable_id' => $acces->responsableImpose(),
                'statuts' => ['EN_COURS', 'A_CORRIGER', 'SOUMIS'],
            ], 200), $acces),
        ];
    }

    /** @return array{dossier:array<string, mixed>, frais:array<int, mixed>, emballages:array<int, mixed>} */
    public function nouveauDepart(): array
    {
        return ['dossier' => ['mode_transport' => 'AERIEN', 'statut' => 'EN_COURS'], 'frais' => [], 'emballages' => []];
    }

    /**
     * Enregistre un départ : le dossier, le départ du pointage, et les colis
     * enregistrés pour la destination qui partent avec lui.
     *
     * @param array<string, mixed> $saisie
     * @return array{id:int, numero:string}
     * @throws DossierEnvoiInvalide avec la liste complète des corrections
     */
    public function creerDepart(array $saisie, DossierEnvoiAcces $acces): array
    {
        $this->erreursLecture = [];

        if (!$acces->peutCreer()) {
            throw new DossierEnvoiInvalide(["Seul l'agent export prépare un départ."]);
        }

        $dossier = $this->lireDossier($saisie, true);
        $fixes = $this->lireFrais($saisie);
        $emballages = $this->lireEmballages($saisie);

        [$dossier, $erreurs] = $this->controler($dossier, $fixes, 0);

        foreach (['agence_depart_id' => "L'agence de départ", 'agence_arrivee_id' => 'La destination'] as $champ => $libelle) {
            if ($dossier[$champ] !== null && $this->repo->agence((int) $dossier[$champ]) === null) {
                $erreurs[] = $libelle . " choisie n'existe plus.";
            }
        }

        if ($erreurs !== []) {
            throw new DossierEnvoiInvalide(array_values(array_unique($erreurs)));
        }

        $userId = $acces->userId();
        $agenceDepart = (int) $dossier['agence_depart_id'];
        $agenceArrivee = (int) $dossier['agence_arrivee_id'];

        $this->pdo->beginTransaction();

        try {
            $colis = $this->pointage->idsAExpedierEntre($agenceDepart, $agenceArrivee);
            $saisis = $this->repo->totauxColis($colis);

            $reference = 'DEP-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));
            $expeditionId = $this->pointage->creerDepart($reference, Regles::TRANSPORT_DU_MODE[$dossier['mode_transport']], $agenceDepart, $agenceArrivee, $userId, false);
            $this->pointage->rattacherAuDepart($colis, $expeditionId);
            foreach ($colis as $colisId) {
                $this->pointage->journaliser($colisId, $expeditionId, $agenceDepart, 'DEPART', 'ENVOI', $userId);
            }

            $prefixe = Regles::prefixeNumero(
                Regles::codeAgence($this->repo->agence($agenceDepart) ?? []),
                new DateTimeImmutable($this->repo->maintenant())
            );

            $dossier += [
                'numero' => Regles::numeroDossier($prefixe, $this->repo->prochainRang($prefixe)),
                'statut' => 'EN_COURS',
                'responsable_id' => $userId,
                'expedition_id' => $expeditionId,
                'colis_erp' => $saisis['colis'],
                'poids_erp_kg' => $saisis['poids'],
                'taux_eur_xof' => $this->repo->tauxEurXof() ?? Regles::TAUX_EUR_XOF_DEFAUT,
                'created_by' => $userId,
            ];

            $id = $this->repo->inserer($dossier);
            $this->repo->remplacerEmballages($id, $emballages);
            foreach ($fixes as $poste => $ligne) {
                $this->repo->enregistrerPoste($id, $poste, $ligne);
            }
            $this->repo->journaliser($id, $userId, 'CREATION', null, null, (string) $dossier['numero'], null);

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return ['id' => $id, 'numero' => (string) $dossier['numero']];
    }

    /**
     * Complète ou corrige un départ tant qu'il n'est pas soumis.
     *
     * @param array<string, mixed> $saisie
     * @throws DossierEnvoiInvalide
     */
    public function mettreAJour(int $id, array $saisie, DossierEnvoiAcces $acces): string
    {
        $this->erreursLecture = [];
        $existant = $this->repo->trouver($id);

        if ($existant === null || !$acces->peutModifier($existant)) {
            throw new DossierEnvoiInvalide(["Ce départ n'est plus modifiable : il a été soumis au Directeur général, ou n'est pas le vôtre."]);
        }

        $dossier = $this->lireDossier($saisie, false) + [
            'agence_depart_id' => (int) $existant['agence_depart_id'],
            'agence_arrivee_id' => (int) $existant['agence_arrivee_id'],
        ];
        $fixes = $this->lireFrais($saisie);
        $emballages = $this->lireEmballages($saisie);

        [$dossier, $erreurs] = $this->controler($dossier, $fixes, $id);

        if ($erreurs !== []) {
            throw new DossierEnvoiInvalide(array_values(array_unique($erreurs)));
        }

        $userId = $acces->userId();
        $this->pdo->beginTransaction();

        try {
            $avant = [$this->resumeFrais($this->repo->frais($id)), $this->resumeEmballages($this->repo->emballages($id))];

            $this->repo->mettreAJour($id, $dossier);
            $this->repo->remplacerEmballages($id, $emballages);
            foreach ($fixes as $poste => $ligne) {
                $this->repo->enregistrerPoste($id, $poste, $ligne);
            }

            $apres = $this->repo->trouver($id) ?? [];
            foreach (self::CHAMPS_JOURNAL as $colonne => [$libelle, $affichage]) {
                if ($this->comparable($existant[$colonne] ?? null) !== $this->comparable($apres[$colonne] ?? null)) {
                    $this->repo->journaliser($id, $userId, 'MODIFICATION', $libelle, $this->affichage($colonne, $existant[$affichage] ?? null), $this->affichage($colonne, $apres[$affichage] ?? null), null);
                }
            }

            $maintenant = [$this->resumeFrais($this->repo->frais($id)), $this->resumeEmballages($this->repo->emballages($id))];
            if ($avant[0] !== $maintenant[0]) {
                $this->repo->journaliser($id, $userId, 'MODIFICATION', '6 à 9. Frais', $avant[0] ?: '—', $maintenant[0] ?: '—', null);
            }
            if ($avant[1] !== $maintenant[1]) {
                $this->repo->journaliser($id, $userId, 'MODIFICATION', '10. Emballages', $avant[1] ?: '—', $maintenant[1] ?: '—', null);
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return 'Départ ' . $existant['numero'] . ' enregistré.';
    }

    /**
     * Ce que l'agent vient de saisir, pour réafficher le formulaire après un
     * refus sans rien perdre.
     *
     * @param array<string, mixed> $saisie
     * @return array{dossier:array<string, mixed>, frais:array<int, mixed>, emballages:array<int, mixed>}
     */
    public function brouillonDepuisSaisie(array $saisie, ?int $id): array
    {
        $this->erreursLecture = [];

        $factures = [];
        foreach ($id !== null ? $this->repo->frais($id) : [] as $ligne) {
            $factures[(string) $ligne['poste']] ??= $ligne;
        }

        $frais = [];
        foreach ($this->lireFrais($saisie) as $poste => $ligne) {
            $facture = $factures[$poste] ?? [];
            $frais[] = $ligne + [
                'poste' => $poste,
                'montant_facture' => $facture['montant_facture'] ?? null,
                'devise_facture' => $facture['devise_facture'] ?? null,
                'numero_facture' => $facture['numero_facture'] ?? null,
            ];
        }

        $emballages = [];
        foreach ((array) ($saisie['emballages'] ?? []) as $ligne) {
            if (is_array($ligne) && trim((string) ($ligne['type'] ?? '')) !== '') {
                $emballages[] = ['type' => (string) $ligne['type'], 'quantite' => (string) ($ligne['quantite'] ?? '')];
            }
        }

        $dossier = $this->lireDossier($saisie, $id === null);
        $dossier['numero_document'] = trim((string) ($saisie['numero_document'] ?? '')) ?: null;

        return ['dossier' => $dossier, 'frais' => $frais, 'emballages' => $emballages];
    }

    // ------------------------------------------------------------------
    // Fiche
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
        $voitSaisie = $acces->voitSaisie();
        $synthese = Regles::synthese($dossier, $frais, $emballages, $types);

        if (!$voitSaisie) {
            unset($dossier['colis_erp'], $dossier['poids_erp_kg']);
            $synthese['ecart_saisie'] = null;
        }

        $modifiable = $acces->peutModifier($dossier);

        return [
            'dossier' => $dossier,
            'frais' => $frais,
            'emballages' => $emballages,
            'documents' => $documents,
            'synthese' => $synthese,
            'manques' => Regles::controlerSoumission($dossier, $frais, $emballages, $types),
            'journal' => $this->repo->journal($id),
            'agents_saisie' => $voitSaisie && !empty($dossier['expedition_id']) ? $this->repo->agentsDeSaisie((int) $dossier['expedition_id']) : [],
            'erreurs' => [],
            'droits' => [
                'modifier' => $modifiable,
                'soumettre' => $acces->peutSoumettre($dossier),
                'valider' => $acces->peutValider($dossier),
                'renvoyer' => $acces->peutRenvoyer($dossier),
                'rouvrir' => $acces->peutRouvrir($dossier),
                'voit_saisie' => $voitSaisie,
            ],
        ] + ($modifiable ? $this->referentiels() : ['agences' => [], 'prestataires' => []]);
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
            throw new DossierEnvoiInvalide(["Les pièces de ce départ ne peuvent plus être modifiées : il a été soumis au Directeur général, ou n'est pas le vôtre."]);
        }

        $type = strtoupper(trim((string) ($saisie['type_document'] ?? '')));
        if (!isset(Regles::PIECES[$type])) {
            throw new DossierEnvoiInvalide(['Choisissez le type de document.']);
        }

        $poste = array_search($type, Regles::PIECE_DU_POSTE, true);
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

        $base = Regles::nomFichier((string) $dossier['numero'], $type, $this->repo->compterDocuments($id, $type) + 1);

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
            $this->repo->journaliser($id, $userId, 'DOCUMENT_AJOUT', Regles::PIECES[$type], null, $nom, null);

            if ($poste !== null) {
                $this->repo->enregistrerFacture($id, $poste, (float) $montant, $devise, $numeroFacture, $documentId);
                $this->repo->journaliser(
                    $id,
                    $userId,
                    'FACTURE',
                    Regles::COLONNE_DU_POSTE[$poste] . '. ' . Regles::POSTES[$poste],
                    null,
                    Regles::nombre((float) $montant, 2) . ' ' . $devise . ($numeroFacture !== null ? ' (facture ' . $numeroFacture . ')' : ''),
                    null
                );
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            $this->stockage->effacer($range['stored_path']);
            throw $e;
        }

        return Regles::PIECES[$type] . ' jointe : ' . $nom . '.';
    }

    public function retirerDocument(int $id, int $documentId, DossierEnvoiAcces $acces): string
    {
        $dossier = $this->repo->trouver($id);

        if ($dossier === null || !$acces->peutModifier($dossier)) {
            throw new DossierEnvoiInvalide(["Les pièces de ce départ ne peuvent plus être modifiées : il a été soumis au Directeur général, ou n'est pas le vôtre."]);
        }

        $document = $this->repo->document($id, $documentId);
        if ($document === null) {
            throw new DossierEnvoiInvalide(['Cette pièce a déjà été retirée.']);
        }

        $this->pdo->beginTransaction();

        try {
            $this->repo->retirerDocument($documentId, $acces->userId());
            $this->repo->effacerFactureDuDocument($documentId);
            $this->repo->journaliser($id, $acces->userId(), 'DOCUMENT_RETRAIT', Regles::PIECES[(string) $document['type_document']] ?? (string) $document['type_document'], (string) $document['nom_fichier'], null, null);
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

        return $chemin === null ? null : ['chemin' => $chemin, 'nom' => (string) $document['nom_fichier'], 'mime' => (string) $document['mime_type']];
    }

    // ------------------------------------------------------------------
    // Circuit : soumettre, valider, renvoyer, rouvrir
    // ------------------------------------------------------------------

    public function soumettre(int $id, DossierEnvoiAcces $acces): string
    {
        $dossier = $this->exiger($id, $acces->peutSoumettre(...), "Ce départ ne peut plus être soumis : il l'a déjà été, ou n'est pas le vôtre.");

        $types = array_values(array_unique(array_map('strval', array_column($this->repo->documents($id), 'type_document'))));
        $manques = Regles::controlerSoumission($dossier, $this->repo->frais($id), $this->repo->emballages($id), $types);

        if ($manques !== []) {
            throw new DossierEnvoiInvalide($manques);
        }

        $this->circuit($id, ['statut' => 'SOUMIS', 'soumis_le' => $this->repo->maintenant()], $acces, 'SOUMISSION', (string) $dossier['statut'], null);

        return 'Départ ' . $dossier['numero'] . ' soumis au Directeur général.';
    }

    public function valider(int $id, mixed $commentaire, DossierEnvoiAcces $acces): string
    {
        $dossier = $this->exiger($id, $acces->peutValider(...), 'Seul un départ soumis peut être validé, et seulement par le Directeur général.');
        $commentaire = $this->texte($commentaire, 2000);
        $ecart = Regles::ecartSaisie($dossier);

        if ($ecart !== null && $ecart['depasse'] && $commentaire === null) {
            throw new DossierEnvoiInvalide([
                "L'écart entre le document de la compagnie et la saisie des colis dépasse " . Regles::nombre(Regles::SEUIL_ECART_SAISIE_POURCENT)
                . ' % : indiquez ce que vous avez vérifié avant de valider.',
            ]);
        }

        $this->circuit($id, [
            'statut' => 'VALIDE',
            'valide_par_id' => $acces->userId(),
            'valide_le' => $this->repo->maintenant(),
            'commentaire_dg' => $commentaire,
        ], $acces, 'VALIDATION', (string) $dossier['statut'], $commentaire);

        return 'Départ ' . $dossier['numero'] . ' validé.';
    }

    public function renvoyer(int $id, mixed $motif, DossierEnvoiAcces $acces): string
    {
        $dossier = $this->exiger($id, $acces->peutRenvoyer(...), 'Seul un départ soumis peut être renvoyé, et seulement par le Directeur général.');
        $motif = $this->motif($motif, "Indiquez le motif du renvoi : l'agent export le verra sur son départ.");

        $this->circuit($id, ['statut' => 'A_CORRIGER', 'motif_renvoi' => $motif], $acces, 'RENVOI', (string) $dossier['statut'], $motif);

        return 'Départ ' . $dossier['numero'] . " renvoyé à l'agent export.";
    }

    public function rouvrir(int $id, mixed $motif, DossierEnvoiAcces $acces): string
    {
        $dossier = $this->exiger($id, $acces->peutRouvrir(...), 'Seul un départ validé peut être rouvert, et seulement par le Directeur général.');
        $motif = $this->motif($motif, 'Indiquez le motif de la réouverture : il reste inscrit au journal.');

        $this->circuit($id, [
            'statut' => 'A_CORRIGER',
            'valide_par_id' => null,
            'valide_le' => null,
            'motif_renvoi' => $motif,
        ], $acces, 'REOUVERTURE', (string) $dossier['statut'], $motif);

        return 'Départ ' . $dossier['numero'] . " rouvert : l'agent export peut le corriger.";
    }

    // ------------------------------------------------------------------
    // Listes
    // ------------------------------------------------------------------

    /**
     * Départs soumis, du plus ancien au plus récent.
     *
     * @return array<int, array<string, mixed>>
     */
    public function aValider(DossierEnvoiAcces $acces): array
    {
        if (!$acces->estValideur()) {
            return [];
        }

        $dossiers = $this->enrichir($this->repo->lister(['statuts' => ['SOUMIS']], 300), $acces);
        usort($dossiers, static fn (array $a, array $b): int => strcmp((string) $a['soumis_le'], (string) $b['soumis_le']));

        return $dossiers;
    }

    /**
     * Historique filtré : ce qui s'affiche est exactement ce qui s'exporte.
     *
     * @param array<string, mixed> $requete
     * @return array<string, mixed>
     */
    public function historique(array $requete, DossierEnvoiAcces $acces): array
    {
        $periode = Regles::resoudrePeriode(
            (string) ($requete['periode'] ?? 'ce_mois'),
            (string) ($requete['du'] ?? ''),
            (string) ($requete['au'] ?? ''),
            new DateTimeImmutable($this->aujourdhui())
        );

        $responsable = $acces->responsableImpose() ?? ((int) ($requete['agent'] ?? 0) ?: null);
        $recherche = trim((string) ($requete['q'] ?? ''));

        $dossiers = $this->enrichir($this->repo->lister([
            'responsable_id' => $responsable,
            'du' => $periode['du'],
            'au' => $periode['au'],
            'q' => $recherche,
        ], 2000), $acces);

        $agents = $this->repo->utilisateursDuRole(DossierEnvoiAcces::ROLE_AGENT);
        $nomAgent = null;
        foreach ($agents as $agent) {
            if ($agent['id'] === $responsable) {
                $nomAgent = $agent['full_name'];
            }
        }

        $filtres = $periode + ['agent' => $responsable, 'q' => $recherche];

        return [
            'filtres' => $filtres,
            'dossiers' => $dossiers,
            'totaux' => $this->totaux($dossiers),
            'agents' => $acces->voitTout() ? $agents : [],
            'voit_tout' => $acces->voitTout(),
            'voit_saisie' => $acces->voitSaisie(),
            'libelles_filtres' => array_values(array_filter([
                'Période du ' . date('d/m/Y', (int) strtotime($periode['du'])) . ' au ' . date('d/m/Y', (int) strtotime($periode['au'])),
                'Agent : ' . ($responsable === null ? 'tous' : ($nomAgent ?? 'agent n° ' . $responsable)),
                $recherche !== '' ? 'Recherche : ' . $recherche : null,
            ])),
            'edite_le' => $this->repo->maintenant(),
        ];
    }

    /**
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

        if (!isset(Regles::TYPES_PRESTATAIRE[$type])) {
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
            $erreurs[] = "Ce prestataire n'existe plus.";
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
    private function lireDossier(array $s, bool $avecTrajet): array
    {
        $mode = strtoupper(trim((string) ($s['mode_transport'] ?? '')));

        $dossier = [
            'mode_transport' => isset(Regles::MODES[$mode]) ? $mode : '',
            'date_depart_effective' => $this->date($s['date_depart_effective'] ?? null, 'Colonne 1 — Date de départ'),
            'transporteur_id' => $this->identifiant($s['transporteur_id'] ?? null),
            'numero_document' => $this->texte($s['numero_document'] ?? null, 60),
            'nb_colis_declare' => $this->nombre($s['nb_colis_declare'] ?? null, 'Colonne 4 — Nombre de colis', true),
            'poids_brut_kg' => $this->nombre($s['poids_brut_kg'] ?? null, 'Colonne 5 — Poids total'),
        ];

        if ($avecTrajet) {
            $dossier['agence_depart_id'] = $this->identifiant($s['agence_depart_id'] ?? null);
            $dossier['agence_arrivee_id'] = $this->identifiant($s['agence_arrivee_id'] ?? null);
        }

        return $dossier;
    }

    /**
     * Colonnes 6 à 9. Le prestataire se tape librement : s'il correspond à un
     * prestataire connu, le dossier s'y rattache.
     *
     * @param array<string, mixed> $s
     * @return array<string, array<string, mixed>>
     */
    private function lireFrais(array $s): array
    {
        $fixes = [];

        foreach (Regles::POSTES as $poste => $libelle) {
            $ligne = is_array($s['frais'][$poste] ?? null) ? $s['frais'][$poste] : [];
            $nom = $this->texte($ligne['prestataire'] ?? null, 150);
            $prestataireId = $nom !== null ? $this->repo->prestataireNomme($nom, 0) : null;

            $fixes[$poste] = [
                'prestataire_id' => $prestataireId,
                'prestataire_libre' => $prestataireId === null ? $nom : null,
                'prestataire' => $prestataireId !== null ? $nom : null,
                'montant_prevu' => $this->nombre($ligne['montant_prevu'] ?? null, 'Colonne ' . Regles::COLONNE_DU_POSTE[$poste] . ' — ' . $libelle),
                'devise' => $this->devise($ligne['devise'] ?? null),
            ];
        }

        return $fixes;
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
            $quantite = $this->nombre($e['quantite'] ?? null, 'Colonne 10 — Emballages', true);

            if ($type === '' && $quantite === null) {
                continue;
            }
            if (!in_array($type, Regles::EMBALLAGES, true)) {
                $this->erreursLecture[] = "Colonne 10 — Emballages : choisissez le type de chaque ligne.";
                continue;
            }
            if ($quantite === null || $quantite <= 0) {
                $this->erreursLecture[] = 'Colonne 10 — Emballages : le nombre de « ' . $type . ' » doit être supérieur à zéro.';
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

        return in_array($devise, Regles::DEVISES, true) ? $devise : 'XOF';
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

    /**
     * @param array<string, mixed> $dossier
     * @param array<string, array<string, mixed>> $fixes
     * @return array{0:array<string, mixed>, 1:array<int, string>}
     */
    private function controler(array $dossier, array $fixes, int $exclure): array
    {
        $compagnie = $dossier['transporteur_id'] !== null ? $this->repo->prestataire((int) $dossier['transporteur_id']) : null;
        $controle = Regles::controlerDossier($dossier, $compagnie, $this->aujourdhui());
        $dossier = $controle['dossier'];
        $erreurs = array_merge($this->erreursLecture, $controle['erreurs'], Regles::controlerFrais($fixes));

        if ($dossier['numero_document'] !== null) {
            $doublon = $this->repo->documentDejaUtilise((string) $dossier['numero_document'], (string) $dossier['type_document'], $dossier['transporteur_id'], $exclure);
            if ($doublon !== null) {
                $erreurs[] = 'Colonne 3 — ' . Regles::DOCUMENT_DU_MODE[$dossier['mode_transport']] . ' : le numéro '
                    . $dossier['numero_document'] . ' est déjà utilisé par le départ ' . $doublon . '.';
            }
        }

        return [$dossier, $erreurs];
    }

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
                Regles::STATUTS[$ancienStatut] ?? $ancienStatut,
                Regles::STATUTS[(string) $valeurs['statut']] ?? (string) $valeurs['statut'],
                $motif
            );
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Ajoute à chaque départ ses frais, emballages et chiffres calculés, en
     * trois requêtes pour toute la liste. Les chiffres de la saisie sont
     * retirés pour qui ne doit pas les voir.
     *
     * @param array<int, array<string, mixed>> $dossiers
     * @return array<int, array<string, mixed>>
     */
    private function enrichir(array $dossiers, DossierEnvoiAcces $acces): array
    {
        if ($dossiers === []) {
            return [];
        }

        $ids = array_map(static fn (array $d): int => (int) $d['id'], $dossiers);
        $frais = $this->repo->fraisDes($ids);
        $emballages = $this->repo->emballagesDes($ids);
        $types = $this->repo->typesDocumentsDes($ids);
        $voitSaisie = $acces->voitSaisie();

        $enrichis = [];
        foreach ($dossiers as $dossier) {
            $id = (int) $dossier['id'];
            $dossier['frais'] = $frais[$id] ?? [];
            $dossier['emballages'] = $emballages[$id] ?? [];
            $dossier['synthese'] = Regles::synthese($dossier, $dossier['frais'], $dossier['emballages'], $types[$id] ?? []);

            if (!$voitSaisie) {
                unset($dossier['colis_erp'], $dossier['poids_erp_kg']);
                $dossier['synthese']['ecart_saisie'] = null;
            }

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
            'colis_erp' => 0,
            'poids_erp' => 0.0,
            'cout_prevu' => 0.0,
            'cout_facture' => 0.0,
            'ecart_facture' => 0.0,
            'postes' => array_fill_keys(array_keys(Regles::POSTES), 0.0),
        ];

        foreach ($dossiers as $d) {
            $s = $d['synthese'];
            $totaux['colis'] += (int) ($d['nb_colis_declare'] ?? 0);
            $totaux['poids'] += (float) ($d['poids_brut_kg'] ?? 0);
            $totaux['colis_erp'] += (int) ($d['colis_erp'] ?? 0);
            $totaux['poids_erp'] += (float) ($d['poids_erp_kg'] ?? 0);
            $totaux['cout_prevu'] += (float) $s['cout_prevu_xof'];
            $totaux['cout_facture'] += (float) $s['cout_facture_xof'];
            $totaux['ecart_facture'] += (float) $s['ecart_facture_xof'];

            foreach ($s['frais_par_poste'] as $poste => $ligne) {
                $totaux['postes'][$poste] += (float) Regles::enXof(
                    isset($ligne['montant_prevu']) ? (float) $ligne['montant_prevu'] : null,
                    (string) ($ligne['devise'] ?? 'XOF'),
                    (float) $s['taux']
                );
            }
        }

        return $totaux;
    }

    private function comparable(mixed $valeur): string
    {
        if ($valeur === null || $valeur === '') {
            return '';
        }

        return is_numeric($valeur) ? rtrim(rtrim(number_format((float) $valeur, 6, '.', ''), '0'), '.') : (string) $valeur;
    }

    private function affichage(string $colonne, mixed $valeur): string
    {
        $texte = $this->comparable($valeur);

        return match (true) {
            $texte === '' => '—',
            $colonne === 'mode_transport' => Regles::MODES[$texte] ?? $texte,
            default => $texte,
        };
    }

    /** @param array<int, array<string, mixed>> $lignes */
    private function resumeFrais(array $lignes): string
    {
        $morceaux = [];

        foreach (Regles::fraisParPoste($lignes) as $poste => $f) {
            $prestataire = trim((string) ($f['prestataire'] ?? $f['prestataire_libre'] ?? ''));
            $morceaux[] = Regles::COLONNE_DU_POSTE[$poste] . '. ' . ($prestataire !== '' ? $prestataire . ' ' : '')
                . ($this->comparable($f['montant_prevu'] ?? null) ?: '—') . ' ' . ($f['devise'] ?? 'XOF');
        }

        return implode(' | ', $morceaux);
    }

    /** @param array<int, array<string, mixed>> $lignes */
    private function resumeEmballages(array $lignes): string
    {
        $morceaux = array_map(static fn (array $e): string => (int) $e['quantite'] . ' ' . $e['type'], $lignes);
        sort($morceaux);

        return implode(', ', $morceaux);
    }

    private function aujourdhui(): string
    {
        return substr($this->repo->maintenant(), 0, 10);
    }
}
