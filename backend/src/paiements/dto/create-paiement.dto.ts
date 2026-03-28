import { ApiProperty } from '@nestjs/swagger';
import {
  IsNumber,
  IsEnum,
  IsOptional,
  IsString,
  IsDateString,
  ValidateIf,
} from 'class-validator';
import { PaymentMode } from '../entities/paiement.entity';

export class CreatePaiementDto {
  @ApiProperty({ example: 1, required: false })
  @ValidateIf((o) => !o.ref_colis) // id_facture obligatoire seulement si ref_colis absent
  @IsNumber()
  id_facture?: number;

  @ApiProperty({ example: 'LBP-0226-007', required: false })
  @ValidateIf((o) => !o.id_facture) // ref_colis obligatoire seulement si id_facture absent
  @IsString()
  @IsOptional()
  ref_colis?: string;

  @ApiProperty({ example: 5000 })
  @IsNumber()
  montant: number;

  @ApiProperty({ example: 0 })
  @IsOptional()
  @IsNumber()
  monnaie_rendue?: number;

  @ApiProperty({ enum: PaymentMode, example: PaymentMode.ESPECES })
  @IsEnum(PaymentMode)
  mode_paiement: PaymentMode;

  @ApiProperty({ example: 'CHQ-123456', required: false })
  @IsOptional()
  @IsString()
  reference_paiement?: string;

  @ApiProperty({
    example: 'CHQ-123456',
    required: false,
    description: 'Alias for reference_paiement',
  })
  @IsOptional()
  @IsString()
  reference?: string; // alias frontend → mappé vers reference_paiement dans le service

  @ApiProperty({ example: '2024-01-29' })
  @IsDateString()
  date_paiement: string;
}
