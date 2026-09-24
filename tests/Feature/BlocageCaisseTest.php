<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Finance\BlocageCaisseService;
use Tests\TestCase;

/**
 * Pas de point de caisse soumis, pas de caisse ouverte le lendemain.
 *
 * Mesuré en production le 24/09/2026 : 1 316 270 FCFA encaissés en un mois
 * sans qu'aucun point ne soit signé. Abobo Dokui en oubliait treize sur
 * vingt et un, l'aéroport n'en avait jamais soumis un seul. Cet argent n'a
 * jamais été compté par personne.
 *
 * Décidé par la direction : la règle s'applique au 1er octobre 2026, le temps
 * de prévenir toutes les agences.
 */
final class BlocageCaisseTest extends TestCase
{
    private function source(): string
    {
        return (string) file_get_contents(BASE_PATH . '/app/Services/Finance/BlocageCaisseService.php');
    }

    /**
     * Les journées antérieures ne bloquent rien : personne n'était tenu de
     * les soumettre, et l'aéroport aurait été paralysé dès le premier jour
     * par ses sept journées de retard.
     */
    public function test_la_regle_ne_s_applique_qu_a_partir_du_premier_octobre(): void
    {
        self::assertSame('2026-10-01', BlocageCaisseService::DEBUT_OBLIGATION);

        $source = $this->source();
        self::assertStringContainsString('$aujourdhui <= self::DEBUT_OBLIGATION', $source);
        self::assertStringContainsString("'debut' => self::DEBUT_OBLIGATION", $source);
    }

    /**
     * Le message dit ce qui manque, ce que ça représente, et quoi faire. Rien
     * de plus : le personnel des agences n'a pas à interpréter un écart.
     */
    public function test_le_message_dit_quoi_faire(): void
    {
        $message = BlocageCaisseService::message([
            'date' => '2026-10-02',
            'montant' => 120000.0,
            'operations' => 3,
        ]);

        self::assertStringContainsString('Caisse fermée', $message);
        self::assertStringContainsString('02/10/2026', $message);
        self::assertStringContainsString('120 000 FCFA', $message);
        self::assertStringContainsString('Soumettez ce point pour rouvrir la caisse', $message);
    }

    /**
     * La journée en cours ne bloque jamais : elle n'est pas finie.
     */
    public function test_la_journee_en_cours_ne_bloque_pas(): void
    {
        self::assertStringContainsString(
            'DATE(p.date_paiement) < :aujourdhui',
            $this->source(),
            "Seules les journées achevées peuvent bloquer."
        );
    }

    /**
     * Un point rouvert par un encaissement tardif n'est pas un point soumis :
     * la journée n'a pas été recomptée, elle doit bloquer.
     */
    public function test_seul_un_point_soumis_ou_consolide_libere_la_caisse(): void
    {
        self::assertStringContainsString("e.statut IN ('soumis', 'consolide')", $this->source());
    }

    /**
     * La direction n'est jamais bloquée : c'est elle qui débloque, en
     * soumettant le point manquant à la place de l'agence. Sans cette clé, un
     * défaut du logiciel arrêterait une agence sans recours.
     */
    public function test_la_direction_n_est_jamais_bloquee(): void
    {
        $source = $this->source();

        self::assertStringContainsString('ROLES_EXEMPTES', $source);
        foreach (['dg', 'assistant_dg', 'caissiere_principale'] as $role) {
            self::assertStringContainsString("'{$role}'", $source);
        }

        self::assertStringContainsString('Auth::isAdmin()', $source);
    }

    /**
     * Toutes les portes par lesquelles l'argent entre doivent être gardées :
     * en oublier une rendrait le blocage contournable.
     */
    public function test_toutes_les_portes_de_la_caisse_sont_gardees(): void
    {
        $finance = (string) file_get_contents(BASE_PATH . '/app/Controllers/Finance/FinanceController.php');

        self::assertSame(
            3,
            substr_count($finance, '$this->refuserSiCaisseFermee('),
            'Facturer, encaisser et payer par portefeuille doivent tous être gardés.'
        );

        // Facturer depuis un colis est un encaissement comme un autre.
        $colisage = (string) file_get_contents(BASE_PATH . '/app/Controllers/Colisage/ColisageController.php');
        self::assertStringContainsString('BlocageCaisseService::creer()->blocageCourant()', $colisage);
    }
}
