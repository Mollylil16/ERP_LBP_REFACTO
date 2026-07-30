<?php
/** @var \App\Support\ViewBag $viewData */
/** @var array $appels */
/** @var array $clients */
/** @var string $dateDebut */
/** @var string $dateFin */
/** @var string $typeFilter */

use App\Helpers\Auth;
use App\Helpers\Csrf;

$canManage = Auth::can('call_center_manage');
$csrfToken = Csrf::token();

$typeLabels = [
    'information'  => 'Information',
    'reclamation'  => 'Réclamation',
    'suivi_colis'  => 'Suivi Colis',
    'autre'        => 'Autre',
];
$statutLabels = [
    'traite'     => ['label' => 'Traité',      'color' => '#22c55e'],
    'en_cours'   => ['label' => 'En cours',    'color' => '#f97316'],
    'a_rappeler' => ['label' => 'À rappeler',  'color' => '#a855f7'],
];
?>

<?php if ($canManage): ?>
<!-- Modal Nouvel Appel -->
<div id="modal-appel" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:1rem;padding:2rem;max-width:520px;width:90%;max-height:90vh;overflow-y:auto;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
      <h2 style="margin:0;font-size:1.2rem;display:flex;align-items:center;gap:.5rem;">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#0ea5e9" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
        Enregistrer un Appel
      </h2>
      <button onclick="document.getElementById('modal-appel').style.display='none'" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:#64748b;">×</button>
    </div>
    <form method="POST" action="/call-center/appels/enregistrer">
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
          <label style="display:block;font-size:.85rem;font-weight:600;margin-bottom:.4rem;color:#374151;">N° de Suivi (optionnel)</label>
          <input type="text" name="numero_tracking" placeholder="Ex: LB-CI-0726-001" style="width:100%;padding:.6rem .8rem;border:1px solid #d1d5db;border-radius:.5rem;font-size:.9rem;box-sizing:border-box;">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div>
            <label style="display:block;font-size:.85rem;font-weight:600;margin-bottom:.4rem;color:#374151;">Type d'appel *</label>
            <select name="type_appel" required style="width:100%;padding:.6rem .8rem;border:1px solid #d1d5db;border-radius:.5rem;font-size:.9rem;">
              <?php foreach ($typeLabels as $k => $v): ?>
                <option value="<?= $k ?>"><?= $v ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label style="display:block;font-size:.85rem;font-weight:600;margin-bottom:.4rem;color:#374151;">Statut *</label>
            <select name="statut" required style="width:100%;padding:.6rem .8rem;border:1px solid #d1d5db;border-radius:.5rem;font-size:.9rem;">
              <?php foreach ($statutLabels as $k => $v): ?>
                <option value="<?= $k ?>"><?= $v['label'] ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div>
          <label style="display:block;font-size:.85rem;font-weight:600;margin-bottom:.4rem;color:#374151;">Satisfaction (1 à 5)</label>
          <div style="display:flex;gap:.5rem;">
            <?php for ($i = 1; $i <= 5; $i++): ?>
              <label style="display:flex;flex-direction:column;align-items:center;gap:.2rem;cursor:pointer;">
                <input type="radio" name="satisfaction_score" value="<?= $i ?>" style="accent-color:#f97316;">
                <span style="font-size:.85rem;font-weight:600;"><?= $i ?>/5</span>
              </label>
            <?php endfor; ?>
          </div>
        </div>
        <div>
          <label style="display:block;font-size:.85rem;font-weight:600;margin-bottom:.4rem;color:#374151;">Description *</label>
          <textarea name="description" rows="4" required placeholder="Résumé de l'appel..." style="width:100%;padding:.6rem .8rem;border:1px solid #d1d5db;border-radius:.5rem;font-size:.9rem;resize:vertical;box-sizing:border-box;"></textarea>
        </div>
        <div style="display:flex;gap:.75rem;justify-content:flex-end;">
          <button type="button" onclick="document.getElementById('modal-appel').style.display='none'" style="padding:.6rem 1.2rem;border:1px solid #d1d5db;border-radius:.5rem;background:#fff;cursor:pointer;">Annuler</button>
          <button type="submit" style="padding:.6rem 1.5rem;background:#0ea5e9;color:#fff;border:none;border-radius:.5rem;font-weight:600;cursor:pointer;">Enregistrer</button>
        </div>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="finea-shell">
  <div class="finea-container">

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
      <div>
        <h2 style="margin:0;font-size:1.3rem;display:flex;align-items:center;gap:.5rem;">
          <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="#0ea5e9" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
          Journal des Appels
        </h2>
        <p style="color:#64748b;font-size:.9rem;margin:.3rem 0 0;"><?= count($appels) ?> appel(s) sur la période sélectionnée</p>
      </div>
      <?php if ($canManage): ?>
        <button onclick="document.getElementById('modal-appel').style.display='flex'" style="background:#0ea5e9;color:#fff;padding:.65rem 1.3rem;border:none;border-radius:.5rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:.4rem;">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Enregistrer un Appel
        </button>
      <?php endif; ?>
    </div>

    <!-- Filtres -->
    <form method="GET" action="/call-center/appels" style="background:#fff;border-radius:.75rem;padding:1.25rem;margin-bottom:1.5rem;box-shadow:0 1px 4px rgba(0,0,0,.07);display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap;">
      <div>
        <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:.3rem;">Date début</label>
        <input type="date" name="date_debut" value="<?= htmlspecialchars($dateDebut) ?>" style="padding:.5rem .7rem;border:1px solid #d1d5db;border-radius:.4rem;font-size:.9rem;">
      </div>
      <div>
        <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:.3rem;">Date fin</label>
        <input type="date" name="date_fin" value="<?= htmlspecialchars($dateFin) ?>" style="padding:.5rem .7rem;border:1px solid #d1d5db;border-radius:.4rem;font-size:.9rem;">
      </div>
      <div>
        <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:.3rem;">Type</label>
        <select name="type_appel" style="padding:.5rem .7rem;border:1px solid #d1d5db;border-radius:.4rem;font-size:.9rem;">
          <option value="">Tous les types</option>
          <?php foreach ($typeLabels as $k => $v): ?>
            <option value="<?= $k ?>" <?= $typeFilter === $k ? 'selected' : '' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" style="padding:.5rem 1.2rem;background:#0369a1;color:#fff;border:none;border-radius:.4rem;font-weight:600;cursor:pointer;">Filtrer</button>
    </form>

    <!-- Tableau -->
    <div style="background:#fff;border-radius:.75rem;box-shadow:0 1px 4px rgba(0,0,0,.07);overflow:hidden;">
      <?php if (empty($appels)): ?>
        <div style="padding:2rem;text-align:center;color:#94a3b8;">Aucun appel pour cette période.</div>
      <?php else: ?>
        <table style="width:100%;border-collapse:collapse;font-size:.875rem;">
          <thead style="background:#f8fafc;">
            <tr>
              <th style="text-align:left;padding:.8rem 1rem;color:#374151;font-weight:600;">#</th>
              <th style="text-align:left;padding:.8rem 1rem;color:#374151;font-weight:600;">Client</th>
              <th style="text-align:left;padding:.8rem 1rem;color:#374151;font-weight:600;">Type</th>
              <th style="text-align:left;padding:.8rem 1rem;color:#374151;font-weight:600;">Suivi</th>
              <th style="text-align:left;padding:.8rem 1rem;color:#374151;font-weight:600;">Agent</th>
              <th style="text-align:left;padding:.8rem 1rem;color:#374151;font-weight:600;">Satisfaction</th>
              <th style="text-align:left;padding:.8rem 1rem;color:#374151;font-weight:600;">Statut</th>
              <th style="text-align:left;padding:.8rem 1rem;color:#374151;font-weight:600;">Date</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($appels as $a): ?>
            <tr style="border-top:1px solid #f1f5f9;">
              <td style="padding:.7rem 1rem;color:#94a3b8;">#<?= $a['id'] ?></td>
              <td style="padding:.7rem 1rem;font-weight:500;"><?= htmlspecialchars((string)($a['client_name'] ?? '—')) ?></td>
              <td style="padding:.7rem 1rem;"><?= htmlspecialchars((string)($typeLabels[$a['type_appel']] ?? $a['type_appel'])) ?></td>
              <td style="padding:.7rem 1rem;font-family:monospace;font-size:.8rem;color:#0369a1;"><?= htmlspecialchars((string)($a['numero_tracking'] ?? '—')) ?></td>
              <td style="padding:.7rem 1rem;color:#64748b;"><?= htmlspecialchars((string)($a['agent_name'] ?? '')) ?></td>
              <td style="padding:.7rem 1rem;">
                <?php if ($a['satisfaction_score']): ?>
                  <span style="color:#f97316;"><?= str_repeat('★', (int)$a['satisfaction_score']) ?><?= str_repeat('☆', 5 - (int)$a['satisfaction_score']) ?></span>
                <?php else: ?>
                  <span style="color:#d1d5db;">—</span>
                <?php endif; ?>
              </td>
              <td style="padding:.7rem 1rem;">
                <?php $si = $statutLabels[$a['statut']] ?? ['label' => $a['statut'], 'color' => '#94a3b8']; ?>
                <span style="background:<?= $si['color'] ?>22;color:<?= $si['color'] ?>;padding:.2rem .6rem;border-radius:999px;font-size:.75rem;font-weight:600;"><?= $si['label'] ?></span>
              </td>
              <td style="padding:.7rem 1rem;color:#64748b;font-size:.8rem;"><?= date('d/m/Y H:i', strtotime($a['created_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

  </div>
</div>
