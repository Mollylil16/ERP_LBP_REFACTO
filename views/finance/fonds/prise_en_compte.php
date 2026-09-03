<?php

declare(strict_types=1);

use App\Helpers\Csrf;
use App\Helpers\View;

/**
 * @var array<int, \App\Models\Finance\DemandeFonds> $items
 * @var int $total
 * @var int $page
 * @var int $totalPages
 * @var array<string, mixed> $filters
 * @var array<int, array{id: int, name: string, code: string}> $agences
 * @var bool $isSuperUser
 */
?>

<div class="finea-shell">
    <div class="finea-container" style="max-width: 1400px; margin: 0 auto; padding: 1.5rem 1rem;">

        <!-- Header -->
        <div style="margin-bottom: 1.5rem;">
            <div style="display: inline-flex; align-items: center; gap: 8px; font-size: 0.8rem; font-weight: 700; color: #d97706; text-transform: uppercase; letter-spacing: 0.05em; background: #fef3c7; padding: 4px 10px; border-radius: 9999px; margin-bottom: 0.4rem;">
                <span>💵 GUICHET DE CAISSE & DÉCAISSEMENTS</span>
            </div>
            <h1 style="font-size: 1.75rem; font-weight: 800; color: #0f172a; margin: 0;">PRISE EN COMPTE</h1>
            <p style="color: #64748b; margin: 0.25rem 0 0; font-size: 0.9rem;">File d'attente des demandes de fonds validées par la Direction, en attente de paiement physique par la Caisse.</p>
        </div>

        <!-- Filter Bar -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1rem 1.25rem; margin-bottom: 1.5rem;">
            <form method="get" action="<?= View::url('finance/fonds/prise-en-compte') ?>" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
                
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
                    <input type="text" name="q" placeholder="Numéro, dossier, motif..." value="<?= View::e($filters['q'] ?? '') ?>" class="finea-input" style="width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem;">
                </div>

                <div>
                    <button type="submit" class="finea-button finea-button--primary" style="padding: 0.55rem 1.2rem; border-radius: 6px; font-weight: 700; font-size: 0.85rem; background: #0f172a; width: 100%;">
                        Filtrer
                    </button>
                </div>
            </form>
        </div>

        <!-- Banner Header Matching Screenshot 3 -->
        <div style="background: #fee2e2; border: 1px solid #fca5a5; border-radius: 8px; padding: 0.85rem; text-align: center; margin-bottom: 1.5rem;">
            <h3 style="margin: 0; color: #991b1b; font-size: 1rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;">
                LISTE DES DEMANDES DE FONDS EN COURS
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
                            <th style="padding: 12px 14px;">Date</th>
                            <th style="padding: 12px 14px;">Cadre</th>
                            <th style="padding: 12px 14px;">N° Dossier</th>
                            <th style="padding: 12px 14px;">Motif de la demande</th>
                            <th style="padding: 12px 14px; text-align: right;">Montant</th>
                            <th style="padding: 12px 14px;">Demandeur</th>
                            <th style="padding: 12px 14px; text-align: center;">Action / Décaissement</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 3rem 1rem; color: #94a3b8;">
                                    <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">🎉</div>
                                    <div style="font-weight: 700; color: #475569; font-size: 1rem;">Aucune demande en attente de décaissement</div>
                                    <div style="font-size: 0.85rem; margin-top: 0.25rem;">Toutes les demandes validées ont déjà été prises en compte et décaissées.</div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($items as $idx => $item): ?>
                                <?php
                                $cadreLabel = $item->cadre === 'traitement_dossier' ? 'Traitement de Dossier' : 'Fonctionnement';
                                $cadreColor = $item->cadre === 'traitement_dossier' ? '#2563eb' : '#7c3aed';
                                $dateStr = $item->createdAt ? date('d/m/Y', strtotime($item->createdAt)) : '—';
                                ?>
                                <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#fff'">
                                    <td style="padding: 12px 14px; font-weight: 600; color: #94a3b8;"><?= ($page - 1) * 25 + $idx + 1 ?></td>
                                    <td style="padding: 12px 14px;">
                                        <a href="<?= View::url('finance/fonds/' . $item->id) ?>" style="font-weight: 700; color: #0284c7; text-decoration: none;">
                                            <?= View::e($item->numeroDemande) ?>
                                        </a>
                                    </td>
                                    <td style="padding: 12px 14px; color: #475569; white-space: nowrap;"><?= View::e($dateStr) ?></td>
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
                                    <td style="padding: 12px 14px; text-align: center;">
                                        <a href="<?= View::url('finance/fonds/' . $item->id) ?>" class="finea-button finea-button--primary finea-button-sm" style="display: inline-flex; align-items: center; gap: 4px; padding: 6px 14px; background: #d97706; color: #fff; font-weight: 700; font-size: 0.8rem; border-radius: 6px; text-decoration: none; border: none;" title="Procéder au décaissement">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="4" width="20" height="16" rx="2"></rect><line x1="6" y1="12" x2="18" y2="12"></line></svg>
                                            Décaisser
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
