<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use PDO;
use ReflectionClass;
use Tests\TestCase;

/**
 * Indicateurs des tableaux de bord Tickets et Facturation.
 *
 * Ils affichaient jusqu ici des zeros ecrits en dur, indiscernables d un vrai zero
 * pour qui regarde l ecran. Deux exigences sont verifiees ici : les chiffres sont
 * bien calcules, et un indicateur qui echoue affiche un tiret plutot qu un zero.
 */
final class TableauxDeBordTest extends TestCase
{
    // -----------------------------------------------------------------
    // Tickets
    // -----------------------------------------------------------------

    public function test_les_tickets_ouverts_sont_comptes(): void
    {
        $pdo = $this->baseTickets();
        $kpis = $this->kpis('App\Repositories\Tickets\TicketsDashboardRepository', $pdo);

        self::assertSame('3', $kpis[0]['value'], 'Trois tickets non clos.');
        self::assertSame('warning', $kpis[0]['tone']);
    }

    public function test_les_tickets_urgents_et_en_retard_sont_distingues(): void
    {
        $pdo = $this->baseTickets();
        $kpis = $this->kpis('App\Repositories\Tickets\TicketsDashboardRepository', $pdo);

        self::assertSame('2', $kpis[1]['value'], 'Deux priorités hautes encore ouvertes.');
        self::assertSame('1', $kpis[2]['value'], 'Un seul dont l\'échéance est dépassée.');
        self::assertSame('danger', $kpis[2]['tone']);
    }

    public function test_sans_ticket_les_indicateurs_passent_au_vert(): void
    {
        $pdo = $this->baseTickets(vide: true);
        $kpis = $this->kpis('App\Repositories\Tickets\TicketsDashboardRepository', $pdo);

        self::assertSame('0', $kpis[0]['value']);
        self::assertSame('success', $kpis[0]['tone'], 'Zéro ticket ouvert est une bonne nouvelle, pas une alerte.');
    }

    public function test_une_table_absente_affiche_un_tiret_et_non_un_zero(): void
    {
        // Le point du chantier : un zero trompeur ressemble a une donnee reelle.
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $kpis = $this->kpis('App\Repositories\Tickets\TicketsDashboardRepository', $pdo);

        foreach ($kpis as $kpi) {
            self::assertSame('—', $kpi['value'], 'Un indicateur incalculable doit le dire.');
        }
    }

    // -----------------------------------------------------------------
    // Facturation
    // -----------------------------------------------------------------

    public function test_les_montants_factures_excluent_les_euros(): void
    {
        $pdo = $this->baseFactures();
        $kpis = $this->kpis('App\Repositories\Facturation\FacturationDashboardRepository', $pdo);

        self::assertSame('300 000 XOF', $kpis[1]['value'], 'Les 150 EUR ne doivent pas gonfler le total en francs.');
        self::assertStringContainsString('150,00 EUR', $kpis[1]['meta'], 'Les euros sont annoncés à part.');
    }

    public function test_le_restant_du_est_ventile_de_la_meme_facon(): void
    {
        $pdo = $this->baseFactures();
        $kpis = $this->kpis('App\Repositories\Facturation\FacturationDashboardRepository', $pdo);

        self::assertSame('50 000 XOF', $kpis[2]['value']);
        self::assertSame('warning', $kpis[2]['tone']);
    }

    public function test_les_factures_annulees_sont_exclues(): void
    {
        $pdo = $this->baseFactures();
        $kpis = $this->kpis('App\Repositories\Facturation\FacturationDashboardRepository', $pdo);

        self::assertSame('3', $kpis[0]['value'], 'Quatre factures en base, dont une annulée.');
    }

    public function test_facturation_affiche_un_tiret_si_la_table_manque(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $kpis = $this->kpis('App\Repositories\Facturation\FacturationDashboardRepository', $pdo);

        self::assertSame('—', $kpis[0]['value']);
        self::assertSame('—', $kpis[1]['value']);
    }

    // -----------------------------------------------------------------

    /**
     * Instancie le depot sur une base de test et retourne ses indicateurs.
     *
     * Le constructeur est court-circuite : seul le calcul des indicateurs nous
     * interesse, pas le libelle ni la navigation du module.
     *
     * @return array<int, array<string, mixed>>
     */
    private function kpis(string $classe, PDO $pdo): array
    {
        $reflexion = new ReflectionClass($classe);

        self::assertStringContainsString(
            '$data[\'kpis\'] = [',
            (string) file_get_contents((string) $reflexion->getFileName()),
            'Le dépôt doit remplacer les indicateurs par défaut.'
        );

        $depot = $reflexion->newInstanceWithoutConstructor();

        $pdoParent = $reflexion->getParentClass()->getProperty('pdo');
        $pdoParent->setAccessible(true);
        $pdoParent->setValue($depot, $pdo);

        try {
            $donnees = $depot->dashboard();
        } catch (\Throwable $e) {
            self::fail('dashboard() a echoue : ' . $e->getMessage());
        }

        return (array) ($donnees['kpis'] ?? []);
    }

    private function baseTickets(bool $vide = false): PDO
    {
        $pdo = $this->sqlite();
        $pdo->exec('CREATE TABLE tickets (id INTEGER PRIMARY KEY, priority TEXT, status TEXT, due_at TEXT, created_at TEXT)');

        if (!$vide) {
            $pdo->exec("INSERT INTO tickets (priority, status, due_at, created_at) VALUES
                ('high',   'open',        datetime('now','-2 day'), datetime('now')),
                ('urgent', 'in_progress', datetime('now','+3 day'), datetime('now','-5 day')),
                ('normal', 'assigned',    NULL,                     datetime('now','-1 day')),
                ('high',   'closed',      NULL,                     datetime('now','-10 day'))");
        }

        return $pdo;
    }

    private function baseFactures(): PDO
    {
        $pdo = $this->sqlite();
        $pdo->exec('CREATE TABLE lbp_factures (id INTEGER PRIMARY KEY, devise TEXT, montant_total REAL,
                    montant_restant REAL, statut TEXT, date_emission TEXT, date_echeance_solde TEXT)');
        $pdo->exec("INSERT INTO lbp_factures (devise, montant_total, montant_restant, statut, date_emission, date_echeance_solde) VALUES
            ('XOF', 200000, 50000, 'partiellement_payee', datetime('now'),          NULL),
            ('XOF', 100000,     0, 'payee',               datetime('now','-3 day'), NULL),
            ('EUR',    150,   150, 'emise',               datetime('now','-1 day'), NULL),
            ('XOF',  90000, 90000, 'annulee',             datetime('now','-8 day'), NULL)");

        return $pdo;
    }

    private function sqlite(): PDO
    {
        // CURDATE() et NOW() n existent pas en SQLite : on les traduit.
        return new class ('sqlite::memory:') extends PDO {
            public function __construct(string $dsn)
            {
                parent::__construct($dsn);
                $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            }

            public function query(string $query, ?int $mode = null, mixed ...$args): \PDOStatement|false
            {
                return parent::query($this->traduire($query));
            }

            private function traduire(string $sql): string
            {
                $sql = str_replace('CURDATE()', "date('now')", $sql);
                $sql = str_replace('NOW()', "datetime('now')", $sql);
                $sql = preg_replace('/DATE_SUB\(date\(\'now\'\), INTERVAL (\d+) DAY\)/', "date('now','-$1 day')", $sql) ?? $sql;
                $sql = preg_replace('/DATE\(([a-z_]+)\)/i', "date($1)", $sql) ?? $sql;

                return $sql;
            }
        };
    }
}
