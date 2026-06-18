<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Repositories\Admin\SystemTestRepository;
use Throwable;

final class SystemTestService
{
    private const STATUS_PASSED = 'passed';
    private const STATUS_WARNING = 'warning';
    private const STATUS_FAILED = 'failed';

    public function __construct(private SystemTestRepository $repository) {}

    /** @return array<string, mixed> */
    public function dashboardSummary(): array
    {
        $latest = $this->repository->latestRun();
        return [
            'latest' => $latest,
            'healthScore' => (int) ($latest['score'] ?? 0),
            'healthStatus' => (string) ($latest['status'] ?? self::STATUS_WARNING),
            'phpVersion' => PHP_VERSION,
            'environment' => $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'local',
            'basePath' => BASE_PATH,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function latestRuns(int $limit = 10): array
    {
        return $this->repository->latestRuns($limit);
    }

    /** @return array<int, array<string, mixed>> */
    public function moduleCards(): array
    {
        return array_values($this->modules());
    }

    /** @return array<string, mixed> */
    public function runApplicationSuite(): array
    {
        $startedAt = microtime(true);
        $checks = [];

        $checks[] = $this->checkDatabase();
        $checks[] = $this->checkPhpSyntax();
        $checks[] = $this->runCommandCheck('PHPUnit', $this->phpunitCommand());
        $checks[] = $this->runCommandCheck('Smoke Admin', $this->phpCommand('tests/Smoke/smoke_admin.php'));
        $checks[] = $this->runCommandCheck('Smoke Visibility', $this->phpCommand('tests/Smoke/smoke_visibility.php'));
        $checks[] = $this->runCommandCheck('Smoke Espace employé', $this->phpCommand('tests/Smoke/smoke_employee_portal.php'));

        foreach ($this->modules() as $module) {
            $checks[] = $this->runModuleProbe($module, false);
            $checks[] = $this->checkModuleViews($module);
            $checks[] = $this->checkModuleIncludes($module);
            $checks[] = $this->checkModuleAssets($module);
            $checks[] = $this->checkModuleForms($module);
            $checks[] = $this->checkComponentArchitecture($module);
            $checks[] = $this->checkModulePages($module);
        }

        return $this->finalizeRun('application', 'application', $checks, $startedAt);
    }

    /** @return array<string, mixed> */
    public function runModuleSuite(string $slug): array
    {
        $startedAt = microtime(true);
        $modules = $this->modules();
        if (!isset($modules[$slug])) {
            return [
                'ok' => false,
                'status' => self::STATUS_FAILED,
                'score' => 0,
                'message' => 'Module inconnu ou non déclaré dans SystemTestService.',
            ];
        }

        $module = $modules[$slug];
        $checks = [
            $this->checkDatabase(),
            $this->runModuleProbe($module, true),
            $this->checkModuleRoutes($module),
            $this->checkModuleViews($module),
            $this->checkModuleIncludes($module),
            $this->checkModuleAssets($module),
            $this->checkModuleForms($module),
            $this->checkComponentArchitecture($module),
            $this->checkModulePages($module),
        ];

        return $this->finalizeRun('module', $slug, $checks, $startedAt);
    }

    /** @param array<string, mixed> $module */
    private function runModuleProbe(array $module, bool $deep): array
    {
        $tables = $this->repository->inspectTables($module['tables'] ?? []);
        $failedTables = array_filter($tables, static fn(array $table): bool => $table['status'] === self::STATUS_FAILED);
        $missingTables = array_filter($tables, static fn(array $table): bool => $table['status'] === self::STATUS_WARNING);

        $status = self::STATUS_PASSED;
        if ($failedTables !== []) {
            $status = self::STATUS_FAILED;
        } elseif ($missingTables !== []) {
            $status = self::STATUS_WARNING;
        }

        return [
            'name' => 'BDD module • ' . $module['label'],
            'module' => $module['slug'],
            'status' => $status,
            'message' => $this->statusMessage($status, 'Tables métier accessibles.', 'Certaines tables métier ne sont pas encore présentes.', 'Erreur SQL pendant le contrôle du module.'),
            'details' => [
                'tables' => $tables,
                'deep' => $deep,
            ],
        ];
    }

    /** @param array<string, mixed> $module */
    private function checkModuleRoutes(array $module): array
    {
        $missing = [];
        foreach ($module['routes'] ?? [] as $route) {
            if (!is_string($route) || $route === '') {
                continue;
            }
            if (!$this->routeDeclared($route)) {
                $missing[] = $route;
            }
        }

        $status = $missing === [] ? self::STATUS_PASSED : self::STATUS_WARNING;
        return [
            'name' => 'Routes • ' . $module['label'],
            'module' => $module['slug'],
            'status' => $status,
            'message' => $missing === [] ? 'Routes principales déclarées.' : 'Routes manquantes ou non détectées dans routes/*.php.',
            'details' => ['missing' => $missing, 'expected' => $module['routes'] ?? []],
        ];
    }

    /** @param array<string, mixed> $module */
    private function checkModuleViews(array $module): array
    {
        $missing = [];
        foreach ($module['views'] ?? [] as $view) {
            $path = BASE_PATH . '/views/' . ltrim((string) $view, '/') . '.php';
            if (!is_file($path)) {
                $missing[] = $view;
            }
        }

        $status = $missing === [] ? self::STATUS_PASSED : self::STATUS_WARNING;
        return [
            'name' => 'Vues • ' . $module['label'],
            'module' => $module['slug'],
            'status' => $status,
            'message' => $missing === [] ? 'Vues principales présentes.' : 'Certaines vues ne sont pas encore créées.',
            'details' => ['missing' => $missing, 'expected' => $module['views'] ?? []],
        ];
    }

    /** @param array<string, mixed> $module */
    private function checkModuleIncludes(array $module): array
    {
        $files = $this->modulePhpFiles($module);
        $broken = [];
        $checkedIncludes = 0;

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if (!is_string($content) || $content === '') {
                continue;
            }

            foreach ($this->extractStaticIncludes($content) as $include) {
                $checkedIncludes++;
                $target = $this->resolveIncludePath($include, $file);
                if ($target === null) {
                    continue;
                }
                if (!is_file($target)) {
                    $broken[] = [
                        'file' => $this->relativePath($file),
                        'include' => $include,
                        'resolved' => $this->relativePath($target),
                    ];
                }
            }
        }

        $status = $broken === [] ? self::STATUS_PASSED : self::STATUS_FAILED;

        return [
            'name' => 'Includes / partials • ' . $module['label'],
            'module' => $module['slug'],
            'status' => $status,
            'message' => $broken === []
                ? 'Tous les includes statiques du module pointent vers des fichiers existants.'
                : 'Un ou plusieurs includes/partials requis par les vues du module sont manquants.',
            'details' => [
                'files_checked' => count($files),
                'includes_checked' => $checkedIncludes,
                'broken' => $broken,
            ],
        ];
    }

    /** @param array<string, mixed> $module */
    private function checkModuleAssets(array $module): array
    {
        $result = $this->inspectAssetReferences($this->modulePhpFiles($module));
        $status = $result['broken'] === [] ? self::STATUS_PASSED : self::STATUS_FAILED;
        return [
            'name' => 'Assets CSS / JS / images • ' . $module['label'],
            'module' => $module['slug'],
            'status' => $status,
            'message' => $status === self::STATUS_PASSED
                ? 'Toutes les références locales détectées pointent vers un fichier existant.'
                : 'Une ou plusieurs références CSS, JavaScript ou image sont cassées.',
            'details' => [
                'references_checked' => $result['checked'],
                'broken' => array_map(fn(array $item): array => [
                    'file' => $this->relativePath($item['file']),
                    'asset' => $item['asset'],
                    'resolved' => $this->relativePath($item['resolved']),
                ], $result['broken']),
            ],
        ];
    }

    /** @param array<string, mixed> $module */
    private function checkModuleForms(array $module): array
    {
        $broken = [];
        $count = 0;
        foreach ($this->modulePhpFiles($module) as $file) {
            $result = $this->inspectTemplateForms((string) file_get_contents($file));
            $count += $result['forms'];
            foreach ($result['broken'] as $issue) {
                $broken[] = ['file' => $this->relativePath($file)] + $issue;
            }
        }
        return [
            'name' => 'Formulaires & CSRF • ' . $module['label'],
            'module' => $module['slug'],
            'status' => $broken === [] ? self::STATUS_PASSED : self::STATUS_FAILED,
            'message' => $broken === [] ? 'Actions, méthodes et jetons CSRF sont cohérents.' : 'Formulaire incomplet ou non sécurisé détecté.',
            'details' => ['forms_checked' => $count, 'broken' => $broken],
        ];
    }

    /** @param array<string, mixed> $module */
    private function checkComponentArchitecture(array $module): array
    {
        $broken = [];
        $layout = (string) @file_get_contents(BASE_PATH . '/views/layouts/module.php');
        if (!str_contains($layout, 'Navigation::module')) {
            $broken[] = 'Le layout module n’utilise pas Navigation::module().';
        }
        foreach ($module['views'] ?? [] as $view) {
            if (!str_ends_with((string) $view, 'dashboard')) continue;
            $source = (string) @file_get_contents(BASE_PATH . '/views/' . $view . '.php');
            if ($source !== '' && !str_contains($source, 'Dashboard::')) {
                $broken[] = $view . ' n’utilise pas le composant Dashboard.';
            }
        }
        if (($module['slug'] ?? '') === 'rh') {
            $lifecycle = (string) @file_get_contents(BASE_PATH . '/views/rh/lifecycle/index.php');
            if (!str_contains($lifecycle, 'Modal::render') || !str_contains($lifecycle, 'RecordList::render')) {
                $broken[] = 'Le cycle de vie RH doit utiliser Modal et RecordList.';
            }
        }
        return [
            'name' => 'Architecture composants • ' . $module['label'],
            'module' => $module['slug'],
            'status' => $broken === [] ? self::STATUS_PASSED : self::STATUS_FAILED,
            'message' => $broken === [] ? 'Navigation et dashboards utilisent les composants partagés.' : 'Une page structurante contourne les composants partagés.',
            'details' => ['broken' => $broken],
        ];
    }

    private function checkDatabase(): array
    {
        try {
            $ok = $this->repository->ping();
            return [
                'name' => 'Connexion base de données',
                'module' => 'system',
                'status' => $ok ? self::STATUS_PASSED : self::STATUS_FAILED,
                'message' => $ok ? 'Connexion PDO opérationnelle.' : 'La base ne répond pas correctement.',
                'details' => ['driver' => 'pdo_mysql'],
            ];
        } catch (Throwable $e) {
            return [
                'name' => 'Connexion base de données',
                'module' => 'system',
                'status' => self::STATUS_FAILED,
                'message' => $e->getMessage(),
                'details' => [],
            ];
        }
    }

    private function checkPhpSyntax(): array
    {
        $files = $this->phpFiles([BASE_PATH . '/app', BASE_PATH . '/routes', BASE_PATH . '/tests']);
        $errors = [];
        foreach ($files as $file) {
            $command = $this->phpCliBinary() . ' -l ' . escapeshellarg($file);
            $output = [];
            $code = 0;
            exec($command . ' 2>&1', $output, $code);
            if ($code !== 0) {
                $errors[] = ['file' => $this->relativePath($file), 'output' => implode("\n", $output)];
                if (count($errors) >= 10) {
                    break;
                }
            }
        }

        return [
            'name' => 'Syntaxe PHP',
            'module' => 'system',
            'status' => $errors === [] ? self::STATUS_PASSED : self::STATUS_FAILED,
            'message' => $errors === [] ? count($files) . ' fichier(s) contrôlé(s), aucune erreur de syntaxe.' : 'Erreur de syntaxe détectée.',
            'details' => ['files_checked' => count($files), 'errors' => $errors],
        ];
    }


    /** @param array<string, mixed> $module */
    private function checkModulePages(array $module): array
    {
        $pages = $module['pages'] ?? $module['routes'] ?? [];
        $results = [];

        foreach ($pages as $page) {
            if (!is_string($page) || $page === '') {
                continue;
            }

            $results[] = $this->probeHttpPage($page);
        }

        $failed = array_values(array_filter($results, static fn(array $result): bool => $result['status'] === self::STATUS_FAILED));
        $warnings = array_values(array_filter($results, static fn(array $result): bool => $result['status'] === self::STATUS_WARNING));

        $status = self::STATUS_PASSED;
        if ($failed !== []) {
            $status = self::STATUS_FAILED;
        } elseif ($warnings !== []) {
            $status = self::STATUS_WARNING;
        }

        return [
            'name' => 'Pages HTTP • ' . $module['label'],
            'module' => $module['slug'],
            'status' => $status,
            'message' => match ($status) {
                self::STATUS_PASSED => 'Toutes les pages principales répondent sans erreur détectée.',
                self::STATUS_WARNING => 'Certaines pages répondent avec un avertissement HTTP ou applicatif.',
                default => 'Une ou plusieurs pages du module affichent une erreur.',
            },
            'details' => [
                'pages' => $results,
                'failed_count' => count($failed),
                'warning_count' => count($warnings),
            ],
        ];
    }

    private function probeHttpPage(string $path): array
    {
        $url = $this->absoluteUrl($path);
        $startedAt = microtime(true);

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 8,
                'ignore_errors' => true,
                'header' => "User-Agent: ERP-LBP-HealthCheck/1.0\r\nAccept: text/html,application/json\r\n",
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        $headers = $http_response_header ?? [];
        $httpCode = $this->extractHttpCode($headers);
        $bodyText = is_string($body) ? $body : '';

        $patterns = [
            'Fatal error',
            'Parse error',
            'Warning:',
            'Notice:',
            'Uncaught',
            'Stack trace',
            'Erreur de connexion à la base de données',
            'SQLSTATE',
            'PDOException',
            'mysqli_sql_exception',
            'Undefined variable',
            'Undefined array key',
            'Call to undefined',
            'Class "',
            'Page introuvable',
        ];

        $matches = [];
        foreach ($patterns as $pattern) {
            if (stripos($bodyText, $pattern) !== false) {
                $matches[] = $pattern;
            }
        }

        $redirectLocation = $this->redirectLocation($headers);
        $isExpectedProtectedRedirect = in_array($httpCode, [301, 302, 303, 307, 308], true)
            && $this->isLoginRedirect($redirectLocation, $bodyText);

        $forms = $isExpectedProtectedRedirect ? ['forms' => 0, 'broken' => []] : $this->inspectHtmlForms($bodyText);
        $status = self::STATUS_PASSED;
        $message = $isExpectedProtectedRedirect ? 'Page protégée correctement par authentification.' : 'Page accessible.';

        if (!$isExpectedProtectedRedirect && ($body === false || $httpCode >= 500 || $matches !== [] || $forms['broken'] !== [])) {
            $status = self::STATUS_FAILED;
            $message = 'Erreur détectée sur la page.';
        } elseif (!$isExpectedProtectedRedirect && $httpCode >= 400) {
            $status = self::STATUS_WARNING;
            $message = 'La page répond avec un code HTTP non bloquant mais à vérifier.';
        }

        return [
            'path' => $path,
            'url' => $url,
            'status' => $status,
            'http_code' => $httpCode,
            'message' => $message,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'matched_patterns' => $matches,
            'forms' => $forms,
            'excerpt' => $this->errorExcerpt($bodyText, $matches),
        ];
    }

    private function absoluteUrl(string $path): string
    {
        $config = require BASE_PATH . '/config/app.php';
        $baseUrl = rtrim((string) ($config['url'] ?? ''), '/');

        if ($baseUrl === '') {
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $baseUrl = $scheme . '://' . $host;
        }

        return $baseUrl . '/' . ltrim($path, '/');
    }

    /** @param array<int, string> $headers */
    private function redirectLocation(array $headers): string
    {
        foreach ($headers as $header) {
            if (stripos($header, 'Location:') === 0) {
                return trim(substr($header, strlen('Location:')));
            }
        }

        return '';
    }

    private function isLoginRedirect(string $location, string $body): bool
    {
        $target = strtolower($location);
        if ($target !== '' && (str_contains($target, '/login') || str_ends_with($target, 'login'))) {
            return true;
        }

        $excerpt = strtolower(strip_tags(substr($body, 0, 800)));
        return str_contains($excerpt, 'connexion') && str_contains($excerpt, 'erp lbp');
    }

    /** @param array<int, string> $headers */
    private function extractHttpCode(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches)) {
                return (int) $matches[1];
            }
        }

        return 0;
    }

    /** @param array<int, string> $patterns */
    private function errorExcerpt(string $body, array $patterns): string
    {
        if ($body === '') {
            return '';
        }

        foreach ($patterns as $pattern) {
            $position = stripos($body, $pattern);
            if ($position !== false) {
                $start = max(0, $position - 220);
                return trim(strip_tags(substr($body, $start, 900)));
            }
        }

        return trim(strip_tags(substr($body, 0, 500)));
    }



    /** @param array<int, string> $files @return array{checked:int, broken:array<int, array{file:string, asset:string, resolved:string}>} */
    private function inspectAssetReferences(array $files): array
    {
        $checked = 0;
        $broken = [];
        $patterns = [
            '/(?:href|src)=["\']([^"\']+\.(?:css|js|png|jpe?g|gif|svg|webp|ico|woff2?|ttf))(?:\?[^"\']*)?["\']/i',
            '/asset_url\(["\']([^"\']+)["\']\)/i',
        ];

        foreach ($files as $file) {
            $content = (string) @file_get_contents($file);
            if ($content === '') {
                continue;
            }

            foreach ($patterns as $pattern) {
                if (!preg_match_all($pattern, $content, $matches)) {
                    continue;
                }

                foreach ($matches[1] as $asset) {
                    $asset = trim((string) $asset);
                    if ($asset === '' || preg_match('#^(?:https?:)?//#i', $asset) || str_starts_with($asset, 'data:') || str_starts_with($asset, '#')) {
                        continue;
                    }

                    $checked++;
                    $resolved = $this->resolveAssetPath($asset);
                    if ($resolved === null || !is_file($resolved)) {
                        $broken[] = [
                            'file' => $file,
                            'asset' => $asset,
                            'resolved' => $resolved ?? BASE_PATH . '/' . ltrim($asset, '/'),
                        ];
                    }
                }
            }
        }

        return ['checked' => $checked, 'broken' => $broken];
    }

    private function resolveAssetPath(string $asset): ?string
    {
        $asset = preg_replace('/[?#].*$/', '', $asset) ?? $asset;
        $asset = ltrim($asset, '/');

        $candidates = [
            BASE_PATH . '/public/' . $asset,
            BASE_PATH . '/' . $asset,
        ];

        if (str_starts_with($asset, 'css/') || str_starts_with($asset, 'js/') || str_starts_with($asset, 'images/')) {
            $candidates[] = BASE_PATH . '/assets/' . $asset;
            $candidates[] = BASE_PATH . '/public/assets/' . $asset;
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return $candidates[0] ?? null;
    }

    /** @return array{forms:int, broken:array<int, array<string, mixed>>} */
    private function inspectTemplateForms(string $content): array
    {
        return $this->inspectForms($content, true);
    }

    /** @return array{forms:int, broken:array<int, array<string, mixed>>} */
    private function inspectHtmlForms(string $content): array
    {
        return $this->inspectForms($content, false);
    }

    /** @return array{forms:int, broken:array<int, array<string, mixed>>} */
    private function inspectForms(string $content, bool $templateMode): array
    {
        $broken = [];
        if ($content === '' || !preg_match_all('/<form\b[^>]*>(.*?)<\/form>/is', $content, $matches, PREG_SET_ORDER)) {
            return ['forms' => 0, 'broken' => []];
        }

        foreach ($matches as $index => $match) {
            $form = (string) $match[0];
            $attributes = $this->htmlAttributes($form);
            $method = strtoupper((string) ($attributes['method'] ?? 'GET'));
            $action = trim((string) ($attributes['action'] ?? ''));

            if ($action === '' && !$this->formHasTemplateAction($form)) {
                $broken[] = ['form' => $index + 1, 'issue' => 'missing_action'];
            }

            if (!in_array($method, ['GET', 'POST'], true)) {
                $broken[] = ['form' => $index + 1, 'issue' => 'invalid_method', 'method' => $method];
            }

            if ($method === 'POST') {
                $hasCsrf = str_contains($form, 'csrf_field(')
                    || str_contains($form, 'Csrf::field(')
                    || str_contains($form, 'Csrf::input(')
                    || preg_match('/name=["\']_csrf_token["\']/i', $form) === 1
                    || preg_match('/name=["\']csrf_token["\']/i', $form) === 1
                    || preg_match('/name=["\']_token["\']/i', $form) === 1;

                if (!$hasCsrf) {
                    $broken[] = ['form' => $index + 1, 'issue' => $templateMode ? 'missing_csrf_template' : 'missing_csrf_html'];
                }
            }
        }

        return ['forms' => count($matches), 'broken' => $broken];
    }

    private function formHasTemplateAction(string $form): bool
    {
        if (preg_match('/<form\b[^>]*\baction\s*=\s*(["\'])\s*<\?=/is', $form) === 1) {
            return true;
        }

        return preg_match('/<form\b[^>]*\baction\s*=\s*(["\'])[^"\']+\1/is', $form) === 1;
    }

    /** @return array<string, string> */
    private function htmlAttributes(string $tag): array
    {
        $attributes = [];
        if (preg_match_all('/([a-zA-Z_:][-a-zA-Z0-9_:.]*)\s*=\s*(["\'])(.*?)\2/s', $tag, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $attributes[strtolower($match[1])] = html_entity_decode($match[3], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }
        return $attributes;
    }

    private function runCommandCheck(string $name, string $command): array
    {
        $output = [];
        $code = 0;
        $startedAt = microtime(true);
        exec('cd ' . escapeshellarg(BASE_PATH) . ' && ' . $command . ' 2>&1', $output, $code);

        return [
            'name' => $name,
            'module' => 'system',
            'status' => $code === 0 ? self::STATUS_PASSED : self::STATUS_FAILED,
            'message' => $code === 0 ? $name . ' terminé avec succès.' : $name . ' a échoué.',
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'details' => [
                'exit_code' => $code,
                'command' => $command,
                'output' => implode("\n", array_slice($output, -120)),
            ],
        ];
    }

    /** @param array<int, array<string, mixed>> $checks */
    private function finalizeRun(string $scope, string $module, array $checks, float $startedAt): array
    {
        $score = $this->score($checks);
        $status = $this->globalStatus($checks);
        $payload = [
            'ok' => $status !== self::STATUS_FAILED,
            'scope' => $scope,
            'module' => $module,
            'status' => $status,
            'score' => $score,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'checks' => $checks,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $payload['run_id'] = $this->repository->storeRun($scope, $module, $status, $score, $payload);
        return $payload;
    }

    /** @param array<int, array<string, mixed>> $checks */
    private function score(array $checks): int
    {
        if ($checks === []) {
            return 0;
        }

        $points = 0;
        foreach ($checks as $check) {
            $points += match ($check['status'] ?? self::STATUS_FAILED) {
                self::STATUS_PASSED => 100,
                self::STATUS_WARNING => 55,
                default => 0,
            };
        }

        return (int) round($points / count($checks));
    }

    /** @param array<int, array<string, mixed>> $checks */
    private function globalStatus(array $checks): string
    {
        foreach ($checks as $check) {
            if (($check['status'] ?? self::STATUS_FAILED) === self::STATUS_FAILED) {
                return self::STATUS_FAILED;
            }
        }
        foreach ($checks as $check) {
            if (($check['status'] ?? self::STATUS_FAILED) === self::STATUS_WARNING) {
                return self::STATUS_WARNING;
            }
        }
        return self::STATUS_PASSED;
    }

    private function statusMessage(string $status, string $passed, string $warning, string $failed): string
    {
        return match ($status) {
            self::STATUS_PASSED => $passed,
            self::STATUS_WARNING => $warning,
            default => $failed,
        };
    }


    private function phpCliBinary(): string
    {
        $candidates = [];

        if (PHP_OS_FAMILY === 'Windows') {
            $candidates[] = PHP_BINDIR . DIRECTORY_SEPARATOR . 'php.exe';
            $candidates[] = 'php';
        } else {
            $candidates[] = PHP_BINDIR . DIRECTORY_SEPARATOR . 'php';
            $candidates[] = 'php';
        }

        foreach ($candidates as $candidate) {
            if ($candidate === 'php' || is_file($candidate)) {
                return escapeshellarg($candidate);
            }
        }

        return escapeshellarg('php');
    }

    private function phpunitCommand(): string
    {
        $binary = PHP_OS_FAMILY === 'Windows' ? '.\\vendor\\bin\\phpunit.bat' : './vendor/bin/phpunit';
        return $binary . ' --colors=never';
    }

    private function phpCommand(string $script): string
    {
        return $this->phpCliBinary() . ' ' . escapeshellarg(BASE_PATH . '/' . ltrim($script, '/'));
    }

    /** @param array<int, string> $directories @return array<int, string> */
    private function phpFiles(array $directories): array
    {
        $files = [];
        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }
        sort($files);
        return $files;
    }

    private function routeDeclared(string $route): bool
    {
        $contents = '';
        foreach (glob(BASE_PATH . '/routes/*.php') ?: [] as $file) {
            $contents .= "\n" . (file_get_contents($file) ?: '');
        }

        if ($contents === '') {
            return false;
        }

        if (str_contains($contents, "'{$route}'") || str_contains($contents, '"' . $route . '"')) {
            return true;
        }

        $segments = array_values(array_filter(explode('/', trim($route, '/'))));
        for ($split = 1; $split < count($segments); $split++) {
            $prefix = '/' . implode('/', array_slice($segments, 0, $split));
            $suffix = '/' . implode('/', array_slice($segments, $split));
            $hasPrefix = str_contains($contents, "'{$prefix}'") || str_contains($contents, '"' . $prefix . '"');
            $hasSuffix = str_contains($contents, "'{$suffix}'") || str_contains($contents, '"' . $suffix . '"');
            if ($hasPrefix && $hasSuffix) {
                return true;
            }
        }

        return false;
    }

    private function relativePath(string $path): string
    {
        return ltrim(str_replace(BASE_PATH, '', $path), '/\\');
    }

    /** @param array<string, mixed> $module @return array<int, string> */
    private function modulePhpFiles(array $module): array
    {
        $files = [];
        foreach ($module['views'] ?? [] as $view) {
            $path = BASE_PATH . '/views/' . ltrim((string) $view, '/') . '.php';
            if (is_file($path)) {
                $files[] = $path;
            }
        }

        $slug = (string) ($module['slug'] ?? '');
        $candidateDirectories = [
            BASE_PATH . '/views/' . $slug,
            BASE_PATH . '/views/' . str_replace('-', '_', $slug),
        ];

        if ($slug === 'rh') {
            $candidateDirectories[] = BASE_PATH . '/views/rh';
            foreach (glob(BASE_PATH . '/app/Controllers/Rh*Controller.php') ?: [] as $controller) {
                $files[] = $controller;
            }
        }
        if ($slug === 'espace-employe') {
            $files[] = BASE_PATH . '/app/Controllers/EmployeePortalController.php';
        }
        if ($slug === 'admin') {
            $candidateDirectories[] = BASE_PATH . '/views/admin';
        }
        if ($slug === 'site-admin') {
            $candidateDirectories[] = BASE_PATH . '/views/site';
        }

        foreach ($candidateDirectories as $directory) {
            foreach ($this->phpFiles([$directory]) as $file) {
                $files[] = $file;
            }
        }

        $files = array_values(array_unique($files));
        sort($files);
        return $files;
    }

    /** @return array<int, string> */
    private function extractStaticIncludes(string $content): array
    {
        $includes = [];

        $patterns = [
            '/\b(?:require|require_once|include|include_once)\s*\(?\s*BASE_PATH\s*\.\s*([\'\"])(.*?)\1\s*\)?\s*;/i',
            '/\b(?:require|require_once|include|include_once)\s*\(?\s*__DIR__\s*\.\s*([\'\"])(.*?)\1\s*\)?\s*;/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $prefix = str_contains($match[0], 'BASE_PATH') ? 'BASE_PATH' : '__DIR__';
                    $includes[] = $prefix . ':' . $match[2];
                }
            }
        }

        return array_values(array_unique($includes));
    }

    private function resolveIncludePath(string $include, string $sourceFile): ?string
    {
        if (str_starts_with($include, 'BASE_PATH:')) {
            return BASE_PATH . substr($include, strlen('BASE_PATH:'));
        }

        if (str_starts_with($include, '__DIR__:')) {
            return dirname($sourceFile) . substr($include, strlen('__DIR__:'));
        }

        return null;
    }

    /** @return array<string, array<string, mixed>> */
    private function modules(): array
    {
        return [
            'finance' => ['slug' => 'finance', 'label' => 'Finance', 'code' => 'FIN', 'accent' => '#2563eb', 'tables' => ['permission_entities', 'user_permissions'], 'routes' => ['/finance', '/finance/dashboard'], 'pages' => ['/finance', '/finance/dashboard'], 'views' => ['finance/dashboard', 'finance/_navigation']],
            'rh' => [
                'slug' => 'rh', 'label' => 'RH', 'code' => 'RH', 'accent' => '#0ea5e9',
                'tables' => ['rh_employees', 'rh_services', 'rh_functions', 'rh_statuses', 'rh_contracts', 'rh_assignments', 'rh_evaluations', 'rh_training_sessions', 'rh_workflow_requests'],
                'routes' => ['/rh', '/rh/dashboard', '/rh/personnel', '/rh/mutations', '/rh/mouvements', '/rh/pointage', '/rh/contrats', '/rh/paie', '/rh/parametrage', '/rh/cycle-vie'],
                'pages' => ['/rh', '/rh/dashboard', '/rh/personnel', '/rh/mutations', '/rh/mouvements', '/rh/pointage', '/rh/contrats', '/rh/paie', '/rh/parametrage', '/rh/cycle-vie'],
                'views' => ['rh/dashboard', 'rh/_navigation', 'rh/personnel/index', 'rh/personnel/mutations-index', 'rh/personnel/movements-index', 'rh/settings/index', 'rh/lifecycle/index'],
            ],
            'espace-employe' => [
                'slug' => 'espace-employe', 'label' => 'Espace employé', 'code' => 'EMP', 'accent' => '#0ea5e9',
                'tables' => ['users', 'rh_employees', 'employee_legal_requests', 'employee_request_events', 'rh_explanation_requests', 'rh_attendance_daily', 'rh_leave_opening_balance'],
                'routes' => ['/espace-employe', '/espace-employe/dashboard', '/espace-employe/demandes/nouvelle'],
                'pages' => ['/espace-employe', '/espace-employe/dashboard', '/espace-employe/demandes/nouvelle'],
                'views' => ['employee/dashboard', 'employee/_navigation', 'employee/request-form', 'employee/request-show'],
            ],
            'colisage' => ['slug' => 'colisage', 'label' => 'Colisage', 'code' => 'COL', 'accent' => '#f97316', 'tables' => ['permission_entities'], 'routes' => ['/colisage', '/colisage/dashboard'], 'pages' => ['/colisage', '/colisage/dashboard'], 'views' => ['colisage/dashboard', 'colisage/_navigation']],
            'logistique' => ['slug' => 'logistique', 'label' => 'Logistique', 'code' => 'LOG', 'accent' => '#22c55e', 'tables' => ['permission_entities'], 'routes' => ['/logistique', '/logistique/dashboard'], 'pages' => ['/logistique', '/logistique/dashboard'], 'views' => ['logistique/dashboard', 'logistique/_navigation']],
            'crm' => ['slug' => 'crm', 'label' => 'CRM', 'code' => 'CRM', 'accent' => '#ec4899', 'tables' => ['permission_entities'], 'routes' => ['/crm', '/crm/dashboard'], 'pages' => ['/crm', '/crm/dashboard'], 'views' => ['crm/dashboard', 'crm/_navigation']],
            'tickets' => ['slug' => 'tickets', 'label' => 'Tickets', 'code' => 'TIC', 'accent' => '#ef4444', 'tables' => ['permission_entities'], 'routes' => ['/tickets', '/tickets/dashboard'], 'pages' => ['/tickets', '/tickets/dashboard'], 'views' => ['tickets/dashboard', 'tickets/_navigation']],
            'site-admin' => ['slug' => 'site-admin', 'label' => 'Site internet', 'code' => 'WEB', 'accent' => '#14b8a6', 'tables' => ['permission_entities'], 'routes' => ['/site-admin', '/site-admin/dashboard', '/site'], 'pages' => ['/site-admin', '/site-admin/dashboard', '/site'], 'views' => ['site_admin/dashboard', 'site_admin/_navigation', 'site/index']],
            'transit-douane' => ['slug' => 'transit-douane', 'label' => 'Transit Douane', 'code' => 'TDO', 'accent' => '#7c3aed', 'tables' => ['permission_entities'], 'routes' => ['/transit-douane', '/transit-douane/dashboard'], 'pages' => ['/transit-douane', '/transit-douane/dashboard'], 'views' => ['transit_douane/dashboard', 'transit_douane/_navigation']],
            'tracking-colis' => ['slug' => 'tracking-colis', 'label' => 'Tracking Colis', 'code' => 'TRK', 'accent' => '#06b6d4', 'tables' => ['permission_entities'], 'routes' => ['/tracking-colis', '/tracking-colis/dashboard'], 'pages' => ['/tracking-colis', '/tracking-colis/dashboard'], 'views' => ['tracking_colis/dashboard', 'tracking_colis/_navigation']],
            'facturation' => ['slug' => 'facturation', 'label' => 'Facturation', 'code' => 'FAC', 'accent' => '#16a34a', 'tables' => ['permission_entities'], 'routes' => ['/facturation', '/facturation/dashboard'], 'pages' => ['/facturation', '/facturation/dashboard'], 'views' => ['facturation/dashboard', 'facturation/_navigation']],
            'entrepots' => ['slug' => 'entrepots', 'label' => 'Entrepôts', 'code' => 'ENT', 'accent' => '#a16207', 'tables' => ['permission_entities'], 'routes' => ['/entrepots', '/entrepots/dashboard'], 'pages' => ['/entrepots', '/entrepots/dashboard'], 'views' => ['entrepots/dashboard', 'entrepots/_navigation']],
            'flotte-transport' => ['slug' => 'flotte-transport', 'label' => 'Flotte / Transport', 'code' => 'FLT', 'accent' => '#ea580c', 'tables' => ['permission_entities'], 'routes' => ['/flotte-transport', '/flotte-transport/dashboard'], 'pages' => ['/flotte-transport', '/flotte-transport/dashboard'], 'views' => ['flotte_transport/dashboard', 'flotte_transport/_navigation']],
            'portefeuille-clients' => ['slug' => 'portefeuille-clients', 'label' => 'Portefeuille Clients', 'code' => 'PCL', 'accent' => '#84cc16', 'tables' => ['permission_entities'], 'routes' => ['/portefeuille-clients', '/portefeuille-clients/dashboard'], 'pages' => ['/portefeuille-clients', '/portefeuille-clients/dashboard'], 'views' => ['portefeuille_clients/dashboard', 'portefeuille_clients/_navigation']],
            'agents-correspondants' => ['slug' => 'agents-correspondants', 'label' => 'Agents & Correspondants', 'code' => 'AGT', 'accent' => '#6366f1', 'tables' => ['permission_entities'], 'routes' => ['/agents-correspondants', '/agents-correspondants/dashboard'], 'pages' => ['/agents-correspondants', '/agents-correspondants/dashboard'], 'views' => ['agents_correspondants/dashboard', 'agents_correspondants/_navigation']],
            'pilotage-dg' => ['slug' => 'pilotage-dg', 'label' => 'Pilotage DG', 'code' => 'DG', 'accent' => '#0f172a', 'tables' => ['permission_entities'], 'routes' => ['/pilotage-dg', '/pilotage-dg/dashboard'], 'pages' => ['/pilotage-dg', '/pilotage-dg/dashboard'], 'views' => ['pilotage_dg/dashboard', 'pilotage_dg/_navigation']],
            'admin' => ['slug' => 'admin', 'label' => 'Administration', 'code' => 'ADM', 'accent' => '#111827', 'tables' => ['users', 'permission_entities', 'user_permissions', 'system_test_runs'], 'routes' => ['/admin', '/admin/dashboard'], 'pages' => ['/admin', '/admin/dashboard'], 'views' => ['admin/dashboard', 'admin/_navigation', 'admin/system_tests/index']],
        ];
    }

}
