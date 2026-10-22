<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Noel\NoelEnigme;
use App\Services\Noel\NoelMotDuMatin;
use Tests\TestCase;

/**
 * L'énigme du matin se joue, elle ne se lit pas.
 *
 * Demande de l'administrateur le 22/10/2026 : on laisse le personnel répondre,
 * on félicite si c'est juste, et au bout de trois essais on donne la solution
 * avec le pourquoi. Une réponse sans explication laisse la personne aussi
 * avancée qu'avant, et un peu vexée en plus.
 */
final class NoelEnigmeTest extends TestCase
{
    /** @return array{reponse: string, pourquoi: string, acceptees: array<int, string>} */
    private function enigme(): array
    {
        return [
            'reponse' => 'Le tiroir-caisse',
            'pourquoi' => "On l'alimente du matin au soir, puis le point de caisse le vide pour la nuit.",
            'acceptees' => ['tiroir caisse', 'la caisse', 'caisse'],
        ];
    }

    public function test_une_bonne_reponse_est_felicitee(): void
    {
        $verdict = NoelEnigme::juger('le tiroir-caisse', $this->enigme(), 0);

        self::assertSame(NoelEnigme::JUSTE, $verdict['verdict']);
        self::assertStringContainsString('Chapeau', $verdict['message']);
        self::assertNotNull($verdict['pourquoi'], 'Même juste, on explique : c\'est ce qui reste.');
    }

    public function test_la_solution_arrive_au_troisieme_essai_avec_le_pourquoi(): void
    {
        $enigme = $this->enigme();
        $essais = 0;
        $verdicts = [];

        foreach (['le camion', 'un trou', 'je sèche'] as $tentative) {
            $verdict = NoelEnigme::juger($tentative, $enigme, $essais);
            $essais = $verdict['essais'];
            $verdicts[] = $verdict;
        }

        self::assertSame(NoelEnigme::ENCORE, $verdicts[0]['verdict']);
        self::assertNull($verdicts[0]['reponse'], 'Au premier essai raté, on ne vend pas la mèche.');

        self::assertSame(NoelEnigme::ENCORE, $verdicts[1]['verdict']);
        self::assertNull($verdicts[1]['reponse']);

        self::assertSame(NoelEnigme::DONNEE, $verdicts[2]['verdict']);
        self::assertStringContainsString('tiroir-caisse', (string) $verdicts[2]['reponse']);
        self::assertStringContainsString('point de caisse', (string) $verdicts[2]['pourquoi']);
        self::assertSame(3, $verdicts[2]['essais']);
    }

    public function test_la_relance_annonce_ce_qui_reste(): void
    {
        // Ne pas dire combien d'essais il reste, c'est laisser la personne
        // jouer sans savoir quand ça s'arrête.
        $premier = NoelEnigme::juger('faux', $this->enigme(), 0);
        $second = NoelEnigme::juger('faux', $this->enigme(), 1);

        self::assertStringContainsString('deux essais', $premier['message']);
        self::assertStringContainsString('dernier essai', $second['message']);
    }

    public function test_on_accepte_la_reponse_ecrite_autrement(): void
    {
        /*
         * On tape « caisse », « LA CAISSE », « tirroir caisse ». Exiger la
         * formulation exacte ferait perdre quelqu'un qui avait trouvé — et
         * c'est la façon la plus sûre de ne plus jamais le faire jouer.
         */
        $enigme = $this->enigme();

        foreach (['Le tiroir-caisse', 'LE TIROIR CAISSE', 'tiroir caisse', 'caisse', 'la caisse', 'les caisses'] as $ecriture) {
            self::assertTrue(
                NoelEnigme::correspond($ecriture, $enigme),
                'Devrait être accepté : ' . $ecriture
            );
        }
    }

    public function test_une_faute_de_frappe_ne_coute_pas_la_victoire(): void
    {
        self::assertTrue(NoelEnigme::correspond('tiroir caise', $this->enigme()));
    }

    public function test_une_mauvaise_reponse_reste_une_mauvaise_reponse(): void
    {
        // La tolérance ne doit pas aller jusqu'à tout accepter, sinon le jeu
        // n'en est plus un.
        foreach (['la banane', 'le camion', '', '   ', '?'] as $faux) {
            self::assertFalse(
                NoelEnigme::correspond($faux, $this->enigme()),
                'Ne devrait pas être accepté : ' . $faux
            );
        }
    }

    public function test_toutes_les_enigmes_de_l_annee_sont_jouables(): void
    {
        /*
         * Chacune doit avoir sa réponse, son explication, et accepter au moins
         * une autre écriture. Une énigme sans « pourquoi » est précisément ce
         * que l'administrateur a demandé d'éviter.
         */
        $debut = new \DateTimeImmutable('2026-01-01 08:00:00', new \DateTimeZone('Africa/Abidjan'));
        $vues = 0;

        for ($i = 0; $i < 365; $i++) {
            $enigme = NoelMotDuMatin::enigmeDuJour($debut->modify('+' . $i . ' day'));

            if ($enigme === null) {
                continue;
            }

            $vues++;
            self::assertNotSame('', trim($enigme['reponse']));
            self::assertNotSame('', trim($enigme['pourquoi']));
            self::assertNotEmpty($enigme['acceptees'], 'Une seule écriture acceptée, c\'est trop peu.');

            // Et surtout : sa propre réponse doit être reconnue. Une faute de
            // frappe dans la liste des variantes rendrait l'énigme imbattable.
            self::assertTrue(
                NoelEnigme::correspond($enigme['reponse'], $enigme),
                'Énigme imbattable : ' . $enigme['reponse']
            );
        }

        self::assertGreaterThan(40, $vues, 'Une énigme par semaine, au moins.');
    }

    public function test_le_score_ne_sort_pas_de_la_conversation(): void
    {
        /*
         * Savoir qui a séché sur l'énigme de mercredi ne regarde personne, et
         * surtout pas la direction. Le compteur vit dans la session.
         */
        $source = (string) file_get_contents(BASE_PATH . '/app/Services/Noel/NoelEnigme.php');

        self::assertStringNotContainsString('INSERT', $source);
        self::assertStringNotContainsString('Database', $source);
        self::assertStringNotContainsString('AuditLog', $source);
    }
}
