<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;
use App\View\Pages\SiteAdmin\AnalyticsPage;

final class SiteAnalytics
{
    public static function page(AnalyticsPage $page): string
    {
        $d = $page->data;
        $s = $d['summary'] ?? [];

        $html = Ui::pageHeader(
            'Pilotage & Audience Site Web',
            'Visiteurs en direct, suivi des recherches colis, annonces et colis retirés.',
            ['eyebrow' => 'Module Site ERP LBP']
        );

        // KPIs
        $html .= Dashboard::kpis([
            ['label' => 'Visiteurs en direct (En ligne)', 'value' => $s['online_count'] ?? 0, 'meta' => 'Connectés les 15 dernières min'],
            ['label' => 'Visiteurs uniques', 'value' => $s['visitors'] ?? 0, 'meta' => '30 derniers jours'],
            ['label' => 'Pages vues', 'value' => $s['views'] ?? 0, 'meta' => '30 derniers jours'],
            ['label' => 'Recherches tracking', 'value' => count($d['tracking_searches'] ?? []), 'meta' => 'Consultations colis'],
        ]);

        // 1. Visiteurs connectés en temps réel
        $html .= '<section class="finea-section-card" style="margin-bottom: 24px;">';
        $html .= '<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">';
        $html .= '<h2 class="finea-section-title" style="margin:0; display: flex; align-items: center; gap: 8px;">';
        $html .= '<span style="width: 10px; height: 10px; border-radius: 50%; background: #10b981; display: inline-block; animation: pulse 1.5s infinite;"></span>';
        $html .= 'Visiteurs connectés en temps réel sur le site (' . count($d['online_visitors'] ?? []) . ')';
        $html .= '</h2>';
        $html .= '<span style="font-size: 0.8rem; color: #64748b;">Mise à jour continue</span>';
        $html .= '</div>';

        if (empty($d['online_visitors'])) {
            $html .= '<p style="color: #64748b; font-size: 0.9rem; padding: 12px 0;">Aucun visiteur actif en ce moment sur le site vitrine. Dès qu’un client se connecte, sa session apparaît ici en direct.</p>';
        } else {
            $html .= '<div class="site-analytics-table"><table><thead><tr><th>Heure</th><th>Page consultée</th><th>Action / Bouton</th><th>Adresse IP</th><th>Appareil / Navigateur</th></tr></thead><tbody>';
            foreach ($d['online_visitors'] as $row) {
                $html .= '<tr>'
                    . '<td><strong style="color: #10b981;">' . View::e(date('H:i:s', strtotime((string)$row['created_at']))) . '</strong></td>'
                    . '<td><code>' . View::e((string)($row['page_path'] ?: '/')) . '</code></td>'
                    . '<td>' . View::e((string)($row['target_label'] ?: 'Navigation')) . '</td>'
                    . '<td>' . View::e((string)$row['ip_address']) . '</td>'
                    . '<td>' . View::e(substr((string)$row['user_agent'], 0, 70)) . '</td>'
                    . '</tr>';
            }
            $html .= '</tbody></table></div>';
        }
        $html .= '</section>';

        // 2. Recherches de colis (Tracking) sur le site
        $html .= '<section class="finea-section-card" style="margin-bottom: 24px;">';
        $html .= '<h2 class="finea-section-title">🔍 Qui suit son colis sur le site ? (Consultations récentes)</h2>';
        if (empty($d['tracking_searches'])) {
            $html .= '<p style="color: #64748b; font-size: 0.9rem; padding: 12px 0;">Aucune recherche de colis récente enregistrée. Les consultations de tracking s\'afficheront ici.</p>';
        } else {
            $html .= '<div class="site-analytics-table"><table><thead><tr><th>Date & Heure</th><th>N° Tracking / Référence</th><th>Statut du Colis</th><th>Adresse IP Visiteur</th></tr></thead><tbody>';
            foreach ($d['tracking_searches'] as $row) {
                $statusColor = '#2563eb';
                $statusText = strtoupper((string)($row['statut'] ?: 'INCONNU'));
                if (in_array($statusText, ['RETIRÉ', 'LIVRÉ'])) {
                    $statusColor = '#10b981';
                }
                $html .= '<tr>'
                    . '<td>' . View::e(date('d/m/Y H:i', strtotime((string)$row['created_at']))) . '</td>'
                    . '<td><strong style="color: #1d2b57;">' . View::e((string)$row['tracking_ref']) . '</strong></td>'
                    . '<td><span style="display:inline-block; padding: 3px 10px; border-radius: 12px; font-size: 0.78rem; font-weight: 700; background: ' . $statusColor . '15; color: ' . $statusColor . ';">' . View::e($statusText) . '</span></td>'
                    . '<td>' . View::e((string)$row['ip_address']) . '</td>'
                    . '</tr>';
            }
            $html .= '</tbody></table></div>';
        }
        $html .= '</section>';

        // 3. Colis retirés par les clients
        $html .= '<section class="finea-section-card" style="margin-bottom: 24px;">';
        $html .= '<h2 class="finea-section-title">✅ Qui a récupéré son colis ? (Colis Retirés / Livrés)</h2>';
        if (empty($d['delivered_parcels'])) {
            $html .= '<p style="color: #64748b; font-size: 0.9rem; padding: 12px 0;">Aucun colis au statut RETIRÉ pour le moment.</p>';
        } else {
            $html .= '<div class="site-analytics-table"><table><thead><tr><th>N° Colis</th><th>Destinataire / Client</th><th>Agence de retrait</th><th>Date de retrait</th><th>Statut</th></tr></thead><tbody>';
            foreach ($d['delivered_parcels'] as $row) {
                $html .= '<tr>'
                    . '<td><strong>' . View::e((string)($row['numero_tracking'] ?: $row['reference'])) . '</strong></td>'
                    . '<td>' . View::e((string)($row['destinataire_name'] ?: $row['expediteur_name'] ?: 'Client LBP')) . '</td>'
                    . '<td>' . View::e((string)($row['agence_name'] ?: 'Agence LBP')) . '</td>'
                    . '<td>' . View::e(date('d/m/Y H:i', strtotime((string)$row['updated_at']))) . '</td>'
                    . '<td><span style="padding: 3px 10px; border-radius: 12px; font-size: 0.78rem; font-weight: 700; background: #ecfdf5; color: #047857;">RETIRÉ / LIVRÉ</span></td>'
                    . '</tr>';
            }
            $html .= '</tbody></table></div>';
        }
        $html .= '</section>';

        // 4. Graphiques & Activité générale
        $daily = array_map(static fn(array $row): array => ['label' => $row['label'], 'total' => (int)$row['views'] + (int)$row['clicks']], $d['daily'] ?? []);
        $html .= '<div class="site-analytics-grid">' . self::bars('Activité des 14 derniers jours', $daily) . self::bars('Pages les plus vues', $d['pages'] ?? []) . self::bars('Boutons les plus cliqués', $d['clicks'] ?? []) . '</div>';

        return $html;
    }

    private static function bars(string $title, array $rows): string
    {
        $totals = array_map(static fn(array $row): int => (int)($row['total'] ?? 0), $rows);
        $max = max([1, ...$totals]);
        $h = '<section class="finea-section-card"><h2 class="finea-section-title">' . View::e($title) . '</h2><div class="site-analytics-bars">';
        foreach ($rows as $r) {
            $h .= '<div><span>' . View::e((string)$r['label']) . '</span><i><b style="width:' . (((int)$r['total'] / $max) * 100) . '%"></b></i><strong>' . (int)$r['total'] . '</strong></div>';
        }
        return $h . '</div></section>';
    }
}
