<?php
/** @var array $agencies */
use App\Helpers\Csrf;
use App\Helpers\View;

ob_start();
require __DIR__ . '/../../_navigation.php';
?>

<div class="page-header">
    <div>
        <p class="eyebrow">Colisage — Inventaire</p>
        <h1>Nouvelle Campagne d'Inventaire</h1>
    </div>
    <a href="<?= View::url('colisage/inventaire') ?>" class="btn btn-ghost">
        <span class="material-icons">arrow_back</span> Retour
    </a>
</div>

<section class="card" style="max-width:600px;">
    <h2 class="card-title"><span class="material-icons">fact_check</span> Démarrer une campagne</h2>
    <form action="<?= View::url('colisage/inventaire') ?>" method="POST">
        <?= Csrf::input() ?>
        <div class="form-group">
            <label for="agency_id">Agence à inventorier *</label>
            <select name="agency_id" id="agency_id" class="form-select" required>
                <option value="">— Sélectionner une agence —</option>
                <?php foreach ($agencies as $a): ?>
                <option value="<?= $a['id'] ?>"><?= View::e($a['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <p style="font-size:.85rem; color:var(--color-muted);">
            Une campagne sera créée en statut "EN COURS". Vous pourrez ensuite scanner les colis un par un via leur numéro de tracking.
        </p>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <span class="material-icons">play_arrow</span> Démarrer l'inventaire
            </button>
        </div>
    </form>
</section>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
