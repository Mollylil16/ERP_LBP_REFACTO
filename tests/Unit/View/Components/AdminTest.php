<?php

declare(strict_types=1);

namespace Tests\Unit\View\Components;

use App\Models\User;
use App\View\Components\Admin;
use Tests\TestCase;

final class AdminTest extends TestCase
{
    public function test_user_table_escapes_values_and_exposes_actions(): void
    {
        $html = Admin::userTable([[
            'id' => 4,
            'name' => '<Admin>',
            'profile_reference' => 'Compte système',
            'email' => 'admin@example.test',
            'phone' => '',
            'profile' => 'Administrateur',
            'is_admin' => true,
            'status' => 'Actif',
            'status_tone' => 'ok',
            'created_at' => '19/06/2026',
            'actions' => [['label' => 'Profil', 'href' => 'admin/users/4']],
        ]]);

        self::assertStringContainsString('&lt;Admin&gt;', $html);
        self::assertStringContainsString('admin/users/4', $html);
        self::assertStringContainsString('is-admin', $html);
    }

    public function test_permission_summary_handles_admin_and_empty_user(): void
    {
        $admin = new User(1, 'Admin', 'admin@example.test', null, 'hash', isAdmin: true);
        $user = new User(2, 'User', 'user@example.test', null, 'hash');

        self::assertStringContainsString('Accès administrateur complet', Admin::permissionSummary($admin, []));
        self::assertStringContainsString('Aucune permission attribuée', Admin::permissionSummary($user, []));
    }

    public function test_admin_views_use_page_objects_components_and_shared_navigation(): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(BASE_PATH . '/views/admin')
        );

        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $source = (string) file_get_contents($file->getPathname());
            self::assertStringContainsString('/** @var ', $source, $file->getPathname());
            self::assertStringContainsString('$page */', $source, $file->getPathname());
            self::assertStringContainsString('Components\\Admin', $source, $file->getPathname());
            self::assertStringNotContainsString('ViewBag::from(get_defined_vars())', $source);
            self::assertStringNotContainsString('_navigation.php', $source);
        }

        self::assertFileDoesNotExist(BASE_PATH . '/views/admin/_navigation.php');
    }

    public function test_admin_controllers_share_one_module_context(): void
    {
        $base = (string) file_get_contents(BASE_PATH . '/app/Controllers/Admin/AdminBaseController.php');
        self::assertStringContainsString('AdminNavigation::items()', $base);
        self::assertStringContainsString('protected function adminView(', $base);

        foreach ([
            'AdminDashboardController.php',
            'AdminPermissionController.php',
            'AdminSystemTestController.php',
            'AdminUserController.php',
        ] as $file) {
            $source = (string) file_get_contents(BASE_PATH . '/app/Controllers/Admin/' . $file);
            self::assertStringContainsString('extends AdminBaseController', $source, $file);
            self::assertStringNotContainsString('AdminNavigation::items()', $source, $file);
            self::assertStringNotContainsString('private function viewData(', $source, $file);
        }
    }

    public function test_admin_component_does_not_duplicate_generic_ui_api(): void
    {
        $source = (string) file_get_contents(BASE_PATH . '/app/View/Components/Admin.php');

        self::assertStringNotContainsString('function pageHeader(', $source);
        self::assertStringNotContainsString('function button(', $source);
        self::assertStringNotContainsString('function section(', $source);
    }

    public function test_system_tests_ui_supports_sequential_module_execution(): void
    {
        $component = (string) file_get_contents(BASE_PATH . '/app/View/Components/Admin.php');
        $javascript = (string) file_get_contents(BASE_PATH . '/public/assets/js/system-tests.js');
        $styles = (string) file_get_contents(BASE_PATH . '/public/assets/css/system-tests.css');

        foreach ([
            'data-health-module-card',
            'data-health-module-label',
            'data-health-card-progress',
            'data-health-gauge-caption',
        ] as $attribute) {
            self::assertStringContainsString($attribute, $component);
        }

        self::assertStringContainsString('async function runAllSequentially()', $javascript);
        self::assertStringContainsString('await runOne(', $javascript);
        self::assertStringContainsString('renderGlobalReport()', $javascript);
        self::assertStringContainsString('.health-module-card.is-focus', $styles);
        self::assertStringContainsString('.health-module-card.is-muted', $styles);
    }
}
