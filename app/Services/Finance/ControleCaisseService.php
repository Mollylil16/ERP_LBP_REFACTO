<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Database;
use PDO;

/**
 * Le contrôle des caisses : où est l'argent, qui l'a compté, qui ne l'a pas
 * compté.
 *
 * Cet écran est né d'une phrase des agences, répétée pendant des semaines :
 * « quand je compte ma caisse le soir, ce n'est pas ce que le logiciel
 * affiche ». Le relevé du 24/09/2026 a donné les trois causes, et cette
 * classe les met chacune sous une colonne :
 *
 *   1. la journée n'a jamais été soumise — 1 316 270 FCFA en un mois, jamais
 *      comptés par personne. C'est le trou le plus large ;
 *   2. le point a été signé en milieu d'après-midi et l'agence a continué
 *      d'encaisser jusqu'à 19 h — 526 500 FCFA entrés après la signature ;
 *   3. l'écart déclaré au comptage, avec son explication.
 *
 * Rien n'est écrit ici : l'écran lit, il ne corrige pas. Une correction se
 * fait par le point de caisse, par l'agence, sous son nom.
 *
 * L'argent est rattaché à l'agence de celui qui l'a encaissé — jamais à
 * l'agence de la facture. Le billet est dans le tiroir où on l'a reçu.
 */
final class ControleCaisseService
{
    /** Périodes de contrôle proposées, en jours. */
    public const FENETRES = [7, 30, 90];

    /** Le tiroir ne contient que des espèces : le reste ne s'y compte pas. */
    private const EST_ESPECES = "LOWER(COALESCE(NULLIF(p.mode, ''), NULLIF(p.mode_paiement, ''), 'especes')) IN ('especes', 'espece', 'cash')";

    /** L'agence qui détient l'argent : celle du collecteur, pas celle de la facture. */
    private const AGENCE_ARGENT = 'COALESCE(p.agence_id, u.agence_id, f.agence_id)';

    public function __construct(private PDO $pdo)
    {
    }

    public static function creer(): self
    {
        return new self(Database::getConnection());
    }

    /**
     * Tout l'écran, en une lecture.
     *
     * @param array<string, mixed> $requete
     * @return array<string, mixed>
     */
    public function tableau(array $requete): array
    {
        $filtres = $this->filtres($requete);
        $depuis = date('Y-m-d', strtotime($filtres['jour'] . ' -' . ($filtres['fenetre'] - 1) . ' days'));

        $caisses = $this->caissesDuJour($filtres['jour'], $filtres['agence_id']);

        return [
            'filtres' => $filtres + ['depuis' => $depuis],
            'agences' => $this->agences(),
            'caisses' => $caisses,
            'totaux' => $this->totaux($caisses),
            'sansPoint' => $this->journeesSansPoint($depuis, $filtres['jour'], $filtres['agence_id']),
            'apresSoumission' => $this->encaisseApresSoumission($depuis, $filtres['jour'], $filtres['agence_id']),
            'ecarts' => $this->ecartsDeclares($depuis, $filtres['jour'], $filtres['agence_id']),
        ];
    }

    /**
     * @param array<string, mixed> $requete
     * @return array{jour:string, fenetre:int, agence_id:int}
     */
    public function filtres(array $requete): array
    {
        $jour = (string) ($requete['jour'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $jour) !== 1) {
            $jour = date('Y-m-d');
        }

        $fenetre = (int) ($requete['fenetre'] ?? 0);
        if (!in_array($fenetre, self::FENETRES, true)) {
            $fenetre = 30;
        }

        return [
            'jour' => $jour,
            'fenetre' => $fenetre,
            'agence_id' => max(0, (int) ($requete['agence_id'] ?? 0)),
        ];
    }

    // ------------------------------------------------------------------
    // 1. Les caisses de la journée
    // ------------------------------------------------------------------

    /**
     * Une ligne par agence : encaissé, compté, écart, état du point.
     *
     * Une agence n'apparaît que si de l'argent y est entré ce jour-là ou si un
     * point y a été ouvert. Les agences au repos ne remplissent pas l'écran.
     *
     * @return array<int, array<string, mixed>>
     */
    public function caissesDuJour(string $jour, int $agenceId = 0): array
    {
        $conditionAgence = $agenceId > 0 ? ' AND s.id = :agence' : '';

        $stmt = $this->pdo->prepare("
            SELECT s.id AS agence_id, s.name AS agence,
                   COALESCE(enc.total, 0) AS encaisse,
                   COALESCE(enc.especes, 0) AS especes,
                   COALESCE(enc.nb, 0) AS operations,
                   enc.dernier_encaissement,
                   e.id AS etat_id,
                   e.statut,
                   e.date_soumission,
                   e.reouvert_le,
                   e.solde_caisse_agence_xof AS theorique,
                   e.solde_physique_declare AS compte,
                   e.ecart_caisse AS ecart,
                   e.explication_ecart
            FROM company_sites s
            LEFT JOIN (
                SELECT " . self::AGENCE_ARGENT . " AS agence_id,
                       SUM(p.montant) AS total,
                       SUM(CASE WHEN " . self::EST_ESPECES . " THEN p.montant ELSE 0 END) AS especes,
                       COUNT(*) AS nb,
                       MAX(p.date_paiement) AS dernier_encaissement
                FROM lbp_paiements p
                JOIN lbp_factures f ON f.id = p.facture_id
                LEFT JOIN users u ON u.id = p.caissiere_id
                WHERE DATE(p.date_paiement) = :jour
                  AND p.devise = 'XOF'
                GROUP BY agence_id
            ) AS enc ON enc.agence_id = s.id
            LEFT JOIN lbp_etats_journaliers e ON e.agence_id = s.id AND e.date_jour = :jour_etat
            WHERE (enc.agence_id IS NOT NULL OR e.id IS NOT NULL)" . $conditionAgence . "
            ORDER BY s.name
        ");

        $parametres = ['jour' => $jour, 'jour_etat' => $jour];
        if ($agenceId > 0) {
            $parametres['agence'] = $agenceId;
        }
        $stmt->execute($parametres);

        $apres = $this->apresSoumissionParAgence($jour);
        $lignes = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $l) {
            $id = (int) $l['agence_id'];
            $compte = $l['compte'] === null ? null : (float) $l['compte'];
            $statut = (string) ($l['statut'] ?? '');
            $soumis = in_array($statut, ['soumis', 'consolide'], true);

            $lignes[] = [
                'agence_id' => $id,
                'agence' => (string) $l['agence'],
                'encaisse' => (float) $l['encaisse'],
                'especes' => (float) $l['especes'],
                'operations' => (int) $l['operations'],
                'dernier_encaissement' => $l['dernier_encaissement'],
                'theorique' => $l['theorique'] === null ? null : (float) $l['theorique'],
                'compte' => $compte,
                'ecart' => $compte === null ? null : (float) $l['ecart'],
                'explication' => trim((string) ($l['explication_ecart'] ?? '')),
                'date_soumission' => $l['date_soumission'],
                'etat' => $this->etat($l, $soumis),
                'compte_sans_comptage' => $soumis && $compte === null,
                'apres_soumission' => (float) ($apres[$id]['total'] ?? 0),
                'operations_apres' => (int) ($apres[$id]['nb'] ?? 0),
            ];
        }

        return $lignes;
    }

    /**
     * L'état d'une caisse, en un mot.
     *
     * @param array<string, mixed> $ligne
     */
    private function etat(array $ligne, bool $soumis): string
    {
        if ($soumis) {
            return (string) $ligne['statut'] === 'consolide' ? 'CONSOLIDE' : 'SOUMIS';
        }

        // Un point rouvert par un encaissement tardif n'est plus un point
        // soumis : la journée attend d'être recomptée.
        if (!empty($ligne['reouvert_le'])) {
            return 'ROUVERT';
        }

        return $ligne['etat_id'] === null ? 'AUCUN_POINT' : 'BROUILLON';
    }

    /**
     * Ce qui est entré en caisse après la signature du point, par agence.
     *
     * @return array<int, array{nb:int, total:float}>
     */
    private function apresSoumissionParAgence(string $jour): array
    {
        $stmt = $this->pdo->prepare("
            SELECT e.agence_id, COUNT(*) AS nb, SUM(p.montant) AS total
            FROM lbp_etats_journaliers e
            JOIN lbp_paiements p ON DATE(p.date_paiement) = e.date_jour
            JOIN lbp_factures f ON f.id = p.facture_id
            LEFT JOIN users u ON u.id = p.caissiere_id
            WHERE e.date_jour = :jour
              AND e.date_soumission IS NOT NULL
              AND p.date_paiement > e.date_soumission
              AND p.devise = 'XOF'
              AND " . self::AGENCE_ARGENT . " = e.agence_id
            GROUP BY e.agence_id
        ");
        $stmt->execute(['jour' => $jour]);

        $parAgence = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $l) {
            $parAgence[(int) $l['agence_id']] = ['nb' => (int) $l['nb'], 'total' => (float) $l['total']];
        }

        return $parAgence;
    }

    /**
     * Le résumé de la journée, tel qu'il s'affiche en haut de l'écran.
     *
     * @param array<int, array<string, mixed>> $caisses
     * @return array<string, mixed>
     */
    public function totaux(array $caisses): array
    {
        $totaux = [
            'encaisse' => 0.0,
            'especes' => 0.0,
            'agences' => 0,
            'soumises' => 0,
            'non_comptes' => 0.0,
            'ecart' => 0.0,
            'apres_soumission' => 0.0,
            'sans_comptage' => 0,
        ];

        foreach ($caisses as $c) {
            $soumise = in_array($c['etat'], ['SOUMIS', 'CONSOLIDE'], true);

            $totaux['encaisse'] += $c['encaisse'];
            $totaux['especes'] += $c['especes'];
            $totaux['apres_soumission'] += $c['apres_soumission'];

            if ($c['operations'] > 0) {
                $totaux['agences']++;
            }
            if ($soumise) {
                $totaux['soumises']++;
            } else {
                // Tant que le point n'est pas soumis, cet argent n'a été
                // compté par personne.
                $totaux['non_comptes'] += $c['encaisse'];
            }
            if ($c['ecart'] !== null) {
                $totaux['ecart'] += $c['ecart'];
            }
            if (!empty($c['compte_sans_comptage'])) {
                $totaux['sans_comptage']++;
            }
        }

        return $totaux;
    }

    // ------------------------------------------------------------------
    // 2. Les journées jamais comptées
    // ------------------------------------------------------------------

    /**
     * Les journées où de l'argent est entré sans qu'aucun point ne soit soumis.
     *
     * @return array<int, array<string, mixed>>
     */
    public function journeesSansPoint(string $depuis, string $au, int $agenceId = 0): array
    {
        $conditionAgence = $agenceId > 0 ? ' AND s.id = :agence' : '';

        $stmt = $this->pdo->prepare("
            SELECT jours.jour, s.id AS agence_id, s.name AS agence,
                   jours.total, jours.nb,
                   CASE WHEN brouillon.id IS NULL THEN 'AUCUN_POINT' ELSE 'BROUILLON' END AS etat,
                   brouillon.created_at AS ouvert_le
            FROM (
                SELECT DATE(p.date_paiement) AS jour,
                       " . self::AGENCE_ARGENT . " AS agence_id,
                       SUM(p.montant) AS total,
                       COUNT(*) AS nb
                FROM lbp_paiements p
                JOIN lbp_factures f ON f.id = p.facture_id
                LEFT JOIN users u ON u.id = p.caissiere_id
                WHERE DATE(p.date_paiement) BETWEEN :depuis AND :au
                  AND p.devise = 'XOF'
                GROUP BY jour, agence_id
            ) AS jours
            JOIN company_sites s ON s.id = jours.agence_id
            LEFT JOIN lbp_etats_journaliers e
                   ON e.agence_id = jours.agence_id
                  AND e.date_jour = jours.jour
                  AND e.statut IN ('soumis', 'consolide')
            LEFT JOIN lbp_etats_journaliers brouillon
                   ON brouillon.agence_id = jours.agence_id
                  AND brouillon.date_jour = jours.jour
            WHERE e.id IS NULL" . $conditionAgence . "
            ORDER BY jours.jour DESC, jours.total DESC
            LIMIT 60
        ");

        $parametres = ['depuis' => $depuis, 'au' => $au];
        if ($agenceId > 0) {
            $parametres['agence'] = $agenceId;
        }
        $stmt->execute($parametres);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ------------------------------------------------------------------
    // 3. Les points signés trop tôt
    // ------------------------------------------------------------------

    /**
     * Les journées dont le point a été signé avant le dernier encaissement.
     *
     * @return array<int, array<string, mixed>>
     */
    public function encaisseApresSoumission(string $depuis, string $au, int $agenceId = 0): array
    {
        $conditionAgence = $agenceId > 0 ? ' AND e.agence_id = :agence' : '';

        $stmt = $this->pdo->prepare("
            SELECT e.date_jour, e.agence_id, s.name AS agence,
                   e.date_soumission,
                   e.solde_caisse_agence_xof AS solde_soumis,
                   COUNT(p.id) AS nb_apres,
                   COALESCE(SUM(p.montant), 0) AS montant_apres,
                   MAX(p.date_paiement) AS dernier_encaissement
            FROM lbp_etats_journaliers e
            LEFT JOIN company_sites s ON s.id = e.agence_id
            JOIN lbp_paiements p ON DATE(p.date_paiement) = e.date_jour
            JOIN lbp_factures f ON f.id = p.facture_id
            LEFT JOIN users u ON u.id = p.caissiere_id
            WHERE e.date_jour BETWEEN :depuis AND :au
              AND e.date_soumission IS NOT NULL
              AND p.date_paiement > e.date_soumission
              AND p.devise = 'XOF'
              AND " . self::AGENCE_ARGENT . " = e.agence_id" . $conditionAgence . "
            GROUP BY e.id, e.date_jour, e.agence_id, s.name, e.date_soumission, e.solde_caisse_agence_xof
            ORDER BY montant_apres DESC
            LIMIT 30
        ");

        $parametres = ['depuis' => $depuis, 'au' => $au];
        if ($agenceId > 0) {
            $parametres['agence'] = $agenceId;
        }
        $stmt->execute($parametres);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ------------------------------------------------------------------
    // 4. Les écarts déclarés au comptage
    // ------------------------------------------------------------------

    /**
     * Les comptages physiques qui ne tombent pas sur l'attendu, les plus
     * lourds d'abord.
     *
     * @return array<int, array<string, mixed>>
     */
    public function ecartsDeclares(string $depuis, string $au, int $agenceId = 0): array
    {
        $conditionAgence = $agenceId > 0 ? ' AND e.agence_id = :agence' : '';

        $stmt = $this->pdo->prepare("
            SELECT e.date_jour, e.agence_id, s.name AS agence,
                   e.solde_caisse_agence_xof AS theorique,
                   e.solde_physique_declare AS compte,
                   e.ecart_caisse AS ecart,
                   e.explication_ecart,
                   e.statut
            FROM lbp_etats_journaliers e
            LEFT JOIN company_sites s ON s.id = e.agence_id
            WHERE e.date_jour BETWEEN :depuis AND :au
              AND e.solde_physique_declare IS NOT NULL
              AND e.ecart_caisse <> 0" . $conditionAgence . "
            ORDER BY ABS(e.ecart_caisse) DESC, e.date_jour DESC
            LIMIT 30
        ");

        $parametres = ['depuis' => $depuis, 'au' => $au];
        if ($agenceId > 0) {
            $parametres['agence'] = $agenceId;
        }
        $stmt->execute($parametres);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int, array<string, mixed>> */
    public function agences(): array
    {
        return $this->pdo
            ->query('SELECT id, name FROM company_sites ORDER BY name')
            ->fetchAll(PDO::FETCH_ASSOC);
    }
}
