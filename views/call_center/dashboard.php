<?php
/** @var \App\Support\ViewBag $viewData */
/** @var array $kpis */
/** @var array $recentAppels */
/** @var array $recentLitiges */

use App\Helpers\Auth;
use App\Helpers\Csrf;

$canManage = Auth::can('call_center_manage');
?>
<div class="finea-shell">
  <div class="finea-container">

    <!-- Header -->
    <div class="rh-hero rh-hero-white" style="background: linear-gradient(135deg,#0369a1,#0ea5e9); color:#fff; border-radius:1rem; padding:2rem; margin-bottom:2rem;">
      <div style="display:flex;align-items:center;gap:1rem;">
        <div style="width:56px;height:56px;border-radius:50%;background:rgba(255,255,255,0.2);display:flex;align-items:center;justify-content:center;">
          <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
        </div>
        <div>
          <div style="font-size:0.8rem;opacity:0.8;letter-spacing:0.08em;text-transform:uppercase;">CAL Dashboard</div>
          <h1 style="font-size:1.6rem;font-weight:700;margin:0;">Tableau de bord Call Center</h1>
          <p style="opacity:0.8;margin:0.2rem 0 0;">Suivi des appels clients, réclamations et assistance en temps réel</p>
        </div>
      </div>
    </div>

    <!-- KPIs -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;margin-bottom:2rem;">
      <?php
      $kpiItems = [
          [
            'label' => 'Total Appels',
            'value' => $kpis['total_appels'],
            'color' => '#0ea5e9',
            'svg'   => '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="#0ea5e9" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>'
          ],
          [
            'label' => "Appels aujourd'hui",
            'value' => $kpis['appels_aujourdhui'],
            'color' => '#22c55e',
            'svg'   => '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="#22c55e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>'
          ],
          [
            'label' => 'Satisfaction Moy.',
            'value' => $kpis['avg_satisfaction'] . '/5',
            'color' => '#f97316',
            'svg'   => '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="#f97316" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>'
          ],
          [
            'label' => 'Litiges Ouverts',
            'value' => $kpis['open_litiges'],
            'color' => '#ef4444',
            'svg'   => '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>'
          ],
          [
            'label' => 'Nouveaux Litiges',
            'value' => $kpis['new_litiges'],
            'color' => '#a855f7',
            'svg'   => '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="#a855f7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>'
          ],
          [
            'label' => 'Taux Résolution',
            'value' => $kpis['resolution_rate'] . '%',
            'color' => '#14b8a6',
            'svg'   => '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="#14b8a6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>'
          ],
      ];
      foreach ($kpiItems as $k): ?>
        <div style="background:#fff;border-radius:.75rem;padding:1.25rem;box-shadow:0 1px 4px rgba(0,0,0,.07);border-left:4px solid <?= $k['color'] ?>;">
          <div><?= $k['svg'] ?></div>
          <div style="font-size:1.6rem;font-weight:700;color:<?= $k['color'] ?>;margin:.3rem 0;"><?= htmlspecialchars((string)$k['value']) ?></div>
          <div style="font-size:.8rem;color:#64748b;"><?= $k['label'] ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">

      <!-- Derniers Appels -->
      <div style="background:#fff;border-radius:.75rem;padding:1.5rem;box-shadow:0 1px 4px rgba(0,0,0,.07);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
          <h3 style="margin:0;font-size:1rem;display:flex;align-items:center;gap:.5rem;">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#0ea5e9" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            Derniers Appels
          </h3>
          <a href="/call-center/appels" style="font-size:.8rem;color:#0ea5e9;text-decoration:none;">Voir tout →</a>
        </div>
        <?php if (empty($recentAppels)): ?>
          <p style="color:#94a3b8;font-size:.9rem;">Aucun appel enregistré.</p>
        <?php else: ?>
          <table style="width:100%;border-collapse:collapse;font-size:.85rem;">
            <thead><tr style="border-bottom:2px solid #f1f5f9;">
              <th style="text-align:left;padding:.4rem .6rem;color:#64748b;">Client</th>
              <th style="text-align:left;padding:.4rem .6rem;color:#64748b;">Type</th>
              <th style="text-align:left;padding:.4rem .6rem;color:#64748b;">Agent</th>
              <th style="text-align:left;padding:.4rem .6rem;color:#64748b;">Statut</th>
            </tr></thead>
            <tbody>
            <?php foreach ($recentAppels as $a): ?>
              <tr style="border-bottom:1px solid #f8fafc;">
                <td style="padding:.5rem .6rem;font-weight:500;"><?= htmlspecialchars((string)($a['client_name'] ?? '—')) ?></td>
                <td style="padding:.5rem .6rem;"><?= htmlspecialchars((string)($a['type_appel'] ?? '')) ?></td>
                <td style="padding:.5rem .6rem;color:#64748b;"><?= htmlspecialchars((string)($a['agent_name'] ?? '')) ?></td>
                <td style="padding:.5rem .6rem;">
                  <?php
                  $sc = ['traite' => '#22c55e', 'en_cours' => '#f97316', 'a_rappeler' => '#a855f7'];
                  $sc2 = $sc[$a['statut']] ?? '#94a3b8';
                  ?>
                  <span style="background:<?= $sc2 ?>22;color:<?= $sc2 ?>;padding:.2rem .5rem;border-radius:999px;font-size:.75rem;font-weight:600;"><?= htmlspecialchars((string)($a['statut'] ?? '')) ?></span>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

      <!-- Litiges Récents -->
      <div style="background:#fff;border-radius:.75rem;padding:1.5rem;box-shadow:0 1px 4px rgba(0,0,0,.07);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
          <h3 style="margin:0;font-size:1rem;display:flex;align-items:center;gap:.5rem;">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            Litiges Récents
          </h3>
          <a href="/call-center/litiges" style="font-size:.8rem;color:#ef4444;text-decoration:none;">Voir tout →</a>
        </div>
        <?php if (empty($recentLitiges)): ?>
          <p style="color:#94a3b8;font-size:.9rem;">Aucun litige enregistré.</p>
        <?php else: ?>
          <table style="width:100%;border-collapse:collapse;font-size:.85rem;">
            <thead><tr style="border-bottom:2px solid #f1f5f9;">
              <th style="text-align:left;padding:.4rem .6rem;color:#64748b;">Client</th>
              <th style="text-align:left;padding:.4rem .6rem;color:#64748b;">Type</th>
              <th style="text-align:left;padding:.4rem .6rem;color:#64748b;">Gravité</th>
              <th style="text-align:left;padding:.4rem .6rem;color:#64748b;">Statut</th>
            </tr></thead>
            <tbody>
            <?php foreach ($recentLitiges as $l): ?>
              <tr style="border-bottom:1px solid #f8fafc;">
                <td style="padding:.5rem .6rem;font-weight:500;"><?= htmlspecialchars((string)($l['client_name'] ?? '—')) ?></td>
                <td style="padding:.5rem .6rem;"><?= htmlspecialchars((string)($l['type_litige'] ?? '')) ?></td>
                <td style="padding:.5rem .6rem;">
                  <?php
                  $gc = ['critique' => '#ef4444', 'elevee' => '#f97316', 'moyenne' => '#eab308', 'faible' => '#22c55e'];
                  $gc2 = $gc[$l['gravite']] ?? '#94a3b8';
                  ?>
                  <span style="background:<?= $gc2 ?>22;color:<?= $gc2 ?>;padding:.2rem .5rem;border-radius:999px;font-size:.75rem;font-weight:600;"><?= htmlspecialchars((string)($l['gravite'] ?? '')) ?></span>
                </td>
                <td style="padding:.5rem .6rem;">
                  <?php
                  $stc = ['resolu' => '#22c55e', 'nouveau' => '#ef4444', 'en_cours' => '#f97316', 'annule' => '#94a3b8'];
                  $stc2 = $stc[$l['statut']] ?? '#94a3b8';
                  ?>
                  <span style="background:<?= $stc2 ?>22;color:<?= $stc2 ?>;padding:.2rem .5rem;border-radius:999px;font-size:.75rem;font-weight:600;"><?= htmlspecialchars((string)($l['statut'] ?? '')) ?></span>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>

    <!-- Accès rapides -->
    <div style="margin-top:1.5rem;display:flex;gap:1rem;flex-wrap:wrap;">
      <a href="/call-center/rayons" style="background:linear-gradient(135deg,#0369a1,#0ea5e9);color:#fff;padding:.75rem 1.5rem;border-radius:.5rem;text-decoration:none;font-weight:600;display:flex;align-items:center;gap:.5rem;">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        Vue Rayons Temps Réel
      </a>
      <?php if ($canManage): ?>
        <a href="/call-center/appels" style="background:#0ea5e9;color:#fff;padding:.75rem 1.5rem;border-radius:.5rem;text-decoration:none;font-weight:600;display:flex;align-items:center;gap:.5rem;">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Enregistrer un Appel
        </a>
        <a href="/call-center/litiges" style="background:#ef4444;color:#fff;padding:.75rem 1.5rem;border-radius:.5rem;text-decoration:none;font-weight:600;display:flex;align-items:center;gap:.5rem;">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Ouvrir un Litige
        </a>
      <?php endif; ?>
    </div>

  </div>
</div>
