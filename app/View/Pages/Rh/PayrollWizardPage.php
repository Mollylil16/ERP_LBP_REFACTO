<?php

declare(strict_types=1);

namespace App\View\Pages\Rh;

final class PayrollWizardPage
{
    /**
     * @param array<int,array<string,mixed>> $employees
     * @param array<int,array<string,mixed>> $periods
     * @param array<int,array<string,mixed>> $contractRules   Coefficients par type de contrat
     * @param array<int,array<string,mixed>> $lineItems       Catalogue des rubriques de paie
     * @param array<string,mixed>            $payrollSettings Taux sociaux/fiscaux globaux
     * @param array<int,array<string,mixed>> $employeeContracts Contrats actifs indexés par employee_id
     * @param array<int,array<string,mixed>> $attendanceSummaries Résumés mensuels de pointages
     */
    public function __construct(
        public readonly array $employees = [],
        public readonly array $periods = [],
        public readonly ?int $selectedEmployeeId = null,
        public readonly ?int $selectedPeriodId = null,
        public readonly array $contractRules = [],
        public readonly array $lineItems = [],
        public readonly array $payrollSettings = [],
        public readonly array $employeeContracts = [],
        public readonly array $attendanceSummaries = [],
    ) {
    }
}
