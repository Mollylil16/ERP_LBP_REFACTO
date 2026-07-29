<?php

namespace App\Controllers\Core;

use App\Controllers\BaseController;
use App\Helpers\Auth;

class HomeController extends BaseController
{
    public function index(): void
    {
        if (Auth::check()) {
            $this->redirect('selection_portail');
        } else {
            $this->redirect('login');
        }
    }
}
