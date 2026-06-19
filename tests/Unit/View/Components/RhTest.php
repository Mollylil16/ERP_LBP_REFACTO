<?php

declare(strict_types=1);

namespace Tests\Unit\View\Components;

use App\View\Components\Rh;
use Tests\TestCase;

final class RhTest extends TestCase
{
    public function test_table_escapes_values_and_accepts_renderers(): void
    {
        $html = Rh::table(
            [['name' => '<Alice>', 'count' => 2]],
            [
                ['label' => 'Nom', 'key' => 'name'],
                ['label' => 'Total', 'render' => static fn(array $row): string => '<strong>' . $row['count'] . '</strong>'],
            ]
        );

        self::assertStringContainsString('&lt;Alice&gt;', $html);
        self::assertStringContainsString('<strong>2</strong>', $html);
    }

    public function test_pagination_marks_current_page(): void
    {
        $html = Rh::pagination(2, 3, static fn(int $page): string => '/rh/personnel?page=' . $page);

        self::assertStringContainsString('class="is-active"', $html);
        self::assertStringContainsString('aria-current="page"', $html);
        self::assertStringContainsString('page=3', $html);
    }

    public function test_internal_rh_pages_adopt_shared_component(): void
    {
        foreach ([
            'views/rh/personnel/exit.php',
            'views/rh/personnel/form.php',
            'views/rh/personnel/index.php',
            'views/rh/personnel/movements-index.php',
            'views/rh/personnel/mutation.php',
            'views/rh/personnel/mutations-index.php',
            'views/rh/personnel/show.php',
            'views/rh/settings/index.php',
            'views/rh/lifecycle/index.php',
        ] as $file) {
            $source = (string) file_get_contents(BASE_PATH . '/' . $file);
            self::assertStringContainsString('Components\\Rh', $source, $file);
        }
    }

    public function test_personnel_index_has_no_data_preparation_logic(): void
    {
        $source = (string) file_get_contents(BASE_PATH . '/views/rh/personnel/index.php');

        foreach ([
            '$filters',
            '$pagination',
            '$optionRows',
            '$queryForPage',
            'Auth::canOperation',
            'http_build_query',
            'array_map',
            'array_filter',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $source);
        }

        self::assertStringContainsString('EmployeeCard::render', $source);
        self::assertStringContainsString('Rh::paginationLinks($page->pagination)', $source);
    }

    public function test_rh_views_do_not_rebuild_view_bags_or_navigation(): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(BASE_PATH . '/views/rh')
        );

        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $source = (string) file_get_contents($file->getPathname());
            self::assertStringNotContainsString('ViewBag::from(get_defined_vars())', $source);
        }

        self::assertFileDoesNotExist(BASE_PATH . '/views/rh/' . '_navigation.php');
        self::assertFileDoesNotExist(BASE_PATH . '/views/rh/' . '_restricted-data.php');
    }

    public function test_rh_controllers_share_one_module_layout_context(): void
    {
        $baseController = (string) file_get_contents(
            BASE_PATH . '/app/Controllers/Rh/RhBaseController.php'
        );

        foreach ([
            'RhDashboardController.php',
            'RhLifecycleController.php',
            'RhModuleController.php',
            'RhPersonnelController.php',
            'RhSettingsController.php',
        ] as $file) {
            $source = (string) file_get_contents(BASE_PATH . '/app/Controllers/Rh/' . $file);
            self::assertStringContainsString('extends RhBaseController', $source, $file);
            self::assertStringNotContainsString('RhNavigation::items()', $source, $file);
        }

        self::assertStringContainsString('RhNavigation::items()', $baseController);
        self::assertStringContainsString("'moduleNavigation'", $baseController);
        self::assertStringContainsString('protected function rhView(', $baseController);
    }

    public function test_rh_component_does_not_duplicate_page_header(): void
    {
        $source = (string) file_get_contents(BASE_PATH . '/app/View/Components/Rh.php');

        self::assertStringNotContainsString('function pageHeader(', $source);
    }
}
