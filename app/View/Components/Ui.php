<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;

final class Ui
{
    /**
     * API moderne : Ui::pageHeader('Titre', 'Sous-titre', ['eyebrow' => '...', 'actions' => '...'])
     * API compatible : Ui::pageHeader('Eyebrow', 'Titre', 'Sous-titre', 'Actions', ['class' => '...'])
     *
     * @param string|array<string,mixed> $subtitleOrOptions
     * @param array<string,mixed> $attrs
     */
    public static function pageHeader(string $titleOrEyebrow, string $subtitleOrTitle = '', string|array $subtitleOrOptions = '', string $actions = '', array $attrs = []): string
    {
        if (is_array($subtitleOrOptions)) {
            $options = $subtitleOrOptions;
            $title = $titleOrEyebrow;
            $subtitle = $subtitleOrTitle;
            $eyebrow = (string) ($options['eyebrow'] ?? '');
            $actions = (string) ($options['actions'] ?? '');
            $attrs = $options;
            unset($attrs['eyebrow'], $attrs['actions']);
        } else {
            $eyebrow = $titleOrEyebrow;
            $title = $subtitleOrTitle;
            $subtitle = $subtitleOrOptions;
        }

        $class = Html::classes(['finea-page-header', (string) ($attrs['class'] ?? '')]);
        $eyebrowHtml = $eyebrow !== '' ? '<p class="rh-eyebrow">' . View::e($eyebrow) . '</p>' : '';

        return '<section class="' . View::e($class) . '"><div>' . $eyebrowHtml . '<h1>' . View::e($title) . '</h1>'
            . ($subtitle !== '' ? '<p>' . View::e($subtitle) . '</p>' : '')
            . '</div>' . $actions . '</section>';
    }

    /** @param array<string,mixed> $attrs */
    public static function section(string $title, string $content, string $subtitle = '', array $attrs = []): string
    {
        $class = Html::classes(['finea-section-card', (string) ($attrs['class'] ?? '')]);

        $id = isset($attrs['id']) && $attrs['id'] !== '' ? ' id="' . View::e((string) $attrs['id']) . '"' : '';

        return '<section class="' . View::e($class) . '"' . $id . '><div class="finea-section-heading"><h2 class="finea-section-title">' . View::e($title) . '</h2>'
            . ($subtitle !== '' ? '<span>' . View::e($subtitle) . '</span>' : '')
            . '</div>' . $content . '</section>';
    }

    /**
     * API moderne : Ui::button('Label', ['href' => '...', 'variant' => 'secondary'])
     * API compatible : Ui::button('Label', 'url', 'secondary', 'button')
     *
     * @param string|array<string,mixed>|null $hrefOrOptions
     * @param string|array<string,mixed> $variantOrOptions
     */
    public static function button(string $label, string|array|null $hrefOrOptions = '', string|array $variantOrOptions = 'primary', string $type = 'button'): string
    {
        if (is_array($hrefOrOptions)) {
            $options = $hrefOrOptions;
            $href = (string) ($options['href'] ?? '');
            $variant = (string) ($options['variant'] ?? 'primary');
            $type = (string) ($options['type'] ?? $type);
        } elseif (is_array($variantOrOptions)) {
            $options = $variantOrOptions;
            $href = (string) ($hrefOrOptions ?? '');
            $variant = (string) ($options['variant'] ?? 'primary');
            $type = (string) ($options['type'] ?? $type);
        } else {
            $href = (string) ($hrefOrOptions ?? '');
            $variant = $variantOrOptions;
        }

        $class = 'finea-action-btn finea-action-btn--' . preg_replace('/[^a-z0-9_-]/i', '', $variant);

        if ($href !== '') {
            return '<a class="' . View::e($class) . '" href="' . View::url(ltrim($href, '/')) . '">' . View::e($label) . '</a>';
        }

        return '<button class="' . View::e($class) . '" type="' . View::e($type) . '">' . View::e($label) . '</button>';
    }

    public static function badge(string $label, string $tone = 'neutral'): string
    {
        return '<span class="finea-badge finea-badge--' . View::e($tone) . '">' . View::e($label) . '</span>';
    }

    public static function emptyState(string $title, string $message = ''): string
    {
        return '<div class="finea-empty-state"><strong>' . View::e($title) . '</strong>' . ($message !== '' ? '<p>' . View::e($message) . '</p>' : '') . '</div>';
    }
}
