<?php

declare(strict_types=1);

namespace App\View\Components;

final class ContractAllowanceRow
{
    /** @param array<string,mixed> $allowance */
    public static function render(int|string $index, array $allowance = []): string
    {
        $name = 'allowance_taxable[' . $index . ']';

        return '<div class="rh-allowance-row" data-contract-allowance-row>'
            . Form::input('allowance_name[]', [
                'label' => 'Libellé',
                'placeholder' => 'Transport, logement...',
                'value' => (string) ($allowance['name'] ?? ''),
            ])
            . Form::input('allowance_amount[]', [
                'label' => 'Montant',
                'type' => 'number',
                'step' => '0.01',
                'min' => '0',
                'value' => (string) ($allowance['amount'] ?? ''),
            ])
            . Form::checkbox($name, [
                'label' => 'Imposable ITS',
                'checked' => !empty($allowance['is_taxable']),
                'data-contract-taxable' => true,
            ])
            . Ui::button('Retirer', [
                'variant' => 'danger',
                'type' => 'button',
                'data-contract-remove-allowance' => true,
            ])
            . '</div>';
    }
}
