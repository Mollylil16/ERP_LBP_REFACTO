<?php

declare(strict_types=1);

namespace App\Services\Noel;

use App\Models\Database;
use PDO;
use Throwable;

/**
 * Savoir si NOEL sert — avec un chiffre, pas une impression.
 *
 * Dans trois mois, personne ne saura dire si NOEL vaut son existence. Il faut
 * compter : questions posees, questions comprises, liens suivis. C'est aussi
 * ce qui dira si la cle d'API vaut ses 12 000 FCFA, puisque c'est exactement
 * le nombre de questions qu'il n'a PAS su traiter.
 *
 * CETTE TABLE NE CONTIENT AUCUN TEXTE. Ni la question, ni la reponse, ni le
 * nom de la personne : seulement des compteurs par jour et par sujet. On peut
 * donc mesurer sans jamais conserver ce que quelqu'un a ecrit, et le rapport
 * au directeur reste ce qu'on a promis qu'il serait.
 */
final class NoelMesure
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * Noter qu'une question a ete posee, et si NOEL l'a comprise.
     *
     * @param array{concept: ?string, intention: string} $lecture
     */
    public function noterQuestion(array $lecture, ?\DateTimeInterface $jour = null): void
    {
        $compris = ($lecture['concept'] ?? null) !== null
            || ($lecture['intention'] ?? '') !== NoelVocabulaire::INTENTION_INCONNUE;

        $this->incrementer(
            $compris ? 'comprises' : 'incomprises',
            (string) ($lecture['concept'] ?? ''),
            (string) ($lecture['intention'] ?? ''),
            $jour
        );
    }

    /**
     * Noter qu'un lien propose par NOEL a ete suivi.
     *
     * C'est la mesure la plus honnete de son utilite : une reponse qu'on lit
     * puis qu'on abandonne n'a servi a rien ; une reponse qu'on clique a
     * amene quelqu'un la ou il voulait aller.
     */
    public function noterLienSuivi(string $concept, ?\DateTimeInterface $jour = null): void
    {
        $this->incrementer('liens_suivis', $concept, '', $jour);
    }

    private function incrementer(string $colonne, string $concept, string $intention, ?\DateTimeInterface $jour): void
    {
        $jour ??= new \DateTimeImmutable('now', new \DateTimeZone('Africa/Abidjan'));

        try {
            /*
             * Une seule requete, et pas un SELECT puis un UPDATE : deux agents
             * qui posent une question a la meme seconde se marcheraient dessus
             * et l'un des deux comptages serait perdu.
             */
            $stmt = $this->pdo->prepare("
                INSERT INTO lbp_noel_mesures (jour, concept, intention, {$colonne})
                VALUES (:jour, :concept, :intention, 1)
                ON DUPLICATE KEY UPDATE {$colonne} = {$colonne} + 1
            ");
            $stmt->execute([
                'jour' => $jour->format('Y-m-d'),
                'concept' => mb_substr($concept, 0, 40),
                'intention' => mb_substr($intention, 0, 20),
            ]);
        } catch (Throwable $e) {
            /*
             * Une mesure qui echoue ne doit jamais empecher NOEL de repondre.
             * Compter est utile ; aider l'est davantage.
             */
            error_log('[NOEL mesure] ' . $e->getMessage());
        }
    }

    /**
     * Le bilan d'une periode : combien de questions, combien comprises.
     *
     * @return array{questions: int, comprises: int, incomprises: int, liens_suivis: int, taux: ?float}
     */
    public function bilan(string $du, string $au): array
    {
        $vide = ['questions' => 0, 'comprises' => 0, 'incomprises' => 0, 'liens_suivis' => 0, 'taux' => null];

        try {
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(comprises), 0) AS comprises,
                       COALESCE(SUM(incomprises), 0) AS incomprises,
                       COALESCE(SUM(liens_suivis), 0) AS liens
                  FROM lbp_noel_mesures
                 WHERE jour BETWEEN :du AND :au
            ");
            $stmt->execute(['du' => $du, 'au' => $au]);
            $ligne = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('[NOEL mesure] bilan illisible : ' . $e->getMessage());

            return $vide;
        }

        $comprises = (int) ($ligne['comprises'] ?? 0);
        $incomprises = (int) ($ligne['incomprises'] ?? 0);
        $total = $comprises + $incomprises;

        return [
            'questions' => $total,
            'comprises' => $comprises,
            'incomprises' => $incomprises,
            'liens_suivis' => (int) ($ligne['liens'] ?? 0),
            'taux' => $total > 0 ? round(($comprises / $total) * 100, 1) : null,
        ];
    }

    /**
     * Les sujets sur lesquels NOEL se fait le plus prendre en defaut.
     *
     * C'est la liste qui dit ou enrichir le dictionnaire en priorite : inutile
     * d'ajouter cent mots sur la comptabilite si tout le monde bute sur le
     * point de caisse.
     *
     * @return array<int, array{concept: string, incomprises: int}>
     */
    public function pointsFaibles(string $du, string $au, int $limite = 5): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT concept, SUM(incomprises) AS incomprises
                  FROM lbp_noel_mesures
                 WHERE jour BETWEEN :du AND :au AND incomprises > 0
                 GROUP BY concept
                 ORDER BY SUM(incomprises) DESC
                 LIMIT :limite
            ");
            $stmt->bindValue('du', $du);
            $stmt->bindValue('au', $au);
            $stmt->bindValue('limite', max(1, $limite), PDO::PARAM_INT);
            $stmt->execute();
            $lignes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('[NOEL mesure] points faibles illisibles : ' . $e->getMessage());

            return [];
        }

        return array_map(static fn(array $l): array => [
            'concept' => (string) $l['concept'] !== '' ? (string) $l['concept'] : 'sujet non identifie',
            'incomprises' => (int) $l['incomprises'],
        ], $lignes);
    }

    /**
     * Le bilan dit en une phrase, pour le rapport du soir.
     *
     * @param array{questions: int, comprises: int, incomprises: int, taux: ?float} $bilan
     */
    public static function enPhrase(array $bilan): string
    {
        if ($bilan['questions'] === 0) {
            return "Personne n'a sollicite NOEL.";
        }

        $phrase = $bilan['questions'] . ' question' . ($bilan['questions'] > 1 ? 's' : '')
            . ' posee' . ($bilan['questions'] > 1 ? 's' : '') . ' a NOEL';

        if ($bilan['taux'] !== null) {
            $phrase .= ', ' . number_format((float) $bilan['taux'], 0, ',', ' ') . ' % comprises';
        }

        if ($bilan['incomprises'] > 0) {
            $phrase .= ' — ' . $bilan['incomprises'] . ' restee'
                . ($bilan['incomprises'] > 1 ? 's' : '') . ' sans reponse';
        }

        return $phrase . '.';
    }
}
