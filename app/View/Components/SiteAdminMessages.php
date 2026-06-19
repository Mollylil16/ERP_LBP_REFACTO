<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;
use App\View\Pages\SiteAdmin\ConversationsPage;

final class SiteAdminMessages
{
    public static function page(ConversationsPage $page): string
    {
        $html = Ui::pageHeader('Messages clients', 'Répondez aux visiteurs connectés depuis leur espace client.', [
            'eyebrow' => 'Assistance instantanée',
        ]);
        $html .= '<section class="site-admin-messaging"><aside class="site-admin-conversations">';
        if ($page->conversations === []) {
            $html .= Ui::emptyState('Aucune conversation', 'Les nouveaux échanges clients apparaîtront ici.');
        }
        foreach ($page->conversations as $conversation) {
            $active = (int) ($page->activeConversation['id'] ?? 0) === (int) $conversation['id'];
            $html .= '<a class="' . ($active ? 'is-active' : '') . '" href="'
                . View::url('site-admin/messages') . '?conversation=' . (int) $conversation['id'] . '"><strong>'
                . View::e((string) $conversation['full_name']) . '</strong><span>'
                . View::e((string) ($conversation['last_message'] ?: 'Pièce jointe ou nouvelle conversation'))
                . '</span><small>' . (int) $conversation['message_count'] . ' message(s) · '
                . View::e((string) $conversation['status']) . '</small></a>';
        }
        $html .= '</aside><div class="site-admin-chat">';
        if ($page->activeConversation === []) {
            return $html . Ui::emptyState('Sélectionnez une conversation') . '</div></section>';
        }
        $id = (int) $page->activeConversation['id'];
        $html .= '<header><div><strong>' . View::e((string) $page->activeConversation['full_name'])
            . '</strong><span>' . View::e((string) $page->activeConversation['email'])
            . ' · ' . View::e((string) ($page->activeConversation['phone'] ?? '')) . '</span></div>'
            . '<em>' . View::e((string) $page->activeConversation['status']) . '</em></header>'
            . '<div class="site-chat" data-chat data-feed-url="' . View::url('site-admin/messages/' . $id . '/feed') . '">'
            . SiteChat::messages($page->messages, 'manager')
            . '<form method="post" enctype="multipart/form-data" action="' . View::url('site-admin/messages/' . $id)
            . '" data-chat-form>' . Form::hidden('_csrf_token', $page->csrfToken)
            . Form::textarea('message', ['label' => 'Réponse', 'rows' => 3, 'placeholder' => 'Répondre au client...'])
            . Form::dropzone('attachment', 'Joindre une image, vidéo ou note vocale', [
                'accept' => 'image/jpeg,image/png,image/webp,video/mp4,video/webm,audio/mpeg,audio/ogg,audio/webm,audio/mp4',
                'hint' => 'Média local, 20 Mo maximum.',
            ])
            . '<button class="site-voice-button" type="button" data-voice-record>Enregistrer une note vocale</button>'
            . Ui::button('Envoyer au client', ['variant' => 'primary', 'type' => 'submit'])
            . '</form></div></div></section>';
        return $html;
    }
}
