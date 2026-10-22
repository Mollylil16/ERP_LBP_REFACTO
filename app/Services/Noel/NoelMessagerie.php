<?php

declare(strict_types=1);

namespace App\Services\Noel;

use App\Repositories\Messagerie\MessagerieDepotInterface;
use App\Security\NoelAcces;
use Throwable;

/**
 * NOEL dans la messagerie : il repond quand c'est a lui qu'on ecrit.
 *
 * Il n'a pas d'ecran a lui, et c'est voulu. Deux messageries dans le meme
 * logiciel obligeraient les agents a en apprendre deux et a se demander
 * chaque fois laquelle ouvrir. NOEL est donc un contact comme un autre : on
 * lui ecrit, il repond dans le meme fil.
 *
 * TOUT LE BRANCHEMENT TIENT EN UN APPEL, place apres l'enregistrement d'un
 * message. Volontairement : le fichier qui envoie les messages appartient a la
 * messagerie, pas a NOEL, et plus on y touche, plus on risque de gener ceux
 * qui le font evoluer.
 *
 * Cette classe ne leve jamais d'exception. Si NOEL ne sait pas repondre, ou si
 * quoi que ce soit echoue de son cote, le message de la personne a deja ete
 * enregistre et part normalement : un assistant en panne ne doit jamais casser
 * la messagerie de l'entreprise.
 */
final class NoelMessagerie
{
    /**
     * Un message vient d'etre enregistre : NOEL doit-il repondre ?
     *
     * @param int $conversationId la discussion qui vient de recevoir le message
     * @param int $auteurId       qui l'a ecrit
     * @param string $texte       ce qui a ete ecrit
     */
    public static function surMessage(
        int $conversationId,
        int $auteurId,
        string $texte,
        MessagerieDepotInterface $depot
    ): void {
        try {
            if (!self::doitRepondre($conversationId, $auteurId, $depot)) {
                return;
            }

            $reponse = (new NoelRepondeur())->repondre($texte);

            $depot->ajouterMessage([
                'conversation_id' => $conversationId,
                'auteur_id' => (int) NoelCompte::id(),
                'texte' => NoelRepondeur::enMessage($reponse),
                'reponse_a_id' => null,
                'transfere_de_id' => null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $e) {
            /*
             * Le message de la personne est deja enregistre. Un assistant en
             * panne ne doit jamais casser la messagerie : on note et on se
             * tait.
             */
            error_log('[NOEL messagerie] reponse impossible : ' . $e->getMessage());
        }
    }

    /**
     * NOEL ne parle que dans SA discussion, et jamais apres lui-meme.
     */
    private static function doitRepondre(
        int $conversationId,
        int $auteurId,
        MessagerieDepotInterface $depot
    ): bool {
        if (!NoelAcces::estAllume()) {
            return false;
        }

        $noel = NoelCompte::id();

        if ($noel === null || $auteurId === $noel) {
            // Se repondre a soi-meme ouvrirait une boucle sans fin.
            return false;
        }

        $conversation = $depot->conversation($conversationId);

        if ($conversation === null || (string) ($conversation['type'] ?? '') !== 'direct') {
            /*
             * Jamais dans un groupe. NOEL qui s'invite dans une discussion
             * entre collegues serait au mieux penible, au pire indiscret — et
             * il n'aurait aucun moyen de savoir a qui il repond.
             */
            return false;
        }

        $membre = $depot->membre($conversationId, $noel);

        return $membre !== null && ($membre['quitte_at'] ?? null) === null;
    }

    /**
     * Ouvrir la discussion avec NOEL pour quelqu'un, ou retrouver la sienne.
     *
     * La cle directe reprend exactement la convention de la messagerie —
     * « petit-grand » des deux identifiants — sans quoi deux discussions
     * paralleles pourraient exister pour la meme paire.
     */
    public static function conversationAvec(int $utilisateurId, MessagerieDepotInterface $depot): ?int
    {
        $noel = NoelCompte::id();

        if ($noel === null || $utilisateurId === $noel) {
            return null;
        }

        try {
            $cle = min($noel, $utilisateurId) . '-' . max($noel, $utilisateurId);
            $existante = $depot->conversationDirecte($cle);
            $maintenant = date('Y-m-d H:i:s');

            if ($existante === null) {
                $existante = $depot->creerConversation([
                    'type' => 'direct',
                    'nom' => null,
                    'description' => null,
                    'cle_directe' => $cle,
                    'createur_id' => $utilisateurId,
                    'created_at' => $maintenant,
                ]);
            }

            foreach ([$utilisateurId, $noel] as $membreId) {
                $membre = $depot->membre($existante, $membreId);

                if ($membre === null || ($membre['quitte_at'] ?? null) !== null) {
                    $depot->ajouterMembre($existante, $membreId, 'membre', $maintenant);
                }
            }

            return $existante;
        } catch (Throwable $e) {
            error_log('[NOEL messagerie] ouverture impossible : ' . $e->getMessage());

            return null;
        }
    }

    /**
     * L'appel a poser dans la messagerie, ecrit ici pour etre recopie tel quel.
     *
     * Une seule ligne, apres l'enregistrement du message dans
     * MessagerieService::envoyer(). Rien d'autre n'est a modifier.
     */
    public const ACCROCHE = <<<'TXT'
    \App\Services\Noel\NoelMessagerie::surMessage($conversationId, $this->utilisateurId, $texte, $this->depot);
    TXT;
}
