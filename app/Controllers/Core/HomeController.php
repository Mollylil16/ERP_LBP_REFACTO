<?php

namespace App\Controllers\Core;

use App\Controllers\BaseController;
use App\Helpers\Auth;
use App\Helpers\Response;

class HomeController extends BaseController
{
    public function index(): void
    {
        if (Auth::check()) {
            Response::redirect('selection_portail');
        } else {
            Response::redirect('login');
        }
    }
}
