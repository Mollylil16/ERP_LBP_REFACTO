<?php

declare(strict_types=1);

namespace App\Services;

final class FormIntegrityService
{
    public function inspectTemplate(string $source): array
    {
        $broken = [];
        $count = 0;
        if (preg_match_all('/<form\b([^>]*)>(.*?)<\/form>/is', $source, $forms, PREG_SET_ORDER)) {
            foreach ($forms as $index => $form) {
                $count++;
                $attributes = $form[1];
                $body = $form[2];
                preg_match('/\bmethod\s*=\s*["\']([^"\']+)["\']/i', $attributes, $methodMatch);
                $method = strtolower($methodMatch[1] ?? 'get');
                if (!preg_match('/\baction\s*=\s*["\'][^"\']+/i', $attributes)) {
                    $broken[] = ['form' => $index + 1, 'issue' => 'Action absente'];
                }
                if (!in_array($method, ['get', 'post'], true)) {
                    $broken[] = ['form' => $index + 1, 'issue' => 'Méthode invalide'];
                }
                if ($method === 'post' && !str_contains($body, '_csrf_token') && !str_contains($body, 'Csrf::input')) {
                    $broken[] = ['form' => $index + 1, 'issue' => 'Jeton CSRF absent'];
                }
            }
        }
        return ['forms' => $count, 'broken' => $broken];
    }

    public function inspectHtml(string $html): array
    {
        if (!class_exists(\DOMDocument::class)) {
            return ['forms' => 0, 'broken' => [], 'warning' => 'Extension DOM indisponible.'];
        }
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        $broken = [];
        $forms = $dom->getElementsByTagName('form');
        foreach ($forms as $index => $form) {
            $method = strtolower(trim($form->getAttribute('method')) ?: 'get');
            $action = trim($form->getAttribute('action'));
            if ($action === '') $broken[] = ['form' => $index + 1, 'issue' => 'Action absente'];
            if (!in_array($method, ['get', 'post'], true)) $broken[] = ['form' => $index + 1, 'issue' => 'Méthode invalide'];
            if ($method === 'post') {
                $hasCsrf = false;
                foreach ($form->getElementsByTagName('input') as $input) {
                    if ($input->getAttribute('name') === '_csrf_token') $hasCsrf = true;
                }
                if (!$hasCsrf) $broken[] = ['form' => $index + 1, 'issue' => 'Jeton CSRF absent'];
            }
        }
        return ['forms' => $forms->length, 'broken' => $broken, 'warning' => null];
    }
}
