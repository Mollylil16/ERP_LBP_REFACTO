<?php

namespace App\Modules\Supervision\Repositories;

use App\Models\Database;
use PDO;

class SupervisionRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function getKpisConsolides(array $agencesFiltre = []): array
    {
        // On récupère le nombre de colis du jour, et le total encaissé.
        // Optionnel : on peut limiter par agencesFiltre si c'est un superviseur régional.
        $kpis = [
            'total_transactions_jour' => 0,
            'total_encaissements_jour' => 0,
            'agences_actives' => 0
        ];

        // Construction du filtre des agences (si non vide)
        $whereAgencesCaisse = "";
        $whereAgencesColis = "";
        $params = [];
        if (!empty($agencesFiltre)) {
            $inQuery = implode(',', array_fill(0, count($agencesFiltre), '?'));
            $whereAgencesCaisse = " AND id_caisse IN (SELECT id FROM lbp_caisses WHERE id_agence IN ($inQuery))";
            $whereAgencesColis = " AND id_agence_depart IN ($inQuery)";
            $params = $agencesFiltre;
        }

        // Transactions de caisse du jour (ENTREE)
        $sqlCaisse = "
            SELECT COUNT(*) as nb_transactions, SUM(montant) as total_encaissements
            FROM lbp_mouvements_caisse
            WHERE type_mouvement = 'ENTREE' 
              AND DATE(date_mouvement) = CURRENT_DATE
              $whereAgencesCaisse
        ";
        $stmtCaisse = $this->pdo->prepare($sqlCaisse);
        $stmtCaisse->execute($params);
        $resCaisse = $stmtCaisse->fetch(PDO::FETCH_ASSOC);

        if ($resCaisse) {
            $kpis['total_transactions_jour'] = (int)$resCaisse['nb_transactions'];
            $kpis['total_encaissements_jour'] = (float)$resCaisse['total_encaissements'];
        }

        // Agences actives (qui ont fait au moins un colis ou un mouvement)
        $sqlAgences = "
            SELECT COUNT(DISTINCT id_agence) as nb_agences_actives FROM (
                SELECT c.id_agence FROM lbp_caisses c JOIN lbp_mouvements_caisse m ON m.id_caisse = c.id WHERE DATE(m.date_mouvement) = CURRENT_DATE
                UNION
                SELECT id_agence_depart as id_agence FROM lbp_colis WHERE DATE(created_at) = CURRENT_DATE
            ) as sub
        ";
        $stmtAgences = $this->pdo->query($sqlAgences); // TODO: Appliquer $agencesFiltre si besoin
        $kpis['agences_actives'] = (int)$stmtAgences->fetchColumn();

        return $kpis;
    }

    public function signalerAnomalie(array $data): array
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO lbp_signalements (id_agence, id_auteur, type, description, gravite) 
            VALUES (:id_agence, :id_auteur, :type, :description, :gravite)
            RETURNING *
        ");
        $stmt->execute([
            'id_agence' => $data['id_agence'],
            'id_auteur' => $data['id_auteur'],
            'type' => $data['type'],
            'description' => $data['description'] ?? null,
            'gravite' => $data['gravite'] ?? 'moyen'
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function demanderJustification(array $data): array
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO lbp_demandes_justification (id_demandeur, id_destinataire, id_agence, id_operation, type_operation, motif) 
            VALUES (:id_demandeur, :id_destinataire, :id_agence, :id_operation, :type_operation, :motif)
            RETURNING *
        ");
        $stmt->execute([
            'id_demandeur' => $data['id_demandeur'],
            'id_destinataire' => $data['id_destinataire'],
            'id_agence' => $data['id_agence'],
            'id_operation' => $data['id_operation'] ?? null,
            'type_operation' => $data['type_operation'] ?? null,
            'motif' => $data['motif']
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function annoterOperation(array $data): array
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO lbp_annotations_supervision (id_auteur, id_operation, type_operation, contenu, visibilite) 
            VALUES (:id_auteur, :id_operation, :type_operation, :contenu, :visibilite)
            RETURNING *
        ");
        $stmt->execute([
            'id_auteur' => $data['id_auteur'],
            'id_operation' => $data['id_operation'],
            'type_operation' => $data['type_operation'],
            'contenu' => $data['contenu'],
            'visibilite' => 'direction'
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
