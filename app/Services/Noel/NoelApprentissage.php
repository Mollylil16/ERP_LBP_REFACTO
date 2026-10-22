<?php

declare(strict_types=1);

namespace App\Services\Noel;

use App\Helpers\Auth;
use App\Models\Database;
use App\Security\NoelAcces;
use PDO;
use Throwable;

/**
 * Ce qu'on apprend a NOEL — avec les mots du personnel, pas les miens.
 *
 * Les 723 expressions de son dictionnaire, c'est moi qui les ai devinees. Le
 * vrai dictionnaire s'ecrira autrement : chaque fois que NOEL ne comprend pas,
 * la question peut partir chez l'administrateur, qui repond a la personne et
 * transforme sa question en nouvelle entree. En quelques mois, les agents
 * auront ecrit un dictionnaire que personne n'aurait su produire d'avance.
 *
 * RIEN NE PART SANS QUE LA PERSONNE L'AIT VOULU. Une question que NOEL n'a pas
 * comprise peut etre une demande d'aide — mais aussi une confidence. La
 * stocker sans demander serait exactement la trahison contre laquelle on a
 * bati la cloison du 22/10/2026 : NOEL propose, la personne accepte, et c'est
 * seulement alors que la question est conservee.
 */
final class NoelApprentissage
{
    public const RECUE = 'recue';
    public const REPONDUE = 'repondue';
    public const APPRISE = 'apprise';
    public const CLASSEE = 'classee';

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * Ce que NOEL propose quand il n'a pas compris.
     *
     * La phrase dit trois choses, et les trois comptent : qu'il n'a pas
     * compris, ce qui arrivera s'il transmet, et que c'est a la personne de
     * decider. Transmettre d'office aurait ete plus simple et aurait coute la
     * confiance de tout le monde.
     */
    public static function proposition(): string
    {
        return "Je n'ai pas compris votre question, et je prefere le dire plutot que de repondre a cote. "
            . "Voulez-vous que je la transmette a l'administrateur ? Il vous repondra, et j'apprendrai "
            . "au passage. Votre question ne part que si vous me le demandez.";
    }

    /**
     * La personne accepte : on garde la question.
     *
     * @return int|null l'identifiant de la demande, ou null si rien n'a pu etre garde
     */
    public function transmettre(string $question, ?string $conceptDevine = null): ?int
    {
        $question = trim($question);

        if ($question === '') {
            return null;
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO lbp_noel_apprentissages (user_id, question, concept_devine, statut)
                VALUES (:user, :question, :concept, :statut)
            ");
            $stmt->execute([
                'user' => Auth::id() ?: null,
                'question' => mb_substr($question, 0, 500),
                'concept' => $conceptDevine !== null ? mb_substr($conceptDevine, 0, 40) : null,
                'statut' => self::RECUE,
            ]);

            return (int) $this->pdo->lastInsertId();
        } catch (Throwable $e) {
            error_log('[NOEL apprentissage] transmission impossible : ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Les questions qui attendent une reponse de l'administrateur.
     *
     * Reservees a l'administrateur et au directeur : ce sont les mots d'agents
     * identifies, pas un tableau de bord public.
     *
     * @return array<int, array<string, mixed>>
     */
    public function enAttente(int $limite = 50): array
    {
        if (!NoelAcces::estDestinataireDesRapports()) {
            return [];
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT a.id, a.question, a.concept_devine, a.statut, a.created_at,
                       COALESCE(u.full_name, 'Agent') AS auteur
                  FROM lbp_noel_apprentissages a
                  LEFT JOIN users u ON u.id = a.user_id
                 WHERE a.statut IN ('recue', 'repondue')
                 ORDER BY a.id DESC
                 LIMIT :limite
            ");
            $stmt->bindValue('limite', max(1, $limite), PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('[NOEL apprentissage] lecture impossible : ' . $e->getMessage());

            return [];
        }
    }

    /**
     * L'administrateur repond a la personne.
     */
    public function repondre(int $id, string $reponse): bool
    {
        if (!NoelAcces::estDestinataireDesRapports()) {
            return false;
        }

        return $this->changer($id, self::REPONDUE, trim($reponse));
    }

    /**
     * La question a servi : son vocabulaire a rejoint le dictionnaire.
     *
     * On ne supprime pas la ligne — elle dit ce que NOEL a appris et quand.
     * C'est la memoire de son apprentissage, et le seul moyen de savoir si le
     * dictionnaire grandit vraiment ou si l'on se contente de repondre.
     */
    public function marquerApprise(int $id): bool
    {
        return $this->changer($id, self::APPRISE, null);
    }

    /**
     * La question ne menera a rien : on la classe sans suite.
     */
    public function classer(int $id): bool
    {
        return $this->changer($id, self::CLASSEE, null);
    }

    private function changer(int $id, string $statut, ?string $reponse): bool
    {
        if (!NoelAcces::estDestinataireDesRapports() || $id <= 0) {
            return false;
        }

        try {
            if ($reponse !== null) {
                $stmt = $this->pdo->prepare("
                    UPDATE lbp_noel_apprentissages
                       SET statut = :statut, reponse = :reponse, repondu_par = :par, updated_at = NOW()
                     WHERE id = :id
                ");
                $stmt->execute([
                    'statut' => $statut,
                    'reponse' => mb_substr($reponse, 0, 1000),
                    'par' => Auth::id() ?: null,
                    'id' => $id,
                ]);

                return $stmt->rowCount() > 0;
            }

            $stmt = $this->pdo->prepare("
                UPDATE lbp_noel_apprentissages
                   SET statut = :statut, updated_at = NOW()
                 WHERE id = :id
            ");
            $stmt->execute(['statut' => $statut, 'id' => $id]);

            return $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            error_log('[NOEL apprentissage] mise a jour impossible : ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Ce que NOEL dit a la personne quand sa question a trouve reponse.
     *
     * Le geste compte autant que la reponse : quelqu'un qui a signale un
     * blocage et n'en entend plus parler ne signalera pas le suivant.
     */
    public static function retour(string $reponse): string
    {
        return "L'administrateur a repondu a votre question : " . trim($reponse)
            . "\nVous pouvez actualiser la page.";
    }

    /**
     * Combien de questions attendent, et combien ont deja servi.
     *
     * @return array{en_attente: int, apprises: int}
     */
    public function bilan(): array
    {
        try {
            $stmt = $this->pdo->query("
                SELECT
                    SUM(statut IN ('recue', 'repondue')) AS en_attente,
                    SUM(statut = 'apprise') AS apprises
                  FROM lbp_noel_apprentissages
            ");
            $ligne = $stmt ? ($stmt->fetch(PDO::FETCH_ASSOC) ?: []) : [];
        } catch (Throwable $e) {
            error_log('[NOEL apprentissage] bilan illisible : ' . $e->getMessage());

            return ['en_attente' => 0, 'apprises' => 0];
        }

        return [
            'en_attente' => (int) ($ligne['en_attente'] ?? 0),
            'apprises' => (int) ($ligne['apprises'] ?? 0),
        ];
    }
}
