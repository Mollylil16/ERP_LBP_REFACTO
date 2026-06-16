<?php

namespace App\Services;

class RhPayrollEngine
{
    /**
     * Calcule le bulletin de paie complet selon la legislation ivoirienne.
     */
    public function calculate(array $employee, array $contract, array $allowances, array $params, array $attendances): array
    {
        $baseSalary = (float)$contract['base_salary'];
        
        // 1. Calcul des Heures Supplementaires
        $overtimeHours = 0;
        foreach ($attendances as $att) {
            $overtimeHours += (float)$att['overtime_hours'];
        }
        $hourlyRate = $baseSalary / 173.33;
        // Majoration simplifiee a 15% pour toutes les heures supp
        $overtimePay = round($overtimeHours * $hourlyRate * 1.15, 0);

        // 2. Calcul des Indemnites
        $taxableAllowances = 0;
        $nonTaxableAllowances = 0;
        $lines = [];

        foreach ($allowances as $allowance) {
            $amount = (float)$allowance['amount'];
            if ($allowance['is_taxable']) {
                $taxableAllowances += $amount;
            } else {
                $nonTaxableAllowances += $amount;
            }
            $lines[] = [
                'type' => 'gain',
                'label' => $allowance['name'],
                'base' => $amount,
                'rate' => null,
                'amount' => $amount,
                'is_taxable' => $allowance['is_taxable']
            ];
        }

        // 3. Salaire Brut
        $grossSalary = $baseSalary + $taxableAllowances + $overtimePay;
        $lines[] = ['type' => 'gain', 'label' => 'Salaire de Base', 'base' => $baseSalary, 'rate' => null, 'amount' => $baseSalary, 'is_taxable' => 1];
        if ($overtimePay > 0) {
            $lines[] = ['type' => 'gain', 'label' => 'Heures Supplementaires', 'base' => $overtimePay, 'rate' => null, 'amount' => $overtimePay, 'is_taxable' => 1];
        }

        // 4. Retenues Sociales (CNPS et CMU)
        $cnpsCeiling = (float)$params['cnps_ceiling'];
        $cnpsBase = min($grossSalary, $cnpsCeiling);
        $cnpsRate = (float)$params['cnps_employee_rate'];
        $cnpsDeduction = round($cnpsBase * ($cnpsRate / 100), 0);
        $lines[] = ['type' => 'deduction', 'label' => 'Retenue CNPS', 'base' => $cnpsBase, 'rate' => $cnpsRate, 'amount' => $cnpsDeduction, 'is_taxable' => 0];

        // CMU (Fixe a 1000 FCFA par personne en CI)
        $cmuDeduction = 1000;
        $lines[] = ['type' => 'deduction', 'label' => 'Retenue CMU', 'base' => 1000, 'rate' => null, 'amount' => $cmuDeduction, 'is_taxable' => 0];

        // 5. Impots sur les Traitements et Salaires (ITS)
        // L'assiette de l'impot est de 80% du salaire brut
        $taxBase = $grossSalary * 0.8;

        // IS (Impot sur Salaire) : 1.2%
        $isDeduction = round($taxBase * 0.012, 0);

        // CN (Contribution Nationale)
        $cnDeduction = $this->calculateCN($taxBase);

        // IGR (Impot General sur le Revenu)
        $parts = $this->calculateParts($employee['marital_status'] ?? 'Célibataire', (int)($employee['children_count'] ?? 0));
        $igrDeduction = $this->calculateIGR($taxBase, $isDeduction, $cnDeduction, $parts);

        $itsDeduction = $isDeduction + $cnDeduction + $igrDeduction;

        if ($isDeduction > 0) $lines[] = ['type' => 'deduction', 'label' => 'Impôt sur Salaire (IS)', 'base' => $taxBase, 'rate' => 1.2, 'amount' => $isDeduction, 'is_taxable' => 0];
        if ($cnDeduction > 0) $lines[] = ['type' => 'deduction', 'label' => 'Contribution Nationale (CN)', 'base' => $taxBase, 'rate' => null, 'amount' => $cnDeduction, 'is_taxable' => 0];
        if ($igrDeduction > 0) $lines[] = ['type' => 'deduction', 'label' => "IGR ($parts parts)", 'base' => $taxBase, 'rate' => null, 'amount' => $igrDeduction, 'is_taxable' => 0];

        // 6. Salaire Net
        $totalDeductions = $cnpsDeduction + $cmuDeduction + $itsDeduction;
        $netSalary = $grossSalary + $nonTaxableAllowances - $totalDeductions;

        return [
            'base_salary' => $baseSalary,
            'overtime_pay' => $overtimePay,
            'total_allowances' => $taxableAllowances + $nonTaxableAllowances,
            'gross_salary' => $grossSalary,
            'cnps_deduction' => $cnpsDeduction,
            'cmu_deduction' => $cmuDeduction,
            'its_deduction' => $itsDeduction,
            'net_salary' => $netSalary,
            'lines' => $lines
        ];
    }

    private function calculateCN(float $base): float
    {
        $cn = 0;
        if ($base > 200000) {
            $cn += ($base - 200000) * 0.10;
            $cn += 70000 * 0.05;
            $cn += 80000 * 0.015;
        } elseif ($base > 130000) {
            $cn += ($base - 130000) * 0.05;
            $cn += 80000 * 0.015;
        } elseif ($base > 50000) {
            $cn += ($base - 50000) * 0.015;
        }
        return round($cn, 0);
    }

    private function calculateParts(string $maritalStatus, int $childrenCount): float
    {
        $parts = 1.0; // Célibataire sans enfant
        if (in_array(strtolower($maritalStatus), ['marié', 'mariée', 'marie'])) {
            $parts = 2.0;
        }
        $parts += $childrenCount * 0.5;
        return min(5.0, $parts); // Maximum 5 parts en CI
    }

    private function calculateIGR(float $taxBase, float $is, float $cn, float $parts): float
    {
        $netForIgr = ($taxBase - $is - $cn) * 0.85;
        $quotient = $netForIgr / $parts;
        
        $igr = 0;
        if ($quotient > 840000) {
            $igr = ($quotient * 0.36) - 105652;
        } elseif ($quotient > 320000) {
            $igr = ($quotient * 0.28) - 38452;
        } elseif ($quotient > 180000) {
            $igr = ($quotient * 0.24) - 25652;
        } elseif ($quotient > 95000) {
            $igr = ($quotient * 0.20) - 18452;
        } elseif ($quotient > 45000) {
            $igr = ($quotient * 0.15) - 13702;
        } elseif ($quotient > 25000) {
            $igr = ($quotient * 0.10) - 11452;
        }

        $igrTotal = $igr * $parts;
        return max(0, round($igrTotal, 0));
    }
}
