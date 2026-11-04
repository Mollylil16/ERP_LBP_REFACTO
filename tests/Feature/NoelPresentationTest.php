<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Noel\NoelCarte;
use App\Services\Noel\NoelProcedure;
use App\Services\Noel\NoelRepondeur;
use App\Services\Noel\NoelVocabulaire;
use Tests\TestCase;

/**
 * Présenter un module, et donner la marche à suivre.
 *
 * Constaté en production le 23/10/2026 : « parle-moi du module finance »
 * restait sans réponse. NOEL savait où étaient les écrans, mais pas raconter
 * ce qu'on y fait — alors que c'est par là qu'on commence quand on découvre le
 * logiciel.
 */
final class NoelPresentationTest extends TestCase
{
    private function noel(): NoelRepondeur
    {
        return new NoelRepondeur(new NoelCarte());
    }

    public function test_une_demande_de_presentation_est_reconnue(): void
    {
        foreach ([
            'parle moi du module finance',
            'c est quoi la logistique',
            'a quoi sert le crm',
            'explique moi les entrepots',
        ] as $question) {
            $lu = NoelVocabulaire::comprendre($question);

            self::assertSame(
                NoelVocabulaire::INTENTION_PRESENTATION,
                $lu['intention'],
                'Mal lu : ' . $question
            );
            self::assertNotNull($lu['module'], 'Module non reconnu : ' . $question);
        }
    }

    public function test_un_module_nomme_seul_vaut_une_presentation(): void
    {
        $lu = NoelVocabulaire::comprendre('la logistique');

        self::assertSame('logistique', $lu['module']);
        self::assertSame(NoelVocabulaire::INTENTION_PRESENTATION, $lu['intention']);
    }

    public function test_l_expression_la_plus_longue_gagne(): void
    {
        // « transit douane » doit l'emporter sur « transit » seul, sinon on
        // présente le mauvais module.
        self::assertSame('transit-douane', NoelVocabulaire::comprendre('le transit douane')['module']);
    }

    public function test_une_recherche_d_ecran_ne_devient_pas_une_presentation(): void
    {
        /*
         * « où je facture » attend une adresse, « c'est quoi la facturation »
         * attend une explication. Répondre un cours à la première serait aussi
         * inutile que répondre un lien à la seconde.
         */
        $lu = NoelVocabulaire::comprendre('ou je soumets mon point de caisse');

        self::assertSame(NoelVocabulaire::INTENTION_OU, $lu['intention']);
    }

    public function test_la_presentation_dit_a_quoi_sert_le_module(): void
    {
        $reponse = $this->noel()->repondre('parle moi du module finance');

        self::assertStringContainsString('Finance', $reponse['texte']);
        self::assertStringContainsString('écrans', $reponse['texte']);
        self::assertStringContainsString('groupes', $reponse['texte']);
        self::assertNotEmpty($reponse['liens'], 'Une présentation doit proposer par où entrer.');
    }

    public function test_la_presentation_amorce_la_question_suivante(): void
    {
        /*
         * Terminer par « dites-moi ce que vous voulez faire » laisse la
         * personne devant une page blanche. Un exemple concret lui donne le
         * ton et le vocabulaire.
         */
        $reponse = $this->noel()->repondre('parle moi du module finance');

        self::assertStringContainsString('comment je fais pour', $reponse['texte']);
        self::assertStringContainsString('point de caisse', $reponse['texte']);
    }

    public function test_un_module_ferme_n_est_jamais_decrit(): void
    {
        /*
         * Le module RH est invisible pour le reste du personnel. Le présenter
         * reviendrait à apprendre à quelqu'un qu'il y a une porte — ce que ce
         * module interdit précisément.
         */
        $reponse = $this->noel()->repondre('explique moi le module rh');

        self::assertStringNotContainsString('Ressources humaines', $reponse['texte']);
        self::assertStringNotContainsString('écrans', $reponse['texte']);
        self::assertSame([], $reponse['liens']);
    }

    public function test_une_demande_de_marche_a_suivre_est_reconnue(): void
    {
        foreach ([
            'comment je fais pour encaisser un reglement',
            'quelles sont les etapes pour enregistrer un colis',
            'par ou je commence pour soumettre mon point',
        ] as $question) {
            self::assertSame(
                NoelVocabulaire::INTENTION_PROCEDURE,
                NoelVocabulaire::comprendre($question)['intention'],
                'Mal lu : ' . $question
            );
        }
    }

    public function test_la_marche_a_suivre_donne_les_gestes_dans_l_ordre(): void
    {
        $reponse = $this->noel()->repondre('comment faire pour encaisser un reglement');

        self::assertStringContainsString('1. ', $reponse['texte']);
        self::assertStringContainsString('5. ', $reponse['texte']);
        self::assertStringContainsString('A retenir', $reponse['texte']);
    }

    public function test_une_procedure_se_refuse_comme_un_ecran_se_refuse(): void
    {
        /*
         * Raconter comment intégrer un collaborateur à quelqu'un qui n'a pas
         * accès aux Ressources humaines reviendrait à lui décrire une pièce
         * fermée à clef — et à lui apprendre qu'elle existe.
         */
        $reponse = $this->noel()->repondre('comment je fais pour integrer un collaborateur');

        self::assertStringNotContainsString('1. ', $reponse['texte']);
        self::assertStringContainsString('vous ne pouvez pas ouvrir', $reponse['texte']);
        self::assertSame([], $reponse['liens']);
    }

    public function test_aucune_procedure_n_est_improvisee(): void
    {
        /*
         * NOEL ne doit jamais inventer une marche à suivre : quelqu'un la
         * suivrait, et elle serait fausse. Quand elle n'est pas écrite, il le
         * dit. C'est la contrepartie de l'absence d'intelligence artificielle :
         * ce qu'il explique, un humain l'a écrit et relu.
         */
        self::assertNull(NoelProcedure::pour('comptabilite'));
        self::assertNull(NoelProcedure::pour(null, null));
        self::assertNotNull(NoelProcedure::pour('point_caisse'));
    }

    public function test_aucune_adresse_citee_par_noel_n_est_morte(): void
    {
        /*
         * Constaté le 28/10/2026 : quatre adresses du dictionnaire ne
         * menaient nulle part — « logistique/groupage-codes-non-payes »,
         * « admin/utilisateurs », « admin/agences »,
         * « espace-employe/demandes ». Elles étaient silencieusement écartées
         * par le filtre des droits, donc NOEL ne proposait simplement rien là
         * où il aurait dû orienter. Un lien mort ne se voit pas : il se mesure.
         */
        $sources = '';
        foreach (['NoelVocabulaire', 'NoelProcedure'] as $classe) {
            $sources .= (string) file_get_contents(BASE_PATH . '/app/Services/Noel/' . $classe . '.php');
        }

        preg_match_all("/'ecrans' => \[([^\]]*)\]/", $sources, $blocs);

        $adresses = [];
        foreach ($blocs[1] as $bloc) {
            preg_match_all("/'([a-z0-9\/\-?=]+)'/", $bloc, $trouvees);
            foreach ($trouvees[1] as $adresse) {
                $adresses[$adresse] = $adresse;
            }
        }

        self::assertNotEmpty($adresses);

        $routes = '';
        foreach (glob(BASE_PATH . '/routes/*.php') ?: [] as $fichier) {
            $routes .= (string) file_get_contents($fichier);
        }

        $mortes = [];
        foreach ($adresses as $adresse) {
            $chemin = explode('?', $adresse)[0];
            $reste = '/' . (str_contains($chemin, '/') ? explode('/', $chemin, 2)[1] : $chemin);
            $dernier = '/' . basename($chemin);

            $declaree = str_contains($routes, "get('" . $reste . "'")
                || str_contains($routes, "get('" . $dernier . "'")
                || str_contains($routes, "group('" . $reste . "'");

            if (!$declaree) {
                $mortes[] = $adresse;
            }
        }

        self::assertSame([], $mortes, 'Adresses sans route : ' . implode(', ', $mortes));
    }

    // ------------------------------------------------------------------
    // Expliquer un module qu'on n'a pas nommé
    // ------------------------------------------------------------------

    /**
     * Personne ne parle en noms de modules.
     *
     * Signalé par la direction le 03/11/2026 : NOEL « n'arrive pas toujours
     * à expliquer chaque module ». La cause mesurée n'était pas une
     * description manquante — les seize en ont une — mais la reconnaissance :
     * l'intention « présentation » exige qu'un module soit nommé, et
     * « comptabilité », « paie » ou « pointage » ne nomment aucun module.
     *
     * L'adresse d'un écran, elle, commence par son module. On remonte donc
     * de l'écran au module, ce qui évite d'allonger la liste des synonymes
     * d'un mot chaque fois qu'on en découvre un.
     */
    public function test_un_mot_qui_ne_nomme_aucun_module_remonte_jusqu_au_sien(): void
    {
        foreach ([
            'comptabilite' => 'finance',
            'paie' => 'rh',
            'pointage' => 'rh',
            'conges' => 'rh',
        ] as $mot => $attendu) {
            $lu = NoelVocabulaire::comprendre("c'est quoi la " . $mot . ' ?');

            self::assertSame($attendu, $lu['module'], $mot);
            self::assertSame(NoelVocabulaire::INTENTION_PRESENTATION, $lu['intention'], $mot);
        }
    }

    /**
     * Et au bout, une vraie explication plutôt qu'une adresse.
     *
     * Avant ce rattrapage, « explique-moi la comptabilité » répondait
     * « C'est ici. » suivi d'un lien. La question demandait ce que c'est, pas
     * où c'est.
     *
     * L'exemple est pris sur Finance et non sur la paie : le module RH est
     * fermé hors session, et NOEL refuse de décrire une porte qu'on ne peut
     * pas ouvrir — ce que vérifie déjà
     * test_un_module_ferme_n_est_jamais_decrit().
     */
    public function test_un_mot_de_metier_recoit_une_explication_et_non_une_adresse(): void
    {
        $reponse = $this->noel()->repondre('explique moi la comptabilite');

        self::assertStringContainsString('Finance', $reponse['texte']);
        self::assertStringContainsString('écrans', $reponse['texte']);
        self::assertStringNotContainsString("C'est ici", $reponse['texte']);
    }

    /**
     * Le module le mieux représenté l'emporte ; à égalité, le premier écran.
     *
     * La liste d'écrans d'un concept est écrite par ordre de pertinence :
     * « paie » commence par rh/paie avant espace-employe/dashboard. Cet ordre
     * est une information, et s'en priver revenait à ne rien répondre sur la
     * paie, les congés, les clients et le groupage.
     */
    public function test_le_premier_ecran_tranche_les_egalites(): void
    {
        self::assertSame('crm', NoelVocabulaire::comprendre("c'est quoi un client ?")['module']);
        self::assertSame('colisage', NoelVocabulaire::comprendre("c'est quoi le groupage ?")['module']);
    }

    /**
     * Le vocabulaire entier doit savoir dire de quel module il parle.
     *
     * C'est la mesure qui tient la promesse faite à la direction : 502
     * expressions passées à la question « c'est quoi … ? », et aucune qui
     * laisse NOEL sans module à présenter. Mesuré le 03/11/2026.
     */
    public function test_tout_le_vocabulaire_remonte_a_un_module(): void
    {
        $concepts = (new \ReflectionClass(NoelVocabulaire::class))->getConstant('CONCEPTS');

        self::assertIsArray($concepts);
        self::assertNotEmpty($concepts);

        $muets = [];
        $total = 0;

        foreach ($concepts as $cle => $concept) {
            foreach ($concept['mots'] as $mot) {
                if (mb_strlen((string) $mot) < 4) {
                    continue;
                }

                $total++;

                if (NoelVocabulaire::comprendre("c'est quoi " . $mot . ' ?')['module'] === null) {
                    $muets[] = $cle . ' : ' . $mot;
                }
            }
        }

        self::assertGreaterThan(400, $total, 'Le vocabulaire a maigri : v\'rifiez ce qui a \'t\' retir\'.');
        self::assertSame([], array_slice($muets, 0, 10), count($muets) . ' expression(s) sans module.');
    }

    /**
     * Mais une question qui demande où reste une question qui demande où.
     *
     * Le rattrapage ne doit pas transformer « où je soumets mon point de
     * caisse » en cours sur la Finance : l'intention « présentation » exige
     * toujours que la phrase demande une explication.
     */
    public function test_le_rattrapage_ne_transforme_pas_une_adresse_en_cours(): void
    {
        $lu = NoelVocabulaire::comprendre('ou je soumets mon point de caisse');

        self::assertSame(NoelVocabulaire::INTENTION_OU, $lu['intention']);
    }

    // ------------------------------------------------------------------
    // La precision : repondre la chose demandee, et pas son voisinage
    // ------------------------------------------------------------------

    /**
     * Une question pointue appelle une réponse pointue.
     *
     * « C'est quoi un point de caisse ? » nomme une CHOSE, pas un module.
     * Répondre par la présentation entière de Finance — vingt-deux écrans,
     * sept groupes — noyait la question, et les quatre liens rendus ne
     * contenaient même pas celui des points de caisse. Mesuré le 03/11/2026.
     */
    public function test_une_question_pointue_recoit_l_ecran_demande(): void
    {
        $reponse = $this->noel()->repondre("c'est quoi un point de caisse ?");

        self::assertStringContainsString('point de caisse', mb_strtolower($reponse['texte']));
        self::assertCount(1, $reponse['liens'], 'Un seul écran, celui qu\'on demande.');
        self::assertStringContainsString('Points de Caisse', $reponse['liens'][0]['label'] ?? '');

        // Et surtout pas le catalogue du module.
        self::assertStringNotContainsString('22 ecran', $reponse['texte']);
        self::assertStringNotContainsString('groupes', $reponse['texte']);
    }

    /** Nommer le module, en revanche, appelle bien sa présentation entière. */
    public function test_nommer_le_module_appelle_toujours_sa_presentation(): void
    {
        $reponse = $this->noel()->repondre('parle moi du module finance');

        self::assertStringContainsString('écrans', $reponse['texte']);
        self::assertStringContainsString('groupes', $reponse['texte']);
    }

    /**
     * Le mot « caisse » ne doit pas tout avaler.
     *
     * Il traverse l'appro, le point de caisse, les mouvements et les fonds :
     * l'entendre n'apprend presque rien. Le concept du point de caisse le
     * revendiquait pourtant à lui seul et emportait toute phrase qui le
     * contenait — « c'est quoi un appro de caisse » recevait une explication
     * sur la clôture du soir.
     */
    public function test_le_mot_caisse_n_emporte_plus_toute_la_phrase(): void
    {
        foreach ([
            "c'est quoi un appro de caisse ?" => 'appro',
            'comment valider un appro de caisse' => 'appro',
            "c'est quoi les mouvements de caisse ?" => 'mouvements_caisse',
            "c'est quoi le solde de la veille ?" => 'mouvements_caisse',
            "c'est quoi un point de caisse ?" => 'point_caisse',
        ] as $question => $attendu) {
            self::assertSame($attendu, NoelVocabulaire::comprendre($question)['concept'], $question);
        }
    }

    /**
     * Un concept vaut sa meilleure preuve, pas la somme de toutes.
     *
     * Additionner double-comptait les expressions qui se recouvrent : sur
     * « le point de caisse de mon agence », le concept « agence » encaissait
     * « agence » puis « mon agence » — les mêmes mots deux fois — et battait
     * « point de caisse ».
     */
    public function test_une_expression_longue_resiste_a_un_mot_rare_voisin(): void
    {
        $lu = NoelVocabulaire::comprendre('je veux voir le point de caisse de mon agence');

        self::assertSame('point_caisse', $lu['concept']);
    }

    /**
     * Le bon de caisse sort d'une demande de fonds.
     *
     * La route est /finance/fonds/{id}/bon-caisse-pdf. Les factures le
     * revendiquaient aussi, et gagnaient : NOEL envoyait aux factures
     * quelqu'un qui cherchait un bon de caisse.
     */
    public function test_le_bon_de_caisse_mene_aux_demandes_de_fonds(): void
    {
        self::assertSame('demande_fonds', NoelVocabulaire::comprendre("c'est quoi un bon de caisse ?")['concept']);

        $routes = (string) file_get_contents(BASE_PATH . '/routes/finance.php');
        self::assertStringContainsString('bon-caisse-pdf', $routes, 'La route justifie ce rattachement.');
    }

    /**
     * Le banc d'essai : des questions réelles, et le concept attendu.
     *
     * 17 sur 22 avant la reprise du 03/11/2026, 21 après. Ce test tient ce
     * chiffre : une retouche du classement qui ferait retomber NOEL sous ce
     * seuil se verra tout de suite.
     */
    public function test_le_banc_d_essai_des_questions_reelles(): void
    {
        $banc = [
            ["c'est quoi un appro de caisse ?", 'appro'],
            ['comment valider un appro de caisse', 'appro'],
            ["c'est quoi les mouvements de caisse ?", 'mouvements_caisse'],
            ['ou est le journal de caisse', 'mouvements_caisse'],
            ["c'est quoi un point de caisse ?", 'point_caisse'],
            ['je dois soumettre mon point', 'point_caisse'],
            ['la cloture de caisse du soir', 'point_caisse'],
            ["c'est quoi une demande de fonds ?", 'demande_fonds'],
            ['ou je decaisse une demande', 'decaissement'],
            ['une depense de prestataire', 'depense'],
            ['ou je saisis un colis', 'colis'],
            ["c'est quoi le groupage", 'groupage'],
            ['je cherche ou est mon colis', 'suivi_colis'],
            ["c'est quoi le pointage", 'pointage'],
            ['mon bulletin de paie', 'paie'],
            ['je veux poser des conges', 'conge'],
            ['le dossier du personnel', 'dossier_personnel'],
            ['la facture du client', 'facture'],
            ["c'est quoi la comptabilite", 'comptabilite'],
            ['mes entrepots et mon stock', 'entrepot'],
        ];

        $rates = [];

        foreach ($banc as [$question, $attendu]) {
            $obtenu = NoelVocabulaire::comprendre($question)['concept'];

            if ($obtenu !== $attendu) {
                $rates[] = $question . ' : attendu ' . $attendu . ', obtenu ' . ($obtenu ?? 'rien');
            }
        }

        self::assertSame([], $rates, count($rates) . ' question(s) mal comprises.');
    }

    public function test_chaque_procedure_est_complete(): void
    {
        foreach (NoelProcedure::gestesConnus() as $geste) {
            $procedure = NoelProcedure::pour($geste);

            self::assertNotNull($procedure, $geste);
            self::assertNotSame('', trim($procedure['titre']), $geste);
            self::assertGreaterThanOrEqual(4, count($procedure['etapes']), $geste . ' : trop peu d\'étapes.');
            self::assertNotEmpty($procedure['ecrans'], $geste . ' : aucun écran rattaché.');
        }
    }

    public function test_les_procedures_portent_les_pieges_appris_cette_semaine(): void
    {
        // Ce que NOEL explique doit porter ce qu'on a corrigé, sinon le guide
        // répète les erreurs que le code ne fait plus.
        self::assertStringContainsString(
            'Annuler ce règlement',
            (string) NoelProcedure::pour('facture')['attention']
        );
        self::assertStringContainsString(
            "reintegrez l'ancien",
            (string) NoelProcedure::pour('dossier_personnel')['attention']
        );
        self::assertStringContainsString(
            "personne ne decaisse ce qu'il a autorise",
            (string) NoelProcedure::pour('decaissement')['attention']
        );
    }
}
