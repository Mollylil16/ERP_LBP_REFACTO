<?php

declare(strict_types=1);

use App\Helpers\Csrf;
use App\Helpers\View;

/**
 * @var \App\Models\Finance\DemandeFonds $demande
 * @var array<int, array<string, mixed>> $historique
 * @var bool $canValidate
 * @var bool $canDecaisser
 * @var bool $canImputer
 */

$statusBadges = [
    'en_attente' => ['label' => 'En attente de validation', 'bg' => '#fee2e2', 'color' => '#dc2626', 'icon' => '⏳'],
    'validee'    => ['label' => 'Validée (À décaisser)',    'bg' => '#fef3c7', 'color' => '#d97706', 'icon' => '✓'],
    'decaissee'  => ['label' => 'Décaissée (En cours)',     'bg' => '#e0e7ff', 'color' => '#4338ca', 'icon' => '💵'],
    'imputee'    => ['label' => 'Imputée & Clôturée',       'bg' => '#dcfce7', 'color' => '#15803d', 'icon' => '✅'],
    'rejetee'    => ['label' => 'Rejetée',                  'bg' => '#f1f5f9', 'color' => '#64748b', 'icon' => '✕'],
];

$badgeInfo = $statusBadges[$demande->statut] ?? ['label' => ucfirst($demande->statut), 'bg' => '#f1f5f9', 'color' => '#475569', 'icon' => '•'];
$cadreLabel = $demande->cadre === 'traitement_dossier' ? 'Traitement de Dossier (Transit)' : 'Fonctionnement Général';
$cadreColor = $demande->cadre === 'traitement_dossier' ? '#2563eb' : '#7c3aed';
?>

<div class="finea-shell">
    <div class="finea-container" style="max-width: 1200px; margin: 0 auto; padding: 1.5rem 1rem;">

        <!-- Top Navigation Bar -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <a href="<?= View::url('finance/fonds') ?>" style="display: inline-flex; align-items: center; gap: 6px; color: #64748b; text-decoration: none; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem;">
                    ← Retour à la liste des demandes
                </a>
                <div style="display: flex; align-items: center; gap: 12px;">
                    <h1 style="font-size: 1.75rem; font-weight: 800; color: #0f172a; margin: 0;">
                        Demande <?= View::e($demande->numeroDemande) ?>
                    </h1>
                    <span style="display: inline-block; padding: 5px 12px; border-radius: 9999px; font-size: 0.8rem; font-weight: 700; background: <?= $badgeInfo['bg'] ?>; color: <?= $badgeInfo['color'] ?>;">
                        <?= $badgeInfo['icon'] ?> <?= View::e($badgeInfo['label']) ?>
                    </span>
                </div>
            </div>

            <!-- Fast Action Buttons (PDF, Validation, Décaissement) -->
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <?php if (in_array($demande->statut, ['validee', 'decaissee', 'imputee'])): ?>
                    <a href="<?= View::url('finance/fonds/' . $demande->id . '/bon-caisse-pdf') ?>" target="_blank" class="finea-button" style="display: inline-flex; align-items: center; gap: 6px; padding: 0.65rem 1.2rem; font-weight: 700; font-size: 0.875rem; border-radius: 8px; background: #0f172a; color: #fff; text-decoration: none;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                        Bon de Sortie (PDF)
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; align-items: start;">

            <!-- Left Main Column : Détails & Formulaires d'actions -->
            <div style="display: flex; flex-direction: column; gap: 1.5rem;">

                <!-- Fiche Récapitulative -->
                <div style="background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); padding: 1.5rem;">
                    <h3 style="margin: 0 0 1.25rem; font-size: 1.1rem; font-weight: 800; color: #0f172a; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.75rem;">
                        Informations sur la Demande
                    </h3>

                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.25rem; margin-bottom: 1.5rem;">
                        <div>
                            <div style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Cadre de la dépense</div>
                            <div style="margin-top: 0.25rem;">
                                <span style="font-size: 0.85rem; font-weight: 700; padding: 3px 10px; border-radius: 6px; background: <?= $cadreColor ?>15; color: <?= $cadreColor ?>;">
                                    <?= View::e($cadreLabel) ?>
                                </span>
                            </div>
                        </div>

                        <div>
                            <div style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">N° Dossier / Transit</div>
                            <div style="font-size: 1rem; font-weight: 800; color: #0f172a; margin-top: 0.25rem; font-family: monospace;">
                                <?= !empty($demande->dossierNum) ? View::e($demande->dossierNum) : '<span style="color:#94a3b8;">Non rattaché (Fonctionnement)</span>' ?>
                            </div>
                        </div>

                        <div>
                            <div style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Montant Sollicité</div>
                            <div style="font-size: 1.5rem; font-weight: 900; color: #0284c7; margin-top: 0.25rem;">
                                <?= number_format($demande->montant, 0, ',', ' ') ?> <span style="font-size: 0.9rem; color: #64748b;"><?= View::e($demande->devise) ?></span>
                            </div>
                        </div>

                        <div>
                            <div style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Agence & Date</div>
                            <div style="font-size: 0.95rem; font-weight: 700; color: #1e293b; margin-top: 0.25rem;">
                                🏢 <?= View::e($demande->agenceNom ?? 'Agence LBP') ?>
                            </div>
                            <div style="font-size: 0.8rem; color: #64748b;">
                                Le <?= $demande->createdAt ? date('d/m/Y à H:i', strtotime($demande->createdAt)) : '—' ?>
                            </div>
                        </div>
                    </div>

                    <!-- Motif détaillé -->
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; margin-bottom: 1rem;">
                        <div style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 0.35rem;">
                            Motif & Libellé de la dépense
                        </div>
                        <div style="font-size: 0.95rem; color: #0f172a; font-weight: 600; line-height: 1.5;">
                            <?= nl2br(View::e($demande->motif)) ?>
                        </div>
                    </div>

                    <!-- Intervenants -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1rem; padding-top: 1rem; border-top: 1px solid #f1f5f9; font-size: 0.85rem;">
                        <div>
                            <span style="color: #64748b; display: block; font-size: 0.75rem; text-transform: uppercase; font-weight: 700;">Demandeur</span>
                            <strong style="color: #1e293b;"><?= View::e($demande->demandeurNom ?? '—') ?></strong>
                        </div>
                        <div>
                            <span style="color: #64748b; display: block; font-size: 0.75rem; text-transform: uppercase; font-weight: 700;">Validateur (Direction)</span>
                            <strong style="color: <?= $demande->validateurNom ? '#166534' : '#94a3b8' ?>;">
                                <?= View::e($demande->validateurNom ?? 'En attente') ?>
                            </strong>
                        </div>
                        <div>
                            <span style="color: #64748b; display: block; font-size: 0.75rem; text-transform: uppercase; font-weight: 700;">Décaissement Caisse</span>
                            <strong style="color: <?= $demande->caissiereNom ? '#4338ca' : '#94a3b8' ?>;">
                                <?= View::e($demande->caissiereNom ?? 'Non décaissé') ?>
                            </strong>
                        </div>
                    </div>

                    <?php if ($demande->statut === 'rejetee' && !empty($demande->motifRejet)): ?>
                        <div style="margin-top: 1.25rem; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 1rem; color: #991b1b;">
                            <strong>Motif du rejet :</strong> <?= View::e($demande->motifRejet) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- SECTION ACTIONS CONDITIONNELLES -->

                <!-- 1. BLOC DE VALIDATION / REJET (Réservé Direction : Assistante DG / DG / Admin) -->
                <?php if ($demande->statut === 'en_attente' && $canValidate): ?>
                    <div style="background: #ffffff; border-radius: 12px; border: 2px solid #38bdf8; box-shadow: 0 4px 12px rgba(56, 189, 248, 0.15); padding: 1.5rem;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.75rem;">
                            <span style="font-size: 1.2rem;">🛡️</span>
                            <h3 style="margin: 0; font-size: 1.1rem; font-weight: 800; color: #0369a1;">
                                Décision Direction (Assistante DG / DG / Admin)
                            </h3>
                        </div>
                        <p style="color: #64748b; font-size: 0.85rem; margin: 0 0 1.25rem;">
                            En tant que Direction, vous pouvez autoriser le décaissement en caisse ou rejeter cette demande de fonds.
                        </p>

                        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                            <!-- Formulaire Validation -->
                            <form method="post" action="<?= View::url('finance/fonds/' . $demande->id . '/valider') ?>" style="flex: 1;" onsubmit="return confirm('Confirmer la validation de cette demande de fonds pour décaissement ?');">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="commentaire" value="Validé par la Direction">
                                <button type="submit" class="finea-button finea-button--primary" style="width: 100%; padding: 0.75rem; font-weight: 700; background: #16a34a; border: none; border-radius: 8px; color: #fff; cursor: pointer; display: inline-flex; justify-content: center; align-items: center; gap: 6px;">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                    Valider et Autoriser le Décaissement
                                </button>
                            </form>

                            <!-- Formulaire Rejet -->
                            <form method="post" action="<?= View::url('finance/fonds/' . $demande->id . '/rejeter') ?>" style="flex: 1;">
                                <?= Csrf::field() ?>
                                <div style="display: flex; gap: 6px;">
                                    <input type="text" name="motif_rejet" placeholder="Motif du rejet..." required class="finea-input" style="flex: 1; padding: 0.65rem; border: 1px solid #fca5a5; border-radius: 6px; font-size: 0.85rem;">
                                    <button type="submit" style="background: #dc2626; color: #fff; border: none; border-radius: 6px; padding: 0.65rem 1rem; font-weight: 700; cursor: pointer; font-size: 0.85rem;" onclick="return confirm('Êtes-vous sûr de vouloir rejeter cette demande ?');">
                                        Rejeter
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- 2. BLOC DECAISSEMENT CAISSE (Quand statut = validee) -->
                <?php if ($demande->statut === 'validee' && $canDecaisser): ?>
                    <div style="background: #ffffff; border-radius: 12px; border: 2px solid #fbbf24; box-shadow: 0 4px 12px rgba(251, 191, 36, 0.15); padding: 1.5rem;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.75rem;">
                            <span style="font-size: 1.2rem;">💵</span>
                            <h3 style="margin: 0; font-size: 1.1rem; font-weight: 800; color: #b45309;">
                                Prise en Compte Caisse & Décaissement
                            </h3>
                        </div>
                        <p style="color: #64748b; font-size: 0.85rem; margin: 0 0 1.25rem;">
                            La demande est validée par la Direction. La caissière peut maintenant enregistrer le paiement physique et éditer le Bon de Sortie.
                        </p>

                        <form method="post" action="<?= View::url('finance/fonds/' . $demande->id . '/decaisser') ?>" style="display: flex; gap: 1rem; align-items: center;">
                            <?= Csrf::field() ?>
                            <div style="flex: 1;">
                                <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #78350f; text-transform: uppercase; margin-bottom: 0.25rem;">Mode de paiement</label>
                                <select name="mode_paiement" class="finea-select" style="width: 100%; padding: 0.65rem; border: 1px solid #fcd34d; border-radius: 6px; font-weight: 700;">
                                    <option value="Espèces">Espèces (Caisse Agence)</option>
                                    <option value="Mobile Money (Wave / Orange)">Mobile Money (Wave / Orange)</option>
                                    <option value="Chèque">Chèque Bancaire</option>
                                    <option value="Virement">Virement Bancaire</option>
                                </select>
                            </div>
                            <div style="padding-top: 1.1rem;">
                                <button type="submit" class="finea-button finea-button--primary" style="padding: 0.75rem 1.5rem; font-weight: 700; background: #d97706; border: none; border-radius: 8px; color: #fff; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;" onclick="return confirm('Confirmer le décaissement de <?= number_format($demande->montant, 0, ',', ' ') ?> FCFA ?');">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="4" width="20" height="16" rx="2"></rect><line x1="6" y1="12" x2="18" y2="12"></line></svg>
                                    Enregistrer le Décaissement
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- 3. BLOC IMPUTATION / RESTITUTION (Quand statut = decaissee) -->
                <?php if ($demande->statut === 'decaissee' && $canImputer): ?>
                    <div style="background: #ffffff; border-radius: 12px; border: 2px solid #818cf8; box-shadow: 0 4px 12px rgba(129, 140, 248, 0.15); padding: 1.5rem;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.75rem;">
                            <span style="font-size: 1.2rem;">📑</span>
                            <h3 style="margin: 0; font-size: 1.1rem; font-weight: 800; color: #4338ca;">
                                Imputation Comptable & Décharge
                            </h3>
                        </div>
                        <p style="color: #64748b; font-size: 0.85rem; margin: 0 0 1.25rem;">
                            Renseignez le montant réel dépensé avec justificatifs. Le reliquat éventuel sera automatiquement calculé et réintégré en caisse.
                        </p>

                        <form method="post" action="<?= View::url('finance/fonds/' . $demande->id . '/imputer') ?>" id="formImputation">
                            <?= Csrf::field() ?>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                                <div>
                                    <label style="display: block; font-size: 0.8rem; font-weight: 700; color: #1e293b; margin-bottom: 0.35rem;">
                                        Montant réel dépensé (FCFA) <span style="color: #dc2626;">*</span>
                                    </label>
                                    <input type="number" step="100" name="montant_reel_depense" id="montantReel" value="<?= $demande->montant ?>" required class="finea-input" style="width: 100%; padding: 0.65rem; border: 1px solid #cbd5e1; border-radius: 6px; font-weight: 800; font-size: 1.1rem; color: #0f172a;" oninput="calculerReliquat(<?= $demande->montant ?>)">
                                </div>
                                <div>
                                    <label style="display: block; font-size: 0.8rem; font-weight: 700; color: #1e293b; margin-bottom: 0.35rem;">
                                        Reliquat restitué en caisse (FCFA)
                                    </label>
                                    <input type="text" id="reliquatAffichage" value="0 FCFA" readonly class="finea-input" style="width: 100%; padding: 0.65rem; border: 1px solid #e2e8f0; border-radius: 6px; background: #f8fafc; font-weight: 800; font-size: 1.1rem; color: #16a34a;">
                                </div>
                            </div>

                            <div style="margin-bottom: 1rem;">
                                <label style="display: block; font-size: 0.8rem; font-weight: 700; color: #1e293b; margin-bottom: 0.35rem;">
                                    Références des pièces justificatives (Quittances douane, Reçus, Factures)
                                </label>
                                <input type="text" name="pieces_justificatives" placeholder="Ex: Quittance Douane N° 849204, Reçu transport 12/09" class="finea-input" style="width: 100%; padding: 0.65rem; border: 1px solid #cbd5e1; border-radius: 6px;">
                            </div>

                            <div style="margin-bottom: 1.25rem;">
                                <label style="display: block; font-size: 0.8rem; font-weight: 700; color: #1e293b; margin-bottom: 0.35rem;">
                                    Commentaires / Observations de clôture
                                </label>
                                <textarea name="commentaires" rows="2" placeholder="Observations comptables sur la dépense..." class="finea-textarea" style="width: 100%; padding: 0.65rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem;"></textarea>
                            </div>

                            <div style="text-align: right;">
                                <button type="submit" class="finea-button finea-button--primary" style="padding: 0.75rem 1.5rem; font-weight: 700; background: #4f46e5; border: none; border-radius: 8px; color: #fff; cursor: pointer;" onclick="return confirm('Valider définitivement cette imputation ?');">
                                    Valider l'Imputation & Clôturer
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- 4. BLOC IMPUTATION EFFECTUEE (Détails clôture) -->
                <?php if ($demande->statut === 'imputee' && $demande->imputation): ?>
                    <div style="background: #f0fdf4; border: 2px solid #86efac; border-radius: 12px; padding: 1.5rem;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.75rem;">
                            <span style="font-size: 1.2rem;">✅</span>
                            <h3 style="margin: 0; font-size: 1.1rem; font-weight: 800; color: #15803d;">
                                Dossier de Fonds Imputé & Clôturé
                            </h3>
                        </div>
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-top: 1rem; font-size: 0.9rem;">
                            <div>
                                <span style="color: #64748b; display: block; font-size: 0.75rem; text-transform: uppercase;">Montant Initial</span>
                                <strong><?= number_format($demande->imputation->montantEngage, 0, ',', ' ') ?> FCFA</strong>
                            </div>
                            <div>
                                <span style="color: #64748b; display: block; font-size: 0.75rem; text-transform: uppercase;">Montant Réel Dépensé</span>
                                <strong style="color: #15803d;"><?= number_format($demande->imputation->montantReelDepense, 0, ',', ' ') ?> FCFA</strong>
                            </div>
                            <div>
                                <span style="color: #64748b; display: block; font-size: 0.75rem; text-transform: uppercase;">Reliquat Reversé</span>
                                <strong style="color: #0284c7;"><?= number_format($demande->imputation->montantReliquatRestitue, 0, ',', ' ') ?> FCFA</strong>
                            </div>
                        </div>
                        <?php if (!empty($demande->imputation->piecesJustificatives)): ?>
                            <div style="margin-top: 0.75rem; font-size: 0.85rem; color: #166534;">
                                <strong>Pièces :</strong> <?= View::e($demande->imputation->piecesJustificatives) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            </div>

            <!-- Right Column : Timeline & Journal d'Audit de Traçabilité -->
            <div style="background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); padding: 1.5rem;">
                <h3 style="margin: 0 0 1.25rem; font-size: 1.1rem; font-weight: 800; color: #0f172a; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.75rem; display: flex; align-items: center; gap: 8px;">
                    <span>📜</span> Journal de Traçabilité
                </h3>

                <div style="position: relative; padding-left: 20px; border-left: 2px solid #e2e8f0; display: flex; flex-direction: column; gap: 1.5rem;">
                    <?php if (empty($historique)): ?>
                        <p style="color: #94a3b8; font-size: 0.85rem; margin: 0;">Aucun historique consigné.</p>
                    <?php else: ?>
                        <?php foreach ($historique as $hist): ?>
                            <?php
                            $actionIcons = [
                                'CREATION'     => '📝',
                                'VALIDATION'   => '🛡️',
                                'REJET'        => '❌',
                                'DECAISSEMENT' => '💵',
                                'IMPUTATION'   => '✅',
                                'MODIFICATION' => '✏️',
                            ];
                            $icon = $actionIcons[$hist['action']] ?? '•';
                            ?>
                            <div style="position: relative;">
                                <span style="position: absolute; left: -29px; top: 0; background: #fff; border: 2px solid #cbd5e1; border-radius: 50%; width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; font-size: 10px;">
                                    <?= $icon ?>
                                </span>
                                <div style="font-size: 0.75rem; font-weight: 700; color: #0284c7; text-transform: uppercase;">
                                    <?= View::e($hist['action']) ?>
                                </div>
                                <div style="font-size: 0.85rem; font-weight: 700; color: #1e293b; margin: 2px 0;">
                                    <?= View::e($hist['user_nom'] ?? 'Utilisateur Système') ?>
                                </div>
                                <div style="font-size: 0.8rem; color: #64748b; line-height: 1.4;">
                                    <?= View::e($hist['commentaire'] ?? '') ?>
                                </div>
                                <div style="font-size: 0.72rem; color: #94a3b8; margin-top: 4px;">
                                    <?= date('d/m/Y à H:i:s', strtotime($hist['created_at'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>

    </div>
</div>

<script>
function calculerReliquat(montantInitial) {
    const montantReel = parseFloat(document.getElementById('montantReel').value) || 0;
    const reliquat = Math.max(0, montantInitial - montantReel);
    document.getElementById('reliquatAffichage').value = new Intl.NumberFormat('fr-FR').format(reliquat) + ' FCFA';
}
</script>
