<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Helpers\Auth;
use App\Middleware\AdminMiddleware;
use App\Models\Database;
use App\Repositories\Admin\ComptesAuditRepository;
use App\Repositories\Admin\RolesRepository;
use App\View\Pages\Admin\JournalPage;
use App\View\Pages\Admin\RolesPage;
use PDO;

/**
 * Le journal des comptes et l'écran des rôles.
 *
 * Deux lectures de contrôle, strictement réservées à l'administrateur : elles
 * disent qui a touché à quoi, et qui peut faire quoi.
 */
final class AdminJournalController extends AdminBaseController
{
    private ComptesAuditRepository $audit;
    private RolesRepository $roles;

    public function __construct()
    {
        $pdo = Database::getConnection();
        $this->audit = new ComptesAuditRepository($pdo);
        $this->roles = new RolesRepository($pdo);
    }

    public function journal(): void
    {
        AdminMiddleware::check();

        $filtres = $this->filtres();

        $this->adminView('admin/journal', 'Journal des comptes', 'journal', [
            'page' => new JournalPage(
                $this->audit->journal($filtres),
                $filtres,
                $this->comptes(),
                $this->audit->acteurs()
            ),
        ]);
    }

    public function journalPdf(): void
    {
        AdminMiddleware::check();

        $filtres = $this->filtres();
        $page = new JournalPage($this->audit->journal($filtres), $filtres, [], [], Auth::user()?->fullName ?? '');

        require BASE_PATH . '/views/admin/journal_pdf.php';
    }

    public function journalExcel(): void
    {
        AdminMiddleware::check();

        $filtres = $this->filtres();
        $page = new JournalPage($this->audit->journal($filtres), $filtres);

        $suffixe = $filtres['du'] === '' ? date('Y-m-d') : $filtres['du'] . '_' . $filtres['au'];

        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="journal_comptes_' . $suffixe . '.xls"');
        header('Cache-Control: max-age=0');

        // Sans cette marque, le tableur lit les accents en ISO et affiche « Ã© ».
        echo "\xEF\xBB\xBF";
        require BASE_PATH . '/views/admin/journal_excel.php';
    }

    public function rolesEtAnomalies(): void
    {
        AdminMiddleware::check();

        $this->adminView('admin/roles', 'Rôles et anomalies', 'roles', [
            'page' => new RolesPage(
                $this->roles->rolesEtPorteurs(),
                $this->roles->comptesSansRole(),
                $this->roles->comptesDormants(),
                $this->roles->administrateurs()
            ),
        ]);
    }

    /**
     * Les mêmes filtres pour l'écran et pour ses deux exports : un PDF qui ne
     * dirait pas la même chose que l'écran d'où il sort ne vaudrait rien.
     *
     * @return array<string, string>
     */
    private function filtres(): array
    {
        return [
            'du' => trim((string) ($_GET['du'] ?? '')),
            'au' => trim((string) ($_GET['au'] ?? '')),
            'action' => trim((string) ($_GET['action'] ?? '')),
            'cible_id' => trim((string) ($_GET['cible_id'] ?? '')),
            'acteur_id' => trim((string) ($_GET['acteur_id'] ?? '')),
            'q' => trim((string) ($_GET['q'] ?? '')),
        ];
    }

    /** @return array<int, array{id:int, name:string}> */
    private function comptes(): array
    {
        try {
            $stmt = Database::getConnection()->query('SELECT id, full_name AS name FROM users ORDER BY full_name ASC');

            return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
