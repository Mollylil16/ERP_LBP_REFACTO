<?php

declare(strict_types=1);

namespace App\Security;

use App\Helpers\Auth;

/**
 * Les modules dont l'existence même ne regarde pas tout le monde.
 *
 * Un module ordinaire se ferme en cachant sa tuile : celui qui tape l'adresse
 * à la main tombe sur un refus, et c'est suffisant. Les Ressources humaines,
 * non. On y lit les salaires, les contrats, les sanctions et les sorties de
 * chacun ; la responsable RH demande le 05/10/2026 que le module disparaisse
 * de la vue de tous.
 *
 * Alors la porte se ferme à l'entrée du routeur, avant tout contrôleur : ni
 * tuile, ni page, ni formulaire, ni export. Et le refus est un 404, pas un
 * 403 — répondre « accès refusé » confirmerait que le module existe, et à qui
 * s'adresser pour y entrer.
 *
 * L'Espace employé n'est pas concerné : chacun y consulte son propre dossier,
 * et il vit sous sa propre adresse.
 */
final class ModuleReserve
{
    /**
     * Le préfixe d'adresse de chaque module réservé, et qui peut l'ouvrir.
     *
     * @var array<string, array<int, string>>
     */
    public const RESERVES = [
        'rh' => ['admin', 'dg', 'responsable_rh'],
    ];

    /** Le module réservé auquel cette adresse appartient, s'il y en a un. */
    public static function moduleDe(string $chemin): ?string
    {
        foreach (array_keys(self::RESERVES) as $slug) {
            if ($chemin === '/' . $slug || str_starts_with($chemin, '/' . $slug . '/')) {
                return $slug;
            }
        }

        return null;
    }

    /** L'utilisateur courant peut-il ouvrir ce module réservé ? */
    public static function peutOuvrir(string $slug): bool
    {
        $roles = self::RESERVES[$slug] ?? null;

        if ($roles === null) {
            return true;
        }

        if (!Auth::check()) {
            return false;
        }

        return Auth::isAdmin() || Auth::hasAnyRole($roles);
    }

    /**
     * Cette adresse doit-elle être refusée à l'utilisateur courant ?
     *
     * C'est l'unique question que pose le routeur : il ne connaît ni les rôles
     * ni les modules, il demande seulement si ce chemin se laisse ouvrir.
     */
    public static function refuse(string $chemin): bool
    {
        $slug = self::moduleDe($chemin);

        return $slug !== null && !self::peutOuvrir($slug);
    }
}
