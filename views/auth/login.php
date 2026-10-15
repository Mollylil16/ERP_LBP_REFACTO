<?php

use App\Helpers\Csrf;
use App\Helpers\View;
use App\View\Components\Auth;
use App\View\Components\Form;
use App\View\Components\Ui;

/** @var \App\Support\ViewBag $viewData */ $viewData ??= \App\Support\ViewBag::from(get_defined_vars());
ob_start();
?>

<section class="auth-page">
    <?= Auth::panneauVisuel() ?>

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
