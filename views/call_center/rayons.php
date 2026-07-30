<?php
/** @var \App\Support\ViewBag $viewData */
/** @var array $sites */
/** @var array $rayons */
/** @var array $colisParRayon */
/** @var int|null $agenceId */
/** @var int $colisHorsDelai */
/** @var string $dernierRefresh */
?>
<!-- Rafraîchissement automatique toutes les 60 secondes -->
<meta http-equiv="refresh" content="60">

<div class="finea-shell">
  <div class="finea-container">

    <!-- Header -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
      <div>
        <h2 style="margin:0;font-size:1.3rem;display:flex;align-items:center;gap:.5rem;">
          <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="#0ea5e9" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
          Vue Rayons — Temps Réel
        </h2>
        <p style="color:#64748b;font-size:.85rem;margin:.3rem 0 0;">
          Dernière mise à jour : <strong><?= htmlspecialchars($dernierRefresh) ?></strong>
          <span style="margin-left:.5rem;background:#fef9c3;color:#b45309;padding:.15rem .5rem;border-radius:999px;font-size:.75rem;">Auto-refresh 60s</span>
          <?php if ($colisHorsDelai > 0): ?>
            <span style="margin-left:.5rem;background:#fef2f2;color:#ef4444;padding:.15rem .5rem;border-radius:999px;font-size:.75rem;font-weight:600;"><?= $colisHorsDelai ?> colis hors délai</span>
          <?php endif; ?>
        </p>
      </div>
      <div style="display:flex;gap:.75rem;align-items:center;">
        <button onclick="location.reload()" style="background:#0ea5e9;color:#fff;border:none;border-radius:.5rem;padding:.5rem 1rem;cursor:pointer;font-weight:600;font-size:.85rem;">Rafraîchir</button>
      </div>
    </div>

    <!-- Filtre par agence -->
    <form method="GET" action="/call-center/rayons" style="background:#fff;border-radius:.75rem;padding:1rem 1.25rem;margin-bottom:1.5rem;box-shadow:0 1px 4px rgba(0,0,0,.07);display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap;">
      <div>
        <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:.3rem;">Filtrer par agence</label>
        <select name="agence_id" style="padding:.5rem .9rem;border:1px solid #d1d5db;border-radius:.4rem;font-size:.9rem;min-width:200px;" onchange="this.form.submit()">
          <option value="">Toutes les agences</option>
          <?php foreach ($sites as $s): ?>
            <option value="<?= $s['id'] ?>" <?= (int)$agenceId === (int)$s['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $s['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>

    <?php if (empty($rayons)): ?>
      <div style="background:#fff;border-radius:.75rem;padding:3rem;text-align:center;color:#94a3b8;box-shadow:0 1px 4px rgba(0,0,0,.07);">
        <div style="margin-bottom:1rem;display:flex;justify-content:center;">
          <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="#94a3b8" stroke-width="1.5"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        </div>
        <p>Aucun rayon configuré<?= $agenceId ? ' pour cette agence' : '' ?>.</p>
        <a href="/logistique/rayons" style="color:#0ea5e9;font-size:.9rem;">Configurer les rayons →</a>
      </div>
    <?php else: ?>

      <?php
      // Grouper les rayons par agence
      $rayonsParAgence = [];
      foreach ($rayons as $r) {
          $rayonsParAgence[$r['agence_nom'] ?? 'Agence ' . $r['agence_id']][] = $r;
      }
      ?>

      <?php foreach ($rayonsParAgence as $agenceNom => $agenceRayons): ?>
        <div style="margin-bottom:2rem;">
          <h3 style="font-size:1rem;font-weight:700;color:#1e293b;margin:0 0 1rem;padding-bottom:.5rem;border-bottom:2px solid #e2e8f0;display:flex;align-items:center;gap:.5rem;">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#0ea5e9" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M9 8h1"/><path d="M9 12h1"/><path d="M9 16h1"/><path d="M14 8h1"/><path d="M14 12h1"/><path d="M14 16h1"/><path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"/></svg>
            <?= htmlspecialchars($agenceNom) ?>
            <span style="font-weight:400;color:#64748b;font-size:.85rem;">(<?= count($agenceRayons) ?> rayon<?= count($agenceRayons) > 1 ? 's' : '' ?>)</span>
          </h3>

          <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1.25rem;">
            <?php foreach ($agenceRayons as $r):
              $occupee  = (int) $r['capacite_occupee'];
              $max      = (int) $r['capacite_max'];
              $pct      = $max > 0 ? min(100, round($occupee / $max * 100)) : 0;
              $colis    = $colisParRayon[(int)$r['id']] ?? [];
              $colisEnRetard = count(array_filter($colis, fn($c) => isset($c['jours_retard']) && (int)$c['jours_retard'] > 0));

              if ($pct >= 90)       { $barColor = '#ef4444'; $badge = ['PLEIN', '#fef2f2', '#ef4444']; }
              elseif ($pct >= 70)  { $barColor = '#f97316'; $badge = ['CHARGÉ', '#fff7ed', '#f97316']; }
              elseif ($pct >= 40)  { $barColor = '#eab308'; $badge = ['ACTIF', '#fefce8', '#eab308']; }
              else                  { $barColor = '#22c55e'; $badge = ['DISPONIBLE', '#f0fdf4', '#22c55e']; }

              if ($r['statut'] === 'MAINTENANCE') { $barColor = '#94a3b8'; $badge = ['MAINTENANCE', '#f8fafc', '#94a3b8']; }
            ?>
            <div style="background:#fff;border-radius:.75rem;box-shadow:0 1px 4px rgba(0,0,0,.07);overflow:hidden;border:1px solid #f1f5f9;">
              <!-- Entête rayon -->
              <div style="padding:1rem 1.25rem;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center;">
                <div>
                  <div style="font-size:1rem;font-weight:700;color:#1e293b;"><?= htmlspecialchars($r['code_rayon']) ?></div>
                  <div style="font-size:.8rem;color:#64748b;"><?= htmlspecialchars($r['nom_rayon']) ?></div>
                </div>
                <span style="background:<?= $badge[1] ?>;color:<?= $badge[2] ?>;padding:.25rem .7rem;border-radius:999px;font-size:.75rem;font-weight:700;"><?= $badge[0] ?></span>
              </div>

              <!-- Jauge -->
              <div style="padding:.75rem 1.25rem;border-bottom:1px solid #f1f5f9;">
                <div style="display:flex;justify-content:space-between;font-size:.8rem;color:#64748b;margin-bottom:.3rem;">
                  <span><?= $occupee ?> / <?= $max ?> colis</span>
                  <strong style="color:<?= $barColor ?>;"><?= $pct ?>%</strong>
                </div>
                <div style="background:#f1f5f9;border-radius:999px;height:8px;overflow:hidden;">
                  <div style="width:<?= $pct ?>%;height:100%;background:<?= $barColor ?>;border-radius:999px;transition:width .3s;"></div>
                </div>
                <?php if ($colisEnRetard > 0): ?>
                  <div style="margin-top:.4rem;font-size:.75rem;color:#ef4444;font-weight:600;"><?= $colisEnRetard ?> colis hors délai</div>
                <?php endif; ?>
              </div>

              <!-- Liste des colis (max 8 affichés) -->
              <div style="max-height:220px;overflow-y:auto;">
                <?php if (empty($colis)): ?>
                  <div style="padding:1rem 1.25rem;color:#94a3b8;font-size:.85rem;text-align:center;">Rayon vide</div>
                <?php else: ?>
                  <?php foreach (array_slice($colis, 0, 8) as $col):
                    $retard = isset($col['jours_retard']) ? (int)$col['jours_retard'] : 0;
                    $rowBg = $retard > 0 ? '#fef2f2' : '#fff';
                  ?>
                    <div style="padding:.6rem 1.25rem;border-bottom:1px solid #f8fafc;background:<?= $rowBg ?>;display:flex;justify-content:space-between;align-items:center;gap:.5rem;">
                      <div style="flex:1;min-width:0;">
                        <div style="font-family:monospace;font-size:.78rem;color:#0369a1;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                          <?= htmlspecialchars((string)$col['numero_tracking']) ?>
                        </div>
                        <div style="font-size:.75rem;color:#64748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                          → <?= htmlspecialchars((string)($col['destinataire_nom'] ?? '')) ?>
                          <?php if ($col['destinataire_phone']): ?>
                            <span style="color:#94a3b8;"> · <?= htmlspecialchars((string)$col['destinataire_phone']) ?></span>
                          <?php endif; ?>
                        </div>
                      </div>
                      <div style="text-align:right;flex-shrink:0;">
                        <?php if ($retard > 0): ?>
                          <span style="background:#fef2f2;color:#ef4444;padding:.15rem .4rem;border-radius:4px;font-size:.7rem;font-weight:700;">+<?= $retard ?>j</span>
                        <?php elseif ($col['date_limite_retrait']): ?>
                          <span style="font-size:.7rem;color:#64748b;">Limite: <?= date('d/m', strtotime($col['date_limite_retrait'])) ?></span>
                        <?php endif; ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                  <?php if (count($colis) > 8): ?>
                    <div style="padding:.5rem 1.25rem;font-size:.75rem;color:#64748b;text-align:center;background:#f8fafc;">
                      + <?= count($colis) - 8 ?> autres colis…
                    </div>
                  <?php endif; ?>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>

    <?php endif; ?>

    <div style="margin-top:1rem;font-size:.8rem;color:#94a3b8;text-align:center;">
      Vue en lecture seule — Rafraîchissement automatique toutes les 60 secondes
    </div>
  </div>
</div>
