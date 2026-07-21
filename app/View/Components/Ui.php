<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;

final class Ui
{
    /**
     * API moderne : Ui::pageHeader('Titre', 'Sous-titre', ['eyebrow' => '...', 'actions' => [...]])
     * API compatible : Ui::pageHeader('Eyebrow', 'Titre', 'Sous-titre', 'Actions', ['class' => '...'])
     *
     * @param string|array<string,mixed> $subtitleOrOptions
     * @param string|array<int,string> $actions
     * @param array<string,mixed> $attrs
     */
    public static function pageHeader(
        string $titleOrEyebrow,
        string $subtitleOrTitle = '',
        string|array $subtitleOrOptions = '',
        string|array $actions = '',
        array $attrs = []
    ): string
    {
        if (is_array($subtitleOrOptions)) {
            $options = $subtitleOrOptions;
            $title = $titleOrEyebrow;
            $subtitle = $subtitleOrTitle;
            $eyebrow = (string) ($options['eyebrow'] ?? '');
            $actions = $options['actions'] ?? '';
            if (!is_string($actions) && !is_array($actions)) {
                $actions = '';
            }
            $attrs = $options;
            unset($attrs['eyebrow'], $attrs['actions']);
        } else {
            $eyebrow = $titleOrEyebrow;
            $title = $subtitleOrTitle;
            $subtitle = $subtitleOrOptions;
        }

        $class = Html::classes(['finea-page-header', (string) ($attrs['class'] ?? '')]);
        $eyebrowHtml = $eyebrow !== '' ? '<p class="rh-eyebrow">' . View::e($eyebrow) . '</p>' : '';
        $actionsHtml = '';
        $renderedActions = is_array($actions)
            ? implode('', array_filter($actions, 'is_string'))
            : $actions;

        if ($renderedActions !== '') {
            $actionsHtml = str_contains($renderedActions, 'class="finea-header-actions"')
                ? $renderedActions
                : '<div class="finea-header-actions">' . $renderedActions . '</div>';
        }

        return '<section class="' . View::e($class) . '"><div>' . $eyebrowHtml . '<h1>' . View::e($title) . '</h1>'
            . ($subtitle !== '' ? '<p>' . View::e($subtitle) . '</p>' : '')
            . '</div>' . $actionsHtml . '</section>';
    }

    /** @param array<string,mixed> $attrs */
    public static function section(string $title, string $content, string $subtitle = '', array $attrs = []): string
    {
        $attrs['class'] = Html::classes(['finea-section-card', (string) ($attrs['class'] ?? '')]);

        return '<section' . Html::attrs($attrs) . '><div class="finea-section-heading"><h2 class="finea-section-title">' . View::e($title) . '</h2>'
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
        $disabled = false;
        $customClass = '';
        $options = [];

        if (is_array($hrefOrOptions)) {
            $options = $hrefOrOptions;
            $href = (string) ($options['href'] ?? '');
            $variant = (string) ($options['variant'] ?? 'primary');
            $type = (string) ($options['type'] ?? $type);
            $disabled = (bool) ($options['disabled'] ?? false);
            $customClass = (string) ($options['class'] ?? '');
        } elseif (is_array($variantOrOptions)) {
            $options = $variantOrOptions;
            $href = (string) ($hrefOrOptions ?? '');
            $variant = (string) ($options['variant'] ?? 'primary');
            $type = (string) ($options['type'] ?? $type);
            $disabled = (bool) ($options['disabled'] ?? false);
            $customClass = (string) ($options['class'] ?? '');
        } else {
            $href = (string) ($hrefOrOptions ?? '');
            $variant = $variantOrOptions;
        }

        $class = Html::classes([
            'finea-action-btn',
            'finea-action-btn--' . preg_replace('/[^a-z0-9_-]/i', '', $variant),
            $customClass,
        ]);

        if ($href !== '') {
            return '<a class="' . View::e($class) . '" href="' . View::url(ltrim($href, '/')) . '">' . View::e($label) . '</a>';
        }

        unset($options['href'], $options['variant'], $options['type'], $options['disabled'], $options['class']);
        $buttonAttrs = array_merge([
            'class' => $class,
            'type' => $type,
            'disabled' => $disabled,
        ], $options);

        return '<button' . Html::attrs($buttonAttrs) . '>' . View::e($label) . '</button>';
    }

    /** @param array<string,mixed> $options */
    public static function badge(string $label, string $tone = 'neutral', array $options = []): string
    {
        $unstyled = (bool) ($options['unstyled'] ?? false);
        $customClass = (string) ($options['class'] ?? '');

        $class = Html::classes([
            'finea-badge' => !$unstyled,
            'finea-badge--' . preg_replace('/[^a-z0-9_-]/i', '', $tone) => !$unstyled,
            $customClass,
        ]);

        return '<span class="' . View::e($class) . '">' . View::e($label) . '</span>';
    }

    public static function emptyState(string $title, string $message = ''): string
    {
        return '<div class="finea-empty-state"><strong>' . View::e($title) . '</strong>' . ($message !== '' ? '<p>' . View::e($message) . '</p>' : '') . '</div>';
    }

    /**
     * Generic modal dialog component using standard design system buttons and styles.
     *
     * @param array<string,mixed> $options  Optional: 'btnLabel', 'btnVariant', 'formId'
     */
    public static function modal(string $id, string $title, string $fieldsHtml, string $action = '', array $options = []): string
    {
        $btnLabel = $options['btnLabel'] ?? 'Enregistrer';
        $btnVariant = $options['btnVariant'] ?? 'accent';
        $formId = $options['formId'] ?? '';
        $formIdAttr = $formId !== '' ? ' id="' . View::e($formId) . '"' : '';

        $closeBtn = self::button('Fermer', [
            'variant' => 'secondary',
            'type' => 'button',
            'onclick' => "document.getElementById('" . View::e($id) . "').style.display='none'",
        ]);
        $submitBtn = self::button($btnLabel, [
            'variant' => $btnVariant,
            'type' => 'submit',
        ]);

        return '<div id="' . View::e($id) . '" style="display:none; position:fixed; inset:0; background:rgba(15, 23, 42, 0.6); z-index:999; justify-content:center; align-items:center; padding:1.5rem; backdrop-filter:blur(4px);">'
            . '<div class="finea-section-card" style="max-width:550px; width:100%; margin:0; box-shadow:0 25px 50px -12px rgba(0, 0, 0, 0.25); border-radius:12px; padding:1.75rem;">'
            . '<h3 class="finea-section-title" style="margin-bottom:1.25rem; font-size:1.25rem; font-weight:700; border-bottom:1px solid #e2e8f0; padding-bottom:0.75rem;">' . View::e($title) . '</h3>'
            . '<form method="post" action="' . ($action !== '' ? $action : '') . '"' . $formIdAttr . '>'
            . '<div class="rh-form-grid" style="gap:1.25rem; margin-bottom:1.5rem;">' . $fieldsHtml . '</div>'
            . '<div style="display:flex; justify-content:flex-end; gap:0.75rem; border-top:1px solid #e2e8f0; padding-top:1rem;">'
            . $closeBtn
            . $submitBtn
            . '</div></form></div>div>';
    }
}
