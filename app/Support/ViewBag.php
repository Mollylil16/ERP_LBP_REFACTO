<?php

declare(strict_types=1);

namespace App\Support;

use ArrayAccess;

/**
 * Conteneur de donnees de vue typé pour limiter extract() et les variables indefinies.
 */
final class ViewBag implements ArrayAccess
{
    /** @param array<string,mixed> $data */
    public function __construct(private array $data = []) {}


    /** @return array<string,mixed> */
    public static function defaults(): array
    {
        return [
            'pageTitle' => '',
            'moduleName' => '',
            'moduleCode' => '',
            'activeModule' => '',
            'additionalStyles' => [],
            'additionalScripts' => [],
            'content' => '',
            'errors' => [],
            'old' => [],
            'flash' => [],
            'stats' => [],
            'statistics' => [],
            'entities' => [],
            'modules' => [],
            'navigation' => [],
            'documents' => [],
            'requests' => [],
            'attendance' => [],
            'explanations' => [],
            'employees' => [],
            'filters' => [],
            'records' => [],
            'settings' => [],
            'csrfToken' => '',
        ];
    }

    /** @param array<string,mixed> $data */
    public static function from(array $data): self
    {
        return new self($data);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function string(string $key, string $default = ''): string
    {
        return (string) ($this->data[$key] ?? $default);
    }

    public function int(string $key, int $default = 0): int
    {
        return (int) ($this->data[$key] ?? $default);
    }

    public function bool(string $key, bool $default = false): bool
    {
        return (bool) ($this->data[$key] ?? $default);
    }

    /** @return array<mixed> */
    public function array(string $key, array $default = []): array
    {
        $value = $this->data[$key] ?? $default;
        return is_array($value) ? $value : $default;
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        return $this->data;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function offsetExists(mixed $offset): bool { return is_string($offset) && $this->has($offset); }
    public function offsetGet(mixed $offset): mixed { return is_string($offset) ? $this->get($offset) : null; }
    public function offsetSet(mixed $offset, mixed $value): void { if (is_string($offset)) { $this->data[$offset] = $value; } }
    public function offsetUnset(mixed $offset): void { if (is_string($offset)) { unset($this->data[$offset]); } }
}
