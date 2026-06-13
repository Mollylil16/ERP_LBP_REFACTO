<?php

namespace App\Modules\Rh\Services;

use App\Modules\Rh\Repositories\OperatorRepository;

class OperatorService
{
    public function __construct(private OperatorRepository $repository = new OperatorRepository()) {}

    public function listOperateurs(int $agenceId): array
    {
        return $this->repository->fetchOperateursByAgence($agenceId);
    }

    public function createOperateur(array $payload): array
    {
        if (empty($payload['id_agence']) || empty($payload['nom_complet']) || empty($payload['code_secret'])) {
            throw new \InvalidArgumentException('Agence, nom complet et code secret sont requis');
        }

        // Vérifier longueur
        if (strlen($payload['code_secret']) !== 6) {
            throw new \InvalidArgumentException('Le mot de passe (code) de l’opérateur doit comporter exactement 6 caractères');
        }

        $payload['code_secret_hash'] = password_hash($payload['code_secret'], PASSWORD_DEFAULT);

        return $this->repository->createOperateur($payload);
    }

    public function updateOperateur(int $id, array $payload): array
    {
        if (!empty($payload['code_secret'])) {
            if (strlen($payload['code_secret']) !== 6) {
                throw new \InvalidArgumentException('Le mot de passe (code) de l’opérateur doit comporter exactement 6 caractères');
            }
            $payload['code_secret_hash'] = password_hash($payload['code_secret'], PASSWORD_DEFAULT);
        }

        $op = $this->repository->updateOperateur($id, $payload);
        if ($op === null) {
            throw new \RuntimeException('Opérateur introuvable');
        }

        return $op;
    }

    public function toggleOperateur(int $id): array
    {
        $op = $this->repository->fetchOperateurById($id);
        if ($op === null) {
            throw new \RuntimeException('Opérateur introuvable');
        }

        return $this->repository->updateOperateur($id, ['isActive' => !$op['is_active']]);
    }

    /**
     * Valide le mot de passe d'un opérateur pour une agence donnée et retourne son ID.
     */
    public function authenticateOperateur(int $agenceId, string $password): array
    {
        if (empty($password)) {
            throw new \InvalidArgumentException('Le mot de passe opérateur est requis');
        }

        $operateurs = $this->repository->fetchOperateursByAgence($agenceId);

        foreach ($operateurs as $op) {
            if (password_verify($password, $op['code_secret_hash'])) {
                // Opérateur trouvé
                return [
                    'id' => $op['id'],
                    'nom_complet' => $op['nom_complet']
                ];
            }
        }

        throw new \RuntimeException('Mot de passe opérateur invalide');
    }
}
