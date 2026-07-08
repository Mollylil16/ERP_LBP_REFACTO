<?php

declare(strict_types=1);

namespace App\Models\Colisage;

final class Client
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $phone,
        public readonly ?string $email,
        public readonly ?string $address,
        public readonly string $type,
        public readonly string $createdAt,
        public readonly ?string $updatedAt
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['id'] ?? 0),
            (string) ($data['name'] ?? ''),
            isset($data['phone']) ? (string) $data['phone'] : null,
            isset($data['email']) ? (string) $data['email'] : null,
            isset($data['address']) ? (string) $data['address'] : null,
            (string) ($data['type'] ?? 'standard'),
            (string) ($data['created_at'] ?? ''),
            isset($data['updated_at']) ? (string) $data['updated_at'] : null
        );
    }
}
