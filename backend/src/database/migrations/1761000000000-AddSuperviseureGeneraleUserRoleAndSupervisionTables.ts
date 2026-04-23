import { MigrationInterface, QueryRunner } from 'typeorm';

/**
 * Rôle SUPERVISEURE_GENERALE + tables métier « supervision réseau »
 * (signalements, demandes de justification, annotations, rapports au directeur).
 */
export class AddSuperviseureGeneraleUserRoleAndSupervisionTables1761000000000
  implements MigrationInterface
{
  name = 'AddSuperviseureGeneraleUserRoleAndSupervisionTables1761000000000';

  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(`
      DO $migration$
      BEGIN
        IF NOT EXISTS (
          SELECT 1
          FROM pg_catalog.pg_enum e
          INNER JOIN pg_catalog.pg_type t ON e.enumtypid = t.oid
          WHERE t.typname = 'lbp_users_role_enum'
            AND e.enumlabel = 'SUPERVISEURE_GENERALE'
        ) THEN
          ALTER TYPE "public"."lbp_users_role_enum" ADD VALUE 'SUPERVISEURE_GENERALE';
        END IF;
      END
      $migration$;
    `);

    await queryRunner.query(`
      INSERT INTO "lbp_roles" ("code", "libelle", "description", "niveau_hierarchique", "est_actif", "created_at", "updated_at")
      SELECT
        'SUPERVISEURE_GENERALE',
        'Superviseure générale',
        'Supervision réseau — lecture des activités, pilotage, rapports au directeur (pas de modification des données opérationnelles).',
        2,
        true,
        NOW(),
        NOW()
      WHERE NOT EXISTS (SELECT 1 FROM "lbp_roles" WHERE "code" = 'SUPERVISEURE_GENERALE')
    `);

    await queryRunner.query(`
      CREATE TABLE IF NOT EXISTS "lbp_supervision_signalements" (
        "id" SERIAL NOT NULL,
        "id_agence" integer,
        "id_auteur" integer NOT NULL,
        "type" character varying(80) NOT NULL,
        "description" text,
        "gravite" character varying(20) NOT NULL DEFAULT 'moyen',
        "statut" character varying(20) NOT NULL DEFAULT 'ouvert',
        "created_at" TIMESTAMP NOT NULL DEFAULT now(),
        "updated_at" TIMESTAMP NOT NULL DEFAULT now(),
        CONSTRAINT "PK_lbp_supervision_signalements" PRIMARY KEY ("id")
      );
    `);
    await queryRunner.query(`
      ALTER TABLE "lbp_supervision_signalements"
        ADD CONSTRAINT "FK_signalement_agence" FOREIGN KEY ("id_agence") REFERENCES "agences"("id") ON DELETE SET NULL;
    `);
    await queryRunner.query(`
      ALTER TABLE "lbp_supervision_signalements"
        ADD CONSTRAINT "FK_signalement_auteur" FOREIGN KEY ("id_auteur") REFERENCES "lbp_users"("id") ON DELETE CASCADE;
    `);

    await queryRunner.query(`
      CREATE TABLE IF NOT EXISTS "lbp_supervision_demandes_justification" (
        "id" SERIAL NOT NULL,
        "id_demandeur" integer NOT NULL,
        "id_destinataire" integer,
        "id_agence" integer,
        "id_operation" character varying(64),
        "motif" text NOT NULL,
        "statut" character varying(20) NOT NULL DEFAULT 'en_attente',
        "reponse" text,
        "created_at" TIMESTAMP NOT NULL DEFAULT now(),
        "updated_at" TIMESTAMP NOT NULL DEFAULT now(),
        CONSTRAINT "PK_lbp_supervision_dj" PRIMARY KEY ("id")
      );
    `);
    await queryRunner.query(`
      ALTER TABLE "lbp_supervision_demandes_justification"
        ADD CONSTRAINT "FK_dj_demandeur" FOREIGN KEY ("id_demandeur") REFERENCES "lbp_users"("id") ON DELETE CASCADE;
    `);
    await queryRunner.query(`
      ALTER TABLE "lbp_supervision_demandes_justification"
        ADD CONSTRAINT "FK_dj_destinataire" FOREIGN KEY ("id_destinataire") REFERENCES "lbp_users"("id") ON DELETE SET NULL;
    `);
    await queryRunner.query(`
      ALTER TABLE "lbp_supervision_demandes_justification"
        ADD CONSTRAINT "FK_dj_agence" FOREIGN KEY ("id_agence") REFERENCES "agences"("id") ON DELETE SET NULL;
    `);

    await queryRunner.query(`
      CREATE TABLE IF NOT EXISTS "lbp_supervision_annotations" (
        "id" SERIAL NOT NULL,
        "id_auteur" integer NOT NULL,
        "cible" character varying(40) NOT NULL DEFAULT 'operation',
        "cible_id" character varying(64) NOT NULL,
        "contenu" text NOT NULL,
        "visibilite" character varying(20) NOT NULL DEFAULT 'direction',
        "created_at" TIMESTAMP NOT NULL DEFAULT now(),
        "updated_at" TIMESTAMP NOT NULL DEFAULT now(),
        CONSTRAINT "PK_lbp_supervision_annotations" PRIMARY KEY ("id")
      );
    `);
    await queryRunner.query(`
      ALTER TABLE "lbp_supervision_annotations"
        ADD CONSTRAINT "FK_ann_auteur" FOREIGN KEY ("id_auteur") REFERENCES "lbp_users"("id") ON DELETE CASCADE;
    `);

    await queryRunner.query(`
      CREATE TABLE IF NOT EXISTS "lbp_supervision_rapports" (
        "id" SERIAL NOT NULL,
        "id_auteur" integer NOT NULL,
        "type" character varying(50) NOT NULL,
        "periode" character varying(20) NOT NULL,
        "id_agence" integer,
        "date_debut" date,
        "date_fin" date,
        "commentaire" text,
        "statut_lecture" character varying(20) NOT NULL DEFAULT 'non_lu',
        "soumis_a" integer,
        "created_at" TIMESTAMP NOT NULL DEFAULT now(),
        "updated_at" TIMESTAMP NOT NULL DEFAULT now(),
        CONSTRAINT "PK_lbp_supervision_rapports" PRIMARY KEY ("id")
      );
    `);
    await queryRunner.query(`
      ALTER TABLE "lbp_supervision_rapports"
        ADD CONSTRAINT "FK_rapport_auteur" FOREIGN KEY ("id_auteur") REFERENCES "lbp_users"("id") ON DELETE CASCADE;
    `);
    await queryRunner.query(`
      ALTER TABLE "lbp_supervision_rapports"
        ADD CONSTRAINT "FK_rapport_agence" FOREIGN KEY ("id_agence") REFERENCES "agences"("id") ON DELETE SET NULL;
    `);
    await queryRunner.query(`
      ALTER TABLE "lbp_supervision_rapports"
        ADD CONSTRAINT "FK_rapport_soumis" FOREIGN KEY ("soumis_a") REFERENCES "lbp_users"("id") ON DELETE SET NULL;
    `);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(
      `DROP TABLE IF EXISTS "lbp_supervision_rapports"`,
    );
    await queryRunner.query(
      `DROP TABLE IF EXISTS "lbp_supervision_annotations"`,
    );
    await queryRunner.query(
      `DROP TABLE IF EXISTS "lbp_supervision_demandes_justification"`,
    );
    await queryRunner.query(
      `DROP TABLE IF EXISTS "lbp_supervision_signalements"`,
    );
    /* enum / rôle : non supprimés en down (production) */
  }
}
