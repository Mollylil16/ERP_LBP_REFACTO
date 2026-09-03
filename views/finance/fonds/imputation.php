<?php

declare(strict_types=1);

use App\Helpers\View;

/**
 * @var array<int, \App\Models\Finance\DemandeFonds> $items
 * @var int $total
 * @var int $page
 * @var int $totalPages
 * @var array<string, mixed> $filters
 * @var array<int, array{id: int, name: string, code: string}> $agences
 * @var bool $isSuperUser
 * @var string $currentStatut
 */
?>

<div class="finea-shell">
    <div class="finea-container" style="max-width: 1400px; margin: 0 auto; padding: 1.5rem 1rem;">

        <!-- Header -->
        <div style="margin-bottom: 1.5rem;">
            <div style="display: inline-flex; align-items: center; gap: 8px; font-size: 0.8rem; font-weight: 700; color: #4338ca; text-transform: uppercase; letter-spacing: 0.05em; background: #e0e7ff; padding: 4px 10px; border-radius: 9999px; margin-bottom: 0.4rem;">
                <span>📑 CONTRÔLE DE GESTION & JUSTIFICATIFS</span>
            </div>
            <h1 style="font-size: 1.75rem; font-weight: 800; color: #0f172a; margin: 0;">IMPUTATION DES FONDS</h1>
            <p style="color: #64748b; margin: 0.25rem 0 0; font-size: 0.9rem;">Justification des dépenses engagées, enregistrement des pièces comptables et régularisation des reliquats de caisse.</p>
        </div>

        <!-- Filter Bar & Statut Tabs -->
        <div style="display: flex; gap: 10px; margin-bottom: 1.25rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.75rem;">
            <a href="<?= View::url('finance/fonds/imputation') ?>?statut=decaissee" style="padding: 8px 16px; border-radius: 6px; font-weight: 700; font-size: 0.875rem; text-decoration: none; <?= $currentStatut === 'decaissee' ? 'background:#4338ca; color:#fff;' : 'background:#f1f5f9; color:#475569;' ?>">
                ⏳ À Imputer / En attente de justificatifs
            </a>
            <a href="<?= View::url('finance/fonds/imputation') ?>?statut=imputee" style="padding: 8px 16px; border-radius: 6px; font-weight: 700; font-size: 0.875rem; text-decoration: none; <?= $currentStatut === 'imputee' ? 'background:#15803d; color:#fff;' : 'background:#f1f5f9; color:#475569;' ?>">
                ✅ Déjà Imputées & Clôturées
            </a>
        </div>

        <!-- Filter Card -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1rem 1.25rem; margin-bottom: 1.5rem;">
            <form method="get" action="<?= View::url('finance/fonds/imputation') ?>" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
                <input type="hidden" name="statut" value="<?= View::e($currentStatut) ?>">

                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #475569; margin-bottom: 0.35rem;">
                        🏢 Agence
                    </label>
                    <select name="agence_id" class="finea-select" style="width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem;">
                        <option value="">Toutes les agences</option>
                        <?php foreach ($agences as $ag): ?>
                            <option value="<?= $ag['id'] ?>" <?= (string)($filters['agence_id'] ?? '') === (string)$ag['id'] ? 'selected' : '' ?>>
                                <?= View::e($ag['name']) ?> (<?= View::e($ag['code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #475569; margin-bottom: 0.35rem;">
                        📂 Cadre
                    </label>
                    <select name="cadre" class="finea-select" style="width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem;">
                        <option value="">Tous les cadres</option>
                        <option value="traitement_dossier" <?= ($filters['cadre'] ?? '') === 'traitement_dossier' ? 'selected' : '' ?>>Traitement de Dossier</option>
                        <option value="fonctionnement" <?= ($filters['cadre'] ?? '') === 'fonctionnement' ? 'selected' : '' ?>>Fonctionnement</option>
                    </select>
                </div>

                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #475569; margin-bottom: 0.35rem;">
                        🔎 Recherche
                    </label>
                    <input type="text" name="q" placeholder="Numéro, motif, dossier..." value="<?= View::e($filters['q'] ?? '') ?>" class="finea-input" style="width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem;">
                </div>

                <div>
                    <button type="submit" class="finea-button finea-button--primary" style="padding: 0.55rem 1.2rem; border-radius: 6px; font-weight: 700; font-size: 0.85rem; background: #0f172a; width: 100%;">
                        Filtrer
                    </button>
                </div>
            </form>
        </div>

        <!-- Banner Header Matching Screenshot 4 -->
        <div style="background: #fee2e2; border: 1px solid #fca5a5; border-radius: 8px; padding: 0.85rem; text-align: center; margin-bottom: 1.5rem;">
            <h3 style="margin: 0; color: #991b1b; font-size: 1rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;">
                LISTE DES DEMANDES D'IMPUTATIONS DE FONDS
            </h3>
        </div>

        <!-- Table Card -->
        <div style="background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); overflow: hidden;">
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.875rem;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.03em;">
                            <th style="padding: 12px 14px;">N°</th>
                            <th style="padding: 12px 14px;">Numéro</th>
                            <th style="padding: 12px 14px;">Date Décaissement</th>
                            <th style="padding: 12px 14px;">Cadre</th>
                            <th style="padding: 12px 14px;">N° Dossier</th>
                            <th style="padding: 12px 14px;">Motif de la demande</th>
                            <th style="padding: 12px 14px; text-align: right;">Montant Engagé</th>
                            <th style="padding: 12px 14px;">Demandeur</th>
                            <th style="padding: 12px 14px;">Validateur</th>
                            <th style="padding: 12px 14px; text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr>
                                <td colspan="10" style="text-align: center; padding: 3rem 1rem; color: #94a3b8;">
                                    <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">📋</div>
                                    <div style="font-weight: 700; color: #475569; font-size: 1rem;">Aucune demande d'imputation trouvée</div>
                                    <div style="font-size: 0.85rem; margin-top: 0.25rem;">Toutes les dépenses ont été régularisées ou aucune demande ne correspond à vos filtres.</div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($items as $idx => $item): ?>
                                <?php
                                $cadreLabel = $item->cadre === 'traitement_dossier' ? 'Traitement de Dossier' : 'Fonctionnement';
                                $cadreColor = $item->cadre === 'traitement_dossier' ? '#2563eb' : '#7c3aed';
                                $dateStr = $item->dateDecaissement ? date('d/m/Y à H:i', strtotime($item->dateDecaissement)) : ($item->createdAt ? date('d/m/Y', strtotime($item->createdAt)) : '—');
                                ?>
                                <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#fff'">
                                    <td style="padding: 12px 14px; font-weight: 600; color: #94a3b8;"><?= ($page - 1) * 25 + $idx + 1 ?></td>
                                    <td style="padding: 12px 14px;">
                                        <a href="<?= View::url('finance/fonds/' . $item->id) ?>" style="font-weight: 700; color: #0284c7; text-decoration: none;">
                                            <?= View::e($item->numeroDemande) ?>
                                        </a>
                                    </td>
                                    <td style="padding: 12px 14px; color: #475569; white-space: nowrap; font-size: 0.82rem;"><?= View::e($dateStr) ?></td>
                                    <td style="padding: 12px 14px;">
                                        <span style="font-size: 0.75rem; font-weight: 700; padding: 2px 8px; border-radius: 4px; background: <?= $cadreColor ?>15; color: <?= $cadreColor ?>;">
                                            <?= View::e($cadreLabel) ?>
                                        </span>
                                    </td>
                                    <td style="padding: 12px 14px;">
                                        <?php if (!empty($item->dossierNum)): ?>
                                            <span style="font-weight: 700; color: #0f172a; font-family: monospace; font-size: 0.82rem; background: #f1f5f9; padding: 2px 6px; border-radius: 4px;">
                                                <?= View::e($item->dossierNum) ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #cbd5e1;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 12px 14px; max-width: 320px; font-weight: 500; color: #1e293b;">
                                        <div style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= View::e($item->motif) ?>">
                                            <?= View::e($item->motif) ?>
                                        </div>
                                    </td>
                                    <td style="padding: 12px 14px; text-align: right; font-weight: 800; color: #0f172a; white-space: nowrap;">
                                        <?= number_format($item->montant, 0, ',', ' ') ?> <small style="color: #64748b; font-weight: 600;"><?= View::e($item->devise) ?></small>
                                    </td>
                                    <td style="padding: 12px 14px; color: #334155; font-weight: 600;">
                                        <?= View::e($item->demandeurNom ?? '—') ?>
                                    </td>
                                    <td style="padding: 12px 14px; color: #166534; font-weight: 600; font-size: 0.85rem;">
                                        <?= View::e($item->validateurNom ?? '—') ?>
                                    </td>
                                    <td style="padding: 12px 14px; text-align: center;">
                                        <a href="<?= View::url('finance/fonds/' . $item->id) ?>" class="finea-button finea-button--primary finea-button-sm" style="display: inline-flex; align-items: center; gap: 4px; padding: 6px 14px; background: #4f46e5; color: #fff; font-weight: 700; font-size: 0.8rem; border-radius: 6px; text-decoration: none; border: none;" title="Imputer et justifier">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                            <?= $item->statut === 'imputee' ? 'Voir Détail' : 'Imputer' ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>
