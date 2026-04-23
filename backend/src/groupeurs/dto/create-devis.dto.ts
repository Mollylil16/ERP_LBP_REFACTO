import {
  IsIn,
  IsNumber,
  IsOptional,
  IsString,
  MaxLength,
} from 'class-validator';

export class CreateDevisDto {
  @IsString()
  @MaxLength(30)
  numero: string;

  @IsString()
  @MaxLength(150)
  client_nom: string;

  @IsOptional()
  @IsString()
  @MaxLength(120)
  client_contact?: string;

  @IsString()
  @MaxLength(100)
  origine: string;

  @IsString()
  @MaxLength(100)
  destination: string;

  @IsOptional()
  @IsIn(['maritime', 'aerien', 'terrestre', 'multimodal'])
  mode_transport?: string;

  @IsOptional()
  @IsIn(['FCL', 'LCL'])
  type_chargement?: string;

  @IsOptional()
  @IsString()
  marchandise?: string;

  @IsOptional()
  @IsNumber()
  poids_kg?: number;

  @IsOptional()
  @IsNumber()
  volume_m3?: number;

  @IsOptional()
  @IsNumber()
  prix_propose?: number;

  @IsOptional()
  @IsString()
  @MaxLength(5)
  devise?: string;

  @IsOptional()
  @IsNumber()
  validite_jours?: number;

  @IsOptional()
  @IsIn(['brouillon', 'envoye', 'accepte', 'refuse', 'expire'])
  statut?: string;

  @IsOptional()
  @IsString()
  notes?: string;
}
