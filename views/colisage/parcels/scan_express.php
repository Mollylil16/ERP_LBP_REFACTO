<?php

use App\Helpers\View;
use App\Helpers\Csrf;

?>

<style>
.lbp-scan-container {
    max-width: 900px;
    margin: 0 auto;
    padding: 1.5rem;
}
.lbp-scan-card {
    background: #ffffff;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 10px 30px -10px rgba(15, 23, 42, 0.08);
    padding: 2rem;
    margin-bottom: 2rem;
}
.lbp-scan-mode-toggle {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.lbp-scan-mode-btn {
    flex: 1;
    padding: 1rem 1.5rem;
    border-radius: 12px;
    border: 2px solid #cbd5e1;
    background: #f8fafc;
    color: #475569;
    font-weight: 800;
    font-size: 1rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    transition: all 0.2s ease;
}
.lbp-scan-mode-btn.is-active-depart {
    background: #2563eb;
    color: #ffffff;
    border-color: #1d4ed8;
    box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
}
.lbp-scan-mode-btn.is-active-arrivee {
    background: #16a34a;
    color: #ffffff;
    border-color: #15803d;
    box-shadow: 0 4px 14px rgba(22, 163, 74, 0.35);
}
.lbp-scan-input-box {
    position: relative;
    margin-bottom: 1rem;
}
.lbp-scan-input {
    width: 100%;
    padding: 1.25rem 1.5rem;
    font-size: 1.3rem;
    font-weight: 800;
    border: 2px solid #2563eb;
    border-radius: 12px;
    outline: none;
    font-family: Consolas, Monaco, monospace;
    color: #0f172a;
    background: #fafafa;
    box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);
}
.lbp-scan-input:focus {
    background: #ffffff;
    box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.15);
}
.lbp-scan-feedback {
    padding: 1rem 1.25rem;
    border-radius: 10px;
    font-weight: 700;
    font-size: 0.95rem;
    margin-bottom: 1.5rem;
    display: none;
}
.lbp-scan-feedback.is-success {
    display: block;
    background: #f0fdf4;
    color: #15803d;
    border: 1px solid #bbf7d0;
}
.lbp-scan-feedback.is-error {
    display: block;
    background: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}
</style>

<div class="lbp-scan-container">

    <!-- Page Title -->
    <div style="margin-bottom: 1.5rem;">
        <h2 style="font-size: 1.5rem; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 10px; margin: 0 0 6px 0;">
            <svg viewBox="0 0 24 24" width="26" height="26" stroke="#2563eb" stroke-width="2.2" fill="none"><path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/><rect x="7" y="7" width="10" height="10" rx="1"/></svg>
            Scan Express Douchette & Tracking 2-Scans
        </h2>
        <p style="color: #64748b; font-size: 0.9rem; margin: 0;">Bipez le code-barres de l'étiquette thermique pour déclencher le transit automatique ou valider l'arrivée.</p>
    </div>

    <div class="lbp-scan-card">
        
        <!-- Mode Selector -->
        <div class="lbp-scan-mode-toggle">
            <button type="button" class="lbp-scan-mode-btn is-active-depart" id="btn-mode-depart" onclick="setScanMode('DEPART')">
                <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2.2" fill="none"><path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.7 5.2c.3.4.8.5 1.3.3l.5-.3c.4-.2.6-.6.5-1.1z"/></svg>
                SCAN DÉPART (AGENCE DÉPART)
            </button>
            <button type="button" class="lbp-scan-mode-btn" id="btn-mode-arrivee" onclick="setScanMode('ARRIVEE')" style="margin: 0;">
                <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2.2" fill="none"><path d="M12 22s-8-4.5-8-11.8A8 8 0 0 1 12 2a8 8 0 0 1 8 8.2c0 7.3-8 11.8-8 11.8z"/><circle cx="12" cy="10" r="3"/></svg>
                SCAN ARRIVÉE (AGENCE ARRIVÉE)
            </button>
        </div>

        <!-- Scan Input Box -->
        <form id="form-scan-express" onsubmit="handleScanSubmit(event)">
            <input type="hidden" id="scan_action" name="scan_action" value="DEPART">
            <div class="lbp-scan-input-box">
                <input type="text" id="barcode_input" name="barcode" class="lbp-scan-input" placeholder="Bipez le code-barres de l'étiquette ici..." autofocus autocomplete="off">
            </div>
        </form>

        <!-- Feedback Alert Message -->
        <div id="scan-feedback" class="lbp-scan-feedback"></div>

        <!-- Instructions -->
        <div style="font-size: 0.82rem; color: #64748b; background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px 16px; border-radius: 8px; display: flex; align-items: center; gap: 8px;">
            <svg viewBox="0 0 24 24" width="16" height="16" stroke="#2563eb" stroke-width="2" fill="none"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            Le curseur reste automatiquement verrouillé sur le champ de saisie pour enchaîner les bips douchette sans utiliser la souris.
        </div>
    </div>

    <!-- Scanned History Table -->
    <div style="background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 1.5rem; box-shadow: 0 10px 30px -10px rgba(15, 23, 42, 0.08);">
        <h3 style="font-size: 1.1rem; font-weight: 800; color: #0f172a; margin: 0 0 1rem 0; display: flex; align-items: center; gap: 8px;">
            <svg viewBox="0 0 24 24" width="18" height="18" stroke="#16a34a" stroke-width="2.2" fill="none"><polyline points="20 6 9 17 4 12"/></svg>
            Historique de la Session de Scan
        </h3>
        <table class="finea-table" style="width: 100%;">
            <thead>
                <tr>
                    <th>Heure</th>
                    <th>Code-Barres / Tracking</th>
                    <th>Action</th>
                    <th>Statut</th>
                    <th>Message</th>
                </tr>
            </thead>
            <tbody id="scan-history-body">
                <tr>
                    <td colspan="5" style="text-align: center; color: #94a3b8; padding: 1.5rem;">Aucun code-barres scanné dans cette session.</td>
                </tr>
            </tbody>
        </table>
    </div>

</div>

<script>
var currentScanMode = 'DEPART';

function setScanMode(mode) {
    currentScanMode = mode;
    document.getElementById('scan_action').value = mode;
    var btnDepart = document.getElementById('btn-mode-depart');
    var btnArrivee = document.getElementById('btn-mode-arrivee');

    if (mode === 'DEPART') {
        btnDepart.className = 'lbp-scan-mode-btn is-active-depart';
        btnArrivee.className = 'lbp-scan-mode-btn';
    } else {
        btnDepart.className = 'lbp-scan-mode-btn';
        btnArrivee.className = 'lbp-scan-mode-btn is-active-arrivee';
    }

    var input = document.getElementById('barcode_input');
    input.focus();
}

function handleScanSubmit(e) {
    e.preventDefault();
    var input = document.getElementById('barcode_input');
    var barcode = input.value.trim();
    if (!barcode) return;

    var feedback = document.getElementById('scan-feedback');
    feedback.className = 'lbp-scan-feedback';
    feedback.style.display = 'none';

    var formData = new FormData();
    formData.append('barcode', barcode);
    formData.append('scan_action', currentScanMode);

    fetch('<?= View::url('colisage/scan-express/process') ?>', {
        method: 'POST',
        body: formData
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
        input.value = '';
        input.focus();

        if (data.success) {
            feedback.className = 'lbp-scan-feedback is-success';
            var trackingUrl = '<?= View::url('site/tracking') ?>?ref=' + encodeURIComponent(data.tracking);
            feedback.innerHTML = data.message + ' <a href="' + trackingUrl + '" target="_blank" style="margin-left:10px; color:#2563eb; text-decoration:underline; font-weight:800;">[ 👁️ Ouvrir le suivi public sur le site ]</a>';
            addHistoryRow(data);
        } else {
            feedback.className = 'lbp-scan-feedback is-error';
            feedback.textContent = data.message;
        }
    })
    .catch(function(err) {
        input.value = '';
        input.focus();
        feedback.className = 'lbp-scan-feedback is-error';
        feedback.textContent = 'Erreur réseau lors du traitement du scan.';
    });
}

function addHistoryRow(data) {
    var tbody = document.getElementById('scan-history-body');
    if (tbody.children.length === 1 && tbody.children[0].cells.length === 1) {
        tbody.innerHTML = '';
    }

    var now = new Date();
    var timeStr = now.toLocaleTimeString();

    var tr = document.createElement('tr');
    var badgeColor = data.action === 'DEPART' ? '#2563eb' : '#16a34a';
    tr.innerHTML = '<td><strong>' + timeStr + '</strong></td>' +
        '<td><strong style="font-family:monospace; font-size:1rem; color:#0f172a;">' + data.tracking + '</strong></td>' +
        '<td><span style="background:' + badgeColor + '; color:#fff; padding:3px 10px; border-radius:12px; font-weight:800; font-size:0.75rem;">' + data.action + '</span></td>' +
        '<td><span style="font-weight:700; color:#475569;">' + data.statut + '</span></td>' +
        '<td><small style="color:#16a34a; font-weight:700;">' + data.message + '</small></td>';

    tbody.insertBefore(tr, tbody.firstChild);
}

document.addEventListener("DOMContentLoaded", function() {
    var input = document.getElementById('barcode_input');
    if (input) input.focus();
});
</script>
