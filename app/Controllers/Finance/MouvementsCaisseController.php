<?php

declare(strict_types=1);

namespace App\Controllers\Finance;

use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Helpers\View;
use App\Middleware\RoleMiddleware;
use App\Security\MouvementsCaisseAcces;
use App\Services\Finance\MouvementsCaisseService;
use Throwable;

/**
 * Finance > Mouvements de caisse.
 *
 * Ce qui entre et ce qui sort d'un tiroir dans la journée, avec les soldes et
 * les deux historiques. L'écran lit LBP : les règlements de factures, les
 * approvisionnements validés et les décaissements de demandes de fonds y
 * arrivent seuls. La saisie ne sert qu'à ce que LBP ne connaît pas, et c'est
 * la seule chose qui s'y retire.
 */
final class MouvementsCaisseController extends FinanceBaseController
{
    private MouvementsCaisseService $service;

    public function __construct()
    {
        $this->service = MouvementsCaisseService::creer();
    }

    public function index(): void
    {
        RoleMiddleware::check(MouvementsCaisseAcces::ROLES_LECTURE);

        $this->financeView(
            'finance/mouvements-caisse/index',
            'Mouvements de caisse',
            'mouvements-caisse',
            $this->service->tableauDuJour($_GET)
        );
    }

    public function versement(): void
    {
        $this->ecrire(
            fn (int $userId): array => $this->service->enregistrerVersement($_POST, $userId),
            'versement'
        );
    }

    public function retrait(): void
    {
        $this->ecrire(
            fn (int $userId): array => $this->service->enregistrerRetrait($_POST, $userId),
            'retrait'
        );
    }

    public function supprimer(string $id): void
    {
        $this->ecrire(
            fn (int $userId): array => $this->service->supprimer((int) $id, $userId),
            'supprimer'
        );
    }

    // ------------------------------------------------------------------
    // Les quatre exports
    // ------------------------------------------------------------------

    public function versementsPdf(): void
    {
        $this->exporter('versements', 'pdf');
    }

    public function versementsExcel(): void
    {
        $this->exporter('versements', 'excel');
    }

    public function retraitsPdf(): void
    {
        $this->exporter('retraits', 'pdf');
    }

    public function retraitsExcel(): void
    {
        $this->exporter('retraits', 'excel');
    }

    /**
     * Sort un historique tel qu'il est à l'écran.
     *
     * Les filtres sont relus depuis la même requête GET que l'écran — l'écran
     * les repasse dans le lien d'export — et le tableau est recalculé par le
     * même appel de service. Un document qui dirait autre chose que l'écran
     * d'où il sort ne servirait qu'à faire douter des deux.
     */
    private function exporter(string $sens, string $format): void
    {
        RoleMiddleware::check(MouvementsCaisseAcces::ROLES_LECTURE);

        $mouvements = $this->service->tableauDuJour($_GET);
        $editePar = Auth::user()?->fullName ?? '';
        $jour = (string) $mouvements['date'];

        if ($format === 'excel') {
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $sens . '_caisse_' . $jour . '.xls"');
            header('Cache-Control: max-age=0');

            // Sans cette marque, le tableur lit les accents en ISO et affiche « Ã© ».
            echo "\xEF\xBB\xBF";

            require BASE_PATH . '/views/finance/mouvements-caisse/export_excel.php';

            return;
        }

        require BASE_PATH . '/views/finance/mouvements-caisse/export_pdf.php';
    }

    // ------------------------------------------------------------------

    /**
     * Le tronc commun des trois écritures : jeton de session, identité de
     * l'auteur, exécution, et retour sur l'écran avec ses filtres.
     *
     * @param callable(int):array{0: string, 1: array<int, string>} $action
     */
    private function ecrire(callable $action, string $nom): void
    {
        RoleMiddleware::check(MouvementsCaisseAcces::ROLES_LECTURE);

        $retour = 'finance/mouvements-caisse' . $this->requete();

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée ou requête invalide. Veuillez réessayer.');
            $this->retour($retour);

            return;
        }

        $userId = MouvementsCaisseAcces::courant()->userId();

        if ($userId === null) {
            Session::flash('error', 'Session expirée. Reconnectez-vous avant de saisir.');
            $this->retour($retour);

            return;
        }

        try {
            [$message, $erreurs] = $action($userId);
        } catch (Throwable $e) {
            error_log('[Mouvements caisse] ' . $nom . ' : ' . $e->getMessage());
            Session::flash('error', "L'opération n'a pas abouti. Rien n'a été modifié : réessayez.");
            $this->retour($retour);

            return;
        }

        if ($erreurs !== []) {
            Session::flash('error', implode(' ', $erreurs));
        } else {
            Session::flash('success', $message);
        }

        $this->retour($retour);
    }

    /** Conserve la journée et les filtres regardés au retour d'une action. */
    private function requete(): string
    {
        $filtres = array_filter([
            'date' => $_POST['f_date'] ?? null,
            'caisse_id' => $_POST['f_caisse_id'] ?? null,
            'agence_id' => $_POST['f_agence_id'] ?? null,
            'q' => $_POST['f_q'] ?? null,
        ], static fn (mixed $v): bool => $v !== null && $v !== '');

        return $filtres === [] ? '' : '?' . http_build_query($filtres);
    }

    private function retour(string $url): void
    {
        header('Location: ' . View::url($url));
        exit;
    }
}
