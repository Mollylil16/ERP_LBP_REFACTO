<?php

declare(strict_types=1);

namespace App\Services\Noel;

use App\Helpers\View;
use App\Security\NoelAcces;
use Throwable;

/**
 * La bouche de NOEL : ce qu'il repond quand on lui ecrit.
 *
 * Tout le reste etait le cerveau — la carte du logiciel, le dictionnaire, le
 * journal, la veille, le mot du matin. Cette classe les assemble en une
 * reponse, et elle seule decide de ce qui sort.
 *
 * TROIS REGLES QU'ELLE NE TRANSGRESSE JAMAIS.
 *
 * Elle ne nomme que les ecrans que la personne peut ouvrir. Si le concept est
 * compris mais qu'aucun ecran ne lui est accessible, NOEL ne dit pas « c'est
 * dans Finance, mais vous n'y avez pas droit » : il propose de transmettre.
 * Nommer une porte fermee, c'est apprendre a quelqu'un qu'il y a une porte.
 *
 * Elle prefere avouer plutot qu'inventer. Un assistant qui improvise une
 * procedure est pire qu'aucun assistant : on suit son conseil, et c'est faux.
 *
 * Elle ne transmet rien sans accord. Une question incomprise peut etre une
 * demande d'aide comme une confidence.
 */
final class NoelRepondeur
{
    private NoelCarte $carte;
    private ?NoelJournal $journal;
    private ?NoelMesure $mesure;

    public function __construct(
        ?NoelCarte $carte = null,
        ?NoelJournal $journal = null,
        ?NoelMesure $mesure = null
    ) {
        $this->carte = $carte ?? new NoelCarte();
        $this->journal = $journal;
        $this->mesure = $mesure;
    }

    /**
     * La reponse a un message.
     *
     * @return array{texte: string, liens: array<int, array{label: string, url: string}>, attente: ?string}
     */
    public function repondre(string $message): array
    {
        $lu = NoelVocabulaire::comprendre($message);

        $this->mesurer($lu);

        return match ($lu['intention']) {
            NoelVocabulaire::INTENTION_SALUTATION => $this->bonjour(),
            NoelVocabulaire::INTENTION_REMERCIEMENT => $this->deRien(),
            NoelVocabulaire::INTENTION_SIGNALEMENT => $this->signalement($message, $lu),
            NoelVocabulaire::INTENTION_PANNE => $this->panne($message, $lu),
            NoelVocabulaire::INTENTION_PRESENTATION => $this->presenter($lu),
            NoelVocabulaire::INTENTION_PROCEDURE => $this->marcheASuivre($message, $lu),
            NoelVocabulaire::INTENTION_INCONNUE => $this->jeNeSaisPas($message, $lu),
            default => $this->orienter($lu),
        };
    }

    // ------------------------------------------------------------------
    // Les reponses
    // ------------------------------------------------------------------

    /** @return array{texte: string, liens: array<int, array<string, string>>, attente: ?string} */
    private function bonjour(): array
    {
        $mot = NoelMotDuMatin::duJour(null, NoelAcces::prenom());

        $texte = $mot['salutation'] . '. ' . $mot['texte'];

        if ($mot['categorie'] === NoelMotDuMatin::ENIGME) {
            // On ne donne pas la reponse : chercher trente secondes est tout
            // l'interet du matin. Trois essais, puis la solution et le pourquoi.
            return self::reponse($texte, [], 'enigme');
        }

        return self::reponse($texte);
    }

    /** @return array{texte: string, liens: array<int, array<string, string>>, attente: ?string} */
    private function deRien(): array
    {
        return self::reponse("Avec plaisir. Je suis la si ca bloque encore.");
    }

    /**
     * La personne demande qu'on intervienne : acces, correction, proposition.
     *
     * @param array<string, mixed> $lu
     *
     * @return array{texte: string, liens: array<int, array<string, string>>, attente: ?string}
     */
    private function signalement(string $message, array $lu): array
    {
        // « au sujet de les factures » ne se dit pas : les libelles portent
        // deja leur article.
        $sujet = $lu['libelle'] !== null ? ', concernant ' . $lu['libelle'] : '';

        return self::reponse(
            "C'est note" . $sujet . ". Je transmets votre demande a l'administrateur, "
                . "et je vous previens des que c'est regle. Confirmez-moi simplement l'envoi.",
            $this->liens($lu),
            'transmission'
        );
    }

    /**
     * Quelque chose ne marche pas. On cherche la cause avant de citer la regle.
     *
     * @param array<string, mixed> $lu
     *
     * @return array{texte: string, liens: array<int, array<string, string>>, attente: ?string}
     */
    private function panne(string $message, array $lu): array
    {
        $cause = $this->causeConnue($lu);

        if ($cause !== null) {
            return self::reponse($cause, $this->liens($lu));
        }

        $liens = $this->liens($lu);

        if ($liens === []) {
            return $this->jeNeSaisPas($message, $lu);
        }

        return self::reponse(
            "Je ne vois pas de cause evidente de mon cote. L'ecran concerne est ci-dessous. "
                . "Si ca bloque toujours, dites-le-moi : je transmets a l'administrateur.",
            $liens,
            'transmission'
        );
    }

    /**
     * Ce que le journal sait, et qui explique souvent le blocage.
     *
     * C'est ici que NOEL cesse de reciter le manuel. « Le decaissement se fait
     * sur la fiche » ne sert a rien a quelqu'un pour qui le panneau n'existera
     * jamais ; « vous avez valide cette demande ce matin » lui dit tout.
     *
     * @param array<string, mixed> $lu
     */
    private function causeConnue(array $lu): ?string
    {
        $journal = $this->journal();

        if ($journal === null) {
            return null;
        }

        try {
            if ($lu['concept'] === 'point_caisse') {
                $heure = $journal->pointDeCaisseDuJour();

                if ($heure !== null) {
                    return "Votre point de caisse du jour est deja soumis, a " . $heure
                        . ". C'est sans doute pour cela que l'ecran ne vous propose plus de le faire. "
                        . "Si un montant est faux, il faut le rouvrir plutot que le ressaisir.";
                }
            }

            if ($lu['concept'] === 'facture') {
                $doublons = $journal->doublonsDeReglementDuJour();

                if ($doublons !== []) {
                    return NoelJournal::phraseDuDoublon($doublons[0]);
                }
            }

            if ($lu['concept'] === 'decaissement') {
                return "Si l'ecran ne vous propose pas le panneau de decaissement, c'est "
                    . "probablement que vous avez vous-meme valide cette demande : depuis le "
                    . "14 octobre, celui qui autorise la depense n'est pas celui qui sort l'argent. "
                    . "Le decaissement revient alors a quelqu'un d'autre.";
            }
        } catch (Throwable $e) {
            error_log('[NOEL repondeur] cause illisible : ' . $e->getMessage());
        }

        return null;
    }

    /**
     * La personne cherche un ecran.
     *
     * @param array<string, mixed> $lu
     *
     * @return array{texte: string, liens: array<int, array<string, string>>, attente: ?string}
     */
    private function orienter(array $lu): array
    {
        $liens = $this->liens($lu);

        if ($liens === []) {
            /*
             * Le concept est compris, mais aucun ecran n'est ouvert a cette
             * personne. On ne dit pas « c'est dans Finance mais vous n'y avez
             * pas droit » : nommer une porte fermee, c'est apprendre qu'il y a
             * une porte.
             */
            return self::reponse(
                "Je ne trouve pas d'ecran que vous puissiez ouvrir pour cela. "
                    . "Si vous pensez qu'il vous en faut l'acces, dites-le-moi : je transmets.",
                [],
                'transmission'
            );
        }

        $texte = count($liens) === 1
            ? "C'est ici."
            : "Cela se trouve a l'un de ces endroits.";

        return self::reponse($texte, $liens);
    }

    /**
     * Presenter un module : a quoi il sert, et ce qu'on y trouve.
     *
     * « Parle-moi du module finance » n'attend pas un lien mais une
     * explication. NOEL a deja toute la matiere — le catalogue porte la
     * description de chaque module et la liste de ses ecrans, groupe par
     * groupe. Il ne lui manquait que de s'en servir pour raconter.
     *
     * Et il ne raconte que ce que la personne peut ouvrir : presenter en
     * detail un module auquel elle n'a pas acces serait lui decrire une piece
     * fermee a clef.
     *
     * @param array<string, mixed> $lu
     *
     * @return array{texte: string, liens: array<int, array<string, string>>, attente: ?string}
     */
    private function presenter(array $lu): array
    {
        $slug = (string) ($lu['module'] ?? '');
        $modules = $this->carte->modulesVisibles();

        if ($slug === '' || !isset($modules[$slug])) {
            /*
             * Le module existe peut-etre, mais pas pour cette personne. On ne
             * le nomme pas : apprendre a quelqu'un qu'il y a une porte est
             * exactement ce qu'on evite partout ailleurs.
             */
            return self::reponse(
                "Je ne vois pas de module de ce nom parmi ceux que vous pouvez ouvrir. "
                    . "Dites-moi plutot ce que vous cherchez a faire, je vous montrerai ou c'est.",
                [],
                'transmission'
            );
        }

        $module = $modules[$slug];
        $ecrans = $module['ecrans'];

        $texte = $module['label'] . ".";

        if (trim((string) $module['description']) !== '') {
            $texte .= ' ' . rtrim((string) $module['description'], '.') . '.';
        }

        $texte .= "

Vous y avez acces a " . count($ecrans) . ' ecran'
            . (count($ecrans) > 1 ? 's' : '') . self::parGroupes($ecrans) . '.';

        $texte .= "

Dites-moi ce que vous voulez y faire — par exemple « comment je fais pour "
            . self::exempleDeGeste($slug) . " » — et je vous donne les etapes.";

        return self::reponse($texte, self::premiersEcrans($ecrans));
    }

    /**
     * « rangés en 6 groupes : Pilotage, Gestion des fonds, … »
     *
     * @param array<int, array<string, string>> $ecrans
     */
    private static function parGroupes(array $ecrans): string
    {
        $groupes = [];

        foreach ($ecrans as $ecran) {
            $groupe = trim((string) ($ecran['groupe'] ?? ''));

            if ($groupe !== '') {
                $groupes[$groupe] = $groupe;
            }
        }

        if ($groupes === []) {
            return '';
        }

        return ', ranges en ' . count($groupes) . ' groupe' . (count($groupes) > 1 ? 's' : '')
            . ' : ' . implode(', ', array_values($groupes));
    }

    /**
     * Les premiers ecrans, pour entrer quelque part plutot que de lire une liste.
     *
     * @param array<int, array<string, string>> $ecrans
     *
     * @return array<int, array{label: string, url: string}>
     */
    private static function premiersEcrans(array $ecrans): array
    {
        $liens = [];

        foreach (array_slice($ecrans, 0, 4) as $ecran) {
            $liens[] = [
                'label' => (string) $ecran['label'],
                'url' => View::url((string) $ecran['url']),
            ];
        }

        return $liens;
    }

    /**
     * Un exemple de geste propre au module, pour amorcer la question suivante.
     *
     * Terminer une presentation par « dites-moi ce que vous voulez faire »
     * laisse la personne devant une page blanche. Un exemple concret lui donne
     * le ton et le vocabulaire.
     */
    private static function exempleDeGeste(string $slug): string
    {
        return match ($slug) {
            'finance' => 'soumettre mon point de caisse',
            'colisage' => 'enregistrer un colis',
            'logistique' => 'preparer un depart',
            'rh' => 'integrer un collaborateur',
            'crm' => 'creer une fiche client',
            'entrepots' => 'retirer un colis du magasin',
            'transit-douane' => 'suivre un dossier de dedouanement',
            'flotte-transport' => 'suivre un vehicule',
            'employee' => 'consulter mon bulletin',
            default => 'faire une recherche',
        };
    }

    /**
     * La marche a suivre : les gestes dans l'ordre.
     *
     * @param array<string, mixed> $lu
     *
     * @return array{texte: string, liens: array<int, array<string, string>>, attente: ?string}
     */
    private function marcheASuivre(string $message, array $lu): array
    {
        $etapes = NoelProcedure::pour($lu['concept'] ?? null, $lu['module'] ?? null);

        if ($etapes === null) {
            // Mieux vaut orienter que d'inventer une procedure : quelqu'un la
            // suivrait, et elle serait fausse.
            $liens = $this->liens($lu);

            if ($liens !== []) {
                return self::reponse(
                    "Je n'ai pas de marche a suivre ecrite pour cela, mais l'ecran est ici. "
                        . "Si vous voulez que je l'apprenne, dites-le-moi.",
                    $liens,
                    'transmission'
                );
            }

            return $this->jeNeSaisPas($message, $lu);
        }

        /*
         * Une marche a suivre se refuse comme un ecran se refuse.
         *
         * Raconter comment integrer un collaborateur a quelqu'un qui n'a pas
         * acces aux Ressources humaines reviendrait a lui decrire une piece
         * fermee a clef — et a lui apprendre que cette piece existe, ce que le
         * module RH interdit precisement. Les etapes suivent les droits des
         * ecrans qu'elles decrivent.
         */
        $liens = $this->liens(['ecrans' => $etapes['ecrans']]);

        if ($liens === []) {
            return self::reponse(
                "Cette operation se fait dans une partie du logiciel que vous ne pouvez pas ouvrir. "
                    . "Dites-moi ce que vous cherchez a faire et je vous orienterai autrement, "
                    . "ou je transmets votre demande a l'administrateur.",
                [],
                'transmission'
            );
        }

        $texte = $etapes['titre'] . "
";

        foreach ($etapes['etapes'] as $rang => $etape) {
            $texte .= "
" . ($rang + 1) . '. ' . $etape;
        }

        if ($etapes['attention'] !== null) {
            $texte .= "

A retenir : " . $etapes['attention'];
        }

        return self::reponse($texte, $liens);
    }

    /**
     * NOEL n'a pas compris, et il le dit.
     *
     * @param array<string, mixed> $lu
     *
     * @return array{texte: string, liens: array<int, array<string, string>>, attente: ?string}
     */
    private function jeNeSaisPas(string $message, array $lu): array
    {
        if (!NoelAcces::saitConverser()) {
            return self::reponse(
                NoelApprentissage::proposition()
                    . "\n\nJe sais surtout vous dire ou se trouvent les ecrans du logiciel "
                    . "et ce qu'on y saisit. Pour le reste, je ne sais pas encore tenir une vraie conversation.",
                [],
                'transmission'
            );
        }

        return self::reponse(NoelApprentissage::proposition(), [], 'transmission');
    }

    // ------------------------------------------------------------------
    // Briques
    // ------------------------------------------------------------------

    /**
     * Les ecrans proposables, c'est-a-dire ceux que la personne peut ouvrir.
     *
     * @param array<string, mixed> $lu
     *
     * @return array<int, array{label: string, url: string}>
     */
    private function liens(array $lu): array
    {
        $candidats = (array) ($lu['ecrans'] ?? []);
        $visibles = [];

        /*
         * Le PREMIER module qui porte une adresse gagne, pas le dernier.
         *
         * Plusieurs modules citent le meme ecran : « finance/clotures » figure
         * au menu de Finance et a celui de Recherche & Audit. En ecrasant au
         * fur et a mesure, NOEL annoncait « Recherche & Audit > Points de
         * Caisse » a une caissiere qui cherchait simplement sa cloture. Le
         * catalogue est range du plus central au plus specialise : le premier
         * est le bon.
         */
        foreach ($this->carte->modulesVisibles() as $module) {
            foreach ($module['ecrans'] as $ecran) {
                if (!isset($visibles[$ecran['url']])) {
                    $visibles[$ecran['url']] = $module['label'] . ' > ' . $ecran['label'];
                }
            }
        }

        $liens = [];
        foreach ($candidats as $url) {
            $url = ltrim((string) $url, '/');

            if (isset($visibles[$url])) {
                $liens[] = ['label' => $visibles[$url], 'url' => View::url($url)];
            }
        }

        return $liens;
    }

    /**
     * Sommes-nous dans la suite de tests ?
     *
     * Database::getConnection() n'echoue pas quand la base manque : il arrete
     * le processus. Un try/catch ne protege donc de rien, et le repondeur
     * ferait tomber toute la suite des la premiere question posee. Meme parade
     * que AuditLogService et NoelCarte.
     */
    private static function enTest(): bool
    {
        return ($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? '') === 'testing';
    }

    /** @param array<string, mixed> $lu */
    private function mesurer(array $lu): void
    {
        if ($this->mesure === null && self::enTest()) {
            return;
        }

        try {
            ($this->mesure ?? new NoelMesure())->noterQuestion([
                'concept' => $lu['concept'] ?? null,
                'intention' => (string) ($lu['intention'] ?? ''),
            ]);
        } catch (Throwable $e) {
            // Compter est utile ; repondre l'est davantage.
            error_log('[NOEL repondeur] mesure impossible : ' . $e->getMessage());
        }
    }

    private function journal(): ?NoelJournal
    {
        if ($this->journal !== null) {
            return $this->journal;
        }

        if (self::enTest()) {
            return null;
        }

        try {
            return $this->journal = new NoelJournal();
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * @param array<int, array{label: string, url: string}> $liens
     *
     * @return array{texte: string, liens: array<int, array{label: string, url: string}>, attente: ?string}
     */
    private static function reponse(string $texte, array $liens = [], ?string $attente = null): array
    {
        return ['texte' => $texte, 'liens' => $liens, 'attente' => $attente];
    }

    /**
     * La reponse mise en texte, pour un fil de discussion qui ne connait que
     * des messages.
     *
     * @param array{texte: string, liens: array<int, array{label: string, url: string}>} $reponse
     */
    public static function enMessage(array $reponse): string
    {
        $texte = $reponse['texte'];

        foreach ($reponse['liens'] as $lien) {
            $texte .= "\n" . $lien['label'] . ' : ' . $lien['url'];
        }

        return $texte;
    }
}
