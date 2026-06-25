<?php

declare(strict_types=1);

namespace App\Repositories\Rh;

use PDO;

final class RhContractRulesRepository
{
    public function __construct(private PDO $pdo) {}

    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        return $this->pdo->query("
            SELECT id, contract_type, trial_duration_days, max_renewals, alert_days_before_end, is_active
            FROM rh_contract_rules
            ORDER BY is_active DESC, contract_type ASC
        ")->fetchAll() ?: [];
    }

    public function save(array $data): void
    {
        $id = (int) ($data['id'] ?? 0);
        $type = trim((string) ($data['contract_type'] ?? ''));
        $trialDays = (int) ($data['trial_duration_days'] ?? 0);
        $maxRenewals = (int) ($data['max_renewals'] ?? 0);
        $alertDays = (int) ($data['alert_days_before_end'] ?? 30);

        if ($type === '') {
            throw new \RuntimeException('Le type de contrat est obligatoire.');
        }

        if ($id > 0) {
            $stmt = $this->pdo->prepare("
                UPDATE rh_contract_rules
                SET contract_type = :type, trial_duration_days = :trial, max_renewals = :renew, alert_days_before_end = :alert, updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                'type' => $type,
                'trial' => $trialDays,
                'renew' => $maxRenewals,
                'alert' => $alertDays,
                'id' => $id,
            ]);
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO rh_contract_rules (contract_type, trial_duration_days, max_renewals, alert_days_before_end, is_active, created_at)
                VALUES (:type, :trial, :renew, :alert, 1, NOW())
            ");
            $stmt->execute([
                'type' => $type,
                'trial' => $trialDays,
                'renew' => $maxRenewals,
                'alert' => $alertDays,
            ]);
        }
    }

    public function toggle(int $id): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE rh_contract_rules
            SET is_active = IF(is_active = 1, 0, 1), updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute(['id' => $id]);
    }
}
