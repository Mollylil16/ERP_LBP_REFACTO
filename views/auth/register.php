<?php
ob_start();
?>

<section class="auth-page">

    <div class="auth-card">


        <div class="auth-header">
            <h1>Créer un compte</h1>

        </div>

        <form method="POST" action="">

            <button type="submit" class="btn btn-primary btn-block">
                Créer mon compte
            </button>

        </form>

        <div class="auth-footer">
            <span>Déjà inscrit ?</span>

            <a href="">
                Se connecter
            </a>
        </div>

    </div>

</section>

<?php
$content = ob_get_clean();

require BASE_PATH . '/views/layouts/guest.php';
