<?php

declare(strict_types=1);

namespace App\Controllers\Finance;

use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Helpers\View;
use App\Middleware\RoleMiddleware;
use App\Security\ApproCaisseAcces;
use App\Services\Finance\ApproCaisseService;
use Throwable;

/**
 * Finance > Appro Caisse.
 *
 * La caissière principale saisit l'argent remis à une agence, le comptable le
 * valide. L'agence, elle, lit ce qu'elle a reçu : depuis qu'un appro validé
 * entre dans l'attendu de son point de caisse, le lui cacher reviendrait à lui
 * réclamer le soir un argent dont elle ignore l'origine.
 */
final class ApproCaisseController extends FinanceBaseController
{
    private ApproCaisseService $service;

    public function __construct()
    {
        $this->service = ApproCaisseService::creer();
    }

    public function index(): void
    {
        RoleMiddleware::check(ApproCaisseAcces::ROLES_LECTURE);

        $this->financeView(
            'finance/appro-caisse/index',
            'Appro Caisse',
            'appro-caisse',
            $this->service->tableau($_GET)
        );
    }

    public function enregistrer(): void
    {
        RoleMiddleware::check(ApproCaisseAcces::ROLES_LECTURE);
        $retour = 'finance/appro-caisse' . $this->requete();

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée ou requête invalide. Veuillez réessayer.');
            $this->retour($retour);
        }

        $this->agir(fn (): array => $this->service->enregistrer($_POST), $retour, 'enregistrer');
    }

    public function valider(string $id): void
    {
        RoleMiddleware::check(ApproCaisseAcces::ROLES_LECTURE);
        $retour = 'finance/appro-caisse' . $this->requete();

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée ou requête invalide. Veuillez réessayer.');
            $this->retour($retour);
        }

        $this->agir(fn (): array => $this->service->valider((int) $id), $retour, 'valider');
    }

    public function rejeter(string $id): void
    {
        RoleMiddleware::check(ApproCaisseAcces::ROLES_LECTURE);
        $retour = 'finance/appro-caisse' . $this->requete();

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée ou requête invalide. Veuillez réessayer.');
            $this->retour($retour);
        }

        $motif = (string) ($_POST['motif_rejet'] ?? '');

        $this->agir(fn (): array => $this->service->rejeter((int) $id, $motif), $retour, 'rejeter');
    }

    // ------------------------------------------------------------------

    /**
     * Exécute une action du service et rapporte son issue, sans jamais laisser
     * une erreur technique s'afficher au comptoir.
     *
     * @param callable():array{0:string, 1:array<int, string>} $action
     */
    private function agir(callable $action, string $retour, string $nom): void
    {
        try {
            [$message, $erreurs] = $action();
        } catch (Throwable $e) {
            error_log('[Appro caisse] ' . $nom . ' : ' . $e->getMessage());
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

    /** Conserve les filtres de l'écran au retour d'une action. */
    private function requete(): string
    {
        $filtres = array_filter([
            'du' => $_POST['f_du'] ?? null,
            'au' => $_POST['f_au'] ?? null,
            'agence_id' => $_POST['f_agence_id'] ?? null,
            'statut' => $_POST['f_statut'] ?? null,
        ], static fn (mixed $v): bool => $v !== null && $v !== '');

        return $filtres === [] ? '' : '?' . http_build_query($filtres);
    }

    private function retour(string $url): void
    {
        header('Location: ' . View::url($url));
        exit;
    }
}
