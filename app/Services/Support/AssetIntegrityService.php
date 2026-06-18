<?php

declare(strict_types=1);

namespace App\Services\Support;

final class AssetIntegrityService
{
    /** @param array<int, string> $files */
    public function inspect(array $files): array
    {
        $references = [];
        $broken = [];
        foreach ($files as $file) {
            if (!is_file($file)) continue;
            $content = file_get_contents($file);
            if (!is_string($content)) continue;
            foreach ($this->extract($content) as $asset) {
                $references[] = ['file' => $file, 'asset' => $asset];
                $path = BASE_PATH . '/public/assets/' . ltrim($asset, '/');
                if (!is_file($path)) $broken[] = ['file' => $file, 'asset' => $asset, 'resolved' => $path];
            }
        }
        return ['checked' => count($references), 'references' => $references, 'broken' => $broken];
    }

    /** @return array<int, string> */
    public function extract(string $content): array
    {
        $assets = [];
        foreach ([
            '/View::asset\(\s*[\'"]([^\'"]+)[\'"]\s*\)/',
            '/(?:href|src)\s*=\s*[\'"]\/?assets\/([^\'"?#]+)(?:[?#][^\'"]*)?[\'"]/i',
            '/[\'"]((?:css|js|images)\/[^\'"]+\.(?:css|js|png|jpe?g|webp|svg))[\'"]/i',
        ] as $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[1] as $asset) $assets[] = ltrim((string) $asset, '/');
            }
        }
        return array_values(array_unique($assets));
    }
}
