<?php

use App\Helpers\Csrf;
use App\Helpers\View;
use App\View\Components\Form;
use App\View\Components\Ui;

/** @var \App\Support\ViewBag $viewData */ $viewData ??= \App\Support\ViewBag::from(get_defined_vars());
ob_start();
?>

<section class="auth-page">
    <div class="auth-visual-panel">
        <div class="auth-visual-logo">
            <img src="<?= View::asset('images/logo-lbp.png') ?>" alt="La Belle Porte - LBP CI" class="auth-logo-img">
        </div>
        <div class="auth-badge">ERP LBP Transit</div>
        <h1>La Belle Porte<br><span class="auth-hero-accent">Côte d'Ivoire</span></h1>
        <p>Votre plateforme de gestion intégrée pour le transit, la logistique et les opérations douanières.</p>

        <ul class="auth-highlights">
            <li>Gestion complète du transit et douane</li>
            <li>Suivi des colis et expéditions en temps réel</li>
            <li>Pilotage financier et ressources humaines</li>
            <li>Tableau de bord multi-modules</li>
        </ul>

        <div class="auth-visual-decoration" aria-hidden="true">
            <div class="auth-decoration-ring"></div>
            <div class="auth-decoration-ring auth-decoration-ring--2"></div>
        </div>
    </div>

    <div class="auth-card">
        <div class="auth-card-logo">
            <img src="<?= View::asset('images/logo-lbp.png') ?>" alt="LBP" class="auth-card-logo-img">
        </div>

        <div class="auth-header">
            <span class="auth-kicker">Connexion</span>
            <h2>Bienvenue sur votre espace ERP</h2>
            <p>Connectez-vous pour accéder au tableau de bord et gérer vos opérations.</p>
        </div>

        <form method="POST" action="<?= View::url('login') ?>" class="auth-form">
            <?= Csrf::input() ?>

            <?= Form::input('email', [
                'label' => 'Identifiant ou email',
                'type' => 'text',
                'placeholder' => 'votre.email@labelleporte.ci',
                'required' => true,
                'autocomplete' => 'username',
            ]) ?>

            <?= Form::input('password', [
                'label' => 'Mot de passe',
                'type' => 'password',
                'placeholder' => '••••••••',
                'required' => true,
                'autocomplete' => 'current-password',
            ]) ?>

            <?= Ui::button('Se connecter', [
                'variant' => 'primary',
                'type' => 'submit',
                'class' => 'btn btn-primary btn-login',
            ]) ?>
        </form>

        <div class="auth-footer">
            <span>© <?= date('Y') ?> La Belle Porte CI • ERP de transit • Version 1.0</span>
        </div>
    </div>
</section>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/guest.php';
