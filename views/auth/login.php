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
        <div class="auth-badge">ERP LBP Transit</div>
        <h1>Centralisez vos opérations de transit.</h1>
        <p>Suivez les chargements, les documents et les équipes en temps réel depuis une interface moderne et sécurisée.</p>

        <ul class="auth-highlights">
            <li>Tableau de bord temps réel</li>
            <li>Alertes et suivi quotidien</li>
            <li>Accès administrateur sécurisé</li>
        </ul>

        <div class="auth-credential-box">
            <strong>Compte par défaut</strong>
            <span>Identifiant : admin</span>
            <span>Mot de passe : admin</span>
        </div>
    </div>

    <div class="auth-card">
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
                'placeholder' => 'admin ou admin@erp-lbp.local',
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
                'class' => 'btn btn-primary',
            ]) ?>
        </form>

        <div class="auth-footer">
            <span>Accès sécurisé • ERP de transit • Version 1.0</span>
        </div>
    </div>
</section>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/guest.php';
