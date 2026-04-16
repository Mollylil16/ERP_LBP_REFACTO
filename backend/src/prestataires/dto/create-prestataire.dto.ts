import { IsBoolean, IsEmail, IsOptional, IsString, MaxLength } from 'class-validator';

export class CreatePrestataireDto {
  @IsString()
  @MaxLength(200)
  nom: string;

  @IsOptional()
  @IsString()
  @MaxLength(100)
  pays?: string;

  @IsOptional()
  @IsBoolean()
  actif?: boolean;

  @IsOptional()
  @IsString()
  @MaxLength(150)
  contact_nom?: string;

  @IsOptional()
  @IsString()
  @MaxLength(50)
  contact_tel?: string;

  @IsOptional()
  @IsEmail()
  @MaxLength(200)
  contact_email?: string;
}

