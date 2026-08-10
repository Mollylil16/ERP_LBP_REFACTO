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
        $html .= '<section class="finea-section-card" style="margin-bottom: 24px; padding: 24px;">';
        $html .= '<h2 class="finea-section-title" style="margin-bottom: 16px; font-weight: 800; display:flex; align-items:center; gap:8px;"><svg viewBox="0 0 24 24" width="20" height="20" stroke="#2563eb" stroke-width="2.2" fill="none"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg> Qui suit son colis sur le site ? (Consultations récentes)</h2>';
        if (empty($d['tracking_searches'])) {
            $html .= '<p style="color: #64748b; font-size: 0.9rem; padding: 12px 0;">Aucune recherche de colis récente enregistrée. Les consultations de tracking s\'afficheront ici.</p>';
        } else {
            $html .= '<div style="overflow-x:auto; border-radius:12px; border:1px solid #e2e8f0;"><table style="width:100%; border-collapse:collapse; text-align:left; font-size:0.88rem; background:#ffffff;"><thead><tr style="background:#f8fafc; border-bottom:2px solid #e2e8f0; color:#475569; font-size:0.78rem; text-transform:uppercase; letter-spacing:0.6px;"><th style="padding:14px 18px; font-weight:800; width:160px;">Date & Heure</th><th style="padding:14px 18px; font-weight:800;">N° Tracking / Référence</th><th style="padding:14px 18px; font-weight:800;">Statut du Colis</th><th style="padding:14px 18px; font-weight:800; text-align:right; width:180px;">Adresse IP Visiteur</th></tr></thead><tbody>';
            foreach ($d['tracking_searches'] as $row) {
                $statut = $row['statut'] ?? null;
                $statusBadge = '';
                if (empty($statut)) {
                    $statusBadge = '<span style="display:inline-flex; align-items:center; gap:4px; padding:4px 12px; border-radius:20px; font-size:0.78rem; font-weight:700; background:#fef2f2; color:#dc2626; border:1px solid #fecaca;"><span style="width:6px; height:6px; border-radius:50%; background:#ef4444;"></span> NON TROUVÉ EN ERP</span>';
                } else {
                    $clean = mb_strtoupper(trim((string)$statut), 'UTF-8');
                    $bg = '#dbeafe'; $color = '#1e40af'; $border = '#93c5fd'; $dot = '#3b82f6';
                    if (str_contains($clean, 'RETIR') || str_contains($clean, 'LIVR')) {
                        $bg = '#dcfce7'; $color = '#15803d'; $border = '#86efac'; $dot = '#22c55e';
                    } elseif (str_contains($clean, 'ARRIV') || str_contains($clean, 'RECEPT')) {
                        $bg = '#ccfbf1'; $color = '#0f766e'; $border = '#99f6e4'; $dot = '#14b8a6';
                    } elseif (str_contains($clean, 'PREPAR') || str_contains($clean, 'BROUILLON')) {
                        $bg = '#fef3c7'; $color = '#b45309'; $border = '#fde68a'; $dot = '#f59e0b';
                    }
                    $statusBadge = sprintf(
                        '<span style="display:inline-flex; align-items:center; gap:4px; padding:4px 12px; border-radius:20px; font-size:0.78rem; font-weight:700; background:%s; color:%s; border:1px solid %s;"><span style="width:6px; height:6px; border-radius:50%%; background:%s;"></span> %s</span>',
                        $bg, $color, $border, $dot, View::e($clean)
                    );
                }

                $html .= '<tr style="border-bottom:1px solid #f1f5f9;">'
                    . '<td style="padding:14px 18px; white-space:nowrap;"><strong style="color:#0f172a;">' . View::e(date('d/m/Y H:i', strtotime((string)$row['created_at']))) . '</strong></td>'
                    . '<td style="padding:14px 18px;"><code style="font-family:Consolas, Monaco, monospace; font-size:0.9rem; background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; padding:4px 10px; border-radius:6px; font-weight:700;">' . View::e((string)$row['tracking_ref']) . '</code></td>'
                    . '<td style="padding:14px 18px;">' . $statusBadge . '</td>'
                    . '<td style="padding:14px 18px; text-align:right; white-space:nowrap;"><span style="font-family:Consolas, monospace; color:#64748b; font-size:0.85rem; background:#f1f5f9; border:1px solid #e2e8f0; padding:4px 10px; border-radius:6px; display:inline-flex; align-items:center; gap:6px;"><svg viewBox="0 0 24 24" width="13" height="13" stroke="#64748b" stroke-width="2" fill="none"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10z"/></svg> ' . View::e((string)$row['ip_address']) . '</span></td>'
                    . '</tr>';
            }
            $html .= '</tbody></table></div>';
        }
        $html .= '</section>';

        // 3. Colis retirés par les clients
        $html .= '<section class="finea-section-card" style="margin-bottom: 24px;">';
        $html .= '<h2 class="finea-section-title" style="display:flex; align-items:center; gap:8px;"><svg viewBox="0 0 24 24" width="20" height="20" stroke="#16a34a" stroke-width="2.2" fill="none"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> Qui a récupéré son colis ? (Colis Retirés / Livrés)</h2>';
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
