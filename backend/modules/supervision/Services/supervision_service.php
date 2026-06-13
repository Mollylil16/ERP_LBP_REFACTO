<?php

namespace App\Modules\Supervision\Services;

use App\Modules\Supervision\Repositories\SupervisionRepository;
use App\Models\Database;
use PDO;

class SupervisionService
{
    public function __construct(private SupervisionRepository $repository = new SupervisionRepository()) {}

    /**
     * Récupère la liste des agences que le superviseur est autorisé à voir.
     */
    public function getAgencesAutorisees(array $user): array
    {
        $role = $user['role_name'] ?? '';
        
        // Si c'est un Superviseur Général, il voit tout
        if ($role === 'Superviseur Général' || $role === 'Directeur Général' || $role === 'Assistante DG') {
            return []; // Tableau vide = aucun filtre, on prend toutes les agences
        }

        // Si c'est un Superviseur Régional, on filtre par pays (déduit via le code agence)
        if ($role === 'Superviseur Régional') {
            $userAgenceCode = $user['code_agence'] ?? 'CI'; // Par defaut CI
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT id FROM lbp_agences WHERE code_agence = :code");
            $stmt->execute(['code' => $userAgenceCode]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [-1]; // -1 pour ne rien trouver si erreur
        }

        // Par défaut, s'il n'est pas superviseur, il ne voit rien de la supervision globale
        return [-1];
    }

    public function getKpisConsolides(array $user): array
    {
        $agencesFiltre = $this->getAgencesAutorisees($user);
        return $this->repository->getKpisConsolides($agencesFiltre);
    }

    public function signalerAnomalie(array $payload, int $id_auteur): array
    {
        if (empty($payload['id_agence']) || empty($payload['type']) || empty($payload['description'])) {
            throw new \InvalidArgumentException('L\'agence, le type et la description sont requis.');
        }

        $data = [
            'id_agence' => $payload['id_agence'],
            'id_auteur' => $id_auteur,
            'type' => $payload['type'],
            'description' => $payload['description'],
            'gravite' => $payload['gravite'] ?? 'moyen'
        ];

        return $this->repository->signalerAnomalie($data);
    }

    public function demanderJustification(array $payload, int $id_demandeur): array
    {
        if (empty($payload['id_destinataire']) || empty($payload['id_agence']) || empty($payload['motif'])) {
            throw new \InvalidArgumentException('Le destinataire, l\'agence et le motif sont requis.');
        }

        $data = [
            'id_demandeur' => $id_demandeur,
            'id_destinataire' => $payload['id_destinataire'],
            'id_agence' => $payload['id_agence'],
            'id_operation' => $payload['id_operation'] ?? null,
            'type_operation' => $payload['type_operation'] ?? null,
            'motif' => $payload['motif']
        ];

        return $this->repository->demanderJustification($data);
    }

    public function annoterOperation(array $payload, int $id_auteur): array
    {
        if (empty($payload['id_operation']) || empty($payload['type_operation']) || empty($payload['contenu'])) {
            throw new \InvalidArgumentException('L\'opération, le type d\'opération et le contenu sont requis.');
        }

        $data = [
            'id_auteur' => $id_auteur,
            'id_operation' => $payload['id_operation'],
            'type_operation' => $payload['type_operation'],
            'contenu' => $payload['contenu']
        ];

        return $this->repository->annoterOperation($data);
    }
}
