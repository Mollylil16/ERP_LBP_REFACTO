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

    public function put(string $uri, callable|array $action): void
    {
        $this->addRoute('PUT', $uri, $action);
    }

    public function delete(string $uri, callable|array $action): void
    {
        $this->addRoute('DELETE', $uri, $action);
    }

    public function patch(string $uri, callable|array $action): void
    {
        $this->addRoute('PATCH', $uri, $action);
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
        $requestMethod = strtoupper($requestMethod);

        foreach ($this->routes as $route) {
            if ($route['method'] !== $requestMethod) {
                continue;
            }

            // Convertir les paramètres :param en regex capture group ([^/]+)
            $pattern = preg_replace('/:[a-zA-Z0-9_]+/', '([^/]+)', $route['uri']);
            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $requestUri, $matches)) {
                array_shift($matches); // Retirer la correspondance globale
                $params = array_map('urldecode', $matches);
                $this->executeAction($route['action'], $params);
                return;
            }
        }

        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'statusCode' => 404,
            'message' => 'Route non trouvée : ' . $requestUri
        ]);
        exit;
    }

    private function executeAction(callable|array $action, array $params = []): void
    {
        if (is_callable($action)) {
            call_user_func_array($action, $params);
            return;
        }

        [$controllerClass, $method] = $action;

        $controller = new $controllerClass();

        call_user_func_array([$controller, $method], $params);
    }

    private function normalizeUri(string $uri): string
    {
        $uri = parse_url($uri, PHP_URL_PATH) ?? '/';

        // Retire le chemin du dossier public quand le projet est dans un sous-dossier WAMP / Apache
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePath = str_replace('/public/index.php', '', $scriptName);
        $basePath = str_replace('/index.php', '', $basePath);

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
