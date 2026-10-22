<?php

declare(strict_types=1);

namespace App\Services\Noel;

use App\Models\Database;
use App\Repositories\Finance\EtatJournalierRepository;
use PDO;
use Throwable;

/**
 * Le rapport du soir, pour le directeur et pour l'administrateur.
 *
 * UNE LISTE DE CHIFFRES SE LIT UNE FOIS PUIS S'IGNORE. Ce qu'un directeur veut
 * savoir, ce n'est pas « Adjame a facture 1,2 million » — c'est « Adjame a
 * facture 40 % de moins qu'un mardi ordinaire ». Meme donnee, autre valeur.
 * Chaque chiffre est donc accompagne de sa moyenne des quatre memes jours
 * precedents, et de l'ecart.
 *
 * CE RAPPORT NE PARLE QUE DU TRAVAIL. Jamais un mot de conversation, jamais
 * rien de ce que quelqu'un aura confie a NOEL. C'est la cloison posee le
 * 22/10/2026 avec l'administrateur : sans elle, le personnel cesserait de
 * parler a NOEL en deux semaines, et le metier de collegue mourrait.
 *
 * Aucune intelligence artificielle : tout vient de la base. NOEL ne fait que
 * mettre en phrase ce que le SQL a compte.
 */
final class NoelRapport
{
    /** Sur combien de memes jours on calcule l'ordinaire. */
    public const SEMAINES_DE_REFERENCE = 4;

    /** En deca, un ecart n'est que du bruit et ne merite pas d'etre signale. */
    public const ECART_NOTABLE = 20.0;

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * Le rapport d'une journee.
     *
     * @return array{
     *     date: string,
     *     jour: string,
     *     chiffres: array<string, array{libelle: string, valeur: float, unite: string, ordinaire: float, ecart: ?float}>,
     *     agences: array<int, array<string, mixed>>,
     *     encaissements: array<string, float>,
     *     faits: array<int, string>
     * }
     */
    public function duJour(?string $date = null): array
    {
        $date ??= date('Y-m-d');

        $chiffres = [
            'factures' => $this->chiffre('Factures emises', 'nombre', $date, $this->sqlFactures('COUNT(*)')),
            'facture_xof' => $this->chiffre('Montant facture', 'XOF', $date, $this->sqlFactures('COALESCE(SUM(montant_total), 0)')),
            'encaisse_xof' => $this->chiffre('Encaissements', 'XOF', $date, $this->sqlEncaissements()),
            'colis' => $this->chiffre('Colis enregistres', 'colis', $date, $this->sqlColis('COALESCE(SUM(nombre_colis), 0)')),
            'poids' => $this->chiffre('Poids total', 'kg', $date, $this->sqlColis('COALESCE(SUM(poids_total), 0)')),
        ];

        $agences = $this->pointsDeCaisse($date);

        return [
            'date' => $date,
            'jour' => self::nomDuJour($date),
            'chiffres' => $chiffres,
            'agences' => $agences,
            'encaissements' => $this->encaissementsParMode($date),
            'faits' => $this->faitsMarquants($chiffres, $agences, $date),
        ];
    }

    // ------------------------------------------------------------------
    // Les chiffres, et ce qu'ils valent d'ordinaire
    // ------------------------------------------------------------------

    /**
     * Un chiffre du jour, compare aux quatre memes jours precedents.
     *
     * On compare un mardi a des mardis, jamais a la moyenne de la semaine :
     * le samedi d'une entreprise de fret ne ressemble a aucun autre jour, et
     * le melanger aux autres rendrait tous les ecarts faux.
     *
     * @return array{libelle: string, valeur: float, unite: string, ordinaire: float, ecart: ?float}
     */
    private function chiffre(string $libelle, string $unite, string $date, string $sql): array
    {
        $valeur = $this->valeur($sql, $date);

        $passes = [];
        for ($semaine = 1; $semaine <= self::SEMAINES_DE_REFERENCE; $semaine++) {
            $jour = date('Y-m-d', (int) strtotime($date . ' -' . $semaine . ' week'));
            $passes[] = $this->valeur($sql, $jour);
        }

        $ordinaire = $passes === [] ? 0.0 : array_sum($passes) / count($passes);

        return [
            'libelle' => $libelle,
            'valeur' => $valeur,
            'unite' => $unite,
            'ordinaire' => round($ordinaire, 2),
            'ecart' => self::ecart($valeur, $ordinaire),
        ];
    }

    /**
     * L'ecart en pourcentage, ou null quand il ne veut rien dire.
     *
     * Partir de zero rend tout pourcentage infini : un jour sans aucune
     * facture suivi d'un jour a trois ne vaut pas « +300 % », ca ne veut rien
     * dire. On prefere ne rien annoncer que d'annoncer un chiffre faux.
     */
    public static function ecart(float $valeur, float $ordinaire): ?float
    {
        if ($ordinaire <= 0.0) {
            return null;
        }

        return round((($valeur - $ordinaire) / $ordinaire) * 100, 1);
    }

    // ------------------------------------------------------------------
    // Les requetes
    // ------------------------------------------------------------------

    private function sqlFactures(string $agregat): string
    {
        return "SELECT {$agregat} FROM lbp_factures WHERE DATE(date_emission) = :jour";
    }

    private function sqlColis(string $agregat): string
    {
        return "SELECT {$agregat} FROM lbp_colis WHERE DATE(created_at) = :jour";
    }

    /**
     * Les encaissements se comptent dans lbp_paiements, jamais sur la facture.
     *
     * Le 15/10/2026 une facture corrigee a la main affichait 100 000 F alors
     * que ses deux lignes de paiement en disaient 200 000 : c'est la somme des
     * paiements qui fait foi, puisque c'est elle qui remplit le tiroir.
     */
    private function sqlEncaissements(): string
    {
        return "SELECT COALESCE(SUM(montant), 0) FROM lbp_paiements
                 WHERE DATE(date_paiement) = :jour AND devise = 'XOF'";
    }

    /**
     * La ventilation par mode de reglement.
     *
     * On passe par MODE_SQL et non par la colonne mode_paiement : seule
     * `mode` est reellement ecrite, l'autre vaut toujours 'ESPECES' et
     * donnerait une ventilation entierement fausse.
     *
     * @return array<string, float>
     */
    private function encaissementsParMode(string $date): array
    {
        $modeSql = EtatJournalierRepository::MODE_SQL;

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    SUM(CASE WHEN {$modeSql} IN ('especes', 'espece', 'cash') THEN p.montant ELSE 0 END) AS especes,
                    SUM(CASE WHEN {$modeSql} IN ('mobile_money', 'wave', 'orange_money', 'mtn') THEN p.montant ELSE 0 END) AS mobile,
                    SUM(CASE WHEN {$modeSql} IN ('carte', 'cb') THEN p.montant ELSE 0 END) AS carte,
                    SUM(CASE WHEN {$modeSql} IN ('virement', 'cheque') THEN p.montant ELSE 0 END) AS banque
                FROM lbp_paiements p
                WHERE DATE(p.date_paiement) = :jour AND p.devise = 'XOF'
            ");
            $stmt->execute(['jour' => $date]);
            $ligne = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('[NOEL rapport] ventilation impossible : ' . $e->getMessage());

            return [];
        }

        return [
            'Especes' => (float) ($ligne['especes'] ?? 0),
            'Mobile Money' => (float) ($ligne['mobile'] ?? 0),
            'Carte' => (float) ($ligne['carte'] ?? 0),
            'Cheque / virement' => (float) ($ligne['banque'] ?? 0),
        ];
    }

    /**
     * Qui a soumis son point, et qui ne l'a pas fait.
     *
     * Meme definition que CronAlertesDirection et que NoelVeille : « soumis »
     * ou « consolide » valent cloture.
     *
     * @return array<int, array{agence: string, soumis: bool, heure: ?string}>
     */
    private function pointsDeCaisse(string $date): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT s.name AS agence,
                       e.statut,
                       e.date_soumission
                  FROM company_sites s
                  LEFT JOIN lbp_etats_journaliers e
                         ON e.agence_id = s.id AND e.date_jour = :jour
                 WHERE s.is_active = 1
                 ORDER BY s.name ASC
            ");
            $stmt->execute(['jour' => $date]);
            $lignes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('[NOEL rapport] points de caisse illisibles : ' . $e->getMessage());

            return [];
        }

        $agences = [];
        foreach ($lignes as $ligne) {
            $statut = (string) ($ligne['statut'] ?? '');
            $soumis = in_array($statut, ['soumis', 'consolide'], true);

            $agences[] = [
                'agence' => (string) $ligne['agence'],
                'soumis' => $soumis,
                'heure' => $soumis && !empty($ligne['date_soumission'])
                    ? date('H\hi', (int) strtotime((string) $ligne['date_soumission']))
                    : null,
            ];
        }

        return $agences;
    }

    private function valeur(string $sql, string $date): float
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['jour' => $date]);

            return (float) $stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log('[NOEL rapport] chiffre illisible : ' . $e->getMessage());

            return 0.0;
        }
    }

    // ------------------------------------------------------------------
    // Ce que NOEL retient
    // ------------------------------------------------------------------

    /**
     * Les trois ou quatre phrases qui resument la journee.
     *
     * C'est la partie qu'on lit vraiment. Elle ne dit que ce qui sort de
     * l'ordinaire — un rapport qui repete chaque soir « tout va bien » cesse
     * d'etre lu au bout d'une semaine, et le soir ou il dit autre chose,
     * personne ne le remarque.
     *
     * @param array<string, array<string, mixed>> $chiffres
     * @param array<int, array<string, mixed>> $agences
     *
     * @return array<int, string>
     */
    private function faitsMarquants(array $chiffres, array $agences, string $date): array
    {
        $faits = [];

        $enRetard = array_values(array_filter($agences, static fn(array $a): bool => !$a['soumis']));

        if ($enRetard !== []) {
            $noms = implode(', ', array_column($enRetard, 'agence'));
            $faits[] = count($enRetard) === 1
                ? "Point de caisse non soumis : " . $noms . "."
                : count($enRetard) . " agences n'ont pas soumis leur point : " . $noms . ".";
        } elseif ($agences !== []) {
            // On le dit aussi quand tout va bien : un gardien qui n'ouvre la
            // bouche que pour gronder finit par etre ferme.
            $faits[] = "Toutes les agences ont soumis leur point de caisse.";
        }

        foreach ($chiffres as $chiffre) {
            $ecart = $chiffre['ecart'];

            if ($ecart === null || abs($ecart) < self::ECART_NOTABLE) {
                continue;
            }

            $faits[] = sprintf(
                '%s : %s %s, soit %s%% %s qu\'un %s ordinaire.',
                $chiffre['libelle'],
                self::nombre((float) $chiffre['valeur']),
                $chiffre['unite'],
                number_format(abs((float) $ecart), 0, ',', ' '),
                $ecart > 0 ? 'de plus' : 'de moins',
                self::nomDuJour($date)
            );
        }

        if (count($faits) === 1 && $enRetard === []) {
            $faits[] = "Journee dans la moyenne, rien a signaler.";
        }

        return $faits;
    }

    public static function nombre(float $valeur): string
    {
        return number_format($valeur, $valeur === floor($valeur) ? 0 : 2, ',', ' ');
    }

    public static function nomDuJour(string $date): string
    {
        $jours = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];

        return $jours[((int) date('N', (int) strtotime($date))) - 1] ?? 'jour';
    }
}
