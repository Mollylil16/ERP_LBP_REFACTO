<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Ce qui rend l'ERP utilisable sur un téléphone et une tablette.
 *
 * Signalé le 24/09/2026 : « on peine à manipuler le logiciel sur téléphone et
 * tablette ». L'audit a montré quatre causes, toutes vérifiées ici parce
 * qu'elles se réintroduisent sans bruit :
 *
 * 1. le bouton « Menu » n'avait d'écouteur que dans rh.js, chargé par le seul
 *    module RH. Partout ailleurs, sous 850 px, la barre latérale restait hors
 *    écran et le bouton ne faisait rien : plus aucune navigation ;
 * 2. l'enveloppe qui fait défiler les tableaux n'était déclarée que dans
 *    colisage.css, alors que les tableaux font 760 px au minimum ;
 * 3. finance.css annulait ce défilement avec overflow: hidden ;
 * 4. la déconnexion était masquée sous 620 px, et c'est le seul lien de sortie.
 *
 * Seconde passe, 28/10/2026. Les agents du terrain travaillent debout, une
 * main sur l'appareil, et le logiciel restait « très difficile à manier ». La
 * cause mesurée n'était plus un débordement — les quatre ci-dessus étaient
 * traitées — mais l'inverse : un tableau de sept colonnes comprimé dans
 * 390 px laissait moins de soixante pixels par colonne, et les mots se
 * coupaient en plein milieu. Sous 640 px chaque ligne devient donc une carte,
 * et chaque cellule porte le nom de sa colonne.
 */
final class RenduMobileTest extends TestCase
{
    private function fichier(string $chemin): string
    {
        return (string) file_get_contents(BASE_PATH . '/' . $chemin);
    }

    /**
     * Le menu doit vivre dans le script chargé par tous les gabarits, sinon il
     * ne sert qu'au module qui charge son propre script.
     */
    public function test_le_menu_mobile_est_dans_le_script_commun(): void
    {
        $commun = $this->fichier('public/assets/js/components.js');

        self::assertStringContainsString('data-module-menu', $commun);
        self::assertStringContainsString('moduleSidebar', $commun);
        self::assertStringContainsString('module-sidebar-backdrop', $commun, 'Le menu ouvert doit poser un voile.');
        self::assertStringContainsString('aria-expanded', $commun);

        // Un second écouteur ouvrirait puis refermerait aussitôt le panneau.
        $rh = $this->fichier('public/assets/js/rh.js');
        self::assertStringNotContainsString('data-module-menu', $rh, 'Le menu ne doit plus être géré deux fois.');
    }

    /**
     * Le menu ouvert doit passer au-dessus de son propre voile. Pose a
     * z-index 40 sous une barre laterale a 20, le voile recouvrait le menu :
     * chaque appui tombait sur lui, et sur iPhone il n'y repondait meme pas —
     * Safari n'envoie « clic » a un simple div que s'il se declare cliquable.
     * Ecran fige, aucun bouton utilisable.
     */
    public function test_le_menu_ouvert_passe_au_dessus_de_son_voile(): void
    {
        $socle = $this->fichier('public/assets/css/finea-ui.css');
        $mobile = substr($socle, (int) strpos($socle, '@media (max-width: 850px)'));

        preg_match('/\.module-sidebar \{(.*?)\}/s', $mobile, $barre);
        self::assertNotEmpty($barre, 'Regle de la barre laterale introuvable.');
        preg_match('/z-index: (\d+)/', $barre[1], $rangBarre);
        self::assertNotEmpty($rangBarre, 'La barre laterale doit declarer son rang.');

        preg_match('/\.module-sidebar-backdrop \{(.*?)\}/s', $socle, $voile);
        preg_match('/z-index: (\d+)/', $voile[1], $rangVoile);

        self::assertGreaterThan(
            (int) $rangVoile[1],
            (int) $rangBarre[1],
            'Le voile recouvrirait le menu et avalerait chaque appui.'
        );

        self::assertStringContainsString('cursor: pointer;', $voile[1], 'Sans cela, iOS ignore les appuis sur le voile.');

        // Le menu de Finance compte dix-huit entrees : il doit pouvoir defiler.
        self::assertStringContainsString('overflow-y: auto;', $barre[1]);
    }

    public function test_le_script_commun_est_charge_par_le_gabarit_des_modules(): void
    {
        $gabarit = $this->fichier('views/layouts/module.php');

        self::assertStringContainsString('js/components.js', $gabarit);
        self::assertStringContainsString('data-module-menu', $gabarit);
    }

    /**
     * Les tableaux font 760 px au minimum : sans enveloppe qui défile, leurs
     * colonnes de droite — montants, actions — sont inatteignables au doigt.
     */
    public function test_l_enveloppe_des_tableaux_defile_dans_toute_l_application(): void
    {
        $socle = $this->fichier('public/assets/css/finea-ui.css');

        self::assertMatchesRegularExpression(
            '/\.finea-table-wrap,\s*\.finea-table-wrapper \{[^}]*overflow-x: auto;/s',
            $socle,
            "Les deux noms d'enveloppe doivent défiler, et depuis la feuille commune."
        );

        $finance = $this->fichier('public/assets/css/finance.css');
        self::assertStringNotContainsString(
            'overflow: hidden;',
            $finance,
            'finance.css annulait le défilement des tableaux du module.'
        );
    }

    public function test_la_deconnexion_reste_atteignable_sur_un_petit_ecran(): void
    {
        $socle = $this->fichier('public/assets/css/finea-ui.css');
        $petit = substr($socle, (int) strpos($socle, '@media (max-width: 620px)'));

        self::assertStringContainsString('.module-profile a', $petit);
        self::assertStringNotContainsString(
            ".module-profile a {\n    display: none;",
            $petit,
            "C'est le seul lien de sortie du gabarit : le masquer enferme l'utilisateur."
        );
    }

    /**
     * 100vw inclut la barre de défilement : le bloc dépasse alors de quelques
     * pixels et toute la page se met à défiler de côté.
     */
    public function test_aucun_bloc_ne_se_mesure_en_100vw_dans_le_gabarit(): void
    {
        self::assertStringNotContainsString('100vw', $this->fichier('views/layouts/module.php'));
    }

    /**
     * Les grilles et les rangées posées en style inline — 137 dans le projet —
     * ne peuvent être reprises qu'en bloc.
     */
    public function test_les_grilles_en_style_inline_se_replient_sur_telephone(): void
    {
        $socle = $this->fichier('public/assets/css/finea-ui.css');
        $mobile = substr($socle, (int) strpos($socle, '@media (max-width: 850px)'));

        self::assertStringContainsString('[style*="grid-template-columns"]', $mobile);
        self::assertStringContainsString('grid-template-columns: 1fr !important;', $mobile);
        self::assertStringContainsString('flex-wrap: wrap;', $mobile);
        // Un champ ne doit jamais imposer sa largeur à la page.
        self::assertStringContainsString('min-width: 0 !important;', $mobile);
    }

    // ------------------------------------------------------------------
    // Seconde passe : la ligne devient une carte
    // ------------------------------------------------------------------

    private function scriptCommun(): string
    {
        return $this->fichier('public/assets/js/components.js');
    }

    private function socle(): string
    {
        return $this->fichier('public/assets/css/finea-ui.css');
    }

    /**
     * Le bloc de style qui ne vaut que pour un téléphone.
     *
     * Une règle de carte qui s'échapperait de sa requête média casserait
     * tous les tableaux du bureau d'un coup : c'est la première chose à tenir.
     */
    public function test_les_cartes_ne_valent_que_sous_un_ecran_de_telephone(): void
    {
        $socle = $this->socle();
        $depart = strpos($socle, 'table[data-mobile="cartes"]');

        self::assertNotFalse($depart, 'Aucune règle de carte dans la feuille de style.');

        $media = strrpos(substr($socle, 0, $depart), '@media');
        self::assertNotFalse($media, 'Les cartes ne sont pas sous une requête média.');

        $ouverture = substr($socle, $media, $depart - $media);

        self::assertSame(1, preg_match('/max-width: (\\d{3})px/', $ouverture, $trouve));
        self::assertLessThanOrEqual(
            640,
            (int) $trouve[1],
            "Au-dessus de 640 px un tableau tient encore : les cartes n'ont rien à y faire."
        );
    }

    /**
     * L'étiquette vient de l'en-tête, et de nulle part ailleurs.
     *
     * C'est ce qui permet aux vingt-deux composants qui fabriquent leurs
     * tableaux à la main de n'avoir rien à changer, et à tout tableau écrit
     * demain d'être lisible au téléphone sans qu'on y pense.
     */
    public function test_l_etiquette_est_posee_depuis_l_en_tete(): void
    {
        $script = $this->scriptCommun();

        self::assertStringContainsString('tableauxEnCartes', $script);
        self::assertStringContainsString('thead tr', $script);
        self::assertStringContainsString('dataset.etiquette', $script);
        self::assertStringContainsString('content: attr(data-etiquette)', $this->socle());
    }

    /** Elle tourne au chargement, et reste appelable après coup. */
    public function test_la_mise_en_cartes_tourne_au_chargement(): void
    {
        $script = $this->scriptCommun();
        $demarrage = substr($script, (int) strpos($script, 'DOMContentLoaded'));

        self::assertStringContainsString('tableauxEnCartes()', $demarrage);
        self::assertStringContainsString(
            'window.FineaComponents = { init, menuMobile, tableauxEnCartes }',
            $script
        );
    }

    /**
     * Les trois garde-fous, sans lesquels la transformation ferait des dégâts.
     *
     * Un tableau sans en-tête ne peut pas étiqueter ses cellules ; un tableau
     * étroit n'a rien à gagner à tripler de hauteur ; et une cellule qui couvre
     * plusieurs colonnes — un total, un « aucune ligne » — n'appartient à
     * aucune d'elles et ne doit porter aucun nom.
     */
    public function test_les_trois_garde_fous_de_la_mise_en_cartes(): void
    {
        $bloc = substr($this->scriptCommun(), (int) strpos($this->scriptCommun(), 'const tableauxEnCartes'));

        self::assertStringContainsString('entetes.length === 0', $bloc, "Sans en-tête, on ne transforme pas.");
        self::assertStringContainsString('entetes.length <= 3', $bloc, 'Un tableau étroit reste un tableau.');
        self::assertStringContainsString('colSpan', $bloc);
        self::assertStringContainsString('dataset.pleineLargeur', $bloc);
    }

    /**
     * Les documents imprimables gardent leurs tableaux.
     *
     * Un bordereau, un rapport, le journal de caisse : ce sont des pages à
     * part, sans coque et sans ce script. Les mettre en cartes sur du papier
     * n'aurait aucun sens — et la portée du sélecteur est ce qui les en
     * préserve.
     */
    public function test_les_documents_imprimables_restent_des_tableaux(): void
    {
        $bloc = substr($this->scriptCommun(), (int) strpos($this->scriptCommun(), 'const tableauxEnCartes'), 1400);

        self::assertStringContainsString('.module-content table', $bloc);
        self::assertStringContainsString('.finea-shell table', $bloc);

        $journal = $this->fichier('app/View/Components/MouvementsCaisse.php');
        $extrait = substr($journal, (int) strpos($journal, 'public static function journalPdf'), 3000);

        self::assertStringContainsString('<!doctype html>', $extrait);
        self::assertStringNotContainsString('module-content', $extrait);
        self::assertStringNotContainsString('finea-shell', $extrait);
    }

    /**
     * Une cellule vide ne se voit pas dans un tableau ; en carte elle
     * montrerait une étiquette suivie de rien.
     */
    public function test_une_cellule_vide_disparait_de_la_carte(): void
    {
        $bloc = substr($this->scriptCommun(), (int) strpos($this->scriptCommun(), 'const tableauxEnCartes'));

        self::assertStringContainsString('dataset.vide', $bloc);
        self::assertStringContainsString('td[data-vide]', $this->socle());
    }

    /**
     * Le texte des champs ne rapetisse pas.
     *
     * Sous 16 px, iOS agrandit la page à chaque fois qu'on touche un champ, et
     * il faut repincer pour revoir le formulaire. C'est l'une des causes les
     * plus courantes d'un « impossible à manier », et la seule protection est
     * de ne jamais fixer une police plus petite sur les champs.
     */
    public function test_les_champs_ne_rapetissent_pas_la_police(): void
    {
        $socle = $this->socle();
        $champs = substr($socle, (int) strpos($socle, '.finea-input,'), 400);

        self::assertStringContainsString('font: inherit', $champs);
    }
}
