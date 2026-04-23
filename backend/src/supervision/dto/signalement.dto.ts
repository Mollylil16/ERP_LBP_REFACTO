import { IsInt, IsOptional, IsString, MinLength, MaxLength } from 'class-validator';
import { Type } from 'class-transformer';

export class SignalementDto {
  @IsOptional()
  @Type(() => Number)
  @IsInt()
  agenceId?: number;

  @IsString()
  @MinLength(2)
  @MaxLength(80)
  type: string;

  @IsString()
  @MinLength(3)
  description: string;

  @IsString()
  @MaxLength(20)
  gravite: 'faible' | 'moyen' | 'critique' | string;
}
