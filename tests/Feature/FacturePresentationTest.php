<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\View\Components\ColisageFacture;
use Tests\TestCase;

/**
 * Présentation de la facture client, à l'impression et sur un téléphone.
 *
 * Le même document sert deux usages : il est imprimé au guichet, et envoyé au
 * client qui l'ouvre sur son téléphone. Deux défauts signalés le 20/09/2026 :
 *
 * - le bandeau de pied partait seul sur une deuxième feuille, donc au dos de la
 *   facture en recto verso. La page mesurait quelques millimètres de trop ;
 * - sur un téléphone, la feuille gardait sa largeur de bureau : le client
 *   devait faire défiler de côté, et la colonne du montant total sortait de
 *   l'écran.
 *
 * Mesuré au navigateur après correction : 1 page à l'impression au lieu de 2,
 * et plus aucun débordement horizontal à 481 px de large.
 */
final class FacturePresentationTest extends TestCase
{
    private function feuille(): string
    {
        return (string) file_get_contents(BASE_PATH . '/public/assets/css/facture-print.css');
    }

    public function test_le_document_se_declare_a_la_largeur_du_telephone(): void
    {
        $html = ColisageFacture::document($this->colis(), null, 289.65, 'Agent LBP');

        // Sans cette balise, un téléphone rend la page en 980 px et la réduit :
        // le client reçoit une facture illisible.
        self::assertStringContainsString('name="viewport"', $html);
        self::assertStringContainsString('width=device-width', $html);
        self::assertStringContainsString('facture-print.css', $html);
    }

    public function test_la_feuille_prevoit_le_telephone(): void
    {
        $css = $this->feuille();

        // 1024 px et non 720 : une tablette fait 768 px en portrait et 1024 en
        // paysage, et restait sinon sur la présentation de bureau.
        self::assertMatchesRegularExpression(
            '/@media screen and \(max-width: *1024px\)/',
            $css,
            'La feuille doit prévoir le téléphone et la tablette.'
        );

        // Les deux encarts de l'en-tête sont posés en absolu sur l'image : sur
        // un petit écran ils la débordaient au lieu de tenir dedans.
        self::assertStringContainsString('.header-overlay-center,', $css);
        self::assertStringContainsString('position: static;', $css);
    }

    /**
     * Le montant total est la seule chose que le client cherche. Le tableau ne
     * doit donc pas défiler de côté : il tient dans la largeur.
     */
    public function test_le_tableau_des_marchandises_tient_dans_la_largeur(): void
    {
        $css = $this->feuille();

        self::assertStringContainsString('table-layout: fixed;', $css);
        self::assertStringNotContainsString(
            'overflow-x: auto',
            $css,
            'Faire défiler le tableau cachait la colonne du montant total.'
        );

        // Les en-têtes portent leur largeur en style inline, prévue pour l'A4 :
        // seul !important la remplace sur un téléphone.
        self::assertMatchesRegularExpression('/\.items-table th:nth-child\(9\) \{ width: \d+% !important; \}/', $css);
    }

    /**
     * Les deux bandeaux sont des images larges. Sans plafond, leur hauteur suit
     * la largeur de la page et pousse le pied sur une deuxième feuille.
     */
    public function test_les_bandeaux_ont_une_hauteur_plafonnee_a_l_impression(): void
    {
        $css = $this->feuille();
        $impression = substr($css, (int) strpos($css, '@media print'));

        self::assertStringContainsString('.header-bg-img', $impression);
        self::assertStringContainsString('.footer-img', $impression);
        self::assertSame(
            2,
            substr_count($impression, 'max-height:'),
            'Les deux bandeaux doivent être plafonnés en hauteur.'
        );
    }

    /**
     * Le pied est épinglé au bas de la feuille imprimée. Tant qu'il suivait le
     * fil du document, il suffisait que la facture dépasse de quelques
     * millimètres pour qu'il parte seul sur une deuxième page — au dos de la
     * facture en recto verso.
     */
    public function test_le_pied_est_epingle_au_bas_de_la_feuille(): void
    {
        $impression = substr($this->feuille(), (int) strpos($this->feuille(), '@media print'));

        self::assertStringContainsString('.facture-pied', $impression);
        self::assertMatchesRegularExpression('/\.facture-pied \{[^}]*position: fixed;/s', $impression);
        // La place qu'il occupe doit être rendue au contenu, sinon il le couvre.
        self::assertMatchesRegularExpression('/\.facture-container \{[^}]*padding-bottom: \d+mm;/s', $impression);

        $document = ColisageFacture::document($this->colis(), null, 289.65, 'Agent LBP');
        self::assertStringContainsString('class="facture-pied"', $document);
    }

    public function test_aucun_bloc_ne_se_coupe_entre_deux_pages(): void
    {
        $impression = substr($this->feuille(), (int) strpos($this->feuille(), '@media print'));

        self::assertStringContainsString('page-break-inside: avoid;', $impression);
        self::assertStringContainsString('.signature-box,', $impression);
        self::assertStringContainsString('.footer-address,', $impression);

        // Facture longue : l'en-tête du tableau se répète sur chaque page.
        self::assertStringContainsString('display: table-header-group;', $impression);
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
            'agence_name' => 'Aéroport Port Bouët Fret',
            'trafic' => 'Groupage aérien',
            'devise' => 'XOF',
            'montant_total' => 190000,
            'assurance_souscrite' => 1,
            'montant_assurance' => 10000,
            'nombre_colis' => 3,
            'poids_total' => 130.0,
            'marchandises' => [[
                'description' => 'Carton de marchandises diverses',
                'nbre_colis' => 1,
                'emballage' => 'Carton standard',
                'qte_emballage' => 2,
                'prix_emballage' => 2500,
                'poids_unitaire' => 32.5,
                'prix_kg' => 1500,
                'total_ligne' => 45000,
            ]],
        ];
    }
}
