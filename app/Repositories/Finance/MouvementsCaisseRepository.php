<?php

declare(strict_types=1);

namespace App\Repositories\Finance;

use PDO;
use Throwable;

/**
 * Les entrées et les sorties d'un tiroir, lues là où elles vivent déjà.
 *
 * Rien n'est recopié ici : un règlement de facture vit dans lbp_paiements, un
 * approvisionnement dans lbp_appros_caisse, un décaissement dans
 * lbp_demandes_fonds. Seul ce que LBP ne connaît pas est saisi à la main, dans
 * lbp_mouvements_caisse. Le dépôt lit les quatre sources séparément et laisse
 * au service le soin de les mettre en forme : une seule requête qui les
 * réunirait obligerait à un UNION dont les colonnes mentent de l'une à l'autre.
 *
 * La classe n'est pas finale : les tests la dérivent pour fournir des lignes
 * sans base de données.
 */
class MouvementsCaisseRepository
{
    /**
     * Garde-fou de volume. Une journée de caisse compte quelques dizaines de
     * lignes ; la borne protège d'une date absurde ou d'une base de test
     * gonflée, sans jamais tronquer une journée réelle.
     */
    private const PLAFOND = 2000;

    /** @var array<string, bool> */
    private array $tables = [];

    public function __construct(protected PDO $pdo)
    {
    }

    // ------------------------------------------------------------------
    // Référentiel
    // ------------------------------------------------------------------

    /**
     * Les caisses ouvertes, nommées, avec leur solde courant.
     *
     * @return array<int, array<string, mixed>>
     */
    public function caisses(int $agenceId = 0): array
    {
        $conditions = ['k.actif = 1'];
        $parametres = [];

        if ($agenceId > 0) {
            $conditions[] = 'k.agency_id = :agence';
            $parametres['agence'] = $agenceId;
        }

        return $this->lignes('
            SELECT k.id, k.nom, k.code, k.type, k.balance AS solde,
                   k.agency_id AS agence_id, s.name AS agence
            FROM lbp_caisses k
            LEFT JOIN company_sites s ON s.id = k.agency_id
            WHERE ' . implode(' AND ', $conditions) . '
            ORDER BY s.name ASC, k.nom ASC, k.id ASC
        ', $parametres);
    }

    /** @return array<string, mixed>|null */
    public function caisse(int $id): ?array
    {
        $lignes = $this->lignes(
            'SELECT k.id, k.nom, k.type, k.actif, k.agency_id AS agence_id, s.name AS agence
             FROM lbp_caisses k
             LEFT JOIN company_sites s ON s.id = k.agency_id
             WHERE k.id = :id LIMIT 1',
            ['id' => $id]
        );

        return $lignes[0] ?? null;
    }

    /** @return array<int, array{id: int, name: string}> */
    public function agences(): array
    {
        $lignes = $this->lignes('SELECT id, name FROM company_sites WHERE is_active = 1 ORDER BY name ASC');

        return array_map(
            static fn (array $l): array => ['id' => (int) $l['id'], 'name' => (string) $l['name']],
            $lignes
        );
    }

    // ------------------------------------------------------------------
    // Entrées
    // ------------------------------------------------------------------

    /**
     * Les règlements de factures du jour.
     *
     * Aucun filtre sur `mode_paiement` : seule `mode` est réellement écrite à
     * l'encaissement (PaiementRepository::create), l'autre colonne reste à
     * 'ESPECES' pour tout le monde et masquerait les trois quarts de la
     * journée.
     *
     * L'agence retenue est celle où le billet a été pris, pas celle de la
     * facture : un client qui règle à Dokui une facture d'Adjamé laisse son
     * argent à Dokui.
     *
     * @param array<string, mixed> $portee
     * @return array<int, array<string, mixed>>
     */
    public function paiements(string $jour, array $portee): array
    {
        $conditions = ['DATE(p.date_paiement) = :jour'];
        $parametres = ['jour' => $jour];

        if ((int) ($portee['agence_id'] ?? 0) > 0) {
            $conditions[] = 'COALESCE(p.agence_id, f.agence_id) = :agence';
            $parametres['agence'] = (int) $portee['agence_id'];
        }

        $recherche = (string) ($portee['q'] ?? '');
        if ($recherche !== '') {
            // Un paramètre nommé ne peut pas servir deux fois : PDO tourne ici
            // sans emulated prepares.
            $conditions[] = '(f.numero_facture LIKE :q1 OR cl.name LIKE :q2 OR c.numero_tracking LIKE :q3)';
            $parametres['q1'] = '%' . $recherche . '%';
            $parametres['q2'] = '%' . $recherche . '%';
            $parametres['q3'] = '%' . $recherche . '%';
        }

        return $this->lignes('
            SELECT p.id,
                   p.montant,
                   p.devise,
                   p.mode,
                   p.date_paiement,
                   f.numero_facture,
                   COALESCE(p.agence_id, f.agence_id) AS agence_id,
                   s.name AS agence,
                   cl.name AS tiers,
                   u.full_name AS caissier,
                   c.numero_tracking,
                   ' . $this->expressionDossier() . ' AS dossier
            FROM lbp_paiements p
            JOIN lbp_factures f ON f.id = p.facture_id
            LEFT JOIN lbp_colis c ON c.id = f.colis_id
            LEFT JOIN lbp_clients cl ON cl.id = f.client_id
            LEFT JOIN company_sites s ON s.id = COALESCE(p.agence_id, f.agence_id)
            LEFT JOIN users u ON u.id = p.caissiere_id
            WHERE ' . implode(' AND ', $conditions) . '
            ORDER BY p.date_paiement ASC, p.id ASC
            LIMIT ' . self::PLAFOND . '
        ', $parametres);
    }

    /**
     * Les approvisionnements validés dont la date de remise tombe ce jour-là.
     *
     * Un appro annoncé n'est pas un appro remis : seul le validé entre en
     * caisse, comme dans l'attendu du point de caisse.
     *
     * @param array<string, mixed> $portee
     * @return array<int, array<string, mixed>>
     */
    public function approsValides(string $jour, array $portee): array
    {
        $conditions = ["a.statut = 'validee'", 'a.date_effet = :jour'];
        $parametres = ['jour' => $jour];

        if ((int) ($portee['agence_id'] ?? 0) > 0) {
            $conditions[] = 'a.agence_id = :agence';
            $parametres['agence'] = (int) $portee['agence_id'];
        }

        $recherche = (string) ($portee['q'] ?? '');
        if ($recherche !== '') {
            $conditions[] = '(a.numero LIKE :q1 OR a.motif LIKE :q2)';
            $parametres['q1'] = '%' . $recherche . '%';
            $parametres['q2'] = '%' . $recherche . '%';
        }

        return $this->lignes('
            SELECT a.id, a.numero, a.montant, a.devise, a.motif, a.source,
                   a.date_effet, a.agence_id, s.name AS agence, u.full_name AS caissier
            FROM lbp_appros_caisse a
            LEFT JOIN company_sites s ON s.id = a.agence_id
            LEFT JOIN users u ON u.id = a.validateur_id
            WHERE ' . implode(' AND ', $conditions) . '
            ORDER BY a.id ASC
            LIMIT ' . self::PLAFOND . '
        ', $parametres);
    }

    /**
     * Le cumul depuis l origine, par devise.
     *
     * L ecran affichait sous « Depuis l origine » exactement les chiffres de
     * la journee : le meme nombre apparaissait deux fois sous deux libelles
     * dont l un etait faux. Sur l ancien logiciel ce bloc portait 6,4
     * milliards, c est-a-dire tout l historique.
     *
     * Ce sont des agregats et non des listes : pas de plafond de lignes, donc
     * pas de total tronque presente comme complet. La recherche libre ne s y
     * applique pas — un cumul depuis l origine ne se filtre pas par mot-cle.
     *
     * @param array<string, mixed> $portee
     * @return array{versements: float, retraits: float, versements_eur: float, retraits_eur: float}
     */
    public function cumuls(array $portee): array
    {
        $agence = (int) ($portee['agence_id'] ?? 0);

        $somme = function (string $sql, array $parametres): array {
            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($parametres);
                $ligne = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

                return [
                    'xof' => (float) ($ligne['xof'] ?? 0),
                    'eur' => (float) ($ligne['eur'] ?? 0),
                ];
            } catch (\Throwable $e) {
                error_log('[MouvementsCaisseRepository] cumuls : ' . $e->getMessage());

                return ['xof' => 0.0, 'eur' => 0.0];
            }
        };

        $bornePaiements = $agence > 0 ? ' AND COALESCE(p.agence_id, f.agence_id) = :agence' : '';
        $borneAppros = $agence > 0 ? ' AND a.agence_id = :agence' : '';
        $borneDemandes = $agence > 0 ? ' AND df.agence_id = :agence' : '';
        $borneMvt = $agence > 0 ? ' AND m.agence_id = :agence' : '';
        $params = $agence > 0 ? ['agence' => $agence] : [];

        $paiements = $somme('
            SELECT COALESCE(SUM(CASE WHEN UPPER(p.devise) = \'EUR\' THEN 0 ELSE p.montant END), 0) AS xof,
                   COALESCE(SUM(CASE WHEN UPPER(p.devise) = \'EUR\' THEN p.montant ELSE 0 END), 0) AS eur
            FROM lbp_paiements p
            LEFT JOIN lbp_factures f ON f.id = p.facture_id
            WHERE 1 = 1' . $bornePaiements . '
        ', $params);

        $appros = $somme('
            SELECT COALESCE(SUM(CASE WHEN UPPER(a.devise) = \'EUR\' THEN 0 ELSE a.montant END), 0) AS xof,
                   COALESCE(SUM(CASE WHEN UPPER(a.devise) = \'EUR\' THEN a.montant ELSE 0 END), 0) AS eur
            FROM lbp_appros_caisse a
            WHERE a.statut = \'validee\'' . $borneAppros . '
        ', $params);

        $entreesSaisies = $somme('
            SELECT COALESCE(SUM(CASE WHEN UPPER(m.devise) = \'EUR\' THEN 0 ELSE m.amount END), 0) AS xof,
                   COALESCE(SUM(CASE WHEN UPPER(m.devise) = \'EUR\' THEN m.amount ELSE 0 END), 0) AS eur
            FROM lbp_mouvements_caisse m
            WHERE m.type = \'ENTREE\' AND m.annule_le IS NULL' . $borneMvt . '
        ', $params);

        $demandes = $somme('
            SELECT COALESCE(SUM(CASE WHEN UPPER(df.devise) = \'EUR\' THEN 0 ELSE df.montant END), 0) AS xof,
                   COALESCE(SUM(CASE WHEN UPPER(df.devise) = \'EUR\' THEN df.montant ELSE 0 END), 0) AS eur
            FROM lbp_demandes_fonds df
            WHERE df.statut IN (\'decaissee\', \'imputee\')' . $borneDemandes . '
        ', $params);

        $sortiesSaisies = $somme('
            SELECT COALESCE(SUM(CASE WHEN UPPER(m.devise) = \'EUR\' THEN 0 ELSE m.amount END), 0) AS xof,
                   COALESCE(SUM(CASE WHEN UPPER(m.devise) = \'EUR\' THEN m.amount ELSE 0 END), 0) AS eur
            FROM lbp_mouvements_caisse m
            WHERE m.type = \'DECAISSEMENT\' AND m.annule_le IS NULL' . $borneMvt . '
        ', $params);

        return [
            'versements' => $paiements['xof'] + $appros['xof'] + $entreesSaisies['xof'],
            'retraits' => $demandes['xof'] + $sortiesSaisies['xof'],
            'versements_eur' => $paiements['eur'] + $appros['eur'] + $entreesSaisies['eur'],
            'retraits_eur' => $demandes['eur'] + $sortiesSaisies['eur'],
        ];
    }

    // ------------------------------------------------------------------
    // Sorties
    // ------------------------------------------------------------------

    /**
     * Les demandes de fonds sorties de caisse ce jour-là.
     *
     * Les deux statuts comptent : une demande imputée a bien quitté le tiroir
     * le jour du décaissement, et l'imputation qui suit — souvent le lendemain
     * — ne doit pas la faire disparaître de la journée où l'argent est sorti.
     *
     * @param array<int, string> $statuts
     * @param array<string, mixed> $portee
     * @return array<int, array<string, mixed>>
     */
    public function demandesDecaissees(string $jour, array $portee, array $statuts): array
    {
        $statuts = array_values(array_filter($statuts, static fn (string $s): bool => $s !== ''));

        if ($statuts === []) {
            return [];
        }

        $jetons = [];
        $parametres = ['jour' => $jour];

        foreach ($statuts as $rang => $statut) {
            $jeton = 'statut' . $rang;
            $jetons[] = ':' . $jeton;
            $parametres[$jeton] = $statut;
        }

        $conditions = [
            'df.statut IN (' . implode(', ', $jetons) . ')',
            'df.date_decaissement IS NOT NULL',
            'DATE(df.date_decaissement) = :jour',
        ];

        if ((int) ($portee['agence_id'] ?? 0) > 0) {
            $conditions[] = 'df.agence_id = :agence';
            $parametres['agence'] = (int) $portee['agence_id'];
        }

        $recherche = (string) ($portee['q'] ?? '');
        if ($recherche !== '') {
            $conditions[] = '(df.numero_demande LIKE :q1 OR df.motif LIKE :q2'
                . ' OR df.dossier_num LIKE :q3 OR dem.full_name LIKE :q4)';
            $parametres['q1'] = '%' . $recherche . '%';
            $parametres['q2'] = '%' . $recherche . '%';
            $parametres['q3'] = '%' . $recherche . '%';
            $parametres['q4'] = '%' . $recherche . '%';
        }

        return $this->lignes('
            SELECT df.id, df.numero_demande, df.montant, df.devise, df.motif,
                   df.cadre, df.dossier_num, df.date_decaissement, df.mode_paiement,
                   df.agence_id, s.name AS agence,
                   cai.full_name AS caissier, dem.full_name AS tiers
            FROM lbp_demandes_fonds df
            LEFT JOIN company_sites s ON s.id = df.agence_id
            LEFT JOIN users cai ON cai.id = df.caissiere_id
            LEFT JOIN users dem ON dem.id = df.demandeur_id
            WHERE ' . implode(' AND ', $conditions) . '
            ORDER BY df.date_decaissement ASC, df.id ASC
            LIMIT ' . self::PLAFOND . '
        ', $parametres);
    }

    // ------------------------------------------------------------------
    // Saisie manuelle
    // ------------------------------------------------------------------

    /**
     * Les mouvements saisis à la main, d'un type et d'un jour.
     *
     * Le type 'APPRO' de l'ENUM n'est jamais lu ici : les approvisionnements
     * sont pris dans lbp_appros_caisse. Les accepter des deux côtés doublerait
     * chaque remise du siège.
     *
     * Les lignes annulées sortent du tableau et des cumuls, mais restent en
     * base : un mouvement d'argent effacé ne se retrouve plus.
     *
     * @param array<string, mixed> $portee
     * @return array<int, array<string, mixed>>
     */
    public function mouvementsSaisis(string $jour, string $type, array $portee): array
    {
        if (!in_array($type, ['ENTREE', 'DECAISSEMENT'], true)) {
            return [];
        }

        $conditions = [
            'm.annule_le IS NULL',
            'm.type = :type',
            'COALESCE(m.date_mouvement, DATE(m.created_at)) = :jour',
        ];
        $parametres = ['type' => $type, 'jour' => $jour];

        if ((int) ($portee['caisse_id'] ?? 0) > 0) {
            $conditions[] = 'm.caisse_id = :caisse';
            $parametres['caisse'] = (int) $portee['caisse_id'];
        }

        if ((int) ($portee['agence_id'] ?? 0) > 0) {
            $conditions[] = 'COALESCE(m.agence_id, k.agency_id) = :agence';
            $parametres['agence'] = (int) $portee['agence_id'];
        }

        $recherche = (string) ($portee['q'] ?? '');
        if ($recherche !== '') {
            $conditions[] = '(m.libelle LIKE :q1 OR m.justification LIKE :q2'
                . ' OR m.reference LIKE :q3 OR m.tiers LIKE :q4 OR m.dossier_numero LIKE :q5)';
            $parametres['q1'] = '%' . $recherche . '%';
            $parametres['q2'] = '%' . $recherche . '%';
            $parametres['q3'] = '%' . $recherche . '%';
            $parametres['q4'] = '%' . $recherche . '%';
            $parametres['q5'] = '%' . $recherche . '%';
        }

        return $this->lignes('
            SELECT m.id, m.caisse_id, m.type, m.amount AS montant, m.devise, m.cadre,
                   m.dossier_numero, COALESCE(NULLIF(m.libelle, \'\'), m.justification) AS libelle,
                   m.reference, m.mode_reglement, m.tiers,
                   COALESCE(m.agence_id, k.agency_id) AS agence_id,
                   COALESCE(m.date_mouvement, DATE(m.created_at)) AS date_mouvement,
                   m.created_at, k.nom AS caisse, s.name AS agence, u.full_name AS caissier
            FROM lbp_mouvements_caisse m
            LEFT JOIN lbp_caisses k ON k.id = m.caisse_id
            LEFT JOIN company_sites s ON s.id = COALESCE(m.agence_id, k.agency_id)
            LEFT JOIN users u ON u.id = m.recorded_by
            WHERE ' . implode(' AND ', $conditions) . '
            ORDER BY m.id ASC
            LIMIT ' . self::PLAFOND . '
        ', $parametres);
    }

    /**
     * Un mouvement saisi, encore vivant. Rend null pour tout le reste — et un
     * identifiant de paiement ou d'appro tombe précisément dans ce « reste ».
     *
     * @return array<string, mixed>|null
     */
    public function mouvementSaisi(int $id): ?array
    {
        $lignes = $this->lignes('
            SELECT m.id, m.caisse_id, m.type, m.amount AS montant, m.devise, m.cadre,
                   m.dossier_numero, m.libelle, m.reference, m.tiers,
                   COALESCE(m.agence_id, k.agency_id) AS agence_id,
                   COALESCE(m.date_mouvement, DATE(m.created_at)) AS date_mouvement,
                   k.nom AS caisse
            FROM lbp_mouvements_caisse m
            LEFT JOIN lbp_caisses k ON k.id = m.caisse_id
            WHERE m.id = :id AND m.annule_le IS NULL
            LIMIT 1
        ', ['id' => $id]);

        return $lignes[0] ?? null;
    }

    /**
     * Cette référence est-elle déjà connue de LBP ?
     *
     * Rend le nom de l'écran où la pièce vit déjà, ou null. C'est le garde-fou
     * de la ressaisie : un règlement de facture entre en caisse tout seul, le
     * retaper ici le compterait deux fois.
     */
    public function referenceDejaConnue(string $reference): ?string
    {
        $reference = trim($reference);

        if ($reference === '') {
            return null;
        }

        $pistes = [
            'une facture' => 'SELECT 1 FROM lbp_factures WHERE numero_facture = :reference LIMIT 1',
            'un approvisionnement de caisse' => 'SELECT 1 FROM lbp_appros_caisse WHERE numero = :reference LIMIT 1',
            'une demande de fonds' => 'SELECT 1 FROM lbp_demandes_fonds WHERE numero_demande = :reference LIMIT 1',
        ];

        foreach ($pistes as $ou => $sql) {
            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute(['reference' => $reference]);

                if ($stmt->fetchColumn() !== false) {
                    return $ou;
                }
            } catch (Throwable $e) {
                // Une table absente ne doit pas bloquer une saisie légitime.
                continue;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $valeurs
     */
    public function enregistrerMouvement(array $valeurs): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO lbp_mouvements_caisse
                (caisse_id, type, amount, devise, cadre, dossier_numero, libelle, justification,
                 reference, mode_reglement, tiers, agence_id, date_mouvement, recorded_by, created_at)
            VALUES
                (:caisse_id, :type, :montant, :devise, :cadre, :dossier_numero, :libelle, :libelle_court,
                 :reference, :mode_reglement, :tiers, :agence_id, :date_mouvement, :recorded_by, NOW())
        ');

        $stmt->execute([
            'caisse_id' => (int) $valeurs['caisse_id'],
            'type' => (string) $valeurs['type'],
            'montant' => (float) $valeurs['montant'],
            'devise' => (string) $valeurs['devise'],
            'cadre' => (string) $valeurs['cadre'],
            'dossier_numero' => $valeurs['dossier_numero'] ?? null,
            'libelle' => (string) $valeurs['libelle'],
            // `justification` existait avant cet écran : la garder alignée évite
            // qu'un ancien rapport affiche une ligne sans intitulé.
            'libelle_court' => mb_substr((string) $valeurs['libelle'], 0, 255),
            'reference' => $valeurs['reference'] ?? null,
            'mode_reglement' => (string) $valeurs['mode_reglement'],
            'tiers' => $valeurs['tiers'] ?? null,
            'agence_id' => (int) $valeurs['agence_id'] > 0 ? (int) $valeurs['agence_id'] : null,
            'date_mouvement' => (string) $valeurs['date_mouvement'],
            'recorded_by' => (int) $valeurs['recorded_by'] > 0 ? (int) $valeurs['recorded_by'] : null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Annule un mouvement saisi : il quitte le tableau et les cumuls, la ligne
     * reste en base avec la main et l'heure de celui qui l'a retirée.
     */
    public function annulerMouvement(int $id, int $userId): bool
    {
        $stmt = $this->pdo->prepare('
            UPDATE lbp_mouvements_caisse
               SET annule_le = NOW(), annule_par = :user
             WHERE id = :id AND annule_le IS NULL
        ');
        $stmt->execute(['user' => $userId > 0 ? $userId : null, 'id' => $id]);

        return $stmt->rowCount() > 0;
    }

    // ------------------------------------------------------------------

    /**
     * Le numéro du dossier d'envoi qui porte le colis facturé, s'il existe.
     *
     * Sous-requête corrélée, et non jointure : deux dossiers rattachés à la
     * même expédition dupliqueraient la ligne de paiement, et le règlement
     * serait compté deux fois dans la journée.
     */
    private function expressionDossier(): string
    {
        if (!$this->tableExiste('lbp_dossiers_envoi')) {
            return 'NULL';
        }

        return '(SELECT de.numero FROM lbp_dossiers_envoi de
                  WHERE c.expedition_id IS NOT NULL
                    AND de.expedition_id = c.expedition_id
                  ORDER BY de.id ASC LIMIT 1)';
    }

    private function tableExiste(string $table): bool
    {
        if (array_key_exists($table, $this->tables)) {
            return $this->tables[$table];
        }

        try {
            $stmt = $this->pdo->prepare('
                SELECT COUNT(*) FROM information_schema.tables
                WHERE table_schema = DATABASE() AND table_name = :table
            ');
            $stmt->execute(['table' => $table]);
            $this->tables[$table] = (int) $stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            $this->tables[$table] = false;
        }

        return $this->tables[$table];
    }

    /**
     * Exécute et rend les lignes. Une source manquante vaut zéro ligne : la
     * journée des trois autres doit rester lisible, et le journal du serveur
     * garde la trace de la panne.
     *
     * @param array<string, mixed> $parametres
     * @return array<int, array<string, mixed>>
     */
    private function lignes(string $sql, array $parametres = []): array
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($parametres);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('[MouvementsCaisseRepository] ' . $e->getMessage());

            return [];
        }
    }
}
