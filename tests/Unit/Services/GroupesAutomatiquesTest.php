<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Messagerie\GroupesAutomatiques;
use App\Services\Messagerie\MessagerieException;
use App\Services\Messagerie\MessagerieService;
use DateTimeImmutable;
use Tests\Support\Messagerie\DepotMessagerieMemoire;
use Tests\TestCase;

/**
 * Groupes automatiques de la messagerie (lot 5) : un groupe par agence,
 * « Tout LBP », « Annonces Direction », tenus à jour par la seule
 * synchronisation.
 *
 * Agences : 10 Abobo Dokui, 20 Adjamé Pharmacie Latin, 30 une agence fermée.
 * Personnages : 1 Awa (Dokui), 2 Bakary chef d'agence à Dokui, 3 Clarisse
 * (Adjamé), 4 Diane cheffe d'agence à Adjamé, 5 Fanta du call center
 * (toutes agences), 9 le DG (toutes agences), 8 l'administrateur, 7 un
 * compte désactivé de Dokui, 80 NOEL.
 */
final class GroupesAutomatiquesTest extends TestCase
{
    private const NOEL = 80;

    private DepotMessagerieMemoire $depot;
    private DateTimeImmutable $heure;

    protected function setUp(): void
    {
        parent::setUp();

        $this->depot = new DepotMessagerieMemoire();
        $this->depot->ajouterAgence(10, 'Agence Abobo Dokui');
        $this->depot->ajouterAgence(20, 'Adjamé Pharmacie Latin');
        $this->depot->ajouterAgence(30, 'Agence fermée', false);

        $this->depot->ajouterCompte(1, 'Awa', 10, ['agent_saisie']);
        $this->depot->ajouterCompte(2, 'Bakary', 10, ['caissiere', 'chef_agence']);
        $this->depot->ajouterCompte(3, 'Clarisse', 20, ['agent_saisie']);
        $this->depot->ajouterCompte(4, 'Diane', 20, ['chef_agence']);
        $this->depot->ajouterCompte(5, 'Fanta', null, ['agent_call_center']);
        $this->depot->ajouterCompte(9, 'Directeur Général', null, ['', 'dg']);
        $this->depot->ajouterCompte(8, 'Admin', null, [], true);
        $this->depot->ajouterCompte(7, 'Ancien agent', 10, ['agent_saisie'], false, false);
        $this->depot->ajouterCompte(self::NOEL, 'NOEL', null);

        $this->heure = new DateTimeImmutable('2026-10-22 09:00:00');
    }

    // ------------------------------------------------------------------
    // Création, membres, rôles
    // ------------------------------------------------------------------

    public function test_la_premiere_passe_cree_les_groupes_et_y_range_chacun(): void
    {
        $bilan = $this->synchro()->synchroniserTout();

        self::assertSame(4, $bilan['groupes_crees'], 'Tout LBP, Annonces Direction, Dokui, Adjamé — pas l’agence fermée.');
        self::assertSame(0, $bilan['erreurs']);

        $tout = $this->groupe(GroupesAutomatiques::CLE_TOUT);
        $annonces = $this->groupe(GroupesAutomatiques::CLE_ANNONCES);
        $dokui = $this->groupe('auto:agence:10');
        $adjame = $this->groupe('auto:agence:20');

        self::assertSame('Tout LBP', $tout['nom']);
        self::assertSame('groupe', $tout['type']);
        self::assertSame('Annonces Direction', $annonces['nom']);
        self::assertSame('annonces', $annonces['type']);
        self::assertSame('Agence Abobo Dokui', $dokui['nom']);
        self::assertSame('Agence Adjamé Pharmacie Latin', $adjame['nom'], 'Le préfixe « Agence » est ajouté s’il manque.');
        self::assertSame(10, $dokui['agence_id']);
        foreach ([$tout, $annonces, $dokui, $adjame] as $g) {
            self::assertTrue((bool) $g['automatique']);
        }

        $actifsHumains = [1 => true, 2 => true, 3 => true, 4 => true, 5 => true, 8 => true, 9 => true];
        self::assertEqualsCanonicalizing(array_keys($actifsHumains), array_keys($this->membres($tout['id'])));
        self::assertEqualsCanonicalizing(array_keys($actifsHumains), array_keys($this->membres($annonces['id'])));
        self::assertSame([1 => 'membre', 2 => 'admin'], $this->membres($dokui['id']));
        self::assertSame([3 => 'membre', 4 => 'admin'], $this->membres($adjame['id']));
    }

    public function test_la_direction_administre_les_annonces_et_tout_lbp(): void
    {
        $this->synchro()->synchroniserTout();

        foreach ([GroupesAutomatiques::CLE_ANNONCES, GroupesAutomatiques::CLE_TOUT] as $cle) {
            $membres = $this->membres($this->groupe($cle)['id']);
            self::assertSame('admin', $membres[9], 'Le DG (rôle dg).');
            self::assertSame('admin', $membres[8], 'L’administrateur (users.is_admin).');
            self::assertSame('membre', $membres[2], 'Un chef d’agence n’y est que membre.');
            self::assertSame('membre', $membres[5]);
        }
    }

    public function test_noel_n_entre_jamais_dans_un_groupe_automatique(): void
    {
        $this->synchro()->synchroniserTout();
        self::assertSame([], $this->groupesDe(self::NOEL));

        // Même si quelqu'un l'y avait mis, la passe suivante l'en sort.
        $this->depot->ajouterMembre($this->groupe(GroupesAutomatiques::CLE_TOUT)['id'], self::NOEL, 'membre', '2026-10-22 09:00:00');
        $bilan = $this->synchro()->synchroniserTout();
        self::assertSame(1, $bilan['membres_retires']);
        self::assertSame([], $this->groupesDe(self::NOEL));

        // Et sa propre vérification ne l'y fait pas entrer.
        $this->synchro()->synchroniserCompte(['id' => self::NOEL, 'agence_id' => null, 'actif' => true, 'admin' => false, 'roles' => []]);
        self::assertSame([], $this->groupesDe(self::NOEL));
    }

    public function test_les_comptes_toutes_agences_ne_sont_dans_aucun_groupe_d_agence(): void
    {
        $this->synchro()->synchroniserTout();

        foreach ([5, 8, 9] as $userId) {
            self::assertEqualsCanonicalizing(
                [GroupesAutomatiques::CLE_TOUT, GroupesAutomatiques::CLE_ANNONCES],
                $this->groupesDe($userId)
            );
        }
    }

    public function test_un_compte_d_une_agence_fermee_ou_inconnue_n_a_pas_de_groupe_d_agence(): void
    {
        $this->depot->ajouterCompte(6, 'Eric', 30);
        $this->depot->ajouterCompte(11, 'Gilles', 999);

        $this->synchro()->synchroniserTout();

        self::assertNull($this->groupeOuNull('auto:agence:30'));
        self::assertNull($this->groupeOuNull('auto:agence:999'));
        self::assertEqualsCanonicalizing([GroupesAutomatiques::CLE_TOUT, GroupesAutomatiques::CLE_ANNONCES], $this->groupesDe(6));
        self::assertEqualsCanonicalizing([GroupesAutomatiques::CLE_TOUT, GroupesAutomatiques::CLE_ANNONCES], $this->groupesDe(11));
    }

    // ------------------------------------------------------------------
    // Changements de comptes
    // ------------------------------------------------------------------

    public function test_un_changement_d_agence_fait_sortir_d_un_groupe_et_entrer_dans_l_autre(): void
    {
        $this->synchro()->synchroniserTout();
        $dokui = $this->groupe('auto:agence:10')['id'];
        $adjame = $this->groupe('auto:agence:20')['id'];

        $this->depot->utilisateurs[1]['agence_id'] = 20;
        $bilan = $this->synchro()->synchroniserTout();

        self::assertSame(1, $bilan['membres_ajoutes']);
        self::assertSame(1, $bilan['membres_retires']);
        self::assertArrayNotHasKey(1, $this->membres($dokui));
        self::assertSame('membre', $this->membres($adjame)[1]);
        self::assertNotNull($this->depot->membre($dokui, 1)['quitte_at'], 'L’adhésion passée reste : ses messages aussi.');
    }

    public function test_un_chef_d_agence_mute_perd_l_administration_de_son_ancien_groupe(): void
    {
        $this->synchro()->synchroniserTout();
        $dokui = $this->groupe('auto:agence:10')['id'];
        $adjame = $this->groupe('auto:agence:20')['id'];

        $this->depot->utilisateurs[2]['agence_id'] = 20;
        $this->synchro()->synchroniserTout();

        self::assertSame([1 => 'membre'], $this->membres($dokui));
        self::assertSame('admin', $this->membres($adjame)[2], 'Les deux chefs d’Adjamé l’administrent.');
        self::assertSame('admin', $this->membres($adjame)[4]);
    }

    public function test_un_role_retire_ou_donne_change_le_role_dans_le_groupe(): void
    {
        $this->synchro()->synchroniserTout();
        $dokui = $this->groupe('auto:agence:10')['id'];

        $this->depot->utilisateurs[2]['roles'] = ['caissiere'];
        $this->depot->utilisateurs[1]['roles'] = ['agent_saisie', 'chef_agence'];
        $bilan = $this->synchro()->synchroniserTout();

        self::assertSame(2, $bilan['roles_changes']);
        self::assertSame([1 => 'admin', 2 => 'membre'], $this->membres($dokui));
    }

    public function test_un_compte_desactive_est_retire_et_revient_s_il_est_reactive(): void
    {
        $this->synchro()->synchroniserTout();
        self::assertSame([], $this->groupesDe(7), 'Désactivé avant la première passe : jamais ajouté.');

        $this->depot->utilisateurs[1]['actif'] = false;
        $bilan = $this->synchro()->synchroniserTout();
        self::assertSame(3, $bilan['membres_retires'], 'Dokui, Tout LBP, Annonces Direction.');
        self::assertSame([], $this->groupesDe(1));

        $this->depot->utilisateurs[1]['actif'] = true;
        $this->synchro()->synchroniserTout();
        self::assertEqualsCanonicalizing(['auto:agence:10', GroupesAutomatiques::CLE_TOUT, GroupesAutomatiques::CLE_ANNONCES], $this->groupesDe(1));
    }

    public function test_une_agence_renommee_renomme_son_groupe(): void
    {
        $this->synchro()->synchroniserTout();
        $this->depot->agences[10]['nom'] = 'Abobo Dokui Gare';

        $bilan = $this->synchro()->synchroniserTout();

        self::assertSame(1, $bilan['groupes_renommes']);
        self::assertSame('Agence Abobo Dokui Gare', $this->groupe('auto:agence:10')['nom']);
    }

    public function test_une_agence_fermee_garde_son_groupe_mais_le_vide(): void
    {
        $this->synchro()->synchroniserTout();
        $dokui = $this->groupe('auto:agence:10');

        $this->depot->agences[10]['active'] = false;
        $this->synchro()->synchroniserTout();

        self::assertSame([], $this->membres($dokui['id']));
        self::assertSame('Agence Abobo Dokui', $this->groupe('auto:agence:10')['nom']);
    }

    // ------------------------------------------------------------------
    // Idempotence
    // ------------------------------------------------------------------

    public function test_deux_passes_de_suite_la_seconde_ne_change_rien(): void
    {
        $this->synchro()->synchroniserTout();
        $conversations = $this->depot->conversations;
        $membres = $this->depot->membres;

        $this->heure = $this->heure->modify('+1 hour');
        $bilan = $this->synchro()->synchroniserTout();

        self::assertSame(GroupesAutomatiques::bilanVide(), $bilan);
        self::assertSame($conversations, $this->depot->conversations, 'Pas même un maj_at touché.');
        self::assertSame($membres, $this->depot->membres);
    }

    public function test_la_synchronisation_ne_remet_pas_a_zero_la_sourdine_d_un_membre(): void
    {
        $this->synchro()->synchroniserTout();
        $tout = $this->groupe(GroupesAutomatiques::CLE_TOUT)['id'];
        $this->en(1)->regler($tout, 'sourdine', true);
        $this->en(1)->regler($tout, 'archivee', true);

        $this->depot->utilisateurs[1]['agence_id'] = 20;
        $this->synchro()->synchroniserTout();

        self::assertSame(1, $this->depot->membre($tout, 1)['sourdine']);
        self::assertSame(1, $this->depot->membre($tout, 1)['archivee']);
    }

    public function test_les_groupes_d_un_utilisateur_ne_touchent_pas_ses_groupes_ordinaires(): void
    {
        $groupe = $this->en(1)->creerGroupe('Caisse Dokui', '', [2]);
        $this->depot->utilisateurs[1]['actif'] = false;

        $this->synchro()->synchroniserTout();

        self::assertNull($this->depot->membre($groupe, 1)['quitte_at'], 'Un groupe créé à la main n’est pas l’affaire de la synchronisation.');
    }

    // ------------------------------------------------------------------
    // Vérification légère de l'utilisateur courant
    // ------------------------------------------------------------------

    public function test_la_verification_d_un_compte_le_range_sans_toucher_aux_autres(): void
    {
        $this->synchro()->synchroniserTout();
        $dokui = $this->groupe('auto:agence:10')['id'];
        $adjame = $this->groupe('auto:agence:20')['id'];

        $this->depot->utilisateurs[1]['agence_id'] = 20;
        $this->depot->utilisateurs[3]['agence_id'] = 10;
        $bilan = $this->synchro()->synchroniserCompte($this->compte(1));

        self::assertSame(1, $bilan['membres_ajoutes']);
        self::assertSame(1, $bilan['membres_retires']);
        self::assertArrayHasKey(1, $this->membres($adjame));
        self::assertArrayNotHasKey(1, $this->membres($dokui));
        self::assertArrayHasKey(3, $this->membres($adjame), 'Clarisse attend sa propre vérification ou la nuit.');
    }

    public function test_la_verification_d_un_compte_sans_groupes_lance_une_passe_complete(): void
    {
        $bilan = $this->synchro()->synchroniserCompte($this->compte(3));

        self::assertSame(4, $bilan['groupes_crees']);
        self::assertCount(7, $this->membres($this->groupe(GroupesAutomatiques::CLE_TOUT)['id']), 'Les autres ne restent pas dehors jusqu’à la nuit.');
    }

    public function test_la_verification_legere_ne_refait_rien_tant_que_le_compte_n_a_pas_bouge(): void
    {
        $synchro = $this->synchro();
        [$bilan, $memo] = $synchro->synchroniserCompteSiBesoin($this->compte(1), null);
        self::assertNotNull($bilan, 'Premier appel : vérification.');

        // On efface ses adhésions dans le dos de la synchronisation : sans
        // changement de compte et avant dix minutes, elle ne regarde pas.
        foreach ($this->depot->membres as $cle => $m) {
            if ($m['user_id'] === 1) {
                $this->depot->membres[$cle]['quitte_at'] = '2026-10-22 09:00:00';
            }
        }
        $this->heure = $this->heure->modify('+5 minutes');
        [$bilan, $memo2] = $synchro->synchroniserCompteSiBesoin($this->compte(1), $memo);
        self::assertNull($bilan);
        self::assertSame($memo, $memo2);
        self::assertSame([], $this->groupesDe(1));

        // Passé dix minutes, elle revérifie et le remet en place.
        $this->heure = $this->heure->modify('+6 minutes');
        [$bilan] = $synchro->synchroniserCompteSiBesoin($this->compte(1), $memo2);
        self::assertSame(3, $bilan['membres_ajoutes']);
    }

    public function test_la_verification_legere_repart_des_que_l_agence_ou_les_roles_changent(): void
    {
        $synchro = $this->synchro();
        [, $memo] = $synchro->synchroniserCompteSiBesoin($this->compte(1), null);

        $this->depot->utilisateurs[1]['roles'] = ['chef_agence'];
        [$bilan, $memo] = $synchro->synchroniserCompteSiBesoin($this->compte(1), $memo);
        self::assertSame(1, $bilan['roles_changes']);

        $this->depot->utilisateurs[1]['agence_id'] = 20;
        [$bilan] = $synchro->synchroniserCompteSiBesoin($this->compte(1), $memo);
        self::assertSame(1, $bilan['membres_ajoutes']);
        self::assertSame('admin', $this->membres($this->groupe('auto:agence:20')['id'])[1]);
    }

    public function test_l_empreinte_ignore_l_ordre_des_roles_et_les_roles_vides(): void
    {
        self::assertSame(
            GroupesAutomatiques::empreinte(['id' => 1, 'agence_id' => 10, 'actif' => true, 'admin' => false, 'roles' => ['b', 'a']]),
            GroupesAutomatiques::empreinte(['id' => 1, 'agence_id' => 10, 'actif' => true, 'admin' => false, 'roles' => ['a', '', 'b']])
        );
        self::assertNotSame(
            GroupesAutomatiques::empreinte(['id' => 1, 'agence_id' => 10, 'actif' => true, 'admin' => false, 'roles' => []]),
            GroupesAutomatiques::empreinte(['id' => 1, 'agence_id' => 20, 'actif' => true, 'admin' => false, 'roles' => []])
        );
    }

    // ------------------------------------------------------------------
    // Règles d'un groupe automatique dans la messagerie
    // ------------------------------------------------------------------

    public function test_personne_ne_quitte_ni_ne_gere_un_groupe_automatique(): void
    {
        $this->synchro()->synchroniserTout();
        $dokui = $this->groupe('auto:agence:10')['id'];
        $this->depot->ajouterCompte(12, 'Hervé', 20);

        // Bakary en est administrateur : rien de tout cela ne lui est permis.
        $this->attendreRefus(403, fn() => $this->en(2)->quitter($dokui));
        $this->attendreRefus(403, fn() => $this->en(1)->quitter($dokui));
        $this->attendreRefus(403, fn() => $this->en(2)->ajouterMembres($dokui, [12]));
        $this->attendreRefus(403, fn() => $this->en(2)->retirerMembre($dokui, 1));
        $this->attendreRefus(403, fn() => $this->en(2)->changerRole($dokui, 1, 'admin'));
        $this->attendreRefus(403, fn() => $this->en(2)->renommer($dokui, 'Dokui', null));
        $this->attendreRefus(403, fn() => $this->en(2)->passerEnAnnonces($dokui, true));

        $tout = $this->groupe(GroupesAutomatiques::CLE_TOUT)['id'];
        $this->attendreRefus(403, fn() => $this->en(9, true)->quitter($tout));
        $this->attendreRefus(403, fn() => $this->en(9, true)->renommer($tout, 'Tous', null));

        self::assertSame([1 => 'membre', 2 => 'admin'], $this->membres($dokui));
        self::assertSame('Agence Abobo Dokui', $this->groupe('auto:agence:10')['nom']);
    }

    public function test_le_refus_le_dit_clairement(): void
    {
        $this->synchro()->synchroniserTout();
        $dokui = $this->groupe('auto:agence:10')['id'];

        try {
            $this->en(1)->quitter($dokui);
            self::fail('Le refus était attendu.');
        } catch (MessagerieException $e) {
            self::assertStringContainsString('tenu à jour automatiquement', $e->getMessage());
        }
    }

    public function test_on_y_met_en_sourdine_on_archive_et_on_ecrit(): void
    {
        $this->synchro()->synchroniserTout();
        $tout = $this->groupe(GroupesAutomatiques::CLE_TOUT)['id'];

        $this->en(1)->regler($tout, 'sourdine', true);
        $this->en(1)->regler($tout, 'archivee', true);
        $message = $this->en(1)->envoyer($tout, 'Bonjour à tous');

        self::assertSame(1, $this->depot->membre($tout, 1)['sourdine']);
        self::assertSame('Bonjour à tous', $message['texte'], 'Dans « Tout LBP », chacun écrit.');
    }

    public function test_seuls_les_administrateurs_ecrivent_dans_les_annonces(): void
    {
        $this->synchro()->synchroniserTout();
        $annonces = $this->groupe(GroupesAutomatiques::CLE_ANNONCES)['id'];

        $this->attendreRefus(403, fn() => $this->en(1)->envoyer($annonces, 'Je peux ?'));
        $this->attendreRefus(403, fn() => $this->en(2)->envoyer($annonces, 'Moi chef d’agence ?'));
        self::assertFalse($this->en(1)->detail($annonces)['peut_ecrire']);

        $this->en(9, true)->envoyer($annonces, 'Réunion lundi à 8 h');
        $this->en(8, true)->envoyer($annonces, 'Maintenance ce soir');
        self::assertTrue($this->en(9, true)->detail($annonces)['peut_ecrire']);
    }

    public function test_dans_un_groupe_automatique_seuls_les_administrateurs_epinglent(): void
    {
        $this->synchro()->synchroniserTout();
        $dokui = $this->groupe('auto:agence:10')['id'];
        $message = $this->en(1)->envoyer($dokui, 'Arrivage demain');

        $this->attendreRefus(403, fn() => $this->en(1)->epingler($dokui, $message['id']));
        self::assertFalse($this->en(1)->detail($dokui)['peut_epingler']);

        $this->en(2)->epingler($dokui, $message['id']);
        self::assertTrue($this->en(2)->detail($dokui)['peut_epingler']);
        self::assertCount(1, $this->en(1)->detail($dokui)['epingles']);
        $this->attendreRefus(403, fn() => $this->en(1)->desepingler($dokui, $message['id']));
    }

    public function test_la_fiche_dit_ce_que_l_ecran_peut_proposer(): void
    {
        $this->synchro()->synchroniserTout();
        $dokui = $this->en(2)->detail($this->groupe('auto:agence:10')['id']);

        self::assertTrue($dokui['automatique']);
        self::assertTrue($dokui['peut_administrer'], 'Le chef d’agence en reste administrateur (photo, épingles, suppression).');
        self::assertFalse($dokui['peut_quitter']);
        self::assertFalse($dokui['peut_gerer_membres']);
        self::assertFalse($dokui['peut_renommer']);
        self::assertFalse($dokui['peut_changer_type']);

        // Un groupe ordinaire, lui, garde tout.
        $groupe = $this->en(1)->creerGroupe('Caisse Dokui', '', [2]);
        $ordinaire = $this->en(1)->detail($groupe);
        self::assertFalse($ordinaire['automatique']);
        self::assertTrue($ordinaire['peut_quitter']);
        self::assertTrue($ordinaire['peut_gerer_membres']);
        self::assertTrue($ordinaire['peut_renommer']);
        self::assertTrue($ordinaire['peut_changer_type']);
        self::assertTrue($ordinaire['peut_epingler']);
        self::assertTrue($this->en(2)->detail($groupe)['peut_quitter']);
        self::assertFalse($this->en(2)->detail($groupe)['peut_gerer_membres'], 'Simple membre.');
    }

    public function test_la_liste_des_discussions_signale_les_groupes_automatiques(): void
    {
        $this->synchro()->synchroniserTout();
        $this->en(1)->creerGroupe('Caisse Dokui', '', [2]);

        $parNom = [];
        foreach ($this->en(1)->conversations() as $c) {
            $parNom[$c['nom']] = $c;
        }

        self::assertTrue($parNom['Tout LBP']['automatique']);
        self::assertTrue($parNom['Annonces Direction']['automatique']);
        self::assertSame('annonces', $parNom['Annonces Direction']['type']);
        self::assertSame(10, $parNom['Agence Abobo Dokui']['agence_id']);
        self::assertFalse($parNom['Caisse Dokui']['automatique']);
    }

    public function test_un_nouveau_membre_voit_l_historique_deja_lu(): void
    {
        $this->synchro()->synchroniserTout();
        $dokui = $this->groupe('auto:agence:10')['id'];
        $this->en(1)->envoyer($dokui, 'Consignes du matin');

        $this->depot->utilisateurs[3]['agence_id'] = 10;
        $this->synchro()->synchroniserTout();

        self::assertCount(1, $this->en(3)->messages($dokui)['messages']);
        self::assertArrayNotHasKey($dokui, $this->depot->nonLus(3));
    }

    public function test_l_etat_pour_l_administration(): void
    {
        $this->synchro()->synchroniserTout();

        $etat = $this->synchro()->etat();

        self::assertSame(['Annonces Direction', 'Tout LBP', 'Agence Abobo Dokui', 'Agence Adjamé Pharmacie Latin'], array_column($etat, 'nom'));
        self::assertSame(7, $etat[1]['membres']);
        self::assertSame(2, $etat[1]['admins']);
        self::assertSame(2, $etat[2]['membres']);
        self::assertSame(1, $etat[2]['admins']);
    }

    // ------------------------------------------------------------------

    private function synchro(): GroupesAutomatiques
    {
        return new GroupesAutomatiques($this->depot, [self::NOEL], fn(): DateTimeImmutable => $this->heure);
    }

    private function en(int $userId, bool $direction = false): MessagerieService
    {
        return new MessagerieService($this->depot, $userId, $direction, fn(): DateTimeImmutable => $this->heure);
    }

    /** @return array<string, mixed> */
    private function compte(int $userId): array
    {
        return $this->depot->comptesPourGroupes($userId)[0];
    }

    /** @return array<string, mixed>|null */
    private function groupeOuNull(string $cle): ?array
    {
        foreach ($this->depot->conversations as $c) {
            if ($c['cle_directe'] === $cle) {
                return $c;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function groupe(string $cle): array
    {
        $groupe = $this->groupeOuNull($cle);
        self::assertNotNull($groupe, 'Groupe ' . $cle . ' absent.');

        return $groupe;
    }

    /** @return array<int, string> user_id => rôle, membres actifs, triés */
    private function membres(int $conversationId): array
    {
        $r = [];
        foreach ($this->depot->membresActifs($conversationId) as $m) {
            $r[(int) $m['user_id']] = (string) $m['role'];
        }
        ksort($r);

        return $r;
    }

    /** @return array<int, string> clés des groupes automatiques dont il est membre actif */
    private function groupesDe(int $userId): array
    {
        $r = [];
        foreach ($this->depot->membresAutomatiques($userId) as $m) {
            $r[] = (string) $this->depot->conversations[$m['conversation_id']]['cle_directe'];
        }

        return $r;
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
