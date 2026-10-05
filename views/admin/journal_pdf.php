<?php

use App\View\Components\AdminComptesJournal;
use App\View\Pages\Admin\JournalPage;

/** @var JournalPage $page */

echo AdminComptesJournal::exportPdf($page->lignes, $page->filtres, $page->editePar);
