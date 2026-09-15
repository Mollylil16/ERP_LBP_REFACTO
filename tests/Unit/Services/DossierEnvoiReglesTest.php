<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Colisage\DossierEnvoiRegles as Regles;
use DateTimeImmutable;
use Tests\TestCase;

/**
 * Règles des dossiers d'envoi, telles que validées le 15/09/2026
 * (cahier des charges CDC-ENV-01, sections 07 à 11 et critères de recette).
 */
final class DossierEnvoiReglesTest extends TestCase
{
    // ------------------------------------------------------------------
    // Clés de contrôle
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
    }

    public function test_une_lta_incomplete_rappelle_le_format(): void
    {
        $lta = Regles::verifierLta('057-3021546');

        self::assertFalse($lta['ok']);
        self::assertStringContainsString('11 chiffres', (string) $lta['message']);
        self::assertFalse(Regles::verifierLta('057-3021546A')['ok'], 'Une lettre dans une LTA de compagnie est refusée.');
    }

    public function test_les_lta_du_cahier_des_charges_sont_valides(): void
    {
        foreach (['923-44100873', '483-61204500', '057-30217806'] as $numero) {
            self::assertTrue(Regles::verifierLta($numero)['ok'], $numero);
        }
    }

    public function test_le_conteneur_suit_la_norme_iso_6346(): void
    {
        self::assertTrue(Regles::verifierConteneur('CSQU 305438 3')['ok']);
        self::assertSame('CSQU3054383', Regles::verifierConteneur('csqu3054383')['numero']);

        $faux = Regles::verifierConteneur('CSQU3054384');
        self::assertFalse($faux['ok']);
        self::assertStringContainsString('attendu 3', (string) $faux['message']);

        self::assertFalse(Regles::verifierConteneur('CSQ3054383')['ok']);
    }

    // ------------------------------------------------------------------
    // Numéro de dossier
    // ------------------------------------------------------------------

    public function test_le_code_agence_est_le_code_iata_de_la_ville(): void
    {
        self::assertSame('ABJ', Regles::codeAgence(['code' => 'ABJ-FRET', 'city' => 'Abidjan']));
        self::assertSame('ABJ', Regles::codeAgence(['code' => 'ABO-DOK', 'city' => 'Abidjan']));
        self::assertSame('PAR', Regles::codeAgence(['code' => 'FRA', 'city' => 'Bobigny']), 'FRA est aussi Francfort.');
        self::assertSame('DKR', Regles::codeAgence(['code' => 'SEN', 'city' => 'Dakar']));
        self::assertSame('SPY', Regles::codeAgence(['code' => 'SPY', 'city' => 'San Pedro']));
    }

    public function test_le_code_parametre_prime_sur_la_ville(): void
    {
        self::assertSame('YMR', Regles::codeAgence(['code_dossier' => 'ymr', 'code' => 'ABJ-HQ', 'city' => 'Abidjan']));
        self::assertSame('LYO', Regles::codeAgence(['code' => 'LYO-01', 'city' => 'Lyon']), 'Ville inconnue : lettres du code.');
        self::assertSame('LBP', Regles::codeAgence([]));
    }

    public function test_le_numero_suit_le_format_valide(): void
    {
        $prefixe = Regles::prefixeNumero('ABJ', new DateTimeImmutable('2026-09-15 10:00:00'));

        self::assertSame('ENV-ABJ-2609', $prefixe);
        self::assertSame('ENV-ABJ-2609-0042', Regles::numeroDossier($prefixe, 42));
        self::assertSame('ENV-ABJ-2609-0042_PIECE_TRANSPORT_1', Regles::nomFichier('ENV-ABJ-2609-0042', 'PIECE_TRANSPORT', 1));
    }

    // ------------------------------------------------------------------
    // Statuts
    // ------------------------------------------------------------------

    public function test_le_statut_suit_la_saisie(): void
    {
        self::assertSame('BROUILLON', Regles::statutProgression([]));
        self::assertSame('BROUILLON', Regles::statutProgression(['transporteur_id' => 2]));
        self::assertSame('RESERVE', Regles::statutProgression(['transporteur_id' => 2, 'numero_document' => '057-30215463']));
        self::assertSame('PARTI', Regles::statutProgression(['date_depart_effective' => '2026-09-02']));
        self::assertSame('ARRIVE', Regles::statutProgression(['date_depart_effective' => '2026-09-02', 'date_arrivee' => '2026-09-03']));
        self::assertSame('LIVRE', Regles::statutProgression(['date_arrivee' => '2026-09-03', 'date_livraison' => '2026-09-04']));
    }

    public function test_un_dossier_renvoye_reste_a_corriger_et_un_dossier_soumis_ne_bouge_pas(): void
    {
        $livre = ['date_depart_effective' => '2026-09-02', 'date_arrivee' => '2026-09-03', 'date_livraison' => '2026-09-04'];

        self::assertSame('A_CORRIGER', Regles::statutApresEnregistrement('A_CORRIGER', $livre));
        self::assertSame('SOUMIS', Regles::statutApresEnregistrement('SOUMIS', $livre));
        self::assertSame('LIVRE', Regles::statutApresEnregistrement('ARRIVE', $livre));
    }

    // ------------------------------------------------------------------
    // Contrôles à l'enregistrement
    // ------------------------------------------------------------------

    public function test_le_prefixe_doit_correspondre_a_la_compagnie(): void
    {
        $controle = Regles::controlerSaisie(
            $this->dossier(['numero_document' => '923-44100873']),
            [],
            ['id' => 2, 'name' => 'Air France', 'type' => 'COMPAGNIE_AERIENNE', 'prefixe_lta' => '057'],
            '2026-09-15'
        );

        self::assertContains('Le préfixe 923 ne correspond pas à Air France (préfixe 057).', $controle['erreurs']);
    }

    public function test_un_transporteur_d_un_autre_mode_est_refuse(): void
    {
        $controle = Regles::controlerSaisie(
            $this->dossier(['mode_transport' => 'MARITIME', 'type_document' => 'BL', 'numero_document' => 'ABJ0914']),
            [],
            ['id' => 1, 'name' => 'Corsair', 'type' => 'COMPAGNIE_AERIENNE', 'prefixe_lta' => ''],
            '2026-09-15'
        );

        self::assertNotSame([], array_filter($controle['erreurs'], static fn (string $e): bool => str_contains($e, 'Corsair')));
    }

    public function test_un_transporteur_sans_type_est_accepte(): void
    {
        $controle = Regles::controlerSaisie($this->dossier(), [], ['id' => 4, 'name' => 'Sealogis', 'type' => '', 'prefixe_lta' => null], '2026-09-15');

        self::assertSame([], $controle['erreurs']);
    }

    public function test_les_tranches_doivent_totaliser_le_document(): void
    {
        $bon = Regles::controlerSaisie(
            $this->dossier(['nb_colis_declare' => 57]),
            [['nb_colis' => 40, 'reference' => 'HF 520'], ['nb_colis' => 17, 'reference' => 'HF 522']],
            null,
            '2026-09-15'
        );
        self::assertSame([], $bon['erreurs']);
        self::assertSame('VOL', $bon['tranches'][0]['type']);

        $faux = Regles::controlerSaisie(
            $this->dossier(['nb_colis_declare' => 57]),
            [['nb_colis' => 40], ['nb_colis' => 15]],
            null,
            '2026-09-15'
        );
        self::assertContains('Les tranches totalisent 55 colis alors que le document en déclare 57.', $faux['erreurs']);
    }

    public function test_un_conteneur_faux_est_refuse_dans_sa_tranche(): void
    {
        $controle = Regles::controlerSaisie(
            $this->dossier(['mode_transport' => 'MARITIME', 'type_document' => 'BL', 'numero_document' => 'ABJ0914']),
            [['reference' => 'CSQU3054384', 'nb_colis' => null]],
            null,
            '2026-09-15'
        );

        self::assertNotSame([], array_filter($controle['erreurs'], static fn (string $e): bool => str_starts_with($e, 'Tranche 1')));
    }

    public function test_une_lta_fille_exige_son_transitaire_mais_pas_de_cle(): void
    {
        $controle = Regles::controlerSaisie(
            $this->dossier(['type_document' => 'LTA_FILLE', 'numero_document' => 'SLG-0917', 'emetteur_document_id' => null]),
            [],
            null,
            '2026-09-15'
        );

        self::assertContains('Choisissez le transitaire qui a émis la LTA fille.', $controle['erreurs']);
        self::assertCount(1, $controle['erreurs'], 'Le format de la LTA fille est libre : aucune clé contrôlée.');
    }

    public function test_les_incoherences_de_poids_et_de_dates_sont_refusees(): void
    {
        $controle = Regles::controlerSaisie(
            $this->dossier([
                'poids_brut_kg' => 402.0,
                'poids_taxable_kg' => 390.0,
                'date_depart_effective' => '2026-09-20',
                'date_arrivee' => '2026-09-10',
            ]),
            [],
            null,
            '2026-09-15'
        );

        self::assertContains('Le poids taxable ne peut pas être inférieur au poids brut.', $controle['erreurs']);
        self::assertContains('La date de départ effective ne peut pas être dans le futur : utilisez la date prévue.', $controle['erreurs']);
        self::assertContains("La date d'arrivée ne peut pas précéder la date de départ.", $controle['erreurs']);
    }

    public function test_sans_frais_coche_avec_un_montant_est_refuse(): void
    {
        $erreurs = Regles::controlerFrais(['LIVRAISON_DEPART' => ['montant_prevu' => 25000.0, 'sans_frais' => true]], [['libelle' => null, 'montant_prevu' => 10.0]]);

        self::assertCount(2, $erreurs);
    }

    // ------------------------------------------------------------------
    // Pièces, écarts et soumission
    // ------------------------------------------------------------------

    public function test_les_pieces_attendues_suivent_les_postes_payants(): void
    {
        $attendues = Regles::piecesAttendues([
            ['poste' => 'TRANSIT_DEPART', 'montant_prevu' => 185000],
            ['poste' => 'LIVRAISON_DEPART', 'montant_prevu' => null, 'sans_frais' => 1],
            ['poste' => 'AUTRE', 'montant_prevu' => 5000],
        ]);

        self::assertSame(['PIECE_TRANSPORT', 'MANIFESTE', 'FACTURE_TRANSIT_DEPART'], $attendues);
    }

    public function test_un_ecart_de_facture_au_dela_de_5_pourcent_est_signale(): void
    {
        $taux = 655.957;

        $pile = Regles::ecartFacture(['montant_prevu' => 200, 'devise' => 'EUR', 'montant_facture' => 210, 'devise_facture' => 'EUR'], $taux);
        self::assertSame(5.0, $pile['pourcent']);
        self::assertFalse($pile['depasse'], '5 % pile reste toléré.');

        $au_dela = Regles::ecartFacture(['montant_prevu' => 200, 'devise' => 'EUR', 'montant_facture' => 211, 'devise_facture' => 'EUR'], $taux);
        self::assertTrue($au_dela['depasse']);

        self::assertNull(Regles::ecartFacture(['montant_prevu' => 200, 'devise' => 'EUR'], $taux), 'Sans facture, pas d\'écart.');
    }

    public function test_la_soumission_liste_tout_ce_qui_manque(): void
    {
        $manques = Regles::controlerSoumission(['statut' => 'PARTI', 'taux_eur_xof' => 655.957], [], [], [], null);

        self::assertStringContainsString('doit être livré', $manques[0]);
        self::assertContains('Pièce manquante : pièce de transport.', $manques);
        self::assertContains("Indiquez le type et le nombre d'emballages.", $manques);
        self::assertContains('Fret transporteur : saisissez un montant, ou cochez « sans frais ».', $manques);
    }

    public function test_un_dossier_complet_se_soumet(): void
    {
        [$dossier, $frais, $emballages, $pieces] = $this->dossierComplet();

        self::assertSame([], Regles::controlerSoumission($dossier, $frais, $emballages, $pieces, 48));
    }

    public function test_un_ecart_avec_le_pointage_doit_etre_commente(): void
    {
        [$dossier, $frais, $emballages, $pieces] = $this->dossierComplet();

        $manques = Regles::controlerSoumission($dossier, $frais, $emballages, $pieces, 46);
        self::assertSame(['Écart de 2 colis entre le document (48) et le pointage (46) : commentez-le.'], $manques);

        $dossier['commentaire_ecart'] = '2 colis restés à quai, repartis sur le vol suivant.';
        self::assertSame([], Regles::controlerSoumission($dossier, $frais, $emballages, $pieces, 46));
    }

    public function test_un_ecart_de_facture_doit_etre_commente_pour_soumettre(): void
    {
        [$dossier, $frais, $emballages, $pieces] = $this->dossierComplet();
        $frais[0]['montant_facture'] = 1100000;

        $manques = Regles::controlerSoumission($dossier, $frais, $emballages, $pieces, 48);
        self::assertCount(1, $manques);
        self::assertStringContainsString('Fret transporteur : écart de facture de 10', $manques[0]);
    }

    public function test_la_synthese_retient_le_facture_quand_il_existe(): void
    {
        $synthese = Regles::synthese(
            ['taux_eur_xof' => 655.957, 'poids_brut_kg' => 402.0, 'poids_taxable_kg' => 418.5, 'nb_colis_declare' => 31],
            [
                ['poste' => 'FRET', 'montant_prevu' => 1000000, 'devise' => 'XOF', 'montant_facture' => 1012000, 'devise_facture' => 'XOF'],
                ['poste' => 'TRANSIT_ARRIVEE', 'montant_prevu' => 290, 'devise' => 'EUR'],
            ],
            [['type' => 'Carton', 'quantite' => 22], ['type' => 'Valise', 'quantite' => 4]],
            ['PIECE_TRANSPORT'],
            31
        );

        self::assertSame(1190227.53, $synthese['cout_prevu_xof']);
        self::assertSame(1202227.53, $synthese['cout_retenu_xof']);
        self::assertSame(12000.0, $synthese['ecart_facture_xof']);
        self::assertSame(2873.0, $synthese['cout_par_kg'], 'Coût au kilo taxable, arrondi.');
        self::assertSame(1, $synthese['pieces_presentes']);
        self::assertSame(4, $synthese['pieces_attendues']);
        self::assertSame(0, $synthese['ecart_colis']);
        self::assertSame('22 × Carton, 4 × Valise', $synthese['emballages_texte']);
    }

    // ------------------------------------------------------------------
    // Historique et pièces jointes
    // ------------------------------------------------------------------

    public function test_les_periodes_raccourcies(): void
    {
        $jour = new DateTimeImmutable('2026-09-15');

        self::assertSame(['periode' => 'ce_mois', 'du' => '2026-09-01', 'au' => '2026-09-30'], Regles::resoudrePeriode('', '', '', $jour));
        self::assertSame(['periode' => 'mois_dernier', 'du' => '2026-08-01', 'au' => '2026-08-31'], Regles::resoudrePeriode('mois_dernier', '', '', $jour));
        self::assertSame(['periode' => 'trimestre', 'du' => '2026-07-01', 'au' => '2026-09-30'], Regles::resoudrePeriode('trimestre', '', '', $jour));
        self::assertSame(['periode' => 'annee', 'du' => '2026-01-01', 'au' => '2026-12-31'], Regles::resoudrePeriode('annee', '', '', $jour));
        self::assertSame(['periode' => 'libre', 'du' => '2026-09-01', 'au' => '2026-09-15'], Regles::resoudrePeriode('libre', '2026-09-15', '2026-09-01', $jour));
        self::assertSame('ce_mois', Regles::resoudrePeriode('libre', '2026-02-30', '2026-03-01', $jour)['periode'], 'Une date impossible retombe sur le mois en cours.');
    }

    public function test_seul_le_type_reel_du_fichier_compte(): void
    {
        self::assertSame('pdf', Regles::extensionDocument('application/pdf', 'lta.pdf'));
        self::assertNull(Regles::extensionDocument('application/x-dosexec', 'facture.pdf'), 'Un .exe renommé en .pdf reste refusé.');
        self::assertSame('xlsx', Regles::extensionDocument('application/zip', 'manifeste.xlsx'));
        self::assertNull(Regles::extensionDocument('application/zip', 'archive.zip'));
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
            'transporteur_id' => 2,
            'type_document' => 'LTA_DIRECTE',
            'numero_document' => '057-30215463',
            'emetteur_document_id' => null,
            'document_principal' => null,
            'nb_colis_declare' => null,
            'poids_brut_kg' => null,
            'poids_taxable_kg' => null,
            'volume_m3' => null,
            'date_depart_effective' => null,
            'date_arrivee' => null,
            'date_livraison' => null,
        ];
    }

    /**
     * @return array{0:array<string, mixed>, 1:array<int, array<string, mixed>>, 2:array<int, array<string, mixed>>, 3:array<int, string>}
     */
    private function dossierComplet(): array
    {
        $dossier = $this->dossier([
            'statut' => 'LIVRE',
            'nb_colis_declare' => 48,
            'poids_brut_kg' => 612.5,
            'date_depart_effective' => '2026-09-02',
            'date_arrivee' => '2026-09-03',
            'date_livraison' => '2026-09-05',
            'taux_eur_xof' => 655.957,
            'commentaire_ecart' => null,
        ]);

        $frais = [
            ['poste' => 'FRET', 'montant_prevu' => 1000000, 'devise' => 'XOF', 'montant_facture' => 1000000, 'devise_facture' => 'XOF'],
            ['poste' => 'TRANSIT_DEPART', 'montant_prevu' => 185000, 'devise' => 'XOF'],
            ['poste' => 'TRANSIT_ARRIVEE', 'montant_prevu' => 290, 'devise' => 'EUR'],
            ['poste' => 'LIVRAISON_DEPART', 'montant_prevu' => null, 'sans_frais' => 1],
            ['poste' => 'LIVRAISON_ARRIVEE', 'montant_prevu' => 120, 'devise' => 'EUR'],
        ];

        $pieces = ['PIECE_TRANSPORT', 'MANIFESTE', 'FACTURE_FRET', 'FACTURE_TRANSIT_DEPART', 'FACTURE_TRANSIT_ARRIVEE', 'BON_LIVRAISON_ARRIVEE'];

        return [$dossier, $frais, [['type' => 'Carton', 'quantite' => 30]], $pieces];
    }
}
