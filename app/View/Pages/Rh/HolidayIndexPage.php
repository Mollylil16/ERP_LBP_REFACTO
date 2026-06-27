<?php

declare(strict_types=1);

namespace App\View\Pages\Rh;

final class HolidayIndexPage
{
    public readonly string $selectedMonth;
    public readonly int $businessDaysCount;
    public readonly array $filteredHolidays;

    /**
     * @param array<int,array<string,mixed>> $allHolidays
     */
    public function __construct(array $allHolidays, string $selectedMonth)
    {
        $this->selectedMonth = $selectedMonth;

        // Calculate business days (Monday to Friday) in the selected month
        $parts = explode('-', $selectedMonth);
        $year = (int)$parts[0];
        $month = (int)$parts[1];

        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $businessDays = 0;
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $timestamp = mktime(0, 0, 0, $month, $day, $year);
            $dayOfWeek = (int)date('N', $timestamp);
            if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
                $businessDays++;
            }
        }
        $this->businessDaysCount = $businessDays;

        // Filter holidays for the selected month
        $filtered = [];
        $targetMonth = sprintf('%02d', $month);
        $targetYear = sprintf('%04d', $year);

        foreach ($allHolidays as $h) {
            $hTimestamp = strtotime($h['holiday_date']);
            if (!$hTimestamp) {
                continue;
            }
            $hMonth = date('m', $hTimestamp);
            $hYear = date('Y', $hTimestamp);

            if ((int)($h['is_recurring'] ?? 0) === 1) {
                if ($hMonth === $targetMonth) {
                    $filtered[] = $h;
                }
            } else {
                if ($hMonth === $targetMonth && $hYear === $targetYear) {
                    $filtered[] = $h;
                }
            }
        }

        // Sort filtered holidays ascending by date
        usort($filtered, function ($a, $b) {
            return strcmp($a['holiday_date'], $b['holiday_date']);
        });

        $this->filteredHolidays = $filtered;
    }

    /** @param string|null $date */
    public function formatDate(?string $date): string
    {
        if (!$date) {
            return '';
        }
        $ts = strtotime($date);
        return $ts ? date('d/m/Y', $ts) : '';
    }
}
