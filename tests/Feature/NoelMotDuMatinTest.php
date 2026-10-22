<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Noel\NoelMotDuMatin;
use Tests\TestCase;

/**
 * Le mot que NOEL dit chaque matin.
 *
 * Une première version voulait des messages ancrés dans la journée — « le vol
 * de Paris part jeudi ». L'administrateur l'a écartée le 22/10/2026 : le jour
 * où le vol ne part pas jeudi, NOEL ment. Après lui avoir promis qu'il
 * n'inventerait jamais rien, c'était un mauvais départ.
 *
 * Ces tests gardent la nouvelle règle : rien de ce que dit NOEL le matin ne
 * peut devenir faux avec le temps.
 */
final class NoelMotDuMatinTest extends TestCase
{
    private function jour(string $date): \DateTimeImmutable
    {
        return new \DateTimeImmutable($date . ' 08:00:00', new \DateTimeZone('Africa/Abidjan'));
    }

    public function test_le_ton_suit_le_jour_de_la_semaine(): void
    {
        /*
         * Lundi on encourage, vendredi on rit : ce n'est pas le même matin.
         * Jeudi est le creux de la semaine, celui où l'on tient par habitude.
         */
        $attendus = [
            '2026-10-26' => NoelMotDuMatin::ENCOURAGEMENT, // lundi
            '2026-10-27' => NoelMotDuMatin::PROVERBE,      // mardi
            '2026-10-28' => NoelMotDuMatin::ENIGME,        // mercredi
            '2026-10-29' => NoelMotDuMatin::DOUCEUR,       // jeudi
            '2026-10-30' => NoelMotDuMatin::BLAGUE,        // vendredi
        ];

        foreach ($attendus as $date => $categorie) {
            self::assertSame($categorie, NoelMotDuMatin::duJour($this->jour($date))['categorie'], $date);
        }
    }

    public function test_tout_le_monde_lit_le_meme_mot_le_meme_jour(): void
    {
        /*
         * C'est voulu : que chacun lise la même énigme le mercredi crée un
         * petit quelque chose de commun entre Dokui, Adjamé et Bobigny — on
         * s'en parle à midi. Tirer au hasard pour chacun couperait ce fil.
         */
        $a = NoelMotDuMatin::duJour($this->jour('2026-10-28'), 'Mariam');
        $b = NoelMotDuMatin::duJour($this->jour('2026-10-28'), 'Yvette');

        self::assertSame($a['texte'], $b['texte']);
        self::assertNotSame($a['salutation'], $b['salutation'], 'Chacun doit être nommé par son prénom.');
    }

    public function test_aucun_message_ne_peut_devenir_faux(): void
    {
        /*
         * Le cœur de la correction. Un mois, une année, une heure ou une
         * échéance citée deviendra fausse un jour — et NOEL aura menti sans
         * que personne ne s'en aperçoive.
         */
        $interdits = [
            'janvier', 'février', 'mars', 'avril', 'juin', 'juillet',
            'septembre', 'octobre', 'novembre', 'décembre',
            '2025', '2026', '2027',
            'le vol de', 'part jeudi', 'échéance', 'demain matin', 'cette semaine',
        ];

        foreach ($this->tousLesMessages() as $message) {
            foreach ($interdits as $interdit) {
                self::assertStringNotContainsString(
                    $interdit,
                    mb_strtolower($message),
                    'Ce message vieillira : ' . $message
                );
            }
        }
    }

    public function test_un_message_qui_nomme_un_jour_tombe_bien_ce_jour_la(): void
    {
        /*
         * « Vendredi est le seul jour où même l'imprimante coopère » est vrai
         * le vendredi, et faux les six autres matins. On parcourt une année
         * entière : aucun message ne doit nommer un jour qui n'est pas le sien.
         */
        $jours = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
        $debut = $this->jour('2026-01-01');

        for ($i = 0; $i < 365; $i++) {
            $date = $debut->modify('+' . $i . ' day');
            $texte = mb_strtolower(NoelMotDuMatin::duJour($date)['texte']);
            $sien = $jours[((int) $date->format('N')) - 1];

            foreach ($jours as $jour) {
                if ($jour === $sien) {
                    continue;
                }

                self::assertStringNotContainsString(
                    $jour,
                    $texte,
                    'Le ' . $date->format('D d/m') . ' NOEL parle de ' . $jour . ' : ' . $texte
                );
            }
        }
    }

    public function test_chaque_enigme_a_sa_reponse(): void
    {
        // Une énigme sans réponse est une plaisanterie cruelle. La réponse
        // reste cachée jusqu'au clic : chercher trente secondes est tout
        // l'intérêt du matin.
        $trouvees = 0;
        $debut = $this->jour('2026-01-01');

        for ($i = 0; $i < 365; $i++) {
            $mot = NoelMotDuMatin::duJour($debut->modify('+' . $i . ' day'));

            if ($mot['categorie'] !== NoelMotDuMatin::ENIGME) {
                self::assertNull($mot['reponse'], 'Seule une énigme porte une réponse.');
                continue;
            }

            $trouvees++;
            self::assertNotNull($mot['reponse']);
            self::assertNotSame('', trim((string) $mot['reponse']));
        }

        self::assertGreaterThan(40, $trouvees, 'Une énigme par semaine, au moins.');
    }

    public function test_le_soir_on_dit_bonsoir(): void
    {
        // Se tromper là-dessus s'entend tout de suite à Abidjan.
        $matin = NoelMotDuMatin::duJour(
            new \DateTimeImmutable('2026-10-26 08:00:00', new \DateTimeZone('Africa/Abidjan')),
            'Yvette'
        );
        $soir = NoelMotDuMatin::duJour(
            new \DateTimeImmutable('2026-10-26 18:30:00', new \DateTimeZone('Africa/Abidjan')),
            'Yvette'
        );

        self::assertSame('Bonjour Yvette', $matin['salutation']);
        self::assertSame('Bonsoir Yvette', $soir['salutation']);
    }

    public function test_sans_prenom_noel_salue_quand_meme(): void
    {
        $mot = NoelMotDuMatin::duJour($this->jour('2026-10-26'));

        self::assertSame('Bonjour', $mot['salutation']);
    }

    public function test_la_banque_tient_plusieurs_semaines_sans_se_repeter(): void
    {
        $repartition = NoelMotDuMatin::repartition();

        self::assertGreaterThanOrEqual(60, NoelMotDuMatin::tailleDeLaBanque());

        foreach ($repartition as $categorie => $nombre) {
            // Douze messages par catégorie, c'est douze semaines avant de se
            // répéter sur les catégories vues une fois par semaine.
            self::assertGreaterThanOrEqual(10, $nombre, 'Catégorie trop pauvre : ' . $categorie);
        }
    }

    public function test_aucun_emoji_dans_la_banque(): void
    {
        // Règle du projet. Et un emoji ne se rend pas pareil sur chaque poste.
        foreach ($this->tousLesMessages() as $message) {
            self::assertSame(
                0,
                preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $message),
                'Emoji dans : ' . $message
            );
        }
    }

    /** @return array<int, string> */
    private function tousLesMessages(): array
    {
        $messages = [];
        $debut = $this->jour('2026-01-01');

        for ($i = 0; $i < 365; $i++) {
            $mot = NoelMotDuMatin::duJour($debut->modify('+' . $i . ' day'));
            $messages[$mot['texte']] = $mot['texte'];

            if ($mot['reponse'] !== null) {
                $messages[$mot['reponse']] = $mot['reponse'];
            }
        }

        return array_values($messages);
    }
}
