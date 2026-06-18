<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;

final class Modal
{
    public static function render(string $id, string $title, string $content, string $triggerLabel, array $options = []): string
    {
        $id = preg_replace('/[^a-z0-9_-]/i', '-', $id);
        $trigger = Ui::button($triggerLabel, [
            'variant' => (string) ($options['variant'] ?? 'accent'),
            'type' => 'button',
        ]);
        $trigger = str_replace('<button ', '<button data-modal-open="' . View::e($id) . '" ', $trigger);
        return '<div class="finea-modal-launcher">' . $trigger . '</div>'
            . '<dialog class="finea-modal" id="' . View::e($id) . '" data-modal>'
            . '<div class="finea-modal-dialog"><header><div><p>' . View::e((string) ($options['eyebrow'] ?? 'Nouvelle opération'))
            . '</p><h2>' . View::e($title) . '</h2></div><button type="button" class="finea-modal-close" data-modal-close aria-label="Fermer">×</button></header>'
            . '<div class="finea-modal-body">' . $content . '</div></div></dialog>';
    }
}
