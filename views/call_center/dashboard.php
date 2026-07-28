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
        <div style="width:56px;height:56px;border-radius:50%;background:rgba(255,255,255,0.2);display:flex;align-items:center;justify-content:center;font-size:1.6rem;">📞</div>
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
          ['label' => 'Total Appels',     'value' => $kpis['total_appels'],      'color' => '#0ea5e9', 'icon' => '📞'],
          ['label' => "Appels aujourd'hui",'value' => $kpis['appels_aujourdhui'], 'color' => '#22c55e', 'icon' => '📅'],
          ['label' => 'Satisfaction Moy.','value' => $kpis['avg_satisfaction'] . '/5', 'color' => '#f97316', 'icon' => '⭐'],
          ['label' => 'Litiges Ouverts',  'value' => $kpis['open_litiges'],      'color' => '#ef4444', 'icon' => '⚠️'],
          ['label' => 'Nouveaux Litiges', 'value' => $kpis['new_litiges'],       'color' => '#a855f7', 'icon' => '🆕'],
          ['label' => 'Taux Résolution',  'value' => $kpis['resolution_rate'] . '%', 'color' => '#14b8a6', 'icon' => '✅'],
      ];
      foreach ($kpiItems as $k): ?>
        <div style="background:#fff;border-radius:.75rem;padding:1.25rem;box-shadow:0 1px 4px rgba(0,0,0,.07);border-left:4px solid <?= $k['color'] ?>;">
          <div style="font-size:1.5rem;"><?= $k['icon'] ?></div>
          <div style="font-size:1.6rem;font-weight:700;color:<?= $k['color'] ?>;margin:.3rem 0;"><?= htmlspecialchars((string)$k['value']) ?></div>
          <div style="font-size:.8rem;color:#64748b;"><?= $k['label'] ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">

      <!-- Derniers Appels -->
      <div style="background:#fff;border-radius:.75rem;padding:1.5rem;box-shadow:0 1px 4px rgba(0,0,0,.07);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
          <h3 style="margin:0;font-size:1rem;">📞 Derniers Appels</h3>
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
          <h3 style="margin:0;font-size:1rem;">⚠️ Litiges Récents</h3>
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
      <a href="/call-center/rayons" style="background:linear-gradient(135deg,#0369a1,#0ea5e9);color:#fff;padding:.75rem 1.5rem;border-radius:.5rem;text-decoration:none;font-weight:600;display:flex;align-items:center;gap:.5rem;">🏪 Vue Rayons Temps Réel</a>
      <?php if ($canManage): ?>
        <a href="/call-center/appels" style="background:#0ea5e9;color:#fff;padding:.75rem 1.5rem;border-radius:.5rem;text-decoration:none;font-weight:600;">+ Enregistrer un Appel</a>
        <a href="/call-center/litiges" style="background:#ef4444;color:#fff;padding:.75rem 1.5rem;border-radius:.5rem;text-decoration:none;font-weight:600;">+ Ouvrir un Litige</a>
      <?php endif; ?>
    </div>

  </div>
</div>
