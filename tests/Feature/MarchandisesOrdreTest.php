<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\View\Components\Colisage;
use App\View\Components\ColisageFacture;
use Tests\TestCase;

/**
 * Ordre des colonnes d'une ligne de marchandise, décidé le 20/09/2026.
 *
 * N° · Nbre colis · Description · Qté · Poids · Prix/kg · Type d'emballage ·
 * Nbre d'emballage · Prix emballage · Nbre d'étiquette · Total.
 *
 * Deux notions nouvelles :
 *
 * - la quantité décrit le contenu — cent habits restent cent habits — et ne se
 *   facture pas : le prix se fait au poids ;
 * - les étiquettes produit (attiéké, vêtement, chaussure) sont vendues par LBP
 *   au guichet, à un prix saisi par l'agent. Sans elles, cette vente
 *   n'apparaissait sur aucune facture.
 *
 * Trois écrans saisissent ces lignes : l'enregistrement d'un colis, sa
 * modification, et l'écran « Autres ». Ils doivent dire la même chose.
 */
final class MarchandisesOrdreTest extends TestCase
{
    /** @var array<int, string> */
    private const ORDRE = [
        'Nbre Colis', 'Description', 'Qté', 'Poids (kg)', 'Prix / Kg',
        'Type d\'emballage', 'Nbre emb.', 'Prix emb.', 'Nbre étiquette', 'Total',
    ];

    private function ordreRespecte(string $html, string $ecran): void
    {
        $position = 0;
        foreach (self::ORDRE as $colonne) {
            $trouve = strpos($html, '>' . $colonne . '</th>', $position);
            self::assertNotFalse($trouve, "Colonne « {$colonne} » absente ou hors d'ordre dans {$ecran}.");
            $position = $trouve;
        }
    }

    public function test_l_enregistrement_d_un_colis_suit_l_ordre_convenu(): void
    {
        $html = Colisage::createPage([], [], []);

        $this->ordreRespecte($html, "l'enregistrement");

        self::assertStringContainsString('name="m_qty[]"', $html);
        self::assertStringContainsString('name="m_nbre_etiquettes[]"', $html);
        self::assertStringContainsString('name="m_prix_etiquette[]"', $html);
    }

    public function test_l_ecran_autres_suit_le_meme_ordre(): void
    {
        $html = Colisage::marchandisesInputTable([]);

        $this->ordreRespecte($html, "l'écran Autres");

        self::assertStringContainsString('name="m_qty[]"', $html);
        self::assertStringContainsString('name="m_nbre_etiquettes[]"', $html);
        self::assertStringContainsString('name="m_prix_etiquette[]"', $html);
        // Le prix de l'emballage manquait sur cet écran : il y était saisi
        // nulle part, et la ligne partait donc sous-facturée.
        self::assertStringContainsString('name="m_prix_emballage[]"', $html);
    }

    public function test_la_modification_d_un_colis_reprend_les_valeurs_enregistrees(): void
    {
        $colis = [
            'id' => 7,
            'numero_tracking' => 'LBP-ABJ-2609-0042',
            'marchandises' => [[
                'description' => 'HABITS',
                'nbre_colis' => 1,
                'quantite' => 100,
                'emballage' => 'Carton standard',
                'qte_emballage' => 2,
                'prix_emballage' => 2500,
                'nbre_etiquettes' => 4,
                'prix_etiquette' => 200,
                'poids_unitaire' => 32.5,
                'prix_kg' => 1500,
                'total_ligne' => 54550,
            ]],
        ];

        $html = Colisage::parcelEditPage($colis, [], [], []);

        $this->ordreRespecte($html, "la modification");
        self::assertStringContainsString('value="100"', $html, 'La quantité saisie doit être rechargée.');
        self::assertStringContainsString('name="m_nbre_etiquettes[]"', $html);
        self::assertStringContainsString('name="m_prix_etiquette[]"', $html);
    }

    /**
     * Le total d'une ligne additionne ce qui est vendu : le transport au poids,
     * les emballages et les étiquettes. La quantité n'y entre pas.
     */
    public function test_le_total_d_une_ligne_ajoute_les_etiquettes_et_ignore_la_quantite(): void
    {
        $source = (string) file_get_contents(
            BASE_PATH . '/app/Repositories/Colisage/ColisageRepository.php'
        );

        $position = strpos($source, 'function createMarchandise(');
        self::assertNotFalse($position);

        $extrait = substr($source, $position, 1400);
        self::assertStringContainsString('($nbEtiquettes * $prixEtiquette)', $extrait);
        self::assertStringContainsString('($qteEmb * $prixEmb)', $extrait);
        self::assertStringContainsString('($poidsUnitaire * $prixKg)', $extrait);
        self::assertStringNotContainsString('$qte *', $extrait, 'La quantité ne se facture pas.');
    }

    public function test_la_facture_suit_le_meme_ordre_et_montre_les_etiquettes(): void
    {
        $html = ColisageFacture::document($this->colis(), null, 0.0, 'Agent LBP');

        $ordreFacture = ['Nbre colis', 'Description', 'Qté', 'Poids (kg)', 'Prix / kg',
            'Type d\'emballage', 'Nbre emb.', 'Prix emb.', 'Total'];

        $position = 0;
        foreach ($ordreFacture as $colonne) {
            $trouve = strpos($html, '>' . $colonne . '</th>', $position);
            self::assertNotFalse($trouve, "Colonne « {$colonne} » absente ou hors d'ordre sur la facture.");
            $position = $trouve;
        }

        self::assertStringContainsString('DONT ÉTIQUETTES', $html);
        self::assertStringContainsString('800 FCFA', $html, '4 étiquettes à 200 F.');
        self::assertStringContainsString('colspan="8"', $html, 'Dix colonnes : le pied en couvre huit.');
    }

    public function test_une_facture_sans_etiquette_ne_montre_pas_la_ligne(): void
    {
        $colis = $this->colis();
        $colis['marchandises'][0]['nbre_etiquettes'] = 0;

        $html = ColisageFacture::document($colis, null, 0.0, 'Agent LBP');

        self::assertStringNotContainsString('DONT ÉTIQUETTES', $html);
    }

    /** @return array<string, mixed> */
    private function colis(): array
    {
        return [
            'id' => 1,
            'numero_tracking' => 'LBP-ABJ-2609-0042',
            'created_at' => '2026-09-14 09:12:00',
            'expediteur_name' => 'KOUASSI YAO',
            'expediteur_phone' => '+225 07 07 07 07 07',
            'destinataire_name' => 'DIABATE AMINATA',
            'destinataire_phone' => '+33 7 51 19 83 82',
            'agence_arrivee_name' => 'Paris Bobigny',
            'trafic' => 'Groupage aérien',
            'devise' => 'XOF',
            'montant_total' => 54550,
            'nombre_colis' => 1,
            'marchandises' => [[
                'description' => 'HABITS',
                'nbre_colis' => 1,
                'quantite' => 100,
                'emballage' => 'Carton standard',
                'qte_emballage' => 2,
                'prix_emballage' => 2500,
                'nbre_etiquettes' => 4,
                'prix_etiquette' => 200,
                'poids_unitaire' => 32.5,
                'prix_kg' => 1500,
                'total_ligne' => 54550,
            ]],
        ];
    }
}
