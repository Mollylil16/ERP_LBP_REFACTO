<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Colisage\DossierEnvoiRegles as Regles;
use DateTimeImmutable;
use Tests\TestCase;

/**
 * Règles des départs préparés par l'agent export, arrêtées le 15/09/2026 :
 * dix colonnes saisies d'après le document de la compagnie, contrôle du
 * Directeur général contre la saisie des colis au-delà de 2 % d'écart.
 */
final class DossierEnvoiReglesTest extends TestCase
{
    /** Pièces d'un départ dont les colonnes 6, 7 et 9 sont payantes et la colonne 8 sans frais. */
    private const PIECES_COMPLETES = ['PIECE_TRANSPORT', 'FACTURE_TRANSIT_DEPART', 'FACTURE_TRANSIT_ARRIVEE', 'BON_LIVRAISON_ARRIVEE'];

    // ------------------------------------------------------------------
    // LTA et numéro
    // ------------------------------------------------------------------

    public function test_une_lta_valide_est_remise_en_forme(): void
    {
        $lta = Regles::verifierLta('05730215463');

        self::assertTrue($lta['ok']);
        self::assertSame('057-30215463', $lta['numero']);
    }

    public function test_une_lta_a_la_cle_fausse_indique_le_chiffre_attendu(): void
    {
        $lta = Regles::verifierLta('057-30215464');

        self::assertFalse($lta['ok']);
        self::assertStringContainsString('attendu 3', (string) $lta['message']);
        self::assertStringContainsString('11 chiffres', (string) Regles::verifierLta('057-3021546')['message']);
    }

    public function test_le_code_agence_est_le_code_iata_de_la_ville(): void
    {
        self::assertSame('ABJ', Regles::codeAgence(['code' => 'ABJ-FRET', 'city' => 'Abidjan']));
        self::assertSame('PAR', Regles::codeAgence(['code' => 'FRA', 'city' => 'Bobigny']));
        self::assertSame('DKR', Regles::codeAgence(['code' => 'SEN', 'city' => 'Dakar']));
        self::assertSame('YMR', Regles::codeAgence(['code_dossier' => 'ymr', 'city' => 'Abidjan']));
    }

    public function test_le_numero_suit_le_format_valide(): void
    {
        $prefixe = Regles::prefixeNumero('ABJ', new DateTimeImmutable('2026-09-15'));

        self::assertSame('ENV-ABJ-2609-0042', Regles::numeroDossier($prefixe, 42));
    }

    // ------------------------------------------------------------------
    // Colonnes 1 à 5
    // ------------------------------------------------------------------

    public function test_un_depart_complet_est_accepte_et_remis_en_forme(): void
    {
        $controle = Regles::controlerDossier($this->dossier(['numero_document' => '05730215463']), $this->airFrance(), '2026-09-15');

        self::assertSame([], $controle['erreurs']);
        self::assertSame('057-30215463', $controle['dossier']['numero_document']);
        self::assertSame('LTA_DIRECTE', $controle['dossier']['type_document']);
    }

    public function test_chaque_colonne_manquante_est_nommee(): void
    {
        $erreurs = Regles::controlerDossier(['mode_transport' => 'AERIEN'], null, '2026-09-15')['erreurs'];

        self::assertContains("Choisissez l'agence de départ.", $erreurs);
        self::assertContains('Choisissez la destination.', $erreurs);
        self::assertContains('Colonne 1 — Date de départ : saisissez la date de départ.', $erreurs);
        self::assertContains('Colonne 2 — Compagnie : choisissez la compagnie.', $erreurs);
        self::assertContains('Colonne 3 — LTA : saisissez son numéro.', $erreurs);
        self::assertContains('Colonne 4 — Nombre de colis : saisissez le nombre de colis du document.', $erreurs);
        self::assertContains('Colonne 5 — Poids total : saisissez le poids total du document.', $erreurs);
    }

    public function test_le_prefixe_de_la_lta_doit_etre_celui_de_la_compagnie(): void
    {
        $erreurs = Regles::controlerDossier($this->dossier(['numero_document' => '923-44100873']), $this->airFrance(), '2026-09-15')['erreurs'];

        self::assertContains('Colonne 3 — LTA : le préfixe 923 ne correspond pas à Air France (préfixe 057).', $erreurs);
    }

    public function test_la_colonne_3_prend_le_nom_du_document_du_mode(): void
    {
        $controle = Regles::controlerDossier(
            $this->dossier(['mode_transport' => 'MARITIME', 'numero_document' => 'abj0914']),
            ['id' => 9, 'name' => 'CMA CGM', 'type' => 'COMPAGNIE_MARITIME', 'prefixe_lta' => null],
            '2026-09-15'
        );

        self::assertSame([], $controle['erreurs'], 'Un connaissement ne suit pas la clé des LTA.');
        self::assertSame('ABJ0914', $controle['dossier']['numero_document']);
        self::assertSame('BL', $controle['dossier']['type_document']);
    }

    public function test_les_incoherences_de_trajet_de_date_et_de_compagnie_sont_refusees(): void
    {
        $erreurs = Regles::controlerDossier(
            $this->dossier(['agence_arrivee_id' => 3402, 'date_depart_effective' => '2026-09-20', 'mode_transport' => 'MARITIME', 'numero_document' => 'X1']),
            $this->airFrance(),
            '2026-09-15'
        )['erreurs'];

        self::assertContains("La destination doit être différente de l'agence de départ.", $erreurs);
        self::assertContains('Colonne 1 — Date de départ : la date de départ ne peut pas être dans le futur.', $erreurs);
        self::assertNotSame([], array_filter($erreurs, static fn (string $e): bool => str_starts_with($e, 'Colonne 2') && str_contains($e, 'Air France')));
    }

    // ------------------------------------------------------------------
    // Soumission
    // ------------------------------------------------------------------

    public function test_la_soumission_liste_les_colonnes_6_a_10_et_les_pieces(): void
    {
        $manques = Regles::controlerSoumission(
            ['statut' => 'EN_COURS'],
            [['poste' => 'TRANSIT_DEPART', 'montant_prevu' => 185000, 'prestataire_id' => null, 'prestataire_libre' => null]],
            [],
            []
        );

        self::assertContains('Colonne 6 — Transitaire au départ : indiquez le prestataire.', $manques);
        self::assertContains("Colonne 7 — Transitaire à destination : saisissez le montant (0 s'il n'y a pas de frais).", $manques);
        self::assertContains('Colonne 10 — Emballages : indiquez le type et le nombre.', $manques);
        self::assertContains('Pièce à joindre : document de la compagnie (lta, bl…).', $manques);
        self::assertContains('Pièce à joindre : facture du transitaire au départ.', $manques);
    }

    public function test_un_depart_complet_se_soumet(): void
    {
        self::assertSame([], Regles::controlerSoumission(
            ['statut' => 'EN_COURS'],
            $this->fraisComplets(),
            [['type' => 'Carton', 'quantite' => 30]],
            self::PIECES_COMPLETES
        ));
    }

    public function test_un_depart_deja_soumis_ne_se_resoumet_pas(): void
    {
        $manques = Regles::controlerSoumission(['statut' => 'SOUMIS'], $this->fraisComplets(), [['type' => 'Carton', 'quantite' => 30]], self::PIECES_COMPLETES);

        self::assertSame(['Ce départ a déjà été soumis au Directeur général.'], $manques);
    }

    // ------------------------------------------------------------------
    // Contrôle du Directeur général
    // ------------------------------------------------------------------

    public function test_l_exemple_du_boss_depasse_la_tolerance(): void
    {
        $ecart = Regles::ecartSaisie(['nb_colis_declare' => 160, 'poids_brut_kg' => 2477, 'colis_erp' => 141, 'poids_erp_kg' => 2000]);

        self::assertSame(19, $ecart['colis']);
        self::assertSame(477.0, $ecart['poids']);
        self::assertSame(23.9, $ecart['pourcent_poids']);
        self::assertTrue($ecart['depasse']);
    }

    public function test_2_pourcent_pile_reste_tolere(): void
    {
        $pile = Regles::ecartSaisie(['nb_colis_declare' => 50, 'poids_brut_kg' => 2040, 'colis_erp' => 50, 'poids_erp_kg' => 2000]);
        self::assertSame(2.0, $pile['pourcent_poids']);
        self::assertFalse($pile['depasse']);

        $au_dela = Regles::ecartSaisie(['nb_colis_declare' => 50, 'poids_brut_kg' => 2041, 'colis_erp' => 50, 'poids_erp_kg' => 2000]);
        self::assertTrue($au_dela['depasse']);
    }

    public function test_un_depart_sans_aucune_saisie_est_toujours_hors_tolerance(): void
    {
        $ecart = Regles::ecartSaisie(['nb_colis_declare' => 10, 'poids_brut_kg' => 120, 'colis_erp' => 0, 'poids_erp_kg' => 0]);

        self::assertNull($ecart['pourcent_poids']);
        self::assertTrue($ecart['depasse']);
        self::assertNull(Regles::ecartSaisie(['nb_colis_declare' => 10, 'poids_brut_kg' => 120]), 'Un départ repris sans saisie connue n\'a pas de contrôle.');
    }

    public function test_la_synthese_compte_les_colonnes_et_les_couts(): void
    {
        $dossier = $this->dossier(['taux_eur_xof' => 655.957, 'colis_erp' => 48, 'poids_erp_kg' => 612.5]);
        $frais = $this->fraisComplets();
        $frais[0]['montant_facture'] = 197000;
        $frais[0]['devise_facture'] = 'XOF';

        $synthese = Regles::synthese($dossier, $frais, [['type' => 'Carton', 'quantite' => 30], ['type' => 'Bôrô', 'quantite' => 18]], ['PIECE_TRANSPORT']);

        self::assertSame(10, $synthese['colonnes_renseignees']);
        self::assertSame(185000 + 290 * 655.957 + 0 + 120 * 655.957, $synthese['cout_prevu_xof']);
        self::assertSame(12000.0, $synthese['ecart_facture_xof']);
        self::assertTrue($synthese['ecart_facture_depasse'], '+6,5 % au-delà de la tolérance de 5 %.');
        self::assertSame('30 Carton, 18 Bôrô', $synthese['emballages_texte']);
        self::assertFalse($synthese['ecart_saisie']['depasse']);
        self::assertSame(1, $synthese['pieces_presentes']);
        self::assertSame(4, $synthese['pieces_attendues'], 'Document de la compagnie, et la facture de chaque colonne payante (6, 7 et 9).');
    }

    // ------------------------------------------------------------------
    // Historique et pièces
    // ------------------------------------------------------------------

    public function test_les_periodes_raccourcies(): void
    {
        $jour = new DateTimeImmutable('2026-09-15');

        self::assertSame(['periode' => 'ce_mois', 'du' => '2026-09-01', 'au' => '2026-09-30'], Regles::resoudrePeriode('', '', '', $jour));
        self::assertSame(['periode' => 'mois_dernier', 'du' => '2026-08-01', 'au' => '2026-08-31'], Regles::resoudrePeriode('mois_dernier', '', '', $jour));
        self::assertSame(['periode' => 'trimestre', 'du' => '2026-07-01', 'au' => '2026-09-30'], Regles::resoudrePeriode('trimestre', '', '', $jour));
        self::assertSame(['periode' => 'libre', 'du' => '2026-09-01', 'au' => '2026-09-15'], Regles::resoudrePeriode('libre', '2026-09-15', '2026-09-01', $jour));
    }

    public function test_seul_le_type_reel_du_fichier_compte(): void
    {
        self::assertSame('pdf', Regles::extensionDocument('application/pdf', 'lta.pdf'));
        self::assertNull(Regles::extensionDocument('application/x-dosexec', 'facture.pdf'));
        self::assertSame('xlsx', Regles::extensionDocument('application/zip', 'manifeste.xlsx'));
    }

    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $valeurs
     * @return array<string, mixed>
     */
    private function dossier(array $valeurs = []): array
    {
        return $valeurs + [
            'mode_transport' => 'AERIEN',
            'agence_depart_id' => 3402,
            'agence_arrivee_id' => 3400,
            'date_depart_effective' => '2026-09-02',
            'transporteur_id' => 2,
            'numero_document' => '057-30215463',
            'nb_colis_declare' => 48,
            'poids_brut_kg' => 612.5,
        ];
    }

    /** @return array<string, mixed> */
    private function airFrance(): array
    {
        return ['id' => 2, 'name' => 'Air France', 'type' => 'COMPAGNIE_AERIENNE', 'prefixe_lta' => '057'];
    }

    /** @return array<int, array<string, mixed>> */
    private function fraisComplets(): array
    {
        return [
            ['poste' => 'TRANSIT_DEPART', 'prestataire_id' => 4, 'montant_prevu' => 185000, 'devise' => 'XOF'],
            ['poste' => 'TRANSIT_ARRIVEE', 'prestataire_libre' => 'K2S', 'montant_prevu' => 290, 'devise' => 'EUR'],
            ['poste' => 'LIVRAISON_DEPART', 'montant_prevu' => 0, 'devise' => 'XOF'],
            ['poste' => 'LIVRAISON_ARRIVEE', 'prestataire_libre' => 'Livreur Paris', 'montant_prevu' => 120, 'devise' => 'EUR'],
        ];
    }
}
