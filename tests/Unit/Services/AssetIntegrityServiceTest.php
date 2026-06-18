<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\AssetIntegrityService;
use Tests\TestCase;

final class AssetIntegrityServiceTest extends TestCase
{
    public function test_extracts_local_assets_without_duplicates(): void
    {
        $assets = (new AssetIntegrityService())->extract(
            '<link href="<?= View::asset(\'css/rh.css\') ?>"><script src="/assets/js/rh.js"></script><script src="/assets/js/rh.js"></script>'
        );
        self::assertSame(['css/rh.css', 'js/rh.js'], $assets);
    }

    public function test_reports_missing_assets(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'asset-');
        file_put_contents($file, '<script src="/assets/js/absent.js"></script>');
        try {
            $result = (new AssetIntegrityService())->inspect([$file]);
            self::assertSame(1, $result['checked']);
            self::assertSame('js/absent.js', $result['broken'][0]['asset']);
        } finally {
            @unlink($file);
        }
    }
}
