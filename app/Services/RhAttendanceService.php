<?php

namespace App\Services;

use App\Repositories\RhAttendanceRepository;
use PDO;

class RhAttendanceService
{
    public function __construct(
        private RhAttendanceRepository $repository,
        private PDO $pdo
    ) {}

    public function importFromCsv(string $filePath): int
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new \RuntimeException("Fichier CSV introuvable ou illisible.");
        }

        $employees = $this->getEmployeeMap();
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \RuntimeException("Impossible d'ouvrir le fichier CSV.");
        }

        $imported = 0;
        $isFirstRow = true;
        
        while (($row = fgetcsv($handle, 1000, ',')) !== false) {
            // Ignore header
            if ($isFirstRow) {
                $isFirstRow = false;
                continue;
            }

            if (count($row) < 4) continue;

            $matricule = trim((string) $row[0]);
            $date = trim((string) $row[1]);
            $checkIn = trim((string) $row[2]);
            $checkOut = trim((string) $row[3]);

            if (empty($matricule) || empty($date)) continue;
            
            $employeeId = $employees[$matricule] ?? null;
            if (!$employeeId) continue; // Skip unknown employees

            // Calculate hours
            $totalHours = 0;
            $overtimeHours = 0;

            if ($checkIn && $checkOut) {
                $in = strtotime($checkIn);
                $out = strtotime($checkOut);
                if ($out > $in) {
                    $totalHours = round(($out - $in) / 3600, 2);
                    // Assuming standard workday is 8 hours
                    if ($totalHours > 8) {
                        $overtimeHours = $totalHours - 8;
                        $totalHours = 8;
                    }
                }
            }

            $this->repository->upsert([
                'employee_id' => $employeeId,
                'date' => $date,
                'check_in' => $checkIn ?: null,
                'check_out' => $checkOut ?: null,
                'total_hours' => $totalHours,
                'overtime_hours' => $overtimeHours,
                'status' => 'present'
            ]);

            $imported++;
        }
        fclose($handle);

        return $imported;
    }

    private function getEmployeeMap(): array
    {
        $stmt = $this->pdo->query("SELECT id, employee_number FROM rh_employees WHERE is_active = 1");
        $map = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $map[$row['employee_number']] = (int) $row['id'];
        }
        return $map;
    }
}
