<?php

namespace App;

use App\Middleware\ModuleMaintenanceMiddleware;

class Router
{
    private array $routes = [];
    private array $groupPrefixes = [];

    public function get(string $uri, callable|array $action): void
    {
        $this->addRoute('GET', $uri, $action);
    }

    public function post(string $uri, callable|array $action): void
    {
        $this->addRoute('POST', $uri, $action);
    }

    public function group(string $prefix, callable $callback): void
    {
        $this->groupPrefixes[] = $this->normalizePrefix($prefix);

        try {
            $callback($this);
        } finally {
            array_pop($this->groupPrefixes);
        }
    }

    private function addRoute(string $method, string $uri, callable|array $action): void
    {
        $uri = $this->buildGroupedUri($uri);

        $this->routes[] = [
            'method' => $method,
            'uri' => $this->normalizeUri($uri),
            'action' => $action,
        ];
    }

    public function dispatch(string $requestUri, string $requestMethod): void
    {
        $requestUri = $this->normalizeUri($requestUri);
        $this->redirectLegacyPhpUrl($requestUri);
        $maintenance = ModuleMaintenanceMiddleware::stateForPath($requestUri);
        if ($maintenance !== null) {
            http_response_code(503);
            require BASE_PATH . '/views/errors/maintenance.php';
            return;
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== strtoupper($requestMethod)) {
                continue;
            }

            $params = $this->matchRoute($route['uri'], $requestUri);
            if ($params !== null) {
                $this->executeAction($route['action'], $params);
                return;
            }
        }

        http_response_code(404);

        $pageTitle = 'Page introuvable';

        require BASE_PATH . '/views/errors/404.php';
    }

    private function executeAction(callable|array $action, array $params = []): void
    {
        if (is_callable($action)) {
            call_user_func_array($action, $params);
            return;
        }

        [$controllerClass, $method] = $action;

        $controller = new $controllerClass();

        $controller->$method(...$params);
    }

    private function matchRoute(string $routeUri, string $requestUri): ?array
    {
        $parameterNames = [];
        $pattern = preg_replace_callback(
            '/\\\\\{([a-zA-Z_][a-zA-Z0-9_]*)\\\\\}/',
            static function (array $matches) use (&$parameterNames): string {
                $parameterNames[] = $matches[1];
                return '([^/]+)';
            },
            preg_quote($routeUri, '#')
        );
        if (!preg_match('#^' . $pattern . '$#', $requestUri, $matches)) {
            return null;
        }

        array_shift($matches);
        $params = [];
        foreach ($matches as $index => $value) {
            $params[$parameterNames[$index] ?? $index] = rawurldecode($value);
        }

        return array_values($params);
    }

    private function redirectLegacyPhpUrl(string $uri): void
    {
        if ($uri === '/index.php' || !str_ends_with(strtolower($uri), '.php')) {
            return;
        }

        $config = require BASE_PATH . '/config/app.php';
        $canonicalPath = substr($uri, 0, -4) ?: '/';
        $query = $_SERVER['QUERY_STRING'] ?? '';
        $target = rtrim($config['url'], '/') . $canonicalPath;

        if ($query !== '') {
            $target .= '?' . $query;
        }

        header('Location: ' . $target, true, 301);
        exit;
    }

    private function buildGroupedUri(string $uri): string
    {
        $parts = array_filter(
            [...$this->groupPrefixes, $this->normalizePrefix($uri)],
            static fn(string $part): bool => $part !== ''
        );

        return $parts === [] ? '/' : '/' . implode('/', array_map(
            static fn(string $part): string => trim($part, '/'),
            $parts
        ));
    }

    private function normalizePrefix(string $prefix): string
    {
        return trim($prefix, '/');
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
