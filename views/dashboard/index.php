<?php

use App\Helpers\View;
use App\View\Components\Dashboard;
use App\View\Components\Ui;

/** @var array $user */
ob_start();
?>
<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Bonjour ' . ($user['name'] ?? 'Utilisateur'),
            'Vue consolidée des indicateurs et accès rapides vers les modules ERP.',
            ['eyebrow' => 'Dashboard opérationnel', 'class' => 'portal-hero', 'actions' => Ui::button('Choisir un module', ['href' => 'selection_portail', 'variant' => 'accent'])]
        ) ?>

        <?= Dashboard::kpis([
            ['label' => 'Opérations', 'value' => 24, 'meta' => 'Flux actifs aujourd’hui', 'href' => 'selection_portail'],
            ['label' => 'Documents', 'value' => 11, 'meta' => 'À valider ou signer', 'href' => 'selection_portail'],
            ['label' => 'Équipes', 'value' => 7, 'meta' => 'Utilisateurs connectés', 'href' => 'admin/users'],
            ['label' => 'Conformité', 'value' => '96%', 'meta' => 'Niveau de conformité', 'href' => 'admin/system-tests'],
        ], ['class' => 'portal-stats']) ?>

        <?= Ui::section('Accès rapides', Dashboard::actions([
            ['label' => 'Portail des modules', 'hint' => 'Accéder aux espaces métier autorisés', 'url' => '/selection_portail'],
            ['label' => 'Espace employé', 'hint' => 'Demandes RH, pointage et documents', 'url' => '/espace-employe'],
            ['label' => 'Ressources humaines', 'hint' => 'Pilotage des collaborateurs et workflows', 'url' => '/rh/dashboard'],
        ])) ?>
    </div>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/app.php';
