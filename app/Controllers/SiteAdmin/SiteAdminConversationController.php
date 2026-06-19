<?php

declare(strict_types=1);

namespace App\Controllers\SiteAdmin;

use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\Response;
use App\Helpers\Session;
use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\Site\WebsiteCustomerRepository;
use App\Services\Site\WebsiteCustomerService;
use App\View\Components\SiteChat;
use App\View\Pages\SiteAdmin\ConversationsPage;
use RuntimeException;

final class SiteAdminConversationController extends SiteAdminBaseController
{
    private WebsiteCustomerService $service;

    public function __construct()
    {
        $this->service = new WebsiteCustomerService(
            new WebsiteCustomerRepository(Database::getConnection())
        );
    }

    public function index(): void
    {
        AuthMiddleware::check();
        $conversations = $this->service->conversations();
        $activeId = (int) ($_GET['conversation'] ?? ($conversations[0]['id'] ?? 0));
        $active = $activeId > 0 ? ($this->service->conversation($activeId) ?? []) : [];
        $messages = $active !== [] ? $this->service->messages($activeId) : [];

        $this->siteAdminView('site_admin/conversations', 'Messages clients', 'messages', [
            'page' => new ConversationsPage(Csrf::token(), $conversations, $active, $messages),
        ], [
            'accent' => '#14b8a6',
            'accent2' => '#0f766e',
            'gradient' => 'linear-gradient(135deg,#0f766e,#14b8a6)',
            'iconKey' => 'website',
        ], [
            'additionalStyles' => ['css/finea-ui.css', 'css/site-admin.css', 'css/site-chat.css'],
            'additionalScripts' => ['js/site-chat.js'],
        ]);
    }

    public function send(string $conversationId): void
    {
        AuthMiddleware::check();
        $this->verifyCsrf();
        try {
            $this->service->sendManagerMessage(
                (int) $conversationId,
                (int) Auth::id(),
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
        $this->redirect('/site-admin/messages?conversation=' . (int) $conversationId);
    }

    public function feed(string $conversationId): void
    {
        AuthMiddleware::check();
        $messages = $this->service->messages((int) $conversationId, (int) ($_GET['after'] ?? 0));
        Response::json([
            'ok' => true,
            'messages' => array_map(
                static fn(array $message): array => [
                    'id' => (int) $message['id'],
                    'html' => SiteChat::message($message, 'manager'),
                ],
                $messages
            ),
        ]);
    }

    private function verifyCsrf(): void
    {
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'La session du formulaire a expiré.');
            $this->redirect('/site-admin/messages');
        }
    }

    private function wantsJson(): bool
    {
        return str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    }
}
