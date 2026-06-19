<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;

final class SiteChat
{
    /** @param array<int,array<string,mixed>> $messages */
    public static function messages(array $messages, string $viewer): string
    {
        $html = '<div class="site-chat-messages" data-chat-messages data-last-message-id="'
            . (int) ($messages === [] ? 0 : end($messages)['id']) . '">';
        foreach ($messages as $message) {
            $html .= self::message($message, $viewer);
        }
        return $html . '</div>';
    }

    /** @param array<string,mixed> $message */
    public static function message(array $message, string $viewer): string
    {
        $own = (string) ($message['sender_type'] ?? '') === $viewer;
        $html = '<article class="site-chat-message' . ($own ? ' is-own' : '') . '" data-message-id="'
            . (int) ($message['id'] ?? 0) . '"><div>';
        if (($message['message'] ?? '') !== '') {
            $html .= '<p>' . nl2br(View::e((string) $message['message'])) . '</p>';
        }
        $html .= self::attachment($message)
            . '<time>' . View::e((string) ($message['created_at'] ?? '')) . '</time></div></article>';
        return $html;
    }

    /** @param array<string,mixed> $message */
    private static function attachment(array $message): string
    {
        $path = trim((string) ($message['attachment_path'] ?? ''));
        if ($path === '') {
            return '';
        }
        $url = View::asset($path);
        $mime = (string) ($message['attachment_mime'] ?? '');
        if (str_starts_with($mime, 'image/')) {
            return '<a class="site-chat-attachment" href="' . View::e($url) . '" target="_blank"><img src="'
                . View::e($url) . '" alt="' . View::e((string) ($message['attachment_name'] ?? 'Image')) . '"></a>';
        }
        if (str_starts_with($mime, 'video/')) {
            return '<video class="site-chat-media" controls preload="metadata" src="' . View::e($url) . '"></video>';
        }
        if (str_starts_with($mime, 'audio/')) {
            return '<audio class="site-chat-audio" controls preload="metadata" src="' . View::e($url) . '"></audio>';
        }
        return '<a class="site-chat-file" href="' . View::e($url) . '" target="_blank">'
            . View::e((string) ($message['attachment_name'] ?? 'Télécharger le fichier')) . '</a>';
    }
}
