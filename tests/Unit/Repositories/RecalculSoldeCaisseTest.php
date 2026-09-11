<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\Finance\EtatJournalierRepository;
use PDO;
use Tests\TestCase;

/**
 * Logique de recalcul des soldes théoriques figés.
 *
 * Reproduit le calcul de app/Console/RecalculerSoldesCaisse.php pour vérifier
 * qu'il retient bien les seules espèces, et qu'un écart né du défaut de calcul
 * disparaît alors qu'un manquant réel subsiste.
 */
final class RecalculSoldeCaisseTest extends TestCase
{
    public function test_seules_les_especes_forment_le_solde_theorique(): void
    {
        $pdo = $this->base();

        // 40 000 en espèces, 60 000 en mobile money, 10 000 par portefeuille client.
        $this->paiement($pdo, 1, 40000, 'especes');
        $this->paiement($pdo, 1, 60000, 'mobile_money');
        $this->paiement($pdo, 1, 10000, 'portefeuille');

        $ligne = $this->recalcul($pdo)[0];

        self::assertSame(40000.0, (float) $ligne['especes_reelles'], 'Seul le liquide entre dans le tiroir.');
    }

    public function test_les_encaissements_en_euros_sont_ecartes(): void
    {
        $pdo = $this->base();
        $this->paiement($pdo, 1, 40000, 'especes');
        $this->paiement($pdo, 1, 100, 'especes', 'EUR');

        $ligne = $this->recalcul($pdo)[0];

        self::assertSame(40000.0, (float) $ligne['especes_reelles']);
    }

    public function test_un_faux_ecart_disparait_apres_recalcul(): void
    {
        $pdo = $this->base();

        // Journée type : 40 000 en espèces, 60 000 en mobile money. La caissière a
        // compté 40 000 dans son tiroir : sa caisse est juste.
        $this->paiement($pdo, 1, 40000, 'especes');
        $this->paiement($pdo, 1, 60000, 'mobile_money');
        $pdo->exec('UPDATE lbp_etats_journaliers SET solde_caisse_agence_xof = 100000, solde_physique_declare = 40000, ecart_caisse = -60000');

        $ligne = $this->recalcul($pdo)[0];
        $nouvelEcart = round(((float) $ligne['solde_physique_declare']) - ((float) $ligne['especes_reelles']), 2);

        self::assertSame(-60000.0, (float) $ligne['ecart_fige'], 'L\'écart figé accusait la caissière à tort.');
        self::assertSame(0.0, $nouvelEcart, 'Après recalcul, la caisse est juste.');
    }

    public function test_un_manquant_reel_subsiste_apres_recalcul(): void
    {
        $pdo = $this->base();

        // 40 000 encaissés en espèces, 25 000 seulement dans le tiroir.
        $this->paiement($pdo, 1, 40000, 'especes');
        $pdo->exec('UPDATE lbp_etats_journaliers SET solde_caisse_agence_xof = 40000, solde_physique_declare = 25000, ecart_caisse = -15000');

        $ligne = $this->recalcul($pdo)[0];
        $nouvelEcart = round(((float) $ligne['solde_physique_declare']) - ((float) $ligne['especes_reelles']), 2);

        self::assertSame(-15000.0, $nouvelEcart, 'Un vrai manquant ne doit pas être effacé par le recalcul.');
    }

    public function test_un_etat_deja_correct_n_est_pas_touche(): void
    {
        $pdo = $this->base();
        $this->paiement($pdo, 1, 40000, 'especes');
        $pdo->exec('UPDATE lbp_etats_journaliers SET solde_caisse_agence_xof = 40000, solde_physique_declare = 40000, ecart_caisse = 0');

        $ligne = $this->recalcul($pdo)[0];

        self::assertEqualsWithDelta(
            (float) $ligne['solde_fige'],
            (float) $ligne['especes_reelles'],
            0.01,
            'Cet état ne doit pas figurer parmi ceux à corriger.'
        );
    }

    public function test_une_journee_sans_paiement_donne_un_solde_nul(): void
    {
        $pdo = $this->base();
        $pdo->exec('UPDATE lbp_etats_journaliers SET solde_caisse_agence_xof = 55000');

        $ligne = $this->recalcul($pdo)[0];

        self::assertSame(0.0, (float) $ligne['especes_reelles']);
    }

    // -----------------------------------------------------------------

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recalcul(PDO $pdo): array
    {
        $modeSql = EtatJournalierRepository::MODE_SQL;

        $stmt = $pdo->prepare("
            SELECT
                e.id,
                e.solde_caisse_agence_xof AS solde_fige,
                e.solde_physique_declare,
                e.ecart_caisse AS ecart_fige,
                COALESCE(liquide.especes, 0) AS especes_reelles
            FROM lbp_etats_journaliers e
            LEFT JOIN (
                SELECT f.agence_id,
                       DATE(p.date_paiement) AS jour,
                       SUM(CASE WHEN {$modeSql} IN ('especes', 'espece', 'cash') THEN p.montant ELSE 0 END) AS especes
                FROM lbp_paiements p
                INNER JOIN lbp_factures f ON p.facture_id = f.id
                WHERE p.devise = 'XOF'
                GROUP BY f.agence_id, DATE(p.date_paiement)
            ) liquide ON liquide.agence_id = e.agence_id AND liquide.jour = e.date_jour
            WHERE e.date_jour >= :depuis
        ");
        $stmt->execute(['depuis' => '2000-01-01']);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function paiement(PDO $pdo, int $factureId, float $montant, string $mode, string $devise = 'XOF'): void
    {
        $stmt = $pdo->prepare('INSERT INTO lbp_paiements (facture_id, montant, devise, mode, mode_paiement, date_paiement)
                               VALUES (:f, :m, :d, :mo, :mp, :dt)');
        $stmt->execute([
            'f' => $factureId,
            'm' => $montant,
            'd' => $devise,
            'mo' => $mode,
            'mp' => 'ESPECES',
            'dt' => '2026-09-07 10:00:00',
        ]);
    }

    private function base(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $pdo->exec('CREATE TABLE lbp_factures (id INTEGER PRIMARY KEY, agence_id INTEGER)');
        $pdo->exec('INSERT INTO lbp_factures VALUES (1, 1)');

        $pdo->exec('CREATE TABLE lbp_paiements (
            id INTEGER PRIMARY KEY AUTOINCREMENT, facture_id INTEGER, montant REAL,
            devise TEXT, mode TEXT, mode_paiement TEXT, date_paiement TEXT)');

        $pdo->exec('CREATE TABLE lbp_etats_journaliers (
            id INTEGER PRIMARY KEY AUTOINCREMENT, agence_id INTEGER, date_jour TEXT, statut TEXT,
            solde_caisse_agence_xof REAL DEFAULT 0, solde_physique_declare REAL,
            ecart_caisse REAL DEFAULT 0, total_encaisse_xof REAL DEFAULT 0)');
        $pdo->exec("INSERT INTO lbp_etats_journaliers (agence_id, date_jour, statut) VALUES (1, '2026-09-07', 'soumis')");

        return $pdo;
    }
}
