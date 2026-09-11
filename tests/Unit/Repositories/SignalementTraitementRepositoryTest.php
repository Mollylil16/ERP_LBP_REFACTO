<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\PilotageDg\SignalementTraitementRepository;
use PDO;
use Tests\TestCase;

/**
 * Cycle de vie des signalements anti-fraude.
 *
 * Sans ce suivi, un écart expliqué et régularisé continue de remonter en « TRÈS GRAVE »
 * à chaque ouverture du module, et déclencherait une notification chaque jour.
 */
final class SignalementTraitementRepositoryTest extends TestCase
{
    public function test_un_signalement_est_a_traiter_par_defaut(): void
    {
        $depot = new SignalementTraitementRepository($this->base());

        $resultat = $depot->enrichir($this->signalements());

        self::assertSame(2, $resultat['aTraiter']);
        self::assertSame(0, $resultat['traites']);
        self::assertSame('nouveau', $resultat['signalements'][0]['statut_traitement']);
        self::assertFalse($resultat['signalements'][0]['clos']);
    }

    public function test_marquer_vu_ne_clot_pas_le_signalement(): void
    {
        $depot = new SignalementTraitementRepository($this->base());
        $depot->marquer('EC-1', 'vu', 1);

        $resultat = $depot->enrichir($this->signalements());

        self::assertSame('vu', $resultat['signalements'][0]['statut_traitement']);
        self::assertFalse($resultat['signalements'][0]['clos'], 'Avoir vu une fraude ne la règle pas.');
        self::assertSame(2, $resultat['aTraiter']);
    }

    public function test_marquer_traite_clot_le_signalement(): void
    {
        $depot = new SignalementTraitementRepository($this->base());
        $depot->marquer('EC-1', 'traite', 1, 'Régularisé le lendemain');

        $resultat = $depot->enrichir($this->signalements());

        self::assertTrue($resultat['signalements'][0]['clos']);
        self::assertSame(1, $resultat['aTraiter']);
        self::assertSame(1, $resultat['traites']);
        self::assertSame('Régularisé le lendemain', $resultat['signalements'][0]['traitement_commentaire']);
        self::assertSame('Directeur Général', $resultat['signalements'][0]['traitement_par']);
    }

    public function test_classer_sans_suite_clot_aussi(): void
    {
        $depot = new SignalementTraitementRepository($this->base());
        $depot->marquer('CS-9', 'classe', 1);

        $resultat = $depot->enrichir($this->signalements());

        self::assertTrue($resultat['signalements'][1]['clos']);
        self::assertSame(1, $resultat['traites']);
    }

    public function test_rouvrir_remet_le_signalement_a_traiter(): void
    {
        $depot = new SignalementTraitementRepository($this->base());
        $depot->marquer('EC-1', 'traite', 1, 'Classé par erreur');

        $depot->rouvrir('EC-1');
        $resultat = $depot->enrichir($this->signalements());

        self::assertSame('nouveau', $resultat['signalements'][0]['statut_traitement']);
        self::assertNull($resultat['signalements'][0]['traitement_commentaire']);
        self::assertSame(2, $resultat['aTraiter']);
    }

    public function test_un_statut_inconnu_est_ignore(): void
    {
        $depot = new SignalementTraitementRepository($this->base());
        $depot->marquer('EC-1', 'archive-definitive', 1);

        $resultat = $depot->enrichir($this->signalements());

        self::assertSame('nouveau', $resultat['signalements'][0]['statut_traitement']);
    }

    public function test_le_commentaire_est_borne(): void
    {
        $depot = new SignalementTraitementRepository($this->base());
        $depot->marquer('EC-1', 'traite', 1, str_repeat('a', 900));

        $resultat = $depot->enrichir($this->signalements());

        self::assertSame(500, mb_strlen((string) $resultat['signalements'][0]['traitement_commentaire']));
    }

    public function test_une_liste_vide_ne_provoque_aucune_requete(): void
    {
        $depot = new SignalementTraitementRepository($this->base());

        self::assertSame(['signalements' => [], 'aTraiter' => 0, 'traites' => 0], $depot->enrichir([]));
        self::assertSame([], $depot->pourCles([]));
        self::assertSame([], $depot->pourCles(['CLE-INEXISTANTE']));
    }

    public function test_marquer_deux_fois_met_a_jour_sans_dupliquer(): void
    {
        $pdo = $this->base();
        $depot = new SignalementTraitementRepository($pdo);

        $depot->marquer('EC-1', 'vu', 1, 'Premier passage');
        $depot->marquer('EC-1', 'traite', 1, 'Après vérification');

        $lignes = (int) $pdo->query("SELECT COUNT(*) FROM lbp_signalements_traitement WHERE signalement_key = 'EC-1'")->fetchColumn();

        self::assertSame(1, $lignes);
        self::assertSame('Après vérification', $depot->enrichir($this->signalements())['signalements'][0]['traitement_commentaire']);
    }

    // -----------------------------------------------------------------

    /**
     * @return array<int, array<string, mixed>>
     */
    private function signalements(): array
    {
        return [
            ['id' => 'EC-1', 'degre' => 4, 'type' => 'Écart de Caisse non conforme'],
            ['id' => 'CS-9', 'degre' => 3, 'type' => 'Colis Sous-Déclarés'],
        ];
    }

    private function base(): PDO
    {
        $pdo = new class ('sqlite::memory:') extends PDO {
            public function prepare(string $query, array $options = []): \PDOStatement|false
            {
                $query = str_replace('NOW()', "datetime('now')", $query);

                if (str_contains($query, 'ON DUPLICATE KEY UPDATE')) {
                    $query = "INSERT INTO lbp_signalements_traitement
                              (signalement_key, statut, commentaire, traite_par, created_at, updated_at)
                              VALUES (:cle, :statut, :commentaire, :par, datetime('now'), datetime('now'))
                              ON CONFLICT(signalement_key) DO UPDATE SET
                                statut = excluded.statut,
                                commentaire = excluded.commentaire,
                                traite_par = excluded.traite_par,
                                updated_at = datetime('now')";
                }

                return parent::prepare($query, $options);
            }
        };

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, full_name TEXT)');
        $pdo->exec("INSERT INTO users VALUES (1, 'Directeur Général')");
        $pdo->exec('CREATE TABLE lbp_signalements_traitement (
            id INTEGER PRIMARY KEY AUTOINCREMENT, signalement_key TEXT UNIQUE, statut TEXT,
            commentaire TEXT, traite_par INTEGER, created_at TEXT, updated_at TEXT)');

        return $pdo;
    }
}
