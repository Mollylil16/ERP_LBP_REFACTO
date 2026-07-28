<?php
/** @var \App\Support\ViewBag $viewData */
/** @var string $date */
/** @var int|null $agenceId */
/** @var array $sites */
/** @var array $rapportColis */
/** @var array $creditsMap */
/** @var array $totaux */
/** @var bool $vueMensuelle */
/** @var string|null $mois */
/** @var array $journaliers */

$vueMensuelle = $vueMensuelle ?? false;

function fmt_xof(float $val): string {
    return number_format($val, 0, ',', ' ') . ' XOF';
}
function fmt_eur(float $val): string {
    return number_format($val, 2, ',', ' ') . ' €';
}
function fmt_kg(float $val): string {
    return number_format($val, 1, ',', ' ') . ' kg';
}
?>
<div class="finea-shell">
  <div class="finea-container">

    <!-- Header -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
      <div>
        <h2 style="margin:0;font-size:1.3rem;">📊 <?= $vueMensuelle ? 'Rapport Mensuel' : 'Rapport Journalier' ?> par Agence</h2>
        <p style="color:#64748b;font-size:.9rem;margin:.3rem 0 0;">
          <?= $vueMensuelle
              ? 'Mois de <strong>' . date('F Y', strtotime($date)) . '</strong>'
              : 'Journée du <strong>' . date('d/m/Y', strtotime($date)) . '</strong>' ?>
        </p>
      </div>
      <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
        <a href="/colisage/rapports?date=<?= htmlspecialchars($date) ?>&agence_id=<?= $agenceId ?? '' ?>" style="background:#f8fafc;border:1px solid #e2e8f0;color:#374151;padding:.5rem 1rem;border-radius:.4rem;text-decoration:none;font-size:.875rem;">📅 Journalier</a>
        <a href="/colisage/rapports/mensuel?mois=<?= htmlspecialchars($mois ?? date('Y-m')) ?>&agence_id=<?= $agenceId ?? '' ?>" style="background:#f8fafc;border:1px solid #e2e8f0;color:#374151;padding:.5rem 1rem;border-radius:.4rem;text-decoration:none;font-size:.875rem;">📆 Mensuel</a>
        <?php if (!$vueMensuelle): ?>
          <a href="/colisage/rapports/export-csv?date=<?= htmlspecialchars($date) ?>&agence_id=<?= $agenceId ?? '' ?>"
             style="background:#22c55e;color:#fff;padding:.5rem 1.1rem;border-radius:.4rem;text-decoration:none;font-weight:600;font-size:.875rem;display:flex;align-items:center;gap:.4rem;">
            ⬇ Export CSV
          </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Filtres -->
    <form method="GET" action="/colisage/rapports<?= $vueMensuelle ? '/mensuel' : '' ?>"
          style="background:#fff;border-radius:.75rem;padding:1.25rem;margin-bottom:1.5rem;box-shadow:0 1px 4px rgba(0,0,0,.07);display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap;">
      <div>
        <?php if ($vueMensuelle): ?>
          <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:.3rem;">Mois</label>
          <input type="month" name="mois" value="<?= htmlspecialchars($mois ?? date('Y-m')) ?>" style="padding:.5rem .7rem;border:1px solid #d1d5db;border-radius:.4rem;font-size:.9rem;">
        <?php else: ?>
          <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:.3rem;">Date</label>
          <input type="date" name="date" value="<?= htmlspecialchars($date) ?>" style="padding:.5rem .7rem;border:1px solid #d1d5db;border-radius:.4rem;font-size:.9rem;">
        <?php endif; ?>
      </div>
      <div>
        <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:.3rem;">Agence</label>
        <select name="agence_id" style="padding:.5rem .7rem;border:1px solid #d1d5db;border-radius:.4rem;font-size:.9rem;min-width:180px;">
          <option value="">Toutes les agences</option>
          <?php foreach ($sites as $s): ?>
            <option value="<?= $s['id'] ?>" <?= (int)$agenceId === (int)$s['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $s['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" style="padding:.5rem 1.2rem;background:#0369a1;color:#fff;border:none;border-radius:.4rem;font-weight:600;cursor:pointer;">Actualiser</button>
    </form>

    <?php if ($vueMensuelle && !empty($journaliers)): ?>
      <!-- VUE MENSUELLE : Points journaliers -->
      <div style="background:#fff;border-radius:.75rem;box-shadow:0 1px 4px rgba(0,0,0,.07);overflow:hidden;">
        <div style="padding:1rem 1.25rem;border-bottom:1px solid #f1f5f9;font-weight:700;font-size:.95rem;">
          Points journaliers — <?= date('F Y', strtotime($date)) ?>
        </div>
        <table style="width:100%;border-collapse:collapse;font-size:.875rem;">
          <thead style="background:#f8fafc;">
            <tr>
              <th style="text-align:left;padding:.8rem 1rem;color:#374151;">Jour</th>
              <th style="text-align:left;padding:.8rem 1rem;color:#374151;">Agence</th>
              <th style="text-align:right;padding:.8rem 1rem;color:#374151;">Colis</th>
              <th style="text-align:right;padding:.8rem 1rem;color:#374151;">Poids</th>
              <th style="text-align:right;padding:.8rem 1rem;color:#374151;">CA XOF</th>
              <th style="text-align:right;padding:.8rem 1rem;color:#374151;">CA EUR</th>
            </tr>
          </thead>
          <tbody>
          <?php $prevJour = ''; foreach ($journaliers as $j): ?>
            <tr style="border-top:1px solid #f1f5f9;<?= $j['jour'] !== $prevJour ? 'background:#fafafa;' : '' ?>">
              <td style="padding:.7rem 1rem;font-weight:<?= $j['jour'] !== $prevJour ? '700' : '400' ?>;color:#374151;">
                <?php if ($j['jour'] !== $prevJour): $prevJour = $j['jour']; ?>
                  <?= date('d/m/Y', strtotime($j['jour'])) ?>
                <?php endif; ?>
              </td>
              <td style="padding:.7rem 1rem;color:#64748b;"><?= htmlspecialchars((string)$j['agence_name']) ?></td>
              <td style="padding:.7rem 1rem;text-align:right;font-weight:600;"><?= number_format((int)$j['nb_colis']) ?></td>
              <td style="padding:.7rem 1rem;text-align:right;color:#64748b;"><?= fmt_kg((float)$j['poids']) ?></td>
              <td style="padding:.7rem 1rem;text-align:right;color:#0369a1;font-weight:500;"><?= number_format((float)$j['ca_xof'], 0, ',', ' ') ?></td>
              <td style="padding:.7rem 1rem;text-align:right;color:#059669;"><?= number_format((float)$j['ca_eur'], 2, ',', ' ') ?>€</td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

    <?php elseif (!$vueMensuelle): ?>

      <!-- RAPPORT JOURNALIER -->

      <?php if (!empty($totaux)): ?>
        <!-- Totaux -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem;margin-bottom:1.5rem;">
          <?php
          $totalCards = [
              ['label' => 'Colis reçus', 'value' => number_format((int)$totaux['nb_colis']), 'color' => '#0ea5e9', 'icon' => '📦'],
              ['label' => 'Poids total', 'value' => fmt_kg((float)$totaux['poids_total']), 'color' => '#f97316', 'icon' => '⚖️'],
              ['label' => 'CA XOF', 'value' => fmt_xof((float)$totaux['ca_xof']), 'color' => '#22c55e', 'icon' => '💰'],
              ['label' => 'CA EUR', 'value' => fmt_eur((float)$totaux['ca_eur']), 'color' => '#6366f1', 'icon' => '💶'],
              ['label' => 'Hors délai', 'value' => number_format((int)$totaux['nb_hors_delai']), 'color' => '#ef4444', 'icon' => '⚠️'],
              ['label' => 'Crédits non réglés', 'value' => fmt_xof((float)$totaux['credits_non_regle_xof']), 'color' => '#ef4444', 'icon' => '🔴'],
              ['label' => 'Crédits réglés (jour)', 'value' => fmt_xof((float)$totaux['credits_regle_xof_jour']), 'color' => '#22c55e', 'icon' => '✅'],
          ];
          foreach ($totalCards as $tc): ?>
            <div style="background:#fff;border-radius:.75rem;padding:1rem 1.25rem;box-shadow:0 1px 4px rgba(0,0,0,.07);border-left:4px solid <?= $tc['color'] ?>;">
              <div style="font-size:1.2rem;"><?= $tc['icon'] ?></div>
              <div style="font-size:1.15rem;font-weight:700;color:<?= $tc['color'] ?>;margin:.3rem 0 .2rem;"><?= htmlspecialchars((string)$tc['value']) ?></div>
              <div style="font-size:.75rem;color:#64748b;"><?= $tc['label'] ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <!-- Tableau par agence -->
      <div style="background:#fff;border-radius:.75rem;box-shadow:0 1px 4px rgba(0,0,0,.07);overflow:hidden;margin-bottom:1.5rem;">
        <div style="padding:1rem 1.25rem;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center;">
          <div style="font-weight:700;font-size:.95rem;">Détail par Agence — <?= date('d/m/Y', strtotime($date)) ?></div>
          <a href="/colisage/rapports/export-csv?date=<?= htmlspecialchars($date) ?>&agence_id=<?= $agenceId ?? '' ?>"
             style="background:#22c55e;color:#fff;padding:.4rem .9rem;border-radius:.4rem;text-decoration:none;font-size:.8rem;font-weight:600;">⬇ CSV</a>
        </div>
        <div style="overflow-x:auto;">
          <table style="width:100%;border-collapse:collapse;font-size:.875rem;">
            <thead style="background:#f8fafc;">
              <tr>
                <th style="text-align:left;padding:.75rem 1rem;color:#374151;white-space:nowrap;">Agence</th>
                <th style="text-align:right;padding:.75rem 1rem;color:#374151;white-space:nowrap;">Colis reçus</th>
                <th style="text-align:right;padding:.75rem 1rem;color:#374151;white-space:nowrap;">Retirés</th>
                <th style="text-align:right;padding:.75rem 1rem;color:#374151;white-space:nowrap;">Poids (kg)</th>
                <th style="text-align:right;padding:.75rem 1rem;color:#374151;white-space:nowrap;">CA XOF</th>
                <th style="text-align:right;padding:.75rem 1rem;color:#374151;white-space:nowrap;">CA EUR</th>
                <th style="text-align:right;padding:.75rem 1rem;color:#374151;white-space:nowrap;">Hors délai</th>
                <th style="text-align:right;padding:.75rem 1rem;color:#374151;white-space:nowrap;">Crédits non réglés</th>
                <th style="text-align:right;padding:.75rem 1rem;color:#374151;white-space:nowrap;">Crédits réglés (jour)</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($rapportColis as $row):
              $cr = $creditsMap[(int)$row['agence_id']] ?? [];
              $nonRegle = (float)($cr['credits_non_regle_xof'] ?? 0);
              $regleJour = (float)($cr['credits_regle_xof_jour'] ?? 0);
              $horsDelai = (int)$row['nb_hors_delai'];
            ?>
              <tr style="border-top:1px solid #f1f5f9;">
                <td style="padding:.75rem 1rem;font-weight:600;"><?= htmlspecialchars((string)$row['agence_name']) ?></td>
                <td style="padding:.75rem 1rem;text-align:right;">
                  <span style="background:#eff6ff;color:#0369a1;padding:.15rem .5rem;border-radius:999px;font-weight:700;"><?= number_format((int)$row['nb_colis']) ?></span>
                </td>
                <td style="padding:.75rem 1rem;text-align:right;color:#22c55e;font-weight:600;"><?= number_format((int)$row['nb_retires']) ?></td>
                <td style="padding:.75rem 1rem;text-align:right;color:#64748b;"><?= fmt_kg((float)$row['poids_total']) ?></td>
                <td style="padding:.75rem 1rem;text-align:right;color:#0369a1;font-weight:600;"><?= number_format((float)$row['ca_xof'], 0, ',', ' ') ?></td>
                <td style="padding:.75rem 1rem;text-align:right;color:#059669;"><?= $row['ca_eur'] > 0 ? number_format((float)$row['ca_eur'], 2, ',', ' ') . '€' : '—' ?></td>
                <td style="padding:.75rem 1rem;text-align:right;">
                  <?php if ($horsDelai > 0): ?>
                    <span style="background:#fef2f2;color:#ef4444;padding:.15rem .5rem;border-radius:999px;font-weight:700;">⚠️ <?= $horsDelai ?></span>
                  <?php else: ?>
                    <span style="color:#22c55e;">✓ 0</span>
                  <?php endif; ?>
                </td>
                <td style="padding:.75rem 1rem;text-align:right;">
                  <?php if ($nonRegle > 0): ?>
                    <span style="color:#ef4444;font-weight:600;"><?= number_format($nonRegle, 0, ',', ' ') ?> XOF</span>
                    <?php if (($cr['nb_credits_non_regle'] ?? 0) > 0): ?>
                      <span style="color:#94a3b8;font-size:.75rem;"> (<?= $cr['nb_credits_non_regle'] ?>)</span>
                    <?php endif; ?>
                  <?php else: ?>
                    <span style="color:#22c55e;">✓ 0</span>
                  <?php endif; ?>
                </td>
                <td style="padding:.75rem 1rem;text-align:right;">
                  <?php if ($regleJour > 0): ?>
                    <span style="color:#22c55e;font-weight:600;">+<?= number_format($regleJour, 0, ',', ' ') ?> XOF</span>
                  <?php else: ?>
                    <span style="color:#94a3b8;">—</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
            <?php if (!empty($totaux)): ?>
            <tfoot style="background:#f0f9ff;font-weight:700;">
              <tr>
                <td style="padding:.75rem 1rem;">TOTAUX</td>
                <td style="padding:.75rem 1rem;text-align:right;color:#0369a1;"><?= number_format((int)$totaux['nb_colis']) ?></td>
                <td style="padding:.75rem 1rem;text-align:right;">—</td>
                <td style="padding:.75rem 1rem;text-align:right;color:#64748b;"><?= fmt_kg((float)$totaux['poids_total']) ?></td>
                <td style="padding:.75rem 1rem;text-align:right;color:#0369a1;"><?= number_format((float)$totaux['ca_xof'], 0, ',', ' ') ?> XOF</td>
                <td style="padding:.75rem 1rem;text-align:right;color:#059669;"><?= fmt_eur((float)$totaux['ca_eur']) ?></td>
                <td style="padding:.75rem 1rem;text-align:right;color:#ef4444;"><?= number_format((int)$totaux['nb_hors_delai']) ?></td>
                <td style="padding:.75rem 1rem;text-align:right;color:#ef4444;"><?= number_format((float)$totaux['credits_non_regle_xof'], 0, ',', ' ') ?> XOF</td>
                <td style="padding:.75rem 1rem;text-align:right;color:#22c55e;"><?= number_format((float)$totaux['credits_regle_xof_jour'], 0, ',', ' ') ?> XOF</td>
              </tr>
            </tfoot>
            <?php endif; ?>
          </table>
        </div>
      </div>

      <!-- Navigation dates rapides -->
      <div style="display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap;">
        <?php
        $dateObj = new DateTime($date);
        $hier = (clone $dateObj)->modify('-1 day')->format('Y-m-d');
        $demain = (clone $dateObj)->modify('+1 day')->format('Y-m-d');
        ?>
        <a href="/colisage/rapports?date=<?= $hier ?>&agence_id=<?= $agenceId ?? '' ?>" style="background:#f8fafc;border:1px solid #e2e8f0;color:#374151;padding:.5rem 1rem;border-radius:.4rem;text-decoration:none;font-size:.85rem;">← <?= date('d/m', strtotime($hier)) ?></a>
        <a href="/colisage/rapports?date=<?= date('Y-m-d') ?>&agence_id=<?= $agenceId ?? '' ?>" style="background:#0369a1;color:#fff;padding:.5rem 1rem;border-radius:.4rem;text-decoration:none;font-size:.85rem;font-weight:600;">Aujourd'hui</a>
        <?php if ($demain <= date('Y-m-d')): ?>
          <a href="/colisage/rapports?date=<?= $demain ?>&agence_id=<?= $agenceId ?? '' ?>" style="background:#f8fafc;border:1px solid #e2e8f0;color:#374151;padding:.5rem 1rem;border-radius:.4rem;text-decoration:none;font-size:.85rem;"><?= date('d/m', strtotime($demain)) ?> →</a>
        <?php endif; ?>
      </div>

    <?php elseif ($vueMensuelle && empty($journaliers)): ?>
      <div style="background:#fff;border-radius:.75rem;padding:3rem;text-align:center;color:#94a3b8;box-shadow:0 1px 4px rgba(0,0,0,.07);">
        Aucune donnée pour ce mois.
      </div>
    <?php endif; ?>

  </div>
</div>
