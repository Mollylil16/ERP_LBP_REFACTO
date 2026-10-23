<?php

declare(strict_types=1);

namespace App\Services\Colisage;

use App\Repositories\Colisage\PointageColisRepository;
use Closure;
use DateTimeImmutable;
use Throwable;

/**
 * Le résumé de la réception, chaque soir, dans le groupe automatique de
 * l'agence d'envoi (lot 5 de la messagerie : « auto:agence:ID »).
 *
 * « Paris a reçu 500 colis sur 1 000 du départ n° … : 480 reçus en bon état,
 * 20 endommagés, 300 déclarés non reçus, 200 encore en attente. Rapport : … »
 *
 * Un message par départ qui a eu des pointages à l'arrivée dans la journée,
 * ou dont le délai des manquants (24 h après l'arrivée) vient d'échoir. La
 * journée va d'un résumé au suivant : de 18 h la veille à 18 h, heure
 * d'Abidjan — l'heure du lancement par la crontab. Un pointage fait à
 * 18 h 30 entre dans le résumé du lendemain ; aucun ne passe entre deux.
 *
 * Idempotent : un départ n'a qu'un résumé par jour
 * (lbp_colis_resumes_reception, unique sur départ et jour). Relancée, la
 * commande ne renvoie rien ; un message qui n'a pas pu partir rend sa place,
 * et le lancement suivant réessaie.
 *
 * Les comptes sont ceux du moment où le résumé part. Le message lui-même est
 * publié par $publier, qui sait où est le groupe et qui écrit (NOEL).
 */
final class ResumeReceptionService
{
    /** L'heure du résumé, heure du serveur (Abidjan). La crontab doit dire la même. */
    public const HEURE = '18:00:00';

    private Closure $horloge;

    /**
     * @param Closure(int, string): ?array{conversation_id:int, message_id:int} $publier
     *        l'agence d'envoi et le texte ; null si l'agence n'a pas de groupe
     */
    public function __construct(
        private PointageColisRepository $repo,
        private PointageColisService $pointage,
        private Closure $publier,
        ?Closure $horloge = null,
    ) {
        $this->horloge = $horloge ?? fn (): DateTimeImmutable => $this->pointage->maintenant();
    }

    /**
     * La journée qui se termine le $jour à 18 h.
     *
     * @return array{0:string, 1:string}
     */
    public static function fenetre(string $jour): array
    {
        $fin = new DateTimeImmutable($jour . ' ' . self::HEURE);

        return [$fin->modify('-1 day')->format('Y-m-d H:i:s'), $fin->format('Y-m-d H:i:s')];
    }

    /**
     * @return array{jour:string, du:string, au:string, departs:int, envoyes:int, deja_faits:int, sans_groupe:int, erreurs:int, messages:array<int, array{depart:string, texte:string, statut:string}>}
     */
    public function executer(string $jour, string $urlBase, bool $essai = false): array
    {
        [$du, $au] = self::fenetre($jour);
        $maintenant = ($this->horloge)();

        // Délai échu dans la fenêtre, et déjà échu à l'heure qu'il est.
        $finEcheance = min(new DateTimeImmutable($au), $maintenant);
        $delai = PointageColisService::DELAI_MANQUANT_HEURES;
        $premiereDu = (new DateTimeImmutable($du))->modify("-{$delai} hours")->format('Y-m-d H:i:s');
        $premiereAu = $finEcheance->modify("-{$delai} hours")->format('Y-m-d H:i:s');

        $bilan = ['jour' => $jour, 'du' => $du, 'au' => $au, 'departs' => 0, 'envoyes' => 0, 'deja_faits' => 0, 'sans_groupe' => 0, 'erreurs' => 0, 'messages' => []];

        foreach ($this->repo->departsAResumer($du, $au, $premiereDu, $premiereAu) as $ligne) {
            $depart = $this->pointage->enrichir($ligne, $maintenant);
            if ((int) $depart['envoyes'] === 0 || empty($depart['agence_depart_id'])) {
                continue;
            }

            $bilan['departs']++;
            $texte = self::texte($depart, self::lien($urlBase, $depart, $jour));
            $reference = (string) $depart['reference'];

            if ($essai) {
                $bilan['messages'][] = ['depart' => $reference, 'texte' => $texte, 'statut' => $this->repo->resumeExiste((int) $depart['id'], $jour) ? 'deja_fait' : 'a_envoyer'];
                continue;
            }

            $reservation = $this->repo->reserverResume((int) $depart['id'], $jour);
            if ($reservation === null) {
                $bilan['deja_faits']++;
                $bilan['messages'][] = ['depart' => $reference, 'texte' => $texte, 'statut' => 'deja_fait'];
                continue;
            }

            try {
                $publie = ($this->publier)((int) $depart['agence_depart_id'], $texte);
            } catch (Throwable $e) {
                error_log('[Résumé réception] départ ' . $reference . ' : ' . $e->getMessage());
                $publie = false;
            }

            if (is_array($publie)) {
                $this->repo->confirmerResume($reservation, (int) $publie['conversation_id'], (int) $publie['message_id']);
                $bilan['envoyes']++;
                $statut = 'envoye';
            } else {
                $this->repo->libererResume($reservation);
                $publie === null ? $bilan['sans_groupe']++ : $bilan['erreurs']++;
                $statut = $publie === null ? 'sans_groupe' : 'erreur';
            }

            $bilan['messages'][] = ['depart' => $reference, 'texte' => $texte, 'statut' => $statut];
        }

        return $bilan;
    }

    /**
     * Le message d'un départ enrichi.
     *
     * @param array<string, mixed> $depart
     */
    public static function texte(array $depart, string $lien): string
    {
        $nombre = static fn (int $n): string => number_format($n, 0, ',', "\u{202F}");
        $arrivee = trim((string) ($depart['agence_arrivee'] ?? '')) ?: "L'agence d'arrivée";

        $texte = $arrivee . ' a reçu ' . $nombre((int) $depart['recus']) . ' colis sur ' . $nombre((int) $depart['envoyes'])
            . ' du départ n° ' . (string) $depart['reference'];

        $details = [];
        if ((int) $depart['recus'] > 0) {
            $details[] = $nombre((int) $depart['recus_intacts']) . ' reçu' . ((int) $depart['recus_intacts'] > 1 ? 's' : '') . ' en bon état';
        }
        if ((int) $depart['endommages'] > 0) {
            $details[] = $nombre((int) $depart['endommages']) . ' endommagé' . ((int) $depart['endommages'] > 1 ? 's' : '');
        }
        if ((int) $depart['non_recus'] > 0) {
            $details[] = $nombre((int) $depart['non_recus']) . ' déclaré' . ((int) $depart['non_recus'] > 1 ? 's' : '') . ' non reçu' . ((int) $depart['non_recus'] > 1 ? 's' : '');
        }
        if ((int) $depart['en_attente'] > 0) {
            $details[] = $nombre((int) $depart['en_attente']) . ' encore en attente';
        }

        $texte .= ($details !== [] ? ' : ' . implode(', ', $details) : '') . '.';
        $texte .= ' Taux de réception : ' . number_format((float) $depart['taux'], 1, ',', '') . ' %.';

        if ((int) $depart['manquants'] > 0) {
            $texte .= ' ' . $nombre((int) $depart['manquants']) . ' colis manquant' . ((int) $depart['manquants'] > 1 ? 's' : '');
            $texte .= !empty($depart['delai_depasse']) && (int) $depart['en_attente'] > 0
                ? ' : les colis en attente sont comptés manquants 24 h après l’arrivée.'
                : '.';
        }

        return $texte . ' Rapport : ' . $lien;
    }

    /** Le rapport du départ, ouvert sur la journée du résumé. */
    public static function lien(string $urlBase, array $depart, string $jour): string
    {
        return rtrim($urlBase, '/') . '/colisage/rapport-reception?' . http_build_query([
            'periode' => 'jour',
            'date' => $jour,
            'depart' => (int) $depart['id'],
        ]);
    }
}
