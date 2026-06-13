<?php

use App\Helpers\Csrf;

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

        <form method="POST" action="<?= App\Helpers\View::url('login') ?>" class="auth-form">
            <?= Csrf::input() ?>

            <label for="email">Identifiant ou email</label>
            <input id="email" name="email" type="text" placeholder="admin ou admin@erp-lbp.local" required autocomplete="username">

            <label for="password">Mot de passe</label>
            <input id="password" name="password" type="password" placeholder="••••••••" required autocomplete="current-password">

            <button type="submit" class="btn btn-primary">Se connecter</button>
        </form>

        <div class="auth-footer">
            <span>Accès sécurisé • ERP de transit • Version 1.0</span>
        </div>
    </div>
</section>

<?php
$content = ob_get_clean();

require BASE_PATH . '/views/layouts/guest.php';
