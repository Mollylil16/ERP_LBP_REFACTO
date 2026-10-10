<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\View\Components\MouvementsCaisse;
use Tests\TestCase;

/**
 * Rendu de l'écran « Mouvements de caisse ».
 *
 * Trois règles de cet écran ne se voient qu'à l'affichage, et se perdent au
 * premier remaniement :
 *
 *  1. une variation à null veut dire « la veille était à zéro ». Aucun
 *     pourcentage ne doit alors sortir : « -100 % » depuis rien a déjà fait
 *     croire à la direction qu'une journée s'était effondrée ;
 *  2. une ligne de source « auto » est le reflet d'une facture ou d'une
 *     demande de fonds. La supprimer ici laisserait la pièce d'origine en
 *     place et le solde faux : elle ne doit porter aucun bouton d'action ;
 *  3. les deux fenêtres de saisie n'existent que pour qui a le droit de
 *     saisir. Les afficher à un lecteur seul produirait un formulaire que le
 *     serveur refuse.
 */
final class MouvementsCaisseRenduTest extends TestCase
{
    /**
     * Le balisage seul, feuille de style retirée.
     *
     * Les noms de classe vivent aussi dans la méthode styles() : compter
     * « lbp-mvt-supprimer » sur la page entière revenait à compter les règles
     * CSS autant que les boutons, et un test vert ne prouvait plus rien.
     */
    private function balisage(string $html): string
    {
        return (string) preg_replace('#<style>.*?</style>#s', '', $html);
    }

    /**
     * La page telle qu'on la lit, fenêtres de saisie retirées.
     *
     * Le sélecteur de devise contient légitimement « EUR » et « XOF » : les
     * chercher sur la page entière reviendrait à confondre un choix offert à
     * la saisie avec un montant affiché.
     */
    private function horsFenetres(string $html): string
    {
        return (string) preg_replace('#<dialog\b.*?</dialog>#s', '', $this->balisage($html));
    }

    /** @return array<string, mixed> */
    private function donnees(bool $peutSaisir = true, ?float $variationSolde = null): array
    {
        return [
            'date' => '2026-10-08',
            'filtres' => ['date' => '2026-10-08', 'caisse_id' => 0, 'agence_id' => 0, 'q' => ''],
            'caisses' => [
                ['id' => 1, 'nom' => 'Caisse principale', 'agence' => 'Aéroport Port Bouët Fret', 'type' => 'PRINCIPALE', 'solde' => 4875300.0],
            ],
            'agences' => [['id' => 3402, 'name' => 'Aéroport Port Bouët Fret']],
            'kpis' => [
                'entrees' => [
                    'total' => 7482500.0, 'dossiers' => 4950000.0, 'colis' => 2187500.0,
                    'autres' => 345000.0, 'veille' => 6660000.0, 'variation' => 12.3,
                ],
                'sorties' => [
                    'total' => 2914000.0, 'dossiers' => 1480000.0, 'colis' => 734000.0,
                    'autres' => 700000.0, 'veille' => 3520000.0, 'variation' => -17.2,
                ],
                'solde' => ['total' => 4568500.0, 'veille' => 0.0, 'variation' => $variationSolde],
            ],
            'cumuls' => ['versements' => 418772500.0, 'retraits' => 395218400.0, 'solde' => 23554100.0],
            'versements' => [
                [
                    'id' => 901, 'source' => 'auto', 'date' => '2026-10-08', 'cadre' => 'FACTURE_DOSSIER',
                    'cadre_libelle' => 'Règlements de factures Dossier', 'dossier' => 'DOS-2026-01488',
                    'libelle' => 'Règlement facture FA-3402-2026-000181', 'reference' => 'REC-2026-02210',
                    'montant' => 3250000.0, 'devise' => 'XOF', 'tiers' => 'SOCIMEX CI',
                    'caisse' => 'Caisse principale', 'caissier' => 'Awa Koné',
                ],
                [
                    'id' => 903, 'source' => 'saisie', 'date' => '2026-10-08', 'cadre' => 'AUTRE',
                    'cadre_libelle' => 'Autres versements', 'dossier' => null,
                    'libelle' => 'Remboursement avance de frais', 'reference' => null,
                    'montant' => 345000.0, 'devise' => 'XOF', 'tiers' => 'Ibrahim Soro',
                    'caisse' => 'Caisse principale', 'caissier' => 'Awa Koné',
                ],
            ],
            'retraits' => [
                [
                    'id' => 701, 'source' => 'auto', 'date' => '2026-10-08', 'cadre' => 'DOSSIER',
                    'cadre_libelle' => 'Traitement de dossier', 'dossier' => 'DOS-2026-01488',
                    'libelle' => 'Demande de fonds DF-2026-00412', 'reference' => null,
                    'montant' => 1480000.0, 'devise' => 'XOF', 'tiers' => 'Régie douanière',
                    'caisse' => 'Caisse principale', 'caissier' => 'Awa Koné',
                ],
            ],
            'peutSaisir' => $peutSaisir,
        ];
    }

    // ------------------------------------------------------------------
    // La variation absente
    // ------------------------------------------------------------------

    public function test_aucun_pourcentage_quand_la_veille_etait_a_zero(): void
    {
        $html = $this->balisage(MouvementsCaisse::page($this->donnees()));

        // Les deux autres cartes ont bien leur variation : si celle du solde
        // disparaissait pour une autre raison, le test ne vaudrait rien.
        self::assertStringContainsString('+12,3 %', $html, 'La hausse des entrées doit s\'afficher.');
        self::assertStringContainsString('17,2 %', $html, 'La baisse des sorties doit s\'afficher.');

        self::assertSame(
            2,
            substr_count($html, 'lbp-mvt-var is-'),
            'Seules les deux cartes dont la veille est chiffrée portent une pastille de variation.'
        );
        self::assertStringContainsString(
            'Rien enregistré hier',
            $html,
            'L\'absence de comparaison doit être dite, sinon elle passe pour un défaut d\'affichage.'
        );
    }

    public function test_la_variation_s_affiche_des_que_la_veille_est_chiffree(): void
    {
        $html = $this->balisage(MouvementsCaisse::page($this->donnees(true, 45.5)));

        self::assertStringContainsString('+45,5 %', $html);
        self::assertStringNotContainsString('Rien enregistré hier', $html);
    }

    public function test_une_veille_identique_se_dit_en_toutes_lettres(): void
    {
        // Un « +0,0 % » n'apprend rien et attire l'oeil pour rien.
        $html = $this->balisage(MouvementsCaisse::page($this->donnees(true, 0.0)));

        self::assertStringContainsString('Au niveau d', $html);
        self::assertStringNotContainsString('0,0 %', $html);
    }

    // ------------------------------------------------------------------
    // Les lignes reprises d'ailleurs
    // ------------------------------------------------------------------

    public function test_une_ligne_automatique_ne_porte_aucune_action(): void
    {
        $html = $this->balisage(MouvementsCaisse::page($this->donnees()));

        // Deux lignes saisies sur quatre au total : autant de formulaires de
        // suppression, pas un de plus.
        self::assertSame(
            1,
            substr_count($html, 'finance/mouvements-caisse/903/supprimer'),
            'La ligne saisie doit porter sa suppression.'
        );

        foreach ([901, 701] as $automatique) {
            self::assertStringNotContainsString(
                'finance/mouvements-caisse/' . $automatique . '/supprimer',
                $html,
                "La ligne {$automatique} vient d'une pièce d'origine : elle ne se supprime pas ici."
            );
        }

        self::assertSame(
            1,
            substr_count($html, 'lbp-mvt-supprimer'),
            'Un seul bouton Supprimer : celui de la seule ligne saisie.'
        );
    }

    public function test_une_ligne_automatique_dit_d_ou_elle_vient(): void
    {
        $html = $this->balisage(MouvementsCaisse::page($this->donnees()));

        // L'indice doit être du texte, pas seulement une teinte : une couleur
        // seule n'est annoncée par aucun lecteur d'écran.
        self::assertSame(2, substr_count($html, 'Reprise automatique'));
        self::assertSame(2, substr_count($html, 'lbp-mvt-ligne is-auto'));
        self::assertStringContainsString('Se corrige sur la pièce d', $html);
    }

    // ------------------------------------------------------------------
    // Les deux fenêtres de saisie
    // ------------------------------------------------------------------

    public function test_les_deux_fenetres_de_saisie_sont_la_quand_on_peut_saisir(): void
    {
        $html = $this->balisage(MouvementsCaisse::page($this->donnees(true)));

        self::assertStringContainsString('id="mvt-versement"', $html);
        self::assertStringContainsString('id="mvt-retrait"', $html);
        self::assertStringContainsString('Effectuer un versement (encaissement)', $html);
        self::assertStringContainsString('Effectuer un retrait (décaissement)', $html);

        self::assertStringContainsString('action="/finance/mouvements-caisse/versement"', $html);
        self::assertStringContainsString('action="/finance/mouvements-caisse/retrait"', $html);

        // Deux formulaires qui écrivent : deux jetons.
        self::assertSame(3, substr_count($html, 'name="_csrf_token"'), 'Versement, retrait et suppression portent chacun leur jeton.');

        foreach (['Cadre de l&#039;encaissement', 'Nom du déposant', 'Cadre du décaissement', 'Bénéficiaire'] as $champ) {
            self::assertStringContainsString($champ, $html, "Le champ « {$champ} » de la maquette est absent.");
        }
    }

    public function test_aucune_fenetre_de_saisie_sans_le_droit_de_saisir(): void
    {
        $html = $this->balisage(MouvementsCaisse::page($this->donnees(false)));

        self::assertStringNotContainsString('id="mvt-versement"', $html);
        self::assertStringNotContainsString('id="mvt-retrait"', $html);
        self::assertStringNotContainsString('Effectuer un versement', $html);
        self::assertStringNotContainsString('Effectuer un retrait', $html);

        // Rien à écrire : plus aucun formulaire de suppression non plus.
        self::assertStringNotContainsString('lbp-mvt-supprimer', $html);
        self::assertStringNotContainsString('name="_csrf_token"', $html);

        // Et la colonne d'actions disparaît plutôt que de rester vide.
        self::assertStringNotContainsString('>Actions</th>', $html);

        self::assertStringContainsString('lecture seule', $html, 'L\'écran doit dire pourquoi il ne propose rien.');
    }

    /**
     * Le contrôleur relit « f_date », « f_caisse_id », « f_agence_id » et
     * « f_q » pour ramener le caissier sur la journée qu'il regardait. Sans
     * ces champs dans les formulaires, chaque enregistrement le renvoyait sur
     * aujourd'hui, toutes caisses confondues — et il refiltrait entre deux
     * saisies.
     */
    public function test_les_formulaires_reportent_la_journee_et_les_filtres(): void
    {
        $donnees = $this->donnees();
        $donnees['filtres'] = ['date' => '2026-09-30', 'caisse_id' => 7, 'agence_id' => 3402, 'q' => 'SOCIMEX'];

        $html = $this->balisage(MouvementsCaisse::page($donnees));

        // Les deux fenêtres de saisie et la suppression : trois formulaires.
        foreach (['f_date" value="2026-09-30', 'f_caisse_id" value="7',
            'f_agence_id" value="3402', 'f_q" value="SOCIMEX'] as $champ) {
            self::assertSame(
                3,
                substr_count($html, $champ),
                "Le champ caché « {$champ} » manque à l'un des trois formulaires qui écrivent."
            );
        }

        // Et les exports repartent avec les mêmes filtres.
        self::assertStringContainsString('date=2026-09-30&amp;caisse_id=7&amp;agence_id=3402&amp;q=SOCIMEX', $html);
    }

    /**
     * Les noms des champs sont ceux que le service relit.
     *
     * Un libellé juste et un nom de champ faux donnent un formulaire qui
     * s'affiche bien et n'enregistre rien : le serveur ne trouve pas sa clé,
     * refuse, et l'agent ne comprend pas ce qu'on lui reproche. Trois
     * d'entre eux avaient dérivé — « date », « designation », « deposant » —
     * là où le service attend « date_mouvement », « libelle » et « tiers ».
     */
    public function test_les_champs_portent_les_noms_que_le_service_relit(): void
    {
        $html = $this->balisage(MouvementsCaisse::page($this->donnees()));

        // Chaque fenêtre est examinée seule : « caisse_id » sert aussi au
        // filtre de la page, et le compter sur la page entière masquerait son
        // absence dans l'une des deux.
        foreach (['versement', 'retrait'] as $sens) {
            self::assertSame(
                1,
                preg_match('#<form[^>]+/mouvements-caisse/' . $sens . '"(.*?)</form>#s', $html, $trouve),
                "La fenêtre de {$sens} est introuvable.",
            );

            $formulaire = $trouve[1];

            foreach (['caisse_id', 'montant', 'reference', 'mode_reglement',
                'date_mouvement', 'libelle', 'tiers', 'cadre'] as $champ) {
                self::assertStringContainsString(
                    'name="' . $champ . '"',
                    $formulaire,
                    "Le champ « {$champ} » manque à la fenêtre de {$sens}.",
                );
            }

            // Les noms abandonnés ne doivent pas revenir par mégarde.
            foreach (['designation', 'deposant', 'beneficiaire'] as $ancien) {
                self::assertStringNotContainsString('name="' . $ancien . '"', $formulaire);
            }
        }
    }

    /**
     * Sans devise postée, Paris saisit en francs sans le savoir.
     *
     * Les lignes reprises de LBP portent bien leurs euros ; une saisie à la
     * main partait, elle, en francs. L'écran aurait affiché des historiques en
     * euros et des compteurs faux — pour l'agence à qui l'on venait justement
     * d'ajouter la seconde monnaie.
     */
    public function test_les_deux_fenetres_laissent_choisir_la_devise(): void
    {
        $html = $this->balisage(MouvementsCaisse::page($this->donnees()));

        foreach (['versement', 'retrait'] as $sens) {
            self::assertSame(
                1,
                preg_match('#<form[^>]+/mouvements-caisse/' . $sens . '"(.*?)</form>#s', $html, $trouve),
            );

            $formulaire = $trouve[1];

            self::assertMatchesRegularExpression(
                '#<select[^>]+name="devise"#',
                $formulaire,
                "La fenêtre de {$sens} doit laisser choisir la devise.",
            );
            self::assertStringContainsString('value="EUR"', $formulaire, 'L\'euro doit être proposé.');
            self::assertMatchesRegularExpression(
                '#<option value="XOF"[^>]*selected#',
                $formulaire,
                'Le franc reste le choix par défaut : presque toutes les agences encaissent en francs.',
            );
        }
    }

    /**
     * Un pas de saisie entier interdisait de frapper 48,20 EUR : la devise
     * aurait été choisissable et le montant, lui, impossible à écrire.
     */
    public function test_le_montant_accepte_les_centimes_de_l_euro(): void
    {
        $html = $this->balisage(MouvementsCaisse::page($this->donnees()));

        self::assertSame(2, substr_count($html, 'step="0.01"'));
        self::assertStringNotContainsString('En FCFA, sans espace ni décimale.', $html);
    }

    /**
     * Les règles que le serveur applique doivent être dites par le formulaire.
     *
     * Le service refuse un libellé vide et une date dans l'avenir. Laisser le
     * navigateur l'ignorer, c'est faire voyager la saisie pour rien et la
     * rendre à l'agent avec une fenêtre refermée.
     */
    public function test_le_formulaire_annonce_les_regles_du_serveur(): void
    {
        $html = $this->balisage(MouvementsCaisse::page($this->donnees()));

        self::assertSame(2, substr_count($html, 'max="' . date('Y-m-d') . '"'), 'Aucune date dans l\'avenir.');
        self::assertMatchesRegularExpression(
            '/<textarea[^>]*name="libelle"[^>]*required/',
            $html,
            'La désignation est obligatoire côté serveur : elle doit l\'être aussi dans le formulaire.'
        );
    }

    // ------------------------------------------------------------------
    // La structure reprise de l'ancien logiciel
    // ------------------------------------------------------------------

    public function test_l_ecran_reprend_les_trois_cartes_et_les_trois_cumuls(): void
    {
        $html = $this->balisage(MouvementsCaisse::page($this->donnees()));

        foreach (['Entrées caisse', 'Sorties caisse', 'Solde caisse'] as $carte) {
            self::assertStringContainsString($carte, $html, "Carte « {$carte} » absente.");
        }

        foreach (['Total versements', 'Total retraits'] as $cumul) {
            self::assertStringContainsString($cumul, $html, "Cumul « {$cumul} » absent.");
        }

        // La ventilation en trois colonnes des deux cartes de flux.
        self::assertSame(2, substr_count($html, 'lbp-mvt-carte-detail--3'));
        self::assertStringContainsString('finea-shell', $html, 'Même présentation que les autres écrans Finance.');
    }

    public function test_les_colonnes_des_deux_historiques_sont_celles_de_la_maquette(): void
    {
        $html = $this->balisage(MouvementsCaisse::page($this->donnees()));

        foreach (['N°', 'Date', 'Cadre', 'N° Dossier', 'Libellé', 'Référence',
            'Montant', 'Déposant', 'Caisse', 'Caissier', 'Actions'] as $colonne) {
            self::assertStringContainsString('>' . $colonne . '</th>', $html, "Colonne « {$colonne} » absente.");
        }

        self::assertStringContainsString('>Bénéficiaire</th>', $html, 'La colonne du bénéficiaire manque aux retraits.');
    }

    public function test_les_montants_se_lisent_partout_en_fcfa(): void
    {
        // Les cartes disaient FCFA et les tableaux XOF : deux mots pour la même
        // monnaie sur un même écran font douter que ce soit la même. Le code
        // ISO ne subsiste que comme valeur postée par le sélecteur de devise,
        // où il ne se lit pas — d'où l'examen hors fenêtres de saisie.
        $affiche = $this->horsFenetres(MouvementsCaisse::page($this->donnees()));

        self::assertStringNotContainsString('XOF', $affiche);
        self::assertStringContainsString('3 250 000 FCFA', $affiche);
    }

    public function test_l_ecran_se_rend_sans_aucune_donnee(): void
    {
        // Le contrôleur n'est pas encore écrit : la page doit tenir debout même
        // appelée à vide, plutôt que de tomber sur une clé manquante.
        $html = $this->balisage(MouvementsCaisse::page([]));

        self::assertStringContainsString('finea-shell', $html);
        self::assertStringContainsString('Aucun versement', $html);
        self::assertStringContainsString('Aucun retrait', $html);
    }

    public function test_l_ecran_ne_trace_ses_icones_qu_en_svg(): void
    {
        $html = $this->balisage(MouvementsCaisse::page($this->donnees()));

        self::assertSame(
            0,
            preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{231A}-\x{231B}\x{23E9}-\x{23FA}\x{FE0F}]/u', $html),
            'Les icônes de cet écran sont des SVG, jamais des emoji.'
        );
        self::assertStringContainsString('<svg class="lbp-mvt-icone"', $html);
    }

    // ------------------------------------------------------------------
    // Le second compteur, en euros
    // ------------------------------------------------------------------

    /**
     * Le jeu d'essai d'une agence qui encaisse dans les deux monnaies.
     *
     * @return array<string, mixed>
     */
    private function avecEuros(): array
    {
        $donnees = $this->donnees();

        $donnees['kpis']['entrees'] += [
            'total_eur' => 4820.0, 'dossiers_eur' => 3200.0, 'colis_eur' => 1400.0,
            'autres_eur' => 220.0, 'veille_eur' => 4460.0, 'variation_eur' => 8.1,
        ];
        $donnees['kpis']['sorties'] += [
            'total_eur' => 1250.5, 'dossiers_eur' => 900.0, 'colis_eur' => 350.5,
            'autres_eur' => 0.0, 'veille_eur' => 1400.0, 'variation_eur' => -10.7,
        ];
        $donnees['kpis']['solde'] += [
            'total_eur' => 3569.5, 'veille_eur' => 3060.0, 'variation_eur' => 16.6,
        ];
        $donnees['cumuls'] += [
            'versements_eur' => 184200.0, 'retraits_eur' => 96430.25, 'solde_eur' => 87769.75,
        ];

        return $donnees;
    }

    /**
     * Les clés en euros arrivent du service et peuvent manquer.
     *
     * Une clé absente doit valoir zéro, pas faire tomber la page : c'est
     * exactement ce qui avait cassé la suite pendant la mise en place.
     */
    public function test_l_ecran_tient_sans_aucune_cle_en_euros(): void
    {
        $affiche = $this->horsFenetres(MouvementsCaisse::page($this->donnees()));

        self::assertStringContainsString('7 482 500', $affiche, 'Les francs restent affichés.');
        self::assertStringNotContainsString('EUR', $affiche, 'Sans chiffre en euros, aucune ligne en euros.');
        self::assertStringNotContainsString('En euros', $affiche);
    }

    /**
     * La quasi-totalité des agences est en francs : un « 0,00 EUR » permanent
     * sous chaque carte deviendrait un bruit qu'on ne lit plus — y compris le
     * jour où il cesse d'être nul.
     */
    public function test_une_agence_sans_euros_n_affiche_aucun_zero_en_euros(): void
    {
        $donnees = $this->donnees();

        foreach (['entrees', 'sorties', 'solde'] as $bloc) {
            $donnees['kpis'][$bloc] += ['total_eur' => 0.0, 'veille_eur' => 0.0, 'variation_eur' => null];
        }
        $donnees['cumuls'] += ['versements_eur' => 0.0, 'retraits_eur' => 0.0, 'solde_eur' => 0.0];

        $html = $this->balisage(MouvementsCaisse::page($donnees));

        self::assertStringNotContainsString('En euros', $html);
        self::assertStringNotContainsString('0,00 EUR', $html);
    }

    public function test_la_ligne_en_euros_parait_des_qu_elle_porte_un_chiffre(): void
    {
        $html = $this->balisage(MouvementsCaisse::page($this->avecEuros()));

        // Une ligne par carte du jour.
        self::assertSame(3, substr_count($html, 'En euros'));

        // Le total, la veille et la variation propres à l'euro.
        self::assertStringContainsString('4 820,00 EUR', $html);
        self::assertStringContainsString('hier 4 460,00 EUR', $html);
        self::assertStringContainsString('+8,1 %', $html);

        // La ventilation aussi : c'est elle qui dit par quelle porte l'argent entre.
        self::assertStringContainsString('3 200,00 EUR', $html);
        self::assertStringContainsString('1 400,00 EUR', $html);

        // Et les trois cumuls.
        self::assertStringContainsString('184 200,00 EUR', $html);
        self::assertStringContainsString('96 430,25 EUR', $html);
        self::assertStringContainsString('87 769,75 EUR', $html);

        // Les francs ne bougent pas : aucune conversion, deux compteurs séparés.
        self::assertStringContainsString('7 482 500', $html);
        self::assertStringContainsString('418 772 500 FCFA', $html);
    }

    /**
     * Dans une carte qui parle euros, un zéro sur l'une des trois colonnes est
     * un renseignement — rien n'est entré par cette porte-là — et non le bruit
     * que l'on évite ailleurs.
     */
    public function test_une_colonne_vide_reste_lisible_dans_une_carte_en_euros(): void
    {
        $html = $this->balisage(MouvementsCaisse::page($this->avecEuros()));

        self::assertStringContainsString('0,00 EUR', $html, 'La colonne « Autres » des sorties est à zéro en euros.');
    }

    // ------------------------------------------------------------------
    // Les deux documents
    // ------------------------------------------------------------------

    /**
     * Un jeu avec une ligne en euros : les documents doivent la reprendre.
     *
     * @return array<string, mixed>
     */
    private function pourExport(): array
    {
        $donnees = $this->avecEuros();

        $donnees['versements'][] = [
            'id' => 905, 'source' => 'saisie', 'date' => '2026-10-08', 'cadre' => 'FACTURE_COLIS',
            'cadre_libelle' => 'Règlements de factures Colis', 'dossier' => '483-20428520',
            'libelle' => 'Règlement agence de Paris', 'reference' => '0041782-99',
            'montant' => 4820.0, 'devise' => 'EUR', 'tiers' => 'LBP Paris',
            'caisse' => 'Caisse Paris', 'caissier' => 'Awa Koné',
        ];

        return $donnees;
    }

    public function test_le_pdf_reprend_les_colonnes_et_les_lignes_de_l_ecran(): void
    {
        $pdf = MouvementsCaisse::exportPdf($this->pourExport(), 'versements', 'Comptable LBP');

        foreach (['N°', 'Date', 'Cadre', 'N° Dossier', 'Libellé', 'Référence',
            'Montant', 'Déposant', 'Caisse', 'Caissier'] as $colonne) {
            self::assertStringContainsString('>' . $colonne . '</th>', $pdf, "Colonne « {$colonne} » absente du PDF.");
        }

        // La colonne « Actions » n'existe que pour agir : elle n'a rien à faire
        // sur un document imprimé.
        self::assertStringNotContainsString('>Actions</th>', $pdf);

        foreach (['Règlement facture FA-3402-2026-000181', 'Remboursement avance de frais',
            'Règlement agence de Paris', 'SOCIMEX CI', 'Awa Koné'] as $valeur) {
            self::assertStringContainsString($valeur, $pdf, "La ligne « {$valeur} » manque au PDF.");
        }
    }

    public function test_le_pdf_s_imprime_a_l_ouverture_et_repete_son_entete(): void
    {
        $pdf = MouvementsCaisse::exportPdf($this->pourExport(), 'retraits', 'Comptable LBP');

        self::assertStringContainsString('window.print()', $pdf);
        self::assertStringContainsString('thead{display:table-header-group}', $pdf);
        self::assertStringContainsString('<!doctype html>', $pdf);
        self::assertStringContainsString('Retraits de caisse — journée du 08/10/2026', $pdf);
    }

    public function test_le_pdf_porte_sa_portee_son_compte_et_son_editeur(): void
    {
        $donnees = $this->pourExport();
        $donnees['filtres'] = ['date' => '2026-10-08', 'caisse_id' => 1, 'agence_id' => 3402, 'q' => 'SOCIMEX'];

        $pdf = MouvementsCaisse::exportPdf($donnees, 'versements', 'Comptable LBP');

        // Un export sorti d'un écran filtré ne montre qu'une partie de la
        // journée : le document doit le dire, sinon on lui fait dire un total
        // qui n'en est pas un.
        self::assertStringContainsString('Caisse : Caisse principale', $pdf);
        self::assertStringContainsString('Agence : Aéroport Port Bouët Fret', $pdf);
        self::assertStringContainsString('Recherche : SOCIMEX', $pdf);
        self::assertStringContainsString('3 ligne(s)', $pdf);
        self::assertStringContainsString('par Comptable LBP', $pdf);
    }

    public function test_le_pdf_dit_quelles_lignes_sont_reprises(): void
    {
        $pdf = MouvementsCaisse::exportPdf($this->pourExport(), 'versements', '');

        // Une seule des trois lignes de versement vient d'une pièce d'origine.
        self::assertSame(1, substr_count($pdf, 'Reprise automatique'));
        self::assertStringNotContainsString('par ', substr($pdf, 0, (int) strpos($pdf, '<table')));
    }

    public function test_le_tableur_garde_la_devise_et_l_origine_en_colonnes(): void
    {
        $excel = MouvementsCaisse::exportExcel($this->pourExport(), 'versements');

        self::assertStringContainsString('<th>Devise</th>', $excel);
        self::assertStringContainsString('<th>Origine</th>', $excel);
        self::assertSame(2, substr_count($excel, '>FCFA<'));
        self::assertSame(1, substr_count($excel, '>EUR<'));
        self::assertSame(1, substr_count($excel, 'Reprise automatique'));
        self::assertSame(2, substr_count($excel, 'Saisie de caisse'));
    }

    /**
     * Le montant part en nombre, pas en texte : sans cela, aucune somme ne
     * fonctionne dans le tableur, et c'est la première chose qu'on y fait.
     */
    public function test_le_tableur_laisse_les_montants_additionnables(): void
    {
        $excel = MouvementsCaisse::exportExcel($this->pourExport(), 'versements');

        self::assertStringContainsString('<td>3250000.00</td>', $excel);
        self::assertStringContainsString('<td>4820.00</td>', $excel);
    }

    /**
     * « 483-20428520 » part en date si la colonne n'est pas déclarée texte, et
     * le numéro de dossier devient illisible.
     */
    public function test_le_tableur_protege_les_codes_contre_la_conversion_en_date(): void
    {
        $excel = MouvementsCaisse::exportExcel($this->pourExport(), 'versements');

        self::assertMatchesRegularExpression(
            '/mso-number-format:\'\\\\@\'">483-20428520</',
            $excel,
            'Le n° de dossier doit être déclaré texte.'
        );
        self::assertMatchesRegularExpression(
            '/mso-number-format:\'\\\\@\'">0041782-99</',
            $excel,
            'La référence doit être déclarée texte.'
        );
    }

    public function test_les_deux_documents_totalisent_chaque_monnaie_separement(): void
    {
        // Additionner un euro et un franc ne donne un résultat juste dans
        // aucune des deux monnaies.
        $pdf = MouvementsCaisse::exportPdf($this->pourExport(), 'versements', '');

        self::assertStringContainsString('3 595 000 FCFA · 4 820,00 EUR', $pdf);
        self::assertStringContainsString('Total des lignes', $pdf);
    }

    public function test_les_exports_tiennent_sans_aucune_ligne(): void
    {
        $vide = ['date' => '2026-10-08', 'filtres' => [], 'versements' => [], 'retraits' => []];

        $pdf = MouvementsCaisse::exportPdf($vide, 'versements', 'Comptable LBP');
        $excel = MouvementsCaisse::exportExcel($vide, 'retraits');

        self::assertStringContainsString('Aucun mouvement sur cette sélection.', $pdf);
        self::assertStringContainsString('0 ligne(s)', $pdf);
        self::assertStringNotContainsString('<tfoot>', $pdf, 'Pas de ligne de total sans ligne à totaliser.');
        self::assertStringContainsString('<th>Devise</th>', $excel);
    }

    /**
     * Les routes passent le sens dans l'URL : une faute de frappe ne doit pas
     * rendre une page blanche au comptable.
     */
    public function test_un_sens_inconnu_retombe_sur_les_versements(): void
    {
        $pdf = MouvementsCaisse::exportPdf($this->pourExport(), 'nimporte-quoi', '');

        self::assertStringContainsString('Versements de caisse', $pdf);
        self::assertStringContainsString('>Déposant</th>', $pdf);
    }

    public function test_les_documents_n_emploient_aucun_emoji(): void
    {
        $motif = '/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{231A}-\x{231B}\x{23E9}-\x{23FA}\x{FE0F}]/u';

        self::assertSame(0, preg_match($motif, MouvementsCaisse::exportPdf($this->pourExport(), 'versements', '')));
        self::assertSame(0, preg_match($motif, MouvementsCaisse::exportExcel($this->pourExport(), 'retraits')));
    }
}
