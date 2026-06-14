<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;

final class Html
{
    /** @param array<string,mixed> $attrs */
    public static function attrs(array $attrs): string
    {
        $html = '';
        foreach ($attrs as $key => $value) {
            if ($value === null || $value === false) {
                continue;
            }
            if ($value === true) {
                $html .= ' ' . View::e((string) $key);
                continue;
            }
            if (is_array($value)) {
                $value = self::classes($value);
            }
            $html .= ' ' . View::e((string) $key) . '="' . View::e((string) $value) . '"';
        }
        return $html;
    }

    /** @param string|array<int|string,mixed> $classes */
    public static function classes(string|array $classes): string
    {
        if (is_string($classes)) {
            return trim($classes);
        }

        $result = [];
        foreach ($classes as $key => $value) {
            if (is_int($key)) {
                if ($value !== null && $value !== false && trim((string) $value) !== '') {
                    $result[] = trim((string) $value);
                }
                continue;
            }

            if ($value) {
                $result[] = trim((string) $key);
            }
        }

        return trim(implode(' ', array_unique($result)));
    }
}
