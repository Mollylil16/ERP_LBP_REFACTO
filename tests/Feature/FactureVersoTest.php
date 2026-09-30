<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Colisage\ColisageService;
use App\View\Components\ColisageFacture;
use App\View\Components\FicheEngagement;
use Tests\TestCase;

/**
 * Le verso de la facture : la fiche d'engagement client — garantie colis.
 *
 * Décidé le 30/09/2026 : la fiche part désormais avec chaque facture remise au
 * client, au dos de la même feuille. Elle engage les deux parties, donc rien
 * n'en est résumé ni réécrit ; ce qui se resserre, c'est la mise en page.
 *
 * Elle arrive pré-remplie de ce que le logiciel sait déjà — le colis, les deux
 * parties, la marchandise, les montants : l'agent ne recopie pas ce qu'il vient
 * de saisir.
 */
final class FactureVersoTest extends TestCase
{
    /** @return array<string, mixed> */
    private function colis(): array
    {
        return [
            'numero_tracking' => 'LB-CI-465',
            'created_at' => '2026-09-29 13:36:00',
            'expediteur_name' => 'KANE MARIAM',
            'expediteur_phone' => '0707213386',
            'expediteur_address' => 'Cocody Angré, 7e tranche',
            'destinataire_name' => 'BALO ASTA',
            'destinataire_phone' => '+33620930716',
            'destinataire_address' => '17 chemin des Vignes, 93000 Bobigny',
            'agence_depart_name' => 'Aéroport Port Bouët Fret',
            'agence_arrivee_name' => 'Paris 17 chemin des Vignes 93000 Bobigny',
            'trafic' => 'LB-CI Abidjan — France',
            'devise' => 'XOF',
            'montant_total' => 96000,
            'nombre_colis' => 11,
            'poids_total' => 96.0,
            'valeur_declaree' => 450000,
            'assurance_souscrite' => 0,
            'montant_assurance' => 0,
            'marchandises' => [
                ['description' => 'VÊTEMENTS', 'nbre_colis' => 10, 'quantite' => 1, 'poids_unitaire' => 94.0, 'prix_kg' => 900, 'emballage' => 'Petit carton', 'qte_emballage' => 15, 'prix_emballage' => 500, 'total_ligne' => 92100],
                ['description' => 'ATTIÉKÉ', 'nbre_colis' => 1, 'quantite' => 4, 'poids_unitaire' => 2.0, 'prix_kg' => 1850, 'emballage' => 'Étiquettes LBP', 'qte_emballage' => 1, 'prix_emballage' => 200, 'total_ligne' => 3900],
            ],
        ];
    }

    /** La fiche voyage avec la facture : c'est la même impression. */
    public function test_la_fiche_est_au_dos_de_chaque_facture(): void
    {
        $html = ColisageFacture::document($this->colis(), null, 146.35, 'ANOUMAN GEMIMA');

        self::assertStringContainsString('fiche-verso', $html);
        self::assertStringContainsString('FICHE D\'ENGAGEMENT CLIENT', $html);
    }

    /** Une feuille, deux faces : la fiche commence sur une nouvelle page. */
    public function test_la_fiche_s_imprime_sur_une_page_neuve(): void
    {
        $css = (string) file_get_contents(BASE_PATH . '/public/assets/css/facture-print.css');

        self::assertStringContainsString('page-break-before: always', $css);
        self::assertStringContainsString('break-before: page', $css);
    }

    /** Les dix sections de la fiche validée, aucune oubliée. */
    public function test_les_dix_sections_y_sont_toutes(): void
    {
        $html = FicheEngagement::verso($this->colis(), null, 'ANOUMAN GEMIMA');

        foreach ([
            '1. Identification de l\'expéditeur',
            '2. Identification du destinataire',
            '3. Description du colis et déclaration de valeur',
            '4. Garantie souscrite et paiement',
            '5. Engagement du client',
            '6. Risques garantis et exclusions',
            '7. Déclaration de sinistre',
            '8. Bénéficiaire de l\'indemnisation',
            '9. Déclaration et signatures',
            '10. Reçu client',
        ] as $section) {
            self::assertStringContainsString($section, $html, 'Section manquante : ' . $section);
        }
    }

    /**
     * Les clauses sont reprises mot pour mot : c'est un document qui engage, il
     * ne se résume pas.
     */
    public function test_les_clauses_sont_reprises_mot_pour_mot(): void
    {
        $html = FicheEngagement::verso($this->colis(), null, 'ANOUMAN GEMIMA');

        self::assertStringContainsString('Certifie l&#039;exactitude des informations fournies', $html);
        self::assertStringContainsString('sous-évaluation volontaire', $html);
        self::assertStringContainsString('bijoux, pierres précieuses', $html);
        self::assertStringContainsString('Lu et approuvé', $html);
        self::assertStringContainsString('AVIS IMPORTANT', $html);

        // Les sept engagements, pas six.
        self::assertSame(7, substr_count($html, '<li>'));
    }

    /**
     * Ce que le logiciel sait déjà est pré-rempli : l'agent ne recopie pas le
     * colis qu'il vient d'enregistrer.
     */
    public function test_la_fiche_arrive_pre_remplie(): void
    {
        $html = FicheEngagement::verso($this->colis(), null, 'ANOUMAN GEMIMA');

        foreach ([
            'LB-CI-465',
            'KANE MARIAM',
            'BALO ASTA',
            '0707213386',
            'ANOUMAN GEMIMA',
            '29/09/2026',
            'VÊTEMENTS',
            '96 000 FCFA',
            '450 000 FCFA',
        ] as $attendu) {
            self::assertStringContainsString($attendu, $html, 'Absent de la fiche : ' . $attendu);
        }
    }

    /**
     * Les cases sont dessinées, jamais écrites : le caractère « boîte »
     * s'imprime au hasard des polices, et le projet n'accepte aucun symbole de
     * ce genre dans ses vues.
     */
    public function test_les_cases_a_cocher_sont_dessinees(): void
    {
        $html = FicheEngagement::verso($this->colis(), null, 'ANOUMAN GEMIMA');

        self::assertStringContainsString('fiche-case', $html);
        self::assertStringNotContainsString('☐', $html);
        self::assertStringNotContainsString('☒', $html);
    }

    /** La garantie souscrite se voit cochée, sans que l'agent n'ait rien à faire. */
    public function test_la_garantie_souscrite_est_cochee(): void
    {
        $sans = FicheEngagement::verso($this->colis(), null, 'AGENT');

        $avec = $this->colis();
        $avec['assurance_souscrite'] = 1;
        $avec['montant_assurance'] = 9600;

        self::assertStringNotContainsString('is-cochee', $sans);
        self::assertStringContainsString('is-cochee', FicheEngagement::verso($avec, null, 'AGENT'));
        self::assertStringContainsString('9 600 FCFA', FicheEngagement::verso($avec, null, 'AGENT'));
    }

    // ------------------------------------------------------------------
    // La colonne « Description » de la facture
    // ------------------------------------------------------------------

    /**
     * Une facture du 29/09/2026 affichait « MARCHANDISES DIVERSES » sur ses deux
     * lignes : le client ne lisait pas ce qu'il avait confié. Une ligne qui ne
     * vend que des étiquettes ou de l'emballage porte maintenant son nom.
     */
    public function test_une_ligne_sans_marchandise_porte_son_propre_nom(): void
    {
        self::assertSame(
            'ÉTIQUETTES LBP',
            ColisageService::descriptionLigne(['nbre_etiquettes' => 12, 'poids_unitaire' => 0, 'prix_kg' => 0], '')
        );

        self::assertSame(
            'PETIT CARTON',
            ColisageService::descriptionLigne(['emballage' => 'Petit carton', 'poids_unitaire' => 0, 'prix_kg' => 0], '')
        );
    }

    /** Ce que l'agent a nommé prime sur tout le reste. */
    public function test_le_nom_saisi_n_est_jamais_remplace(): void
    {
        self::assertSame(
            'VÊTEMENTS',
            ColisageService::descriptionLigne(['emballage' => 'Petit carton', 'nbre_etiquettes' => 5], 'VÊTEMENTS')
        );
    }

    /** Le libellé générique ne reste que pour ce qui n'est ni l'un ni l'autre. */
    public function test_le_libelle_generique_est_le_dernier_recours(): void
    {
        self::assertSame(
            'MARCHANDISES DIVERSES',
            ColisageService::descriptionLigne(['poids_unitaire' => 94.0, 'prix_kg' => 900], '')
        );
    }

    /**
     * Et pour que ce dernier recours ne serve plus, le formulaire réclame le nom
     * du produit dès qu'une ligne porte de la marchandise — côté navigateur,
     * pour ne pas perdre la saisie comme le ferait un refus du serveur.
     */
    public function test_le_formulaire_reclame_le_nom_du_produit(): void
    {
        $source = (string) file_get_contents(BASE_PATH . '/app/View/Components/Colisage.php');

        self::assertStringContainsString('nomProduit.setAttribute("required", "required")', $source);
        self::assertStringContainsString('(weight > 0 || prixKg > 0) && !produitChoisi', $source);
        self::assertStringContainsString('nomProduit.removeAttribute("required")', $source);
    }
}
