<?php

declare(strict_types=1);

use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\View;

/**
 * @var array<int, \App\Models\Finance\DemandeFonds> $items
 * @var int $total
 * @var int $page
 * @var int $totalPages
 * @var array<string, mixed> $filters
 * @var array<string, mixed> $stats
 * @var array<int, array{id: int, name: string, code: string}> $agences
 * @var bool $isSuperUser
 * @var bool $canValidate
 */

$statusBadges = [
    'en_attente' => ['label' => 'En attente de validation', 'bg' => '#fee2e2', 'color' => '#dc2626', 'icon' => '⏳'],
    'validee'    => ['label' => 'Validée (À décaisser)',    'bg' => '#fef3c7', 'color' => '#d97706', 'icon' => '✓'],
    'decaissee'  => ['label' => 'Décaissée (En cours)',     'bg' => '#e0e7ff', 'color' => '#4338ca', 'icon' => '💵'],
    'imputee'    => ['label' => 'Imputée & Clôturée',       'bg' => '#dcfce7', 'color' => '#15803d', 'icon' => '✅'],
    'rejetee'    => ['label' => 'Rejetée',                  'bg' => '#f1f5f9', 'color' => '#64748b', 'icon' => '✕'],
];
?>

<div class="finea-shell">
    <div class="finea-container" style="max-width: 1400px; margin: 0 auto; padding: 1.5rem 1rem;">

        <!-- Header Title & Quick Action -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <div style="display: inline-flex; align-items: center; gap: 8px; font-size: 0.8rem; font-weight: 700; color: #2563eb; text-transform: uppercase; letter-spacing: 0.05em; background: #eff6ff; padding: 4px 10px; border-radius: 9999px; margin-bottom: 0.4rem;">
                    <span>💳 TRÉSORERIE & DÉCAISSEMENTS</span>
                </div>
                <h1 style="font-size: 1.75rem; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.02em;">DEMANDES DE FONDS</h1>
                <p style="color: #64748b; margin: 0.25rem 0 0; font-size: 0.9rem;">Gestion, traçabilité et validation des décaissements sur dossiers de transit et fonctionnement.</p>
            </div>
            <div>
                <a href="<?= View::url('finance/fonds/nouveau') ?>" class="finea-button finea-button--primary" style="display: inline-flex; align-items: center; gap: 8px; padding: 0.75rem 1.4rem; font-weight: 700; font-size: 0.95rem; border-radius: 8px; background: #0284c7; box-shadow: 0 4px 12px rgba(2, 132, 199, 0.25); text-decoration: none; color: #fff;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    Ajouter une demande de décaissement
                </a>
            </div>
        </div>

        <!-- KPIs Strip -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
            <div style="background: #ffffff; border-radius: 10px; padding: 1.2rem; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                <div style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Total Demandes</div>
                <div style="font-size: 1.6rem; font-weight: 800; color: #0f172a; margin-top: 0.25rem;"><?= number_format((float) ($stats['total_demandes'] ?? 0), 0, ',', ' ') ?></div>
                <div style="font-size: 0.8rem; color: #0284c7; margin-top: 0.2rem; font-weight: 600;"><?= number_format((float) ($stats['montant_total_demande'] ?? 0), 0, ',', ' ') ?> FCFA engagés</div>
            </div>
            <div style="background: #ffffff; border-radius: 10px; padding: 1.2rem; border: 1px solid #fee2e2; border-left: 4px solid #dc2626; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                <div style="font-size: 0.75rem; font-weight: 700; color: #dc2626; text-transform: uppercase;">En Attente Validation</div>
                <div style="font-size: 1.6rem; font-weight: 800; color: #dc2626; margin-top: 0.25rem;"><?= number_format((float) ($stats['total_en_attente'] ?? 0), 0, ',', ' ') ?></div>
                <div style="font-size: 0.8rem; color: #64748b; margin-top: 0.2rem;">À valider par la Direction</div>
            </div>
            <div style="background: #ffffff; border-radius: 10px; padding: 1.2rem; border: 1px solid #fef3c7; border-left: 4px solid #d97706; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                <div style="font-size: 0.75rem; font-weight: 700; color: #d97706; text-transform: uppercase;">Validées (À décaisser)</div>
                <div style="font-size: 1.6rem; font-weight: 800; color: #d97706; margin-top: 0.25rem;"><?= number_format((float) ($stats['total_validees'] ?? 0), 0, ',', ' ') ?></div>
                <div style="font-size: 0.8rem; color: #64748b; margin-top: 0.2rem;">En attente de caisse</div>
            </div>
            <div style="background: #ffffff; border-radius: 10px; padding: 1.2rem; border: 1px solid #dcfce7; border-left: 4px solid #16a34a; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                <div style="font-size: 0.75rem; font-weight: 700; color: #15803d; text-transform: uppercase;">Décaissées / Imputées</div>
                <div style="font-size: 1.6rem; font-weight: 800; color: #15803d; margin-top: 0.25rem;"><?= number_format((float) (($stats['total_decaissees'] ?? 0) + ($stats['total_imputees'] ?? 0)), 0, ',', ' ') ?></div>
                <div style="font-size: 0.8rem; color: #15803d; margin-top: 0.2rem; font-weight: 600;"><?= number_format((float) ($stats['montant_total_decaisse'] ?? 0), 0, ',', ' ') ?> FCFA décaissés</div>
            </div>
        </div>

        <!-- Filter Card -->
        <div style="background: #fdfbf7; border: 1px solid #fed7aa; border-radius: 10px; padding: 1.25rem; margin-bottom: 1.5rem;">
            <form method="get" action="<?= View::url('finance/fonds') ?>" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; align-items: end;">
                
                <!-- Période Date début -->
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #7c2d12; margin-bottom: 0.35rem;">
                        🔍 Période du
                    </label>
                    <input type="date" name="date_from" value="<?= View::e($filters['date_from'] ?? '') ?>" class="finea-input" style="width: 100%; background: #fff; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem;">
                </div>

                <!-- Période Date fin -->
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #7c2d12; margin-bottom: 0.35rem;">
                        Au
                    </label>
                    <input type="date" name="date_to" value="<?= View::e($filters['date_to'] ?? '') ?>" class="finea-input" style="width: 100%; background: #fff; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem;">
                </div>

                <!-- Filtre État -->
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #7c2d12; margin-bottom: 0.35rem;">
                        🔍 Historique par État
                    </label>
                    <select name="statut" class="finea-select" style="width: 100%; background: #fff; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem;">
                        <option value="">Tous les états</option>
                        <option value="en_attente" <?= ($filters['statut'] ?? '') === 'en_attente' ? 'selected' : '' ?>>En attente de validation</option>
                        <option value="validee" <?= ($filters['statut'] ?? '') === 'validee' ? 'selected' : '' ?>>Validée (À décaisser)</option>
                        <option value="decaissee" <?= ($filters['statut'] ?? '') === 'decaissee' ? 'selected' : '' ?>>Décaissée (En cours)</option>
                        <option value="imputee" <?= ($filters['statut'] ?? '') === 'imputee' ? 'selected' : '' ?>>Imputée & Clôturée</option>
                        <option value="rejetee" <?= ($filters['statut'] ?? '') === 'rejetee' ? 'selected' : '' ?>>Rejetée</option>
                    </select>
                </div>

                <!-- Filtre Agence -->
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #7c2d12; margin-bottom: 0.35rem;">
                        🏢 Agence
                    </label>
                    <select name="agence_id" class="finea-select" style="width: 100%; background: #fff; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem;">
                        <option value="">Toutes les agences</option>
                        <?php foreach ($agences as $ag): ?>
                            <option value="<?= $ag['id'] ?>" <?= (string)($filters['agence_id'] ?? '') === (string)$ag['id'] ? 'selected' : '' ?>>
                                <?= View::e($ag['name']) ?> (<?= View::e($ag['code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Filtre Cadre -->
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #7c2d12; margin-bottom: 0.35rem;">
                        📂 Cadre
                    </label>
                    <select name="cadre" class="finea-select" style="width: 100%; background: #fff; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem;">
                        <option value="">Tous les cadres</option>
                        <option value="traitement_dossier" <?= ($filters['cadre'] ?? '') === 'traitement_dossier' ? 'selected' : '' ?>>Traitement de Dossier</option>
                        <option value="fonctionnement" <?= ($filters['cadre'] ?? '') === 'fonctionnement' ? 'selected' : '' ?>>Fonctionnement</option>
                    </select>
                </div>

                <!-- Recherche libre -->
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #7c2d12; margin-bottom: 0.35rem;">
                        🔎 Recherche
                    </label>
                    <input type="text" name="q" placeholder="N° demande, motif, dossier..." value="<?= View::e($filters['q'] ?? '') ?>" class="finea-input" style="width: 100%; background: #fff; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem;">
                </div>

                <!-- Boutons Filtrer / Réinitialiser -->
                <div style="display: flex; gap: 6px;">
                    <button type="submit" class="finea-button finea-button--primary" style="padding: 0.55rem 1rem; border-radius: 6px; font-weight: 700; font-size: 0.85rem; flex: 1; background: #0f172a;">Filtrer</button>
                    <a href="<?= View::url('finance/fonds') ?>" class="finea-button finea-button--secondary" style="padding: 0.55rem 0.8rem; border-radius: 6px; font-size: 0.85rem; text-decoration: none; color: #475569; background: #fff; border: 1px solid #cbd5e1;">✕</a>
                </div>
            </form>
        </div>

        <!-- Table Card -->
        <div style="background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); overflow: hidden;">
            <div style="padding: 1rem 1.25rem; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-size: 1.1rem; font-weight: 800; color: #0f172a;">Liste des Demandes enregistrées</h3>
                <span style="font-size: 0.85rem; color: #64748b; font-weight: 600;"><?= $total ?> demande(s) au total</span>
            </div>

            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.875rem;">
                    <thead>
                        <tr style="background: #f0fdf4; border-bottom: 2px solid #bbf7d0; color: #166534; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.03em;">
                            <th style="padding: 10px 14px;">N°</th>
                            <th style="padding: 10px 14px;">Numéro</th>
                            <th style="padding: 10px 14px;">Date</th>
                            <th style="padding: 10px 14px;">Cadre</th>
                            <th style="padding: 10px 14px;">Dossier</th>
                            <th style="padding: 10px 14px;">Libellé / Motif</th>
                            <th style="padding: 10px 14px; text-align: right;">Montant</th>
                            <th style="padding: 10px 14px;">Demandeur</th>
                            <th style="padding: 10px 14px;">Agence</th>
                            <th style="padding: 10px 14px; text-align: center;">Statut</th>
                            <th style="padding: 10px 14px; text-align: center;">Bon Caisse</th>
                            <th style="padding: 10px 14px; text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody style="divide-y: divide-slate-100;">
                        <?php if (empty($items)): ?>
                            <tr>
                                <td colspan="12" style="text-align: center; padding: 3rem 1rem; color: #94a3b8;">
                                    <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">📂</div>
                                    <div style="font-weight: 700; color: #475569; font-size: 1rem;">Aucune demande de fonds trouvée</div>
                                    <div style="font-size: 0.85rem; margin-top: 0.25rem;">Modifiez vos critères de recherche ou cliquez sur "Ajouter une demande de décaissement".</div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($items as $idx => $item): ?>
                                <?php
                                $badgeInfo = $statusBadges[$item->statut] ?? ['label' => ucfirst($item->statut), 'bg' => '#f1f5f9', 'color' => '#475569', 'icon' => '•'];
                                $cadreLabel = $item->cadre === 'traitement_dossier' ? 'Traitement de Dossier' : 'Fonctionnement';
                                $cadreColor = $item->cadre === 'traitement_dossier' ? '#2563eb' : '#7c3aed';
                                $dateStr = $item->createdAt ? date('d/m/Y', strtotime($item->createdAt)) : '—';
                                ?>
                                <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#fff'">
                                    <td style="padding: 12px 14px; font-weight: 600; color: #94a3b8;"><?= ($page - 1) * 15 + $idx + 1 ?></td>
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
                                    <td style="padding: 12px 14px; max-width: 280px; font-weight: 500; color: #1e293b;">
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
                                    <td style="padding: 12px 14px; color: #64748b; font-size: 0.8rem;">
                                        <?= View::e($item->agenceNom ?? '—') ?>
                                    </td>
                                    <td style="padding: 12px 14px; text-align: center;">
                                        <span style="display: inline-block; padding: 4px 10px; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; background: <?= $badgeInfo['bg'] ?>; color: <?= $badgeInfo['color'] ?>;">
                                            <?= $badgeInfo['icon'] ?> <?= View::e($badgeInfo['label']) ?>
                                        </span>
                                    </td>
                                    <td style="padding: 12px 14px; text-align: center;">
                                        <?php if (in_array($item->statut, ['validee', 'decaissee', 'imputee'])): ?>
                                            <a href="<?= View::url('finance/fonds/' . $item->id . '/bon-caisse-pdf') ?>" target="_blank" title="Imprimer le Bon de Sortie de Caisse" style="display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 6px; background: #f8fafc; border: 1px solid #cbd5e1; color: #0f172a; text-decoration: none; transition: 0.2s;" onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f8fafc'">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                                            </a>
                                        <?php else: ?>
                                            <span style="color: #cbd5e1;" title="Disponible après validation">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 12px 14px; text-align: right; white-space: nowrap;">
                                        <div style="display: inline-flex; gap: 6px; align-items: center;">
                                            <a href="<?= View::url('finance/fonds/' . $item->id) ?>" class="finea-button finea-button--secondary finea-button-sm" style="padding: 5px 10px; font-size: 0.78rem; text-decoration: none; border-radius: 5px; color: #0284c7; font-weight: 700; background: #e0f2fe; border: 1px solid #bae6fd;">
                                                Détail
                                            </a>

                                            <?php if ($item->statut === 'en_attente' && (Auth::isAdmin() || Auth::hasAnyRole(['dg', 'assistant_dg']) || (int)Auth::id() === $item->demandeurId)): ?>
                                                <form method="post" action="<?= View::url('finance/fonds/' . $item->id . '/supprimer') ?>" style="display: inline;" onsubmit="return confirm('Confirmer la suppression de cette demande de fonds ?');">
                                                    <?= Csrf::field() ?>
                                                    <button type="submit" style="background: #fee2e2; border: 1px solid #fca5a5; color: #dc2626; border-radius: 5px; padding: 5px 8px; cursor: pointer; font-size: 0.78rem; font-weight: 700;" title="Supprimer">
                                                        ✕
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; background: #f8fafc; border-top: 1px solid #e2e8f0;">
                    <div style="font-size: 0.85rem; color: #64748b;">
                        Page <strong><?= $page ?></strong> sur <strong><?= $totalPages ?></strong> (Total: <?= $total ?> demandes)
                    </div>
                    <div style="display: flex; gap: 4px;">
                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <?php
                            $queryParams = array_merge($filters, ['page' => $p]);
                            $pageUrl = View::url('finance/fonds') . '?' . http_build_query(array_filter($queryParams));
                            ?>
                            <a href="<?= $pageUrl ?>" style="padding: 6px 12px; border-radius: 6px; font-size: 0.85rem; font-weight: 700; text-decoration: none; <?= $p === $page ? 'background:#0f172a; color:#fff;' : 'background:#fff; color:#475569; border:1px solid #cbd5e1;' ?>">
                                <?= $p ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>
