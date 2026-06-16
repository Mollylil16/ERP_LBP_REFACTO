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

    public function dailySheet(string $date): array
    {
        $date = $this->validDate($date) ?? date('Y-m-d');

        return [
            'date' => $date,
            'rows' => $this->repository->dailySheet($date),
        ];
    }

    public function saveDailySheet(string $date, array $rows): int
    {
        $date = $this->validDate($date);
        if ($date === null) {
            throw new \RuntimeException('La date de pointage est invalide.');
        }

        $activeIds = array_flip($this->repository->activeEmployeeIds());
        $saved = 0;

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $employeeId = (int) ($row['employee_id'] ?? 0);
            if ($employeeId <= 0 || !isset($activeIds[$employeeId])) {
                continue;
            }

            $present = !empty($row['present']);
            $checkIn = $present ? $this->validTime((string) ($row['check_in'] ?? '')) : null;
            $checkOut = $present ? $this->validTime((string) ($row['check_out'] ?? '')) : null;
            [$totalHours, $overtimeHours] = $present
                ? $this->hours($checkIn, $checkOut)
                : [0.0, 0.0];

            $this->repository->upsert([
                'employee_id' => $employeeId,
                'date' => $date,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'total_hours' => $totalHours,
                'overtime_hours' => $overtimeHours,
                'status' => $present ? 'present' : 'absent',
            ]);
            $saved++;
        }

        return $saved;
    }

    private function validDate(string $value): ?string
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', trim($value));
        return $date && $date->format('Y-m-d') === trim($value) ? trim($value) : null;
    }

    private function validTime(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $time = \DateTimeImmutable::createFromFormat('H:i', $value);
        return $time && $time->format('H:i') === $value ? $value : null;
    }

    /** @return array{0:float,1:float} */
    private function hours(?string $checkIn, ?string $checkOut): array
    {
        if ($checkIn === null || $checkOut === null) {
            return [0.0, 0.0];
        }

        $in = strtotime($checkIn);
        $out = strtotime($checkOut);
        if ($in === false || $out === false || $out <= $in) {
            return [0.0, 0.0];
        }

        $worked = round(($out - $in) / 3600, 2);
        return [min($worked, 8.0), max(0.0, $worked - 8.0)];
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
