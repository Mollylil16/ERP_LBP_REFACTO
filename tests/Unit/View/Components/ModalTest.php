<?php

declare(strict_types=1);

namespace Tests\Unit\View\Components;

use App\View\Components\Modal;
use Tests\TestCase;

final class ModalTest extends TestCase
{
    public function test_renders_button_and_native_dialog(): void
    {
        $html = Modal::render('contract-form', 'Nouveau contrat', '<form></form>', 'Créer');
        self::assertStringContainsString('data-modal-open="contract-form"', $html);
        self::assertStringContainsString('<dialog', $html);
        self::assertStringContainsString('id="contract-form"', $html);
        self::assertStringContainsString('data-modal-close', $html);
    }
}
