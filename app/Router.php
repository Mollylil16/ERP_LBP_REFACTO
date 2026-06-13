<?php

namespace App;

class Router
{
    private array $routes = [];

    public function get(string $uri, callable|array $action): void
    {
        $this->addRoute('GET', $uri, $action);
    }

    public function post(string $uri, callable|array $action): void
    {
        $this->addRoute('POST', $uri, $action);
    }

    private function addRoute(string $method, string $uri, callable|array $action): void
    {
        $this->routes[] = [
            'method' => $method,
            'uri' => $this->normalizeUri($uri),
            'action' => $action,
        ];
    }

    public function dispatch(string $requestUri, string $requestMethod): void
    {
        $requestUri = $this->normalizeUri($requestUri);

        foreach ($this->routes as $route) {

            if (
                $route['method'] === strtoupper($requestMethod)
                && $route['uri'] === $requestUri
            ) {
                $this->executeAction($route['action']);
                return;
            }
        }

        http_response_code(404);

        $pageTitle = 'Page introuvable';

        require BASE_PATH . '/views/errors/404.php';
    }

    private function executeAction(callable|array $action): void
    {
        if (is_callable($action)) {
            call_user_func($action);
            return;
        }

        [$controllerClass, $method] = $action;

        $controller = new $controllerClass();

        $controller->$method();
    }

    private function normalizeUri(string $uri): string
    {
        $uri = parse_url($uri, PHP_URL_PATH) ?? '/';

        // Retire le chemin du dossier public quand le projet est dans un sous-dossier WAMP
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePath = str_replace('/index.php', '', $scriptName);

        if ($basePath !== '' && str_starts_with($uri, $basePath)) {
            $uri = substr($uri, strlen($basePath));
        }

        if ($uri === '' || $uri === false) {
            $uri = '/';
        }

        if ($uri !== '/') {
            $uri = rtrim($uri, '/');
        }

        return $uri;
    }
}
