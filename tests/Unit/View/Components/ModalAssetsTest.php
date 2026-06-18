<?php

declare(strict_types=1);

namespace Tests\Unit\View\Components;

use Tests\TestCase;

final class ModalAssetsTest extends TestCase
{
    public function test_shared_assets_support_modal_interactions_and_visuals(): void
    {
        $js = (string) file_get_contents(BASE_PATH . '/public/assets/js/components.js');
        $css = (string) file_get_contents(BASE_PATH . '/public/assets/css/finea-ui.css');
        self::assertStringContainsString('[data-modal-open]', $js);
        self::assertStringContainsString('showModal()', $js);
        self::assertStringContainsString('.finea-modal', $css);
        self::assertStringContainsString('.finea-record-list', $css);
        self::assertStringContainsString('.finea-kpi-card.is-clickable', $css);
    }
}
