<?php

declare(strict_types=1);

namespace App\Repositories\Admin;

use PDO;

/**
 * Le journal des comptes : qui a touché à quel compte, et quoi exactement.
 *
 * Aucun geste d'administration ne laissait de trace lisible. Deux incidents
 * l'ont coûté cher — la purge du 05/08/2026, les rôles du personnel réécrits
 * le 30/09/2026 — sans qu'on puisse dire qui ni quoi.
 *
 * Ce journal n'a pas sa propre table. Il lit `lbp_audit_logs`, la piste déjà
 * chaînée en SHA-256 et vérifiée par VerifyAuditIntegrity : en créer une
 * seconde aurait coupé l'audit en deux, et seule l'une des moitiés aurait été
 * contrôlée. Les écritures passent par AuditLogService::log(), comme partout
 * ailleurs dans le logiciel.
 */
final class ComptesAuditRepository
{
    /** Les gestes tracés sur un compte, et le mot que la direction emploie. */
    public const ACTIONS = [
        'create_user' => 'Compte créé',
        'update_user' => 'Compte modifié',
        'set_roles' => 'Rôles changés',
        'set_permissions' => 'Droits changés',
        'activate_user' => 'Compte activé',
        'deactivate_user' => 'Compte désactivé',
        'reset_password' => 'Mot de passe réinitialisé',
        'login' => 'Connexion',
    ];

    /** Le type d'entité sous lequel les comptes sont inscrits. */
    public const ENTITE = 'users';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param array<string, mixed> $filtres
     * @return array<int, array<string, mixed>>
     */
    public function journal(array $filtres = []): array
    {
        $conditions = ['a.entity_type = :entite'];
        $params = ['entite' => self::ENTITE];

        $du = trim((string) ($filtres['du'] ?? ''));
        if ($du !== '') {
            $conditions[] = 'DATE(a.created_at) >= :du';
            $params['du'] = $du;
        }

        $au = trim((string) ($filtres['au'] ?? ''));
        if ($au !== '') {
            $conditions[] = 'DATE(a.created_at) <= :au';
            $params['au'] = $au;
        }

        $action = trim((string) ($filtres['action'] ?? ''));
        if ($action !== '') {
            $conditions[] = 'a.action = :action';
            $params['action'] = $action;
        }

        $cible = (int) ($filtres['cible_id'] ?? 0);
        if ($cible > 0) {
            $conditions[] = 'a.entity_id = :cible';
            $params['cible'] = $cible;
        }

        $acteur = (int) ($filtres['acteur_id'] ?? 0);
        if ($acteur > 0) {
            $conditions[] = 'a.user_id = :acteur';
            $params['acteur'] = $acteur;
        }

        /*
         * La recherche libre porte aussi sur l'avant et l'après : c'est là que
         * se trouve le rôle qu'on cherche, pas dans un nom de colonne.
         */
        $q = trim((string) ($filtres['q'] ?? ''));
        if ($q !== '') {
            $conditions[] = '(cible.full_name LIKE :q1 OR cible.email LIKE :q2'
                . ' OR acteur.full_name LIKE :q3 OR a.old_values LIKE :q4 OR a.new_values LIKE :q5)';
            foreach (['q1', 'q2', 'q3', 'q4', 'q5'] as $cle) {
                $params[$cle] = '%' . $q . '%';
            }
        }

        try {
            $stmt = $this->pdo->prepare('
                SELECT
                    a.id, a.action, a.entity_id, a.user_id,
                    a.old_values, a.new_values, a.ip_address, a.created_at,
                    cible.full_name AS cible_nom,
                    cible.email AS cible_email,
                    acteur.full_name AS acteur_nom
                FROM lbp_audit_logs a
                LEFT JOIN users cible ON cible.id = a.entity_id
                LEFT JOIN users acteur ON acteur.id = a.user_id
                WHERE ' . implode(' AND ', $conditions) . '
                ORDER BY a.created_at DESC, a.id DESC
                LIMIT 2000
            ');
            $stmt->execute($params);

            return array_map([self::class, 'composer'], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
        } catch (\Throwable $e) {
            error_log('[ComptesAuditRepository] journal : ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Rend lisible ce que la base garde en JSON.
     *
     * Un administrateur ne lit pas {"roles":["caissiere"]} : il lit « rôles :
     * caissiere ». La ligne porte donc aussi sa version en clair.
     *
     * @param array<string, mixed> $ligne
     * @return array<string, mixed>
     */
    private static function composer(array $ligne): array
    {
        $avant = self::decoder($ligne['old_values'] ?? null);
        $apres = self::decoder($ligne['new_values'] ?? null);

        $ligne['avant'] = $avant;
        $ligne['apres'] = $apres;
        $ligne['avant_texte'] = self::enClair($avant);
        $ligne['apres_texte'] = self::enClair($apres);
        $ligne['action_libelle'] = self::ACTIONS[(string) $ligne['action']] ?? (string) $ligne['action'];

        return $ligne;
    }

    /** @return array<string, mixed>|null */
    private static function decoder(mixed $json): ?array
    {
        if (!is_string($json) || trim($json) === '') {
            return null;
        }

        try {
            $valeur = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            return null;
        }

        return is_array($valeur) ? $valeur : null;
    }

    /** @param array<string, mixed>|null $valeurs */
    private static function enClair(?array $valeurs): string
    {
        if ($valeurs === null || $valeurs === []) {
            return '';
        }

        $morceaux = [];
        foreach ($valeurs as $champ => $valeur) {
            if (is_array($valeur)) {
                $valeur = implode(', ', array_map('strval', $valeur));
            }

            $valeur = trim((string) $valeur);
            $morceaux[] = self::libelleChamp((string) $champ) . ' : ' . ($valeur === '' ? '—' : $valeur);
        }

        return implode(' · ', $morceaux);
    }

    private static function libelleChamp(string $champ): string
    {
        return match ($champ) {
            'roles' => 'rôles',
            'is_admin' => 'administrateur',
            'status' => 'statut',
            'agence_id' => 'agence',
            'full_name' => 'nom',
            'email' => 'courriel',
            'permissions' => 'droits',
            default => str_replace('_', ' ', $champ),
        };
    }

    /**
     * Les personnes qui ont deja pose un geste sur un compte.
     *
     * @return array<int, array{id: int, name: string}>
     */
    public function acteurs(): array
    {
        try {
            $stmt = $this->pdo->prepare('
                SELECT DISTINCT u.id, u.full_name AS name
                FROM lbp_audit_logs a
                JOIN users u ON u.id = a.user_id
                WHERE a.entity_type = :entite
                ORDER BY u.full_name ASC
            ');
            $stmt->execute(['entite' => self::ENTITE]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
