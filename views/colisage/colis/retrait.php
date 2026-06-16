<?php
/** @var array $colis */
use App\Helpers\Csrf;
use App\Helpers\View;

ob_start();
require __DIR__ . '/../../_navigation.php';
?>

<div class="page-header">
    <div>
        <p class="eyebrow">Colisage — Remise au destinataire</p>
        <h1>Colis <?= View::e($colis['tracking_number']) ?></h1>
        <p class="subtitle">Enregistrement obligatoire des informations du récupérateur avant remise.</p>
    </div>
    <a href="<?= View::url('colisage/colis/' . $colis['id']) ?>" class="btn btn-ghost">
        <span class="material-icons">arrow_back</span> Retour
    </a>
</div>

<!-- Récapitulatif colis -->
<div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; margin-bottom:1.5rem;">
    <section class="card">
        <h2 class="card-title"><span class="material-icons">inventory_2</span> Colis à remettre</h2>
        <dl class="detail-list">
            <dt>Tracking</dt>
            <dd><strong><?= View::e($colis['tracking_number']) ?></strong></dd>
            <dt>Destinataire officiel</dt>
            <dd><strong><?= View::e($colis['receiver_name'] ?? '—') ?></strong><?= $colis['receiver_phone'] ? ' — ' . $colis['receiver_phone'] : '' ?></dd>
            <dt>Statut actuel</dt>
            <dd><span class="badge badge-success">Arrivé — Disponible au retrait</span></dd>
            <dt>Agence d'arrivée</dt>
            <dd><?= View::e($colis['arrival_agency'] ?? '—') ?></dd>
        </dl>
    </section>

    <section class="card" style="border: 2px solid #f59e0b;">
        <h2 class="card-title" style="color:#92400e;">
            <span class="material-icons" style="color:#f59e0b;">warning</span>
            Important — Vérification CNI
        </h2>
        <p style="font-size:.9rem; color:#78350f;">
            Avant toute remise, vérifiez <strong>physiquement</strong> la pièce d'identité du récupérateur.
            Ces informations sont enregistrées et engagent la responsabilité de l'opérateur.
        </p>
    </section>
</div>

<!-- Formulaire retrait -->
<section class="card">
    <h2 class="card-title"><span class="material-icons">how_to_reg</span> Informations du récupérateur</h2>
    <form method="POST" action="<?= View::url('colisage/colis/' . $colis['id'] . '/retrait') ?>" id="form-retrait">
        <?= Csrf::input() ?>
        <div class="form-grid-3">
            <div class="form-group">
                <label for="retrieval_name">Nom complet du récupérateur *</label>
                <input type="text" name="retrieval_name" id="retrieval_name" class="form-input" placeholder="Prénom Nom" required autofocus>
            </div>
            <div class="form-group">
                <label for="retrieval_cni">N° de pièce d'identité (CNI) *</label>
                <input type="text" name="retrieval_cni" id="retrieval_cni" class="form-input" placeholder="CI-XXXX-XXXXXX" required>
            </div>
            <div class="form-group">
                <label for="retrieval_phone">Téléphone du récupérateur</label>
                <input type="tel" name="retrieval_phone" id="retrieval_phone" class="form-input" placeholder="+225 00 00 00 00">
            </div>
        </div>
        <div class="form-actions" style="margin-top:1.5rem;">
            <button type="submit" class="btn btn-primary btn-lg" onclick="return confirm('Confirmer la remise du colis ? Cette action est irréversible.')">
                <span class="material-icons">how_to_reg</span> Confirmer la remise & marquer RETIRÉ
            </button>
            <a href="<?= View::url('colisage/colis/' . $colis['id']) ?>" class="btn btn-ghost btn-lg">Annuler</a>
        </div>
    </form>
</section>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
