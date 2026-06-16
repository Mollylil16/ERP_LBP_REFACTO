<?php
/** @var array $inventaire */
/** @var array $lignes */
use App\Helpers\Csrf;
use App\Helpers\View;

ob_start();
require __DIR__ . '/../_navigation.php';

$statusLignes = [
    'PRESENT'   => ['label' => 'Présent',   'class' => 'badge-success'],
    'MANQUANT'  => ['label' => 'Manquant',  'class' => 'badge-danger'],
    'ENDOMMAGE' => ['label' => 'Endommagé', 'class' => 'badge-warning'],
];

$nbPresent  = count(array_filter($lignes, fn($l) => $l['status'] === 'PRESENT'));
$nbManquant = count(array_filter($lignes, fn($l) => $l['status'] === 'MANQUANT'));
$nbEndommage= count(array_filter($lignes, fn($l) => $l['status'] === 'ENDOMMAGE'));
?>

<div class="page-header">
    <div>
        <p class="eyebrow">Colisage — Inventaire #<?= $inventaire['id'] ?></p>
        <h1><?= View::e($inventaire['agency_name'] ?? 'Agence') ?></h1>
    </div>
    <div class="header-actions">
        <?php if ($inventaire['status'] === 'EN_COURS'): ?>
        <form method="POST" action="<?= View::url('colisage/inventaire/' . $inventaire['id'] . '/cloturer') ?>" style="margin:0;">
            <?= Csrf::input() ?>
            <button type="submit" class="btn btn-danger" onclick="return confirm('Clôturer l\'inventaire ?')">
                <span class="material-icons">lock</span> Clôturer
            </button>
        </form>
        <?php endif; ?>
        <a href="<?= View::url('colisage/inventaire') ?>" class="btn btn-ghost">
            <span class="material-icons">arrow_back</span> Liste
        </a>
    </div>
</div>

<!-- KPIs inventaire -->
<div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:1rem; margin-bottom:1.5rem;">
    <div class="kpi-card" style="border-left:4px solid <?= $inventaire['status'] === 'EN_COURS' ? '#f59e0b' : '#10b981' ?>;">
        <span style="font-size:.78rem; font-weight:600; text-transform:uppercase;">Statut</span>
        <strong style="font-size:1.2rem;"><?= $inventaire['status'] === 'EN_COURS' ? '🟡 En cours' : '✅ Clôturé' ?></strong>
    </div>
    <div class="kpi-card" style="border-left:4px solid #10b981;">
        <span style="font-size:.78rem; font-weight:600; text-transform:uppercase; color:#10b981;">Présents</span>
        <strong style="font-size:2rem; color:#10b981;"><?= $nbPresent ?></strong>
    </div>
    <div class="kpi-card" style="border-left:4px solid #ef4444;">
        <span style="font-size:.78rem; font-weight:600; text-transform:uppercase; color:#ef4444;">Manquants</span>
        <strong style="font-size:2rem; color:#ef4444;"><?= $nbManquant ?></strong>
    </div>
    <div class="kpi-card" style="border-left:4px solid #f59e0b;">
        <span style="font-size:.78rem; font-weight:600; text-transform:uppercase; color:#f59e0b;">Endommagés</span>
        <strong style="font-size:2rem; color:#f59e0b;"><?= $nbEndommage ?></strong>
    </div>
</div>

<div style="display:grid; grid-template-columns:1fr 2fr; gap:1.5rem;">
    <!-- Scanner -->
    <?php if ($inventaire['status'] === 'EN_COURS'): ?>
    <section class="card" style="height:fit-content;">
        <h2 class="card-title"><span class="material-icons">qr_code_scanner</span> Scanner un colis</h2>
        <form method="POST" action="<?= View::url('colisage/inventaire/' . $inventaire['id'] . '/scan') ?>">
            <?= Csrf::input() ?>
            <div class="form-group">
                <label for="tracking_number">N° Tracking *</label>
                <input type="text" name="tracking_number" id="tracking_number" class="form-input" placeholder="LBP-XXXX-XXXXXXXX" required autofocus autocomplete="off">
                <small class="form-hint">Saisir ou scanner le code-barres du colis.</small>
            </div>
            <div class="form-group">
                <label>État constaté</label>
                <select name="scan_status" class="form-select">
                    <option value="PRESENT">✅ Présent (bon état)</option>
                    <option value="ENDOMMAGE">⚠️ Endommagé</option>
                    <option value="MANQUANT">❌ Manquant (signalement)</option>
                </select>
            </div>
            <div class="form-group">
                <label>Commentaires</label>
                <input type="text" name="comments" class="form-input" placeholder="Ex: Emballage déchiré, côté gauche...">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">
                <span class="material-icons">check</span> Enregistrer le scan
            </button>
        </form>
    </section>
    <?php else: ?>
    <section class="card" style="background:#f0fdf4; border:2px solid #10b981; height:fit-content;">
        <h2 class="card-title" style="color:#065f46;"><span class="material-icons" style="color:#10b981;">verified</span> Inventaire clôturé</h2>
        <p style="font-size:.9rem; color:#047857;">
            Clôturé le <?= $inventaire['closed_at'] ? date('d/m/Y à H:i', strtotime($inventaire['closed_at'])) : '—' ?>
        </p>
    </section>
    <?php endif; ?>

    <!-- Liste des lignes -->
    <section class="card">
        <h2 class="card-title"><span class="material-icons">list</span> Résultat du scan (<?= count($lignes) ?> colis)</h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th>N° Tracking</th>
                    <th>Description</th>
                    <th>État</th>
                    <th>Commentaire</th>
                    <th>Scanné le</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($lignes)): ?>
                <tr><td colspan="5" class="empty-row">Aucun colis scanné.</td></tr>
                <?php else: foreach ($lignes as $l): ?>
                <tr>
                    <td><a href="<?= View::url('colisage/colis/' . $l['colis_id']) ?>" class="link-tracking"><?= View::e($l['tracking_number']) ?></a></td>
                    <td><?= View::e($l['description'] ?? '—') ?></td>
                    <td>
                        <?php $ls = $statusLignes[$l['status']] ?? ['label' => $l['status'], 'class' => 'badge-default']; ?>
                        <span class="badge <?= $ls['class'] ?>"><?= $ls['label'] ?></span>
                    </td>
                    <td><?= View::e($l['comments'] ?? '—') ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($l['scanned_at'])) ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </section>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
