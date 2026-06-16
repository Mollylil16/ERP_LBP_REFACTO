<?php
/** @var array $agencies */
use App\Helpers\Csrf;
use App\Helpers\View;

ob_start();
require __DIR__ . '/../../_navigation.php';
?>

<div class="page-header">
    <div>
        <p class="eyebrow">Logistique Interne — Fournitures</p>
        <h1>Nouvelle demande de fournitures</h1>
    </div>
    <a href="<?= View::url('logistique/fournitures') ?>" class="btn btn-ghost">
        <span class="material-icons">arrow_back</span> Retour
    </a>
</div>

<form action="<?= View::url('logistique/fournitures') ?>" method="POST" class="card" style="max-width:600px;">
    <?= Csrf::input() ?>
    
    <div class="form-group">
        <label for="agency_id">Agence demanderesse *</label>
        <select name="agency_id" id="agency_id" class="form-select" required autofocus>
            <option value="">— Sélectionner l'agence —</option>
            <?php foreach ($agencies as $a): ?>
            <option value="<?= $a['id'] ?>"><?= View::e($a['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div class="form-group">
        <label for="items_requested">Articles demandés *</label>
        <textarea name="items_requested" id="items_requested" class="form-textarea" rows="6" required placeholder="- 5 Rames de papier A4
- 10 Rouleaux de scotch d'emballage
- 2 Cartouches d'encre (HP 305)
..."></textarea>
        <small class="form-hint">Détaillez les articles, quantités et références si possible.</small>
    </div>

    <div class="form-actions" style="margin-top:1.5rem;">
        <button type="submit" class="btn btn-primary">
            <span class="material-icons">send</span> Soumettre la demande
        </button>
    </div>
</form>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
