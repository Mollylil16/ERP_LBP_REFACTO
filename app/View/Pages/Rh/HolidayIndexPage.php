<?php

declare(strict_types=1);

namespace App\View\Pages\Rh;

final class HolidayIndexPage
{
    /**
     * @param array<int,array<string,mixed>> $holidays
     */
    public function __construct(public readonly array $holidays) {}

    /** @param string|null $date */
    public function formatDate(?string $date): string
    {
        if (!$date) {
            return '';
        }
        $ts = strtotime($date);
        return $ts ? date('d/m/Y', $ts) : '';
    }
}
