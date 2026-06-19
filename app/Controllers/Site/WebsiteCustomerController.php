<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\BaseController;
use App\Helpers\Csrf;
use App\Helpers\Response;
use App\Helpers\Session;
use App\Models\Database;
use App\Repositories\Site\WebsiteCustomerRepository;
use App\Repositories\Site\WebsiteRepository;
use App\Services\Site\WebsiteCustomerService;
use App\Services\Site\WebsiteService;
use App\View\Components\SiteChat;
use App\View\Pages\Site\CustomerAccountPage;
use App\View\Pages\Site\SitePage;
use RuntimeException;

final class WebsiteCustomerController extends BaseController
{
    private WebsiteCustomerService $customers;
    private WebsiteService $website;

    public function __construct()
    {
        $pdo = Database::getConnection();
        $this->customers = new WebsiteCustomerService(new WebsiteCustomerRepository($pdo));
        $this->website = new WebsiteService(new WebsiteRepository($pdo));
    }

    public function account(): void
    {
        $customerId = (int) Session::get('site_customer_id', 0);
        $data = $customerId > 0 ? $this->customers->dashboard($customerId) : [];
        $this->view('site/account', [
            'pageTitle' => $customerId > 0 ? 'Mon espace client' : 'Compte client',
            'page' => new CustomerAccountPage(
                $this->sitePage(),
                Csrf::token(),
                $data['customer'] ?? [],
                $data['conversation'] ?? [],
                $data['messages'] ?? [],
                $customerId > 0,
            ),
        ]);
    }

    public function register(): void
    {
        $this->verifyCsrf();
        try {
            $id = $this->customers->register($_POST);
            Session::set('site_customer_id', $id);
            Session::flash('success', 'Votre espace client est prêt.');
        } catch (RuntimeException $exception) {
            Session::flash('error', $exception->getMessage());
        }
        $this->redirect('/site/account');
    }

    public function login(): void
    {
        $this->verifyCsrf();
        try {
            Session::set('site_customer_id', $this->customers->authenticate(
                (string) ($_POST['email'] ?? ''),
                (string) ($_POST['password'] ?? '')
            ));
            Session::flash('success', 'Bienvenue dans votre espace client.');
        } catch (RuntimeException $exception) {
            Session::flash('error', $exception->getMessage());
        }
        $this->redirect('/site/account');
    }

    public function logout(): void
    {
        Session::forget('site_customer_id');
        $this->redirect('/site');
    }

    public function sendMessage(): void
    {
        $customerId = $this->customerId();
        $this->verifyCsrf();
        try {
            $this->customers->sendCustomerMessage(
                $customerId,
                (string) ($_POST['message'] ?? ''),
                $_FILES['attachment'] ?? null,
            );
            if ($this->wantsJson()) {
                Response::json(['ok' => true]);
            }
        } catch (RuntimeException $exception) {
            if ($this->wantsJson()) {
                Response::json(['ok' => false, 'message' => $exception->getMessage()], 422);
            }
            Session::flash('error', $exception->getMessage());
        }
        $this->redirect('/site/account#assistance');
    }

    public function messages(): void
    {
        $customerId = $this->customerId();
        $dashboard = $this->customers->dashboard($customerId);
        $messages = $this->customers->messages(
            (int) $dashboard['conversation']['id'],
            (int) ($_GET['after'] ?? 0),
        );
        Response::json([
            'ok' => true,
            'messages' => array_map(
                static fn(array $message): array => [
                    'id' => (int) $message['id'],
                    'html' => SiteChat::message($message, 'customer'),
                ],
                $messages
            ),
        ]);
    }

    private function sitePage(): SitePage
    {
        $content = $this->website->content();
        return new SitePage(
            'Compte client',
            'account',
            [],
            [],
            $content['services'],
            [],
            [],
            $content['branding'],
            $content['slides'],
            $content['products'],
            $content['topics'],
            $content['announcements'],
            $content['articles'],
        );
    }

    private function customerId(): int
    {
        $id = (int) Session::get('site_customer_id', 0);
        if ($id <= 0) {
            $this->redirect('/site/account');
        }
        return $id;
    }

    private function verifyCsrf(): void
    {
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'La session du formulaire a expiré.');
            $this->redirect('/site/account');
        }
    }

    private function wantsJson(): bool
    {
        return str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    }
}
