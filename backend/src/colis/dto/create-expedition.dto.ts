import { IsEnum, IsInt, IsOptional, IsString } from 'class-validator';
import { ExpeditionType } from '../entities/expedition.entity';

export class CreateExpeditionDto {
  @IsInt()
  id_agence_destination: number;

  @IsEnum(ExpeditionType)
  type: ExpeditionType;

  @IsString()
  @IsOptional()
  numero_container?: string;

  @IsString()
  @IsOptional()
  compagnie_transport?: string;

  @IsOptional()
  date_arrivee_prevue?: Date;
}
