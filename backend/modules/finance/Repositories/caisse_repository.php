<?php

namespace App\Modules\Finance\Repositories;

use App\Models\Database;
use PDO;

class CaisseRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function getCaisse(int $id_agence): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM lbp_caisses WHERE id_agence = :id_agence LIMIT 1");
        $stmt->execute(['id_agence' => $id_agence]);
        $caisse = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$caisse) {
            // Créer la caisse si elle n'existe pas encore pour cette agence
            $insert = $this->pdo->prepare("INSERT INTO lbp_caisses (id_agence, solde_actuel) VALUES (:id_agence, 0) RETURNING *");
            $insert->execute(['id_agence' => $id_agence]);
            return $insert->fetch(PDO::FETCH_ASSOC);
        }

        return $caisse;
    }

    public function fetchMouvements(array $filters = []): array
    {
        $sql = "
            SELECT m.*, u.fullname as createur_nom
            FROM lbp_mouvements_caisse m
            LEFT JOIN lbp_users u ON m.id_createur = u.id
            WHERE 1=1
        ";

        $params = [];
        if (!empty($filters['id_caisse'])) {
            $sql .= " AND m.id_caisse = :id_caisse";
            $params['id_caisse'] = $filters['id_caisse'];
        }

        if (!empty($filters['type_mouvement'])) {
            $sql .= " AND m.type_mouvement = :type";
            $params['type'] = $filters['type_mouvement'];
        }

        $sql .= " ORDER BY m.date_mouvement DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function addMouvement(array $data): array
    {
        try {
            $this->pdo->beginTransaction();

            // Verrouiller la caisse pour mettre à jour le solde
            $caisseStmt = $this->pdo->prepare("SELECT solde_actuel FROM lbp_caisses WHERE id = :id FOR UPDATE");
            $caisseStmt->execute(['id' => $data['id_caisse']]);
            $caisse = $caisseStmt->fetch(PDO::FETCH_ASSOC);

            if (!$caisse) {
                throw new \Exception("Caisse introuvable");
            }

            $soldeAvant = (float)$caisse['solde_actuel'];
            $montant = (float)$data['montant'];
            $type = $data['type_mouvement']; // APPRO, ENTREE, DECAISSEMENT

            if ($type === 'DECAISSEMENT') {
                if ($soldeAvant < $montant) {
                    throw new \Exception("Fonds insuffisants dans la caisse pour ce décaissement. Solde actuel : " . $soldeAvant);
                }
                $soldeApres = $soldeAvant - $montant;
            } else {
                $soldeApres = $soldeAvant + $montant;
            }

            // Mettre à jour le solde de la caisse
            $updateCaisse = $this->pdo->prepare("UPDATE lbp_caisses SET solde_actuel = :solde, updated_at = NOW() WHERE id = :id");
            $updateCaisse->execute(['solde' => $soldeApres, 'id' => $data['id_caisse']]);

            // Inserer le mouvement
            $insert = $this->pdo->prepare("
                INSERT INTO lbp_mouvements_caisse (
                    id_caisse, id_createur, type_mouvement, libelle, montant, solde_apres_operation,
                    mode_reglement, numero_dossier, numero_piece, numero_fiche_recette,
                    numero_bordereau_versement, numero_ordre_decaissement, nom_client, nom_demandeur
                ) VALUES (
                    :id_caisse, :id_createur, :type_mouvement, :libelle, :montant, :solde_apres_operation,
                    :mode_reglement, :numero_dossier, :numero_piece, :numero_fiche_recette,
                    :numero_bordereau_versement, :numero_ordre_decaissement, :nom_client, :nom_demandeur
                ) RETURNING *
            ");

            $insert->execute([
                'id_caisse' => $data['id_caisse'],
                'id_createur' => $data['id_createur'],
                'type_mouvement' => $type,
                'libelle' => $data['libelle'] ?? null,
                'montant' => $montant,
                'solde_apres_operation' => $soldeApres,
                'mode_reglement' => $data['mode_reglement'] ?? null,
                'numero_dossier' => $data['numero_dossier'] ?? null,
                'numero_piece' => $data['numero_piece'] ?? null,
                'numero_fiche_recette' => $data['numero_fiche_recette'] ?? null,
                'numero_bordereau_versement' => $data['numero_bordereau_versement'] ?? null,
                'numero_ordre_decaissement' => $data['numero_ordre_decaissement'] ?? null,
                'nom_client' => $data['nom_client'] ?? null,
                'nom_demandeur' => $data['nom_demandeur'] ?? null,
            ]);

            $mouvement = $insert->fetch(PDO::FETCH_ASSOC);
            $this->pdo->commit();
            return $mouvement;

        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
