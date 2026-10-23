<?php

declare(strict_types=1);

namespace App\Services\Messagerie;

use App\Helpers\View;
use App\Models\Database;
use App\Repositories\Messagerie\NotificationsMessagerieDepotInterface;
use App\Repositories\Messagerie\NotificationsMessagerieRepository;
use App\Services\Mobile\NotificationsPush;
use App\Services\Noel\NoelCompte;
use Closure;
use DateTimeImmutable;
use Throwable;

/**
 * Qui prévenir d'un nouveau message, et quoi lui dire (lot 4).
 *
 * Appelé après la réponse HTTP de celui qui écrit (SuitesEnvoi) : rien ici
 * ne le ralentit. Les notifications partent par NotificationsPush, vers tous
 * les appareils abonnés de chaque destinataire.
 *
 * Ne sont jamais prévenus : l'auteur, un compte désactivé, NOEL, un membre
 * qui a quitté la discussion (il n'est plus parmi les membres actifs), ni
 * celui qui a déjà lu le message.
 *
 * Pas de bombardement :
 *  - une discussion en sourdine ne notifie pas, sauf si le message
 *    mentionne le membre (« @Kouakou », MessagerieMentions) ;
 *  - dans un groupe, au plus une notification par minute et par discussion
 *    pour une même personne (msg_membres.dernier_push_at). La suivante porte
 *    le tag de la discussion : elle remplace la précédente à l'écran et dit
 *    « 3 nouveaux messages ». Une mention passe outre cette limite.
 *
 * Discrétion : avec la préférence « Masquer l'aperçu », la notification dit
 * « Nouveau message de Kouakou » sans rien du contenu.
 *
 * Si l'utilisateur a déjà le portail sous les yeux, c'est le service worker
 * qui se tait : le serveur ne sait pas ce qui est affiché.
 */
final class NotificationsMessagerie
{
    /** Dans un groupe, une notification par minute et par discussion au plus. */
    public const INTERVALLE_GROUPE_SECONDES = 60;

    /** Un message de discussion vieux de plus de 12 heures n'a plus à sonner. */
    public const DUREE_DE_VIE_SECONDES = 43200;

    public const APERCU_MAX = 120;

    private const FORMAT_DATE = 'Y-m-d H:i:s';

    private Closure $horloge;

    public function __construct(
        private NotificationsMessagerieDepotInterface $depot,
        private NotificationsPush $push,
        private ?int $noelId,
        private string $urlMessagerie,
        ?Closure $horloge = null,
    ) {
        $this->horloge = $horloge ?? static fn(): DateTimeImmutable => new DateTimeImmutable();
    }

    /**
     * Branché sur la base et le service de push réels.
     *
     * $urlMessagerie : l'adresse de la messagerie, pour une commande de
     * console qui n'a pas de requête d'où la déduire (« https://…/messagerie ») ;
     * sur le serveur web, celle de la requête.
     */
    public static function parDefaut(?string $urlMessagerie = null): self
    {
        $pdo = Database::getConnection();

        $noel = null;
        try {
            $noel = NoelCompte::id($pdo);
        } catch (Throwable $e) {
            error_log('[Messagerie notifications] compte NOEL illisible : ' . $e->getMessage());
        }

        return new self(
            new NotificationsMessagerieRepository($pdo),
            NotificationsPush::creer($pdo),
            $noel,
            $urlMessagerie ?? View::url('messagerie'),
        );
    }

    /**
     * Un message vient d'être enregistré : prévient qui doit l'être.
     *
     * @return int le nombre de personnes atteintes sur au moins un appareil
     */
    public function surMessage(int $messageId): int
    {
        try {
            return $this->notifier($messageId);
        } catch (Throwable $e) {
            error_log('[Messagerie notifications] message ' . $messageId . ' : ' . $e->getMessage());

            return 0;
        }
    }

    private function notifier(int $messageId): int
    {
        $message = $this->depot->messageANotifier($messageId);
        if ($message === null || $message['supprime_at'] !== null) {
            return 0;
        }

        $conversationId = (int) $message['conversation_id'];
        $auteurId = (int) $message['auteur_id'];
        $groupe = (string) $message['conversation_type'] !== 'direct';
        $maintenant = ($this->horloge)();
        $seuil = $maintenant->modify('-' . self::INTERVALLE_GROUPE_SECONDES . ' seconds')->format(self::FORMAT_DATE);

        $charges = [];
        foreach ($this->depot->destinataires($conversationId, $messageId, $auteurId) as $d) {
            $userId = (int) $d['user_id'];
            if ($userId === $auteurId || $userId === $this->noelId || !$d['actif'] || (int) $d['non_lus'] <= 0) {
                continue;
            }

            $mention = MessagerieMentions::mentionne((string) $message['texte'], (string) $d['nom']);
            if ($d['sourdine'] && !$mention) {
                continue;
            }
            if ($groupe && !$mention && $d['dernier_push_at'] !== null && (string) $d['dernier_push_at'] > $seuil) {
                continue;
            }

            $charges[$userId] = $this->charge($message, $d, $mention);
        }

        if ($charges === []) {
            return 0;
        }

        $atteints = array_keys(array_filter(
            $this->push->envoyerChacun($charges, self::DUREE_DE_VIE_SECONDES),
            static fn(int $n): bool => $n > 0
        ));

        if ($atteints !== []) {
            $this->depot->marquerNotifies($conversationId, $atteints, $maintenant->format(self::FORMAT_DATE));
        }

        return count($atteints);
    }

    /**
     * La notification d'un destinataire : titre, corps, adresse du message,
     * et le tag de la discussion (la suivante remplace celle-ci à l'écran).
     *
     * @param array<string, mixed> $message ligne de messageANotifier
     * @param array<string, mixed> $destinataire ligne de destinataires
     * @return array{type: string, titre: string, corps: string, url: string, tag: string, conversation_id: int, message_id: int}
     */
    public function charge(array $message, array $destinataire, bool $mention = false): array
    {
        $conversationId = (int) $message['conversation_id'];
        $messageId = (int) $message['id'];
        $auteur = trim((string) ($message['auteur_nom'] ?? '')) ?: 'Un collègue';
        $groupe = (string) $message['conversation_type'] !== 'direct';
        $nomGroupe = trim((string) ($message['conversation_nom'] ?? '')) ?: 'Groupe';
        $nonLus = max(1, (int) ($destinataire['non_lus'] ?? 1));
        $plusieurs = $nonLus > 1 ? $nonLus . ' nouveaux messages' : '';

        if (!empty($destinataire['apercu_masque'])) {
            $titre = $mention ? $auteur . ' vous a mentionné' : 'Nouveau message de ' . $auteur;
            $corps = implode(' · ', array_filter([$groupe ? 'Dans « ' . $nomGroupe . ' »' : '', $plusieurs]));
            if ($corps === '') {
                $corps = 'Aperçu masqué : ouvrez la messagerie pour le lire.';
            }
        } else {
            $apercu = self::apercu($message);
            if ($groupe) {
                $titre = $nomGroupe;
                $ligne = $auteur . ($mention ? ' vous a mentionné' : '') . ' : ' . $apercu;
            } else {
                $titre = $auteur . ($mention ? ' vous a mentionné' : '');
                $ligne = $apercu;
            }
            $corps = $plusieurs !== '' ? $plusieurs . ' · ' . $ligne : $ligne;
        }

        return [
            'type' => 'messagerie',
            'titre' => $titre,
            'corps' => $corps,
            'url' => $this->urlMessagerie . '#c=' . $conversationId . '&m=' . $messageId,
            'tag' => 'msg-c-' . $conversationId,
            'conversation_id' => $conversationId,
            'message_id' => $messageId,
        ];
    }

    /**
     * Le début du message, ou ce qu'il contient quand il n'a pas de texte :
     * « Photo », « Document : devis.pdf ». Un transfert le dit.
     *
     * @param array<string, mixed> $message
     */
    public static function apercu(array $message): string
    {
        $texte = trim((string) preg_replace('/\s+/u', ' ', (string) ($message['texte'] ?? '')));

        if ($texte === '') {
            $piece = $message['piece'] ?? null;
            if (!is_array($piece)) {
                $texte = 'Pièce jointe';
            } elseif ((string) $piece['categorie'] === 'image') {
                $texte = 'Photo';
            } else {
                $texte = 'Document : ' . MessagerieFormats::nomAvecExtension((string) $piece['nom_origine'], (string) $piece['extension']);
            }
        }

        if (!empty($message['transfere_de_id'])) {
            $texte = 'Transféré : ' . $texte;
        }

        return mb_strlen($texte) > self::APERCU_MAX ? mb_substr($texte, 0, self::APERCU_MAX - 1) . '…' : $texte;
    }
}
