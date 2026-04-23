import {
  IsEmail,
  IsIn,
  IsOptional,
  IsString,
  MaxLength,
} from 'class-validator';

export class UpdateGroupeurDto {
  @IsOptional()
  @IsString()
  @MaxLength(150)
  raison_sociale?: string;

  @IsOptional()
  @IsString()
  @MaxLength(150)
  nom_commercial?: string;

  @IsOptional()
  @IsIn(['groupeur', 'grossiste', 'mixte'])
  type?: 'groupeur' | 'grossiste' | 'mixte';

  @IsOptional()
  @IsString()
  @MaxLength(80)
  pays?: string;

  @IsOptional()
  @IsString()
  @MaxLength(80)
  ville?: string;

  @IsOptional()
  @IsString()
  adresse?: string;

  @IsOptional()
  @IsString()
  @MaxLength(30)
  telephone?: string;

  @IsOptional()
  @IsEmail()
  @MaxLength(120)
  email_contact?: string;

  @IsOptional()
  @IsString()
  @MaxLength(60)
  numero_registre?: string;

  @IsOptional()
  @IsString()
  corridors?: string;

  @IsOptional()
  @IsString()
  modes_transport?: string;
}
