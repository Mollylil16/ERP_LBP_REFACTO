<?php

declare(strict_types=1);

namespace App\Services\Noel;

use App\Controllers\Finance\FinanceController;
use App\Helpers\Auth;
use App\Models\Database;
use App\Security\ApproCaisseAcces;
use App\Security\DemandesFondsAcces;
use PDO;
use Throwable;

/**
 * Le gardien : ce qui attend chaque personne, et qu'elle a peut-etre oublie.
 *
 * La direction est deja prevenue des oublis par CronAlertesDirection : passe
 * dix-sept heures, elle recoit la liste des agences qui n'ont pas cloture.
 * Mais prevenir le directeur qu'une caissiere a oublie, c'est arriver apres la
 * faute. NOEL, lui, previent CELLE QUI DOIT AGIR, pendant qu'il est encore
 * temps de la reparer sans que personne n'ait a relancer qui que ce soit.
 *
 * AUCUNE INTELLIGENCE ARTIFICIELLE ICI, ET C'EST VOLONTAIRE. « Le point de
 * caisse n'est pas soumis » se sait avec certitude pour trois francs de SQL ;
 * un modele de langage le devinerait, parfois faux, et serait facture a chaque
 * fois. Les regles calculent, NOEL se contente de mettre en mots.
 *
 * Chaque regle n'est evaluee que pour qui a le droit d'agir dessus. On reprend
 * les listes de roles des ecrans eux-memes — jamais une copie : une copie
 * derive, et c'est deja arrive sur les demandes de fonds.
 */
final class NoelVeille
{
    /** L'heure a partir de laquelle un point non soumis devient un oubli. */
    public const HEURE_RAPPEL_POINT = 16;

    /** Au-dela, une demande n'attend plus : elle dort. */
    public const JOURS_AVANT_SOUFFRANCE = 2;

    public const TON_INFO = 'info';
    public const TON_ATTENTION = 'attention';
    public const TON_URGENT = 'urgent';

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * Tout ce qui attend la personne connectee, du plus urgent au reste.
     *
     * @return array<int, array{cle: string, titre: string, detail: string, url: string, ton: string}>
     */
    public function pourMoi(?\DateTimeInterface $maintenant = null): array
    {
        $maintenant ??= new \DateTimeImmutable('now', new \DateTimeZone('Africa/Abidjan'));

        $alertes = array_merge(
            $this->pointDeCaisseNonSoumis($maintenant),
            $this->demandesAValider(),
            $this->demandesADecaisser(),
            $this->approsAValider(),
        );

        // L'urgent d'abord : une liste qui commence par un rappel aimable fait
        // manquer l'alerte qui comptait.
        $rang = [self::TON_URGENT => 0, self::TON_ATTENTION => 1, self::TON_INFO => 2];
        usort(
            $alertes,
            static fn(array $a, array $b): int => ($rang[$a['ton']] ?? 9) <=> ($rang[$b['ton']] ?? 9)
        );

        return $alertes;
    }

    /**
     * Le point de caisse du jour n'est pas soumis, et l'heure tourne.
     *
     * On ne previent que ceux qui peuvent le soumettre, et seulement pour LEUR
     * agence : annoncer a une caissiere de Dokui qu'Adjame n'a pas cloture ne
     * sert a rien, sinon a lui faire croire qu'elle doit s'en occuper.
     *
     * @return array<int, array<string, string>>
     */
    private function pointDeCaisseNonSoumis(\DateTimeInterface $maintenant): array
    {
        if (!Auth::hasAnyRole(FinanceController::ROLES_SOUMISSION_POINT)) {
            return [];
        }

        $agence = (int) (Auth::agenceId() ?? 0);

        if ($agence <= 0) {
            return [];
        }

        $heure = (int) $maintenant->format('G');
        $jour = $maintenant->format('Y-m-d');

        if ($this->pointEstSoumis($agence, $jour)) {
            return [];
        }

        /*
         * Avant seize heures on ne dit rien : la journee n'est pas finie, et
         * un rappel toutes les heures depuis le matin ferait de NOEL une
         * nuisance qu'on apprend a ignorer — y compris le soir ou il a raison.
         */
        if ($heure < self::HEURE_RAPPEL_POINT) {
            return [];
        }

        $urgent = $heure >= 18;

        return [[
            'cle' => 'point-caisse-' . $agence . '-' . $jour,
            'titre' => $urgent
                ? "Votre point de caisse n'est toujours pas soumis"
                : "Votre point de caisse n'est pas encore soumis",
            'detail' => $urgent
                ? "Il est " . $maintenant->format('G\hi') . ". La direction voit les agences non closes passe 17 heures. Quelques minutes maintenant vous evitent une relance demain."
                : "La journee se termine. Pensez a compter la caisse et a soumettre le point avant de partir.",
            'url' => 'finance/clotures',
            'ton' => $urgent ? self::TON_URGENT : self::TON_ATTENTION,
        ]];
    }

    /**
     * Des demandes de fonds attendent une decision.
     *
     * Depuis le partage du 14/10/2026, valider et decaisser sont deux mains
     * differentes : on ne previent donc pas la meme personne des deux.
     *
     * @return array<int, array<string, string>>
     */
    private function demandesAValider(): array
    {
        if (!DemandesFondsAcces::peutValider()) {
            return [];
        }

        $nombre = $this->compter(
            "SELECT COUNT(*) FROM lbp_demandes_fonds WHERE statut = 'en_attente'"
        );

        if ($nombre <= 0) {
            return [];
        }

        $vieilles = $this->compter(
            "SELECT COUNT(*) FROM lbp_demandes_fonds
             WHERE statut = 'en_attente' AND created_at < DATE_SUB(NOW(), INTERVAL "
            . self::JOURS_AVANT_SOUFFRANCE . " DAY)"
        );

        return [[
            'cle' => 'fonds-a-valider-' . date('Y-m-d'),
            'titre' => $nombre . ' demande' . ($nombre > 1 ? 's' : '') . ' de fonds attend'
                . ($nombre > 1 ? 'ent' : '') . ' votre decision',
            'detail' => $vieilles > 0
                ? $vieilles . ' dorment depuis plus de deux jours. Quelqu un attend cet argent pour travailler.'
                : "Ouvrez la fiche par le bouton Detail : c est la que se trouvent Valider et Rejeter.",
            'url' => 'finance/fonds',
            'ton' => $vieilles > 0 ? self::TON_URGENT : self::TON_ATTENTION,
        ]];
    }

    /**
     * Des demandes validees attendent leur decaissement.
     *
     * @return array<int, array<string, string>>
     */
    private function demandesADecaisser(): array
    {
        if (!DemandesFondsAcces::peutDecaisser()) {
            return [];
        }

        $nombre = $this->compter(
            "SELECT COUNT(*) FROM lbp_demandes_fonds WHERE statut = 'validee'"
        );

        if ($nombre <= 0) {
            return [];
        }

        return [[
            'cle' => 'fonds-a-decaisser-' . date('Y-m-d'),
            'titre' => $nombre . ' demande' . ($nombre > 1 ? 's' : '') . ' autorisee'
                . ($nombre > 1 ? 's' : '') . ' attend' . ($nombre > 1 ? 'ent' : '') . ' l argent',
            'detail' => "La decision est prise, il reste a sortir les fonds et a editer le bon de sortie.",
            'url' => 'finance/fonds/prise-en-compte',
            'ton' => self::TON_ATTENTION,
        ]];
    }

    /**
     * Des approvisionnements attendent la validation du comptable.
     *
     * Tant qu'ils ne sont pas valides, ils n'entrent pas dans la caisse de
     * l'agence — et l'agence comptera le soir un argent que le logiciel
     * n'attend pas.
     *
     * @return array<int, array<string, string>>
     */
    private function approsAValider(): array
    {
        if (!ApproCaisseAcces::peutValider()) {
            return [];
        }

        $nombre = $this->compter(
            "SELECT COUNT(*) FROM lbp_appros_caisse WHERE statut = 'en_attente'"
        );

        if ($nombre <= 0) {
            return [];
        }

        return [[
            'cle' => 'appro-a-valider-' . date('Y-m-d'),
            'titre' => $nombre . ' approvisionnement' . ($nombre > 1 ? 's' : '') . ' attend'
                . ($nombre > 1 ? 'ent' : '') . ' votre validation',
            'detail' => "Un appro valide entre en caisse au jour de la remise. Validez-le avant la fermeture de ce jour-la : apres, l ecart deja enregistre ne se recalcule plus.",
            'url' => 'finance/appro-caisse',
            'ton' => self::TON_ATTENTION,
        ]];
    }

    /**
     * Le point du jour de cette agence est-il soumis ?
     *
     * Meme regle que CronAlertesDirection, et pas une variante : « soumis » ou
     * « consolide » valent cloture. Deux definitions differentes du meme fait
     * finiraient par se contredire, et personne ne saurait laquelle croire.
     */
    private function pointEstSoumis(int $agence, string $jour): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 1 FROM lbp_etats_journaliers
                 WHERE agence_id = :agence AND date_jour = :jour
                   AND statut IN ('soumis', 'consolide')
                 LIMIT 1
            ");
            $stmt->execute(['agence' => $agence, 'jour' => $jour]);

            return (bool) $stmt->fetchColumn();
        } catch (Throwable $e) {
            /*
             * Dans le doute, on se tait. Un gardien qui crie faute de savoir
             * est pire qu'un gardien muet : on cesse de l'ecouter, y compris
             * le jour ou il a raison.
             */
            error_log('[NOEL] point de caisse illisible : ' . $e->getMessage());

            return true;
        }
    }

    private function compter(string $sql): int
    {
        try {
            $stmt = $this->pdo->query($sql);

            return $stmt ? (int) $stmt->fetchColumn() : 0;
        } catch (Throwable $e) {
            error_log('[NOEL] comptage impossible : ' . $e->getMessage());

            return 0;
        }
    }

    /**
     * Mettre une alerte en phrase, comme NOEL la dirait.
     *
     * @param array{titre: string, detail: string} $alerte
     */
    public static function enPhrase(array $alerte, string $prenom = ''): string
    {
        /*
         * La minuscule ne se met qu'apres le prenom : « Mariam, votre point… »
         * mais « Votre point… » quand on ne connait pas le prenom. Appliquer
         * lcfirst dans tous les cas donnait une phrase sans majuscule.
         */
        if ($prenom === '') {
            return $alerte['titre'] . '. ' . $alerte['detail'];
        }

        return $prenom . ', ' . lcfirst($alerte['titre']) . '. ' . $alerte['detail'];
    }
}
