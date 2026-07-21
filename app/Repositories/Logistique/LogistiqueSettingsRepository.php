<?php

declare(strict_types=1);

namespace App\Repositories\Logistique;

use App\Models\Logistique\LogistiqueSettings;
use PDO;

class LogistiqueSettingsRepository
{
    public function __construct(private PDO $pdo) {}

    public function getSettings(?int $agenceId = null): LogistiqueSettings
    {
        if ($agenceId !== null && $agenceId > 0) {
            $stmt = $this->pdo->prepare("SELECT * FROM logistique_settings WHERE agence_id = :agence_id");
            $stmt->execute(['agence_id' => $agenceId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return LogistiqueSettings::fromArray($row);
            }
        }

        // Fallback global settings (agence_id IS NULL)
        $stmtGlobal = $this->pdo->query("SELECT * FROM logistique_settings WHERE agence_id IS NULL LIMIT 1");
        $rowGlobal = $stmtGlobal ? $stmtGlobal->fetch(PDO::FETCH_ASSOC) : null;

        if ($rowGlobal) {
            return LogistiqueSettings::fromArray($rowGlobal);
        }

        return LogistiqueSettings::fromArray([
            'id' => 0,
            'agence_id' => null,
            'delai_gratuit_jours' => 7,
            'frais_gardiennage_par_jour' => 500.0,
            'auto_assign_rayon' => 1,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function saveSettings(array $data, ?int $agenceId = null): bool
    {
        $existing = $this->getSettings($agenceId);

        $delaiGratuit = max(0, (int) ($data['delai_gratuit_jours'] ?? 7));
        $fraisParJour = max(0.0, (float) ($data['frais_gardiennage_par_jour'] ?? 500.0));
        $autoAssign = !empty($data['auto_assign_rayon']) ? 1 : 0;

        if ($existing->id > 0) {
            $stmt = $this->pdo->prepare("
                UPDATE logistique_settings
                SET delai_gratuit_jours = :delai,
                    frais_gardiennage_par_jour = :frais,
                    auto_assign_rayon = :auto_assign,
                    updated_at = NOW()
                WHERE id = :id
            ");
            return $stmt->execute([
                'id' => $existing->id,
                'delai' => $delaiGratuit,
                'frais' => $fraisParJour,
                'auto_assign' => $autoAssign,
            ]);
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO logistique_settings (agence_id, delai_gratuit_jours, frais_gardiennage_par_jour, auto_assign_rayon, created_at)
            VALUES (:agence_id, :delai, :frais, :auto_assign, NOW())
        ");
        return $stmt->execute([
            'agence_id' => $agenceId,
            'delai' => $delaiGratuit,
            'frais' => $fraisParJour,
            'auto_assign' => $autoAssign,
        ]);
    }
}
