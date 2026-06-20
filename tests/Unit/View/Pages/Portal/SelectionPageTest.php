<?php

declare(strict_types=1);

namespace Tests\Unit\View\Pages\Portal;

use App\View\Pages\Portal\SelectionPage;
use PHPUnit\Framework\TestCase;

final class SelectionPageTest extends TestCase
{
    public function testItBuildsSearchOptionsFromModules(): void
    {
        $page = new SelectionPage('Amani', [
            ['key' => 'rh', 'label' => 'Ressources humaines', 'code' => 'RH'],
            ['key' => 'admin', 'label' => 'Administration', 'code' => 'ADM'],
        ]);

        self::assertSame([
            ['value' => 'rh', 'label' => 'Ressources humaines · RH'],
            ['value' => 'admin', 'label' => 'Administration · ADM'],
        ], $page->moduleOptions());
    }
}
