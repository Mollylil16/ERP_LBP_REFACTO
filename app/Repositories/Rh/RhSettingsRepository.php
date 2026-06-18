<?php

namespace App\Repositories\Rh;

use PDO;
use RuntimeException;

class RhSettingsRepository
{
    private const CATALOGS = [
        'services' => ['title' => 'Services', 'table' => 'rh_services', 'has_code' => true],
        'functions' => ['title' => 'Fonctions', 'table' => 'rh_functions', 'has_code' => true],
        'statuses' => ['title' => 'Statuts / types de contrat', 'table' => 'rh_statuses', 'has_code' => true],
        'exit_reasons' => ['title' => 'Motifs de sortie', 'table' => 'rh_exit_reasons', 'has_code' => false],
        'document_types' => ['title' => 'Types de documents', 'table' => 'rh_document_types', 'has_code' => true],
        'sites' => ['title' => 'Sites / points de vente', 'table' => 'company_sites', 'has_code' => true],
    ];

    public function __construct(private PDO $pdo) {}

    public function catalogs(): array
    {
        $catalogs = [];
        foreach (self::CATALOGS as $key => $config) {
            $columns = $config['has_code'] ? 'id, name, code, is_active' : 'id, name, NULL AS code, is_active';
            $columns .= $key === 'sites'
                ? ', address, latitude, longitude'
                : ', NULL AS address, NULL AS latitude, NULL AS longitude';
            $order = $key === 'statuses' ? 'sort_order, name' : 'name';
            $rows = $this->pdo->query("SELECT {$columns} FROM {$config['table']} ORDER BY is_active DESC, {$order}")->fetchAll() ?: [];
            $catalogs[$key] = $config + ['key' => $key, 'rows' => $rows];
        }
        return $catalogs;
    }

    public function save(string $catalog, array $input): void
    {
        $config = $this->config($catalog);
        $name = trim((string)($input['name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('Le libelle est obligatoire.');
        }
        $id = (int)($input['id'] ?? 0);
        $code = trim((string)($input['code'] ?? '')) ?: null;
        if ($config['has_code'] && $code === null) {
            $code = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $name));
            $code = trim($code, '_') ?: substr(md5($name), 0, 8);
        }
        [$latitude, $longitude] = $catalog === 'sites'
            ? $this->coordinates($input)
            : [null, null];
        $address = $catalog === 'sites' ? trim((string) ($input['address'] ?? '')) : null;
        if ($address !== null && mb_strlen($address) > 255) {
            throw new RuntimeException('Le libelle de la position est trop long.');
        }

        if ($id > 0) {
            if ($catalog === 'sites') {
                $stmt = $this->pdo->prepare(
                    "UPDATE {$config['table']}
                     SET name = :name, code = :code, address = :address,
                         latitude = :latitude, longitude = :longitude, updated_at = NOW()
                     WHERE id = :id"
                );
                $stmt->execute([
                    'name' => $name,
                    'code' => $code,
                    'address' => $address !== '' ? $address : null,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'id' => $id,
                ]);
                return;
            }
            if ($config['has_code']) {
                $stmt = $this->pdo->prepare("UPDATE {$config['table']} SET name = :name, code = :code, updated_at = NOW() WHERE id = :id");
                $stmt->execute(['name' => $name, 'code' => $code, 'id' => $id]);
            } else {
                $stmt = $this->pdo->prepare("UPDATE {$config['table']} SET name = :name, updated_at = NOW() WHERE id = :id");
                $stmt->execute(['name' => $name, 'id' => $id]);
            }
            return;
        }
        if ($catalog === 'sites') {
            $stmt = $this->pdo->prepare(
                "INSERT INTO {$config['table']} (name, code, address, latitude, longitude, is_active, created_at)
                 VALUES (:name, :code, :address, :latitude, :longitude, 1, NOW())"
            );
            $stmt->execute([
                'name' => $name,
                'code' => $code,
                'address' => $address !== '' ? $address : null,
                'latitude' => $latitude,
                'longitude' => $longitude,
            ]);
            return;
        }
        if ($config['has_code']) {
            $stmt = $this->pdo->prepare("INSERT INTO {$config['table']} (name, code, is_active, created_at) VALUES (:name, :code, 1, NOW())");
            $stmt->execute(['name' => $name, 'code' => $code]);
        } else {
            $stmt = $this->pdo->prepare("INSERT INTO {$config['table']} (name, is_active, created_at) VALUES (:name, 1, NOW())");
            $stmt->execute(['name' => $name]);
        }
    }

    public function toggle(string $catalog, int $id): void
    {
        $config = $this->config($catalog);
        if ($id <= 0) {
            throw new RuntimeException('Parametre introuvable.');
        }
        $stmt = $this->pdo->prepare("UPDATE {$config['table']} SET is_active = IF(is_active = 1, 0, 1), updated_at = NOW() WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    private function config(string $catalog): array
    {
        if (!isset(self::CATALOGS[$catalog])) {
            throw new RuntimeException('Catalogue RH invalide.');
        }
        return self::CATALOGS[$catalog];
    }

    /** @return array{0:?float,1:?float} */
    private function coordinates(array $input): array
    {
        $latitudeValue = trim((string) ($input['latitude'] ?? ''));
        $longitudeValue = trim((string) ($input['longitude'] ?? ''));

        if ($latitudeValue === '' && $longitudeValue === '') {
            return [null, null];
        }
        if ($latitudeValue === '' || $longitudeValue === '') {
            throw new RuntimeException('La latitude et la longitude doivent etre renseignees ensemble.');
        }
        if (!is_numeric($latitudeValue) || !is_numeric($longitudeValue)) {
            throw new RuntimeException('Les coordonnees geographiques sont invalides.');
        }

        $latitude = (float) $latitudeValue;
        $longitude = (float) $longitudeValue;
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            throw new RuntimeException('Les coordonnees geographiques sont hors limites.');
        }

        return [$latitude, $longitude];
    }
}
