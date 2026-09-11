<?php

declare(strict_types=1);

namespace App\Services\AgentsCorrespondants;

use App\Repositories\AgentsCorrespondants\AgentsCorrespondantsDashboardRepository;

final class AgentsCorrespondantsDashboardService extends \App\Services\Shared\AbstractModuleDashboardService implements \App\Services\Shared\ModuleDashboardContract
{
    public function __construct(private AgentsCorrespondantsDashboardRepository $agents)
    {
        parent::__construct($agents);
    }

    /**
     * @return array<string, mixed>
     */
    public function reseau(string $recherche = ''): array
    {
        $agents = $this->agents->agents($recherche);
        $parPays = $this->agents->parPays();

        return [
            'recherche' => $recherche,
            'agents' => $agents,
            'parPays' => $parPays,
            'kpis' => $this->kpis($agents, $parPays),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function trouver(int $id): ?array
    {
        return $this->agents->trouver($id);
    }

    /**
     * Valide la saisie et renvoie les erreurs par champ.
     *
     * @param array<string, mixed> $donnees
     * @return array<int, string>
     */
    public function erreurs(array $donnees): array
    {
        $erreurs = [];

        if (trim((string) ($donnees['name'] ?? '')) === '') {
            $erreurs[] = 'Le nom du correspondant est obligatoire.';
        }

        if (trim((string) ($donnees['country'] ?? '')) === '') {
            $erreurs[] = 'Le pays est obligatoire : c\'est lui qui structure l\'annuaire.';
        }

        $email = trim((string) ($donnees['email'] ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $erreurs[] = 'L\'adresse e-mail saisie n\'est pas valide.';
        }

        return $erreurs;
    }

    /**
     * @param array<string, mixed> $donnees
     */
    public function creer(array $donnees): int
    {
        return $this->agents->creer($donnees);
    }

    /**
     * @param array<string, mixed> $donnees
     */
    public function modifier(int $id, array $donnees): void
    {
        $this->agents->modifier($id, $donnees);
    }

    public function desactiver(int $id): void
    {
        $this->agents->desactiver($id);
    }

    /**
     * @param array<int, array<string, mixed>> $agents
     * @param array<int, array<string, mixed>> $parPays
     * @return array<int, array<string, mixed>>
     */
    private function kpis(array $agents, array $parPays): array
    {
        $actifs = count(array_filter($agents, static fn(array $a): bool => (int) $a['is_active'] === 1));
        $joignables = count(array_filter(
            $agents,
            static fn(array $a): bool => trim((string) ($a['email'] ?? '')) !== '' || trim((string) ($a['phone'] ?? '')) !== ''
        ));
        $villes = array_sum(array_map(static fn(array $p): int => (int) $p['nb_villes'], $parPays));

        return [
            [
                'label' => 'Correspondants',
                'value' => (string) count($agents),
                'meta' => $actifs . ' actif(s), ' . (count($agents) - $actifs) . ' inactif(s)',
            ],
            [
                'label' => 'Pays couverts',
                'value' => (string) count($parPays),
                'meta' => $villes . ' ville(s) renseignée(s)',
            ],
            [
                'label' => 'Joignables',
                'value' => (string) $joignables,
                'meta' => $joignables < count($agents)
                    ? (count($agents) - $joignables) . ' fiche(s) sans e-mail ni téléphone'
                    : 'Toutes les fiches ont un contact',
            ],
            [
                'label' => 'Zones décrites',
                'value' => (string) count(array_filter(
                    $agents,
                    static fn(array $a): bool => trim((string) ($a['coverage'] ?? '')) !== ''
                )),
                'meta' => 'Fiches précisant leur zone de couverture',
            ],
        ];
    }
}
