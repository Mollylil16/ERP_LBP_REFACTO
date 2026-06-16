<?php
use App\Helpers\Csrf;
use App\Helpers\View;
use App\View\Components\Ui;
use App\View\Components\Form;

require BASE_PATH . '/views/rh/_navigation.php';

ob_start();
?>

<?= Ui::pageHeader(
    'Importer les Pointages (Badgeuse)',
    'Importez un fichier CSV contenant les heures de présence de vos employés.',
    ['actions' => '
        <a href="' . View::url('rh/pointage') . '" class="finea-action-btn finea-action-btn--secondary">
            <i class="finea-icon">arrow_back</i> Retour
        </a>
    ']
) ?>

<div class="finea-section-card">
    <div style="margin-bottom: 2rem;">
        <h3>Format de fichier attendu (CSV)</h3>
        <p>Votre fichier doit contenir les 4 colonnes suivantes, dans l'ordre (avec en-têtes sur la 1ère ligne) :</p>
        <ul>
            <li><strong>Matricule</strong> : Le matricule de l'employé (ex: EMP-001)</li>
            <li><strong>Date</strong> : Date du pointage au format YYYY-MM-DD (ex: 2026-06-15)</li>
            <li><strong>Heure Entrée</strong> : Heure d'arrivée au format HH:MM (ex: 08:00)</li>
            <li><strong>Heure Sortie</strong> : Heure de départ au format HH:MM (ex: 18:00)</li>
        </ul>
        <div style="background: #f8fafc; padding: 1rem; border-radius: 4px; border: 1px solid #e2e8f0; margin-top: 1rem;">
            <code>Matricule,Date,Heure Entree,Heure Sortie<br>EMP-001,2026-06-15,08:00,18:00</code>
        </div>
    </div>

    <form action="<?= View::url('rh/pointage/import') ?>" method="post" enctype="multipart/form-data" style="max-width: 500px;">
        <?= Csrf::input() ?>
        
        <div class="finea-form-group">
            <label for="csv_file" class="finea-label">Fichier CSV de la badgeuse</label>
            <input type="file" id="csv_file" name="csv_file" accept=".csv" required class="finea-input" style="padding: 0.5rem;">
        </div>

        <div style="margin-top: 2rem;">
            <button type="submit" class="finea-action-btn finea-action-btn--primary">Lancer l'importation</button>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
