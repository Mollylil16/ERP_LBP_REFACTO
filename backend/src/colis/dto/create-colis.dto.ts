import { ApiProperty } from '@nestjs/swagger';
import {
  IsNotEmpty,
  IsString,
  IsOptional,
  IsDateString,
  IsArray,
  ValidateNested,
  IsNumber,
  Min,
  IsPositive,
  ArrayMinSize,
  MaxLength,
} from 'class-validator';
import { Type } from 'class-transformer';

export class CreateMarchandiseDto {
  @ApiProperty({ example: 'Vêtements' })
  @IsNotEmpty({ message: 'Le nom de la marchandise est obligatoire' })
  @IsString({
    message: 'Le nom de la marchandise doit être une chaîne de caractères',
  })
  @MaxLength(100, {
    message: 'Le nom de la marchandise ne peut pas dépasser 100 caractères',
  })
  nom_marchandise: string;

  @ApiProperty({ example: 2 })
  @IsNumber({}, { message: 'Le nombre de colis doit être un nombre' })
  @IsPositive({ message: 'Le nombre de colis doit être positif' })
  @Min(1, { message: 'Le nombre de colis doit être au minimum 1' })
  nbre_colis: number;

  @ApiProperty({ example: 10 })
  @IsNumber({}, { message: "Le nombre d'articles doit être un nombre" })
  @IsPositive({ message: "Le nombre d'articles doit être positif" })
  @Min(1, { message: "Le nombre d'articles doit être au minimum 1" })
  nbre_articles: number;

  @ApiProperty({ example: 15.5 })
  @IsNumber({}, { message: 'Le poids total doit être un nombre' })
  @IsPositive({ message: 'Le poids total doit être positif' })
  @Min(0.1, { message: 'Le poids total doit être au minimum 0.1 kg' })
  poids_total: number;

  @ApiProperty({ example: 5000 })
  @IsNumber({}, { message: 'Le prix unitaire doit être un nombre' })
  @IsPositive({ message: 'Le prix unitaire doit être positif' })
  @Min(1, { message: 'Le prix unitaire doit être au minimum 1 FCFA' })
  prix_unit: number;

  @ApiProperty({ example: 500 })
  @IsOptional()
  @IsNumber({}, { message: "Le prix d'emballage doit être un nombre" })
  @Min(0, { message: "Le prix d'emballage ne peut pas être négatif" })
  prix_emballage?: number;

  @ApiProperty({ example: 200 })
  @IsOptional()
  @IsNumber({}, { message: "Le prix d'assurance doit être un nombre" })
  @Min(0, { message: "Le prix d'assurance ne peut pas être négatif" })
  prix_assurance?: number;

  @ApiProperty({ example: 1000 })
  @IsOptional()
  @IsNumber({}, { message: "Le prix d'agence doit être un nombre" })
  @Min(0, { message: "Le prix d'agence ne peut pas être négatif" })
  prix_agence?: number;

  @ApiProperty({ example: 1, required: false })
  @IsOptional()
  @IsNumber({}, { message: "L'ID du tarif doit être un nombre" })
  id_tarif?: number;

  @ApiProperty({
    example: 'carton',
    required: false,
    description: "Type d'emballage : carton | sac | valise | bôrô",
  })
  @IsOptional()
  @IsString()
  type_emballage?: string;

  @ApiProperty({ example: 1, description: "Nombre d'emballages utilisés" })
  @IsNumber({}, { message: "Le nombre d'emballage doit être un nombre" })
  @IsPositive({ message: "Le nombre d'emballage doit être positif" })
  @Min(1, { message: "Le nombre d'emballage doit être au minimum 1" })
  nbre_emballage: number;
}

export class CreateColisDto {
  @ApiProperty({ example: 'Export Maritime' })
  @IsNotEmpty({ message: "Le trafic d'envoi est obligatoire" })
  @IsString({ message: "Le trafic d'envoi doit être une chaîne de caractères" })
  trafic_envoi: string;

  @ApiProperty({ example: 'groupage' })
  @IsNotEmpty({ message: "La forme d'envoi est obligatoire" })
  @IsString({ message: "La forme d'envoi doit être une chaîne de caractères" })
  forme_envoi: string;

  @ApiProperty({ example: 'DHL', required: false })
  @IsOptional()
  @IsString({ message: "Le mode d'envoi doit être une chaîne de caractères" })
  mode_envoi?: string;

  @ApiProperty({ example: '2024-01-29' })
  @IsDateString(
    {},
    { message: "La date d'envoi doit être au format ISO (YYYY-MM-DD)" },
  )
  date_envoi: string;

  @ApiProperty({
    example: 1,
    required: false,
    description:
      "Agence pour rattachement et préfixe de référence (LB-CI, LB-SEN, etc.).",
  })
  @IsOptional()
  @IsNumber({}, { message: "L'ID de l'agence doit être un nombre" })
  @IsPositive({ message: "L'ID de l'agence doit être positif" })
  id_agence?: number;

  @ApiProperty({ example: 1 }) // ID du client
  @IsNotEmpty({ message: "L'ID du client est obligatoire" })
  @IsNumber({}, { message: "L'ID du client doit être un nombre" })
  @IsPositive({ message: "L'ID du client doit être positif" })
  id_client: number;

  @ApiProperty({ example: 'Jean Dupont' })
  @IsNotEmpty({ message: 'Le nom du destinataire est obligatoire' })
  @IsString({
    message: 'Le nom du destinataire doit être une chaîne de caractères',
  })
  @MaxLength(100, {
    message: 'Le nom du destinataire ne peut pas dépasser 100 caractères',
  })
  nom_dest: string;

  @ApiProperty({ example: 'Paris' })
  @IsString({
    message: 'Le lieu de destination doit être une chaîne de caractères',
  })
  @IsOptional()
  lieu_dest?: string;

  @ApiProperty({ example: '+33123456789' })
  @IsString({
    message: 'Le téléphone du destinataire doit être une chaîne de caractères',
  })
  @IsOptional()
  tel_dest?: string;

  @ApiProperty({ example: 'jean@example.com' })
  @IsString({
    message: "L'email du destinataire doit être une chaîne de caractères",
  })
  @IsOptional()
  email_dest?: string;

  @ApiProperty({ type: [CreateMarchandiseDto] })
  @IsArray({ message: 'Les marchandises doivent être un tableau' })
  @ArrayMinSize(1, { message: 'Au moins une marchandise est requise' })
  @ValidateNested({ each: true })
  @Type(() => CreateMarchandiseDto)
  marchandises: CreateMarchandiseDto[];

  @ApiProperty({
    example: false,
    required: false,
    description: 'Livraison à domicile demandée',
  })
  @IsOptional()
  livraison?: boolean;

  @ApiProperty({ example: 'Marie Durant', required: false })
  @IsOptional()
  @IsString({
    message: 'Le nom du récupérateur doit être une chaîne de caractères',
  })
  nom_recup?: string;

  @ApiProperty({ example: 'Lyon', required: false })
  @IsOptional()
  @IsString({
    message: "L'adresse du récupérateur doit être une chaîne de caractères",
  })
  adresse_recup?: string;

  @ApiProperty({ example: '+33987654321', required: false })
  @IsOptional()
  @IsString({
    message: 'Le téléphone du récupérateur doit être une chaîne de caractères',
  })
  tel_recup?: string;

  @ApiProperty({ example: 'marie@example.com', required: false })
  @IsOptional()
  @IsString({
    message: "L'email du récupérateur doit être une chaîne de caractères",
  })
  email_recup?: string;
}
