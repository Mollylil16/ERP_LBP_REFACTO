<?php

declare(strict_types=1);

namespace App\View\Pages\SiteAdmin;

final class ConversationsPage
{
    /**
     * @param array<int,array<string,mixed>> $conversations
     * @param array<string,mixed> $activeConversation
     * @param array<int,array<string,mixed>> $messages
     */
    public function __construct(
        public readonly string $csrfToken,
        public readonly array $conversations,
        public readonly array $activeConversation,
        public readonly array $messages,
    ) {
    }
}
