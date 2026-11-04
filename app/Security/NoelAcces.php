<?php

declare(strict_types=1);

namespace App\Security;

use App\Helpers\Auth;

/**
 * Ce que NOEL a le droit de dire, et a qui.
 *
 * Nous avons passe des semaines a fermer des fuites : la caisse d'une autre
 * agence atteignable par l'adresse, les creances de tout le reseau sur un
 * ecran d'agence, le module RH rendu invisible. Un assistant qui interroge la
 * base sans repasser par ces regles les annulerait toutes d'un coup, et sans
 * bruit — c'est la pire facon de perdre ce qu'on a gagne.
 *
 * Alors NOEL ne connait aucun droit propre. Il emprunte ceux de la personne
 * qui lui parle, ecran par ecran, et n'a aucun moyen d'en obtenir davantage.
 */
final class NoelAcces
{
    /**
     * NOEL repond-il a cette personne ?
     *
     * Il parle a tout le personnel connecte : c'est le principe meme d'un
     * collegue. Ce qu'il dit, en revanche, depend de ce que la personne a le
     * droit de voir.
     */
    public static function peutLuiParler(): bool
    {
        return self::estAllume() && Auth::user() !== null;
    }

    /**
     * L'interrupteur general, pose dans config/noel.php.
     *
     * Eteint, NOEL disparait de toutes les pages. On ne laisse pas une bulle
     * qui s'excuse : un assistant muet inquiete plus qu'il ne rassure.
     */
    public static function estAllume(): bool
    {
        $config = require BASE_PATH . '/config/noel.php';

        return (bool) ($config['actif'] ?? false);
    }

    /**
     * NOEL sait-il reellement repondre, ou seulement orienter ?
     *
     * Sans cle d'API, il garde tout de meme sa valeur : il connait la carte du
     * logiciel et les guides, il sait ou se trouve un ecran et ce qu'on y
     * saisit. Il ne sait simplement pas tenir une conversation libre, et il
     * doit le dire plutot que de faire semblant.
     */
    public static function saitConverser(): bool
    {
        $config = require BASE_PATH . '/config/noel.php';

        if (!self::estAllume()) {
            return false;
        }

        // L'IA propre a LBP, servie par Ollama (voir NoelIA).
        if (($config['ia'] ?? 'aucun') === 'ollama') {
            return self::aDroitALIA((string) ($config['ia_comptes'] ?? ''));
        }

        return trim((string) ($config['cle_api'] ?? '')) !== '';
    }

    /**
     * Pendant l'essai, l'IA n'est ouverte qu'a une liste de personnes
     * (decide le 04/11/2026 : le directeur, l'administrateur, la responsable
     * RH, l'assistante DG, la caissiere principale, Mme Djambitche et le
     * comptable). Les autres gardent NOEL tel qu'il etait.
     */
    private static function aDroitALIA(string $liste): bool
    {
        $liste = trim($liste);
        if ($liste === '') {
            return true;
        }

        $email = strtolower(trim((string) (Auth::user()?->email ?? '')));
        if ($email === '') {
            return false;
        }

        $autorises = array_map(static fn(string $e): string => strtolower(trim($e)), explode(',', $liste));

        return in_array($email, $autorises, true);
    }

    /**
     * Qui recoit les signalements et le rapport du soir.
     *
     * L'administrateur et le DG, personne d'autre. Decide le 22/10/2026 :
     * « personne n'a le droit de voir les rapports a part moi seul ».
     */
    public static function estDestinataireDesRapports(): bool
    {
        return Auth::isAdmin() || Auth::hasAnyRole(['dg']);
    }

    /**
     * Les modules dont NOEL ne doit jamais souffler l'existence.
     *
     * Le module RH est invisible pour le reste du personnel : une tuile
     * absente, et un refus en 404 plutot qu'en « acces refuse », pour ne pas
     * confirmer qu'il y a une porte. NOEL doit tenir la meme ligne. Lui
     * repondre « je ne peux pas vous en parler » reviendrait a vendre la
     * meche ; il ignore ces ecrans, purement et simplement.
     */
    public static function peutEvoquerModule(string $slug): bool
    {
        foreach (ModuleReserve::RESERVES as $reserve => $roles) {
            if ($slug === $reserve || str_starts_with($slug, $reserve . '-')) {
                return Auth::isAdmin() || Auth::hasAnyRole($roles);
            }
        }

        /*
         * Les modules gardes par leur controleur, et non par leur menu.
         *
         * Constate le 28/10/2026 : NOEL annoncait au comptable les sept ecrans
         * de l'Administration et les cinq du Pilotage DG. Leurs entrees de
         * menu portent « available => true » en dur — c'est le controleur qui
         * refuse, a l'ouverture. NOEL envoyait donc sur des portes fermees.
         *
         * Pour Finance, Colisage et les autres, « available » porte le vrai
         * controle et suffit. Ici il ne dit rien, alors on le dit a sa place.
         */
        if ($slug === 'admin') {
            // AdminMiddleware::check() exige isAdmin, pas un role.
            return Auth::isAdmin();
        }

        if ($slug === 'pilotage-dg') {
            // Les controleurs du module font RoleMiddleware::check(['dg', 'admin']).
            return Auth::isAdmin() || Auth::hasAnyRole(['dg']);
        }

        return true;
    }

    /**
     * Le prenom de la personne, pour que NOEL l'appelle par son nom.
     *
     * Un assistant qui dit « bonjour » a quelqu'un qu'il ne nomme pas reste
     * une machine. Le nom complet est en « PRENOM NOM » ou « Prenom Nom »
     * selon les comptes ; on prend le premier mot et on le remet en casse de
     * civilite, sans quoi NOEL crierait « BONJOUR MARIAM ».
     */
    public static function prenom(): string
    {
        $nom = trim((string) (Auth::user()?->fullName ?? ''));

        if ($nom === '') {
            return '';
        }

        $premier = explode(' ', $nom)[0];

        return mb_convert_case($premier, MB_CASE_TITLE, 'UTF-8');
    }
}
