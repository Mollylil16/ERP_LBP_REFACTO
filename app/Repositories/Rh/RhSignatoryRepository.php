<?php

declare(strict_types=1);

namespace App\Repositories\Rh;

use PDO;

final class RhSignatoryRepository
{
    public function __construct(private PDO $pdo) {}

    /** @return array<string,mixed> */
    public function getSettings(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM rh_contract_settings LIMIT 1");
        return $stmt->fetch() ?: [];
    }

    public function saveSettings(array $data): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE rh_contract_settings
            SET employer_name = :employer_name,
                legal_form = :legal_form,
                capital_mention = :capital_mention,
                address = :address,
                rccm = :rccm,
                representation_text = :representation_text,
                signature_city = :signature_city,
                dg_signatory_name = :dg_signatory_name,
                dg_title = :dg_title,
                rh_signatory_name = :rh_signatory_name,
                rh_title = :rh_title,
                footer_line1 = :footer_line1,
                footer_line2 = :footer_line2,
                updated_at = NOW()
            WHERE id = 1
        ");
        $stmt->execute([
            'employer_name' => trim((string)($data['employer_name'] ?? '')),
            'legal_form' => trim((string)($data['legal_form'] ?? '')),
            'capital_mention' => trim((string)($data['capital_mention'] ?? '')),
            'address' => trim((string)($data['address'] ?? '')),
            'rccm' => trim((string)($data['rccm'] ?? '')),
            'representation_text' => trim((string)($data['representation_text'] ?? '')),
            'signature_city' => trim((string)($data['signature_city'] ?? '')),
            'dg_signatory_name' => trim((string)($data['dg_signatory_name'] ?? '')),
            'dg_title' => trim((string)($data['dg_title'] ?? '')),
            'rh_signatory_name' => trim((string)($data['rh_signatory_name'] ?? '')),
            'rh_title' => trim((string)($data['rh_title'] ?? '')),
            'footer_line1' => trim((string)($data['footer_line1'] ?? '')),
            'footer_line2' => trim((string)($data['footer_line2'] ?? '')),
        ]);
    }
}
