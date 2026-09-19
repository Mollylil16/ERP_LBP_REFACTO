<?php

declare(strict_types=1);

namespace App\Controllers\Finance;

use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Helpers\View;
use App\Middleware\RoleMiddleware;
use App\Security\RapprochementEnvoisAcces;
use App\Services\Finance\RapprochementEnvoisService;
use Throwable;

/**
 * Finance > Rapprochement des envois.
 *
 * Le comptable compare ce que les agences ont enregistré au document de la
 * compagnie, saisit le montant facturé et son règlement. La direction et son
 * assistante lisent le même écran.
 */
final class RapprochementEnvoisController extends FinanceBaseController
{
    private RapprochementEnvoisService $service;

    public function __construct()
    {
        $this->service = RapprochementEnvoisService::creer();
    }

    public function index(): void
    {
        $acces = $this->acces();

        $tableau = $this->service->tableau($_GET);

        $this->financeView(
            'finance/rapprochement/index',
            'Rapprochement des envois',
            'rapprochement-envois',
            $tableau + ['peutSaisir' => $acces->peutSaisir()]
        );
    }

    public function enregistrer(string $id): void
    {
        $acces = $this->acces();
        $retour = 'finance/rapprochement-envois' . $this->requete();

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée ou requête invalide. Veuillez réessayer.');
            $this->retour($retour);
        }

        try {
            [$message, $erreurs] = $this->service->enregistrer((int) $id, $_POST, $acces);
        } catch (Throwable $e) {
            error_log('[Rapprochement envois] enregistrer : ' . $e->getMessage());
            Session::flash('error', "Le rapprochement n'a pas été enregistré. Rien n'a été modifié : réessayez.");
            $this->retour($retour);

            return;
        }

        if ($erreurs !== []) {
            Session::flash('error', implode(' ', $erreurs));
            $this->retour($retour);

            return;
        }

        Session::flash('success', $message);
        $this->retour($retour);
    }

    public function exportPdf(): void
    {
        $this->acces();

        $rappro = $this->service->tableau($_GET) + ['edite_par' => Auth::user()?->fullName ?? ''];

        require BASE_PATH . '/views/finance/rapprochement/export_pdf.php';
    }

    public function exportExcel(): void
    {
        $this->acces();

        $rappro = $this->service->tableau($_GET) + ['edite_par' => Auth::user()?->fullName ?? ''];

        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="rapprochement_envois_' . $rappro['filtres']['du'] . '_' . $rappro['filtres']['au'] . '.xls"');
        header('Cache-Control: max-age=0');

        // Sans cette marque, le tableur lit les accents en ISO et affiche « Ã© ».
        echo "\xEF\xBB\xBF";
        require BASE_PATH . '/views/finance/rapprochement/export_excel.php';
    }

    // ------------------------------------------------------------------

    private function acces(): RapprochementEnvoisAcces
    {
        RoleMiddleware::check(RapprochementEnvoisAcces::ROLES_LECTURE);

        return RapprochementEnvoisAcces::courant();
    }

    /** Conserve les filtres de l'écran au retour d'une saisie. */
    private function requete(): string
    {
        $filtres = array_filter([
            'du' => $_POST['f_du'] ?? null,
            'au' => $_POST['f_au'] ?? null,
            'transporteur_id' => $_POST['f_transporteur_id'] ?? null,
            'agence_id' => $_POST['f_agence_id'] ?? null,
            'reglement' => $_POST['f_reglement'] ?? null,
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
