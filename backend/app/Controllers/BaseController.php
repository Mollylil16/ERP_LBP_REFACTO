<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\JWT;
use App\Core\Request;
use App\Core\Response;

class BaseController
{
    protected function json(mixed $data, int $statusCode = 200): void
    {
        Response::json($data, $statusCode);
    }

    protected function getRequestBody(): array
    {
        return Request::all();
    }

    protected function getQueryParams(): array
    {
        return Request::query();
    }

    protected function authenticate(): array
    {
        $authHeader = Request::header('Authorization') ?? Request::header('authorization') ?? '';

        if (str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            $secret = Config::get('app.jwt_secret');
            $payload = JWT::decode($token, $secret);
            if ($payload) {
                Auth::setTokenPayload($payload);
                $user = Auth::user();
                if ($user) {
                    return $user;
                }
            }
        }

        Response::error('Non autorisé', 401);
    }

    protected function checkPermission(string ...$permissions): void
    {
        $user = $this->authenticate();

        if (!in_array('setup.bypass', $permissions, true)) {
            if (!empty($user['must_change_password'])) {
                Response::error('Mot de passe doit être changé', 403, ['code' => 'FORCE_CHANGE_PASSWORD']);
            }
            if (empty($user['agence_selected'])) {
                Response::error('Agence non sélectionnée', 403, ['code' => 'FORCE_SELECT_AGENCE']);
            }
        }

        if (in_array($user['nom_role'] ?? '', ['SUPER_ADMIN', 'ADMIN'], true) || ($user['code_acces'] ?? 0) === 2) {
            return;
        }

        // Si setup.bypass est la seule permission, on s'arrête ici
        if (count($permissions) === 1 && $permissions[0] === 'setup.bypass') {
            return;
        }

        if (!Auth::hasPermission(...$permissions)) {
            Response::error('Accès interdit', 403);
        }
    }
}
