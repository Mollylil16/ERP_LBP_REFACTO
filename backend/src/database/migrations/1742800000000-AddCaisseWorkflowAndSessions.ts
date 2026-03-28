import { MigrationInterface, QueryRunner } from 'typeorm';

export class AddCaisseWorkflowAndSessions1742800000000 implements MigrationInterface {
  name = 'AddCaisseWorkflowAndSessions1742800000000';

  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(`
            DO $$ BEGIN
                CREATE TYPE "public"."lbp_caisse_sessions_status_enum" AS ENUM('OPEN', 'CLOSED');
            EXCEPTION
                WHEN duplicate_object THEN null;
            END $$;
        `);

    await queryRunner.query(`
            CREATE TABLE IF NOT EXISTS "lbp_caisse_sessions" (
                "id" SERIAL NOT NULL,
                "status" "public"."lbp_caisse_sessions_status_enum" NOT NULL DEFAULT 'OPEN',
                "date_journee" date NOT NULL,
                "solde_ouverture_theorique" numeric(12,2) NOT NULL DEFAULT '0',
                "solde_ouverture_reel" numeric(12,2) NOT NULL DEFAULT '0',
                "solde_fermeture_theorique" numeric(12,2),
                "solde_fermeture_reel" numeric(12,2),
                "ecart_ouverture" numeric(12,2),
                "ecart_fermeture" numeric(12,2),
                "opened_by" character varying(100),
                "closed_by" character varying(100),
                "note_ouverture" text,
                "note_fermeture" text,
                "created_at" TIMESTAMP NOT NULL DEFAULT now(),
                "updated_at" TIMESTAMP NOT NULL DEFAULT now(),
                "id_caisse" integer NOT NULL,
                CONSTRAINT "PK_lbp_caisse_sessions_id" PRIMARY KEY ("id")
            )
        `);

    await queryRunner.query(`
            DO $$ BEGIN
                ALTER TABLE "lbp_caisse_sessions"
                ADD CONSTRAINT "FK_lbp_caisse_sessions_id_caisse"
                FOREIGN KEY ("id_caisse") REFERENCES "lbp_caisses"("id")
                ON DELETE NO ACTION ON UPDATE NO ACTION;
            EXCEPTION
                WHEN duplicate_object THEN null;
            END $$;
        `);

    await queryRunner.query(`
            DO $$ BEGIN
                CREATE TYPE "public"."lbp_caisse_mouvement_workflows_mouvement_type_enum" AS ENUM(
                    'APPRO', 'DECAISSEMENT', 'ENTREE_CHEQUE', 'ENTREE_ESPECE', 'ENTREE_VIREMENT'
                );
            EXCEPTION
                WHEN duplicate_object THEN null;
            END $$;
        `);

    await queryRunner.query(`
            DO $$ BEGIN
                CREATE TYPE "public"."lbp_caisse_mouvement_workflows_status_enum" AS ENUM(
                    'DRAFT', 'SUBMITTED', 'VALIDATED', 'REJECTED'
                );
            EXCEPTION
                WHEN duplicate_object THEN null;
            END $$;
        `);

    await queryRunner.query(`
            CREATE TABLE IF NOT EXISTS "lbp_caisse_mouvement_workflows" (
                "id" SERIAL NOT NULL,
                "mouvement_id" integer NOT NULL,
                "mouvement_type" "public"."lbp_caisse_mouvement_workflows_mouvement_type_enum" NOT NULL,
                "status" "public"."lbp_caisse_mouvement_workflows_status_enum" NOT NULL DEFAULT 'DRAFT',
                "validation_level_required" integer NOT NULL DEFAULT '1',
                "validation_level_current" integer NOT NULL DEFAULT '0',
                "submitted_by" character varying(100),
                "submitted_at" TIMESTAMP,
                "approved_by" character varying(100),
                "approved_at" TIMESTAMP,
                "rejection_reason" text,
                "justificatif_url" character varying(500),
                "created_at" TIMESTAMP NOT NULL DEFAULT now(),
                "updated_at" TIMESTAMP NOT NULL DEFAULT now(),
                CONSTRAINT "PK_lbp_caisse_mouvement_workflows_id" PRIMARY KEY ("id"),
                CONSTRAINT "UQ_lbp_caisse_mouvement_workflows_mouvement_id" UNIQUE ("mouvement_id")
            )
        `);

    await queryRunner.query(`
            CREATE TABLE IF NOT EXISTS "lbp_caisse_audit_logs" (
                "id" SERIAL NOT NULL,
                "action" character varying(100) NOT NULL,
                "mouvement_id" integer,
                "session_id" integer,
                "actor_username" character varying(100),
                "before_data" jsonb,
                "after_data" jsonb,
                "ip_address" character varying(45),
                "created_at" TIMESTAMP NOT NULL DEFAULT now(),
                CONSTRAINT "PK_lbp_caisse_audit_logs_id" PRIMARY KEY ("id")
            )
        `);

    await queryRunner.query(
      `CREATE INDEX IF NOT EXISTS "IDX_lbp_caisse_sessions_caisse_status" ON "lbp_caisse_sessions" ("id_caisse", "status")`,
    );
    await queryRunner.query(
      `CREATE INDEX IF NOT EXISTS "IDX_lbp_caisse_workflows_status" ON "lbp_caisse_mouvement_workflows" ("status")`,
    );
    await queryRunner.query(
      `CREATE INDEX IF NOT EXISTS "IDX_lbp_caisse_audit_created_at" ON "lbp_caisse_audit_logs" ("created_at")`,
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(
      `DROP INDEX IF EXISTS "IDX_lbp_caisse_audit_created_at"`,
    );
    await queryRunner.query(
      `DROP INDEX IF EXISTS "IDX_lbp_caisse_workflows_status"`,
    );
    await queryRunner.query(
      `DROP INDEX IF EXISTS "IDX_lbp_caisse_sessions_caisse_status"`,
    );
    await queryRunner.query(`DROP TABLE IF EXISTS "lbp_caisse_audit_logs"`);
    await queryRunner.query(
      `DROP TABLE IF EXISTS "lbp_caisse_mouvement_workflows"`,
    );
    await queryRunner.query(`DROP TABLE IF EXISTS "lbp_caisse_sessions"`);
    await queryRunner.query(
      `DROP TYPE IF EXISTS "public"."lbp_caisse_mouvement_workflows_status_enum"`,
    );
    await queryRunner.query(
      `DROP TYPE IF EXISTS "public"."lbp_caisse_mouvement_workflows_mouvement_type_enum"`,
    );
    await queryRunner.query(
      `DROP TYPE IF EXISTS "public"."lbp_caisse_sessions_status_enum"`,
    );
  }
}
