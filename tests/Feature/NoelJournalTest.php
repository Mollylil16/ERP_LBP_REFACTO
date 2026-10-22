<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Noel\NoelJournal;
use Tests\TestCase;

/**
 * Ce que la personne vient de faire — pour répondre à la cause, pas à la règle.
 *
 * Sans ce service, NOEL récite : « le décaissement se fait sur la fiche,
 * panneau du bas ». Avec lui, il comprend : « vous avez validé cette demande
 * ce matin à 9 h 12, c'est pour cela qu'elle vous est refusée ».
 */
final class NoelJournalTest extends TestCase
{
    private function source(): string
    {
        return (string) file_get_contents(BASE_PATH . '/app/Services/Noel/NoelJournal.php');
    }

    public function test_noel_ne_lit_que_le_journal_de_la_personne_qui_lui_parle(): void
    {
        /*
         * Le journal dit qui a supprimé quoi dans toute l'entreprise. En faire
         * une fenêtre ouverte à chacun serait pire que tout ce qu'on a fermé
         * cette semaine.
         */
        $source = $this->source();

        self::assertStringContainsString('WHERE user_id = :moi', $source);
        self::assertStringContainsString('$moi = (int) Auth::id();', $source);
        self::assertStringContainsString('if ($moi <= 0)', $source);
    }

    public function test_les_actions_traduites_sont_celles_qui_existent_vraiment(): void
    {
        /*
         * Relevé en production le 22/10/2026. Traduire des actions imaginées
         * aurait donné un dictionnaire qui ne sert jamais, et laissé sans
         * traduction celles qu'on rencontre tous les jours.
         */
        $source = $this->source();

        foreach ([
            'create_parcel', 'payment', 'withdraw_parcel', 'auto_facturer_colis',
            'submit_cash_report', 'cancel_payment', 'delete_invoice',
        ] as $action) {
            self::assertStringContainsString("'" . $action . "' =>", $source, $action . ' manque.');
        }
    }

    public function test_une_action_inconnue_n_est_pas_traduite_de_travers(): void
    {
        // Mieux vaut afficher « machin_truc » que d'inventer une phrase fausse.
        self::assertStringContainsString('self::ACTIONS[$action] ?? $action', $this->source());
    }

    public function test_noel_sait_si_vous_avez_valide_la_demande_vous_meme(): void
    {
        /*
         * C'est la question qui explique le refus le plus fréquent depuis le
         * partage du 14/10/2026 : personne ne décaisse ce qu'il a validé. Sans
         * elle, NOEL répondrait « le panneau est en bas de la fiche » à
         * quelqu'un qui le cherche en vain parce qu'il n'y sera jamais.
         */
        $source = $this->source();

        self::assertStringContainsString('aiJeValide', $source);
        self::assertStringContainsString('validateur_id = :moi', $source);
    }

    public function test_noel_sait_si_le_point_est_deja_soumis(): void
    {
        /*
         * « Je n'arrive pas à soumettre mon point » a souvent une réponse très
         * simple : c'est déjà fait. Le dire évite un appel, une inquiétude, et
         * parfois une double saisie.
         */
        $source = $this->source();

        self::assertStringContainsString('pointDeCaisseDuJour', $source);
        self::assertStringContainsString("statut IN ('soumis', 'consolide')", $source);
    }

    public function test_le_doublon_de_reglement_se_voit_le_jour_meme(): void
    {
        /*
         * Le 15/10/2026, deux caissières ont encaissé le même acompte de
         * 100 000 F à 92 secondes d'intervalle. L'erreur a été découverte le
         * lendemain, par un appel. Elle se voyait au premier coup d'œil.
         */
        $source = $this->source();

        self::assertStringContainsString('doublonsDeReglementDuJour', $source);
        self::assertStringContainsString('p2.montant = p1.montant', $source);
        self::assertStringContainsString('BETWEEN 0 AND 300', $source);

        // Et seulement pour son agence : le doublon d'Adjamé ne regarde pas
        // la caissière de Dokui.
        self::assertStringContainsString('p1.agence_id = :agence', $source);
    }

    public function test_le_doublon_se_dit_en_francais_avec_le_geste_a_faire(): void
    {
        $phrase = NoelJournal::phraseDuDoublon([
            'facture' => 'FA-3403-2026-000302',
            'montant' => 100000.0,
            'ecart_secondes' => 92,
            'heure' => '8h59',
        ]);

        self::assertStringContainsString('FA-3403-2026-000302', $phrase);
        self::assertStringContainsString('100 000', $phrase);
        self::assertStringContainsString('2 minutes', $phrase);
        self::assertStringContainsString('8h59', $phrase);

        // Une alerte qui ne dit pas quoi faire laisse la personne inquiète et
        // sans solution.
        self::assertStringContainsString('annulez', $phrase);
        self::assertStringContainsString('avant la cloture', $phrase);
    }

    public function test_un_ecart_court_se_dit_en_secondes(): void
    {
        $phrase = NoelJournal::phraseDuDoublon([
            'facture' => 'FA-1', 'montant' => 5000.0, 'ecart_secondes' => 12, 'heure' => '9h00',
        ]);

        self::assertStringContainsString('12 secondes', $phrase);
    }

    public function test_le_refus_de_decaissement_laisse_une_trace(): void
    {
        /*
         * Le journal ne garde que ce qui a réussi : relevé en production, deux
         * lignes de refus sur 4 160. Sans cette trace, NOEL ne pourrait jamais
         * expliquer à quelqu'un pourquoi son décaissement vient de coincer. Et
         * une tentative de sortie d'argent refusée mérite une trace de toute
         * façon.
         */
        $controleur = (string) file_get_contents(
            BASE_PATH . '/app/Controllers/Finance/DemandesFondsController.php'
        );

        self::assertStringContainsString("'refus_decaissement_deux_mains'", $controleur);

        /*
         * On vise l'action decaisser(), pas la fiche : « aDejaValide » y
         * apparait aussi, pour masquer le panneau. Un ancrage sur la premiere
         * occurrence tombait a cote et faisait passer le test pour une
         * mauvaise raison.
         */
        $debut = (int) strpos($controleur, 'public function decaisser');
        $fin = (int) strpos($controleur, 'public function historique');
        $action = substr($controleur, $debut, $fin - $debut);

        self::assertStringContainsString('AuditLogService::log', $action);
        self::assertStringContainsString("'refus_decaissement_deux_mains'", $action);

        // Et avant l'ecriture en base, sinon on journalise un refus qui n'en
        // est pas un.
        self::assertLessThan(
            (int) strpos($action, 'decaisserDemande'),
            (int) strpos($action, 'refus_decaissement_deux_mains')
        );
    }

    public function test_au_dela_de_trois_quarts_d_heure_ce_n_est_plus_recent(): void
    {
        // « Ce que je viens de faire » a une duree de vie. Remonter la journee
        // entiere ferait repondre NOEL sur un geste du matin a quelqu'un qui
        // parle de maintenant.
        self::assertSame(45, NoelJournal::MINUTES_RECENTES);
    }
}
