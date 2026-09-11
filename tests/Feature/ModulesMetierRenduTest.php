<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\View\Components\AgentsCorrespondants;
use App\View\Components\Entrepots;
use App\View\Components\FlotteTransport;
use App\View\Components\PortefeuilleClients;
use App\View\Components\TrackingColis;
use App\View\Components\TransitDouane;
use Tests\TestCase;

/**
 * Rendu des six écrans métier.
 *
 * Ces pages s'affichent surtout au démarrage sur des tables encore vides : c'est
 * précisément le cas où une clé manquante ou une division par zéro se déclare.
 * Chaque écran est donc rendu deux fois, à vide puis avec des données.
 */
final class ModulesMetierRenduTest extends TestCase
{
    public function test_les_six_ecrans_se_rendent_sur_des_donnees_vides(): void
    {
        foreach ($this->ecrans($this->donneesVides()) as $nom => $html) {
            self::assertNotSame('', $html, "L'écran {$nom} n'a rien produit.");
            self::assertStringContainsString('finea-shell', $html, "L'écran {$nom} ne rend pas la coque du module.");
        }
    }

    public function test_les_ecrans_vides_expliquent_pourquoi_ils_sont_vides(): void
    {
        // Une page blanche laisse croire à une panne. Chaque section vide doit
        // dire ce qu'il manque.
        foreach ($this->ecrans($this->donneesVides()) as $nom => $html) {
            self::assertStringContainsString(
                'finea-empty',
                $html,
                "L'écran {$nom} n'affiche aucun état vide explicatif."
            );
        }
    }

    public function test_les_six_ecrans_se_rendent_sur_des_donnees_reelles(): void
    {
        foreach ($this->ecrans($this->donneesPeuplees()) as $nom => $html) {
            self::assertStringContainsString('finea-table', $html, "L'écran {$nom} ne rend aucun tableau.");
        }
    }

    public function test_aucun_ecran_ne_laisse_passer_de_html_non_echappe(): void
    {
        $donnees = $this->donneesPeuplees();
        $injection = '<script>alert(1)</script>';

        $donnees['colis'][0]['numero_tracking'] = $injection;
        $donnees['livreurs'][0]['livreur'] = $injection;
        $donnees['lots'][0]['reference_lot'] = $injection;
        $donnees['clients'][0]['client'] = $injection;
        $donnees['agents'][0]['name'] = $injection;
        $donnees['echecs'][0]['reference'] = $injection;

        foreach ($this->ecrans($donnees) as $nom => $html) {
            self::assertStringNotContainsString(
                '<script>alert(1)</script>',
                $html,
                "L'écran {$nom} restitue une donnée de la base sans l'échapper."
            );
        }
    }

    /**
     * @param array<string, mixed> $donnees
     * @return array<string, string>
     */
    private function ecrans(array $donnees): array
    {
        return [
            'Entrepôts' => Entrepots::occupationPage($donnees, 'Toutes agences'),
            'Flotte' => FlotteTransport::flottePage($donnees, 'Toutes agences'),
            'Transit Douane' => TransitDouane::dossiersPage($donnees, 'Toutes agences'),
            'Portefeuille Clients' => PortefeuilleClients::portefeuillePage($donnees, 'Toutes agences'),
            'Agents & Correspondants' => AgentsCorrespondants::reseauPage($donnees, true, null),
            'Tracking Colis' => TrackingColis::suiviPage($donnees, 'Toutes agences'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function donneesVides(): array
    {
        return [
            'kpis' => [],
            'parametres' => ['delai_gratuit_jours' => 7, 'frais_gardiennage_par_jour' => 500.0],
            'rayons' => [], 'colis' => [], 'enSouffrance' => [], 'mouvements' => [], 'inventaires' => [],
            'livreurs' => [], 'missions' => [], 'enRetard' => [], 'terminees' => [],
            'lots' => [], 'parTrajet' => [], 'enTransit' => [],
            'mois' => 12, 'clients' => [], 'encours' => [], 'inactifs' => [], 'parDevise' => [],
            'recherche' => '', 'agents' => [], 'parPays' => [],
            'fiche' => null, 'statuts' => [], 'echecs' => [],
            'volume' => ['total' => 0, 'echecs' => 0], 'fenetre' => 30,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function donneesPeuplees(): array
    {
        $maintenant = date('Y-m-d H:i:s');

        // array_merge et non « + » : l'union de tableaux garde la clé de gauche,
        // donc les sections peuplées n'écraseraient pas les sections vides.
        return array_merge(
            $this->donneesVides(),
            ['kpis' => [['label' => 'Indicateur', 'value' => '1', 'meta' => 'Test']]],
            $this->sections($maintenant)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function sections(string $maintenant): array
    {
        $colis = [[
            'id' => 1, 'numero_tracking' => 'LBP-0001', 'statut' => 'EN_TRANSIT',
            'poids_total' => 12.5, 'nombre_colis' => 2, 'montant_total' => 50000, 'devise' => 'XOF',
            'expediteur' => 'Awa', 'destinataire' => 'Moussa', 'destinataire_tel' => '0700000000',
            'code_rayon' => 'R1', 'nom_rayon' => 'Rayon 1', 'agence_name' => 'Abidjan',
            'entre_le' => $maintenant, 'jours_stockes' => 12, 'jours_factures' => 5, 'gardiennage_xof' => 5000.0,
            'agence_depart' => 'Paris', 'agence_arrivee' => 'Abidjan', 'trajet' => 'LB-FR',
            'created_at' => $maintenant, 'derniere_etape' => 'Arrivé', 'derniere_etape_le' => $maintenant,
            'jours_depuis_prise_en_charge' => 12, 'immobilise' => false, 'a_trace' => true,
        ]];

        return [
            'rayons' => [[
                'id' => 1, 'code_rayon' => 'R1', 'nom_rayon' => 'Rayon 1', 'capacite_max' => 50,
                'statut' => 'ACTIF', 'agence_name' => 'Abidjan', 'nb_colis' => 20, 'poids_kg' => 250.0,
                'taux' => 40, 'tone' => 'success',
            ]],
            'colis' => $colis,
            'enSouffrance' => $colis,
            'mouvements' => [[
                'id' => 1, 'type_mouvement' => 'ENTREE', 'commentaires' => 'Réception',
                'created_at' => $maintenant, 'numero_tracking' => 'LBP-0001',
                'code_rayon' => 'R1', 'nom_rayon' => 'Rayon 1', 'agence_name' => 'Abidjan', 'auteur' => 'Diarra',
            ]],
            'inventaires' => [[
                'id' => 1, 'date_inventaire' => $maintenant, 'statut' => 'CLOTURE', 'commentaires' => '',
                'agence_name' => 'Abidjan', 'auteur' => 'Diarra', 'nb_lignes' => 10, 'nb_manquants' => 0,
            ]],
            'livreurs' => [[
                'id' => 1, 'modele_vehicule' => 'Hilux', 'plaque_immatriculation' => 'AB-123-CD',
                'statut' => 'Disponible', 'latitude' => 5.3, 'longitude' => -4.0,
                'derniere_localisation' => $maintenant, 'livreur' => 'Koffi', 'phone' => '0700000000',
                'statut_compte' => 'active', 'agence_name' => 'Abidjan', 'missions_ouvertes' => 2,
                'a_position' => true, 'position_fraiche' => true,
            ]],
            'missions' => [[
                'id' => 1, 'reference' => 'EXP-1', 'type_transport' => 'AÉRIEN',
                'date_depart_prevue' => '2026-09-01', 'date_arrivee_estimee' => '2026-09-05',
                'statut' => 'EN_TRANSIT', 'agence_depart' => 'Paris', 'agence_arrivee' => 'Abidjan',
                'livreur' => 'Koffi', 'plaque_immatriculation' => 'AB-123-CD',
                'nb_colis' => 30, 'poids_kg' => 400.0, 'jours_retard' => 6,
            ]],
            'terminees' => [[
                'reference' => 'EXP-0', 'type_transport' => 'MARITIME', 'date_arrivee_estimee' => '2026-08-01',
                'updated_at' => $maintenant, 'statut' => 'CLOTURE',
                'agence_depart' => 'Paris', 'agence_arrivee' => 'Abidjan', 'livreur' => 'Koffi',
            ]],
            'lots' => [[
                'id' => 1, 'reference_lot' => 'LOT-1', 'trajet_code' => 'LB-FR', 'trajet_libelle' => 'Ligne France',
                'frais_douane_xof' => 100000.0, 'frais_fret_xof' => 200000.0, 'frais_manutention_xof' => 50000.0,
                'poids_total_kg' => 700.0, 'cout_kg_saisi' => 500.0, 'statut' => 'VALIDÉ',
                'created_at' => $maintenant, 'total_xof' => 350000.0, 'cout_kg' => 500.0,
                'cout_kg_desynchronise' => false, 'ecart_moyenne' => 0, 'derive' => false,
            ]],
            'parTrajet' => [[
                'trajet_code' => 'LB-FR', 'trajet_libelle' => 'Ligne France', 'nb_lots' => 3,
                'poids_kg' => 2000.0, 'douane_xof' => 300000.0, 'fret_xof' => 600000.0,
                'manutention_xof' => 100000.0, 'total_xof' => 1000000.0, 'cout_kg_moyen' => 500.0,
            ]],
            'enTransit' => [[
                'trajet_code' => 'LB-FR', 'trajet_libelle' => 'Ligne France', 'nb_envois' => 12,
                'nb_colis' => 40, 'poids_kg' => 800.0, 'valeur_declaree' => 3000000.0,
            ]],
            'clients' => [[
                'id' => 1, 'client' => 'Société Alpha', 'phone' => '0700000000', 'email' => 'a@b.ci',
                'type' => 'corporate', 'devise' => 'XOF', 'nb_factures' => 12, 'ca' => 5000000.0,
                'encaisse' => 4800000.0, 'impaye' => 200000.0, 'segment' => 'A', 'part_ca' => 62.5,
                'taux_recouvrement' => 96, 'jours_depuis_derniere' => 4,
                'derniere_facture' => $maintenant,
            ]],
            'encours' => [[
                'id' => 1, 'client' => 'Société Alpha', 'phone' => '0700000000', 'devise' => 'XOF',
                'nb_factures_ouvertes' => 2, 'impaye' => 200000.0, 'plus_ancienne' => '2026-05-01',
                'anciennete_jours' => 133, 'tone' => 'warning', 'a_risque' => true,
            ]],
            'inactifs' => [[
                'id' => 2, 'client' => 'Société Beta', 'phone' => '0700000001', 'type' => 'standard',
                'derniere_facture' => '2026-01-10', 'jours_sans_activite' => 244,
                'nb_factures' => 3, 'devise' => 'XOF', 'ca_historique' => 750000.0,
            ]],
            'parDevise' => [[
                'devise' => 'XOF', 'ca' => 5000000.0, 'encaisse' => 4800000.0,
                'impaye' => 200000.0, 'nb_clients' => 1,
            ]],
            'agents' => [[
                'id' => 1, 'name' => 'Alpha Logistics', 'country' => 'France', 'city' => 'Marseille',
                'contact_name' => 'Jean', 'email' => 'jean@alpha.fr', 'phone' => '+33100000000',
                'coverage' => 'Port de Marseille, Fos-sur-Mer', 'is_active' => 1,
                'created_at' => $maintenant, 'updated_at' => null,
            ]],
            'parPays' => [[
                'country' => 'France', 'nb_agents' => 3, 'nb_actifs' => 2, 'nb_villes' => 2,
            ]],
            'statuts' => [[
                'statut' => 'EN_TRANSIT', 'nb_envois' => 12, 'nb_colis' => 40,
                'poids_kg' => 800.0, 'age_moyen_jours' => 9.4,
            ]],
            'echecs' => [[
                'reference' => 'LBP-9999', 'nb_tentatives' => 4, 'derniere_tentative' => $maintenant,
                'nb_visiteurs' => 2, 'existe_aujourdhui' => 0,
            ]],
            'volume' => ['total' => 120, 'echecs' => 9],
        ];
    }
}
