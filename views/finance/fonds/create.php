<?php

declare(strict_types=1);

use App\Helpers\Csrf;
use App\Helpers\View;

/**
 * @var array<int, array{id: int, name: string, code: string}> $agences
 * @var array<int, string> $dossiersRecents
 * @var string $defaultNum
 * @var int $userAgenceId
 */
?>

<div class="finea-shell">
    <div class="finea-container" style="max-width: 900px; margin: 0 auto; padding: 1.5rem 1rem;">

        <!-- Header -->
        <div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <a href="<?= View::url('finance/fonds') ?>" style="display: inline-flex; align-items: center; gap: 6px; color: #64748b; text-decoration: none; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.5rem;">
                    ← Retour aux demandes de fonds
                </a>
                <h1 style="font-size: 1.6rem; font-weight: 800; color: #0f172a; margin: 0;">Ajouter une Demande de Décaissement</h1>
                <p style="color: #64748b; font-size: 0.9rem; margin: 0.2rem 0 0;">Remplissez le formulaire ci-dessous pour soumettre une demande de fonds pour validation.</p>
            </div>
            <div style="background: #e0f2fe; color: #0369a1; padding: 6px 12px; border-radius: 6px; font-weight: 700; font-family: monospace; font-size: 0.9rem;">
                Réf: <?= View::e($defaultNum) ?>
            </div>
        </div>

        <!-- Form Card -->
        <div style="background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); padding: 2rem;">
            <form method="post" action="<?= View::url('finance/fonds/enregistrer') ?>" id="formDemandeFonds">
                <?= Csrf::field() ?>

                <!-- Cadre de la demande (Dossier vs Fonctionnement) -->
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; font-weight: 700; color: #1e293b; margin-bottom: 0.5rem; font-size: 0.9rem;">
                        Cadre de la dépense <span style="color: #dc2626;">*</span>
                    </label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <label style="border: 2px solid #2563eb; background: #eff6ff; padding: 1rem; border-radius: 8px; cursor: pointer; display: flex; align-items: flex-start; gap: 10px;" id="lblDossier">
                            <input type="radio" name="cadre" value="traitement_dossier" checked style="margin-top: 3px;" onchange="toggleCadre('traitement_dossier')">
                            <div>
                                <strong style="color: #1d4ed8; display: block; font-size: 0.95rem;">Traitement de Dossier</strong>
                                <small style="color: #64748b; font-size: 0.8rem;">Dépense opérationnelle rattachée à un dossier de transit (visite douane, tirage BL, manutention, transport, etc.)</small>
                            </div>
                        </label>
                        <label style="border: 1px solid #cbd5e1; background: #f8fafc; padding: 1rem; border-radius: 8px; cursor: pointer; display: flex; align-items: flex-start; gap: 10px;" id="lblFonct">
                            <input type="radio" name="cadre" value="fonctionnement" style="margin-top: 3px;" onchange="toggleCadre('fonctionnement')">
                            <div>
                                <strong style="color: #475569; display: block; font-size: 0.95rem;">Fonctionnement Général</strong>
                                <small style="color: #64748b; font-size: 0.8rem;">Dépenses courantes de bureau, carburant, assurances, petit matériel, fournitures agence.</small>
                            </div>
                        </label>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.25rem;">
                    <!-- Agence concernée -->
                    <div>
                        <label style="display: block; font-weight: 700; color: #1e293b; margin-bottom: 0.35rem; font-size: 0.85rem;">
                            Agence de rattachement <span style="color: #dc2626;">*</span>
                        </label>
                        <select name="agence_id" class="finea-select" required style="width: 100%; padding: 0.65rem; border: 1px solid #cbd5e1; border-radius: 6px;">
                            <?php foreach ($agences as $ag): ?>
                                <option value="<?= $ag['id'] ?>" <?= $userAgenceId === (int)$ag['id'] ? 'selected' : '' ?>>
                                    <?= View::e($ag['name']) ?> (<?= View::e($ag['code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- N° Dossier Transit (conditionnel) -->
                    <div id="blocDossier">
                        <label style="display: block; font-weight: 700; color: #1e293b; margin-bottom: 0.35rem; font-size: 0.85rem;">
                            N° de Dossier / Tracking <span style="color: #dc2626;">*</span>
                        </label>
                        <input type="text" name="dossier_num" id="inputDossier" list="listDossiers" placeholder="Ex: S-IM00379/26 ou N° Colis LBP" class="finea-input" style="width: 100%; padding: 0.65rem; border: 1px solid #cbd5e1; border-radius: 6px;" required>
                        <datalist id="listDossiers">
                            <?php foreach ($dossiersRecents as $dossier): ?>
                                <option value="<?= View::e($dossier) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                        <small style="color: #64748b; font-size: 0.75rem;">Saisissez le N° du dossier de transit ou sélectionnez dans l'historique.</small>
                    </div>
                </div>

                <!-- Montant & Devise -->
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.25rem; margin-bottom: 1.25rem;">
                    <div>
                        <label style="display: block; font-weight: 700; color: #1e293b; margin-bottom: 0.35rem; font-size: 0.85rem;">
                            Montant sollicité <span style="color: #dc2626;">*</span>
                        </label>
                        <input type="number" step="100" min="100" name="montant" placeholder="Ex: 50000" required class="finea-input" style="width: 100%; padding: 0.65rem; border: 1px solid #cbd5e1; border-radius: 6px; font-weight: 800; font-size: 1.1rem; color: #0f172a;">
                    </div>
                    <div>
                        <label style="display: block; font-weight: 700; color: #1e293b; margin-bottom: 0.35rem; font-size: 0.85rem;">
                            Devise
                        </label>
                        <select name="devise" class="finea-select" style="width: 100%; padding: 0.65rem; border: 1px solid #cbd5e1; border-radius: 6px; font-weight: 700;">
                            <option value="XOF" selected>FCFA (XOF)</option>
                            <option value="EUR">Euros (€)</option>
                            <option value="USD">Dollars ($)</option>
                        </select>
                    </div>
                </div>

                <!-- Motif / Libellé de la dépense -->
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; font-weight: 700; color: #1e293b; margin-bottom: 0.35rem; font-size: 0.85rem;">
                        Motif précis / Libellé de la demande <span style="color: #dc2626;">*</span>
                    </label>
                    <textarea name="motif" rows="3" placeholder="Ex: TRANSPORT POUR LA VISITE / VJ LIQUOR / 03 TC 40' / CARAMEL" required class="finea-textarea" style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem;"></textarea>
                    <small style="color: #64748b; font-size: 0.75rem;">Indiquez l'objet exact pour lequel les fonds sont requis afin de faciliter la validation par la Direction.</small>
                </div>

                <!-- Alert info -->
                <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem; display: flex; gap: 10px; align-items: flex-start;">
                    <span style="font-size: 1.2rem;">ℹ️</span>
                    <div style="font-size: 0.82rem; color: #166534; line-height: 1.4;">
                        <strong>Circuit de validation :</strong> Une fois enregistrée, la demande sera envoyée à la Direction (Assistante DG / DG) pour validation. Après validation, la caisse pourra procéder au décaissement et éditer votre <strong>Bon de Sortie de Caisse</strong>.
                    </div>
                </div>

                <!-- Actions -->
                <div style="display: flex; justify-content: flex-end; gap: 1rem; align-items: center; border-top: 1px solid #f1f5f9; padding-top: 1.25rem;">
                    <a href="<?= View::url('finance/fonds') ?>" style="color: #64748b; text-decoration: none; font-weight: 600; font-size: 0.9rem;">
                        Annuler
                    </a>
                    <button type="submit" class="finea-button finea-button--primary" style="padding: 0.75rem 1.8rem; font-weight: 700; font-size: 0.95rem; border-radius: 8px; background: #0284c7; color: #fff; border: none; cursor: pointer; box-shadow: 0 4px 12px rgba(2, 132, 199, 0.25);">
                        Soumettre la demande de fonds
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>

<script>
function toggleCadre(type) {
    const blocDossier = document.getElementById('blocDossier');
    const inputDossier = document.getElementById('inputDossier');
    const lblDossier = document.getElementById('lblDossier');
    const lblFonct = document.getElementById('lblFonct');

    if (type === 'traitement_dossier') {
        blocDossier.style.display = 'block';
        inputDossier.setAttribute('required', 'required');
        lblDossier.style.borderColor = '#2563eb';
        lblDossier.style.background = '#eff6ff';
        lblFonct.style.borderColor = '#cbd5e1';
        lblFonct.style.background = '#f8fafc';
    } else {
        blocDossier.style.display = 'none';
        inputDossier.removeAttribute('required');
        lblDossier.style.borderColor = '#cbd5e1';
        lblDossier.style.background = '#f8fafc';
        lblFonct.style.borderColor = '#7c3aed';
        lblFonct.style.background = '#f5f3ff';
    }
}
</script>
