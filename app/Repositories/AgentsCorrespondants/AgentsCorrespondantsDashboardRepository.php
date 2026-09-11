<?php

declare(strict_types=1);

namespace App\Repositories\AgentsCorrespondants;

use PDO;
use Throwable;

/**
 * Annuaire des correspondants à l'étranger.
 *
 * La table international_agents existait depuis l'origine sans qu'aucun écran ne
 * la lise ni ne l'alimente. C'est le seul des six modules à écrire : les
 * correspondants n'ont d'existence nulle part ailleurs dans l'ERP, il faut donc
 * bien un endroit pour les saisir.
 */
class AgentsCorrespondantsDashboardRepository extends \App\Repositories\Shared\ModuleDashboardRepository
{
    /**
     * @return array<string,mixed>
     */
    public function dashboard(): array
    {
        return $this->dashboardFor('agents-correspondants');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function agents(string $recherche = '', bool $inactifsInclus = true): array
    {
        $conditions = [];
        $parametres = [];

        if ($recherche !== '') {
            $conditions[] = '(a.name LIKE :q OR a.country LIKE :q OR a.city LIKE :q OR a.contact_name LIKE :q OR a.coverage LIKE :q)';
            $parametres['q'] = '%' . $recherche . '%';
        }

        if (!$inactifsInclus) {
            $conditions[] = 'a.is_active = 1';
        }

        $where = $conditions !== [] ? ' WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "
            SELECT a.id, a.name, a.country, a.city, a.contact_name, a.email, a.phone,
                   a.coverage, a.is_active, a.created_at, a.updated_at
            FROM international_agents a
            {$where}
            ORDER BY a.is_active DESC, a.country ASC, a.name ASC
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($parametres);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            /*
             * Une table absente ne doit pas faire tomber tout l'ecran : la
             * section concernee s'affiche vide et les autres restent lisibles.
             * Mais une requete fautive produit exactement le meme silence. On la
             * trace, sinon un tableau vide se lit comme « aucune donnee » alors
             * que la requete n'est jamais partie.
             */
            error_log('[LBP] Lecture impossible dans ' . static::class . ' : ' . $e->getMessage());

            return [];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function trouver(int $id): ?array
    {
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM international_agents WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $ligne = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return null;
        }

        return $ligne !== false ? $ligne : null;
    }

    /**
     * @param array<string, mixed> $donnees
     */
    public function creer(array $donnees): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO international_agents (name, country, city, contact_name, email, phone, coverage, is_active)
            VALUES (:name, :country, :city, :contact_name, :email, :phone, :coverage, :is_active)
        ");
        $stmt->execute($this->champs($donnees));

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $donnees
     */
    public function modifier(int $id, array $donnees): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE international_agents
            SET name = :name, country = :country, city = :city, contact_name = :contact_name,
                email = :email, phone = :phone, coverage = :coverage, is_active = :is_active,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute($this->champs($donnees) + ['id' => $id]);
    }

    /**
     * Désactive plutôt que supprimer : un correspondant retiré du réseau reste
     * une information utile sur les dossiers passés.
     */
    public function desactiver(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE international_agents SET is_active = 0, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /**
     * Répartition du réseau par pays.
     *
     * @return array<int, array<string, mixed>>
     */
    public function parPays(): array
    {
        try {
            $stmt = $this->pdo->query("
                SELECT country,
                       COUNT(*) AS nb_agents,
                       SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS nb_actifs,
                       COUNT(DISTINCT city) AS nb_villes
                FROM international_agents
                GROUP BY country
                ORDER BY nb_agents DESC, country ASC
            ");

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            /*
             * Une table absente ne doit pas faire tomber tout l'ecran : la
             * section concernee s'affiche vide et les autres restent lisibles.
             * Mais une requete fautive produit exactement le meme silence. On la
             * trace, sinon un tableau vide se lit comme « aucune donnee » alors
             * que la requete n'est jamais partie.
             */
            error_log('[LBP] Lecture impossible dans ' . static::class . ' : ' . $e->getMessage());

            return [];
        }
    }

    /**
     * @param array<string, mixed> $donnees
     * @return array<string, mixed>
     */
    private function champs(array $donnees): array
    {
        return [
            'name' => (string) $donnees['name'],
            'country' => (string) $donnees['country'],
            'city' => self::ouNull($donnees['city'] ?? ''),
            'contact_name' => self::ouNull($donnees['contact_name'] ?? ''),
            'email' => self::ouNull($donnees['email'] ?? ''),
            'phone' => self::ouNull($donnees['phone'] ?? ''),
            'coverage' => self::ouNull($donnees['coverage'] ?? ''),
            'is_active' => !empty($donnees['is_active']) ? 1 : 0,
        ];
    }

    private static function ouNull(mixed $valeur): ?string
    {
        $texte = trim((string) $valeur);

        return $texte !== '' ? $texte : null;
    }
}
