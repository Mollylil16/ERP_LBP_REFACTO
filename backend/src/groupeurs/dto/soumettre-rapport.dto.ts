import { IsIn, IsOptional, IsString } from 'class-validator';

export class SoumettreRapportGroupeurDto {
  @IsIn([
    'activite_groupeur',
    'financier',
    'expeditions',
    'anomalies',
    'performance_globale',
  ])
  type: string;

  @IsOptional()
  @IsIn(['jour', 'semaine', 'mois', 'trimestre', 'annee'])
  periode?: string;

  @IsOptional()
  @IsString()
  date_debut?: string;

  @IsOptional()
  @IsString()
  date_fin?: string;

  /** null/undefined = tous les groupeurs */
  @IsOptional()
  @IsString()
  groupeur_id?: string;

  @IsOptional()
  @IsString()
  commentaire?: string;
}
