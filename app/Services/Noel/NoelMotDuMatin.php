<?php

declare(strict_types=1);

namespace App\Services\Noel;

/**
 * Le mot que NOEL dit chaque matin.
 *
 * Une premiere idee voulait des messages ancres dans la journee — « le vol de
 * Paris part jeudi, on a le temps ». L'administrateur l'a ecartee le
 * 22/10/2026, et il avait raison : le jour ou le vol ne part pas jeudi, NOEL
 * ment. Apres avoir promis qu'il n'inventerait jamais rien, c'etait un mauvais
 * depart.
 *
 * Alors ce ne sont ni des nouvelles ni des rappels, mais des choses vraies
 * quel que soit le jour : une blague, une enigme, un proverbe, une parole
 * douce. Rien a verifier, rien qui vieillisse.
 *
 * Tout est ecrit a la main et range ici. Aucune intelligence artificielle, donc
 * aucun cout, aucune latence, et surtout aucun risque qu'un matin NOEL sorte
 * une phrase que personne n'a relue.
 */
final class NoelMotDuMatin
{
    public const ENCOURAGEMENT = 'encouragement';
    public const PROVERBE = 'proverbe';
    public const ENIGME = 'enigme';
    public const DOUCEUR = 'douceur';
    public const BLAGUE = 'blague';

    /**
     * Le ton de chaque jour de la semaine.
     *
     * Lundi on encourage, vendredi on rit : ce n'est pas le meme matin. Jeudi
     * est le creux de la semaine, celui ou l'on tient par habitude — d'ou la
     * parole douce. Le dimanche aussi, pour qui travaille ce jour-la.
     *
     * @var array<int, string>
     */
    private const TON_DU_JOUR = [
        1 => self::ENCOURAGEMENT,
        2 => self::PROVERBE,
        3 => self::ENIGME,
        4 => self::DOUCEUR,
        5 => self::BLAGUE,
        6 => self::PROVERBE,
        7 => self::DOUCEUR,
    ];

    /** @var array<int, string> */
    private const ENCOURAGEMENTS = [
        "Lundi. Rien n'est encore fait, et c'est exactement pour ça que tout est encore possible.",
        "La semaine est longue vue de lundi. Elle se fait pourtant un jour à la fois, comme toutes les autres.",
        "Tu n'as pas à tout réussir aujourd'hui. Tu as juste à commencer.",
        "Le plus dur, c'est de s'asseoir. Une fois assis, le travail se laisse faire.",
        "Personne ne voit l'effort. Tout le monde voit le résultat. Fais-le quand même.",
        "Si c'était facile, ça n'aurait pas besoin de toi.",
        "Une tâche à la fois, et la montagne devient un chemin.",
        "Tu as déjà traversé des semaines plus dures que celle-ci. Tu l'avais oublié, pas moi.",
        "Ce n'est pas la force qui fait avancer, c'est de recommencer chaque matin.",
        "Avance, même doucement. Un pas lent reste un pas.",
        "Le travail bien fait ne se crie pas. Il se remarque quand même.",
        "Ce que tu fais aujourd'hui, quelqu'un en dépendra demain. Ça vaut le coup de le faire bien.",
    ];

    /** @var array<int, string> */
    private const PROVERBES = [
        "Petit à petit, l'oiseau fait son nid.",
        "Un seul doigt ne peut pas laver le visage.",
        "La patience est un arbre aux racines amères et aux fruits très doux.",
        "Quand les racines sont profondes, on ne craint pas le vent.",
        "On ne construit pas une maison en commençant par le toit.",
        "Celui qui pose une question paraît ignorant cinq minutes. Celui qui n'en pose jamais l'est toute sa vie.",
        "Le mensonge donne des fleurs, jamais des fruits.",
        "L'eau est douce, et pourtant c'est elle qui creuse la pierre.",
        "La pluie ne tombe pas sur un seul toit.",
        "Le chemin se fait en marchant.",
        "Une seule main ne peut pas attacher un fagot.",
        "Ce qui est fait avec soin ne se refait pas deux fois.",
        "Le travail est un trésor qui ne rouille pas.",
        "Qui veut aller loin ménage sa monture.",
        "On reconnaît l'arbre à ses fruits, et l'agent à ses dossiers.",
        "Le silence aussi est une réponse.",
    ];

    /**
     * Les enigmes, avec leur reponse.
     *
     * La reponse ne s'affiche pas tout de suite : l'ecran propose de la
     * decouvrir. Une enigme resolue d'avance n'est plus une enigme, et le
     * petit plaisir de chercher trente secondes est tout l'interet du matin.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const ENIGMES = [
        ["Plus on m'enlève, plus je deviens grand. Qui suis-je ?", "Un trou."],
        ["Je monte et je ne redescends jamais. Qui suis-je ?", "L'âge."],
        ["On me remplit toute la journée, et chaque matin je suis vide. Qui suis-je ?", "Le tiroir-caisse."],
        ["Je voyage à travers le monde en restant dans un coin. Qui suis-je ?", "Le timbre."],
        ["Je n'ai ni bouche ni oreilles, mais je dis toujours la vérité sur ce qu'on me confie. Qui suis-je ?", "La balance."],
        ["Qu'est-ce qui appartient à tout le monde, mais que les autres utilisent plus que toi ?", "Ton nom."],
        ["Je suis léger comme rien, mais personne ne peut me retenir plus d'une minute. Qui suis-je ?", "Le souffle."],
        ["Je peux faire le tour de la terre en restant dans mon coin. Qui suis-je ?", "Le vent."],
        ["Plus j'ai de gardiens, moins je suis en sécurité. Qui suis-je ?", "Le secret."],
        ["Je pars plein et je reviens vide, et personne ne m'en veut. Qui suis-je ?", "Le camion de livraison."],
        ["Qu'est-ce qui se casse dès qu'on le prononce ?", "Le silence."],
        ["Deux mères et deux filles sortent de l'agence. Elles sont trois. Comment ?", "Une grand-mère, sa fille et sa petite-fille."],
        ["Je n'ai pas de pieds et je fais tout le trajet. Qui suis-je ?", "Le colis."],
        ["Qu'est-ce qui grandit quand on le partage ?", "Le savoir."],
    ];

    /** @var array<int, string> */
    private const DOUCEURS = [
        "Si tu es arrivé jusqu'ici, c'est que tu es plus solide que tu ne le crois.",
        "Ce que tu n'as pas fini hier attendra. Commence par ce qui est devant toi.",
        "Tu as le droit d'avoir une journée moyenne. Tout le monde en a.",
        "Respire un coup avant d'ouvrir le premier dossier. Ça ne coûte rien et ça change tout.",
        "Ce que tu portes ce matin, personne ne le voit. Ça ne le rend pas moins lourd. Sois patient avec toi.",
        "Un mot gentil à un collègue ce matin, et vous serez deux à aller mieux.",
        "Si quelque chose t'a fâché avant d'arriver, laisse-le à la porte. Il sera encore là ce soir, tu verras.",
        "Personne ne fait tout bien. On fait de son mieux, et c'est déjà beaucoup.",
        "Tu n'es pas en retard sur ta vie. Il n'y a pas d'horaire.",
        "Prends ton café. Le travail peut attendre cinq minutes, il a attendu toute la nuit.",
        "Demander de l'aide n'est pas une faiblesse. C'est du temps gagné pour tout le monde.",
        "Ce qui est difficile aujourd'hui sera une habitude dans un mois.",
        "Tu as le droit de dire que tu n'as pas compris. C'est même le plus rapide.",
        "Une erreur reconnue tôt coûte moins cher qu'une erreur cachée longtemps. Et personne ne t'en voudra.",
    ];

    /** @var array<int, string> */
    private const BLAGUES = [
        "Pourquoi le colis était-il fatigué en arrivant ? Il avait fait tout le trajet debout.",
        "Un client demande : mon colis arrive quand ? L'agent répond : avant la fin du monde. Le client : et plus précisément ? L'agent : c'est déjà précis, on n'a pas la date de la fin du monde.",
        "Vendredi est le seul jour où même l'imprimante a envie de coopérer.",
        "Mon tiroir-caisse et moi, on a le même problème : on est toujours à l'équilibre juste après la fermeture.",
        "Un carton entre dans une agence. L'agent demande : vous êtes plein ? Le carton répond : non, juste bien rempli.",
        "Le plus court chemin entre deux agences, c'est le téléphone.",
        "Pourquoi les balances sont-elles si honnêtes ? Parce qu'elles n'ont jamais appris à arrondir.",
        "On m'a dit que le travail c'est la santé. Depuis, je laisse les autres se soigner un peu aussi.",
        "Un client me dit : votre système est lent. Je lui réponds : non, il réfléchit. Comme nous avant le café.",
        "Le café du matin, c'est comme un bon manifeste : sans lui, rien ne part.",
        "Deux colis discutent. Le premier dit : j'ai fait Abidjan-Paris. Le second : moi j'ai fait Abidjan-Adjamé, mais aux heures de pointe.",
        "Ce qui est bien avec la fin du mois, c'est qu'elle revient toujours. Ce qui est moins bien, c'est qu'elle revient toujours.",
        "Un agent demande : c'est quoi le secret d'un dossier parfait ? Le chef répond : le faire deux fois. La première ne compte pas.",
        "Mon imprimante a trois états : prête, occupée, et vexée.",
        "Pourquoi l'archiviste est-il toujours calme ? Parce que chez lui, tout est déjà classé.",
    ];

    /**
     * Le mot du jour. Le meme pour toute l'entreprise.
     *
     * C'est voulu : que chacun lise la meme enigme le mercredi cree un petit
     * quelque chose de commun entre Dokui, Adjame et Bobigny — on s'en parle a
     * midi. Tirer au hasard pour chacun aurait coupe ce fil.
     *
     * @return array{categorie: string, texte: string, reponse: ?string, salutation: string}
     */
    public static function duJour(?\DateTimeInterface $jour = null, string $prenom = ''): array
    {
        $jour ??= new \DateTimeImmutable('now', new \DateTimeZone('Africa/Abidjan'));

        $numeroJour = (int) $jour->format('N');
        $categorie = self::TON_DU_JOUR[$numeroJour] ?? self::DOUCEUR;

        $banque = self::banque($categorie);
        $index = (int) $jour->format('z') % max(1, count($banque));
        $choisi = $banque[$index];

        return [
            'categorie' => $categorie,
            'texte' => is_array($choisi) ? $choisi[0] : $choisi,
            'reponse' => is_array($choisi) ? $choisi[1] : null,
            'salutation' => self::salutation($jour, $prenom),
        ];
    }

    /**
     * « Bonjour Mariam » — et non « Bonjour utilisateur ».
     *
     * Un assistant qui ne nomme pas la personne a qui il parle reste une
     * machine. Apres midi on dit bonsoir : se tromper la-dessus a Abidjan
     * s'entend tout de suite.
     */
    private static function salutation(\DateTimeInterface $jour, string $prenom): string
    {
        $heure = (int) $jour->format('G');
        $mot = $heure < 16 ? 'Bonjour' : 'Bonsoir';

        return $prenom !== '' ? $mot . ' ' . $prenom : $mot;
    }

    /** @return array<int, string|array{0: string, 1: string}> */
    private static function banque(string $categorie): array
    {
        return match ($categorie) {
            self::ENCOURAGEMENT => self::ENCOURAGEMENTS,
            self::PROVERBE => self::PROVERBES,
            self::ENIGME => self::ENIGMES,
            self::BLAGUE => self::BLAGUES,
            default => self::DOUCEURS,
        };
    }

    /** Combien de matins NOEL peut tenir sans se repeter, categorie par categorie. */
    public static function tailleDeLaBanque(): int
    {
        return count(self::ENCOURAGEMENTS) + count(self::PROVERBES)
            + count(self::ENIGMES) + count(self::DOUCEURS) + count(self::BLAGUES);
    }

    /** @return array<string, int> */
    public static function repartition(): array
    {
        return [
            self::ENCOURAGEMENT => count(self::ENCOURAGEMENTS),
            self::PROVERBE => count(self::PROVERBES),
            self::ENIGME => count(self::ENIGMES),
            self::DOUCEUR => count(self::DOUCEURS),
            self::BLAGUE => count(self::BLAGUES),
        ];
    }
}
