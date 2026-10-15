<?php

declare(strict_types=1);

namespace App\Controllers\Logistique;

use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Helpers\View;
use PDO;

final class LogistiqueColisageController extends LogistiqueBaseController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    /**
     * Qui peut ouvrir la fiche d'une facture.
     *
     * Ce module masque volontairement les montants. Le lien vers la facture
     * n'en affiche aucun — mais inutile de proposer une porte a quelqu'un qui
     * recevra « Acces refuse » de l'autre cote.
     *
     * @var array<int, string>
     */
    private const ROLES_FACTURE = [
        'caissiere', 'caissiere_principale', 'chef_agence', 'dg',
        'agent_saisie', 'agent_enregistrement', 'gestionnaire_caisse',
        'comptable', 'superviseur_regional', 'superviseur_general',
        'suivi_recouvrement', 'responsable_groupage', 'agent_groupage',
    ];

    public function index(): void
    {
        AuthMiddleware::check();

        [$period, $dateStart, $dateEnd] = $this->resolveDates();
        $filtres = $this->filtresDeListe();

        // Selected agence filter (default: 0/all for global roles / Assistante DG, else user agence)
        $userAgenceId = $_SESSION['user']['agence_id'] ?? null;
        $isGlobal = \App\Helpers\Auth::isAdmin() || \App\Helpers\Auth::isAssistantDg() || \App\Helpers\Auth::hasAnyRole(['dg', 'assistant_dg', 'assistante_dg', 'superviseur_general', 'comptable', 'caissiere_principale']);
        $selectedAgenceId = isset($_GET['agence_id']) ? (int) $_GET['agence_id'] : ($isGlobal ? 0 : ($userAgenceId ? (int) $userAgenceId : 0));

        // Fetch active agencies
        $sitesStmt = $this->pdo->query("SELECT id, name, code, city FROM company_sites WHERE is_active = 1 ORDER BY name ASC");
        $sites = $sitesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Fetch parcels for selected agence and date range
        $parcels = $this->fetchColisData($selectedAgenceId, $dateStart, $dateEnd, $filtres);

        // KPI aggregates (WITHOUT monetary values)
        $totalSaisies = count($parcels);
        $totalNombreColis = 0;
        $totalPoids = 0.0;

        foreach ($parcels as $p) {
            $totalNombreColis += (int) ($p['nombre_colis'] ?? 1);
            $totalPoids += (float) ($p['poids_total'] ?? 0.0);
        }

        $module = (new \App\Repositories\Logistique\LogistiqueDashboardRepository($this->pdo))->dashboard();

        $this->logistiqueView('logistique/colisage', 'Suivi Colisage Agences', 'colisage', $module, [
            'sites' => $sites,
            'period' => $period,
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd,
            'selectedAgenceId' => $selectedAgenceId,
            'parcels' => $parcels,
            'trajets' => $this->catalogueTrajets(),
            'filtres' => $filtres,
            'peutVoirFacture' => \App\Helpers\Auth::isAdmin()
                || \App\Helpers\Auth::isAssistantDg()
                || \App\Helpers\Auth::hasAnyRole(self::ROLES_FACTURE),
            'kpis' => [
                'totalSaisies' => $totalSaisies,
                'totalNombreColis' => $totalNombreColis,
                'totalPoids' => $totalPoids,
            ],
        ]);
    }

    public function exportPdf(): void
    {
        AuthMiddleware::check();

        [$period, $dateStart, $dateEnd] = $this->resolveDates();
        $filtres = $this->filtresDeListe();

        $selectedAgenceId = isset($_GET['agence_id']) ? (int) $_GET['agence_id'] : 0;
        $parcels = $this->fetchColisData($selectedAgenceId, $dateStart, $dateEnd, $filtres);

        $agenceName = 'Toutes les agences';
        if ($selectedAgenceId > 0) {
            $stmt = $this->pdo->prepare("SELECT name FROM company_sites WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $selectedAgenceId]);
            $agenceName = $stmt->fetchColumn() ?: 'Agence #' . $selectedAgenceId;
        }

        // L etat doit dire sur quoi il porte : un PDF filtre sur LB-CI et un
        // PDF complet se ressemblent trop pour qu on les distingue plus tard.
        $categorieLabel = $this->libelleCategorie($filtres['trajet']);

        $totalSaisies = count($parcels);
        $totalNombreColis = array_sum(array_column($parcels, 'nombre_colis'));
        $totalPoids = array_sum(array_column($parcels, 'poids_total'));

        require BASE_PATH . '/views/logistique/colisage_pdf.php';
    }

    public function exportExcel(): void
    {
        AuthMiddleware::check();

        [$period, $dateStart, $dateEnd] = $this->resolveDates();
        $filtres = $this->filtresDeListe();

        $selectedAgenceId = isset($_GET['agence_id']) ? (int) $_GET['agence_id'] : 0;
        $parcels = $this->fetchColisData($selectedAgenceId, $dateStart, $dateEnd, $filtres);

        $agenceName = 'Toutes les agences';
        if ($selectedAgenceId > 0) {
            $stmt = $this->pdo->prepare("SELECT name FROM company_sites WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $selectedAgenceId]);
            $agenceName = $stmt->fetchColumn() ?: 'Agence #' . $selectedAgenceId;
        }

        $categorieLabel = $this->libelleCategorie($filtres['trajet']);

        $dateSuffix = ($dateStart === $dateEnd) ? $dateStart : $dateStart . '_to_' . $dateEnd;
        // Le code de categorie entre dans le nom du fichier : trois exports
        // LB-CI, CA-CI et complet se retrouvaient sinon sous le meme nom.
        $suffixeCategorie = $filtres['trajet'] !== ''
            ? '_' . preg_replace('/[^a-zA-Z0-9_\-]/', '', $filtres['trajet'])
            : '';
        $filename = 'suivi_colisage_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $agenceName)
            . $suffixeCategorie . '_' . $dateSuffix . '.xls';

        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        echo "\xEF\xBB\xBF"; // UTF-8 BOM
        require BASE_PATH . '/views/logistique/colisage_excel.php';
    }

    /**
     * Resolves the start and end dates based on the period.
     *
     * @return array{0: string, 1: string, 2: string} [period, dateStart, dateEnd]
     */
    private function resolveDates(): array
    {
        $period = $_GET['period'] ?? 'today';
        $dateStart = $_GET['date_start'] ?? null;
        $dateEnd = $_GET['date_end'] ?? null;
        $datePattern = '/^\d{4}-\d{2}-\d{2}$/';

        if ($period !== 'custom' || empty($dateStart) || empty($dateEnd) || !preg_match($datePattern, $dateStart) || !preg_match($datePattern, $dateEnd)) {
            $today = new \DateTime();
            switch ($period) {
                case 'yesterday':
                    $yesterday = clone $today;
                    $yesterday->modify('-1 day');
                    $dateStart = $yesterday->format('Y-m-d');
                    $dateEnd = $yesterday->format('Y-m-d');
                    break;
                case 'this_week':
                    $startOfWeek = clone $today;
                    $startOfWeek->setISODate((int)$today->format('o'), (int)$today->format('W'), 1); // Monday
                    $endOfWeek = clone $startOfWeek;
                    $endOfWeek->modify('+6 days'); // Sunday
                    $dateStart = $startOfWeek->format('Y-m-d');
                    $dateEnd = $endOfWeek->format('Y-m-d');
                    break;
                case 'this_month':
                    $dateStart = $today->format('Y-m-01');
                    $dateEnd = $today->format('Y-m-t');
                    break;
                case 'this_year':
                    $dateStart = $today->format('Y-01-01');
                    $dateEnd = $today->format('Y-12-31');
                    break;
                case 'today':
                default:
                    $dateStart = $today->format('Y-m-d');
                    $dateEnd = $today->format('Y-m-d');
                    $period = 'today';
                    break;
            }
        }

        return [$period, $dateStart, $dateEnd];
    }

    /**
     * Les deux filtres de liste : la categorie de code, et la recherche.
     *
     * @return array{trajet: string, q: string}
     */
    private function filtresDeListe(): array
    {
        return [
            'trajet' => trim((string) ($_GET['trajet_code'] ?? '')),
            'q' => trim((string) ($_GET['q'] ?? '')),
        ];
    }

    /**
     * Le catalogue complet des codes, meme ceux a zero colis.
     *
     * Demande expresse de l'administrateur le 15/10/2026 : neuf des treize
     * codes n'ont encore servi a rien, mais les retirer de la liste ferait
     * croire qu'ils n'existent pas le jour ou une agence commencera a les
     * utiliser.
     *
     * @return array<int, array{code: string, libelle: string}>
     */
    private function catalogueTrajets(): array
    {
        $stmt = $this->pdo->query(
            "SELECT code, libelle FROM trajets WHERE code IS NOT NULL AND TRIM(code) <> '' ORDER BY id ASC"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function libelleCategorie(string $code): string
    {
        if ($code === '') {
            return 'Toutes les catégories';
        }

        foreach ($this->catalogueTrajets() as $t) {
            if ($t['code'] === $code) {
                return $t['code'] . ' — ' . $t['libelle'];
            }
        }

        return $code;
    }

    /**
     * Fetch parcels for logistique consultation (STRICTLY WITHOUT MONETARY AMOUNTS).
     *
     * @param array{trajet: string, q: string} $filtres
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchColisData(int $agenceId, string $dateStart, string $dateEnd, array $filtres = ['trajet' => '', 'q' => '']): array
    {
        $sql = "
            SELECT 
                c.id,
                c.numero_tracking,
                c.poids_total,
                c.nombre_colis,
                c.statut,
                c.trajet AS col_trajet,
                c.type_expediteur,
                c.created_at,
                exp.name AS expediteur_name,
                /*
                 * Le numero de l expediteur. L etat ne portait que son nom :
                 * impossible de le rappeler depuis un quai sans rouvrir le
                 * logiciel, alors que le destinataire, lui, avait son contact.
                 */
                exp.phone AS expediteur_phone,
                dest.name AS destinataire_name,
                dest.phone AS destinataire_phone,
                dep.name AS agence_depart_name,
                arr.name AS agence_arrivee_name,
                COALESCE(u.full_name, 'Agent') AS agent_name,
                t.code AS trajet_code,
                t.libelle AS trajet_libelle,
                t.type_transport AS trajet_transport,
                (
                    /*
                     * La nature des produits : vetements, poisson fume, gari.
                     * L etat ne disait que le poids et le nombre, et deux
                     * colis de meme poids n ont pourtant rien de commun — la
                     * douane, le client et l agence de destination veulent
                     * savoir ce qu il y a dedans.
                     *
                     * Sous-requete et non jointure : un colis porte plusieurs
                     * marchandises, et une jointure l aurait compte autant de
                     * fois, faussant le nombre de colis et le poids total.
                     */
                    SELECT GROUP_CONCAT(
                               DISTINCT NULLIF(TRIM(m.description), '')
                               ORDER BY m.description SEPARATOR ', '
                           )
                    FROM lbp_marchandises m
                    WHERE m.colis_id = c.id
                ) AS marchandises,
                /*
                 * La facture du colis, pour y aller d un clic depuis ce suivi.
                 * Un colis n en porte qu une — verifie sur les 1 009 colis de
                 * production le 15/10/2026 — mais on passe quand meme par des
                 * sous-requetes : une jointure qui doublerait un jour fausserait
                 * le nombre de colis et le poids total du pied de tableau.
                 */
                (SELECT f.id FROM lbp_factures f WHERE f.colis_id = c.id ORDER BY f.id LIMIT 1) AS facture_id,
                (SELECT f.numero_facture FROM lbp_factures f WHERE f.colis_id = c.id ORDER BY f.id LIMIT 1) AS facture_numero
            FROM lbp_colis c
            LEFT JOIN lbp_clients exp ON c.expediteur_id = exp.id
            LEFT JOIN lbp_clients dest ON c.destinataire_id = dest.id
            LEFT JOIN company_sites dep ON c.agence_depart_id = dep.id
            LEFT JOIN company_sites arr ON c.agence_arrivee_id = arr.id
            LEFT JOIN users u ON c.agent_groupage_id = u.id
            LEFT JOIN trajets t ON c.trajet_id = t.id
            WHERE 1 = 1
        ";

        $params = [];
        $recherche = $filtres['q'] ?? '';

        /*
         * Une recherche passe outre la periode.
         *
         * Decide avec l administrateur le 15/10/2026 : on cherche un code de
         * colis parce qu on ne sait justement pas quand il a ete saisi. Garder
         * le filtre de dates rendrait l ecran muet neuf fois sur dix, et la
         * recherche inutilisable. Des que le champ est vide, la periode
         * reprend la main.
         */
        if ($recherche === '') {
            $sql .= " AND DATE(c.created_at) BETWEEN :date_start AND :date_end";
            $params['date_start'] = $dateStart;
            $params['date_end'] = $dateEnd;
        }

        if ($agenceId > 0) {
            $sql .= " AND (c.agence_depart_id = :agence_id1 OR c.agence_arrivee_id = :agence_id2)";
            $params['agence_id1'] = $agenceId;
            $params['agence_id2'] = $agenceId;
        }

        if (($filtres['trajet'] ?? '') !== '') {
            $sql .= " AND t.code = :trajet_code";
            $params['trajet_code'] = $filtres['trajet'];
        }

        if ($recherche !== '') {
            /*
             * Un parametre nomme ne peut pas servir deux fois : PDO n emule
             * pas les requetes preparees ici, et la seconde occurrence
             * resterait vide — la recherche ne trouverait rien sans rien
             * signaler. D ou un parametre numerote par colonne cherchee.
             */
            $sql .= " AND (
                c.numero_tracking LIKE :q1
                OR exp.name LIKE :q2
                OR dest.name LIKE :q3
                OR exp.phone LIKE :q4
                OR dest.phone LIKE :q5
                OR EXISTS (SELECT 1 FROM lbp_factures f WHERE f.colis_id = c.id AND f.numero_facture LIKE :q6)
            )";

            $motif = '%' . $recherche . '%';
            foreach (['q1', 'q2', 'q3', 'q4', 'q5', 'q6'] as $nom) {
                $params[$nom] = $motif;
            }
        }

        $sql .= " ORDER BY c.created_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
