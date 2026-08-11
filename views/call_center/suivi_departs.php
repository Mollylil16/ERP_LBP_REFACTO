<?php
/** @var \App\Support\ViewBag $viewData */
/** @var array $grouped */
/** @var array $rawColis */
/** @var string $search */
/** @var int|null $agenceId */
/** @var array $sites */
/** @var bool $canManage */

use App\Helpers\Csrf;
use App\Helpers\View;

$csrfToken = Csrf::token();

$totGrouped = count($grouped);
$totComplets = 0;
$totPartiels = 0;

foreach ($grouped as $g) {
    if ($g['nb_restes'] > 0) {
        $totPartiels++;
    } else {
        $totComplets++;
    }
}
?>

<div class="finea-shell">
  <div class="finea-container">

    <!-- Hero Header -->
    <div class="rh-hero rh-hero-white" style="background: linear-gradient(135deg,#0369a1,#0ea5e9); color:#fff; border-radius:1rem; padding:2rem; margin-bottom:1.5rem;">
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
        <div style="display:flex;align-items:center;gap:1rem;">
          <div style="width:56px;height:56px;border-radius:50%;background:rgba(255,255,255,0.2);display:flex;align-items:center;justify-content:center;">
            <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 17H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-1"/><polygon points="12 15 17 21 7 21 12 15"/></svg>
          </div>
          <div>
            <div style="font-size:0.8rem;opacity:0.85;letter-spacing:0.08em;text-transform:uppercase;">Call Center — Logistique & Relances</div>
            <h1 style="font-size:1.6rem;font-weight:700;margin:0;">Bilan des Départs & Colis Restés</h1>
            <p style="opacity:0.85;margin:0.2rem 0 0;">Identifiez ce qui est parti ou resté en agence (LB-CI, CA-CI, etc.) et notifiez directement les clients.</p>
          </div>
        </div>
        <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
          <a href="/call-center/suivi-departs/export-pdf?q=<?= urlencode($search) ?>&agence_id=<?= $agenceId ?? '' ?>"
             style="background:#0f172a;color:#fff;padding:.6rem 1.2rem;border-radius:.5rem;font-weight:600;text-decoration:none;font-size:.875rem;display:inline-flex;align-items:center;gap:.4rem;box-shadow:0 2px 4px rgba(0,0,0,.15);">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
            Export PDF
          </a>
          <?php if (!empty($canExportExcel)): ?>
            <a href="/call-center/suivi-departs/export-excel?q=<?= urlencode($search) ?>&agence_id=<?= $agenceId ?? '' ?>"
               style="background:#22c55e;color:#fff;padding:.6rem 1.2rem;border-radius:.5rem;font-weight:600;text-decoration:none;font-size:.875rem;display:inline-flex;align-items:center;gap:.4rem;box-shadow:0 2px 4px rgba(0,0,0,.15);">
              <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
              Export Excel
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- KPIs -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:1.5rem;">
      <div style="background:#fff;border-radius:.75rem;padding:1rem 1.25rem;box-shadow:0 1px 4px rgba(0,0,0,.07);border-left:4px solid #0ea5e9;">
        <div style="font-size:1.4rem;font-weight:700;color:#0ea5e9;"><?= $totGrouped ?></div>
        <div style="font-size:.8rem;color:#64748b;">Envois de Fret / Clients</div>
      </div>
      <div style="background:#fff;border-radius:.75rem;padding:1rem 1.25rem;box-shadow:0 1px 4px rgba(0,0,0,.07);border-left:4px solid #22c55e;">
        <div style="font-size:1.4rem;font-weight:700;color:#22c55e;"><?= $totComplets ?></div>
        <div style="font-size:.8rem;color:#64748b;">Envois Complets (Tout parti)</div>
      </div>
      <div style="background:#fff;border-radius:.75rem;padding:1rem 1.25rem;box-shadow:0 1px 4px rgba(0,0,0,.07);border-left:4px solid #f97316;">
        <div style="font-size:1.4rem;font-weight:700;color:#f97316;"><?= $totPartiels ?></div>
        <div style="font-size:.8rem;color:#64748b;">Envois Partiels (Colis restés)</div>
      </div>
    </div>

    <!-- Filtres -->
    <form method="GET" action="/call-center/suivi-departs" style="background:#fff;border-radius:.75rem;padding:1.25rem;margin-bottom:1.5rem;box-shadow:0 1px 4px rgba(0,0,0,.07);display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap;">
      <div style="flex:1;min-width:220px;">
        <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:.3rem;">Rechercher un client ou tracking</label>
        <input type="text" name="q" placeholder="Ex: Yao, LB-CI, +225..." value="<?= htmlspecialchars($search) ?>" style="width:100%;padding:.6rem .8rem;border:1px solid #d1d5db;border-radius:.4rem;font-size:.9rem;box-sizing:border-box;">
      </div>
      <div>
        <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:.3rem;">Agence de départ</label>
        <select name="agence_id" style="padding:.6rem .9rem;border:1px solid #d1d5db;border-radius:.4rem;font-size:.9rem;height:38px;box-sizing:border-box;">
          <option value="">Toutes les agences</option>
          <?php foreach ($sites as $s): ?>
            <option value="<?= $s['id'] ?>" <?= (int)($agenceId ?? 0) === (int)$s['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string)$s['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" style="padding:.6rem 1.5rem;background:#0369a1;color:#fff;border:none;border-radius:.4rem;font-weight:600;cursor:pointer;height:38px;box-sizing:border-box;">Filtrer</button>
      <?php if ($search !== '' || $agenceId !== null): ?>
        <a href="/call-center/suivi-departs" style="padding:.6rem 1.2rem;background:#f1f5f9;color:#475569;border:1px solid #cbd5e1;border-radius:.4rem;font-weight:600;text-decoration:none;font-size:.9rem;height:38px;box-sizing:border-box;display:inline-flex;align-items:center;">Effacer</a>
      <?php endif; ?>
    </form>

    <!-- Liste des Envois par Client & Fret -->
    <?php if (empty($grouped)): ?>
      <div style="background:#fff;border-radius:.75rem;padding:3rem;text-align:center;color:#94a3b8;box-shadow:0 1px 4px rgba(0,0,0,.07);">
        Aucune donnée d'expédition correspondant aux critères.
      </div>
    <?php else: ?>
      <div style="display:grid;gap:1.25rem;">
        <?php foreach ($grouped as $idx => $g):
          $hasReste = $g['nb_restes'] > 0;
          $cardBorder = $hasReste ? '2px solid #f97316' : '1px solid #e2e8f0';
          $headerBg   = $hasReste ? '#fff7ed' : '#f8fafc';
        ?>
          <div style="background:#fff;border-radius:.75rem;box-shadow:0 1px 4px rgba(0,0,0,.07);border:<?= $cardBorder ?>;overflow:hidden;">
            <!-- Header du groupe client -->
            <div style="background:<?= $headerBg ?>;padding:1rem 1.25rem;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
              <div>
                <div style="display:flex;align-items:center;gap:.6rem;flex-wrap:wrap;">
                  <h3 style="margin:0;font-size:1.05rem;color:#0f172a;"><?= htmlspecialchars($g['expediteur_name']) ?></h3>
                  <span style="background:#e0f2fe;color:#0369a1;font-weight:700;padding:.2rem .6rem;border-radius:6px;font-size:.8rem;">
                    Service : <?= htmlspecialchars($g['type_expediteur']) ?>
                    <?= !empty($g['trajet']) ? ' (' . htmlspecialchars($g['trajet']) . ')' : '' ?>
                  </span>
                  <?php if (!empty($g['agence_depart'])): ?>
                    <span style="color:#64748b;font-size:.8rem;">📍 Agence départ : <strong><?= htmlspecialchars($g['agence_depart']) ?></strong></span>
                  <?php endif; ?>
                </div>
                <div style="font-size:.825rem;color:#475569;margin-top:.3rem;">
                  Expéditeur : 📞 <strong><?= htmlspecialchars($g['expediteur_phone'] ?: '—') ?></strong> |
                  Destinataire : <strong><?= htmlspecialchars($g['destinataire_name']) ?></strong> (📞 <?= htmlspecialchars($g['destinataire_phone'] ?: '—') ?>)
                </div>
              </div>

              <!-- Bilan statut & Actions -->
              <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
                <!-- Badges recap -->
                <div style="display:flex;gap:.5rem;align-items:center;">
                  <span style="background:#dcfce7;color:#15803d;padding:.3rem .7rem;border-radius:999px;font-weight:700;font-size:.8rem;">
                    🟢 <?= $g['nb_partis'] ?> / <?= $g['total_colis'] ?> Parti(s)
                  </span>
                  <?php if ($g['nb_restes'] > 0): ?>
                    <span style="background:#fee2e2;color:#b91c1c;padding:.3rem .7rem;border-radius:999px;font-weight:700;font-size:.8rem;">
                      🟠 <?= $g['nb_restes'] ?> Resté(s)
                    </span>
                  <?php endif; ?>
                  <?php if ($g['nb_attente'] > 0): ?>
                    <span style="background:#f1f5f9;color:#64748b;padding:.3rem .7rem;border-radius:999px;font-weight:600;font-size:.8rem;">
                      ⚪ <?= $g['nb_attente'] ?> En attente
                    </span>
                  <?php endif; ?>
                </div>

                <!-- Boutons de Notification Call Center -->
                <?php if ($canManage): ?>
                  <div style="display:flex;gap:.4rem;">
                    <button onclick="notifierGeneralWhatsApp(<?= htmlspecialchars(json_encode($g), ENT_QUOTES) ?>)"
                            style="background:#22c55e;color:#fff;border:none;padding:.4rem .8rem;border-radius:.4rem;font-weight:600;font-size:.8rem;cursor:pointer;display:inline-flex;align-items:center;gap:.3rem;"
                            title="Envoyer la synthèse WhatsApp au client">
                      <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M12.012 2c-5.506 0-9.989 4.478-9.99 9.984a9.96 9.96 0 0 0 1.333 4.982L2 22l5.233-1.371a9.942 9.942 0 0 0 4.777 1.219h.005c5.505 0 9.989-4.478 9.99-9.986 0-2.67-1.037-5.178-2.924-7.067C17.197 2.909 14.69 2 12.012 2zm5.727 14.076c-.314.88-1.545 1.614-2.122 1.697-.577.083-1.127.306-3.666-.694-3.155-1.242-5.187-4.456-5.344-4.664-.158-.209-1.258-1.675-1.258-3.193 0-1.518.788-2.264 1.077-2.564.288-.3.63-.375.84-.375.21 0 .42.001.604.01.184.008.434-.07.683.528.25.599.854 2.079.928 2.229.074.15.124.325.025.525-.1.2-.15.325-.3.5-.15.175-.315.39-.45.525-.15.15-.307.315-.133.615.174.3.774 1.276 1.66 2.067.943.84 1.739 1.102 2.04 1.252.3.15.474.125.649-.075.175-.2.75-.875.95-1.175.2-.3.4-.25.675-.15.275.1.1.75.1.75s1.75.875 2.05.975c.3.1.5.25.4.525z"/></svg>
                      WhatsApp
                    </button>
                    <button onclick="notifierGeneralSMS(<?= htmlspecialchars(json_encode($g), ENT_QUOTES) ?>)"
                            style="background:#0ea5e9;color:#fff;border:none;padding:.4rem .8rem;border-radius:.4rem;font-weight:600;font-size:.8rem;cursor:pointer;display:inline-flex;align-items:center;gap:.3rem;"
                            title="Envoyer la synthèse SMS au client">
                      <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                      SMS
                    </button>
                    <button onclick="lancerAppelClient(<?= (int)$g['colis'][0]['colis_id'] ?>, <?= (int)$g['expediteur_id'] ?>, '<?= htmlspecialchars($g['expediteur_name']) ?>', '<?= htmlspecialchars($g['expediteur_phone']) ?>')"
                            style="background:#f97316;color:#fff;border:none;padding:.4rem .8rem;border-radius:.4rem;font-weight:600;font-size:.8rem;cursor:pointer;display:inline-flex;align-items:center;gap:.3rem;"
                            title="Appeler l'expéditeur">
                      <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                      Appeler
                    </button>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <!-- Tableau des colis du groupe -->
            <div style="overflow-x:auto;">
              <table style="width:100%;border-collapse:collapse;font-size:.85rem;">
                <thead style="background:#fafafa;color:#475569;border-bottom:1px solid #e2e8f0;">
                  <tr>
                    <th style="padding:.6rem 1rem;text-align:left;">N° Tracking</th>
                    <th style="padding:.6rem 1rem;text-align:left;">Destinataire</th>
                    <th style="padding:.6rem 1rem;text-align:right;">Poids</th>
                    <th style="padding:.6rem 1rem;text-align:left;">Statut Colis</th>
                    <th style="padding:.6rem 1rem;text-align:left;">État Départ</th>
                    <th style="padding:.6rem 1rem;text-align:left;">Motif (si resté)</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($g['colis'] as $c):
                    $stDep = $c['statut_depart'] ?? 'NON_SPECIFIE';
                    $isParti = $stDep === 'PARTI' || in_array($c['statut'], ['EN_TRANSIT', 'ARRIVÉ', 'LIVRÉ', 'RETIRÉ'], true);
                    $isReste = $stDep === 'RESTE';
                  ?>
                    <tr style="border-top:1px solid #f1f5f9;">
                      <td style="padding:.6rem 1rem;font-family:monospace;font-weight:700;color:#0ea5e9;">
                        <?= htmlspecialchars($c['numero_tracking']) ?>
                      </td>
                      <td style="padding:.6rem 1rem;"><?= htmlspecialchars($c['destinataire_name']) ?></td>
                      <td style="padding:.6rem 1rem;text-align:right;"><?= number_format((float)$c['poids_total'], 1, ',', ' ') ?> kg</td>
                      <td style="padding:.6rem 1rem;">
                        <span style="background:#f1f5f9;color:#334155;padding:.15rem .5rem;border-radius:4px;font-size:.75rem;font-weight:600;">
                          <?= htmlspecialchars($c['statut']) ?>
                        </span>
                      </td>
                      <td style="padding:.6rem 1rem;">
                        <?php if ($isParti): ?>
                          <span style="color:#15803d;font-weight:700;background:#dcfce7;padding:.2rem .6rem;border-radius:999px;font-size:.75rem;">PARTI</span>
                        <?php elseif ($isReste): ?>
                          <span style="color:#b91c1c;font-weight:700;background:#fee2e2;padding:.2rem .6rem;border-radius:999px;font-size:.75rem;">RESTÉ EN AGENCE</span>
                        <?php else: ?>
                          <span style="color:#64748b;background:#f1f5f9;padding:.2rem .6rem;border-radius:999px;font-size:.75rem;">En attente</span>
                        <?php endif; ?>
                      </td>
                      <td style="padding:.6rem 1rem;color:#b91c1c;font-weight:500;">
                        <?= $isReste && !empty($c['motif_reste']) ? htmlspecialchars($c['motif_reste']) : '—' ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div>
</div>

<!-- Modal Appel -->
<div id="modal-call-depart" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9999;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:1rem;padding:2rem;max-width:460px;width:90%;text-align:center;">
    <h3 id="call-dep-name" style="margin:0;">Appel Client</h3>
    <p id="call-dep-phone" style="color:#64748b;margin:.3rem 0 1.5rem;"></p>
    <div id="call-dep-timer" style="font-size:2.5rem;font-weight:700;font-family:monospace;margin-bottom:1.5rem;">00:00</div>
    <button onclick="finirAppelDep()" style="padding:.7rem 2rem;background:#ef4444;color:#fff;border:none;border-radius:.4rem;font-weight:700;cursor:pointer;width:100%;">Terminer l'appel</button>
    
    <div id="call-dep-note-box" style="display:none;margin-top:1.5rem;text-align:left;">
      <label style="display:block;font-size:.85rem;font-weight:600;margin-bottom:.4rem;">Résumé / Note de l'appel *</label>
      <textarea id="call-dep-note" rows="3" style="width:100%;padding:.6rem;border:1px solid #ccc;border-radius:.4rem;box-sizing:border-box;"></textarea>
      <div style="display:flex;gap:.5rem;justify-content:flex-end;margin-top:1rem;">
        <button onclick="fermerModalCallDep()" style="padding:.5rem 1rem;background:#fff;border:1px solid #ccc;border-radius:.4rem;">Fermer</button>
        <button onclick="sauvegarderAppelDep()" style="padding:.5rem 1.2rem;background:#0ea5e9;color:#fff;border:none;border-radius:.4rem;font-weight:600;">Enregistrer</button>
      </div>
    </div>
  </div>
</div>

<script>
const csrfToken = '<?= $csrfToken ?>';

function generateMessage(group) {
  let text = `Bonjour ${group.expediteur_name},\n\nPoint d'expédition pour vos colis (Service ${group.type_expediteur}) :\n`;
  
  if (group.nb_partis > 0) {
    text += `✅ ${group.nb_partis} colis sur ${group.total_colis} sont PARTIS (En transit).\n`;
  }
  if (group.nb_restes > 0) {
    text += `⚠️ ${group.nb_restes} colis est/sont RESTÉ(S) à l'agence de départ.\n`;
    group.colis.forEach(c => {
      if (c.statut_depart === 'RESTE') {
        text += `  • Tracking ${c.numero_tracking}${c.motif_reste ? ' (Motif: ' + c.motif_reste + ')' : ''}\n`;
      }
    });
    text += `Ces colis partiront lors de la prochaine expédition.\n`;
  }
  text += `\nMerci de votre confiance, LA BELLE PORTE LOGISTICS.`;
  return text;
}

function cleanPhoneNum(p) {
  return p ? p.replace(/[^\d+]/g, '') : '';
}

async function notifierGeneralWhatsApp(group) {
  const phone = cleanPhoneNum(group.expediteur_phone);
  if (!phone) { alert("Numéro de téléphone de l'expéditeur indisponible."); return; }
  const text = generateMessage(group);
  
  // Log notif
  const firstColis = group.colis[0];
  const formData = new FormData();
  formData.append('_csrf_token', csrfToken);
  formData.append('colis_id', firstColis.colis_id);
  formData.append('client_id', group.expediteur_id);
  formData.append('type_notification', 'whatsapp');
  formData.append('description', 'Synthèse départs WhatsApp : ' + group.type_expediteur);
  
  await fetch('/call-center/suivi/notifier', { method: 'POST', body: formData });
  window.open(`https://api.whatsapp.com/send?phone=${encodeURIComponent(phone)}&text=${encodeURIComponent(text)}`, '_blank');
}

async function notifierGeneralSMS(group) {
  const phone = cleanPhoneNum(group.expediteur_phone);
  if (!phone) { alert("Numéro de téléphone de l'expéditeur indisponible."); return; }
  const text = generateMessage(group);
  
  const firstColis = group.colis[0];
  const formData = new FormData();
  formData.append('_csrf_token', csrfToken);
  formData.append('colis_id', firstColis.colis_id);
  formData.append('client_id', group.expediteur_id);
  formData.append('type_notification', 'sms');
  formData.append('description', 'Synthèse départs SMS : ' + group.type_expediteur);
  
  await fetch('/call-center/suivi/notifier', { method: 'POST', body: formData });
  window.location.href = `sms:${encodeURIComponent(phone)}?body=${encodeURIComponent(text)}`;
}

let depTimer = null;
let depSec = 0;
let depColisId = 0;
let depClientId = 0;

function lancerAppelClient(colisId, clientId, name, phone) {
  const cleanP = cleanPhoneNum(phone);
  if (!cleanP) { alert("Téléphone manquant."); return; }
  depColisId = colisId;
  depClientId = clientId;
  depSec = 0;
  
  document.getElementById('call-dep-name').innerText = "Appel : " + name;
  document.getElementById('call-dep-phone').innerText = phone;
  document.getElementById('call-dep-timer').innerText = "00:00";
  document.getElementById('call-dep-note-box').style.display = 'none';
  document.getElementById('modal-call-depart').style.display = 'flex';
  
  depTimer = setInterval(() => {
    depSec++;
    const m = Math.floor(depSec/60).toString().padStart(2, '0');
    const s = (depSec%60).toString().padStart(2, '0');
    document.getElementById('call-dep-timer').innerText = `${m}:${s}`;
  }, 1000);
  
  window.location.href = `tel:${cleanP}`;
}

function finirAppelDep() {
  clearInterval(depTimer);
  document.getElementById('call-dep-note-box').style.display = 'block';
}

function fermerModalCallDep() {
  document.getElementById('modal-call-depart').style.display = 'none';
}

async function sauvegarderAppelDep() {
  const note = document.getElementById('call-dep-note').value.trim();
  if (!note) { alert("Veuillez saisir un résumé."); return; }
  
  const formData = new FormData();
  formData.append('_csrf_token', csrfToken);
  formData.append('colis_id', depColisId);
  formData.append('client_id', depClientId);
  formData.append('type_notification', 'appel');
  formData.append('duree_appel', depSec);
  formData.append('description', note);
  
  await fetch('/call-center/suivi/notifier', { method: 'POST', body: formData });
  fermerModalCallDep();
  location.reload();
}
</script>
