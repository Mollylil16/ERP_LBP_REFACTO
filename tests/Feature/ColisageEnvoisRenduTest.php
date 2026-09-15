<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Colisage\DossierEnvoiRegles as Regles;
use App\View\Components\ColisageEnvois;
use App\View\Components\ColisageEnvoisExport;
use Tests\TestCase;

/**
 * Rendu des écrans et exports des départs préparés par l'agent export.
 *
 * Ce qui compte : les dix colonnes dans l'ordre, plus aucune case à cocher,
 * et les chiffres de la saisie des colis visibles par le seul Directeur général.
 */
final class ColisageEnvoisRenduTest extends TestCase
{
    public function test_preparer_un_depart_presente_les_dix_colonnes_sans_cases_a_cocher(): void
    {
        $html = ColisageEnvois::preparerPage($this->preparation());

        for ($colonne = 1; $colonne <= 10; $colonne++) {
            self::assertStringContainsString('<span class="lbp-envoi-num">' . $colonne . '</span>', $html, 'Colonne ' . $colonne . ' absente.');
        }

        self::assertStringContainsString('departs/enregistrer', $html);
        self::assertStringContainsString('enctype="multipart/form-data"', $html);
        self::assertStringContainsString('name="document_compagnie"', $html);
        self::assertStringContainsString('name="frais[TRANSIT_ARRIVEE][prestataire]"', $html);
        self::assertStringContainsString('name="emballages[0][type]"', $html);
        self::assertStringContainsString('finea-shell', $html, 'Même présentation que les écrans Finance.');
        self::assertStringNotContainsString('colis_ids', $html, 'Plus aucun colis à cocher.');
        self::assertStringNotContainsString('Saisie des colis', $html);
    }

    public function test_preparer_un_depart_liste_les_departs_en_cours(): void
    {
        $donnees = $this->preparation();
        $donnees['en_cours'] = [$this->depart()];

        $html = html_entity_decode(ColisageEnvois::preparerPage($donnees), ENT_QUOTES);

        self::assertStringContainsString('Départs en cours', $html);
        self::assertStringContainsString('ENV-ABJ-2609-0001', $html);
        self::assertStringContainsString('Compléter', $html);
    }

    public function test_le_directeur_ne_voit_pas_de_formulaire_de_depart(): void
    {
        $donnees = ['peut_creer' => false, 'voit_tout' => true, 'est_valideur' => true] + $this->preparation();

        $html = ColisageEnvois::preparerPage($donnees);

        self::assertStringNotContainsString('departs/enregistrer', $html);
        self::assertStringContainsString('envois/a-valider', $html);
    }

    public function test_un_depart_refuse_reaffiche_la_saisie_et_les_erreurs(): void
    {
        $donnees = $this->preparation();
        $donnees['dossier'] = ['mode_transport' => 'AERIEN', 'numero_document' => '057-30215464', 'nb_colis_declare' => 48];
        $donnees['erreurs'] = ['Colonne 3 — LTA : le dernier chiffre de la LTA 057-30215464 ne correspond pas : attendu 3.'];

        $html = html_entity_decode(ColisageEnvois::preparerPage($donnees), ENT_QUOTES);

        self::assertStringContainsString("Le départ n'a pas été enregistré", $html);
        self::assertStringContainsString('attendu 3', $html);
        self::assertStringContainsString('value="057-30215464"', $html);
    }

    public function test_la_fiche_de_l_agent_se_complete_sans_montrer_la_saisie(): void
    {
        $html = html_entity_decode(ColisageEnvois::fichePage($this->fiche(
            ['statut' => 'EN_COURS'],
            ['modifier' => true, 'soumettre' => true],
            ['Colonne 7 — Transitaire à destination : saisissez le montant (0 s\'il n\'y a pas de frais).']
        )), ENT_QUOTES);

        self::assertStringContainsString('DÉPART EN COURS', $html);
        self::assertStringContainsString('envois/7/modifier', $html);
        self::assertStringContainsString('Avant de soumettre, il reste', $html);
        self::assertStringNotContainsString('envois/7/soumettre', $html, 'Pas de soumission tant que le départ est incomplet.');
        self::assertStringContainsString('envois/7/documents', $html);
        self::assertStringNotContainsString('Saisie des colis', $html, "L'agent ne voit pas la saisie.");
        self::assertStringNotContainsString('Agents de saisie', $html);
    }

    public function test_un_depart_complet_se_soumet_depuis_sa_fiche(): void
    {
        $html = ColisageEnvois::fichePage($this->fiche(['statut' => 'EN_COURS'], ['modifier' => true, 'soumettre' => true], []));

        self::assertStringContainsString('envois/7/soumettre', $html);
    }

    public function test_le_directeur_voit_l_ecart_avec_la_saisie_et_qui_appeler(): void
    {
        $fiche = $this->fiche(
            ['statut' => 'SOUMIS', 'soumis_le' => '2026-09-15 09:00:00', 'nb_colis_declare' => 160, 'poids_brut_kg' => '2477.0', 'colis_erp' => 141, 'poids_erp_kg' => '2000.0'],
            ['valider' => true, 'renvoyer' => true, 'voit_saisie' => true],
            []
        );
        $fiche['agents_saisie'] = [['agent' => 'GBOKO GRACE', 'enregistrements' => 82, 'colis' => 82, 'poids' => 1100.0]];

        $html = html_entity_decode(ColisageEnvois::fichePage($fiche), ENT_QUOTES);

        self::assertStringContainsString('Contrôle : document de la compagnie et saisie des colis', $html);
        self::assertStringContainsString('+477,0 kg · +23,9 %', $html);
        self::assertStringContainsString('Écart au-delà de 2 %', $html);
        self::assertStringContainsString('GBOKO GRACE', $html);
        self::assertMatchesRegularExpression('/<textarea[^>]*name="commentaire"[^>]*required/', $html, 'Au-delà de 2 %, le commentaire est exigé.');
        self::assertStringContainsString('envois/7/renvoyer', $html);
        self::assertStringContainsString('EN ATTENTE DU DIRECTEUR GÉNÉRAL', $html);
    }

    public function test_dans_la_tolerance_le_commentaire_du_directeur_est_facultatif(): void
    {
        $fiche = $this->fiche(
            ['statut' => 'SOUMIS', 'nb_colis_declare' => 48, 'poids_brut_kg' => '612.5', 'colis_erp' => 48, 'poids_erp_kg' => '605.0'],
            ['valider' => true, 'renvoyer' => true, 'voit_saisie' => true],
            []
        );

        $html = ColisageEnvois::fichePage($fiche);

        self::assertStringContainsString('dans la tolérance de 2 %', html_entity_decode($html, ENT_QUOTES));
        self::assertDoesNotMatchRegularExpression('/<textarea[^>]*name="commentaire"[^>]*required/', $html);
    }

    public function test_la_liste_a_valider_compare_document_et_saisie(): void
    {
        $depart = $this->depart(['statut' => 'SOUMIS', 'soumis_le' => '2026-09-15 09:00:00', 'colis_erp' => 141, 'poids_erp_kg' => '2000.0', 'nb_colis_declare' => 160, 'poids_brut_kg' => '2477.0'], true);

        $html = html_entity_decode(ColisageEnvois::aValiderPage(['dossiers' => [$depart]]), ENT_QUOTES);

        self::assertStringContainsString('saisie : 2 000,0 kg', $html);
        self::assertStringContainsString('Examiner', $html);
    }

    public function test_l_historique_de_l_agent_exporte_ses_filtres_sans_la_saisie(): void
    {
        $html = html_entity_decode(ColisageEnvois::historiquePage($this->historique(false)), ENT_QUOTES);

        self::assertStringContainsString('historique/pdf?periode=libre&du=2026-09-01&au=2026-09-15', $html);
        self::assertStringContainsString('historique/excel?periode=libre&du=2026-09-01&au=2026-09-15', $html);
        self::assertStringContainsString('rh-personnel-filters', $html);
        self::assertStringNotContainsString('Saisie colis', $html);
        self::assertStringNotContainsString('name="agent"', $html);
        self::assertStringContainsString('1 départ(s)', $html);
    }

    public function test_l_historique_du_directeur_ajoute_la_saisie_et_le_filtre_agent(): void
    {
        $html = ColisageEnvois::historiquePage($this->historique(true));

        self::assertStringContainsString('Saisie colis', $html);
        self::assertStringContainsString('name="agent"', $html);
    }

    public function test_l_export_excel_suit_les_dix_colonnes_et_masque_la_saisie_a_l_agent(): void
    {
        $agent = ColisageEnvoisExport::historiqueExcel($this->historique(false));
        $dg = ColisageEnvoisExport::historiqueExcel($this->historique(true));

        self::assertStringContainsString('<td>057-30215463</td>', $agent);
        self::assertStringContainsString('x:num="612.5"', $agent);
        self::assertStringContainsString('<th>10. Emballages</th>', $agent);
        self::assertStringNotContainsString('Saisie : poids kg', $agent);
        self::assertStringContainsString('Saisie : poids kg', $dg);
    }

    public function test_les_exports_pdf_rappellent_les_filtres_et_prevoient_la_signature(): void
    {
        $pdf = html_entity_decode(ColisageEnvoisExport::historiquePdf($this->historique(false)), ENT_QUOTES);
        self::assertStringContainsString('Période du 01/09/2026 au 15/09/2026', $pdf);
        self::assertStringContainsString('Vérifié par', $pdf);

        $fiche = $this->fiche(['statut' => 'VALIDE', 'valide_le' => '2026-09-16 10:00:00', 'valide_par' => 'KADJO'], [], []);
        $fiche['edite_par'] = 'KADJO';
        $fichePdf = html_entity_decode(ColisageEnvoisExport::fichePdf($fiche), ENT_QUOTES);
        self::assertStringContainsString('Les dix colonnes', $fichePdf);
        self::assertStringContainsString('Validé le 16/09/2026', $fichePdf);
        self::assertStringNotContainsString('Contrôle : document', $fichePdf, "La fiche de l'agent ne montre pas la saisie.");
    }

    public function test_la_page_des_prestataires_signale_les_types_a_preciser(): void
    {
        $html = html_entity_decode(ColisageEnvois::prestatairesPage(['prestataires' => $this->prestataires()]), ENT_QUOTES);

        self::assertStringContainsString('Type à préciser', $html);
        self::assertStringContainsString('form="prestataire-4"', $html);
    }

    public function test_aucun_ecran_ne_laisse_passer_de_html_non_echappe(): void
    {
        $injection = '<script>alert(1)</script>';
        $valeurs = ['numero' => $injection, 'transporteur' => $injection, 'numero_document' => $injection, 'responsable' => $injection,
            'agence_depart' => $injection, 'agence_arrivee' => $injection, 'motif_renvoi' => $injection, 'commentaire_dg' => $injection];
        $depart = $this->depart($valeurs, true);
        $prestataires = [['id' => 1, 'type' => '', 'name' => $injection, 'country' => $injection, 'prefixe_lta' => null, 'is_active' => 1]];

        $fiche = $this->fiche($valeurs + ['statut' => 'A_CORRIGER', 'colis_erp' => 1, 'poids_erp_kg' => 1], ['modifier' => true, 'valider' => true, 'voit_saisie' => true], [$injection]);
        $fiche['documents'][0]['nom_fichier'] = $injection;
        $fiche['documents'][0]['original_name'] = $injection;
        $fiche['journal'] = [['created_at' => '2026-09-15 10:00:00', 'action' => $injection, 'champ' => $injection, 'ancienne_valeur' => $injection, 'nouvelle_valeur' => $injection, 'motif' => $injection, 'par' => $injection]];
        $fiche['agents_saisie'] = [['agent' => $injection, 'enregistrements' => 1, 'colis' => 1, 'poids' => 1.0]];
        $fiche['prestataires'] = $prestataires;
        $fiche['erreurs'] = [$injection];
        $fiche['edite_par'] = $injection;

        $historique = $this->historique(true);
        $historique['dossiers'] = [$depart];
        $historique['libelles_filtres'] = [$injection];
        $historique['edite_par'] = $injection;
        $historique['agents'] = [['id' => 1, 'full_name' => $injection]];

        $preparation = $this->preparation();
        $preparation['prestataires'] = $prestataires;
        $preparation['agences'] = [['id' => 1, 'name' => $injection]];
        $preparation['en_cours'] = [$depart];
        $preparation['erreurs'] = [$injection];

        $ecrans = [
            'préparer' => ColisageEnvois::preparerPage($preparation),
            'fiche' => ColisageEnvois::fichePage($fiche),
            'à valider' => ColisageEnvois::aValiderPage(['dossiers' => [$depart]]),
            'historique' => ColisageEnvois::historiquePage($historique),
            'prestataires' => ColisageEnvois::prestatairesPage(['prestataires' => $prestataires]),
            'export pdf' => ColisageEnvoisExport::historiquePdf($historique),
            'export excel' => ColisageEnvoisExport::historiqueExcel($historique),
            'fiche pdf' => ColisageEnvoisExport::fichePdf($fiche),
        ];

        foreach ($ecrans as $nom => $html) {
            self::assertStringNotContainsString($injection, $html, "L'écran {$nom} laisse passer du HTML.");
        }
    }

    public function test_les_vues_ne_contiennent_que_l_appel_au_composant(): void
    {
        $vues = glob(BASE_PATH . '/views/colisage/envois/*.php') ?: [];
        self::assertCount(8, $vues);

        foreach ($vues as $vue) {
            $source = (string) file_get_contents($vue);
            self::assertDoesNotMatchRegularExpression('/<(div|table|form|section|p|span|html)\b/i', $source, basename($vue) . ' contient du HTML brut.');
            self::assertMatchesRegularExpression('/ColisageEnvois(Export)?::/', $source);
        }
    }

    // ------------------------------------------------------------------
    // Données d'exemple
    // ------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function preparation(): array
    {
        return [
            'dossier' => ['mode_transport' => 'AERIEN', 'statut' => 'EN_COURS'],
            'frais' => [],
            'emballages' => [],
            'agences' => [['id' => 3402, 'name' => 'Aéroport Port Bouët Fret'], ['id' => 3400, 'name' => 'Paris Bobigny']],
            'prestataires' => $this->prestataires(),
            'en_cours' => [],
            'erreurs' => [],
            'peut_creer' => true,
            'voit_tout' => false,
            'est_valideur' => false,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function prestataires(): array
    {
        return [
            ['id' => 2, 'type' => 'COMPAGNIE_AERIENNE', 'name' => 'Air France', 'country' => null, 'prefixe_lta' => '057', 'is_active' => 1],
            ['id' => 4, 'type' => '', 'name' => 'Sealogis', 'country' => null, 'prefixe_lta' => null, 'is_active' => 1],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function frais(): array
    {
        return [
            ['poste' => 'TRANSIT_DEPART', 'prestataire' => 'Sealogis', 'prestataire_id' => 4, 'montant_prevu' => '185000.00', 'devise' => 'XOF', 'montant_facture' => '185000.00', 'devise_facture' => 'XOF', 'numero_facture' => 'SLG-12'],
            ['poste' => 'TRANSIT_ARRIVEE', 'prestataire_libre' => 'K2S', 'montant_prevu' => '290.00', 'devise' => 'EUR'],
            ['poste' => 'LIVRAISON_DEPART', 'montant_prevu' => '0.00', 'devise' => 'XOF'],
            ['poste' => 'LIVRAISON_ARRIVEE', 'prestataire_libre' => 'Livreur Paris', 'montant_prevu' => '120.00', 'devise' => 'EUR'],
        ];
    }

    /**
     * @param array<string, mixed> $valeurs
     * @return array<string, mixed>
     */
    private function brut(array $valeurs = []): array
    {
        return $valeurs + [
            'id' => 7, 'numero' => 'ENV-ABJ-2609-0001', 'mode_transport' => 'AERIEN', 'statut' => 'EN_COURS', 'responsable_id' => 17,
            'responsable' => 'Agent Export', 'agence_depart_id' => 3402, 'agence_depart' => 'Aéroport Port Bouët Fret',
            'agence_arrivee_id' => 3400, 'agence_arrivee' => 'Paris Bobigny', 'expedition_id' => 5,
            'transporteur_id' => 2, 'transporteur' => 'Air France', 'type_document' => 'LTA_DIRECTE', 'numero_document' => '057-30215463',
            'date_depart_effective' => '2026-09-02', 'nb_colis_declare' => 48, 'poids_brut_kg' => '612.5',
            'taux_eur_xof' => '655.957000', 'motif_renvoi' => null, 'commentaire_dg' => null, 'soumis_le' => null, 'valide_le' => null, 'valide_par' => null,
        ];
    }

    /**
     * @param array<string, mixed> $valeurs
     * @return array<string, mixed>
     */
    private function depart(array $valeurs = [], bool $avecSaisie = false): array
    {
        $depart = $this->brut($valeurs);
        if (!$avecSaisie) {
            unset($depart['colis_erp'], $depart['poids_erp_kg']);
        }
        $depart['frais'] = $this->frais();
        $depart['emballages'] = [['type' => 'Carton', 'quantite' => 30], ['type' => 'Bôrô', 'quantite' => 18]];
        $depart['synthese'] = Regles::synthese($depart, $depart['frais'], $depart['emballages'], ['PIECE_TRANSPORT']);

        return $depart;
    }

    /**
     * @param array<string, mixed> $valeurs
     * @param array<string, bool> $droits
     * @param array<int, string> $manques
     * @return array<string, mixed>
     */
    private function fiche(array $valeurs, array $droits, array $manques): array
    {
        $dossier = $this->brut($valeurs);
        $emballages = [['type' => 'Carton', 'quantite' => 30]];

        return [
            'dossier' => $dossier,
            'frais' => $this->frais(),
            'emballages' => $emballages,
            'documents' => [['id' => 3, 'type_document' => 'PIECE_TRANSPORT', 'nom_fichier' => 'ENV-ABJ-2609-0001_PIECE_TRANSPORT_1.pdf', 'original_name' => 'lta.pdf', 'size_bytes' => 245000, 'uploaded_at' => '2026-09-02 18:00:00', 'depose_par' => 'Agent Export']],
            'synthese' => Regles::synthese($dossier, $this->frais(), $emballages, ['PIECE_TRANSPORT']),
            'manques' => $manques,
            'journal' => [['created_at' => '2026-09-02 18:00:00', 'action' => 'CREATION', 'champ' => null, 'ancienne_valeur' => null, 'nouvelle_valeur' => 'ENV-ABJ-2609-0001', 'motif' => null, 'par' => 'Agent Export']],
            'agents_saisie' => [],
            'erreurs' => [],
            'agences' => [],
            'prestataires' => $this->prestataires(),
            'droits' => $droits + ['modifier' => false, 'soumettre' => false, 'valider' => false, 'renvoyer' => false, 'rouvrir' => false, 'voit_saisie' => false],
        ];
    }

    /** @return array<string, mixed> */
    private function historique(bool $directeur): array
    {
        $depart = $this->depart($directeur ? ['colis_erp' => 48, 'poids_erp_kg' => '605.0'] : [], $directeur);

        return [
            'filtres' => ['periode' => 'libre', 'du' => '2026-09-01', 'au' => '2026-09-15', 'agent' => null, 'q' => ''],
            'dossiers' => [$depart],
            'totaux' => [
                'dossiers' => 1, 'colis' => 48, 'poids' => 612.5, 'colis_erp' => $directeur ? 48 : 0, 'poids_erp' => $directeur ? 605.0 : 0.0,
                'cout_prevu' => (float) $depart['synthese']['cout_prevu_xof'], 'cout_facture' => 185000.0, 'ecart_facture' => 0.0,
                'postes' => ['TRANSIT_DEPART' => 185000.0, 'TRANSIT_ARRIVEE' => 190227.53, 'LIVRAISON_DEPART' => 0.0, 'LIVRAISON_ARRIVEE' => 78714.84],
            ],
            'agents' => $directeur ? [['id' => 17, 'full_name' => 'Agent Export']] : [],
            'voit_tout' => $directeur,
            'voit_saisie' => $directeur,
            'libelles_filtres' => ['Période du 01/09/2026 au 15/09/2026', 'Agent : tous'],
            'edite_le' => '2026-09-15 17:42:00',
            'edite_par' => 'Agent Export',
        ];
    }
}
