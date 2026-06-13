<?php
ob_start();
?>

<section class="auth-page">

    <div class="auth-card">


        <div class="auth-header">
            <h1>Se connecter</h1>

        </div>

        <form method="POST" action="">



        </form>

        <div class="auth-footer">

        </div>

    </div>

</section>

<?php
$content = ob_get_clean();

require BASE_PATH . '/views/layouts/guest.php';
