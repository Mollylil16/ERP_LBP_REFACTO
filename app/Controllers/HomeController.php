<?php

namespace App\Controllers;

class HomeController extends BaseController
{
    public function index(): void
    {
        $this->view('home', [
            'pageTitle' => 'Accueil',
        ]);
    }
}
