<?php

declare(strict_types=1);

namespace App\Services\Noel;

/**
 * Le mot que NOEL dit chaque matin.
 *
 * Une premiere idee voulait des messages ancres dans la journee — « le vol de
 * Paris part jeudi ». L'administrateur l'a ecartee le 22/10/2026, et il avait
 * raison : le jour ou le vol ne part pas jeudi, NOEL ment. Apres avoir promis
 * qu'il n'inventerait jamais rien, c'etait un mauvais depart.
 *
 * Ce ne sont donc ni des nouvelles ni des rappels, mais des choses vraies quel
 * que soit le jour : une blague, une enigme, un proverbe, une parole douce.
 *
 * AUCUN MESSAGE NE NOMME UN JOUR DE LA SEMAINE. Une premiere version placait
 * « Lundi. Rien n'est encore fait » dans les encouragements, qui ne sortent
 * que le lundi — c'etait donc vrai, et un test le verifiait sur une annee.
 * Mais le lien etait invisible : deplacer un message de categorie l'aurait
 * rendu faux sans bruit. On a prefere qu'aucun message ne depende du calendrier.
 *
 * Tout est ecrit a la main. Aucune intelligence artificielle, donc aucun cout,
 * aucune latence, et aucun risque qu'un matin NOEL sorte une phrase que
 * personne n'a relue.
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
        "Rien n'est encore fait, et c'est exactement pour ca que tout est encore possible.",
        "La semaine se fait un jour a la fois, comme toutes les autres.",
        "Tu n'as pas a tout reussir. Tu as juste a commencer.",
        "Le plus dur, c'est de s'asseoir. Une fois assis, le travail se laisse faire.",
        "Personne ne voit l'effort. Tout le monde voit le resultat. Fais-le quand meme.",
        "Si c'etait facile, ca n'aurait pas besoin de toi.",
        "Une tache a la fois, et la montagne devient un chemin.",
        "Tu as deja traverse des semaines plus dures que celle-ci. Tu l'avais oublie, pas moi.",
        "Ce n'est pas la force qui fait avancer, c'est de recommencer chaque matin.",
        "Avance, meme doucement. Un pas lent reste un pas.",
        "Le travail bien fait ne se crie pas. Il se remarque quand meme.",
        "Ce que tu fais aujourd'hui, quelqu'un en dependra. Ca vaut le coup de le faire bien.",
        "Commence par le dossier qui te fait le plus peur. Le reste paraitra leger.",
        "Tu ne rattraperas pas tout le retard d'un coup. Prends-en un morceau.",
        "Le courage, ce n'est pas de ne pas avoir peur. C'est d'ouvrir le dossier quand meme.",
        "Chaque colis bien enregistre est un client qui ne rappellera pas en colere.",
        "On ne te demande pas d'etre parfait. On te demande d'etre la.",
        "Ce qui te semble lent aujourd'hui sera un reflexe dans un mois.",
        "La regularite bat le talent, tous les jours de la semaine.",
        "Tu fais partie de ceux sans qui rien ne partirait. Souviens-t'en.",
        "Il n'y a pas de petite tache dans une chaine. Il n'y a que des maillons.",
        "Range ton bureau avant de commencer. La tete suit souvent les mains.",
        "Le travail de ce matin, c'est la tranquillite de ce soir.",
        "Quand tout arrive en meme temps, fais la liste. La liste ne panique pas.",
        "Celui qui avance lentement et sans s'arreter depasse celui qui court par a-coups.",
        "Le dossier le plus vieux est celui qui coute le plus cher. Prends-le en premier.",
        "Tu n'es pas seul. Demande, et quelqu'un repondra.",
        "Ce que tu apprends ce matin, tu l'auras pour toujours.",
        "Les bons jours ne sont pas ceux ou tout va bien. Ce sont ceux ou l'on tient.",
        "Mieux vaut un dossier fini que trois commences.",
        "L'ordre dans les papiers, c'est de l'ordre dans la tete.",
        "Tu as le droit d'etre lent le matin. Le corps aussi doit arriver.",
        "Un probleme dit a voix haute est deja a moitie resolu.",
        "La fin du mois arrive toujours. Ce qui change, c'est si tu l'as preparee.",
        "Fais aujourd'hui ce que tu n'auras pas envie de faire demain non plus.",
    ];

    /** @var array<int, string> */
    private const PROVERBES = [
        "Petit a petit, l'oiseau fait son nid.",
        "Un seul doigt ne peut pas laver le visage.",
        "La patience est un arbre aux racines ameres et aux fruits tres doux.",
        "Quand les racines sont profondes, on ne craint pas le vent.",
        "On ne construit pas une maison en commencant par le toit.",
        "Celui qui pose une question parait ignorant un instant. Celui qui n'en pose jamais l'est toute sa vie.",
        "Le mensonge donne des fleurs, jamais des fruits.",
        "L'eau est douce, et pourtant c'est elle qui creuse la pierre.",
        "La pluie ne tombe pas sur un seul toit.",
        "Le chemin se fait en marchant.",
        "Une seule main ne peut pas attacher un fagot.",
        "Ce qui est fait avec soin ne se refait pas deux fois.",
        "Le travail est un tresor qui ne rouille pas.",
        "Qui veut aller loin menage sa monture.",
        "On reconnait l'arbre a ses fruits.",
        "Le silence aussi est une reponse.",
        "Celui qui se couche avec un probleme se leve avec deux.",
        "La riviere ne remonte jamais, et pourtant elle arrive toujours quelque part.",
        "Un pied ne marche pas sur deux chemins.",
        "On ne peut pas cacher la fumee d'un feu.",
        "Le vieux balai connait les coins de la maison.",
        "Ce n'est pas parce que la nuit est longue que le jour ne viendra pas.",
        "L'eau versee ne se ramasse pas entierement.",
        "Celui qui court trop vite oublie ce qu'il transporte.",
        "La main qui donne est au-dessus de celle qui recoit.",
        "Un arbre seul ne fait pas la foret.",
        "Le miel est doux, mais l'abeille pique.",
        "On apprend a nager dans l'eau, pas dans le sable.",
        "Celui qui connait le chemin marche la nuit comme le jour.",
        "La case qu'on construit ensemble ne tombe pas au premier vent.",
        "Le tambour s'entend de loin parce qu'il est creux.",
        "Ce qui est ecrit ne discute pas.",
        "La verite est comme l'huile : elle finit toujours par remonter.",
        "On ne compte pas les poussins avant qu'ils soient sortis de l'oeuf.",
        "Mieux vaut demander deux fois que se tromper une fois.",
        "Le feu qu'on allume mal brule la main deux fois.",
        "La parole donnee vaut plus que le papier perdu.",
        "Celui qui balaie devant sa porte n'a pas besoin de se plaindre du village.",
        "Le fleuve ne se bouscule pas, et il arrive avant tout le monde.",
        "Une charge bien repartie n'a jamais casse de dos.",
    ];

    /**
     * Les enigmes : la question, la reponse, le pourquoi, et ce qu'on accepte.
     *
     * Le « pourquoi » n'est pas un ornement. Donner la solution sans
     * l'explication laisse la personne aussi avancee qu'avant, et un peu vexee
     * en plus. Les formulations acceptees evitent de refuser quelqu'un qui
     * avait trouve mais l'a dit autrement.
     *
     * @var array<int, array{question: string, reponse: string, pourquoi: string, acceptees: array<int, string>}>
     */
    private const ENIGMES = [
        [
            'question' => "Plus on m'enleve, plus je deviens grand. Qui suis-je ?",
            'reponse' => "Un trou",
            'pourquoi' => "Chaque pelletee de terre retiree agrandit le trou au lieu de le reduire.",
            'acceptees' => ['trou', 'le trou', 'un puits', 'puits'],
        ],
        [
            'question' => "Je monte et je ne redescends jamais. Qui suis-je ?",
            'reponse' => "L'age",
            'pourquoi' => "On peut tout perdre et tout refaire, sauf revenir en arriere sur les annees.",
            'acceptees' => ['age', 'mon age', 'les annees', 'le temps'],
        ],
        [
            'question' => "On me remplit toute la journee, et chaque matin je suis vide. Qui suis-je ?",
            'reponse' => "Le tiroir-caisse",
            'pourquoi' => "On l'alimente du matin au soir, puis le point de caisse le vide pour la nuit.",
            'acceptees' => ['tiroir caisse', 'la caisse', 'caisse', 'tiroir'],
        ],
        [
            'question' => "Je voyage a travers le monde en restant dans un coin. Qui suis-je ?",
            'reponse' => "Le timbre",
            'pourquoi' => "Colle dans l'angle de l'enveloppe, il ne bouge pas et fait pourtant tout le trajet.",
            'acceptees' => ['timbre', 'un timbre'],
        ],
        [
            'question' => "Je n'ai ni bouche ni oreilles, mais je dis toujours la verite sur ce qu'on me confie. Qui suis-je ?",
            'reponse' => "La balance",
            'pourquoi' => "Elle ne sait ni mentir ni arrondir : elle affiche ce qu'on pose dessus.",
            'acceptees' => ['balance', 'une balance', 'le pese colis'],
        ],
        [
            'question' => "Qu'est-ce qui t'appartient, mais que les autres utilisent bien plus que toi ?",
            'reponse' => "Ton nom",
            'pourquoi' => "Tu le prononces rarement ; ceux qui t'appellent le disent toute la journee.",
            'acceptees' => ['nom', 'mon nom', 'le nom', 'ton prenom', 'prenom'],
        ],
        [
            'question' => "Je suis leger comme rien, mais personne ne peut me retenir plus d'une minute. Qui suis-je ?",
            'reponse' => "Le souffle",
            'pourquoi' => "Il ne pese rien et pourtant aucun effort ne permet de le garder longtemps.",
            'acceptees' => ['souffle', 'la respiration', 'respiration', 'air', 'mon souffle'],
        ],
        [
            'question' => "Je fais le tour de la terre en restant dans mon coin. Qui suis-je ?",
            'reponse' => "Le vent",
            'pourquoi' => "Il souffle partout sans jamais quitter l'atmosphere qui l'entoure.",
            'acceptees' => ['vent', 'le vent', 'air'],
        ],
        [
            'question' => "Plus j'ai de gardiens, moins je suis en securite. Qui suis-je ?",
            'reponse' => "Le secret",
            'pourquoi' => "Chaque personne de plus qui le connait est une fuite possible de plus.",
            'acceptees' => ['secret', 'un secret', 'les secrets'],
        ],
        [
            'question' => "Je pars plein et je reviens vide, et personne ne m'en veut. Qui suis-je ?",
            'reponse' => "Le camion de livraison",
            'pourquoi' => "Revenir vide veut dire que tout a ete livre : c'est une bonne nouvelle.",
            'acceptees' => ['camion', 'le camion', 'la camionnette', 'vehicule de livraison'],
        ],
        [
            'question' => "Qu'est-ce qui se casse des qu'on le prononce ?",
            'reponse' => "Le silence",
            'pourquoi' => "Dire le mot « silence » suffit a le faire disparaitre.",
            'acceptees' => ['silence', 'un silence'],
        ],
        [
            'question' => "Deux meres et deux filles sortent de l'agence. Elles sont trois. Comment ?",
            'reponse' => "Une grand-mere, sa fille et sa petite-fille",
            'pourquoi' => "Celle du milieu est a la fois la fille de l'une et la mere de l'autre.",
            'acceptees' => ['grand mere fille petite fille', 'trois generations', 'la mere est aussi fille'],
        ],
        [
            'question' => "Je n'ai pas de pieds et je fais tout le trajet. Qui suis-je ?",
            'reponse' => "Le colis",
            'pourquoi' => "Il traverse les pays sans jamais marcher : ce sont les autres qui le portent.",
            'acceptees' => ['colis', 'un colis', 'le carton', 'carton'],
        ],
        [
            'question' => "Qu'est-ce qui grandit quand on le partage ?",
            'reponse' => "Le savoir",
            'pourquoi' => "Expliquer ne retire rien a celui qui explique, et ajoute a celui qui ecoute.",
            'acceptees' => ['savoir', 'la connaissance', 'connaissance', 'le partage'],
        ],
        [
            'question' => "J'ai des villes sans maisons, des routes sans voitures et des fleuves sans eau. Qui suis-je ?",
            'reponse' => "La carte",
            'pourquoi' => "Tout y est dessine, rien n'y est reel.",
            'acceptees' => ['carte', 'une carte', 'la mappemonde', 'plan'],
        ],
        [
            'question' => "Plus je seche, plus je mouille. Qui suis-je ?",
            'reponse' => "La serviette",
            'pourquoi' => "A mesure qu'elle seche ce qu'on essuie, elle se charge d'eau.",
            'acceptees' => ['serviette', 'un torchon', 'torchon', 'la serpillere'],
        ],
        [
            'question' => "Je suis toujours devant toi, et pourtant tu ne me verras jamais. Qui suis-je ?",
            'reponse' => "L'avenir",
            'pourquoi' => "Il est devant chacun a chaque instant, et devient passe des qu'on l'atteint.",
            'acceptees' => ['avenir', 'le futur', 'futur', 'demain'],
        ],
        [
            'question' => "Qu'est-ce qui a un cou mais pas de tete ?",
            'reponse' => "La bouteille",
            'pourquoi' => "On appelle « col » la partie etroite sous le bouchon.",
            'acceptees' => ['bouteille', 'une bouteille', 'le col de bouteille'],
        ],
        [
            'question' => "Je passe mon temps a tourner sans jamais quitter ma place. Qui suis-je ?",
            'reponse' => "La roue",
            'pourquoi' => "Elle tourne sur son axe : c'est le vehicule qui avance, pas elle.",
            'acceptees' => ['roue', 'une roue', 'le ventilateur', 'ventilateur'],
        ],
        [
            'question' => "Qu'est-ce qui appartient a l'avenir mais se depense au present ?",
            'reponse' => "Le temps",
            'pourquoi' => "On ne possede que celui qui reste, et on ne peut l'utiliser que maintenant.",
            'acceptees' => ['temps', 'le temps'],
        ],
        [
            'question' => "On me coupe, on me frappe, on me pietine, et pourtant je reviens chaque saison. Qui suis-je ?",
            'reponse' => "L'herbe",
            'pourquoi' => "Ses racines restent sous terre : seule la partie visible est atteinte.",
            'acceptees' => ['herbe', 'le gazon', 'gazon', 'la pelouse'],
        ],
        [
            'question' => "Je n'ai pas de voix et je repete tout ce qu'on me dit. Qui suis-je ?",
            'reponse' => "L'echo",
            'pourquoi' => "Il ne produit aucun son : il renvoie celui qui l'a frappe.",
            'acceptees' => ['echo', 'un echo', 'le perroquet'],
        ],
        [
            'question' => "Qu'est-ce qui monte quand la pluie descend ?",
            'reponse' => "Le parapluie",
            'pourquoi' => "On l'ouvre et on le leve au moment precis ou l'eau tombe.",
            'acceptees' => ['parapluie', 'un parapluie', 'le niveau de l eau', 'la riviere'],
        ],
        [
            'question' => "Deux soeurs se font face toute leur vie sans jamais se toucher. Qui sont-elles ?",
            'reponse' => "Les deux rives",
            'pourquoi' => "Separees par le fleuve, elles se regardent sans jamais se rejoindre.",
            'acceptees' => ['rives', 'les rives', 'les berges', 'les deux yeux', 'les yeux'],
        ],
        [
            'question' => "Je deviens plus lourd quand on m'enleve ce que j'ai de plus leger. Qui suis-je ?",
            'reponse' => "Le ballon",
            'pourquoi' => "Vide d'air, il ne flotte plus et retombe au sol.",
            'acceptees' => ['ballon', 'un ballon', 'le ballon de baudruche'],
        ],
        [
            'question' => "Qu'est-ce qu'on jette quand on en a besoin et qu'on ramasse quand on n'en a plus besoin ?",
            'reponse' => "L'ancre",
            'pourquoi' => "On la jette pour s'arreter, on la remonte pour repartir.",
            'acceptees' => ['ancre', 'une ancre', 'le filet', 'filet'],
        ],
        [
            'question' => "Je suis plein de trous et pourtant je retiens l'eau. Qui suis-je ?",
            'reponse' => "L'eponge",
            'pourquoi' => "Ce sont justement ses trous qui absorbent et gardent le liquide.",
            'acceptees' => ['eponge', 'une eponge'],
        ],
        [
            'question' => "Qu'est-ce qui se trouve au milieu de Paris ?",
            'reponse' => "La lettre R",
            'pourquoi' => "Dans le mot « Paris », la lettre du milieu est le R.",
            'acceptees' => ['r', 'la lettre r', 'le r'],
        ],
        [
            'question' => "Je marche a quatre pattes le matin, deux a midi et trois le soir. Qui suis-je ?",
            'reponse' => "L'homme",
            'pourquoi' => "Il rampe enfant, marche adulte et s'appuie sur une canne age : c'est une vie entiere.",
            'acceptees' => ['homme', 'l etre humain', 'etre humain', 'la vie'],
        ],
        [
            'question' => "Qu'est-ce qui a des dents mais ne mange jamais ?",
            'reponse' => "Le peigne",
            'pourquoi' => "Ses dents servent a demeler, pas a mordre.",
            'acceptees' => ['peigne', 'un peigne', 'la scie', 'scie', 'la fourchette'],
        ],
        [
            'question' => "Plus j'en ai, moins tu vois. Qui suis-je ?",
            'reponse' => "L'obscurite",
            'pourquoi' => "Chaque part d'ombre en plus retire de la lumiere.",
            'acceptees' => ['obscurite', 'le noir', 'noir', 'la nuit', 'brouillard', 'le brouillard'],
        ],
        [
            'question' => "On me prepare, on m'attend, et quand j'arrive personne ne me regarde. Qui suis-je ?",
            'reponse' => "Le lendemain",
            'pourquoi' => "On passe la veille a le preparer, et une fois la il devient simplement aujourd'hui.",
            'acceptees' => ['lendemain', 'le jour suivant', 'demain'],
        ],
        [
            'question' => "Qu'est-ce qui se remplit de papier et ne sait pas lire ?",
            'reponse' => "Le classeur",
            'pourquoi' => "Il garde tout et ne comprend rien : c'est l'archiviste qui lit.",
            'acceptees' => ['classeur', 'un classeur', 'le dossier', 'la poubelle', 'l armoire'],
        ],
        [
            'question' => "Je grandis sans etre vivant, je n'ai pas de poumons et pourtant j'ai besoin d'air. Qui suis-je ?",
            'reponse' => "Le feu",
            'pourquoi' => "Il s'etend comme un etre vivant et s'eteint des qu'on le prive d'oxygene.",
            'acceptees' => ['feu', 'le feu', 'un incendie', 'incendie'],
        ],
        [
            'question' => "Qu'est-ce qui pese plus lourd vide que plein ?",
            'reponse' => "Le ballon de football",
            'pourquoi' => "Gonfle, il rebondit et parait leger ; degonfle, il tombe comme une pierre.",
            'acceptees' => ['ballon', 'le ballon', 'la montgolfiere', 'montgolfiere'],
        ],
    ];

    /** @var array<int, string> */
    private const DOUCEURS = [
        "Si tu es arrive jusqu'ici, c'est que tu es plus solide que tu ne le crois.",
        "Ce que tu n'as pas fini hier attendra. Commence par ce qui est devant toi.",
        "Tu as le droit d'avoir une journee moyenne. Tout le monde en a.",
        "Respire un coup avant d'ouvrir le premier dossier. Ca ne coute rien et ca change tout.",
        "Ce que tu portes ce matin, personne ne le voit. Ca ne le rend pas moins lourd. Sois patient avec toi.",
        "Un mot gentil a un collegue, et vous serez deux a aller mieux.",
        "Si quelque chose t'a fache avant d'arriver, laisse-le a la porte. Il sera encore la ce soir, tu verras.",
        "Personne ne fait tout bien. On fait de son mieux, et c'est deja beaucoup.",
        "Tu n'es pas en retard sur ta vie. Il n'y a pas d'horaire.",
        "Prends ton cafe. Le travail peut attendre cinq minutes, il a attendu toute la nuit.",
        "Demander de l'aide n'est pas une faiblesse. C'est du temps gagne pour tout le monde.",
        "Ce qui est difficile aujourd'hui sera une habitude dans un mois.",
        "Tu as le droit de dire que tu n'as pas compris. C'est meme le plus rapide.",
        "Une erreur reconnue tot coute moins cher qu'une erreur cachee longtemps. Et personne ne t'en voudra.",
        "Ton humeur de ce matin n'est pas ta personnalite. C'est juste ce matin.",
        "Tu n'as pas a porter les soucis de toute l'agence. Pose-en quelques-uns.",
        "Les gens se souviennent de comment tu les as traites, pas de ta vitesse.",
        "Si tu te sens perdu, arrete-toi et ecris les trois choses a faire. Trois, pas trente.",
        "Ce n'est pas grave de ne pas savoir. C'est grave de rester seul avec.",
        "Ta valeur ne se mesure pas au nombre de dossiers traites.",
        "Tu as le droit de dire non quand tu es deja plein.",
        "La fatigue ment : elle fait croire que tout est urgent.",
        "Quelqu'un ici est content que tu sois venu travailler aujourd'hui. Il ne te l'a juste pas dit.",
        "Repose ton regard loin de l'ecran une minute. Les yeux aussi travaillent.",
        "Une journee difficile ne fait pas une mauvaise semaine.",
        "Tu peux etre rigoureux sans etre dur avec toi-meme.",
        "Bois de l'eau. C'est le conseil le plus bete et le plus efficace que je connaisse.",
        "Ce que tu crois etre une grosse erreur est souvent une petite correction.",
        "Si le travail te semble insurmontable, c'est peut-etre qu'il est mal decoupe, pas que tu es incapable.",
        "Un collegue qui raie son nom de ta liste de soucis vaut mieux qu'une heure de plus.",
        "Il n'y a pas de honte a recommencer un document. Il y a de la honte a en envoyer un faux.",
        "Tu es arrive. C'est deja la moitie du chemin.",
        "Les journees calmes servent a reparer ce que les journees pleines ont casse.",
        "Fais une chose bien plutot que trois a moitie. Ton soir te remerciera.",
        "Si tu doutes, verifie. Le doute verifie devient une certitude ; le doute garde devient une insomnie.",
        "Personne ne t'en voudra d'avoir pris cinq minutes pour comprendre avant d'agir.",
        "Ce travail est important, mais toi aussi.",
        "Le plus beau dossier du monde ne vaut pas une nuit blanche.",
        "Tu as le droit de demander deux fois. Et trois, si besoin.",
        "Ce soir, quelque chose sera fini qui ne l'etait pas ce matin. C'est deja ca.",
    ];

    /** @var array<int, string> */
    private const BLAGUES = [
        "Pourquoi le colis etait-il fatigue en arrivant ? Il avait fait tout le trajet debout.",
        "Un client demande : mon colis arrive quand ? L'agent repond : avant la fin du monde. Le client : et plus precisement ? L'agent : c'est deja precis, on n'a pas la date.",
        "Mon tiroir-caisse et moi, on a le meme probleme : on est toujours a l'equilibre juste apres la fermeture.",
        "Un carton entre dans une agence. L'agent demande : vous etes plein ? Le carton repond : non, juste bien rempli.",
        "Le plus court chemin entre deux agences, c'est le telephone.",
        "Pourquoi les balances sont-elles si honnetes ? Parce qu'elles n'ont jamais appris a arrondir.",
        "On m'a dit que le travail c'est la sante. Depuis, je laisse les autres se soigner un peu aussi.",
        "Un client me dit : votre systeme est lent. Je lui reponds : non, il reflechit. Comme nous avant le cafe.",
        "Le cafe du matin, c'est comme un bon manifeste : sans lui, rien ne part.",
        "Deux colis discutent. Le premier dit : j'ai fait Abidjan-Paris. Le second : moi j'ai fait Abidjan-Adjame, mais aux heures de pointe.",
        "Ce qui est bien avec la fin du mois, c'est qu'elle revient toujours. Ce qui est moins bien, c'est qu'elle revient toujours.",
        "Un agent demande : c'est quoi le secret d'un dossier parfait ? Le chef repond : le faire deux fois. La premiere ne compte pas.",
        "Mon imprimante a trois etats : prete, occupee, et vexee.",
        "Pourquoi l'archiviste est-il toujours calme ? Parce que chez lui, tout est deja classe.",
        "Un client veut envoyer un piano. L'agent demande : fragile ? Le client : non, lourd. L'agent : alors c'est fragile pour moi.",
        "Le stylo du guichet a une particularite : il fonctionne parfaitement jusqu'a ce qu'un client le prenne.",
        "Mon reveil et moi avons un accord : il sonne, et je fais semblant d'etre surpris.",
        "Un colis mal etiquete, c'est comme un invite sans nom : tout le monde est poli, personne ne sait quoi en faire.",
        "Pourquoi les reunions sur la ponctualite commencent-elles toujours en retard ? Pour laisser une chance a tout le monde de comprendre le sujet.",
        "Le scotch d'emballage a deux vitesses : trop court, et jamais la ou on le cherche.",
        "Un client demande un emballage incassable. On lui a donne un carton et beaucoup d'esperance.",
        "La photocopieuse fonctionne toujours. C'est juste qu'elle choisit quand.",
        "Pourquoi le classeur est-il le collegue le plus fiable ? Il ne dit jamais « je croyais que tu l'avais fait ».",
        "Un chef demande : qui a fait ca ? Silence. Il demande : qui a bien fait ca ? Trois mains se levent.",
        "Mon telephone sonne toujours pile quand j'ai les deux mains dans un carton. C'est une regle de physique.",
        "Le mot de passe le plus sur, c'est celui qu'on a oublie. Personne ne le retrouvera, moi non plus.",
        "Un colis leger et un colis lourd arrivent ensemble. Devine lequel est en haut de la pile.",
        "Ce que j'aime dans les inventaires, c'est le moment ou ca tombe juste. Je l'aime beaucoup parce qu'il est rare.",
        "Le calendrier est un objet etrange : il avance tout seul, mais jamais quand on l'attend.",
        "J'ai demande au systeme d'etre patient. Il m'a repondu : delai depasse.",
        "Un agent range son bureau. Deux minutes apres, il ne trouve plus rien. C'est le prix du progres.",
        "Pourquoi le papier bulle existe-t-il ? Officiellement pour proteger. Officieusement, tout le monde sait pourquoi.",
        "La climatisation a deux reglages : glacial, et en panne.",
        "Un client demande si l'on peut accelerer. On a essaye de courir avec le carton, mais ca n'a rien change.",
        "Le plus grand mystere du bureau reste la disparition des agrafeuses.",
        "Un dossier urgent, c'est un dossier normal qu'on a regarde trop tard.",
        "Je voulais finir tot. Le telephone a eu une autre idee.",
        "Pourquoi le chariot grince-t-il toujours ? Pour prevenir qu'il arrive. C'est une politesse.",
        "Un colis bien ficele, c'est de l'amour qui ne se dit pas.",
        "Le meilleur moment de la journee, c'est quand la derniere ligne du registre tombe juste.",
    ];

    /**
     * Le mot du jour. Le meme pour toute l'entreprise.
     *
     * C'est voulu : que chacun lise la meme enigme le mercredi cree un petit
     * quelque chose de commun entre Dokui, Adjame et Bobigny — on s'en parle a
     * midi. Tirer au hasard pour chacun aurait coupe ce fil.
     *
     * @return array{categorie: string, texte: string, reponse: ?string, pourquoi: ?string, acceptees: array<int, string>, salutation: string}
     */
    public static function duJour(?\DateTimeInterface $jour = null, string $prenom = ''): array
    {
        $jour ??= new \DateTimeImmutable('now', new \DateTimeZone('Africa/Abidjan'));

        $numeroJour = (int) $jour->format('N');
        $categorie = self::TON_DU_JOUR[$numeroJour] ?? self::DOUCEUR;

        $banque = self::banque($categorie);

        /*
         * Le rang du jour dans l'annee, et non une date entiere : deux annees
         * de suite, le meme jour ramene le meme message. C'est voulu — une
         * banque de trente messages vus une fois par semaine tient plus de six
         * mois, et personne ne se souvient d'un proverbe lu l'an dernier.
         */
        $index = (int) $jour->format('z') % max(1, count($banque));
        $choisi = $banque[$index];

        if ($categorie !== self::ENIGME) {
            return [
                'categorie' => $categorie,
                'texte' => (string) $choisi,
                'reponse' => null,
                'pourquoi' => null,
                'acceptees' => [],
                'salutation' => self::salutation($jour, $prenom),
            ];
        }

        /** @var array{question: string, reponse: string, pourquoi: string, acceptees: array<int, string>} $choisi */
        return [
            'categorie' => $categorie,
            'texte' => $choisi['question'],
            'reponse' => $choisi['reponse'],
            'pourquoi' => $choisi['pourquoi'],
            'acceptees' => $choisi['acceptees'],
            'salutation' => self::salutation($jour, $prenom),
        ];
    }

    /**
     * L'enigme du jour sous la forme attendue par NoelEnigme.
     *
     * @return array{reponse: string, pourquoi: string, acceptees: array<int, string>}|null
     */
    public static function enigmeDuJour(?\DateTimeInterface $jour = null): ?array
    {
        $mot = self::duJour($jour);

        if ($mot['categorie'] !== self::ENIGME) {
            return null;
        }

        return [
            'reponse' => (string) $mot['reponse'],
            'pourquoi' => (string) $mot['pourquoi'],
            'acceptees' => $mot['acceptees'],
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

    /** @return array<int, mixed> */
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

    /** Combien de matins NOEL peut tenir, categorie par categorie. */
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

    /**
     * Dans combien de semaines une categorie se repete-t-elle ?
     *
     * Sert a repondre a la question « est-ce que ca tourne assez ? » avec un
     * chiffre plutot qu'une impression.
     *
     * @return array<string, int>
     */
    public static function semainesAvantRepetition(): array
    {
        $joursParSemaine = array_count_values(self::TON_DU_JOUR);
        $semaines = [];

        foreach (self::repartition() as $categorie => $nombre) {
            $parSemaine = $joursParSemaine[$categorie] ?? 1;
            $semaines[$categorie] = (int) floor($nombre / $parSemaine);
        }

        return $semaines;
    }
}
