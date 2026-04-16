import {
  IsDateString,
  IsIn,
  IsNumber,
  IsOptional,
  IsString,
  MaxLength,
  Min,
} from 'class-validator';

export class CreateReglementPrestataireDto {
  @IsDateString()
  date_reglement: string;

  @IsIn(['ESPECE', 'VIREMENT', 'CHEQUE', 'MOBILE', 'AUTRE'])
  mode_reglement: 'ESPECE' | 'VIREMENT' | 'CHEQUE' | 'MOBILE' | 'AUTRE';

  @IsNumber()
  @Min(0.01)
  montant: number;

  @IsOptional()
  @IsString()
  @MaxLength(150)
  reference?: string;

  @IsOptional()
  @IsString()
  note?: string;

  @IsOptional()
  @IsIn(['CAISSE_PRINCIPALE', 'AGENCE'])
  origine_fonds?: 'CAISSE_PRINCIPALE' | 'AGENCE';
}

