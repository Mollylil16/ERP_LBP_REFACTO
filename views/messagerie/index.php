<?php

declare(strict_types=1);

use App\View\Components\Ui;

/** @var \App\Support\ViewBag $viewData */ $viewData ??= \App\Support\ViewBag::from(get_defined_vars());

/*
 * Page provisoire du lot 1 : le socle (tables, API) est livré, l'écran des
 * discussions arrive au lot 2.
 */
ob_start();
echo Ui::pageHeader(
    'Messagerie',
    'Échangez avec vos collègues, en direct ou en groupe.',
    ['eyebrow' => 'Messagerie interne']
);
echo Ui::section(
    'Discussions',
    Ui::emptyState(
        'L’écran des discussions arrive bientôt.',
        'La messagerie interne est en cours d’installation. Vos échanges apparaîtront ici dès sa mise en service.'
    )
);
$content = ob_get_clean();

require BASE_PATH . '/views/layouts/module.php';
