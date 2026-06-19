<?php

declare(strict_types=1);

namespace App\View\Pages\Rh;

final class LifecyclePage
{
    /** @var array<int,array<string,mixed>> */
    public readonly array $employees;
    /** @var array<int,array<string,mixed>> */
    public readonly array $contracts;
    /** @var array<int,array<string,mixed>> */
    public readonly array $assignments;
    /** @var array<int,array<string,mixed>> */
    public readonly array $evaluations;
    /** @var array<int,array<string,mixed>> */
    public readonly array $trainings;
    /** @var array<int,array<string,mixed>> */
    public readonly array $employeeRequests;
    /** @var array<int,array<string,mixed>> */
    public readonly array $workflows;
    /** @var array<int,array<string,mixed>> */
    public readonly array $disciplinaryActions;
    /** @var array<int,array<string,mixed>> */
    public readonly array $alerts;
    /** @var array<int,array{key:string,label:string,href:string}> */
    public readonly array $tabs;
    /** @var array<int,array{value:string,label:string}> */
    public readonly array $employeeOptions;
    /** @var array<int,array{value:string,label:string}> */
    public readonly array $siteOptions;

    /** @param array<string,mixed> $data */
    public function __construct(
        public readonly string $section,
        public readonly string $csrfToken,
        array $data,
    ) {
        $this->employees = self::rows($data, 'employees');
        $this->contracts = self::rows($data, 'contracts');
        $this->assignments = self::rows($data, 'assignments');
        $this->evaluations = self::rows($data, 'evaluations');
        $this->trainings = self::rows($data, 'trainings');
        $this->employeeRequests = self::rows($data, 'employeeRequests');
        $this->workflows = self::rows($data, 'workflows');
        $this->disciplinaryActions = self::rows($data, 'disciplinaryActions');
        $this->alerts = self::rows($data, 'alerts');
        $sites = self::rows($data, 'sites');

        $labels = [
            'contracts' => 'Contrats & essais',
            'assignments' => 'Missions',
            'evaluations' => 'Évaluations',
            'trainings' => 'Formations',
            'workflows' => 'Validations',
            'organization' => 'Organigramme',
            'recruitment' => 'Recrutement',
            'discipline' => 'Discipline',
        ];
        $tabs = [];
        foreach ($labels as $key => $label) {
            $tabs[] = [
                'key' => $key,
                'label' => $label,
                'href' => 'rh/cycle-vie?section=' . $key,
            ];
        }
        $this->tabs = $tabs;
        $this->employeeOptions = array_map(static fn(array $employee): array => [
            'value' => (string) $employee['id'],
            'label' => (string) $employee['full_name'],
        ], $this->employees);
        $this->siteOptions = array_map(static fn(array $site): array => [
            'value' => (string) $site['id'],
            'label' => (string) $site['name'],
        ], $sites);
    }

    public function date(?string $value): string
    {
        return $value ? date('d/m/Y', strtotime($value)) : '—';
    }

    public function pendingWorkflows(): int
    {
        return count(array_filter(
            $this->workflows,
            static fn(array $workflow): bool => $workflow['status'] === 'pending'
        ));
    }

    /** @param array<string,mixed> $data @return array<int,array<string,mixed>> */
    private static function rows(array $data, string $key): array
    {
        return is_array($data[$key] ?? null) ? $data[$key] : [];
    }
}
