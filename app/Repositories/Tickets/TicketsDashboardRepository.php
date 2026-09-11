<?php

declare(strict_types=1);

namespace App\Repositories\Tickets;

use PDO;
use Throwable;

final class TicketsDashboardRepository extends \App\Repositories\Shared\ModuleDashboardRepository
{
    /**
     * Indicateurs du module Tickets.
     *
     * Ils etaient jusqu ici ecrits en dur a zero dans ModuleDashboardService, ce qui
     * est indiscernable d un vrai zero pour celui qui regarde l ecran. Chaque chiffre
     * est desormais calcule, et un indicateur qui ne peut pas l etre affiche un tiret
     * plutot qu un zero trompeur.
     *
     * @return array<string,mixed>
     */
    public function dashboard(): array
    {
        $data = $this->dashboardFor('tickets');

        $ouverts = $this->compter("SELECT COUNT(*) FROM tickets WHERE status IN ('open', 'assigned', 'in_progress', 'waiting')");
        $urgents = $this->compter("SELECT COUNT(*) FROM tickets WHERE priority IN ('high', 'urgent', 'critique', 'haute') AND status IN ('open', 'assigned', 'in_progress', 'waiting')");
        $enRetard = $this->compter("SELECT COUNT(*) FROM tickets WHERE due_at IS NOT NULL AND due_at < NOW() AND status IN ('open', 'assigned', 'in_progress', 'waiting')");
        $duJour = $this->compter("SELECT COUNT(*) FROM tickets WHERE DATE(created_at) = CURDATE()");
        $resolus = $this->compter("SELECT COUNT(*) FROM tickets WHERE status IN ('resolved', 'closed') AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");

        $data['kpis'] = [
            [
                'label' => 'Tickets ouverts',
                'value' => self::afficher($ouverts),
                'meta' => 'Demandes interservices en cours',
                'tone' => ($ouverts ?? 0) > 0 ? 'warning' : 'success',
            ],
            [
                'label' => 'Urgents',
                'value' => self::afficher($urgents),
                'meta' => 'Priorités hautes non traitées',
                'tone' => ($urgents ?? 0) > 0 ? 'danger' : 'success',
            ],
            [
                'label' => 'En retard',
                'value' => self::afficher($enRetard),
                'meta' => 'Échéance dépassée',
                'tone' => ($enRetard ?? 0) > 0 ? 'danger' : 'success',
            ],
            [
                'label' => 'Ouverts aujourd\'hui',
                'value' => self::afficher($duJour),
                'meta' => $resolus !== null ? $resolus . ' résolu(s) sur 30 jours' : 'Nouvelles demandes du jour',
            ],
        ];

        return $data;
    }

    /**
     * Retourne null si le comptage echoue, pour distinguer « indisponible » de « zero ».
     */
    private function compter(string $sql): ?int
    {
        try {
            $stmt = $this->pdo->query($sql);

            return $stmt === false ? null : (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
            return null;
        }
    }

    private static function afficher(?int $valeur): string
    {
        return $valeur === null ? '—' : (string) $valeur;
    }
}
