<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Repositories\CaisseRepository;
use App\Core\SequenceGeneratorService;

class CaisseService
{
    public function __construct(
        private CaisseRepository $repository = new CaisseRepository(),
        private SequenceGeneratorService $sequenceGenerator = new SequenceGeneratorService()
    ) {}

    public function getCaisseStatus(int $id_agence): array
    {
        return $this->repository->getCaisse($id_agence);
    }

    public function listMouvements(array $filters): array
    {
        return $this->repository->fetchMouvements($filters);
    }

    public function addApprovisionnement(int $id_agence, int $id_createur, array $payload): array
    {
        if (empty($payload['montant']) || $payload['montant'] <= 0) {
            throw new \InvalidArgumentException("Le montant de l'approvisionnement doit être positif.");
        }

        $caisse = $this->repository->getCaisse($id_agence);

        $data = [
            'id_caisse' => $caisse['id'],
            'id_createur' => $id_createur,
            'type_mouvement' => 'APPRO',
            'libelle' => $payload['libelle'] ?? 'Approvisionnement de caisse',
            'montant' => $payload['montant'],
            'numero_fiche_recette' => $this->sequenceGenerator->generateFicheRecette(),
            'numero_bordereau_versement' => $this->sequenceGenerator->generateBordereauVersement(),
        ];

        return $this->repository->addMouvement($data);
    }

    public function addDecaissement(int $id_agence, int $id_createur, array $payload): array
    {
        if (empty($payload['montant']) || $payload['montant'] <= 0) {
            throw new \InvalidArgumentException("Le montant du décaissement doit être positif.");
        }
        if (empty($payload['libelle']) || empty($payload['nom_demandeur'])) {
            throw new \InvalidArgumentException("Le libellé et le nom du demandeur sont obligatoires.");
        }

        $caisse = $this->repository->getCaisse($id_agence);

        $data = [
            'id_caisse' => $caisse['id'],
            'id_createur' => $id_createur,
            'type_mouvement' => 'DECAISSEMENT',
            'libelle' => $payload['libelle'],
            'montant' => $payload['montant'],
            'nom_demandeur' => $payload['nom_demandeur'],
            'numero_ordre_decaissement' => $this->sequenceGenerator->generateDecaissement(),
            'numero_dossier' => $payload['numero_dossier'] ?? null,
        ];

        return $this->repository->addMouvement($data);
    }

    public function addEntree(int $id_agence, int $id_createur, array $payload): array
    {
        if (empty($payload['montant']) || $payload['montant'] <= 0) {
            throw new \InvalidArgumentException("Le montant de l'entrée doit être positif.");
        }
        if (empty($payload['mode_reglement'])) {
            throw new \InvalidArgumentException("Le mode de règlement est obligatoire (ESPECE, CHEQUE, VIREMENT).");
        }

        $caisse = $this->repository->getCaisse($id_agence);

        $data = [
            'id_caisse' => $caisse['id'],
            'id_createur' => $id_createur,
            'type_mouvement' => 'ENTREE',
            'libelle' => $payload['libelle'] ?? 'Paiement client',
            'montant' => $payload['montant'],
            'mode_reglement' => $payload['mode_reglement'],
            'numero_dossier' => $payload['numero_dossier'] ?? null,
            'numero_piece' => $payload['numero_piece'] ?? null,
            'nom_client' => $payload['nom_client'] ?? null,
            'numero_fiche_recette' => $this->sequenceGenerator->generateFicheRecette(),
        ];

        return $this->repository->addMouvement($data);
    }
}
