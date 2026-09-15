<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Colisage\PointageColisService;
use App\View\Components\ColisagePointage;
use Tests\TestCase;

/**
 * Rendu des écrans du pointage des colis.
 *
 * Au lancement, ces écrans s'ouvriront surtout vides, ou pour un compte réseau
 * qui n'a pas encore choisi d'agence : c'est là que se cachent les clés
 * manquantes. Chaque écran est rendu vide, puis rempli.
 */
final class ColisagePointageRenduTest extends TestCase
{
    public function test_un_compte_sans_agence_est_prevenu_a_la_reception(): void
    {
        $html = html_entity_decode(ColisagePointage::receptionPage([
            'agences' => [], 'agence_id' => 0, 'peut_choisir' => false, 'departs' => [],
        ]), ENT_QUOTES);

        self::assertStringContainsString("n'est rattaché à aucune agence", $html);
    }

    public function test_la_reception_coche_les_colis_deja_recus_et_bloque_les_colis_retires(): void
    {
        $html = ColisagePointage::receptionPage([
            'agences' => [],
            'agence_id' => 3400,
            'peut_choisir' => false,
            'departs' => [$this->depart([
                'colis' => [
                    $this->colis(['id' => 1, 'numero_tracking' => 'LB-FR-001', 'date_reception' => '2026-09-15 09:00:00', 'recu_par' => 'Agent Paris']),
                    $this->colis(['id' => 2, 'numero_tracking' => 'LB-FR-002']),
                    $this->colis(['id' => 3, 'numero_tracking' => 'LB-FR-003', 'statut' => 'retire']),
                ],
            ])],
        ]);

        self::assertMatchesRegularExpression('/data-colis-id="1"[^>]*checked/', $html);
        self::assertDoesNotMatchRegularExpression('/data-colis-id="2"[^>]*checked/', $html);
        self::assertMatchesRegularExpression('/data-colis-id="3"[^>]*disabled/', $html);
        self::assertStringContainsString('data-url-pointer=', $html);
        self::assertStringContainsString('data-url-scanner=', $html);
        self::assertStringContainsString('data-agence="3400"', $html, 'La douchette connaît son agence.');
        self::assertStringContainsString('Prévenir les clients', $html);
        self::assertStringContainsString('tout-pointer', $html);
    }

    public function test_la_reception_vide_l_explique(): void
    {
        $html = ColisagePointage::receptionPage(['agences' => [], 'agence_id' => 3400, 'peut_choisir' => false, 'departs' => []]);

        self::assertStringContainsString('finea-empty', $html);
        self::assertStringContainsString('Aucun départ en attente', $html);
    }

    public function test_un_compte_reseau_sans_agence_choisie_ne_peut_pas_scanner(): void
    {
        $html = ColisagePointage::receptionPage([
            'agences' => [['id' => 3400, 'name' => 'Paris Bobigny']],
            'agence_id' => null,
            'peut_choisir' => true,
            'departs' => [],
        ]);

        self::assertMatchesRegularExpression('/id="lbp-reception-scan"[^>]*disabled/', $html);
    }

    public function test_le_suivi_se_rend_vide_puis_rempli(): void
    {
        $vide = ColisagePointage::suiviPage(['du' => '2026-08-15', 'au' => '2026-09-15', 'agences' => [], 'agence_id' => 3403, 'peut_choisir' => false, 'suivi' => []]);
        self::assertStringContainsString('Aucun départ sur la période', $vide);

        $rempli = ColisagePointage::suiviPage([
            'du' => '2026-08-15',
            'au' => '2026-09-15',
            'agences' => [['id' => 3403, 'name' => 'Agence Abobo Dokui']],
            'agence_id' => null,
            'peut_choisir' => true,
            'suivi' => [
                'departs' => [$this->depart(['manquants' => 30, 'etat' => PointageColisService::ETAT_MANQUANTS])],
                'totaux' => ['departs' => 1, 'envoyes' => 150, 'recus' => 120, 'manquants' => 30, 'en_attente' => 0],
                'trajets' => [['agence_depart' => 'Agence Abobo Dokui', 'agence_arrivee' => 'Paris Bobigny', 'departs' => 1, 'envoyes' => 150, 'recus' => 120, 'manquants' => 30]],
                'hors_liste' => [['created_at' => '2026-09-15 10:00:00', 'numero_tracking' => 'LB-FR-099', 'agence_reception' => 'Paris Bobigny', 'agence_depart' => 'Agence Abobo Dokui', 'agence_prevue' => 'Agence Sénégal', 'par' => 'Agent Paris']],
            ],
        ]);

        self::assertStringContainsString('finea-table', $rempli);
        self::assertStringContainsString('Colis manquants', $rempli);
        self::assertStringContainsString('LB-FR-099', $rempli);
        self::assertStringContainsString('Toutes les agences', $rempli);
    }

    public function test_le_detail_traduit_l_historique_des_gestes(): void
    {
        $html = ColisagePointage::detailPage([
            'depart' => $this->depart(),
            'colis' => [$this->colis(['etat' => 'MANQUANT'])],
            'historique' => [['created_at' => '2026-09-15 09:00:00', 'action' => 'RECU', 'source' => 'DOUCHETTE', 'numero_tracking' => 'LB-FR-001', 'agence' => 'Paris Bobigny', 'par' => 'Agent Paris']],
        ]);

        self::assertStringContainsString('Coché reçu', $html);
        self::assertStringContainsString('douchette', $html);
        self::assertStringContainsString('Manquant', $html);
        self::assertStringContainsString('/manifeste', $html);
    }

    public function test_aucun_ecran_ne_laisse_passer_de_html_non_echappe(): void
    {
        $injection = '<script>alert(1)</script>';
        $colis = $this->colis(['numero_tracking' => $injection, 'expediteur' => $injection, 'destinataire' => $injection, 'etat' => 'ATTENDU']);
        $depart = $this->depart(['reference' => $injection, 'agence_depart' => $injection, 'agence_arrivee' => $injection, 'colis' => [$colis]]);

        $ecrans = [
            'réception' => ColisagePointage::receptionPage(['agences' => [], 'agence_id' => 2, 'peut_choisir' => false, 'departs' => [$depart]]),
            'suivi' => ColisagePointage::suiviPage(['du' => '2026-09-01', 'au' => '2026-09-15', 'agences' => [], 'agence_id' => 1, 'peut_choisir' => false,
                'suivi' => ['departs' => [$depart], 'totaux' => ['departs' => 1, 'envoyes' => 1, 'recus' => 0, 'manquants' => 0, 'en_attente' => 1],
                    'trajets' => [['agence_depart' => $injection, 'agence_arrivee' => $injection, 'departs' => 1, 'envoyes' => 1, 'recus' => 0, 'manquants' => 0]],
                    'hors_liste' => [['created_at' => '2026-09-15 10:00:00', 'numero_tracking' => $injection, 'agence_reception' => $injection, 'agence_depart' => $injection, 'agence_prevue' => $injection, 'par' => $injection]]]]),
            'détail' => ColisagePointage::detailPage(['depart' => $depart, 'colis' => [$colis],
                'historique' => [['created_at' => '2026-09-15 09:00:00', 'action' => $injection, 'source' => $injection, 'numero_tracking' => $injection, 'agence' => $injection, 'par' => $injection]]]),
        ];

        foreach ($ecrans as $nom => $html) {
            self::assertStringNotContainsString('<script>alert(1)</script>', $html, "L'écran {$nom} laisse passer du HTML.");
        }
    }

    public function test_les_vues_ne_contiennent_que_l_appel_au_composant(): void
    {
        foreach (glob(BASE_PATH . '/views/colisage/pointage/*.php') ?: [] as $vue) {
            $source = (string) file_get_contents($vue);
            self::assertDoesNotMatchRegularExpression('/<(div|table|form|section|p|span)\b/i', $source, basename($vue) . ' contient du HTML brut.');
            self::assertStringContainsString('ColisagePointage::', $source);
        }
    }

    /**
     * @param array<string, mixed> $valeurs
     * @return array<string, mixed>
     */
    private function colis(array $valeurs = []): array
    {
        return $valeurs + [
            'id' => 1, 'numero_tracking' => 'LB-FR-001', 'nombre_colis' => 1, 'poids_total' => 5.5, 'statut' => 'en_transit',
            'created_at' => '2026-09-14 10:00:00', 'expediteur' => 'Client Abidjan', 'destinataire' => 'Client Paris',
            'date_reception' => null, 'recu_par' => null, 'reception_hors_liste' => 0, 'etat' => 'ATTENDU',
        ];
    }

    /**
     * @param array<string, mixed> $valeurs
     * @return array<string, mixed>
     */
    private function depart(array $valeurs = []): array
    {
        return $valeurs + [
            'id' => 7, 'reference' => 'DEP-20260914-1620-AB12', 'type_transport' => 'AÉRIEN', 'statut' => 'ARRIVE', 'est_reprise' => 0,
            'agence_depart_id' => 3403, 'agence_arrivee_id' => 3400, 'date_depart' => '2026-09-14 16:20:00',
            'date_premiere_reception' => '2026-09-15 08:00:00', 'agence_depart' => 'Agence Abobo Dokui', 'agence_arrivee' => 'Paris Bobigny',
            'envoyes' => 150, 'recus' => 120, 'restants' => 30, 'manquants' => 0, 'echeance' => '2026-09-16 08:00:00',
            'etat' => PointageColisService::ETAT_EN_COURS, 'colis' => [],
        ];
    }
}
