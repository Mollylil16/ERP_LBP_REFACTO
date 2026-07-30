<?php
/** @var \App\Support\ViewBag $viewData */
/** @var array $colisList */
/** @var string $search */
/** @var bool $canManage */

use App\Helpers\Csrf;
use App\Helpers\Auth;

$csrfToken = Csrf::token();
?>

<div class="finea-shell">
  <div class="finea-container">

    <!-- Header -->
    <div class="rh-hero rh-hero-white" style="background: linear-gradient(135deg,#0284c7,#0369a1); color:#fff; border-radius:1rem; padding:2rem; margin-bottom:2rem;">
      <div style="display:flex;align-items:center;gap:1rem;">
        <div style="width:56px;height:56px;border-radius:50%;background:rgba(255,255,255,0.2);display:flex;align-items:center;justify-content:center;font-size:1.6rem;">📱</div>
        <div>
          <div style="font-size:0.8rem;opacity:0.8;letter-spacing:0.08em;text-transform:uppercase;">CAL Suivi</div>
          <h1 style="font-size:1.6rem;font-weight:700;margin:0;">Suivi & Relances Clients</h1>
          <p style="opacity:0.8;margin:0.2rem 0 0;">Notifier les clients par WhatsApp/SMS et tracer les appels via Mobile connecté</p>
        </div>
      </div>
    </div>

    <!-- Recherche -->
    <form method="GET" action="/call-center/suivi" style="background:#fff;border-radius:.75rem;padding:1.25rem;margin-bottom:1.5rem;box-shadow:0 1px 4px rgba(0,0,0,.07);display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap;">
      <div style="flex:1;min-width:250px;">
        <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:.3rem;">Rechercher un colis ou client</label>
        <input type="text" name="q" placeholder="Ex: LB-CI-0726-001, Nom du client, Téléphone..." value="<?= htmlspecialchars($search) ?>" style="width:100%;padding:.6rem .8rem;border:1px solid #d1d5db;border-radius:.4rem;font-size:.9rem;box-sizing:border-box;">
      </div>
      <button type="submit" style="padding:.6rem 1.5rem;background:#0369a1;color:#fff;border:none;border-radius:.4rem;font-weight:600;cursor:pointer;height:38px;box-sizing:border-box;">Rechercher</button>
      <?php if ($search !== ''): ?>
        <a href="/call-center/suivi" style="padding:.6rem 1.2rem;background:#f1f5f9;color:#475569;border:1px solid #cbd5e1;border-radius:.4rem;font-weight:600;text-decoration:none;font-size:.9rem;height:38px;box-sizing:border-box;display:inline-flex;align-items:center;">Effacer</a>
      <?php endif; ?>
    </form>

    <!-- Tableau -->
    <div style="background:#fff;border-radius:.75rem;box-shadow:0 1px 4px rgba(0,0,0,.07);overflow:hidden;">
      <?php if (empty($colisList)): ?>
        <div style="padding:3rem;text-align:center;color:#94a3b8;">Aucun colis trouvé.</div>
      <?php else: ?>
        <table style="width:100%;border-collapse:collapse;font-size:.875rem;">
          <thead style="background:#f8fafc;">
            <tr>
              <th style="text-align:left;padding:.8rem 1rem;color:#374151;font-weight:600;">N° Tracking</th>
              <th style="text-align:left;padding:.8rem 1rem;color:#374151;font-weight:600;">Destinataire</th>
              <th style="text-align:left;padding:.8rem 1rem;color:#374151;font-weight:600;">Téléphone</th>
              <th style="text-align:left;padding:.8rem 1rem;color:#374151;font-weight:600;">Statut Colis</th>
              <th style="text-align:left;padding:.8rem 1rem;color:#374151;font-weight:600;">État de Notification</th>
              <?php if ($canManage): ?>
                <th style="text-align:center;padding:.8rem 1rem;color:#374151;font-weight:600;">Actions de Relance</th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($colisList as $c): ?>
            <tr style="border-top:1px solid #f1f5f9;">
              <td style="padding:.8rem 1rem;font-family:monospace;font-weight:600;color:#0f766e;"><?= htmlspecialchars((string)$c['numero_tracking']) ?></td>
              <td style="padding:.8rem 1rem;font-weight:500;"><?= htmlspecialchars((string)($c['destinataire_nom'] ?? '—')) ?></td>
              <td style="padding:.8rem 1rem;color:#475569;"><?= htmlspecialchars((string)($c['destinataire_tel'] ?? '—')) ?></td>
              <td style="padding:.8rem 1rem;">
                <span style="background:#f1f5f9;color:#475569;padding:.2rem .5rem;border-radius:4px;font-size:.75rem;font-weight:600;"><?= htmlspecialchars((string)$c['statut']) ?></span>
              </td>
              <td style="padding:.8rem 1rem;">
                <?php if ($c['type_notification']): ?>
                  <div style="display:inline-flex;align-items:center;gap:.4rem;background:#dcfce7;color:#15803d;padding:.3rem .8rem;border:1px solid #86efac;border-radius:999px;font-size:.75rem;font-weight:600;position:relative;" class="notif-badge">
                    <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#22c55e;"></span>
                    Notifié par <?= htmlspecialchars((string)$c['type_notification']) ?>
                    
                    <div class="notif-tooltip" style="display:none;position:absolute;bottom:100%;left:50%;transform:translateX(-50%);margin-bottom:8px;background:#1e293b;color:#fff;padding:8px 12px;border-radius:.4rem;width:240px;box-shadow:0 4px 6px rgba(0,0,0,.15);z-index:100;font-weight:normal;line-height:1.4;">
                      <strong>Détails relance :</strong><br>
                      📅 Le : <?= date('d/m/Y H:i', strtotime($c['notification_date'])) ?><br>
                      👤 Agent : <?= htmlspecialchars((string)$c['agent_name']) ?><br>
                      <?php if ($c['type_notification'] === 'appel'): ?>
                        ⏱️ Durée : <?= $c['duree_appel'] ? sprintf("%02d:%02d", floor($c['duree_appel']/60), $c['duree_appel']%60) : '00:00' ?><br>
                        ⭐ Score : <?= $c['satisfaction_score'] ? str_repeat('★', (int)$c['satisfaction_score']) : '—' ?><br>
                      <?php endif; ?>
                      <?php if ($c['notification_desc']): ?>
                        📝 Note : <?= htmlspecialchars((string)$c['notification_desc']) ?>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php else: ?>
                  <span style="display:inline-flex;align-items:center;gap:.4rem;background:#fee2e2;color:#b91c1c;padding:.3rem .8rem;border:1px solid #fca5a5;border-radius:999px;font-size:.75rem;font-weight:600;">
                    <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#ef4444;"></span>
                    Non notifié
                  </span>
                <?php endif; ?>
              </td>
              <?php if ($canManage): ?>
                <td style="padding:.8rem 1rem;text-align:center;">
                  <div style="display:inline-flex;gap:.5rem;">
                    <!-- Bouton WhatsApp -->
                    <button onclick="notifierViaWhatsApp(<?= (int)$c['id'] ?>, <?= (int)($c['destinataire_id'] ?? 0) ?>, '<?= htmlspecialchars((string)($c['destinataire_tel'] ?? '')) ?>', '<?= htmlspecialchars((string)$c['numero_tracking']) ?>', '<?= htmlspecialchars((string)$c['statut']) ?>')" class="btn-action-cc btn-whatsapp" title="Notifier par WhatsApp">
                      <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M12.012 2c-5.506 0-9.989 4.478-9.99 9.984a9.96 9.96 0 0 0 1.333 4.982L2 22l5.233-1.371a9.942 9.942 0 0 0 4.777 1.219h.005c5.505 0 9.989-4.478 9.99-9.986 0-2.67-1.037-5.178-2.924-7.067C17.197 2.909 14.69 2 12.012 2zm5.727 14.076c-.314.88-1.545 1.614-2.122 1.697-.577.083-1.127.306-3.666-.694-3.155-1.242-5.187-4.456-5.344-4.664-.158-.209-1.258-1.675-1.258-3.193 0-1.518.788-2.264 1.077-2.564.288-.3.63-.375.84-.375.21 0 .42.001.604.01.184.008.434-.07.683.528.25.599.854 2.079.928 2.229.074.15.124.325.025.525-.1.2-.15.325-.3.5-.15.175-.315.39-.45.525-.15.15-.307.315-.133.615.174.3.774 1.276 1.66 2.067.943.84 1.739 1.102 2.04 1.252.3.15.474.125.649-.075.175-.2.75-.875.95-1.175.2-.3.4-.25.675-.15.275.1.1.75.1.75s1.75.875 2.05.975c.3.1.5.25.4.525z"/></svg>
                    </button>

                    <!-- Bouton SMS -->
                    <button onclick="notifierViaSMS(<?= (int)$c['id'] ?>, <?= (int)($c['destinataire_id'] ?? 0) ?>, '<?= htmlspecialchars((string)($c['destinataire_tel'] ?? '')) ?>', '<?= htmlspecialchars((string)$c['numero_tracking']) ?>', '<?= htmlspecialchars((string)$c['statut']) ?>')" class="btn-action-cc btn-sms" title="Notifier par SMS">
                      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    </button>

                    <!-- Bouton Appeler -->
                    <button onclick="lancerAppel(<?= (int)$c['id'] ?>, <?= (int)($c['destinataire_id'] ?? 0) ?>, '<?= htmlspecialchars((string)($c['destinataire_nom'] ?? '—')) ?>', '<?= htmlspecialchars((string)($c['destinataire_tel'] ?? '')) ?>')" class="btn-action-cc btn-call" title="Appeler (Mobile connecté)">
                      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    </button>
                  </div>
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

<!-- Modal Appel Interactif -->
<div id="modal-timer-call" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9999;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:1rem;padding:2rem;max-width:460px;width:90%;box-shadow:0 10px 25px rgba(0,0,0,.15);text-align:center;">
    <div style="font-size:3rem;margin-bottom:1rem;">📞</div>
    <h3 style="margin:0;font-size:1.3rem;color:#1e293b;" id="call-client-name">Appel avec Client</h3>
    <p style="color:#64748b;font-size:.9rem;margin:.3rem 0 1.5rem;" id="call-client-phone">00 00 00 00</p>
    
    <!-- Timer -->
    <div style="font-size:2.5rem;font-weight:700;color:#0f172a;font-family:monospace;margin-bottom:2rem;" id="call-timer-display">00:00</div>
    
    <!-- Boutons fin d'appel / enregistrement -->
    <div id="call-timer-actions">
      <button onclick="terminerAppel()" style="padding:.8rem 2rem;background:#ef4444;color:#fff;border:none;border-radius:.5rem;font-weight:700;font-size:1rem;cursor:pointer;width:100%;">Terminer l'appel</button>
    </div>
    
    <div id="call-save-form" style="display:none;text-align:left;margin-top:1.5rem;border-top:1px solid #e2e8f0;padding-top:1.5rem;">
      <div style="display:grid;gap:1rem;">
        <div>
          <label style="display:block;font-size:.85rem;font-weight:600;margin-bottom:.4rem;color:#374151;">Note / Résumé de l'appel *</label>
          <textarea id="call-description" rows="3" required placeholder="Ex: Client informé de l'arrivée du colis..." style="width:100%;padding:.6rem .8rem;border:1px solid #d1d5db;border-radius:.5rem;font-size:.9rem;resize:vertical;box-sizing:border-box;"></textarea>
        </div>
        <div>
          <label style="display:block;font-size:.85rem;font-weight:600;margin-bottom:.4rem;color:#374151;">Satisfaction (1 à 5)</label>
          <div style="display:flex;gap:.5rem;">
            <?php for ($i = 1; $i <= 5; $i++): ?>
              <label style="display:flex;flex-direction:column;align-items:center;gap:.2rem;cursor:pointer;">
                <input type="radio" name="call_satisfaction" value="<?= $i ?>" style="accent-color:#0ea5e9;">
                <span style="font-size:1.1rem;"><?= str_repeat('⭐', $i) ?></span>
              </label>
            <?php endfor; ?>
          </div>
        </div>
        <div style="display:flex;gap:.75rem;justify-content:flex-end;margin-top:.5rem;">
          <button type="button" onclick="annulerAppel()" style="padding:.6rem 1.2rem;border:1px solid #d1d5db;border-radius:.5rem;background:#fff;cursor:pointer;">Abandonner</button>
          <button type="button" onclick="enregistrerAppel()" style="padding:.6rem 1.5rem;background:#0ea5e9;color:#fff;border:none;border-radius:.5rem;font-weight:600;cursor:pointer;">Enregistrer l'appel</button>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
.btn-action-cc {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all .2s;
  color: #fff;
}
.btn-whatsapp {
  background-color: #22c55e;
}
.btn-whatsapp:hover {
  background-color: #16a34a;
}
.btn-sms {
  background-color: #0ea5e9;
}
.btn-sms:hover {
  background-color: #0284c7;
}
.btn-call {
  background-color: #f97316;
}
.btn-call:hover {
  background-color: #ea580c;
}
.notif-badge:hover .notif-tooltip {
  display: block !important;
}
</style>

<script>
// CSRF Token global
const csrfToken = '<?= $csrfToken ?>';

// Variables d'appel
let callTimer = null;
let secondsElapsed = 0;
let currentColisId = 0;
let currentClientId = 0;

function formatPhone(phone) {
  if (!phone) return '';
  return phone.replace(/[^\d+]/g, '');
}

// Fonction AJAX de log
async function logNotification(colisId, clientId, type, extraData = {}) {
  const formData = new FormData();
  formData.append('_csrf_token', csrfToken);
  formData.append('colis_id', colisId);
  formData.append('client_id', clientId);
  formData.append('type_notification', type);
  
  for (const [key, value] of Object.entries(extraData)) {
    if (value !== null && value !== undefined) {
      formData.append(key, value);
    }
  }

  try {
    const res = await fetch('/call-center/suivi/notifier', {
      method: 'POST',
      body: formData
    });
    const data = await res.json();
    return data.ok;
  } catch (e) {
    console.error(e);
    return false;
  }
}

// Relance WhatsApp
async function notifierViaWhatsApp(colisId, clientId, phone, tracking, statut) {
  const cleanPhone = formatPhone(phone);
  if (!cleanPhone) {
    alert("Téléphone manquant pour ce client.");
    return;
  }
  const text = `Bonjour, votre colis LBP de tracking ${tracking} est actuellement au statut : ${statut}. Merci pour votre confiance !`;
  const ok = await logNotification(colisId, clientId, 'whatsapp');
  if (ok) {
    window.open(`https://api.whatsapp.com/send?phone=${encodeURIComponent(cleanPhone)}&text=${encodeURIComponent(text)}`, '_blank');
    location.reload();
  } else {
    alert("Une erreur est survenue lors de l'enregistrement de la relance.");
  }
}

// Relance SMS
async function notifierViaSMS(colisId, clientId, phone, tracking, statut) {
  const cleanPhone = formatPhone(phone);
  if (!cleanPhone) {
    alert("Téléphone manquant pour ce client.");
    return;
  }
  const text = `Bonjour, votre colis LBP de tracking ${tracking} est actuellement au statut : ${statut}. Merci pour votre confiance !`;
  const ok = await logNotification(colisId, clientId, 'sms');
  if (ok) {
    window.location.href = `sms:${encodeURIComponent(cleanPhone)}?body=${encodeURIComponent(text)}`;
    setTimeout(() => location.reload(), 1500);
  } else {
    alert("Une erreur est survenue lors de l'enregistrement de la relance.");
  }
}

// Gestion des Appels
function lancerAppel(colisId, clientId, name, phone) {
  const cleanPhone = formatPhone(phone);
  if (!cleanPhone) {
    alert("Téléphone manquant pour ce client.");
    return;
  }

  currentColisId = colisId;
  currentClientId = clientId;
  secondsElapsed = 0;

  document.getElementById('call-client-name').innerText = "Appel avec : " + name;
  document.getElementById('call-client-phone').innerText = phone;
  document.getElementById('call-timer-display').innerText = "00:00";
  document.getElementById('call-timer-actions').style.display = 'block';
  document.getElementById('call-save-form').style.display = 'none';
  document.getElementById('modal-timer-call').style.display = 'flex';

  // Lancer le chronomètre
  callTimer = setInterval(() => {
    secondsElapsed++;
    const m = Math.floor(secondsElapsed / 60).toString().padStart(2, '0');
    const s = (secondsElapsed % 60).toString().padStart(2, '0');
    document.getElementById('call-timer-display').innerText = `${m}:${s}`;
  }, 1000);

  // Déclencher l'appel tel:
  window.location.href = `tel:${cleanPhone}`;
}

function terminerAppel() {
  clearInterval(callTimer);
  document.getElementById('call-timer-actions').style.display = 'none';
  document.getElementById('call-save-form').style.display = 'block';
}

function annulerAppel() {
  document.getElementById('modal-timer-call').style.display = 'none';
}

async function enregistrerAppel() {
  const desc = document.getElementById('call-description').value.trim();
  if (!desc) {
    alert("Veuillez renseigner un résumé pour enregistrer l'appel.");
    return;
  }

  const satisfactionEl = document.querySelector('input[name="call_satisfaction"]:checked');
  const satisfaction = satisfactionEl ? satisfactionEl.value : null;

  const ok = await logNotification(currentColisId, currentClientId, 'appel', {
    duree_appel: secondsElapsed,
    description: desc,
    satisfaction_score: satisfaction
  });

  if (ok) {
    document.getElementById('modal-timer-call').style.display = 'none';
    location.reload();
  } else {
    alert("Erreur lors de l'enregistrement de l'appel.");
  }
}
</script>
