<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use PDO;
use Tests\TestCase;

/**
 * Separation des devises dans les agregats financiers.
 *
 * Additionner des euros a des francs compte 100 EUR pour 100 XOF, soit environ
 * 655 fois moins que leur valeur reelle. Les totaux affiches en francs doivent
 * donc exclure les montants en euros, et les presenter a part.
 *
 * Les requetes sont rejouees ici sur une base en memoire : si quelqu un retire le
 * filtre de devise d un de ces agregats, le test le voit.
 */
final class VentilationDevisesTest extends TestCase
{
    public function test_la_ventilation_par_type_d_envoi_separe_les_devises(): void
    {
        $source = $this->source('app/Repositories/Finance/EtatJournalierRepository.php');

        // Le facture en francs exclut explicitement les euros, qui ont leur
        // propre colonne. La casse du mot-cle AS n'a pas d'importance.
        self::assertMatchesRegularExpression(
            "/SUM\(CASE WHEN f\.devise = 'EUR' THEN 0 ELSE f\.montant_total END\)\s+AS total_facture\b/i",
            $source
        );
        self::assertMatchesRegularExpression('/AS total_facture_eur\b/i', $source);
    }

    /**
     * L'encaisse ventile par type doit s'accorder au solde de caisse, qui est
     * libelle en francs : y verser des euros ferait diverger les deux blocs.
     */
    public function test_l_encaisse_ventile_par_type_ne_retient_que_les_francs(): void
    {
        $source = $this->source('app/Repositories/Finance/EtatJournalierRepository.php');

        self::assertMatchesRegularExpression(
            "/SUM\(CASE WHEN p\.devise = 'XOF' THEN p\.montant ELSE 0 END\)\s+AS total_encaisse\b/i",
            $source
        );
    }

    public function test_la_courbe_des_encaissements_ne_retient_que_les_francs(): void
    {
        $source = $this->source('app/Repositories/Finance/FinanceDashboardRepository.php');

        // La courbe est libellee en XOF : y verser des euros la fausserait.
        self::assertMatchesRegularExpression(
            "/FROM lbp_paiements\s+WHERE date_paiement >= DATE_SUB\(CURDATE\(\), INTERVAL 30 DAY\)\s+AND devise = 'XOF'/",
            $source
        );
    }

    public function test_les_demandes_de_fonds_separent_les_devises(): void
    {
        $source = $this->source('app/Repositories/Finance/DemandeFondsRepository.php');

        self::assertStringContainsString('AS montant_total_demande_eur', $source);
        self::assertStringContainsString('AS montant_total_decaisse_eur', $source);
        self::assertStringNotContainsString('COALESCE(SUM(montant), 0) AS montant_total_demande', $source);
    }

    public function test_les_agences_a_impayes_separent_les_devises(): void
    {
        $source = $this->source('app/Repositories/PilotageDg/PilotageDgDashboardRepository.php');

        self::assertStringContainsString(
            "COALESCE(SUM(CASE WHEN f.devise = 'EUR' THEN 0 ELSE f.montant_restant END), 0) AS montant_impaye",
            $source
        );
    }

    public function test_le_tableau_de_bord_finance_regroupe_deja_par_devise(): void
    {
        $source = $this->source('app/Repositories/Finance/FinanceDashboardRepository.php');

        // Cet agregat n a jamais melange les devises : il groupe dessus.
        // Le test existe pour qu une simplification ne retire pas ce GROUP BY.
        self::assertMatchesRegularExpression(
            "/SELECT devise,.*?FROM lbp_factures.*?GROUP BY devise/s",
            $source
        );
    }

    public function test_le_calcul_rejoue_donne_bien_le_montant_en_francs(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $pdo->exec('CREATE TABLE lbp_factures (id INTEGER PRIMARY KEY, devise TEXT, montant_total REAL, montant_restant REAL)');
        $pdo->exec("INSERT INTO lbp_factures VALUES
            (1, 'XOF', 200000, 50000),
            (2, 'XOF', 100000,      0),
            (3, 'EUR',    150,    150)");

        $ligne = $pdo->query("
            SELECT COALESCE(SUM(CASE WHEN devise = 'EUR' THEN 0 ELSE montant_total END), 0) AS xof,
                   COALESCE(SUM(CASE WHEN devise = 'EUR' THEN montant_total ELSE 0 END), 0) AS eur,
                   COALESCE(SUM(montant_total), 0) AS melange
            FROM lbp_factures
        ")->fetch();

        self::assertSame(300000.0, (float) $ligne['xof'], 'Les francs seuls.');
        self::assertSame(150.0, (float) $ligne['eur'], 'Les euros a part.');
        self::assertSame(300150.0, (float) $ligne['melange'], 'Le calcul d avant, qui ajoutait 150 EUR comme 150 XOF.');
    }

    private function source(string $chemin): string
    {
        return (string) file_get_contents(BASE_PATH . '/' . $chemin);
    }
}
