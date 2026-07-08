<?php

declare(strict_types=1);

namespace App\View\Pages\Colisage;

use App\Helpers\View;

final class ColisageIndexPage
{
    /** @var array<string, mixed> */
    public readonly array $filters;

    /** @var array<int, array<string, mixed>> */
    public readonly array $parcels;

    /** @var array<int, array{number: int, href: string, active: bool}> */
    public readonly array $pagination;

    public readonly int $total;
    public readonly int $currentPage;
    public readonly int $totalPages;

    /** @var array<int, array<string, mixed>> */
    public readonly array $sites;

    /**
     * @param array<string, mixed> $data
     * @param array<int, array<string, mixed>> $sites
     */
    public function __construct(array $data, array $sites = [])
    {
        $this->filters = is_array($data['filters'] ?? null) ? $data['filters'] : [];
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];
        $this->total = (int) ($data['total'] ?? 0);
        $this->currentPage = max(1, (int) ($data['page'] ?? 1));
        $this->totalPages = max(1, (int) ($data['totalPages'] ?? 1));
        $this->sites = $sites;

        // Formatted parcels
        $this->parcels = array_map(function (array $item): array {
            $id = (int) ($item['id'] ?? 0);
            $actions = [
                [
                    'label' => 'Voir détails',
                    'href' => 'colisage/parcels/' . $id,
                    'variant' => 'primary',
                ]
            ];

            return [
                'id' => $id,
                'numero_tracking' => (string) ($item['numero_tracking'] ?? ''),
                'expediteur_name' => (string) ($item['expediteur_name'] ?? ''),
                'destinataire_name' => (string) ($item['destinataire_name'] ?? ''),
                'poids_total' => (float) ($item['poids_total'] ?? 0.0),
                'valeur_declaree' => (float) ($item['valeur_declaree'] ?? 0.0),
                'devise' => (string) ($item['devise'] ?? 'XOF'),
                'statut' => (string) ($item['statut'] ?? 'RÉCEPTIONNÉ'),
                'type_expediteur' => (string) ($item['type_expediteur'] ?? ''),
                'agence_depart_name' => (string) ($item['agence_depart_name'] ?? ''),
                'agence_arrivee_name' => (string) ($item['agence_arrivee_name'] ?? ''),
                'created_at' => (string) ($item['created_at'] ?? ''),
                'actions' => $actions,
            ];
        }, $items);

        // Pagination links
        $paginationLinks = [];
        for ($p = 1; $p <= $this->totalPages; $p++) {
            $query = http_build_query(array_filter(
                $this->filters + ['page' => $p],
                static fn(mixed $val): bool => $val !== '' && $val !== 0
            ));
            $paginationLinks[] = [
                'number' => $p,
                'href' => View::url('colisage/parcels?' . $query),
                'active' => $p === $this->currentPage,
            ];
        }
        $this->pagination = $paginationLinks;
    }
}
