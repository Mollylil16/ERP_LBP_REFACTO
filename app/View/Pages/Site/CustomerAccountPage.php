<?php

declare(strict_types=1);

namespace App\View\Pages\Site;

final class CustomerAccountPage
{
    /**
     * @param array<string,mixed> $customer
     * @param array<string,mixed> $conversation
     * @param array<int,array<string,mixed>> $messages
     */
    public function __construct(
        public readonly SitePage $site,
        public readonly string $csrfToken,
        public readonly array $customer = [],
        public readonly array $conversation = [],
        public readonly array $messages = [],
        public readonly bool $authenticated = false,
    ) {
    }
}
