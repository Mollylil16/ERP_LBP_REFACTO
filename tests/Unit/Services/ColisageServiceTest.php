<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Repositories\Colisage\ColisageRepository;
use App\Services\Colisage\ColisageService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ColisageServiceTest extends TestCase
{
    #[DataProvider('routePrefixProvider')]
    public function test_register_parcel_generates_correct_prefix(
        string $type,
        ?string $depAgencyName,
        ?string $arrAgencyName,
        ?string $trajet,
        string $expectedPrefix
    ): void {
        $repository = $this->createMock(ColisageRepository::class);

        // Mock agency name resolution
        $repository->method('getAgencyNameById')
            ->willReturnCallback(function (int $id) use ($depAgencyName, $arrAgencyName) {
                if ($id === 10) {
                    return $depAgencyName;
                }
                if ($id === 20) {
                    return $arrAgencyName;
                }
                return null;
            });

        // Mock tracking prefix count sequence
        $repository->method('countParcelsWithTrackingPrefix')
            ->willReturn(5); // sequence is count + 1 => 6

        // Assert that the created parcel has the expected tracking number
        $repository->expects(self::once())
            ->method('createParcel')
            ->with(self::callback(function (array $data) use ($expectedPrefix): bool {
                $mmyy = date('my');
                $expectedFullPrefix = $expectedPrefix . '-' . $mmyy . '-006';
                return $data['numero_tracking'] === $expectedFullPrefix;
            }))
            ->willReturn(456);

        $service = new ColisageService($repository);

        $data = [
            'type_expediteur' => $type,
            'agence_depart_id' => 10,
            'agence_arrivee_id' => 20,
            'trajet' => $trajet,
            'marchandises' => [],
        ];

        $id = $service->registerParcel($data);
        self::assertSame(456, $id);
    }

    /**
     * @return array<string, array<int, string|null>>
     */
    public static function routePrefixProvider(): array
    {
        return [
            // Standard maritime
            'Maritime Export' => ['export_maritime', 'Siege Abidjan', 'Agence France', null, 'MP-CI'],
            'Maritime Import' => ['import_maritime', 'Agence France', 'Siege Abidjan', null, 'MP-FR'],

            // DHL
            'DHL Express' => ['dhl', 'Siege Abidjan', 'Agence France', null, 'DL-CI'],

            // Cargo Aérien / Groupage Aérien (standard)
            'Cargo Air: CIV -> FR' => ['export_aerien', 'Siege Abidjan', 'Agence France', null, 'LB-CI'],
            'Cargo Air: FR -> CIV' => ['import_aerien', 'Agence France', 'Siege Abidjan', null, 'LB-FR'],
            'Cargo Air: SEN -> FR' => ['export_aerien', 'Agence Sénégal', 'Agence France', null, 'S-FR'],
            'Cargo Air: CIV -> CAN' => ['export_aerien', 'Siege Abidjan', 'Agence Canada', null, 'LB-CA'],

            // Express / Colis Rapide
            'Colis Rapide: FR -> SEN' => ['colis_rapide_export', 'Agence France', 'Agence Sénégal', null, 'F-SN'],
            'Colis Rapide: CIV -> FR' => ['colis_rapide_export', 'Siege Abidjan', 'Agence France', null, 'CA-CI'],
            'Colis Rapide: FR -> CIV' => ['colis_rapide_import', 'Agence France', 'Siege Abidjan', null, 'CA-FR'],
            'Colis Rapide: SEN -> CIV' => ['colis_rapide_import', 'Agence Sénégal', 'Siege Abidjan', null, 'CA-SN'],
            'Colis Rapide: CIV -> SEN' => ['colis_rapide_export', 'Siege Abidjan', 'Agence Sénégal', null, 'CA-IS'],
            'Colis Rapide: CIV -> CAN' => ['colis_rapide_export', 'Siege Abidjan', 'Agence Canada', null, 'CA-IC'],
            'Colis Rapide: CAN -> CIV' => ['colis_rapide_import', 'Agence Canada', 'Siege Abidjan', null, 'CA-CC'],

            // Express / Colis Rapide with Trajet override
            'Colis Rapide: Trajet CIV_SEN' => ['colis_rapide_export', null, null, 'CIV_SEN', 'CA-IS'],
            'Colis Rapide: Trajet SEN_CIV' => ['colis_rapide_import', null, null, 'SEN_CIV', 'CA-SN'],
        ];
    }
}
