<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Helpers\Auth;
use App\Models\Database;
use PDO;

/**
 * Pas de point de caisse soumis, pas de caisse ouverte le lendemain.
 *
 * Mesuré en production le 24/09/2026 : 1 316 270 FCFA encaissés en un mois
 * sans qu'aucun point ne soit signé. Abobo Dokui en oubliait treize sur
 * vingt et un, l'aéroport n'en avait jamais soumis un seul en sept jours.
 * Cet argent-là n'a jamais été compté par personne, et aucun écart n'aurait
 * pu être détecté.
 *
 * La règle, décidée par la direction : à compter du 1er octobre 2026, une
 * journée encaissée doit être soumise. Tant qu'elle ne l'est pas, l'agence
 * ne peut plus facturer ni encaisser le lendemain. Soumettre le point
 * rouvre la caisse dans la seconde.
 *
 * Deux précautions. Les journées antérieures au 1er octobre ne bloquent
 * rien : personne n'était tenu de les soumettre, et l'aéroport aurait été
 * paralysé dès le premier jour par ses sept journées de retard. Et la
 * direction n'est jamais bloquée : c'est elle qui débloque, en soumettant le
 * point manquant à la place de l'agence si nécessaire.
 */
final class BlocageCaisseService
{
    /**
     * Premier jour où une journée non soumise bloque le lendemain.
     *
     * Les agences ont été prévenues : la règle ne s'applique qu'à partir de
     * cette date, pour laisser le temps à chacun de prendre l'habitude.
     */
    public const DEBUT_OBLIGATION = '2026-10-01';

    /**
     * Rôles que le blocage ne concerne pas.
     *
     * La direction doit pouvoir travailler, et surtout soumettre le point
     * manquant à la place d'une agence empêchée. C'est la clé de secours :
     * sans elle, un défaut du logiciel arrêterait une agence sans recours.
     */
    private const ROLES_EXEMPTES = ['dg', 'assistant_dg', 'assistante_dg', 'caissiere_principale', 'comptable', 'superviseur_general'];

    public function __construct(private PDO $pdo)
    {
    }

    public static function creer(): self
    {
        return new self(Database::getConnection());
    }

    /**
     * La journée la plus ancienne qui bloque cette agence, ou null.
     *
     * @return array{date:string, montant:float, operations:int}|null
     */
    public function journeeBloquante(?int $agenceId, ?string $aujourdhui = null): ?array
    {
        if (empty($agenceId)) {
            return null;
        }

        $aujourdhui ??= date('Y-m-d');

        if ($aujourdhui <= self::DEBUT_OBLIGATION) {
            return null;
        }

        /*
         * Une journée bloque si de l'argent y est entré dans cette agence et
         * qu'aucun point n'a été soumis. Le point rouvert par un encaissement
         * tardif repasse en brouillon : il bloque donc lui aussi, ce qui est
         * voulu — la journée n'a pas été recomptée.
         */
        $stmt = $this->pdo->prepare("
            SELECT jours.jour, jours.total, jours.nb
            FROM (
                SELECT DATE(p.date_paiement) AS jour,
                       COALESCE(p.agence_id, u.agence_id, f.agence_id) AS agence_id,
                       SUM(p.montant) AS total,
                       COUNT(*) AS nb
                FROM lbp_paiements p
                JOIN lbp_factures f ON f.id = p.facture_id
                LEFT JOIN users u ON u.id = p.caissiere_id
                WHERE p.date_paiement >= :debut
                  AND DATE(p.date_paiement) < :aujourdhui
                  AND p.devise = 'XOF'
                GROUP BY jour, agence_id
            ) AS jours
            LEFT JOIN lbp_etats_journaliers e
                   ON e.agence_id = jours.agence_id
                  AND e.date_jour = jours.jour
                  AND e.statut IN ('soumis', 'consolide')
            WHERE jours.agence_id = :agence
              AND e.id IS NULL
            ORDER BY jours.jour
            LIMIT 1
        ");

        $stmt->execute([
            'debut' => self::DEBUT_OBLIGATION . ' 00:00:00',
            'aujourdhui' => $aujourdhui,
            'agence' => $agenceId,
        ]);

        $ligne = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($ligne === false) {
            return null;
        }

        return [
            'date' => (string) $ligne['jour'],
            'montant' => (float) $ligne['total'],
            'operations' => (int) $ligne['nb'],
        ];
    }

    /**
     * L'utilisateur courant est-il empêché de facturer et d'encaisser ?
     *
     * @return array{date:string, montant:float, operations:int}|null
     */
    public function blocageCourant(): ?array
    {
        if ($this->estExempte()) {
            return null;
        }

        return $this->journeeBloquante(Auth::agenceId());
    }

    public function estExempte(): bool
    {
        return Auth::isAdmin()
            || Auth::isAssistantDg()
            || Auth::hasAnyRole(self::ROLES_EXEMPTES);
    }

    /**
     * Le message affiché à l'agence, en une phrase et une action.
     *
     * @param array{date:string, montant:float, operations:int} $blocage
     */
    public static function message(array $blocage): string
    {
        $date = date_create($blocage['date']);

        $message = 'Caisse fermée : le point du '
            . ($date === false ? $blocage['date'] : $date->format('d/m/Y'))
            . ' n\'a pas été soumis. '
            . number_format($blocage['montant'], 0, ',', ' ')
            . ' FCFA ont été encaissés ce jour-là et n\'ont jamais été comptés. ';

        // Dire « soumettez » à quelqu'un qui n'en a pas le droit l'enfermerait
        // sans recours : on lui dit alors qui peut le faire.
        return $message . (self::peutSoumettre()
            ? 'Comptez votre caisse et soumettez ce point pour la rouvrir.'
            : 'Demandez à la caissière ou à l\'agent de saisie de votre agence de soumettre ce point pour rouvrir la caisse.');
    }

    /**
     * L'utilisateur courant peut-il soumettre le point lui-même ?
     *
     * Le comptage porte sur la caisse entière de l'agence : il n'est ouvert
     * qu'à ceux qui la voient entière. La gestionnaire de caisse, qui ne voit
     * que ses propres opérations, reste bloquée comme les autres — c'est la
     * caisse de l'agence qui est fermée — mais elle doit savoir à qui
     * s'adresser plutôt que de rester devant un mur.
     */
    public static function peutSoumettre(): bool
    {
        return Auth::isAdmin()
            || Auth::hasAnyRole(\App\Controllers\Finance\FinanceController::ROLES_SOUMISSION_POINT);
    }
}
