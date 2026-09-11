<?php

declare(strict_types=1);

namespace App\Repositories\Entrepots;

use PDO;
use Throwable;

/**
 * Lecture de l'occupation physique des magasins.
 *
 * Le module Logistique suit le flux (réception, groupage, expédition) ; celui-ci
 * regarde ce qui dort dans les rayons et depuis combien de temps, parce que
 * c'est cela qui immobilise de la place et déclenche le gardiennage facturable.
 *
 * Aucune écriture : les mouvements de rayon sont saisis dans Logistique et dans
 * le Call Center, et doivent le rester pour garder un seul point de saisie.
 */
class EntrepotsDashboardRepository extends \App\Repositories\Shared\ModuleDashboardRepository
{
    /**
     * Position actuelle d'un colis : sa dernière ligne de mouvement. Elle n'est
     * retenue que si ce n'est pas une sortie, un colis sorti n'occupant plus de
     * place.
     */
    private const DERNIER_MOUVEMENT = "
        INNER JOIN (
            SELECT colis_id, MAX(id) AS dernier_id
            FROM logistique_mouvements_rayon
            GROUP BY colis_id
        ) d ON d.dernier_id = m.id
    ";

    /**
     * @return array<string,mixed>
     */
    public function dashboard(): array
    {
        return $this->dashboardFor('entrepots');
    }

    /**
     * Délai de gratuité et tarif de gardiennage : la règle de l'agence si elle
     * est paramétrée, sinon la règle générale (agence_id NULL).
     *
     * @return array{delai_gratuit_jours:int, frais_gardiennage_par_jour:float}
     */
    public function parametres(?int $agenceId): array
    {
        $defaut = ['delai_gratuit_jours' => 7, 'frais_gardiennage_par_jour' => 500.0];

        try {
            $stmt = $this->pdo->prepare("
                SELECT delai_gratuit_jours, frais_gardiennage_par_jour
                FROM logistique_settings
                WHERE agence_id = :agence OR agence_id IS NULL
                ORDER BY (agence_id IS NULL) ASC
                LIMIT 1
            ");
            $stmt->execute(['agence' => $agenceId]);
            $ligne = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return $defaut;
        }

        if (!$ligne) {
            return $defaut;
        }

        return [
            'delai_gratuit_jours' => (int) $ligne['delai_gratuit_jours'],
            'frais_gardiennage_par_jour' => (float) $ligne['frais_gardiennage_par_jour'],
        ];
    }

    /**
     * Rayons avec leur taux de remplissage réel.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rayons(?int $agenceId): array
    {
        $filtre = $agenceId !== null ? ' WHERE r.agence_id = :agence' : '';

        $sql = "
            SELECT
                r.id,
                r.code_rayon,
                r.nom_rayon,
                r.capacite_max,
                r.statut,
                COALESCE(s.name, CONCAT('Agence #', r.agence_id)) AS agence_name,
                COALESCE(occ.nb_colis, 0) AS nb_colis,
                COALESCE(occ.poids_kg, 0) AS poids_kg
            FROM logistique_rayons r
            LEFT JOIN company_sites s ON s.id = r.agence_id
            LEFT JOIN (
                SELECT m.rayon_id,
                       COUNT(*) AS nb_colis,
                       SUM(c.poids_total) AS poids_kg
                FROM logistique_mouvements_rayon m
                " . self::DERNIER_MOUVEMENT . "
                INNER JOIN lbp_colis c ON c.id = m.colis_id
                WHERE m.type_mouvement <> 'SORTIE'
                  AND m.rayon_id IS NOT NULL
                  AND c.statut NOT IN ('LIVRÉ', 'RETIRÉ')
                GROUP BY m.rayon_id
            ) occ ON occ.rayon_id = r.id
            {$filtre}
            ORDER BY agence_name ASC, r.code_rayon ASC
        ";

        return $this->lignes($sql, $agenceId !== null ? ['agence' => $agenceId] : []);
    }

    /**
     * Colis encore en rayon, du plus ancien au plus récent : le haut de cette
     * liste est ce qui coûte de la place.
     *
     * @return array<int, array<string, mixed>>
     */
    public function colisStockes(?int $agenceId, int $limite = 200): array
    {
        $limite = max(1, min($limite, 500));
        $filtre = $agenceId !== null ? ' AND r.agence_id = :agence' : '';

        $sql = "
            SELECT
                c.id,
                c.numero_tracking,
                c.statut,
                c.poids_total,
                c.nombre_colis,
                c.montant_total,
                c.devise,
                expe.name AS expediteur,
                dest.name AS destinataire,
                dest.phone AS destinataire_tel,
                r.code_rayon,
                r.nom_rayon,
                s.name AS agence_name,
                m.created_at AS entre_le,
                DATEDIFF(NOW(), m.created_at) AS jours_stockes
            FROM logistique_mouvements_rayon m
            " . self::DERNIER_MOUVEMENT . "
            INNER JOIN lbp_colis c ON c.id = m.colis_id
            LEFT JOIN lbp_clients expe ON expe.id = c.expediteur_id
            LEFT JOIN lbp_clients dest ON dest.id = c.destinataire_id
            LEFT JOIN logistique_rayons r ON r.id = m.rayon_id
            LEFT JOIN company_sites s ON s.id = r.agence_id
            WHERE m.type_mouvement <> 'SORTIE'
              AND c.statut NOT IN ('LIVRÉ', 'RETIRÉ')
              {$filtre}
            ORDER BY m.created_at ASC
            LIMIT {$limite}
        ";

        return $this->lignes($sql, $agenceId !== null ? ['agence' => $agenceId] : []);
    }

    /**
     * Derniers mouvements, pour voir l'activité du magasin.
     *
     * @return array<int, array<string, mixed>>
     */
    public function mouvementsRecents(?int $agenceId, int $limite = 25): array
    {
        $limite = max(1, min($limite, 200));
        $filtre = $agenceId !== null ? ' WHERE r.agence_id = :agence' : '';

        $sql = "
            SELECT
                m.id,
                m.type_mouvement,
                m.commentaires,
                m.created_at,
                c.numero_tracking,
                r.code_rayon,
                r.nom_rayon,
                s.name AS agence_name,
                u.full_name AS auteur
            FROM logistique_mouvements_rayon m
            LEFT JOIN lbp_colis c ON c.id = m.colis_id
            LEFT JOIN logistique_rayons r ON r.id = m.rayon_id
            LEFT JOIN company_sites s ON s.id = r.agence_id
            LEFT JOIN users u ON u.id = m.effectue_par
            {$filtre}
            ORDER BY m.id DESC
            LIMIT {$limite}
        ";

        return $this->lignes($sql, $agenceId !== null ? ['agence' => $agenceId] : []);
    }

    /**
     * Inventaires récents et leur avancement.
     *
     * @return array<int, array<string, mixed>>
     */
    public function inventaires(?int $agenceId, int $limite = 10): array
    {
        $limite = max(1, min($limite, 100));
        $filtre = $agenceId !== null ? ' WHERE i.agence_id = :agence' : '';

        $sql = "
            SELECT
                i.id,
                i.date_inventaire,
                i.statut,
                i.commentaires,
                s.name AS agence_name,
                u.full_name AS auteur,
                COALESCE(l.nb_lignes, 0) AS nb_lignes,
                COALESCE(l.nb_manquants, 0) AS nb_manquants
            FROM lbp_inventaires i
            LEFT JOIN company_sites s ON s.id = i.agence_id
            LEFT JOIN users u ON u.id = i.cree_par
            LEFT JOIN (
                SELECT inventaire_id,
                       COUNT(*) AS nb_lignes,
                       SUM(CASE WHEN etat <> 'PRÉSENT' THEN 1 ELSE 0 END) AS nb_manquants
                FROM lbp_inventaire_lignes
                GROUP BY inventaire_id
            ) l ON l.inventaire_id = i.id
            {$filtre}
            ORDER BY i.date_inventaire DESC
            LIMIT {$limite}
        ";

        return $this->lignes($sql, $agenceId !== null ? ['agence' => $agenceId] : []);
    }

    /**
     * @param array<string, mixed> $parametres
     * @return array<int, array<string, mixed>>
     */
    private function lignes(string $sql, array $parametres): array
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($parametres);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            /*
             * Une table absente ne doit pas faire tomber tout le tableau de bord :
             * la section concernée s'affiche vide, les autres restent lisibles.
             * Mais une requête fautive produit exactement le même silence. On la
             * trace, sinon un tableau vide se lit comme « aucune donnée » alors
             * que la requête n'est jamais partie.
             */
            error_log('[LBP] Lecture impossible dans ' . static::class . ' : ' . $e->getMessage());

            return [];
        }
    }
}
