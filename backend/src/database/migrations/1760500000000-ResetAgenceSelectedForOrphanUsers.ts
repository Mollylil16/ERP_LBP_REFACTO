import { MigrationInterface, QueryRunner } from 'typeorm';

/**
 * Corrige les comptes "corrompus" : `agence_selected = true` alors qu'aucune agence n'est assignée.
 * Contexte : ancien calcul (front/back) assimilait à tort certains profils à un accès "global",
 * ce qui a pu laisser des utilisateurs entrer dans l'app sans choisir leur agence.
 *
 * Règle : on remet `agence_selected=false` uniquement si :
 * - agence_selected = true
 * - id_agence IS NULL (pas d'agence assignée)
 * - pas un profil siège (DG/ADMIN/ASSISTANT_DG) et pas multi-agences.
 */
export class ResetAgenceSelectedForOrphanUsers1760500000000
  implements MigrationInterface
{
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(`
      UPDATE "lbp_users" AS u
      SET "agence_selected" = false
      WHERE
        u."agence_selected" = true
        AND u."id_agence" IS NULL
        AND COALESCE(u."peut_voir_toutes_agences", false) = false
        AND COALESCE(u."code_acces", 0) <> 2
        AND COALESCE(u."role"::text, '') NOT IN ('DIRECTEUR', 'ADMIN', 'ASSISTANT_DG')
    `);
  }

  public async down(): Promise<void> {
    /* Données métier : pas de retour automatique fiable */
  }
}

