<?php
/** @var \App\Support\ViewBag $viewData */
/** @var array $litiges */
/** @var array $clients */
/** @var array $colis */
/** @var string $statutFilter */
/** @var string $graviteFilter */

use App\Helpers\Auth;
use App\Helpers\Csrf;

$canManage = Auth::can('call_center_manage');
$csrfToken = Csrf::token();

$typeLabels = [
    'colis_perdu'     => 'Colis perdu',
    'colis_endommage' => 'Colis endommagé',
    'retard'          => 'Retard de livraison',
    'facturation'     => 'Problème de facturation',
    'autre'           => 'Autre',
];
$graviteLabels = [
    'faible'   => ['label' => 'Faible',    'color' => '#22c55e'],
    'moyenne'  => ['label' => 'Moyenne',   'color' => '#eab308'],
    'elevee'   => ['label' => 'Élevée',    'color' => '#f97316'],
    'critique' => ['label' => 'Critique',  'color' => '#ef4444'],
];
$statutLabels = [
    'nouveau'  => ['label' => 'Nouveau',   'color' => '#ef4444'],
    'en_cours' => ['label' => 'En cours',  'color' => '#f97316'],
    'resolu'   => ['label' => 'Résolu',    'color' => '#22c55e'],
    'annule'   => ['label' => 'Annulé',    'color' => '#94a3b8'],
];
?>

<?php if ($canManage): ?>
<!-- Modal Nouveau Litige -->
<div id="modal-litige" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:1rem;padding:2rem;max-width:540px;width:90%;max-height:90vh;overflow-y:auto;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
      <h2 style="margin:0;font-size:1.2rem;display:flex;align-items:center;gap:.5rem;">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        Ouvrir un Litige / Réclamation
      </h2>
      <button onclick="document.getElementById('modal-litige').style.display='none'" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:#64748b;">×</button>
    </div>
    <form method="POST" action="/call-center/litiges/enregistrer">
      <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
      <div style="display:grid;gap:1rem;">
        <div>
          <label style="display:block;font-size:.85rem;font-weight:600;margin-bottom:.4rem;color:#374151;">Client *</label>
          <select name="client_id" required style="width:100%;padding:.6rem .8rem;border:1px solid #d1d5db;border-radius:.5rem;font-size:.9rem;">
            <option value="">-- Sélectionner un client --</option>
            <?php foreach ($clients as $c): ?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars((string) $c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label style="display:block;font-size:.85rem;font-weight:600;margin-bottom:.4rem;color:#374151;">Colis concerné (optionnel)</label>
          <select name="colis_id" style="width:100%;padding:.6rem .8rem;border:1px solid #d1d5db;border-radius:.5rem;font-size:.9rem;">
            <option value="">-- Aucun colis spécifique --</option>
            <?php foreach ($colis as $col): ?>
              <option value="<?= $col['id'] ?>"><?= htmlspecialchars((string) $col['numero_tracking']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div>
            <label style="display:block;font-size:.85rem;font-weight:600;margin-bottom:.4rem;color:#374151;">Type de litige *</label>
            <select name="type_litige" required style="width:100%;padding:.6rem .8rem;border:1px solid #d1d5db;border-radius:.5rem;font-size:.9rem;">
              <?php foreach ($typeLabels as $k => $v): ?>
                <option value="<?= $k ?>"><?= $v ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label style="display:block;font-size:.85rem;font-weight:600;margin-bottom:.4rem;color:#374151;">Gravité *</label>
            <select name="gravite" required style="width:100%;padding:.6rem .8rem;border:1px solid #d1d5db;border-radius:.5rem;font-size:.9rem;">
              <?php foreach ($graviteLabels as $k => $v): ?>
                <option value="<?= $k ?>"><?= $v['label'] ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div>
          <label style="display:block;font-size:.85rem;font-weight:600;margin-bottom:.4rem;color:#374151;">Description du problème *</label>
          <textarea name="description" rows="4" required placeholder="Détails du litige..." style="width:100%;padding:.6rem .8rem;border:1px solid #d1d5db;border-radius:.5rem;font-size:.9rem;resize:vertical;box-sizing:border-box;"></textarea>
        </div>
        <div style="display:flex;gap:.75rem;justify-content:flex-end;">
          <button type="button" onclick="document.getElementById('modal-litige').style.display='none'" style="padding:.6rem 1.2rem;border:1px solid #d1d5db;border-radius:.5rem;background:#fff;cursor:pointer;">Annuler</button>
          <button type="submit" style="padding:.6rem 1.5rem;background:#ef4444;color:#fff;border:none;border-radius:.5rem;font-weight:600;cursor:pointer;">Enregistrer</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Modals de résolution (inline pour chaque litige) -->
<?php foreach ($litiges as $l): if (in_array($l['statut'], ['nouveau', 'en_cours'], true)): ?>
<div id="modal-resolve-<?= $l['id'] ?>" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:1rem;padding:2rem;max-width:480px;width:90%;">
    <h2 style="margin:0 0 1.5rem;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
      <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#22c55e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
      Résoudre le Litige #<?= $l['id'] ?>
    </h2>
    <form method="POST" action="/call-center/litiges/<?= $l['id'] ?>/resoudre">
      <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
      <div style="display:grid;gap:1rem;">
        <div>
          <label style="display:block;font-size:.85rem;font-weight:600;margin-bottom:.4rem;">Nouveau statut</label>
          <select name="statut" style="width:100%;padding:.6rem .8rem;border:1px solid #d1d5db;border-radius:.5rem;font-size:.9rem;">
            <option value="resolu">Résolu</option>
            <option value="en_cours">En cours (mise à jour)</option>
            <option value="annule">Annulé</option>
          </select>
        </div>
        <div>
          <label style="display:block;font-size:.85rem;font-weight:600;margin-bottom:.4rem;">Solution apportée *</label>
          <textarea name="solution_apportee" rows="3" required placeholder="Décrivez la solution apportée..." style="width:100%;padding:.6rem .8rem;border:1px solid #d1d5db;border-radius:.5rem;font-size:.9rem;box-sizing:border-box;"></textarea>
        </div>
        <div style="display:flex;gap:.75rem;justify-content:flex-end;">
          <button type="button" onclick="document.getElementById('modal-resolve-<?= $l['id'] ?>').style.display='none'" style="padding:.6rem 1.2rem;border:1px solid #d1d5db;border-radius:.5rem;background:#fff;cursor:pointer;">Annuler</button>
          <button type="submit" style="padding:.6rem 1.5rem;background:#22c55e;color:#fff;border:none;border-radius:.5rem;font-weight:600;cursor:pointer;">Valider</button>
        </div>
      </div>
    </form>
  </div>
</div>
<?php endif; endforeach; ?>
<?php endif; ?>

<div class="finea-shell">
  <div class="finea-container">

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
      <div>
        <h2 style="margin:0;font-size:1.3rem;display:flex;align-items:center;gap:.5rem;">
          <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          Réclamations & Litiges
        </h2>
        <p style="color:#64748b;font-size:.9rem;margin:.3rem 0 0;"><?= count($litiges) ?> litige(s) • <span style="color:#ef4444;"><?= count(array_filter($litiges, fn($l) => in_array($l['statut'], ['nouveau','en_cours']))) ?> ouverts</span></p>
      </div>
      <?php if ($canManage): ?>
        <button onclick="document.getElementById('modal-litige').style.display='flex'" style="background:#ef4444;color:#fff;padding:.65rem 1.3rem;border:none;border-radius:.5rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:.4rem;">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Ouvrir un Litige
        </button>
      <?php endif; ?>
    </div>

    <!-- Filtres -->
    <form method="GET" action="/call-center/litiges" style="background:#fff;border-radius:.75rem;padding:1.25rem;margin-bottom:1.5rem;box-shadow:0 1px 4px rgba(0,0,0,.07);display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap;">
      <div>
        <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:.3rem;">Statut</label>
        <select name="statut" style="padding:.5rem .7rem;border:1px solid #d1d5db;border-radius:.4rem;font-size:.9rem;">
          <option value="">Tous les statuts</option>
          <?php foreach ($statutLabels as $k => $v): ?>
            <option value="<?= $k ?>" <?= $statutFilter === $k ? 'selected' : '' ?>><?= $v['label'] ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:.3rem;">Gravité</label>
        <select name="gravite" style="padding:.5rem .7rem;border:1px solid #d1d5db;border-radius:.4rem;font-size:.9rem;">
          <option value="">Toutes les gravités</option>
          <?php foreach ($graviteLabels as $k => $v): ?>
            <option value="<?= $k ?>" <?= $graviteFilter === $k ? 'selected' : '' ?>><?= $v['label'] ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" style="padding:.5rem 1.2rem;background:#0369a1;color:#fff;border:none;border-radius:.4rem;font-weight:600;cursor:pointer;">Filtrer</button>
    </form>

    <!-- Tableau -->
    <div style="background:#fff;border-radius:.75rem;box-shadow:0 1px 4px rgba(0,0,0,.07);overflow:hidden;">
      <?php if (empty($litiges)): ?>
        <div style="padding:2rem;text-align:center;color:#94a3b8;">Aucun litige pour les filtres sélectionnés.</div>
      <?php else: ?>
        <table style="width:100%;border-collapse:collapse;font-size:.875rem;">
          <thead style="background:#f8fafc;">
            <tr>
              <th style="text-align:left;padding:.8rem 1rem;color:#374151;font-weight:600;">#</th>
              <th style="text-align:left;padding:.8rem 1rem;color:#374151;font-weight:600;">Client</th>
              <th style="text-align:left;padding:.8rem 1rem;color:#374151;font-weight:600;">Type</th>
              <th style="text-align:left;padding:.8rem 1rem;color:#374151;font-weight:600;">Colis</th>
              <th style="text-align:left;padding:.8rem 1rem;color:#374151;font-weight:600;">Gravité</th>
              <th style="text-align:left;padding:.8rem 1rem;color:#374151;font-weight:600;">Statut</th>
              <th style="text-align:left;padding:.8rem 1rem;color:#374151;font-weight:600;">Ouvert le</th>
              <?php if ($canManage): ?><th style="text-align:left;padding:.8rem 1rem;color:#374151;font-weight:600;">Actions</th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($litiges as $l): ?>
            <tr style="border-top:1px solid #f1f5f9;<?= $l['gravite'] === 'critique' ? 'background:#fef2f2;' : '' ?>">
              <td style="padding:.7rem 1rem;color:#94a3b8;">#<?= $l['id'] ?></td>
              <td style="padding:.7rem 1rem;font-weight:500;"><?= htmlspecialchars((string)($l['client_name'] ?? '—')) ?></td>
              <td style="padding:.7rem 1rem;"><?= htmlspecialchars((string)($typeLabels[$l['type_litige']] ?? $l['type_litige'])) ?></td>
              <td style="padding:.7rem 1rem;font-family:monospace;font-size:.8rem;color:#0369a1;"><?= htmlspecialchars((string)($l['numero_tracking'] ?? '—')) ?></td>
              <td style="padding:.7rem 1rem;">
                <?php $gi = $graviteLabels[$l['gravite']] ?? ['label' => $l['gravite'], 'color' => '#94a3b8']; ?>
                <span style="background:<?= $gi['color'] ?>22;color:<?= $gi['color'] ?>;padding:.2rem .6rem;border-radius:999px;font-size:.75rem;font-weight:600;"><?= $gi['label'] ?></span>
              </td>
              <td style="padding:.7rem 1rem;">
                <?php $sti = $statutLabels[$l['statut']] ?? ['label' => $l['statut'], 'color' => '#94a3b8']; ?>
                <span style="background:<?= $sti['color'] ?>22;color:<?= $sti['color'] ?>;padding:.2rem .6rem;border-radius:999px;font-size:.75rem;font-weight:600;"><?= $sti['label'] ?></span>
              </td>
              <td style="padding:.7rem 1rem;color:#64748b;font-size:.8rem;"><?= date('d/m/Y', strtotime($l['date_ouverture'])) ?></td>
              <?php if ($canManage): ?>
              <td style="padding:.7rem 1rem;">
                <?php if (in_array($l['statut'], ['nouveau', 'en_cours'], true)): ?>
                  <button onclick="document.getElementById('modal-resolve-<?= $l['id'] ?>').style.display='flex'" style="background:#22c55e;color:#fff;border:none;border-radius:.4rem;padding:.3rem .8rem;font-size:.8rem;cursor:pointer;font-weight:600;">✓ Traiter</button>
                <?php else: ?>
                  <span style="color:#94a3b8;font-size:.8rem;"><?= $l['date_resolution'] ? date('d/m/Y', strtotime($l['date_resolution'])) : '—' ?></span>
                <?php endif; ?>
              </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

  </div>
</div>
