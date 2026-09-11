<?php

declare(strict_types=1);

namespace App\Repositories\Shared;

use App\Services\Shared\ModuleDashboardService;
use PDO;
use Throwable;

class ModuleDashboardRepository
{
    public function __construct(protected PDO $pdo)
    {
    }

    /**
     * @return array<string,mixed>
     */
    public function dashboardFor(string $slug): array
    {
        return (new ModuleDashboardService())->dashboard($slug);
    }

    /**
     * Libellé d'une agence, pour titrer le périmètre affiché.
     *
     * Appelé une fois par écran ; la valeur est mémorisée le temps de la requête
     * HTTP pour éviter de la redemander si plusieurs sections en ont besoin.
     *
     * @var array<int, string>
     */
    private static array $nomsAgences = [];

    public function nomAgence(?int $agenceId): string
    {
        if ($agenceId === null) {
            return 'Toutes agences';
        }

        if ($agenceId <= 0) {
            return 'Aucune agence rattachée';
        }

        if (isset(self::$nomsAgences[$agenceId])) {
            return self::$nomsAgences[$agenceId];
        }

        try {
            $stmt = $this->pdo->prepare('SELECT name FROM company_sites WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $agenceId]);
            $nom = $stmt->fetchColumn();
        } catch (Throwable) {
            $nom = false;
        }

        return self::$nomsAgences[$agenceId] = is_string($nom) && $nom !== ''
            ? $nom
            : ('Agence #' . $agenceId);
    }
}
