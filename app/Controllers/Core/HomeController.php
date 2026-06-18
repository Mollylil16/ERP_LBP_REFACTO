<?php

namespace App\Controllers\Core;

use App\Controllers\BaseController;

class HomeController extends BaseController
{
    public function index(): void
    {
        $this->view('home', [
            'pageTitle' => 'Accueil',
        ]);
    }
}
