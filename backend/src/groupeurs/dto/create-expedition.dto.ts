import { IsIn, IsOptional, IsString, MaxLength } from 'class-validator';

export class CreateExpeditionDto {
  @IsString()
  @MaxLength(30)
  numero_expedition: string;

  @IsOptional()
  devis_id?: string;

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
  poids_kg?: number;

  @IsOptional()
  volume_m3?: number;

  @IsOptional()
  @IsString()
  numero_conteneur?: string;

  @IsOptional()
  @IsString()
  taille_conteneur?: string;

  @IsOptional()
  @IsString()
  date_depart_prevu?: string;

  @IsOptional()
  @IsString()
  date_arrivee_prevu?: string;

  @IsOptional()
  @IsString()
  date_depart_reel?: string;

  @IsOptional()
  @IsString()
  date_arrivee_reelle?: string;

  @IsOptional()
  @IsString()
  @MaxLength(100)
  armateur?: string;

  @IsOptional()
  @IsString()
  @MaxLength(60)
  numero_bl_master?: string;

  @IsOptional()
  @IsString()
  @MaxLength(60)
  numero_bl_house?: string;

  @IsOptional()
  @IsIn([
    'en_preparation',
    'merchandise_recue',
    'en_transit',
    'arrive_port',
    'en_dedouanement',
    'livre',
    'litige',
  ])
  statut?: string;

  @IsOptional()
  @IsString()
  notes?: string;
}
