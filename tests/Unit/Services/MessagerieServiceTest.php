<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Messagerie\MessagerieException;
use App\Services\Messagerie\MessagerieService;
use DateTimeImmutable;
use Tests\Support\Messagerie\DepotMessagerieMemoire;
use Tests\TestCase;

/**
 * Règles de la messagerie interne (cahier des charges validé en octobre
 * 2026), éprouvées sur une doublure en mémoire des tables msg_*.
 *
 * Personnages : 1 Awa, 2 Bakary, 3 Clarisse, 9 le DG (Direction), 7 un
 * compte désactivé.
 */
final class MessagerieServiceTest extends TestCase
{
    private DepotMessagerieMemoire $depot;
    private DateTimeImmutable $heure;

    protected function setUp(): void
    {
        parent::setUp();

        $this->depot = new DepotMessagerieMemoire();
        $this->depot->ajouterUtilisateur(1, 'Awa');
        $this->depot->ajouterUtilisateur(2, 'Bakary');
        $this->depot->ajouterUtilisateur(3, 'Clarisse');
        $this->depot->ajouterUtilisateur(9, 'Directeur Général');
        $this->depot->ajouterUtilisateur(7, 'Ancien agent', false);
        $this->heure = new DateTimeImmutable('2026-10-22 09:00:00');
    }

    // ------------------------------------------------------------------
    // Qui lit
    // ------------------------------------------------------------------

    public function test_un_non_membre_ne_lit_pas_la_discussion(): void
    {
        $groupe = $this->en(1)->creerGroupe('Caisse Dokui', '', [2]);
        $this->en(1)->envoyer($groupe, 'Point de caisse à 17 h');

        $this->attendreRefus(404, fn() => $this->en(3)->messages($groupe));
        $this->attendreRefus(404, fn() => $this->en(3)->detail($groupe));
        $this->attendreRefus(404, fn() => $this->en(3)->rechercher('caisse', $groupe));
        self::assertSame([], $this->depot->accesDirection);
    }

    public function test_la_vue_direction_est_refusee_aux_autres(): void
    {
        $this->attendreRefus(404, fn() => $this->en(1)->vueDirection());
    }

    public function test_la_vue_direction_liste_tout_sans_rien_inscrire(): void
    {
        $groupe = $this->en(1)->creerGroupe('Caisse Dokui', '', [2]);
        $this->en(1)->envoyer($groupe, 'Point de caisse à 17 h');
        $this->en(2)->ouvrirDirecte(3);

        $vue = $this->en(9, true)->vueDirection();

        self::assertCount(2, $vue['conversations']);
        self::assertSame([], $this->depot->accesDirection, 'Lister n\'est pas ouvrir.');

        $groupes = $this->en(9, true)->vueDirection('', 'groupe')['conversations'];
        self::assertCount(1, $groupes);
        self::assertSame('Awa, Bakary', $groupes[0]['participants']);
        self::assertSame(1, $groupes[0]['nb_messages']);

        self::assertCount(1, $this->en(9, true)->vueDirection('Clarisse')['conversations']);
        // Un type inconnu ne filtre rien plutôt que de tout masquer.
        self::assertCount(2, $this->en(9, true)->vueDirection('', 'nimporte')['conversations']);
    }

    public function test_la_vue_direction_montre_les_ouvertures(): void
    {
        $groupe = $this->en(1)->creerGroupe('Caisse Dokui', '', [2]);
        $this->en(9, true)->detail($groupe);

        $acces = $this->en(9, true)->vueDirection()['acces'];

        self::assertCount(1, $acces);
        self::assertSame('Directeur Général', $acces[0]['lecteur']);
        self::assertSame('Caisse Dokui', $acces[0]['nom']);
    }

    public function test_la_direction_lit_toute_discussion_et_chaque_ouverture_est_inscrite(): void
    {
        $groupe = $this->en(1)->creerGroupe('Caisse Dokui', '', [2]);
        $this->en(1)->envoyer($groupe, 'Point de caisse à 17 h');

        $page = $this->en(9, true)->messages($groupe);

        self::assertSame('Point de caisse à 17 h', $page['messages'][0]['texte']);
        self::assertCount(1, $this->depot->accesDirection);
        self::assertSame(['user_id' => 9, 'conversation_id' => $groupe], array_intersect_key(
            $this->depot->accesDirection[0],
            ['user_id' => 1, 'conversation_id' => 1]
        ));

        // Remonter l'historique n'est pas une nouvelle ouverture.
        $this->en(9, true)->messages($groupe, 999);
        self::assertCount(1, $this->depot->accesDirection);

        $this->en(9, true)->detail($groupe);
        self::assertCount(2, $this->depot->accesDirection);
    }

    public function test_la_direction_lit_sans_pouvoir_ecrire_ou_elle_n_est_pas_membre(): void
    {
        $groupe = $this->en(1)->creerGroupe('Caisse Dokui', '', [2]);

        $this->attendreRefus(404, fn() => $this->en(9, true)->envoyer($groupe, 'Bonjour'));
        self::assertFalse($this->en(9, true)->detail($groupe)['peut_ecrire']);
    }

    public function test_un_membre_qui_a_quitte_ne_lit_plus(): void
    {
        $groupe = $this->en(1)->creerGroupe('Export', '', [2]);
        $this->en(2)->quitter($groupe);

        $this->attendreRefus(404, fn() => $this->en(2)->messages($groupe));
    }

    public function test_les_messages_d_un_compte_desactive_restent_lisibles(): void
    {
        $groupe = $this->en(1)->creerGroupe('Export', '', [2, 7]);
        // Le compte 7 était actif quand il a écrit ; on rejoue son message.
        $this->depot->ajouterMembre($groupe, 7, 'membre', '2026-10-22 08:00:00');
        $this->depot->ajouterMessage([
            'conversation_id' => $groupe, 'auteur_id' => 7, 'texte' => 'Colis remis au transitaire',
            'reponse_a_id' => null, 'transfere_de_id' => null, 'created_at' => '2026-10-22 08:30:00',
        ]);

        $messages = $this->en(2)->messages($groupe)['messages'];

        self::assertSame('Colis remis au transitaire', $messages[0]['texte']);
        self::assertSame('Ancien agent', $messages[0]['auteur']['nom']);
    }

    public function test_un_compte_desactive_n_est_ni_propose_ni_ajoute(): void
    {
        $groupe = $this->en(1)->creerGroupe('Export', '', [2, 7]);

        self::assertNull($this->depot->membre($groupe, 7));
        self::assertNotContains(7, array_column($this->en(1)->annuaire(), 'id'));
        self::assertNotContains(1, array_column($this->en(1)->annuaire(), 'id'), 'On ne se propose pas soi-même.');
        $this->attendreRefus(422, fn() => $this->en(1)->ouvrirDirecte(7));
    }

    // ------------------------------------------------------------------
    // Discussion directe
    // ------------------------------------------------------------------

    public function test_une_seule_discussion_directe_par_paire(): void
    {
        $premiere = $this->en(1)->ouvrirDirecte(2);
        $retour = $this->en(1)->ouvrirDirecte(2);
        $inverse = $this->en(2)->ouvrirDirecte(1);

        self::assertSame($premiere, $retour);
        self::assertSame($premiere, $inverse);
        self::assertCount(1, array_filter($this->depot->conversations, static fn(array $c): bool => $c['type'] === 'direct'));
        self::assertSame('Bakary', $this->en(1)->detail($premiere)['nom']);
    }

    public function test_on_n_ouvre_pas_de_discussion_avec_soi_meme(): void
    {
        $this->attendreRefus(422, fn() => $this->en(1)->ouvrirDirecte(1));
    }

    // ------------------------------------------------------------------
    // Messages
    // ------------------------------------------------------------------

    public function test_un_message_vide_ou_trop_long_est_refuse(): void
    {
        $direct = $this->en(1)->ouvrirDirecte(2);

        $this->attendreRefus(422, fn() => $this->en(1)->envoyer($direct, "   \n "));
        $this->attendreRefus(422, fn() => $this->en(1)->envoyer($direct, str_repeat('é', 4001)));
        self::assertSame(4000, mb_strlen($this->en(1)->envoyer($direct, str_repeat('é', 4000))['texte']));
    }

    public function test_on_ne_repond_qu_a_un_message_de_la_meme_discussion(): void
    {
        $a = $this->en(1)->ouvrirDirecte(2);
        $b = $this->en(1)->ouvrirDirecte(3);
        $ailleurs = $this->en(1)->envoyer($b, 'Ailleurs');
        $ici = $this->en(2)->envoyer($a, 'Question');

        $this->attendreRefus(422, fn() => $this->en(1)->envoyer($a, 'Réponse', $ailleurs['id']));

        $reponse = $this->en(1)->envoyer($a, 'Réponse', $ici['id']);
        self::assertSame('Question', $reponse['reponse_a']['apercu']);
    }

    public function test_le_transfert_porte_la_mention_et_exige_d_etre_membre_de_la_cible(): void
    {
        $source = $this->en(1)->ouvrirDirecte(2);
        $cible = $this->en(1)->ouvrirDirecte(3);
        $etrangere = $this->en(2)->ouvrirDirecte(3);
        $message = $this->en(2)->envoyer($source, 'Le conteneur part jeudi');

        $transfere = $this->en(1)->transferer($message['id'], $cible);

        self::assertTrue($transfere['transfere']);
        self::assertSame('Le conteneur part jeudi', $transfere['texte']);
        self::assertSame($cible, $transfere['conversation_id']);
        $this->attendreRefus(404, fn() => $this->en(1)->transferer($message['id'], $etrangere));
    }

    public function test_l_auteur_modifie_pendant_quinze_minutes_et_l_ancien_texte_est_journalise(): void
    {
        $direct = $this->en(1)->ouvrirDirecte(2);
        $message = $this->en(1)->envoyer($direct, 'Rendez-vous à 10 h');

        $this->attendreRefus(403, fn() => $this->en(2)->modifier($message['id'], 'Pirate'));

        $this->heure = $this->heure->modify('+14 minutes');
        $modifie = $this->en(1)->modifier($message['id'], 'Rendez-vous à 11 h');

        self::assertSame('Rendez-vous à 11 h', $modifie['texte']);
        self::assertTrue($modifie['modifie']);
        self::assertSame('Rendez-vous à 10 h', $this->depot->journal[0]['ancien_texte']);
        self::assertSame('modification', $this->depot->journal[0]['action']);

        $this->heure = $this->heure->modify('+2 minutes');
        self::assertFalse($this->en(1)->messages($direct)['messages'][0]['modifiable']);
        $this->attendreRefus(403, fn() => $this->en(1)->modifier($message['id'], 'Trop tard'));
    }

    public function test_un_message_supprime_est_masque_sauf_pour_la_direction(): void
    {
        $groupe = $this->en(1)->creerGroupe('Export', '', [2, 9]);
        $message = $this->en(1)->envoyer($groupe, 'Code du coffre : 4521');

        $this->en(1)->supprimer($message['id']);

        $vuParBakary = $this->en(2)->messages($groupe)['messages'][0];
        self::assertTrue($vuParBakary['supprime']);
        self::assertNull($vuParBakary['texte']);
        self::assertSame('Message supprimé', $vuParBakary['libelle']);
        self::assertSame('Message supprimé', $this->en(2)->conversations()[0]['dernier_message']['apercu']);

        $vuParLeDg = $this->en(9, true)->messages($groupe)['messages'][0];
        self::assertSame('Code du coffre : 4521', $vuParLeDg['texte']);

        // Le texte reste en base et la suppression est journalisée.
        self::assertSame('Code du coffre : 4521', $this->depot->messages[$message['id']]['texte']);
        self::assertSame('suppression', $this->depot->journal[0]['action']);
        $this->attendreRefus(422, fn() => $this->en(1)->supprimer($message['id']));
    }

    public function test_un_admin_du_groupe_supprime_le_message_d_un_autre_mais_pas_un_simple_membre(): void
    {
        $groupe = $this->en(1)->creerGroupe('Export', '', [2, 3]);
        $message = $this->en(2)->envoyer($groupe, 'Hors sujet');

        $this->attendreRefus(403, fn() => $this->en(3)->supprimer($message['id']));
        self::assertTrue($this->en(1)->supprimer($message['id'])['supprime']);
        self::assertSame(1, $this->depot->journal[0]['auteur_id']);
    }

    public function test_dans_une_directe_seul_l_auteur_supprime(): void
    {
        $direct = $this->en(1)->ouvrirDirecte(2);
        $message = $this->en(1)->envoyer($direct, 'Bonjour');

        $this->attendreRefus(403, fn() => $this->en(2)->supprimer($message['id']));
    }

    // ------------------------------------------------------------------
    // Groupes et annonces
    // ------------------------------------------------------------------

    public function test_tout_utilisateur_cree_un_groupe_et_en_devient_admin(): void
    {
        $groupe = $this->en(3)->creerGroupe('  Agence   Yopougon ', 'Équipe du guichet', [1, 2]);

        $detail = $this->en(3)->detail($groupe);
        self::assertSame('Agence Yopougon', $detail['nom']);
        self::assertSame('admin', $detail['mon_role']);
        self::assertTrue($detail['peut_administrer']);
        self::assertCount(3, $detail['membres']);
        self::assertSame('membre', $this->en(1)->detail($groupe)['mon_role']);
        $this->attendreRefus(422, fn() => $this->en(3)->creerGroupe('   ', '', []));
    }

    public function test_seuls_les_admins_gerent_le_groupe(): void
    {
        $groupe = $this->en(1)->creerGroupe('Export', '', [2]);

        $this->attendreRefus(403, fn() => $this->en(2)->ajouterMembres($groupe, [3]));
        $this->attendreRefus(403, fn() => $this->en(2)->renommer($groupe, 'Pirates', null));
        $this->attendreRefus(403, fn() => $this->en(2)->passerEnAnnonces($groupe, true));

        self::assertSame([3], $this->en(1)->ajouterMembres($groupe, [3, 2, 7]));
        $this->en(1)->changerRole($groupe, 2, 'admin');
        $this->en(2)->renommer($groupe, 'Export aérien', 'Départs AF');
        self::assertSame('Export aérien', $this->en(3)->detail($groupe)['nom']);

        $this->en(2)->retirerMembre($groupe, 3);
        $this->attendreRefus(404, fn() => $this->en(3)->messages($groupe));
    }

    public function test_un_groupe_garde_toujours_un_administrateur(): void
    {
        $groupe = $this->en(1)->creerGroupe('Export', '', [2, 3]);

        $this->attendreRefus(422, fn() => $this->en(1)->changerRole($groupe, 1, 'membre'));

        // Le dernier admin qui part passe la main au plus ancien membre.
        $this->heure = $this->heure->modify('+1 minute');
        $this->en(1)->quitter($groupe);
        $admins = array_filter($this->depot->membresActifs($groupe), static fn(array $m): bool => $m['role'] === 'admin');
        self::assertCount(1, $admins);
    }

    public function test_le_dernier_admin_qui_part_laisse_la_main_au_plus_ancien_membre(): void
    {
        $groupe = $this->en(1)->creerGroupe('Export', '', [2]);
        $this->heure = $this->heure->modify('+1 hour');
        $this->en(1)->ajouterMembres($groupe, [3]);

        $this->heure = $this->heure->modify('+1 minute');
        $this->en(1)->quitter($groupe);

        $roles = array_column($this->en(2)->detail($groupe)['membres'], 'role', 'id');
        self::assertSame(['admin', 'membre'], [$roles[2], $roles[3]]);
    }

    public function test_un_nouveau_membre_voit_tout_l_historique_sans_non_lus(): void
    {
        $groupe = $this->en(1)->creerGroupe('Export', '', [2]);
        $this->en(1)->envoyer($groupe, 'Avant l arrivée de Clarisse');
        $this->en(1)->ajouterMembres($groupe, [3]);

        $page = $this->en(3)->messages($groupe);
        self::assertSame('Avant l arrivée de Clarisse', $page['messages'][0]['texte']);
        self::assertSame(0, $this->en(3)->conversations()[0]['non_lus']);
    }

    public function test_dans_une_discussion_d_annonces_seuls_les_admins_ecrivent(): void
    {
        $groupe = $this->en(1)->creerGroupe('Annonces LBP', '', [2, 3]);
        $this->en(1)->passerEnAnnonces($groupe, true);

        self::assertSame('annonces', $this->en(2)->detail($groupe)['type']);
        self::assertFalse($this->en(2)->detail($groupe)['peut_ecrire']);
        $this->attendreRefus(403, fn() => $this->en(2)->envoyer($groupe, 'Je peux parler ?'));
        self::assertSame('Fermeture à 15 h', $this->en(1)->envoyer($groupe, 'Fermeture à 15 h')['texte']);

        $this->en(1)->passerEnAnnonces($groupe, false);
        self::assertSame('Merci', $this->en(2)->envoyer($groupe, 'Merci')['texte']);
    }

    public function test_une_directe_n_a_pas_d_administration(): void
    {
        $direct = $this->en(1)->ouvrirDirecte(2);

        $this->attendreRefus(403, fn() => $this->en(1)->passerEnAnnonces($direct, true));
        $this->attendreRefus(422, fn() => $this->en(1)->quitter($direct));
    }

    // ------------------------------------------------------------------
    // Anti-abus
    // ------------------------------------------------------------------

    public function test_trente_messages_par_minute_au_plus(): void
    {
        $direct = $this->en(1)->ouvrirDirecte(2);
        for ($i = 1; $i <= 30; $i++) {
            $this->en(1)->envoyer($direct, 'Message ' . $i);
        }

        $this->attendreRefus(429, fn() => $this->en(1)->envoyer($direct, 'Message 31'));

        // Le transfert compte aussi.
        $autre = $this->en(1)->ouvrirDirecte(3);
        $this->attendreRefus(429, fn() => $this->en(1)->transferer(array_key_last($this->depot->messages), $autre));

        $this->heure = $this->heure->modify('+61 seconds');
        self::assertSame('Message 31', $this->en(1)->envoyer($direct, 'Message 31')['texte']);
    }

    // ------------------------------------------------------------------
    // Lecture, épingles, relevé
    // ------------------------------------------------------------------

    public function test_lu_par_dans_un_groupe(): void
    {
        $groupe = $this->en(1)->creerGroupe('Export', '', [2, 3]);
        $message = $this->en(1)->envoyer($groupe, 'Départ confirmé');

        self::assertSame(1, $this->en(2)->conversations()[0]['non_lus']);
        $this->en(2)->marquerLu($groupe, $message['id']);

        $lecture = $this->en(1)->luPar($message['id']);
        self::assertSame(['Bakary'], array_column($lecture['lu_par'], 'nom'));
        self::assertSame(['Clarisse'], array_column($lecture['non_lu_par'], 'nom'));
        self::assertSame(0, $this->en(2)->conversations()[0]['non_lus']);
        $this->attendreRefus(404, fn() => $this->en(7)->luPar($message['id']));
    }

    public function test_la_fiche_dit_jusqu_ou_chaque_membre_a_lu(): void
    {
        $direct = $this->en(1)->ouvrirDirecte(2);
        $premier = $this->en(1)->envoyer($direct, 'Le camion part à 14 h');
        $this->en(1)->envoyer($direct, 'Tu confirmes ?');
        $this->en(2)->marquerLu($direct, $premier['id']);

        $lu = array_column($this->en(1)->detail($direct)['membres'], 'dernier_lu_id', 'id');
        self::assertSame($premier['id'], $lu[2]);
    }

    public function test_marquer_lu_ne_recule_pas_et_ne_depasse_pas_le_dernier_message(): void
    {
        $direct = $this->en(1)->ouvrirDirecte(2);
        $m = $this->en(1)->envoyer($direct, 'Un');

        $this->en(2)->marquerLu($direct, 999999);
        self::assertSame($m['id'], $this->depot->membre($direct, 2)['dernier_message_lu_id']);

        $this->en(2)->marquerLu($direct, 1);
        self::assertSame($m['id'], $this->depot->membre($direct, 2)['dernier_message_lu_id']);
    }

    public function test_trois_epingles_au_plus(): void
    {
        $groupe = $this->en(1)->creerGroupe('Export', '', [2]);
        $ids = [];
        for ($i = 1; $i <= 4; $i++) {
            $ids[] = $this->en(1)->envoyer($groupe, 'Consigne ' . $i)['id'];
        }

        foreach (array_slice($ids, 0, 3) as $id) {
            $this->en(2)->epingler($groupe, $id);
        }
        $this->en(2)->epingler($groupe, $ids[0]); // déjà épinglé : sans effet

        $this->attendreRefus(422, fn() => $this->en(2)->epingler($groupe, $ids[3]));
        $this->en(2)->desepingler($groupe, $ids[0]);
        $this->en(2)->epingler($groupe, $ids[3]);
        self::assertCount(3, $this->en(1)->detail($groupe)['epingles']);
    }

    public function test_le_releve_rend_les_nouveautes_et_touche_la_presence(): void
    {
        $direct = $this->en(1)->ouvrirDirecte(2);
        $this->en(1)->envoyer($direct, 'Avant');

        $premier = $this->en(2)->nouveautes(null, null);
        self::assertSame([], $premier['messages'], 'Le premier relevé ne renvoie que le curseur.');
        self::assertSame('2026-10-22 09:00:00', $this->depot->presence[2]['vu_at']);

        $this->heure = $this->heure->modify('+3 seconds');
        $nouveau = $this->en(1)->envoyer($direct, 'Après');
        $this->heure = $this->heure->modify('+3 seconds');
        $releve = $this->en(2)->nouveautes($premier['curseur'], $premier['serveur_at']);

        self::assertSame(['Après'], array_column($releve['messages'], 'texte'));
        self::assertSame($nouveau['id'], $releve['curseur']);
        self::assertSame(2, $releve['non_lus_total']);
        self::assertSame([['user_id' => 1, 'en_ligne' => false, 'vu_at' => null]], $releve['presence']);

        // Awa relève à son tour : elle apparaît en ligne pour Bakary.
        $this->en(1)->nouveautes(null, null);
        $presence = $this->en(2)->nouveautes($releve['curseur'], $releve['serveur_at'])['presence'];
        self::assertTrue($presence[0]['en_ligne']);
    }

    public function test_un_curseur_a_zero_n_est_pas_un_premier_releve(): void
    {
        // Constaté sur MySQL : sans aucun message, le premier relevé rend
        // le curseur 0 ; le relevé suivant doit rendre le premier message.
        $direct = $this->en(1)->ouvrirDirecte(2);
        $premier = $this->en(2)->nouveautes(null, null);
        self::assertSame(0, $premier['curseur']);

        $this->en(1)->envoyer($direct, 'Tout premier');
        $suite = $this->en(2)->nouveautes($premier['curseur'], $premier['serveur_at']);

        self::assertSame(['Tout premier'], array_column($suite['messages'], 'texte'));
    }

    public function test_le_releve_signale_les_lectures_et_les_suppressions(): void
    {
        $direct = $this->en(1)->ouvrirDirecte(2);
        $message = $this->en(1)->envoyer($direct, 'Secret');
        $releve = $this->en(1)->nouveautes(null, null);

        $this->heure = $this->heure->modify('+3 seconds');
        $this->en(2)->marquerLu($direct, $message['id']);
        $this->en(1)->supprimer($message['id']);

        $suite = $this->en(1)->nouveautes($releve['curseur'], $releve['serveur_at']);
        self::assertSame([['conversation_id' => $direct, 'user_id' => 2, 'dernier_message_lu_id' => $message['id']]], $suite['lectures']);
        self::assertTrue($suite['messages_modifies'][0]['supprime']);
        self::assertNull($suite['messages_modifies'][0]['texte']);
    }

    public function test_une_presence_masquee_ne_se_voit_pas(): void
    {
        $this->en(1)->ouvrirDirecte(2);
        $this->en(1)->enregistrerPreferences(['presence_masquee' => '1']);
        $this->en(1)->nouveautes(null, null);

        $presence = $this->en(2)->nouveautes(null, null)['presence'];
        self::assertFalse($presence[0]['en_ligne']);
        self::assertNull($presence[0]['vu_at']);
        self::assertTrue($this->en(1)->preferences()['presence_masquee']);
    }

    // ------------------------------------------------------------------
    // Recherche
    // ------------------------------------------------------------------

    public function test_la_recherche_ne_couvre_que_les_discussions_du_membre(): void
    {
        $mienne = $this->en(1)->ouvrirDirecte(2);
        $autre = $this->en(2)->ouvrirDirecte(3);
        $this->en(1)->envoyer($mienne, 'Le conteneur MSCU arrive');
        $this->en(2)->envoyer($autre, 'Le conteneur TGHU est bloqué');

        $resultats = $this->en(1)->rechercher('conteneur');

        self::assertSame(['Le conteneur MSCU arrive'], array_column($resultats, 'texte'));
        $this->attendreRefus(422, fn() => $this->en(1)->rechercher(' a '));
    }

    public function test_le_plein_texte_n_est_tente_qu_avec_des_mots_de_trois_lettres(): void
    {
        self::assertTrue(MessagerieService::pleinTexteApplicable(MessagerieService::motsRecherche('conteneur bloqué')));
        self::assertFalse(MessagerieService::pleinTexteApplicable(MessagerieService::motsRecherche('AF 57')));
        self::assertSame(['MSCU', 'arrive'], MessagerieService::motsRecherche('+MSCU -arrive*'), 'Les opérateurs saisis sont neutralisés.');
    }

    // ------------------------------------------------------------------

    private function en(int $userId, bool $direction = false): MessagerieService
    {
        return new MessagerieService($this->depot, $userId, $direction, fn(): DateTimeImmutable => $this->heure);
    }

    private function attendreRefus(int $code, callable $action): void
    {
        try {
            $action();
        } catch (MessagerieException $e) {
            self::assertSame($code, $e->getCode(), $e->getMessage());

            return;
        }

        self::fail('Un refus ' . $code . ' était attendu.');
    }
}
