<?php

use App\Helpers\Csrf;
use App\Helpers\View;
use App\View\Components\Form;
use App\View\Components\Ui;

require BASE_PATH . '/views/rh/_navigation.php';

ob_start();
?>
<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Importer les pointages',
            'Import badgeuse CSV',
            'Importez un fichier contenant le matricule, la date, l’heure d’arrivée et l’heure de sortie.',
            Ui::button('Retour', ['href' => 'rh/pointage', 'variant' => 'secondary']),
            ['class' => 'rh-hero']
        ) ?>

        <section class="finea-section-card rh-import-card">
            <div>
                <h2 class="finea-section-title">Format attendu</h2>
                <p>Colonnes : Matricule, Date, Heure Entree, Heure Sortie.</p>
                <code>EMP-001,2026-06-16,08:00,17:30</code>
            </div>
            <form action="<?= View::url('rh/pointage/import') ?>" method="post" enctype="multipart/form-data" class="rh-compact-form">
                <?= Csrf::input() ?>
                <?= Form::dropzone('csv_file', 'Fichier CSV de la badgeuse', [
                    'accept' => '.csv,text/csv',
                    'hint' => 'Déposez le fichier CSV ou cliquez pour le sélectionner.',
                    'required' => true,
                ]) ?>
                <?= Ui::button("Lancer l'importation", ['variant' => 'primary', 'type' => 'submit']) ?>
            </form>
        </section>
    </div>
</div>
<?php $content = ob_get_clean(); require BASE_PATH . '/views/layouts/module.php';
