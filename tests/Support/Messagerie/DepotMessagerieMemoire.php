<?php

declare(strict_types=1);

namespace Tests\Support\Messagerie;

use App\Repositories\Messagerie\MessagerieDepotInterface;

/**
 * Doublure en mémoire des tables msg_*, fidèle au contrat de
 * MessagerieDepotInterface : elle permet d'éprouver les règles du service
 * sans base de données.
 */
final class DepotMessagerieMemoire implements MessagerieDepotInterface
{
    /** @var array<int, array<string, mixed>> */
    public array $utilisateurs = [];
    /** @var array<int, array<string, mixed>> */
    public array $conversations = [];
    /** @var array<string, array<string, mixed>> clé « conv-user » */
    public array $membres = [];
    /** @var array<int, array<string, mixed>> */
    public array $messages = [];
    /** @var array<int, array<string, mixed>> */
    public array $journal = [];
    /** @var array<int, array<string, mixed>> */
    public array $epingles = [];
    /** @var array<int, array<string, mixed>> */
    public array $presence = [];
    /** @var array<int, array<string, mixed>> */
    public array $accesDirection = [];
    /** @var array<int, array<string, mixed>> */
    public array $fichiers = [];
    /** @var array<int, array<string, mixed>> */
    public array $pieces = [];
    /** @var array<string, array<string, mixed>> clé « user-pièce » */
    public array $favoris = [];

    private int $prochainId = 1;

    public function ajouterUtilisateur(int $id, string $nom, bool $actif = true): void
    {
        $this->utilisateurs[$id] = ['id' => $id, 'nom' => $nom, 'actif' => $actif, 'agence' => 'Abidjan', 'fonction' => null];
    }

    public function utilisateur(int $id): ?array
    {
        return $this->utilisateurs[$id] ?? null;
    }

    public function annuaire(int $saufId, string $recherche, int $limite): array
    {
        $r = array_filter($this->utilisateurs, static fn(array $u): bool => $u['actif'] && $u['id'] !== $saufId
            && ($recherche === '' || stripos($u['nom'], $recherche) !== false));

        return array_slice(array_values($r), 0, $limite);
    }

    public function idsActifs(array $ids): array
    {
        return array_values(array_filter($ids, fn(int $id): bool => ($this->utilisateurs[$id]['actif'] ?? false) === true));
    }

    public function conversation(int $id): ?array
    {
        return $this->conversations[$id] ?? null;
    }

    public function conversationDirecte(string $cle): ?int
    {
        foreach ($this->conversations as $c) {
            if ($c['cle_directe'] === $cle) {
                return $c['id'];
            }
        }

        return null;
    }

    public function creerConversation(array $donnees): int
    {
        $id = $this->prochainId++;
        $this->conversations[$id] = $donnees + [
            'id' => $id, 'photo' => null, 'agence_id' => null, 'automatique' => 0,
            'dernier_message_at' => null, 'maj_at' => $donnees['created_at'],
        ];
        $this->conversations[$id]['id'] = $id;

        return $id;
    }

    public function modifierConversation(int $id, array $champs, string $maintenant): void
    {
        foreach (['nom', 'description', 'type', 'photo'] as $c) {
            if (array_key_exists($c, $champs)) {
                $this->conversations[$id][$c] = $champs[$c];
            }
        }
        $this->conversations[$id]['maj_at'] = $maintenant;
    }

    public function membre(int $conversationId, int $userId): ?array
    {
        return $this->membres["$conversationId-$userId"] ?? null;
    }

    public function membresActifs(int $conversationId): array
    {
        $r = [];
        foreach ($this->membres as $m) {
            if ($m['conversation_id'] === $conversationId && $m['quitte_at'] === null) {
                $r[] = $m + ['nom' => $this->utilisateurs[$m['user_id']]['nom'], 'actif' => $this->utilisateurs[$m['user_id']]['actif']];
            }
        }

        return $r;
    }

    public function ajouterMembre(int $conversationId, int $userId, string $role, string $maintenant): void
    {
        $this->membres["$conversationId-$userId"] = [
            'conversation_id' => $conversationId, 'user_id' => $userId, 'role' => $role,
            'rejoint_at' => $maintenant, 'quitte_at' => null,
            'dernier_message_lu_id' => $this->maxId($conversationId), 'lu_at' => $maintenant,
            'sourdine' => 0, 'archivee' => 0,
        ];
    }

    public function retirerMembre(int $conversationId, int $userId, string $maintenant): void
    {
        $this->membres["$conversationId-$userId"]['quitte_at'] = $maintenant;
        $this->membres["$conversationId-$userId"]['role'] = 'membre';
    }

    public function changerRole(int $conversationId, int $userId, string $role): void
    {
        $this->membres["$conversationId-$userId"]['role'] = $role;
    }

    public function reglerMembre(int $conversationId, int $userId, string $reglage, bool $valeur): void
    {
        $this->membres["$conversationId-$userId"][$reglage] = $valeur ? 1 : 0;
    }

    public function conversationsDe(int $userId): array
    {
        $r = [];
        foreach ($this->membres as $m) {
            if ($m['user_id'] === $userId && $m['quitte_at'] === null) {
                $r[] = $this->conversations[$m['conversation_id']] + [
                    'role' => $m['role'], 'sourdine' => $m['sourdine'], 'archivee' => $m['archivee'],
                    'dernier_message_lu_id' => $m['dernier_message_lu_id'],
                ];
            }
        }

        return $r;
    }

    public function derniersMessages(array $conversationIds): array
    {
        $r = [];
        foreach ($this->messages as $m) {
            if (in_array($m['conversation_id'], $conversationIds, true)) {
                $r[$m['conversation_id']] = $this->avecAuteur($m);
            }
        }

        return $r;
    }

    public function correspondantsDirects(array $conversationIds, int $userId): array
    {
        $r = [];
        foreach ($this->membres as $m) {
            $c = $this->conversations[$m['conversation_id']];
            if ($c['type'] === 'direct' && in_array($c['id'], $conversationIds, true) && $m['user_id'] !== $userId) {
                $r[$c['id']] = $this->utilisateurs[$m['user_id']];
            }
        }

        return $r;
    }

    public function nonLus(int $userId): array
    {
        $r = [];
        foreach ($this->membres as $mb) {
            if ($mb['user_id'] !== $userId || $mb['quitte_at'] !== null) {
                continue;
            }
            foreach ($this->messages as $m) {
                if ($m['conversation_id'] === $mb['conversation_id'] && $m['id'] > $mb['dernier_message_lu_id']
                    && $m['auteur_id'] !== $userId && $m['supprime_at'] === null) {
                    $r[$m['conversation_id']] = ($r[$m['conversation_id']] ?? 0) + 1;
                }
            }
        }

        return $r;
    }

    public function message(int $id): ?array
    {
        return isset($this->messages[$id]) ? $this->avecAuteur($this->messages[$id]) : null;
    }

    public function messagesParIds(array $ids): array
    {
        $r = [];
        foreach ($ids as $id) {
            if (isset($this->messages[$id])) {
                $r[$id] = $this->avecAuteur($this->messages[$id]);
            }
        }

        return $r;
    }

    public function messages(int $conversationId, int $avant, int $limite): array
    {
        $r = array_filter($this->messages, static fn(array $m): bool => $m['conversation_id'] === $conversationId
            && ($avant === 0 || $m['id'] < $avant));
        ksort($r);

        return array_map(fn(array $m): array => $this->avecAuteur($m), array_slice(array_values($r), -$limite));
    }

    public function compterEnvoisDepuis(int $userId, string $depuis): int
    {
        return count(array_filter($this->messages, static fn(array $m): bool => $m['auteur_id'] === $userId && $m['created_at'] >= $depuis));
    }

    public function ajouterMessage(array $donnees, array $pieces = []): int
    {
        $id = $this->prochainId++;
        $this->messages[$id] = $donnees + ['id' => $id, 'modifie_at' => null, 'supprime_at' => null, 'supprime_par' => null, 'maj_at' => null];
        $this->messages[$id]['id'] = $id;
        foreach ($pieces as $p) {
            $pieceId = $this->prochainId++;
            $this->pieces[$pieceId] = $p + ['id' => $pieceId, 'message_id' => $id, 'created_at' => $donnees['created_at']];
        }
        $this->conversations[$donnees['conversation_id']]['dernier_message_at'] = $donnees['created_at'];
        $cle = $donnees['conversation_id'] . '-' . $donnees['auteur_id'];
        if (isset($this->membres[$cle])) {
            $this->membres[$cle]['dernier_message_lu_id'] = $id;
        }

        return $id;
    }

    public function modifierMessage(int $id, string $nouveauTexte, string $ancienTexte, int $parId, string $maintenant): void
    {
        $this->journal[] = ['message_id' => $id, 'action' => 'modification', 'ancien_texte' => $ancienTexte, 'auteur_id' => $parId];
        $this->messages[$id]['texte'] = $nouveauTexte;
        $this->messages[$id]['modifie_at'] = $maintenant;
        $this->messages[$id]['maj_at'] = $maintenant;
        foreach ($this->pieces as $pieceId => $p) {
            if ($p['message_id'] === $id) {
                $this->pieces[$pieceId]['legende'] = $nouveauTexte === '' ? null : $nouveauTexte;
            }
        }
    }

    public function supprimerMessage(int $id, string $ancienTexte, int $parId, string $maintenant): void
    {
        $this->journal[] = ['message_id' => $id, 'action' => 'suppression', 'ancien_texte' => $ancienTexte, 'auteur_id' => $parId];
        $this->messages[$id]['supprime_at'] = $maintenant;
        $this->messages[$id]['supprime_par'] = $parId;
        $this->messages[$id]['maj_at'] = $maintenant;
        $this->epingles = array_values(array_filter($this->epingles, static fn(array $e): bool => $e['message_id'] !== $id));
    }

    public function marquerLu(int $conversationId, int $userId, int $jusquA, string $maintenant): void
    {
        $cle = "$conversationId-$userId";
        $borne = min($jusquA, $this->maxId($conversationId));
        if (isset($this->membres[$cle]) && $this->membres[$cle]['dernier_message_lu_id'] < $borne) {
            $this->membres[$cle]['dernier_message_lu_id'] = $borne;
            $this->membres[$cle]['lu_at'] = $maintenant;
        }
    }

    public function luPar(int $conversationId, int $messageId, int $auteurId): array
    {
        return array_values(array_filter(
            $this->membresActifs($conversationId),
            static fn(array $m): bool => $m['user_id'] !== $auteurId && $m['dernier_message_lu_id'] >= $messageId
        ));
    }

    public function rechercher(array $conversationIds, array $mots, bool $pleinTexte, int $limite): array
    {
        $r = [];
        foreach (array_reverse($this->messages, true) as $m) {
            if (!in_array($m['conversation_id'], $conversationIds, true) || $m['supprime_at'] !== null) {
                continue;
            }
            $tous = true;
            foreach ($mots as $mot) {
                $tous = $tous && mb_stripos($m['texte'], $mot) !== false;
            }
            if ($tous) {
                $r[] = $this->avecAuteur($m);
            }
        }

        return array_slice($r, 0, $limite);
    }

    public function epingles(int $conversationId): array
    {
        $r = [];
        foreach ($this->epingles as $e) {
            if ($e['conversation_id'] === $conversationId) {
                $r[] = $this->avecAuteur($this->messages[$e['message_id']]);
            }
        }

        return $r;
    }

    public function epingler(int $conversationId, int $messageId, int $parId, string $maintenant): void
    {
        $this->epingles[] = ['conversation_id' => $conversationId, 'message_id' => $messageId];
    }

    public function desepingler(int $conversationId, int $messageId): void
    {
        $this->epingles = array_values(array_filter(
            $this->epingles,
            static fn(array $e): bool => !($e['conversation_id'] === $conversationId && $e['message_id'] === $messageId)
        ));
    }

    public function fichierParEmpreinte(string $empreinte): ?array
    {
        foreach ($this->fichiers as $f) {
            if ($f['empreinte'] === $empreinte) {
                return $f;
            }
        }

        return null;
    }

    public function enregistrerFichier(array $donnees): array
    {
        $existant = $this->fichierParEmpreinte((string) $donnees['empreinte']);
        if ($existant !== null) {
            return $existant;
        }

        $id = $this->prochainId++;
        $this->fichiers[$id] = ['id' => $id] + $donnees;

        return $this->fichiers[$id];
    }

    public function piecesDesMessages(array $messageIds, int $userId): array
    {
        $r = [];
        foreach ($this->pieces as $p) {
            if (in_array($p['message_id'], $messageIds, true)) {
                $r[$p['message_id']][] = $this->pieceComplete($p, $userId);
            }
        }

        return $r;
    }

    public function piece(int $id): ?array
    {
        if (!isset($this->pieces[$id])) {
            return null;
        }
        $p = $this->pieces[$id];
        $m = $this->messages[$p['message_id']];

        return $this->pieceComplete($p, 0) + [
            'chemin' => $this->fichiers[$p['fichier_id']]['chemin'],
            'conversation_id' => $m['conversation_id'],
            'supprime_at' => $m['supprime_at'],
        ];
    }

    public function marquerFavori(int $userId, int $pieceId, bool $actif, string $maintenant): void
    {
        if ($actif) {
            $this->favoris["$userId-$pieceId"] = ['user_id' => $userId, 'piece_id' => $pieceId, 'created_at' => $maintenant];
        } else {
            unset($this->favoris["$userId-$pieceId"]);
        }
    }

    public function documents(int $userId, array $filtres, int $limite, int $decalage): array
    {
        $mes = array_map(static fn(array $c): int => $c['id'], $this->conversationsDe($userId));
        $r = [];
        foreach ($this->pieces as $p) {
            $m = $this->messages[$p['message_id']];
            $l = $this->pieceComplete($p, $userId) + [
                'conversation_id' => $m['conversation_id'],
                'conversation_type' => $this->conversations[$m['conversation_id']]['type'],
                'conversation_nom' => $this->conversations[$m['conversation_id']]['nom'],
                'auteur_id' => $m['auteur_id'],
                'auteur_nom' => $this->utilisateurs[$m['auteur_id']]['nom'] ?? '',
            ];
            $q = (string) ($filtres['q'] ?? '');
            $garde = in_array($m['conversation_id'], $mes, true)
                && $m['supprime_at'] === null
                && (empty($filtres['conversation_id']) || $m['conversation_id'] === $filtres['conversation_id'])
                && (empty($filtres['expediteur_id']) || $m['auteur_id'] === $filtres['expediteur_id'])
                && (empty($filtres['categorie']) || $l['categorie'] === $filtres['categorie'])
                && (empty($filtres['du']) || $l['created_at'] >= $filtres['du'])
                && (empty($filtres['avant']) || $l['created_at'] < $filtres['avant'])
                && (empty($filtres['favoris']) || $l['favori'])
                && ($q === '' || mb_stripos($l['nom_origine'] . "\n" . ($l['legende'] ?? ''), $q) !== false);
            if ($garde) {
                $r[] = $l;
            }
        }

        $cle = match ($filtres['tri'] ?? 'date') {
            'nom' => static fn(array $l): array => [mb_strtolower($l['nom_origine'])],
            'taille' => static fn(array $l): array => [$l['taille']],
            'expediteur' => static fn(array $l): array => [$l['auteur_nom']],
            'type' => static fn(array $l): array => [$l['categorie'], $l['extension']],
            default => static fn(array $l): array => [$l['created_at']],
        };
        $sens = ($filtres['ordre'] ?? 'desc') === 'asc' ? 1 : -1;
        usort($r, static fn(array $a, array $b): int => $sens * ([...$cle($a), $a['id']] <=> [...$cle($b), $b['id']]));

        return ['lignes' => array_slice($r, $decalage, $limite), 'total' => count($r)];
    }

    public function espaceFichiers(): array
    {
        $octets = 0;
        $parMois = [];
        $parCategorie = [];
        foreach ($this->fichiers as $f) {
            $poids = $f['taille'] + $f['taille_miniature'];
            $octets += $poids;
            $mois = substr($f['created_at'], 0, 7);
            $parMois[$mois] = ['mois' => $mois, 'octets' => ($parMois[$mois]['octets'] ?? 0) + $poids, 'fichiers' => ($parMois[$mois]['fichiers'] ?? 0) + 1];
            $c = $f['categorie'];
            $parCategorie[$c] = ['categorie' => $c, 'octets' => ($parCategorie[$c]['octets'] ?? 0) + $poids, 'fichiers' => ($parCategorie[$c]['fichiers'] ?? 0) + 1];
        }
        $sansDedoublonnage = 0;
        foreach ($this->pieces as $p) {
            $sansDedoublonnage += $this->fichiers[$p['fichier_id']]['taille'] + $this->fichiers[$p['fichier_id']]['taille_miniature'];
        }
        krsort($parMois);

        return [
            'octets' => $octets,
            'fichiers' => count($this->fichiers),
            'pieces' => count($this->pieces),
            'octets_sans_dedoublonnage' => $sansDedoublonnage,
            'par_mois' => array_values($parMois),
            'par_categorie' => array_values($parCategorie),
        ];
    }

    public function curseurMessages(int $userId): int
    {
        $max = 0;
        foreach ($this->conversationsDe($userId) as $c) {
            $max = max($max, $this->maxId($c['id']));
        }

        return $max;
    }

    public function nouveauxMessages(int $userId, int $depuis, int $limite): array
    {
        $mes = array_map(static fn(array $c): int => $c['id'], $this->conversationsDe($userId));
        $r = array_filter($this->messages, static fn(array $m): bool => $m['id'] > $depuis && in_array($m['conversation_id'], $mes, true));
        ksort($r);

        return array_map(fn(array $m): array => $this->avecAuteur($m), array_slice(array_values($r), 0, $limite));
    }

    public function messagesRetouches(int $userId, int $jusquA, string $depuis): array
    {
        $mes = array_map(static fn(array $c): int => $c['id'], $this->conversationsDe($userId));
        $r = array_filter($this->messages, static fn(array $m): bool => $m['id'] <= $jusquA
            && $m['maj_at'] !== null && $m['maj_at'] >= $depuis && in_array($m['conversation_id'], $mes, true));

        return array_map(fn(array $m): array => $this->avecAuteur($m), array_values($r));
    }

    public function lecturesDepuis(int $userId, string $depuis): array
    {
        $mes = array_map(static fn(array $c): int => $c['id'], $this->conversationsDe($userId));

        return array_values(array_filter($this->membres, static fn(array $m): bool => in_array($m['conversation_id'], $mes, true)
            && $m['user_id'] !== $userId && $m['quitte_at'] === null && $m['lu_at'] >= $depuis));
    }

    public function conversationsChangees(int $userId, string $depuis): array
    {
        $modifiees = [];
        $quittees = [];
        foreach ($this->membres as $m) {
            if ($m['user_id'] !== $userId) {
                continue;
            }
            if ($m['quitte_at'] !== null && $m['quitte_at'] >= $depuis) {
                $quittees[] = $m['conversation_id'];
            } elseif ($m['quitte_at'] === null
                && ($this->conversations[$m['conversation_id']]['maj_at'] >= $depuis || $m['rejoint_at'] >= $depuis)) {
                $modifiees[] = $m['conversation_id'];
            }
        }

        return ['modifiees' => $modifiees, 'quittees' => $quittees];
    }

    public function presenceCorrespondants(int $userId): array
    {
        $vus = [];
        foreach ($this->conversationsDe($userId) as $c) {
            foreach ($this->membresActifs($c['id']) as $m) {
                if ($m['user_id'] !== $userId) {
                    $p = $this->presence[$m['user_id']] ?? [];
                    $vus[$m['user_id']] = [
                        'user_id' => $m['user_id'], 'actif' => $m['actif'],
                        'vu_at' => $p['vu_at'] ?? null, 'presence_masquee' => (bool) ($p['presence_masquee'] ?? false),
                    ];
                }
            }
        }

        return array_values($vus);
    }

    public function toucherPresence(int $userId, string $maintenant): void
    {
        $this->presence[$userId] = ['vu_at' => $maintenant] + ($this->presence[$userId] ?? []);
        $this->presence[$userId]['vu_at'] = $maintenant;
    }

    public function preferences(int $userId): array
    {
        return ($this->presence[$userId] ?? []) + ['presence_masquee' => 0, 'son_actif' => 1, 'apercu_masque' => 0];
    }

    public function enregistrerPreferences(int $userId, array $preferences): void
    {
        $this->presence[$userId] = $preferences + ($this->presence[$userId] ?? []);
    }

    public function journaliserAccesDirection(int $userId, int $conversationId, string $maintenant): void
    {
        $this->accesDirection[] = ['user_id' => $userId, 'conversation_id' => $conversationId, 'created_at' => $maintenant];
    }

    public function toutesConversations(string $recherche, string $type, int $limite): array
    {
        $r = [];
        foreach ($this->conversations as $c) {
            if ($type !== '' && $c['type'] !== $type) {
                continue;
            }
            $noms = [];
            foreach ($this->membres as $m) {
                if ($m['conversation_id'] === $c['id'] && $m['quitte_at'] === null) {
                    $noms[] = (string) ($this->utilisateurs[$m['user_id']]['nom'] ?? '');
                }
            }
            sort($noms);
            $participants = implode(', ', $noms);
            if ($recherche !== '' && stripos((string) $c['nom'] . ' ' . $participants, $recherche) === false) {
                continue;
            }
            $nb = count(array_filter($this->messages, static fn(array $m): bool => $m['conversation_id'] === $c['id']));
            $r[] = $c + ['nb_messages' => $nb, 'participants' => $participants];
        }
        usort($r, static fn(array $a, array $b): int => strcmp((string) ($b['dernier_message_at'] ?? $b['created_at']), (string) ($a['dernier_message_at'] ?? $a['created_at'])));

        return array_slice($r, 0, $limite);
    }

    public function accesDirectionRecents(int $limite): array
    {
        $r = [];
        foreach (array_reverse($this->accesDirection) as $a) {
            $c = $this->conversations[$a['conversation_id']] ?? [];
            $r[] = [
                'created_at' => $a['created_at'],
                'lecteur' => $this->utilisateurs[$a['user_id']]['nom'] ?? null,
                'conversation_id' => $a['conversation_id'],
                'type' => $c['type'] ?? null,
                'nom' => $c['nom'] ?? null,
            ];
        }

        return array_slice($r, 0, $limite);
    }

    private function maxId(int $conversationId): int
    {
        $max = 0;
        foreach ($this->messages as $m) {
            if ($m['conversation_id'] === $conversationId) {
                $max = max($max, $m['id']);
            }
        }

        return $max;
    }

    /**
     * @param array<string, mixed> $p
     * @return array<string, mixed>
     */
    private function pieceComplete(array $p, int $userId): array
    {
        $f = $this->fichiers[$p['fichier_id']];

        return $p + [
            'mime' => $f['mime'], 'categorie' => $f['categorie'], 'extension' => $f['extension'],
            'taille' => $f['taille'], 'largeur' => $f['largeur'], 'hauteur' => $f['hauteur'],
            'chemin_miniature' => $f['chemin_miniature'],
            'favori' => isset($this->favoris["$userId-{$p['id']}"]),
        ];
    }

    /** @param array<string, mixed> $m */
    private function avecAuteur(array $m): array
    {
        return $m + ['auteur_nom' => $this->utilisateurs[$m['auteur_id']]['nom'] ?? ''];
    }
}
