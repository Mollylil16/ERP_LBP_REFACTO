import {
  IsEmail,
  IsIn,
  IsOptional,
  IsString,
  MaxLength,
} from 'class-validator';

export class CreateGroupeurDto {
  @IsString()
  @MaxLength(20)
  code: string;

  @IsString()
  @MaxLength(150)
  raison_sociale: string;

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

  /** JSON string : ['Chine→CI', ...] */
  @IsOptional()
  @IsString()
  corridors?: string;

  /** JSON string : ['maritime', ...] */
  @IsOptional()
  @IsString()
  modes_transport?: string;

  // Compte utilisateur lié (optionnel) — sinon création d’un user dédiée sera faite côté service
  @IsOptional()
  user_id?: number;
}
