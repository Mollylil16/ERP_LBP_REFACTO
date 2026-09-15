<?php

declare(strict_types=1);

namespace App\Repositories\Colisage;

use PDO;

/**
 * Dossiers d'envoi : ce que le transport d'un départ a coûté et ce qu'il a
 * produit comme pièces.
 *
 * Un dossier complète un départ du pointage (lbp_expeditions) sans le
 * remplacer : il peut exister avant lui, et un départ peut en porter plusieurs
 * quand LBP réserve deux documents de transport.
 *
 * PDO tourne sans émulation des requêtes préparées : un même paramètre nommé
 * ne peut pas figurer deux fois dans une requête.
 */
final class DossierEnvoiRepository
{
    /** Colonnes écrites à la création. */
    private const COLONNES = [
        'numero', 'mode_transport', 'statut', 'responsable_id', 'agence_depart_id', 'agence_arrivee_id',
        'destination', 'expedition_id', 'transporteur_id', 'type_document', 'numero_document',
        'emetteur_document_id', 'document_principal', 'lieu_depart', 'lieu_arrivee',
        'date_depart_prevue', 'date_depart_effective', 'date_arrivee_estimee', 'date_arrivee', 'date_livraison',
        'nb_colis_declare', 'poids_brut_kg', 'poids_taxable_kg', 'volume_m3', 'taux_eur_xof',
        'commentaire_ecart', 'created_by',
    ];

    /** Colonnes que l'agent peut modifier ensuite. Le numéro et le taux restent figés. */
    private const MODIFIABLES = [
        'mode_transport', 'statut', 'agence_depart_id', 'agence_arrivee_id', 'destination', 'expedition_id',
        'transporteur_id', 'type_document', 'numero_document', 'emetteur_document_id', 'document_principal',
        'lieu_depart', 'lieu_arrivee', 'date_depart_prevue', 'date_depart_effective', 'date_arrivee_estimee',
        'date_arrivee', 'date_livraison', 'nb_colis_declare', 'poids_brut_kg', 'poids_taxable_kg', 'volume_m3',
        'commentaire_ecart',
    ];

    /** Colonnes que changent les gestes de circuit : soumettre, valider, renvoyer, réaffecter. */
    private const CIRCUIT = [
        'statut', 'soumis_le', 'valide_par_id', 'valide_le', 'motif_renvoi', 'motif_annulation', 'responsable_id',
    ];

    private const SELECT_DOSSIER = "
        SELECT d.*,
               COALESCE(d.date_depart_effective, d.date_depart_prevue, DATE(d.created_at)) AS date_reference,
               t.name AS transporteur, t.type AS transporteur_type, t.prefixe_lta AS transporteur_prefixe,
               em.name AS emetteur_document,
               ad.name AS agence_depart, aa.name AS agence_arrivee,
               u.full_name AS responsable, v.full_name AS valide_par, c.full_name AS cree_par,
               e.reference AS expedition_reference
        FROM lbp_dossiers_envoi d
        LEFT JOIN lbp_prestataires t ON t.id = d.transporteur_id
        LEFT JOIN lbp_prestataires em ON em.id = d.emetteur_document_id
        LEFT JOIN company_sites ad ON ad.id = d.agence_depart_id
        LEFT JOIN company_sites aa ON aa.id = d.agence_arrivee_id
        LEFT JOIN users u ON u.id = d.responsable_id
        LEFT JOIN users v ON v.id = d.valide_par_id
        LEFT JOIN users c ON c.id = d.created_by
        LEFT JOIN lbp_expeditions e ON e.id = d.expedition_id
    ";

    public function __construct(private PDO $pdo)
    {
    }

    public function maintenant(): string
    {
        return (string) $this->pdo->query('SELECT NOW()')->fetchColumn();
    }

    // ------------------------------------------------------------------
    // Référentiels
    // ------------------------------------------------------------------

    /** @return array<int, array<string, mixed>> */
    public function agences(): array
    {
        return $this->pdo->query('SELECT id, name, code, city, code_dossier FROM company_sites WHERE is_active = 1 ORDER BY name')
            ->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed>|null */
    public function agence(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, code, city, code_dossier FROM company_sites WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** @return array<int, array<string, mixed>> */
    public function prestataires(): array
    {
        return $this->pdo->query('SELECT id, type, name, country, prefixe_lta, is_active FROM lbp_prestataires ORDER BY is_active DESC, name ASC')
            ->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed>|null */
    public function prestataire(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, type, name, country, prefixe_lta, is_active FROM lbp_prestataires WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function prestataireNomme(string $nom, int $exclureId): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM lbp_prestataires WHERE LOWER(TRIM(name)) = LOWER(TRIM(:nom)) AND id <> :exclure LIMIT 1');
        $stmt->execute(['nom' => $nom, 'exclure' => $exclureId]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    public function creerPrestataire(string $type, string $nom, ?string $pays, ?string $prefixe): int
    {
        $this->pdo->prepare('
            INSERT INTO lbp_prestataires (type, name, country, prefixe_lta, is_active, created_at)
            VALUES (:type, :nom, :pays, :prefixe, 1, NOW())
        ')->execute(['type' => $type, 'nom' => $nom, 'pays' => $pays, 'prefixe' => $prefixe]);

        return (int) $this->pdo->lastInsertId();
    }

    public function modifierPrestataire(int $id, string $type, ?string $pays, ?string $prefixe, bool $actif): void
    {
        $this->pdo->prepare('
            UPDATE lbp_prestataires
            SET type = :type, country = :pays, prefixe_lta = :prefixe, is_active = :actif, updated_at = NOW()
            WHERE id = :id
        ')->execute(['type' => $type, 'pays' => $pays, 'prefixe' => $prefixe, 'actif' => $actif ? 1 : 0, 'id' => $id]);
    }

    /** @return array<int, array{id:int, full_name:string}> */
    public function utilisateursDuRole(string $role): array
    {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT u.id, u.full_name
            FROM users u
            JOIN lbp_user_roles r ON r.user_id = u.id
            WHERE r.role = :role AND u.status = 'active'
            ORDER BY u.full_name
        ");
        $stmt->execute(['role' => $role]);

        return array_map(
            static fn (array $l): array => ['id' => (int) $l['id'], 'full_name' => (string) $l['full_name']],
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
        );
    }

    public function tauxEurXof(): ?float
    {
        try {
            $taux = $this->pdo->query("
                SELECT taux FROM lbp_devises_taux
                WHERE devise_source = 'EUR' AND devise_cible = 'XOF'
                ORDER BY updated_at DESC LIMIT 1
            ")->fetchColumn();
        } catch (\Throwable) {
            return null;
        }

        return $taux !== false && (float) $taux > 0 ? (float) $taux : null;
    }

    // ------------------------------------------------------------------
    // Dossier
    // ------------------------------------------------------------------

    /**
     * Rang suivant d'une séquence, sans course possible entre deux saisies :
     * LAST_INSERT_ID(expr) garde la valeur propre à la connexion.
     */
    public function prochainRang(string $prefixe): int
    {
        $this->pdo->prepare('
            INSERT INTO lbp_dossiers_envoi_sequences (prefixe, dernier) VALUES (:prefixe, LAST_INSERT_ID(1))
            ON DUPLICATE KEY UPDATE dernier = LAST_INSERT_ID(dernier + 1)
        ')->execute(['prefixe' => $prefixe]);

        return (int) $this->pdo->query('SELECT LAST_INSERT_ID()')->fetchColumn();
    }

    /** @param array<string, mixed> $dossier */
    public function inserer(array $dossier): int
    {
        $valeurs = [];
        foreach (self::COLONNES as $colonne) {
            $valeurs[$colonne] = $dossier[$colonne] ?? null;
        }

        $this->pdo->prepare(
            'INSERT INTO lbp_dossiers_envoi (' . implode(', ', self::COLONNES) . ', created_at) VALUES (:'
            . implode(', :', self::COLONNES) . ', NOW())'
        )->execute($valeurs);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $dossier */
    public function mettreAJour(int $id, array $dossier): void
    {
        $affectations = [];
        $valeurs = ['id' => $id];

        foreach (self::MODIFIABLES as $colonne) {
            if (array_key_exists($colonne, $dossier)) {
                $affectations[] = $colonne . ' = :' . $colonne;
                $valeurs[$colonne] = $dossier[$colonne];
            }
        }

        if ($affectations === []) {
            return;
        }

        $this->pdo->prepare('UPDATE lbp_dossiers_envoi SET ' . implode(', ', $affectations) . ', updated_at = NOW() WHERE id = :id')
            ->execute($valeurs);
    }

    /** @param array<string, mixed> $valeurs */
    public function changerCircuit(int $id, array $valeurs): void
    {
        $affectations = [];
        $parametres = ['id' => $id];

        foreach (self::CIRCUIT as $colonne) {
            if (array_key_exists($colonne, $valeurs)) {
                $affectations[] = $colonne . ' = :' . $colonne;
                $parametres[$colonne] = $valeurs[$colonne];
            }
        }

        if ($affectations !== []) {
            $this->pdo->prepare('UPDATE lbp_dossiers_envoi SET ' . implode(', ', $affectations) . ', updated_at = NOW() WHERE id = :id')
                ->execute($parametres);
        }
    }

    /** @return array<string, mixed>|null */
    public function trouver(int $id): ?array
    {
        $stmt = $this->pdo->prepare(self::SELECT_DOSSIER . ' WHERE d.id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Numéro du dossier qui utilise déjà ce document, chez le même émetteur.
     *
     * @param array<int, string> $typesDocument valeurs internes, jamais saisies
     */
    public function documentDejaUtilise(string $numero, array $typesDocument, string $colonneEmetteur, ?int $emetteurId, int $exclureId): ?string
    {
        $colonne = $colonneEmetteur === 'emetteur_document_id' ? 'emetteur_document_id' : 'transporteur_id';
        $types = implode(', ', array_map(static fn (string $t): string => "'" . preg_replace('/[^A-Z_]/', '', $t) . "'", $typesDocument));

        $stmt = $this->pdo->prepare("
            SELECT numero FROM lbp_dossiers_envoi
            WHERE numero_document = :numero
              AND statut <> 'ANNULE'
              AND id <> :exclure
              AND type_document IN ({$types})
              AND {$colonne} <=> :emetteur
            LIMIT 1
        ");
        $stmt->execute(['numero' => $numero, 'exclure' => $exclureId, 'emetteur' => $emetteurId]);
        $trouve = $stmt->fetchColumn();

        return $trouve === false ? null : (string) $trouve;
    }

    /**
     * @param array<string, mixed> $filtres
     * @return array<int, array<string, mixed>>
     */
    public function lister(array $filtres, int $limite = 500): array
    {
        $conditions = ['1 = 1'];
        $parametres = [];

        if (($filtres['responsable_id'] ?? null) !== null) {
            $conditions[] = 'd.responsable_id = :responsable';
            $parametres['responsable'] = (int) $filtres['responsable_id'];
        }

        $statuts = array_values(array_filter((array) ($filtres['statuts'] ?? []), 'is_string'));
        if ($statuts !== []) {
            $marques = [];
            foreach ($statuts as $i => $statut) {
                $marques[] = ':statut' . $i;
                $parametres['statut' . $i] = $statut;
            }
            $conditions[] = 'd.statut IN (' . implode(', ', $marques) . ')';
        }

        $exclus = array_values(array_filter((array) ($filtres['statuts_exclus'] ?? []), 'is_string'));
        if ($exclus !== []) {
            $marques = [];
            foreach ($exclus as $i => $statut) {
                $marques[] = ':exclu' . $i;
                $parametres['exclu' . $i] = $statut;
            }
            $conditions[] = 'd.statut NOT IN (' . implode(', ', $marques) . ')';
        }

        if (!empty($filtres['mode'])) {
            $conditions[] = 'd.mode_transport = :mode';
            $parametres['mode'] = (string) $filtres['mode'];
        }

        if (!empty($filtres['agence_depart_id'])) {
            $conditions[] = 'd.agence_depart_id = :agence';
            $parametres['agence'] = (int) $filtres['agence_depart_id'];
        }

        if (!empty($filtres['transporteur_id'])) {
            $conditions[] = 'd.transporteur_id = :transporteur';
            $parametres['transporteur'] = (int) $filtres['transporteur_id'];
        }

        if (!empty($filtres['transitaire_id'])) {
            $conditions[] = "(d.emetteur_document_id = :transitaire1 OR EXISTS (
                SELECT 1 FROM lbp_dossiers_envoi_frais fx
                WHERE fx.dossier_id = d.id AND fx.poste IN ('TRANSIT_DEPART', 'TRANSIT_ARRIVEE') AND fx.prestataire_id = :transitaire2
            ))";
            $parametres['transitaire1'] = (int) $filtres['transitaire_id'];
            $parametres['transitaire2'] = (int) $filtres['transitaire_id'];
        }

        if (!empty($filtres['du']) && !empty($filtres['au'])) {
            $conditions[] = 'COALESCE(d.date_depart_effective, d.date_depart_prevue, DATE(d.created_at)) BETWEEN :du AND :au';
            $parametres['du'] = (string) $filtres['du'];
            $parametres['au'] = (string) $filtres['au'];
        }

        $recherche = trim((string) ($filtres['q'] ?? ''));
        if ($recherche !== '') {
            $conditions[] = '(d.numero LIKE :q1 OR d.numero_document LIKE :q2 OR d.document_principal LIKE :q3
                OR EXISTS (SELECT 1 FROM lbp_dossiers_envoi_tranches tx WHERE tx.dossier_id = d.id AND tx.reference LIKE :q4)
                OR EXISTS (SELECT 1 FROM lbp_colis cx WHERE cx.expedition_id = d.expedition_id AND cx.numero_tracking = :q5))';
            $motif = '%' . $recherche . '%';
            $parametres['q1'] = $motif;
            $parametres['q2'] = $motif;
            $parametres['q3'] = $motif;
            $parametres['q4'] = $motif;
            $parametres['q5'] = $recherche;
        }

        $stmt = $this->pdo->prepare(
            self::SELECT_DOSSIER . ' WHERE ' . implode(' AND ', $conditions)
            . ' ORDER BY date_reference DESC, d.id DESC LIMIT ' . max(1, $limite)
        );
        $stmt->execute($parametres);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // ------------------------------------------------------------------
    // Tranches, emballages, frais
    // ------------------------------------------------------------------

    /** @return array<int, array<string, mixed>> */
    public function tranches(int $dossierId): array
    {
        return $this->lignesDe('lbp_dossiers_envoi_tranches', [$dossierId], 'rang')[$dossierId] ?? [];
    }

    /** @param array<int, array<string, mixed>> $tranches */
    public function remplacerTranches(int $dossierId, array $tranches): void
    {
        $this->pdo->prepare('DELETE FROM lbp_dossiers_envoi_tranches WHERE dossier_id = :id')->execute(['id' => $dossierId]);

        $insertion = $this->pdo->prepare('
            INSERT INTO lbp_dossiers_envoi_tranches
                (dossier_id, rang, type, reference, type_conteneur, chauffeur, date_depart, date_arrivee, nb_colis, poids_kg)
            VALUES (:dossier, :rang, :type, :reference, :conteneur, :chauffeur, :depart, :arrivee, :colis, :poids)
        ');

        foreach (array_values($tranches) as $i => $t) {
            $insertion->execute([
                'dossier' => $dossierId,
                'rang' => $i + 1,
                'type' => $t['type'],
                'reference' => $t['reference'] ?? null,
                'conteneur' => $t['type_conteneur'] ?? null,
                'chauffeur' => $t['chauffeur'] ?? null,
                'depart' => $t['date_depart'] ?? null,
                'arrivee' => $t['date_arrivee'] ?? null,
                'colis' => $t['nb_colis'] ?? null,
                'poids' => $t['poids_kg'] ?? null,
            ]);
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function emballages(int $dossierId): array
    {
        return $this->lignesDe('lbp_dossiers_envoi_emballages', [$dossierId], 'id')[$dossierId] ?? [];
    }

    /** @param array<int, array{type:string, quantite:int}> $emballages */
    public function remplacerEmballages(int $dossierId, array $emballages): void
    {
        $this->pdo->prepare('DELETE FROM lbp_dossiers_envoi_emballages WHERE dossier_id = :id')->execute(['id' => $dossierId]);

        $insertion = $this->pdo->prepare('INSERT INTO lbp_dossiers_envoi_emballages (dossier_id, type, quantite) VALUES (:dossier, :type, :quantite)');
        foreach ($emballages as $e) {
            $insertion->execute(['dossier' => $dossierId, 'type' => $e['type'], 'quantite' => $e['quantite']]);
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function frais(int $dossierId): array
    {
        return $this->fraisDes([$dossierId])[$dossierId] ?? [];
    }

    /**
     * Enregistre la part saisie d'un poste fixe. Le montant facturé n'y figure
     * pas : il vient du dépôt de la facture.
     *
     * @param array<string, mixed> $ligne
     */
    public function enregistrerPosteFixe(int $dossierId, string $poste, array $ligne): void
    {
        $valeurs = [
            'prestataire' => $ligne['prestataire_id'] ?? null,
            'libre' => $ligne['prestataire_libre'] ?? null,
            'prevu' => $ligne['montant_prevu'] ?? null,
            'devise' => $ligne['devise'] ?? 'XOF',
            'sans' => !empty($ligne['sans_frais']) ? 1 : 0,
            'commentaire' => $ligne['commentaire_ecart'] ?? null,
        ];

        $id = $this->idDuPoste($dossierId, $poste);

        if ($id === null) {
            $this->pdo->prepare('
                INSERT INTO lbp_dossiers_envoi_frais
                    (dossier_id, poste, prestataire_id, prestataire_libre, montant_prevu, devise, sans_frais, commentaire_ecart)
                VALUES (:dossier, :poste, :prestataire, :libre, :prevu, :devise, :sans, :commentaire)
            ')->execute($valeurs + ['dossier' => $dossierId, 'poste' => $poste]);

            return;
        }

        $this->pdo->prepare('
            UPDATE lbp_dossiers_envoi_frais
            SET prestataire_id = :prestataire, prestataire_libre = :libre, montant_prevu = :prevu, devise = :devise,
                sans_frais = :sans, commentaire_ecart = :commentaire
            WHERE id = :id
        ')->execute($valeurs + ['id' => $id]);
    }

    /** @param array<int, array<string, mixed>> $lignes */
    public function remplacerAutresFrais(int $dossierId, array $lignes): void
    {
        $this->pdo->prepare("DELETE FROM lbp_dossiers_envoi_frais WHERE dossier_id = :id AND poste = 'AUTRE'")->execute(['id' => $dossierId]);

        $insertion = $this->pdo->prepare("
            INSERT INTO lbp_dossiers_envoi_frais
                (dossier_id, poste, libelle, prestataire_id, prestataire_libre, montant_prevu, devise,
                 montant_facture, devise_facture, numero_facture, sans_frais, commentaire_ecart)
            VALUES (:dossier, 'AUTRE', :libelle, :prestataire, :libre, :prevu, :devise, :facture, :devise_facture, :numero, 0, :commentaire)
        ");

        foreach ($lignes as $l) {
            $insertion->execute([
                'dossier' => $dossierId,
                'libelle' => $l['libelle'] ?? null,
                'prestataire' => $l['prestataire_id'] ?? null,
                'libre' => $l['prestataire_libre'] ?? null,
                'prevu' => $l['montant_prevu'] ?? null,
                'devise' => $l['devise'] ?? 'XOF',
                'facture' => $l['montant_facture'] ?? null,
                'devise_facture' => ($l['montant_facture'] ?? null) !== null ? ($l['devise'] ?? 'XOF') : null,
                'numero' => $l['numero_facture'] ?? null,
                'commentaire' => $l['commentaire_ecart'] ?? null,
            ]);
        }
    }

    public function enregistrerFacture(int $dossierId, string $poste, float $montant, string $devise, ?string $numero, int $documentId): void
    {
        $id = $this->idDuPoste($dossierId, $poste);

        if ($id === null) {
            $this->pdo->prepare('
                INSERT INTO lbp_dossiers_envoi_frais
                    (dossier_id, poste, devise, montant_facture, devise_facture, numero_facture, document_id, sans_frais)
                VALUES (:dossier, :poste, :devise, :montant, :devise_facture, :numero, :document, 0)
            ')->execute([
                'dossier' => $dossierId, 'poste' => $poste, 'devise' => $devise, 'montant' => $montant,
                'devise_facture' => $devise, 'numero' => $numero, 'document' => $documentId,
            ]);

            return;
        }

        $this->pdo->prepare('
            UPDATE lbp_dossiers_envoi_frais
            SET montant_facture = :montant, devise_facture = :devise, numero_facture = :numero, document_id = :document
            WHERE id = :id
        ')->execute(['montant' => $montant, 'devise' => $devise, 'numero' => $numero, 'document' => $documentId, 'id' => $id]);
    }

    public function effacerFactureDuDocument(int $documentId): void
    {
        $this->pdo->prepare('
            UPDATE lbp_dossiers_envoi_frais
            SET montant_facture = NULL, devise_facture = NULL, numero_facture = NULL, document_id = NULL
            WHERE document_id = :document
        ')->execute(['document' => $documentId]);
    }

    private function idDuPoste(int $dossierId, string $poste): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM lbp_dossiers_envoi_frais WHERE dossier_id = :dossier AND poste = :poste ORDER BY id LIMIT 1');
        $stmt->execute(['dossier' => $dossierId, 'poste' => $poste]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    // ------------------------------------------------------------------
    // Pièces jointes
    // ------------------------------------------------------------------

    /** @return array<int, array<string, mixed>> */
    public function documents(int $dossierId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT d.id, d.dossier_id, d.type_document, d.nom_fichier, d.original_name, d.stored_path, d.mime_type,
                   d.size_bytes, d.uploaded_at, u.full_name AS depose_par
            FROM lbp_dossiers_envoi_documents d
            LEFT JOIN users u ON u.id = d.uploaded_by
            WHERE d.dossier_id = :id AND d.deleted_at IS NULL
            ORDER BY d.uploaded_at DESC, d.id DESC
        ');
        $stmt->execute(['id' => $dossierId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed>|null */
    public function document(int $dossierId, int $documentId): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM lbp_dossiers_envoi_documents
            WHERE id = :document AND dossier_id = :dossier AND deleted_at IS NULL
            LIMIT 1
        ');
        $stmt->execute(['document' => $documentId, 'dossier' => $dossierId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Pièces déjà déposées de ce type, retirées comprises : un nom n'est jamais réutilisé. */
    public function compterDocuments(int $dossierId, string $type): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM lbp_dossiers_envoi_documents WHERE dossier_id = :dossier AND type_document = :type');
        $stmt->execute(['dossier' => $dossierId, 'type' => $type]);

        return (int) $stmt->fetchColumn();
    }

    /** @param array<string, mixed> $document */
    public function insererDocument(array $document): int
    {
        $this->pdo->prepare('
            INSERT INTO lbp_dossiers_envoi_documents
                (dossier_id, type_document, nom_fichier, original_name, stored_path, mime_type, size_bytes, uploaded_by, uploaded_at)
            VALUES (:dossier, :type, :nom, :original, :chemin, :mime, :taille, :user, NOW())
        ')->execute([
            'dossier' => $document['dossier_id'],
            'type' => $document['type_document'],
            'nom' => $document['nom_fichier'],
            'original' => $document['original_name'],
            'chemin' => $document['stored_path'],
            'mime' => $document['mime_type'],
            'taille' => $document['size_bytes'],
            'user' => $document['uploaded_by'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function retirerDocument(int $documentId, ?int $userId): void
    {
        $this->pdo->prepare('UPDATE lbp_dossiers_envoi_documents SET deleted_at = NOW(), deleted_by = :user WHERE id = :id AND deleted_at IS NULL')
            ->execute(['user' => $userId, 'id' => $documentId]);
    }

    // ------------------------------------------------------------------
    // Journal
    // ------------------------------------------------------------------

    public function journaliser(int $dossierId, ?int $userId, string $action, ?string $champ, ?string $avant, ?string $apres, ?string $motif): void
    {
        $this->pdo->prepare('
            INSERT INTO lbp_dossiers_envoi_journal (dossier_id, user_id, action, champ, ancienne_valeur, nouvelle_valeur, motif, created_at)
            VALUES (:dossier, :user, :action, :champ, :avant, :apres, :motif, NOW())
        ')->execute([
            'dossier' => $dossierId, 'user' => $userId, 'action' => $action, 'champ' => $champ,
            'avant' => $avant, 'apres' => $apres, 'motif' => $motif,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function journal(int $dossierId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT j.*, u.full_name AS par
            FROM lbp_dossiers_envoi_journal j
            LEFT JOIN users u ON u.id = j.user_id
            WHERE j.dossier_id = :id
            ORDER BY j.created_at DESC, j.id DESC
            LIMIT 300
        ');
        $stmt->execute(['id' => $dossierId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // ------------------------------------------------------------------
    // Lectures groupées, pour les listes
    // ------------------------------------------------------------------

    /**
     * @param array<int, int> $ids
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function fraisDes(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $stmt = $this->pdo->prepare("
            SELECT f.*, p.name AS prestataire
            FROM lbp_dossiers_envoi_frais f
            LEFT JOIN lbp_prestataires p ON p.id = f.prestataire_id
            WHERE f.dossier_id IN (" . $this->marques($ids) . ")
            ORDER BY f.dossier_id, FIELD(f.poste, 'FRET', 'TRANSIT_DEPART', 'TRANSIT_ARRIVEE', 'LIVRAISON_DEPART', 'LIVRAISON_ARRIVEE', 'AUTRE'), f.id
        ");
        $stmt->execute(array_values($ids));

        return $this->grouper($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /**
     * @param array<int, int> $ids
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function emballagesDes(array $ids): array
    {
        return $this->lignesDe('lbp_dossiers_envoi_emballages', $ids, 'id');
    }

    /**
     * @param array<int, int> $ids
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function tranchesDes(array $ids): array
    {
        return $this->lignesDe('lbp_dossiers_envoi_tranches', $ids, 'rang');
    }

    /**
     * @param array<int, int> $ids
     * @return array<int, array<int, string>>
     */
    public function typesDocumentsDes(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $stmt = $this->pdo->prepare('
            SELECT dossier_id, type_document FROM lbp_dossiers_envoi_documents
            WHERE deleted_at IS NULL AND dossier_id IN (' . $this->marques($ids) . ')
            GROUP BY dossier_id, type_document
        ');
        $stmt->execute(array_values($ids));

        $types = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $ligne) {
            $types[(int) $ligne['dossier_id']][] = (string) $ligne['type_document'];
        }

        return $types;
    }

    /**
     * Colis rattachés à chaque départ par le pointage.
     *
     * @param array<int, int> $expeditionIds
     * @return array<int, int>
     */
    public function colisPointesDes(array $expeditionIds): array
    {
        if ($expeditionIds === []) {
            return [];
        }

        $stmt = $this->pdo->prepare("
            SELECT expedition_id, COUNT(*) AS nb FROM lbp_colis
            WHERE statut <> 'annule' AND expedition_id IN (" . $this->marques($expeditionIds) . ')
            GROUP BY expedition_id
        ');
        $stmt->execute(array_values($expeditionIds));

        $nb = array_fill_keys(array_map('intval', $expeditionIds), 0);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $ligne) {
            $nb[(int) $ligne['expedition_id']] = (int) $ligne['nb'];
        }

        return $nb;
    }

    // ------------------------------------------------------------------
    // Départs du pointage
    // ------------------------------------------------------------------

    private const SELECT_DEPART = "
        SELECT e.id, e.reference, e.type_transport, e.statut, e.agence_depart_id, e.agence_arrivee_id,
               COALESCE(e.date_depart_effective, e.created_at) AS date_depart,
               ad.name AS agence_depart, aa.name AS agence_arrivee,
               (SELECT COUNT(*) FROM lbp_colis c WHERE c.expedition_id = e.id AND c.statut <> 'annule') AS nb_colis
        FROM lbp_expeditions e
        LEFT JOIN company_sites ad ON ad.id = e.agence_depart_id
        LEFT JOIN company_sites aa ON aa.id = e.agence_arrivee_id
    ";

    /** @return array<string, mixed>|null */
    public function depart(int $id): ?array
    {
        $stmt = $this->pdo->prepare(self::SELECT_DEPART . ' WHERE e.id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Départs récents auxquels rattacher un dossier, plus celui déjà rattaché.
     *
     * @return array<int, array<string, mixed>>
     */
    public function departsRecents(?int $inclure): array
    {
        $stmt = $this->pdo->prepare(self::SELECT_DEPART . "
            WHERE (e.statut IN ('EN_TRANSIT', 'ARRIVE', 'CLOTURE')
                   AND COALESCE(e.date_depart_effective, e.created_at) >= DATE_SUB(NOW(), INTERVAL 120 DAY))
               OR e.id = :inclure
            ORDER BY date_depart DESC
            LIMIT 200
        ");
        $stmt->execute(['inclure' => $inclure ?? 0]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Départs marqués au pointage qui n'ont encore aucun dossier : aucun envoi
     * ne doit échapper au suivi des coûts.
     *
     * @return array<int, array<string, mixed>>
     */
    public function departsSansDossier(): array
    {
        return $this->pdo->query(self::SELECT_DEPART . "
            WHERE e.statut IN ('EN_TRANSIT', 'ARRIVE', 'CLOTURE')
              AND e.est_reprise = 0
              AND NOT EXISTS (SELECT 1 FROM lbp_dossiers_envoi d WHERE d.expedition_id = e.id AND d.statut <> 'ANNULE')
            ORDER BY date_depart DESC
            LIMIT 50
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<int, int> $expeditionIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function dossiersDesDeparts(array $expeditionIds): array
    {
        if ($expeditionIds === []) {
            return [];
        }

        $stmt = $this->pdo->prepare("
            SELECT id, numero, statut, expedition_id, mode_transport, numero_document, responsable_id
            FROM lbp_dossiers_envoi
            WHERE statut <> 'ANNULE' AND expedition_id IN (" . $this->marques($expeditionIds) . ')
            ORDER BY numero
        ');
        $stmt->execute(array_values($expeditionIds));

        $parDepart = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $ligne) {
            $parDepart[(int) $ligne['expedition_id']][] = $ligne;
        }

        return $parDepart;
    }

    // ------------------------------------------------------------------

    /**
     * @param array<int, int> $ids
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function lignesDe(string $table, array $ids, string $ordre): array
    {
        if ($ids === []) {
            return [];
        }

        $stmt = $this->pdo->prepare("SELECT * FROM {$table} WHERE dossier_id IN (" . $this->marques($ids) . ") ORDER BY dossier_id, {$ordre}");
        $stmt->execute(array_values($ids));

        return $this->grouper($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /**
     * @param array<int, array<string, mixed>> $lignes
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function grouper(array $lignes): array
    {
        $groupes = [];
        foreach ($lignes as $ligne) {
            $groupes[(int) $ligne['dossier_id']][] = $ligne;
        }

        return $groupes;
    }

    /** @param array<int, int> $ids */
    private function marques(array $ids): string
    {
        return implode(',', array_fill(0, count($ids), '?'));
    }
}
