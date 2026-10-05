<?php

declare(strict_types=1);

namespace App\Repositories\Admin;

use App\Services\Admin\AdminService;
use PDO;

/**
 * Qui porte quel rôle, et ce qui cloche.
 *
 * Les rôles s'attribuaient un par un, dans la fiche de chaque compte. Répondre
 * à « qui est comptable ? » demandait d'ouvrir les quarante fiches — et
 * personne ne le faisait, si bien que le 30/09/2026 les rôles du personnel ont
 * été réécrits sans que l'anomalie se voie avant les plaintes.
 *
 * Cet écran la montre en une page, avec trois anomalies que ce logiciel sait
 * produire : un compte sans aucun rôle, un rôle que plus personne ne porte, et
 * un rôle présent en base mais absent du catalogue.
 */
final class RolesRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Le catalogue, chacun avec ceux qui le portent.
     *
     * @return array<int, array{code:string, libelle:string, porteurs:array<int,array{id:int,nom:string,statut:string}>, hors_catalogue:bool}>
     */
    public function rolesEtPorteurs(): array
    {
        $catalogue = AdminService::AVAILABLE_ROLES;
        $porteurs = $this->porteursParRole();

        $lignes = [];

        foreach ($catalogue as $code => $libelle) {
            $lignes[] = [
                'code' => (string) $code,
                'libelle' => (string) $libelle,
                'porteurs' => $porteurs[$code] ?? [],
                'hors_catalogue' => false,
            ];
        }

        // Un rôle porté par un compte mais absent du catalogue est exactement
        // ce que le catalogue effaçait en silence avant le 18/09/2026.
        foreach ($porteurs as $code => $gens) {
            if (!isset($catalogue[$code])) {
                $lignes[] = [
                    'code' => (string) $code,
                    'libelle' => (string) $code,
                    'porteurs' => $gens,
                    'hors_catalogue' => true,
                ];
            }
        }

        return $lignes;
    }

    /** @return array<string, array<int, array{id:int, nom:string, statut:string}>> */
    private function porteursParRole(): array
    {
        try {
            $stmt = $this->pdo->query('
                SELECT r.role, u.id, u.full_name AS nom, u.status AS statut
                FROM lbp_user_roles r
                JOIN users u ON u.id = r.user_id
                ORDER BY r.role ASC, u.full_name ASC
            ');

            $parRole = [];
            foreach ($stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [] as $ligne) {
                $parRole[(string) $ligne['role']][] = [
                    'id' => (int) $ligne['id'],
                    'nom' => (string) $ligne['nom'],
                    'statut' => (string) $ligne['statut'],
                ];
            }

            return $parRole;
        } catch (\Throwable $e) {
            error_log('[RolesRepository] porteursParRole : ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Les comptes actifs qui ne portent aucun rôle.
     *
     * Ils peuvent se connecter et ne rien faire : c'est le symptôme exact des
     * deux incidents de rôles, et il se lit en une requête.
     *
     * @return array<int, array{id:int, nom:string, email:string}>
     */
    public function comptesSansRole(): array
    {
        try {
            $stmt = $this->pdo->query("
                SELECT u.id, u.full_name AS nom, u.email
                FROM users u
                LEFT JOIN lbp_user_roles r ON r.user_id = u.id
                WHERE u.status = 'active' AND u.is_admin = 0 AND r.user_id IS NULL
                ORDER BY u.full_name ASC
            ");

            return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Les comptes qui n'ont jamais ouvert le logiciel depuis un certain temps.
     *
     * @return array<int, array{id:int, nom:string, email:string, last_login_at:?string}>
     */
    public function comptesDormants(int $jours = 60): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, full_name AS nom, email, last_login_at
                FROM users
                WHERE status = 'active'
                  AND (last_login_at IS NULL OR last_login_at < DATE_SUB(NOW(), INTERVAL :jours DAY))
                ORDER BY last_login_at IS NULL DESC, last_login_at ASC
            ");
            $stmt->bindValue(':jours', $jours, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Les comptes administrateurs : ils contournent toutes les règles à deux mains. */
    public function administrateurs(): array
    {
        try {
            $stmt = $this->pdo->query("
                SELECT id, full_name AS nom, email, last_login_at
                FROM users
                WHERE is_admin = 1 AND status = 'active'
                ORDER BY full_name ASC
            ");

            return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Les comptes ouverts depuis le début du mois. */
    public function comptesDuMois(): int
    {
        try {
            $stmt = $this->pdo->query("
                SELECT COUNT(*) FROM users WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
            ");

            return $stmt ? (int) $stmt->fetchColumn() : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
