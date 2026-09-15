<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Colisage\DossierEnvoiRegles as Regles;
use App\View\Components\ColisageEnvois;
use App\View\Components\ColisageEnvoisExport;
use Tests\TestCase;

/**
 * Rendu des écrans et exports des dossiers d'envoi.
 *
 * Au lancement, les listes seront vides : chaque écran est rendu vide, puis
 * rempli. Les exports sont vérifiés sur ce qui compte pour les comparer aux
 * vrais documents : numéros en texte, nombres sommables, filtres rappelés.
 */
final class ColisageEnvoisRenduTest extends TestCase
{
    public function test_la_liste_vide_de_l_agent_propose_d_ouvrir_un_dossier(): void
    {
        $html = html_entity_decode(ColisageEnvois::listePage([
            'filtres' => ['statut' => 'en_cours', 'responsable' => null, 'mode' => '', 'q' => ''],
            'dossiers' => [],
            'departs_sans_dossier' => [[
                'id' => 5, 'reference' => 'DEP-20260914-162000-AB12', 'date_depart' => '2026-09-14 16:20:00',
                'agence_depart' => 'Aéroport Port Bouët Fret', 'agence_arrivee' => 'Paris Bobigny', 'nb_colis' => 140,
            ]],
            'responsables' => [],
            'peut_creer' => true,
            'voit_tout' => false,
            'est_valideur' => false,
        ]), ENT_QUOTES);

        self::assertStringContainsString("Mes dossiers d'envoi", $html);
        self::assertStringContainsString('Nouveau dossier', $html);
        self::assertStringContainsString('Aucun dossier', $html);
        self::assertStringContainsString('Départs du pointage sans dossier', $html);
        self::assertStringContainsString('envois/nouveau?depart=5', $html);
        self::assertStringNotContainsString('name="responsable"', $html, "L'agent ne filtre pas par responsable.");
    }

    public function test_la_liste_du_directeur_filtre_par_responsable_sans_proposer_de_saisie(): void
    {
        $html = ColisageEnvois::listePage([
            'filtres' => ['statut' => 'tous', 'responsable' => 17, 'mode' => 'AERIEN', 'q' => ''],
            'dossiers' => [$this->dossierEnrichi()],
            'departs_sans_dossier' => [],
            'responsables' => [['id' => 17, 'full_name' => "N'DRIN REGIS"]],
            'peut_creer' => false,
            'voit_tout' => true,
            'est_valideur' => true,
        ]);

        self::assertStringContainsString('name="responsable"', $html);
        self::assertStringNotContainsString('envois/nouveau', $html);
        self::assertStringContainsString('ENV-ABJ-2609-0002', $html);
        self::assertStringContainsString('923-44100873', $html);
    }

    public function test_le_formulaire_porte_les_dix_colonnes_lbp(): void
    {
        $html = ColisageEnvois::formulairePage([
            'dossier' => ['id' => null, 'numero' => null, 'statut' => 'BROUILLON', 'mode_transport' => 'AERIEN'],
            'tranches' => [], 'emballages' => [], 'frais' => [],
            'agences' => [['id' => 3402, 'name' => 'Aéroport Port Bouët Fret']],
            'prestataires' => $this->prestataires(),
            'departs' => [],
            'erreurs' => [],
            'id' => null,
        ]);

        for ($colonne = 1; $colonne <= 10; $colonne++) {
            self::assertStringContainsString('LBP ' . $colonne . '<', $html, 'Colonne LBP ' . $colonne . ' absente du formulaire.');
        }

        self::assertStringContainsString('envois/enregistrer', $html);
        self::assertStringContainsString('name="_csrf_token"', $html);
        self::assertStringContainsString('data-mode="MARITIME"', $html, 'Les types de document de chaque mode sont proposés.');
        self::assertStringContainsString('name="frais[TRANSIT_DEPART][montant_prevu]"', $html);
        self::assertStringContainsString('name="tranches[0][reference]"', $html);
        self::assertStringContainsString('<optgroup label="Type à préciser">', $html, 'Les transitaires sans type restent choisissables.');
    }

    public function test_le_formulaire_refuse_reaffiche_les_erreurs_et_la_saisie(): void
    {
        $html = html_entity_decode(ColisageEnvois::formulairePage([
            'dossier' => ['id' => 3, 'numero' => 'ENV-ABJ-2609-0003', 'statut' => 'A_CORRIGER', 'mode_transport' => 'AERIEN',
                'numero_document' => '057-30215464', 'motif_renvoi' => 'Facture K2S illisible'],
            'tranches' => [], 'emballages' => [['type' => 'Carton', 'quantite' => '30']], 'frais' => [],
            'agences' => [], 'prestataires' => [], 'departs' => [],
            'erreurs' => ['Le dernier chiffre de la LTA 057-30215464 ne correspond pas : attendu 3.'],
            'id' => 3,
        ]), ENT_QUOTES);

        self::assertStringContainsString("n'a pas été enregistré", $html);
        self::assertStringContainsString('attendu 3', $html);
        self::assertStringContainsString('value="057-30215464"', $html);
        self::assertStringContainsString('Facture K2S illisible', $html);
        self::assertStringContainsString('envois/3/modifier', $html);
    }

    public function test_la_fiche_explique_ce_qui_manque_avant_de_soumettre(): void
    {
        $html = html_entity_decode(ColisageEnvois::fichePage($this->fiche(
            ['statut' => 'LIVRE'],
            ['modifier' => true, 'soumettre' => true],
            ['Pièce manquante : facture du transitaire à destination.']
        )), ENT_QUOTES);

        self::assertStringContainsString('Pour soumettre au Directeur général, il reste', $html);
        self::assertStringContainsString('facture du transitaire à destination', $html);
        self::assertStringNotContainsString('/soumettre"', $html, 'Pas de formulaire de soumission tant que le dossier est incomplet.');
        self::assertStringContainsString('enctype="multipart/form-data"', $html);
        self::assertStringContainsString('envois/7/documents/3', $html);
        self::assertStringContainsString('/retirer', $html);
    }

    public function test_la_fiche_complete_se_soumet(): void
    {
        $html = ColisageEnvois::fichePage($this->fiche(['statut' => 'LIVRE'], ['modifier' => true, 'soumettre' => true], []));

        self::assertStringContainsString('envois/7/soumettre', $html);
    }

    public function test_le_directeur_valide_ou_renvoie_avec_un_motif(): void
    {
        $html = ColisageEnvois::fichePage($this->fiche(
            ['statut' => 'SOUMIS', 'soumis_le' => '2026-09-15 09:00:00'],
            ['valider' => true, 'renvoyer' => true, 'reaffecter' => true],
            [],
            [['id' => 17, 'full_name' => "N'DRIN REGIS"]]
        ));

        self::assertStringContainsString('envois/7/valider', $html);
        self::assertStringContainsString('envois/7/renvoyer', $html);
        self::assertMatchesRegularExpression('/<textarea[^>]*name="motif"[^>]*required/', $html);
        self::assertStringContainsString('envois/7/reaffecter', $html);
        self::assertStringNotContainsString('enctype="multipart/form-data"', $html, 'Le directeur ne dépose pas de pièce.');
    }

    public function test_l_historique_exporte_exactement_les_filtres_affiches(): void
    {
        $html = html_entity_decode(ColisageEnvois::historiquePage($this->historique()), ENT_QUOTES);

        self::assertStringContainsString('historique/pdf?periode=libre&du=2026-09-01&au=2026-09-15', $html);
        self::assertStringContainsString('historique/excel?periode=libre&du=2026-09-01&au=2026-09-15', $html);
        self::assertStringContainsString('transporteur=2', $html);
        self::assertStringContainsString('1 dossier(s)', $html);
        self::assertStringContainsString('Sealogis', $html);
    }

    public function test_l_historique_vide_l_explique(): void
    {
        $donnees = $this->historique();
        $donnees['dossiers'] = [];
        $donnees['totaux'] = $this->totaux([]);

        self::assertStringContainsString('Aucun dossier sur cette période', ColisageEnvois::historiquePage($donnees));
    }

    public function test_l_export_excel_garde_les_numeros_en_texte_et_les_nombres_sommables(): void
    {
        $xls = ColisageEnvoisExport::historiqueExcel($this->historique());

        self::assertStringContainsString('<td>923-44100873</td>', $xls);
        self::assertStringContainsString('x:num="402.0"', $xls);
        self::assertStringContainsString('x:num="185000.00"', $xls);
        self::assertStringContainsString('Période du 01/09/2026 au 15/09/2026', $xls);
        self::assertStringContainsString('xmlns:x="urn:schemas-microsoft-com:office:excel"', $xls);
    }

    public function test_l_export_pdf_rappelle_les_filtres_et_prevoit_la_signature(): void
    {
        $pdf = html_entity_decode(ColisageEnvoisExport::historiquePdf($this->historique()), ENT_QUOTES);

        self::assertStringContainsString('size:A4 landscape', $pdf);
        self::assertStringContainsString('Période du 01/09/2026 au 15/09/2026', $pdf);
        self::assertStringContainsString('par Agent Export', $pdf);
        self::assertStringContainsString('Vérifié par', $pdf);
        self::assertStringContainsString('ENV-ABJ-2609-0002', $pdf);
    }

    public function test_la_fiche_pdf_liste_les_pieces_et_les_frais(): void
    {
        $fiche = $this->fiche(['statut' => 'VALIDE', 'valide_le' => '2026-09-16 10:00:00', 'valide_par' => 'KADJO'], [], []);
        $fiche['edite_par'] = 'KADJO';

        $pdf = html_entity_decode(ColisageEnvoisExport::fichePdf($fiche), ENT_QUOTES);

        self::assertStringContainsString('size:A4 portrait', $pdf);
        self::assertStringContainsString('[jointe] Pièce de transport', $pdf);
        self::assertStringContainsString('Validé le 16/09/2026', $pdf);
        self::assertStringContainsString('Transitaire au départ', $pdf);
    }

    public function test_la_page_des_prestataires_signale_les_types_a_preciser(): void
    {
        $html = html_entity_decode(ColisageEnvois::prestatairesPage(['prestataires' => $this->prestataires()]), ENT_QUOTES);

        self::assertStringContainsString('Type à préciser', $html);
        self::assertStringContainsString('form="prestataire-4"', $html);
        self::assertStringContainsString('prestataires/enregistrer', $html);
    }

    public function test_aucun_ecran_ne_laisse_passer_de_html_non_echappe(): void
    {
        $injection = '<script>alert(1)</script>';
        $dossier = $this->dossierEnrichi([
            'numero' => $injection, 'transporteur' => $injection, 'numero_document' => $injection, 'responsable' => $injection,
            'agence_depart' => $injection, 'agence_arrivee' => $injection, 'destination' => $injection, 'commentaire_ecart' => $injection,
            'motif_renvoi' => $injection, 'statut' => 'A_CORRIGER',
        ]);
        $prestataires = [['id' => 1, 'type' => '', 'name' => $injection, 'country' => $injection, 'prefixe_lta' => null, 'is_active' => 1]];

        $fiche = $this->fiche(['numero' => $injection, 'transporteur' => $injection, 'motif_renvoi' => $injection, 'statut' => 'A_CORRIGER'], ['modifier' => true], [$injection]);
        $fiche['documents'][0]['nom_fichier'] = $injection;
        $fiche['documents'][0]['original_name'] = $injection;
        $fiche['journal'] = [['created_at' => '2026-09-15 10:00:00', 'action' => $injection, 'champ' => $injection, 'ancienne_valeur' => $injection, 'nouvelle_valeur' => $injection, 'motif' => $injection, 'par' => $injection]];
        $fiche['edite_par'] = $injection;

        $historique = $this->historique();
        $historique['dossiers'] = [$dossier];
        $historique['libelles_filtres'] = [$injection];
        $historique['edite_par'] = $injection;
        $historique['prestataires'] = $prestataires;

        $ecrans = [
            'liste' => ColisageEnvois::listePage(['filtres' => ['statut' => 'tous', 'responsable' => null, 'mode' => '', 'q' => $injection], 'dossiers' => [$dossier],
                'departs_sans_dossier' => [['id' => 1, 'reference' => $injection, 'date_depart' => null, 'agence_depart' => $injection, 'agence_arrivee' => $injection, 'nb_colis' => 1]],
                'responsables' => [['id' => 1, 'full_name' => $injection]], 'peut_creer' => true, 'voit_tout' => true]),
            'formulaire' => ColisageEnvois::formulairePage(['dossier' => $dossier, 'tranches' => [['reference' => $injection]], 'emballages' => [], 'frais' => [['poste' => 'AUTRE', 'libelle' => $injection]],
                'agences' => [['id' => 1, 'name' => $injection]], 'prestataires' => $prestataires, 'departs' => [['id' => 1, 'reference' => $injection, 'agence_depart' => $injection, 'agence_arrivee' => $injection, 'date_depart' => null, 'nb_colis' => 1]],
                'erreurs' => [$injection], 'id' => 1]),
            'fiche' => ColisageEnvois::fichePage($fiche),
            'à valider' => ColisageEnvois::aValiderPage(['dossiers' => [$dossier + ['soumis_le' => '2026-09-15 09:00:00']]]),
            'pièces' => ColisageEnvois::piecesPage(['lignes' => [['dossier' => $dossier, 'type' => 'MANIFESTE', 'libelle' => $injection, 'peut_deposer' => true]]]),
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
        self::assertCount(10, $vues);

        foreach ($vues as $vue) {
            $source = (string) file_get_contents($vue);
            self::assertDoesNotMatchRegularExpression('/<(div|table|form|section|p|span|html)\b/i', $source, basename($vue) . ' contient du HTML brut.');
            self::assertMatchesRegularExpression('/ColisageEnvois(Export)?::/', $source);
        }
    }

    // ------------------------------------------------------------------
    // Données d'exemple, reprises du cahier des charges
    // ------------------------------------------------------------------

    /** @return array<int, array<string, mixed>> */
    private function prestataires(): array
    {
        return [
            ['id' => 1, 'type' => 'COMPAGNIE_AERIENNE', 'name' => 'Corsair', 'country' => null, 'prefixe_lta' => '923', 'is_active' => 1],
            ['id' => 2, 'type' => 'COMPAGNIE_AERIENNE', 'name' => 'Air France', 'country' => null, 'prefixe_lta' => '057', 'is_active' => 1],
            ['id' => 4, 'type' => '', 'name' => 'Sealogis', 'country' => null, 'prefixe_lta' => null, 'is_active' => 1],
        ];
    }

    /**
     * @param array<string, mixed> $valeurs
     * @return array<string, mixed>
     */
    private function dossierBrut(array $valeurs = []): array
    {
        return $valeurs + [
            'id' => 7, 'numero' => 'ENV-ABJ-2609-0002', 'mode_transport' => 'AERIEN', 'statut' => 'LIVRE', 'responsable_id' => 17,
            'responsable' => 'Agent Export', 'agence_depart_id' => 3402, 'agence_depart' => 'Aéroport Port Bouët Fret',
            'agence_arrivee_id' => 3400, 'agence_arrivee' => 'Paris Bobigny', 'destination' => null,
            'expedition_id' => 5, 'expedition_reference' => 'DEP-20260905-1000-AB12',
            'transporteur_id' => 1, 'transporteur' => 'Corsair', 'type_document' => 'LTA_DIRECTE', 'numero_document' => '923-44100873',
            'emetteur_document_id' => null, 'emetteur_document' => null, 'document_principal' => null,
            'lieu_depart' => 'ABJ', 'lieu_arrivee' => 'ORY',
            'date_depart_prevue' => '2026-09-05', 'date_depart_effective' => '2026-09-05', 'date_arrivee_estimee' => null,
            'date_arrivee' => '2026-09-06', 'date_livraison' => '2026-09-08', 'date_reference' => '2026-09-05',
            'nb_colis_declare' => 31, 'poids_brut_kg' => '402.0', 'poids_taxable_kg' => '418.5', 'volume_m3' => null,
            'taux_eur_xof' => '655.957000', 'commentaire_ecart' => null, 'motif_renvoi' => null, 'motif_annulation' => null,
            'soumis_le' => null, 'valide_le' => null, 'valide_par' => null,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function frais(): array
    {
        return [
            ['poste' => 'FRET', 'prestataire' => 'Corsair', 'montant_prevu' => '380000.00', 'devise' => 'XOF', 'montant_facture' => '392000.00', 'devise_facture' => 'XOF', 'numero_facture' => 'CRS-0917', 'sans_frais' => 0],
            ['poste' => 'TRANSIT_DEPART', 'prestataire' => 'Sealogis', 'montant_prevu' => '185000.00', 'devise' => 'XOF', 'montant_facture' => null, 'sans_frais' => 0],
            ['poste' => 'TRANSIT_ARRIVEE', 'prestataire' => 'K2S', 'montant_prevu' => '210.00', 'devise' => 'EUR', 'montant_facture' => null, 'sans_frais' => 0],
            ['poste' => 'LIVRAISON_DEPART', 'prestataire' => null, 'prestataire_libre' => 'Livreur interne', 'montant_prevu' => '25000.00', 'devise' => 'XOF', 'montant_facture' => null, 'sans_frais' => 0],
            ['poste' => 'LIVRAISON_ARRIVEE', 'prestataire' => null, 'montant_prevu' => null, 'devise' => 'XOF', 'montant_facture' => null, 'sans_frais' => 1],
        ];
    }

    /**
     * @param array<string, mixed> $valeurs
     * @return array<string, mixed>
     */
    private function dossierEnrichi(array $valeurs = []): array
    {
        $dossier = $this->dossierBrut($valeurs);
        $dossier['frais'] = $this->frais();
        $dossier['emballages'] = [['type' => 'Carton', 'quantite' => 22], ['type' => 'Valise', 'quantite' => 4]];
        $dossier['tranches'] = [['type' => 'VOL', 'rang' => 1, 'reference' => 'SS 971', 'date_depart' => '2026-09-05', 'nb_colis' => 31]];
        $dossier['synthese'] = Regles::synthese($dossier, $dossier['frais'], $dossier['emballages'], ['PIECE_TRANSPORT', 'MANIFESTE'], 31);

        return $dossier;
    }

    /**
     * @param array<string, mixed> $valeurs
     * @param array<string, bool> $droits
     * @param array<int, string> $manques
     * @param array<int, array{id:int, full_name:string}> $responsables
     * @return array<string, mixed>
     */
    private function fiche(array $valeurs, array $droits, array $manques, array $responsables = []): array
    {
        $dossier = $this->dossierBrut($valeurs);
        $frais = $this->frais();
        $emballages = [['type' => 'Carton', 'quantite' => 22]];

        return [
            'dossier' => $dossier,
            'tranches' => [['type' => 'VOL', 'rang' => 1, 'reference' => 'SS 971', 'type_conteneur' => null, 'chauffeur' => null, 'date_depart' => '2026-09-05', 'date_arrivee' => '2026-09-06', 'nb_colis' => 31, 'poids_kg' => '402.0']],
            'emballages' => $emballages,
            'frais' => $frais,
            'documents' => [['id' => 3, 'type_document' => 'PIECE_TRANSPORT', 'nom_fichier' => 'ENV-ABJ-2609-0002_PIECE_TRANSPORT_1.pdf', 'original_name' => 'LTA corsair.pdf', 'size_bytes' => 245000, 'uploaded_at' => '2026-09-05 18:00:00', 'depose_par' => 'Agent Export']],
            'synthese' => Regles::synthese($dossier, $frais, $emballages, ['PIECE_TRANSPORT'], 31),
            'manques' => $manques,
            'journal' => [['created_at' => '2026-09-05 18:00:00', 'action' => 'DOCUMENT_AJOUT', 'champ' => 'Pièce de transport', 'ancienne_valeur' => null, 'nouvelle_valeur' => 'ENV-ABJ-2609-0002_PIECE_TRANSPORT_1.pdf', 'motif' => null, 'par' => 'Agent Export']],
            'responsables' => $responsables,
            'droits' => $droits + ['modifier' => false, 'soumettre' => false, 'annuler' => false, 'valider' => false, 'renvoyer' => false, 'rouvrir' => false, 'reaffecter' => false],
        ];
    }

    /** @return array<string, mixed> */
    private function historique(): array
    {
        $dossiers = [$this->dossierEnrichi()];

        return [
            'filtres' => [
                'periode' => 'libre', 'du' => '2026-09-01', 'au' => '2026-09-15', 'responsable' => null, 'mode' => '',
                'agence' => null, 'transporteur' => 2, 'transitaire' => null, 'statut' => '', 'pieces' => false,
                'ecart_colis' => false, 'ecart_facture' => false, 'q' => '',
            ],
            'dossiers' => $dossiers,
            'totaux' => $this->totaux($dossiers),
            'agences' => [['id' => 3402, 'name' => 'Aéroport Port Bouët Fret']],
            'prestataires' => $this->prestataires(),
            'responsables' => [],
            'voit_tout' => false,
            'libelles_filtres' => ['Période du 01/09/2026 au 15/09/2026', 'Responsable : tous'],
            'edite_le' => '2026-09-15 17:42:00',
            'edite_par' => 'Agent Export',
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $dossiers
     * @return array<string, mixed>
     */
    private function totaux(array $dossiers): array
    {
        $postes = array_fill_keys(array_keys(Regles::POSTES), ['prevu' => 0.0, 'facture' => 0.0]);

        return [
            'dossiers' => count($dossiers),
            'colis' => array_sum(array_map(static fn (array $d): int => (int) $d['nb_colis_declare'], $dossiers)),
            'poids' => array_sum(array_map(static fn (array $d): float => (float) $d['poids_brut_kg'], $dossiers)),
            'cout_prevu' => 0.0,
            'cout_retenu' => array_sum(array_map(static fn (array $d): float => (float) $d['synthese']['cout_retenu_xof'], $dossiers)),
            'ecart_facture' => 0.0,
            'postes' => $postes,
        ];
    }
}
