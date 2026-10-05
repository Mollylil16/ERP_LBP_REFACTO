<?php

use App\View\Components\AdminComptesJournal;
use App\View\Pages\Admin\JournalPage;

/** @var JournalPage $page */

echo AdminComptesJournal::exportExcel($page->lignes, $page->filtres);
