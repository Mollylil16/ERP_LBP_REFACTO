<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;

final class Form
{
    /** @param array<string,mixed> $attrs */
    public static function field(string $label, string $control, array $attrs = []): string
    {
        $class = Html::classes(['finea-field', (string) ($attrs['fieldClass'] ?? $attrs['class'] ?? '')]);
        $hint = isset($attrs['hint']) ? '<small class="finea-field-hint">' . View::e((string) $attrs['hint']) . '</small>' : '';
        $error = isset($attrs['error']) ? '<small class="finea-field-error">' . View::e((string) $attrs['error']) . '</small>' : '';
        $required = !empty($attrs['required']) ? ' <span class="finea-required">*</span>' : '';
        $id = isset($attrs['for']) ? ' for="' . View::e((string) $attrs['for']) . '"' : '';

        return '<div class="' . View::e($class) . '"><label' . $id . '>' . View::e($label) . $required . '</label>' . $control . $hint . $error . '</div>';
    }

    /**
     * API moderne : Form::input('name', ['label' => 'Nom', 'value' => '...'])
     * API compatible : Form::input('name', 'Nom', '...', ['required' => true])
     *
     * @param string|array<string,mixed> $labelOrOptions
     * @param array<string,mixed> $attrs
     */
    public static function input(string $name, string|array $labelOrOptions = [], mixed $value = '', array $attrs = []): string
    {
        [$label, $value, $attrs] = self::normalizeFieldArguments($labelOrOptions, $value, $attrs);
        $type = (string) ($attrs['type'] ?? 'text');
        unset($attrs['type']);

        $fieldAttrs = self::extractFieldAttrs($attrs);
        $id = (string) ($attrs['id'] ?? self::id($name));

        $inputAttrs = array_merge([
            'class' => Html::classes(['finea-input', (string) ($attrs['class'] ?? '')]),
            'type' => $type,
            'name' => $name,
            'id' => $id,
            'value' => (string) $value,
        ], $attrs);

        $fieldAttrs['for'] = $id;

        return self::field($label, '<input' . Html::attrs($inputAttrs) . '>', $fieldAttrs);
    }

    /** @param string|array<string,mixed> $labelOrOptions @param array<string,mixed> $attrs */
    public static function textarea(string $name, string|array $labelOrOptions = [], mixed $value = '', array $attrs = []): string
    {
        [$label, $value, $attrs] = self::normalizeFieldArguments($labelOrOptions, $value, $attrs);
        $fieldAttrs = self::extractFieldAttrs($attrs);
        $id = (string) ($attrs['id'] ?? self::id($name));

        $textareaAttrs = array_merge([
            'class' => Html::classes(['finea-textarea', 'finea-input', (string) ($attrs['class'] ?? '')]),
            'name' => $name,
            'id' => $id,
            'rows' => $attrs['rows'] ?? 4,
        ], $attrs);

        $fieldAttrs['for'] = $id;

        return self::field($label, '<textarea' . Html::attrs($textareaAttrs) . '>' . View::e((string) $value) . '</textarea>', $fieldAttrs);
    }

    /**
     * API moderne : Form::select('name', $options, $selected, ['label' => '...'])
     * API compatible : Form::select('name', 'Label', $options, $selected, $attrs)
     *
     * @param string|array<int,array{value:mixed,label:mixed}> $labelOrOptions
     * @param mixed $optionsOrSelected
     * @param mixed $selectedOrAttrs
     * @param array<string,mixed> $attrs
     */
    public static function select(string $name, string|array $labelOrOptions, mixed $optionsOrSelected = [], mixed $selectedOrAttrs = null, array $attrs = []): string
    {
        [$label, $options, $selected, $attrs] = self::normalizeChoiceArguments($labelOrOptions, $optionsOrSelected, $selectedOrAttrs, $attrs);
        $fieldAttrs = self::extractFieldAttrs($attrs);
        $id = (string) ($attrs['id'] ?? self::id($name));

        $selectAttrs = array_merge([
            'class' => Html::classes(['finea-select', (string) ($attrs['class'] ?? '')]),
            'name' => $name,
            'id' => $id,
        ], $attrs);

        $fieldAttrs['for'] = $id;
        $html = '<select' . Html::attrs($selectAttrs) . '>' . self::options($options, $selected) . '</select>';

        return self::field($label, $html, $fieldAttrs);
    }

    /**
     * API moderne : Form::selectSearch('name', $options, $selected, ['label' => '...'])
     * API compatible : Form::selectSearch('name', 'Label', $options, $selected, $attrs)
     *
     * @param string|array<int,array{value:mixed,label:mixed}> $labelOrOptions
     * @param mixed $optionsOrSelected
     * @param mixed $selectedOrAttrs
     * @param array<string,mixed> $attrs
     */
    public static function selectSearch(string $name, string|array $labelOrOptions, mixed $optionsOrSelected = [], mixed $selectedOrAttrs = null, array $attrs = []): string
    {
        [$label, $options, $selected, $attrs] = self::normalizeChoiceArguments($labelOrOptions, $optionsOrSelected, $selectedOrAttrs, $attrs);
        $fieldAttrs = self::extractFieldAttrs($attrs);
        $multiple = !empty($attrs['multiple']);
        $selectName = $multiple && !str_ends_with($name, '[]') ? $name . '[]' : $name;
        $id = (string) ($attrs['id'] ?? self::id($name));
        $placeholder = (string) ($attrs['placeholder'] ?? 'Rechercher et sélectionner...');

        unset($attrs['placeholder']);

        $selectAttrs = array_merge([
            'class' => Html::classes(['finea-native-select', 'finea-select-search-source', (string) ($attrs['class'] ?? '')]),
            'name' => $selectName,
            'id' => $id,
            'data-finea-select-search' => '1',
            'data-placeholder' => $placeholder,
            'style' => 'position:absolute!important;opacity:0!important;pointer-events:none!important;width:1px!important;height:1px!important;overflow:hidden!important;',
            'tabindex' => '-1',
            'aria-hidden' => 'true',
        ], $attrs);

        if ($multiple) {
            $selectAttrs['multiple'] = true;
            $selectAttrs['data-multiple'] = '1';
        }

        $fieldAttrs['for'] = $id;
        $html = '<select' . Html::attrs($selectAttrs) . '>' . self::options($options, $selected) . '</select>';

        return self::field($label, $html, $fieldAttrs);
    }

    /** @param string|array<string,mixed> $labelOrOptions @param array<string,mixed> $attrs */
    public static function checkbox(string $name, string|array $labelOrOptions = [], bool $checked = false, array $attrs = []): string
    {
        if (is_array($labelOrOptions)) {
            $attrs = $labelOrOptions;
            $label = (string) ($attrs['label'] ?? $name);
            $checked = (bool) ($attrs['checked'] ?? $checked);
            unset($attrs['label'], $attrs['checked']);
        } else {
            $label = $labelOrOptions;
        }

        $fieldAttrs = self::extractFieldAttrs($attrs);
        $id = (string) ($attrs['id'] ?? self::id($name));

        $inputAttrs = array_merge([
            'class' => Html::classes(['finea-checkbox-input', (string) ($attrs['class'] ?? '')]),
            'type' => 'checkbox',
            'name' => $name,
            'id' => $id,
            'value' => $attrs['value'] ?? '1',
            'checked' => $checked,
        ], $attrs);

        return '<div class="' . View::e(Html::classes(['finea-field', 'finea-check-field', (string) ($fieldAttrs['fieldClass'] ?? '')])) . '"><label class="finea-checkbox" for="' . View::e($id) . '"><input' . Html::attrs($inputAttrs) . '><span>' . View::e($label) . '</span></label></div>';
    }

    /** @param array<string,mixed> $attrs */
    public static function hidden(string $name, mixed $value = '', array $attrs = []): string
    {
        $attrs = array_merge(['type' => 'hidden', 'name' => $name, 'value' => (string) $value], $attrs);
        return '<input' . Html::attrs($attrs) . '>';
    }

    /** @param array<string,mixed> $attrs */
    public static function inputControl(string $name, array $attrs = []): string
    {
        $attrs = array_merge([
            'class' => Html::classes(['finea-input', (string) ($attrs['class'] ?? '')]),
            'type' => (string) ($attrs['type'] ?? 'text'),
            'name' => $name,
        ], $attrs);

        return '<input' . Html::attrs($attrs) . '>';
    }

    /**
     * @param array<int,string> $palette
     * @param array<string,mixed> $attrs
     */
    public static function colorPalette(
        string $name,
        string $label,
        string $value,
        array $palette = [],
        array $attrs = [],
    ): string {
        $palette = $palette !== [] ? $palette : [
            '#111c44', '#1d2b57', '#2563eb', '#0891b2', '#0f766e',
            '#16a34a', '#ca8a04', '#ffcc00', '#ea580c', '#d40511',
            '#be123c', '#7c3aed', '#334155', '#64748b', '#f5f7fb',
        ];
        $id = (string) ($attrs['id'] ?? self::id($name));
        $swatches = '';
        foreach ($palette as $color) {
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                continue;
            }
            $swatches .= '<button type="button" class="finea-color-swatch'
                . (strtolower($color) === strtolower($value) ? ' is-selected' : '')
                . '" style="--swatch:' . View::e($color) . '" data-color-value="'
                . View::e(strtolower($color)) . '" aria-label="Choisir ' . View::e($color) . '"></button>';
        }

        return '<div class="finea-field finea-color-field" data-color-picker>'
            . '<label for="' . View::e($id) . '">' . View::e($label) . '</label>'
            . '<div class="finea-color-control"><input type="color" id="' . View::e($id)
            . '" name="' . View::e($name) . '" value="' . View::e($value)
            . '" data-color-input><output data-color-output>' . View::e(strtoupper($value)) . '</output></div>'
            . '<div class="finea-color-palette">' . $swatches . '</div></div>';
    }

    /** @param array<string,mixed> $attrs */
    public static function dropzone(string $name, string $label, array $attrs = []): string
    {
        $accept = $attrs['accept'] ?? null;
        $hint = (string) ($attrs['hint'] ?? 'Glisser-déposer un fichier ici ou cliquer pour parcourir.');
        $preview = (string) ($attrs['preview'] ?? '');
        $required = !empty($attrs['required']);
        $multiple = !empty($attrs['multiple']);
        $inputName = $multiple && !str_ends_with($name, '[]') ? $name . '[]' : $name;

        $inputAttrs = [
            'type' => 'file',
            'name' => $inputName,
            'accept' => $accept,
            'required' => $required,
            'multiple' => $multiple,
        ];

        return '<label class="finea-dropzone ' . View::e((string) ($attrs['class'] ?? '')) . '" data-finea-dropzone>'
            . '<input' . Html::attrs($inputAttrs) . '>'
            . '<span class="finea-dropzone-icon">⇪</span>'
            . '<strong>' . View::e($label) . '</strong>'
            . '<span>' . View::e($hint) . '</span>'
            . '<div class="finea-file-preview" data-finea-file-preview>' . View::e($preview) . '</div>'
            . '</label>';
    }

    /** @param array<int,array{value:mixed,label:mixed,attrs?:array<string,mixed>}> $options */
    private static function options(array $options, mixed $selected): string
    {
        $selectedValues = is_array($selected) ? array_map('strval', $selected) : [(string) $selected];
        $html = '';

        foreach ($options as $option) {
            $value = (string) ($option['value'] ?? '');
            $label = (string) ($option['label'] ?? $value);
            $attrs = is_array($option['attrs'] ?? null) ? $option['attrs'] : [];
            $attrs = array_merge(['value' => $value], $attrs);
            if (in_array($value, $selectedValues, true)) {
                $attrs['selected'] = true;
            }
            $html .= '<option' . Html::attrs($attrs) . '>' . View::e($label) . '</option>';
        }

        return $html;
    }

    /** @param string|array<string,mixed> $labelOrOptions @param array<string,mixed> $attrs @return array{0:string,1:mixed,2:array<string,mixed>} */
    private static function normalizeFieldArguments(string|array $labelOrOptions, mixed $value, array $attrs): array
    {
        if (is_array($labelOrOptions)) {
            $attrs = $labelOrOptions;
            $label = (string) ($attrs['label'] ?? '');
            $value = $attrs['value'] ?? $value;
            unset($attrs['label'], $attrs['value']);
            return [$label, $value, $attrs];
        }

        return [$labelOrOptions, $value, $attrs];
    }

    /** @param string|array<int,array{value:mixed,label:mixed}> $labelOrOptions @param mixed $optionsOrSelected @param mixed $selectedOrAttrs @param array<string,mixed> $attrs @return array{0:string,1:array<int,array{value:mixed,label:mixed}>,2:mixed,3:array<string,mixed>} */
    private static function normalizeChoiceArguments(string|array $labelOrOptions, mixed $optionsOrSelected, mixed $selectedOrAttrs, array $attrs): array
    {
        if (is_array($labelOrOptions)) {
            $options = $labelOrOptions;
            $selected = $optionsOrSelected;
            if (is_array($selectedOrAttrs)) {
                $attrs = $selectedOrAttrs;
            }
            $label = (string) ($attrs['label'] ?? '');
            unset($attrs['label']);
            return [$label, $options, $selected, $attrs];
        }

        $options = is_array($optionsOrSelected) ? $optionsOrSelected : [];
        return [$labelOrOptions, $options, $selectedOrAttrs, $attrs];
    }

    /** @param array<string,mixed> $attrs @return array<string,mixed> */
    private static function extractFieldAttrs(array &$attrs): array
    {
        $field = [];
        foreach (['hint', 'error', 'required', 'fieldClass'] as $key) {
            if (array_key_exists($key, $attrs)) {
                $field[$key] = $attrs[$key];
                if ($key !== 'required') {
                    unset($attrs[$key]);
                }
            }
        }
        return $field;
    }

    private static function id(string $name): string
    {
        return 'field_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $name);
    }
}
