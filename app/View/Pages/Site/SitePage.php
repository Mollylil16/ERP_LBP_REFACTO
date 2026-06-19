<?php

declare(strict_types=1);

namespace App\View\Pages\Site;

final class SitePage
{
    public readonly string $defaultShipment;
    /** @var array<string,mixed> */
    public readonly array $currentShipment;
    /** @var array<int,string> */
    public readonly array $countries;
    /** @var array<int,array<string,mixed>> */
    public readonly array $mappedAgencies;

    /**
     * @param array<string,array<string,mixed>> $shipments
     * @param array<int,array<string,mixed>> $agencies
     * @param array<int,array<string,mixed>> $services
     * @param array<int,array<string,mixed>> $news
     * @param array<int,array<string,string>> $stats
     */
    public function __construct(
        public readonly string $title,
        public readonly array $shipments,
        public readonly array $agencies,
        public readonly array $services,
        public readonly array $news,
        public readonly array $stats,
        string $reference = '',
    ) {
        $this->defaultShipment = (string) (array_key_first($shipments) ?? '');
        $reference = strtoupper(trim($reference));
        $this->currentShipment = is_array($shipments[$reference] ?? null)
            ? $shipments[$reference]
            : (is_array(reset($shipments)) ? reset($shipments) : []);
        $countries = array_values(array_unique(array_map(
            static fn(array $agency): string => (string) ($agency['country'] ?? ''),
            $agencies
        )));
        sort($countries);
        $this->countries = $countries;
        $this->mappedAgencies = array_map(static function (array $agency, int $index): array {
            $agency['marker_number'] = $index + 1;
            $agency['marker_left'] = max(6, min(94, (((float) ($agency['lng'] ?? 0) + 10) / 130) * 100));
            $agency['marker_top'] = max(6, min(94, 100 - (((float) ($agency['lat'] ?? 0) + 5) / 60) * 100));
            $agency['search'] = strtolower(implode(' ', [
                $agency['name'] ?? '', $agency['city'] ?? '', $agency['country'] ?? '', $agency['services'] ?? '',
            ]));
            return $agency;
        }, $agencies, array_keys($agencies));
    }
}
