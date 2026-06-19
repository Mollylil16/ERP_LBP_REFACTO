<?php

declare(strict_types=1);

namespace Tests\Unit\View\Pages\Rh;

use App\View\Pages\Rh\PersonnelIndexPage;
use Tests\TestCase;

final class PersonnelIndexPageTest extends TestCase
{
    public function test_normalizes_personnel_index_data_outside_the_view(): void
    {
        $page = new PersonnelIndexPage([
            'filters' => ['q' => 'Alice', 'scope' => 'active'],
            'pagination' => [
                'items' => [['id' => 12, 'full_name' => 'Alice']],
                'total' => 1,
                'page' => 1,
                'totalPages' => 2,
            ],
            'options' => [
                'services' => [['id' => 3, 'name' => 'Finance']],
            ],
            'restrictedTables' => ['secret' => 'Donnees sensibles'],
        ], [
            'view' => true,
            'create' => true,
            'update' => true,
            'mutate' => true,
        ]);

        self::assertSame(1, $page->total);
        self::assertTrue($page->canCreate);
        self::assertSame('Tous', $page->filterOptions['services'][0]['label']);
        self::assertCount(3, $page->employees[0]['actions']);
        self::assertCount(2, $page->pagination);
        self::assertSame(['secret' => 'Donnees sensibles'], $page->restrictedTables);
    }
}
