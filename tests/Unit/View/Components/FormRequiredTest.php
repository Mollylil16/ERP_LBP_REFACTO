<?php

declare(strict_types=1);

namespace Tests\Unit\View\Components;

use App\View\Components\Form;
use Tests\TestCase;

final class FormRequiredTest extends TestCase
{
    public function test_required_attribute_is_applied_to_input_and_label(): void
    {
        $html = Form::input('name', 'Nom', '', ['required' => true]);
        self::assertStringContainsString(' required', $html);
        self::assertStringContainsString('finea-required', $html);
    }

    public function test_required_attribute_is_applied_to_select(): void
    {
        $html = Form::select('type', 'Type', [['value' => 'a', 'label' => 'A']], null, ['required' => true]);
        self::assertStringContainsString('<select', $html);
        self::assertStringContainsString(' required', $html);
    }
}
