import { MigrationInterface, QueryRunner } from 'typeorm';

export class AddCdcSecurityFields1762600000000 implements MigrationInterface {
  name = 'AddCdcSecurityFields1762600000000';

  async up(queryRunner: QueryRunner): Promise<void> {
    // ── 1. Champ VIH/handicap chiffré sur les employés (Art. 4 CDT CI) ──────
    await queryRunner.query(`
      DO $$ BEGIN
        IF NOT EXISTS (
          SELECT 1 FROM information_schema.columns
          WHERE table_name='rh_employes' AND column_name='situation_medicale_enc'
        ) THEN
          ALTER TABLE "rh_employes" ADD COLUMN "situation_medicale_enc" TEXT;
        END IF;
      END $$;
    `);

    // ── 2. Signature électronique sur les contrats (CDC §6.3) ─────────────
    await queryRunner.query(`
      DO $$ BEGIN
        IF NOT EXISTS (
          SELECT 1 FROM information_schema.columns
          WHERE table_name='rh_contrats' AND column_name='signe_salarie_at'
        ) THEN
          ALTER TABLE "rh_contrats"
            ADD COLUMN "signe_salarie_at"  TIMESTAMP WITH TIME ZONE,
            ADD COLUMN "signe_rh_at"       TIMESTAMP WITH TIME ZONE,
            ADD COLUMN "signe_rh_user_id"  INTEGER,
            ADD COLUMN "signature_mode"    VARCHAR(20) DEFAULT 'PHYSIQUE',
            ADD COLUMN "document_signe_url" TEXT;
        END IF;
      END $$;
    `);

    // ── 3. MFA pour Admin/Paie (CDC Module 10) ────────────────────────────
    await queryRunner.query(`
      DO $$ BEGIN
        IF NOT EXISTS (
          SELECT 1 FROM information_schema.columns
          WHERE table_name='lbp_users' AND column_name='mfa_secret'
        ) THEN
          ALTER TABLE "lbp_users"
            ADD COLUMN "mfa_secret"   VARCHAR(64),
            ADD COLUMN "mfa_enabled"  BOOLEAN NOT NULL DEFAULT FALSE,
            ADD COLUMN "mfa_required" BOOLEAN NOT NULL DEFAULT FALSE;
        END IF;
      END $$;
    `);

    // Activer MFA_REQUIRED pour ADMIN et RESPONSABLE_RH
    // Cast ::text pour éviter l'erreur PostgreSQL 55P04 (enum value ajouté dans la même session)
    await queryRunner.query(`
      UPDATE "lbp_users" SET "mfa_required" = TRUE
      WHERE "role"::text IN ('ADMIN', 'RESPONSABLE_RH');
    `);
  }

  async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(`ALTER TABLE "lbp_users" DROP COLUMN IF EXISTS "mfa_required"`);
    await queryRunner.query(`ALTER TABLE "lbp_users" DROP COLUMN IF EXISTS "mfa_enabled"`);
    await queryRunner.query(`ALTER TABLE "lbp_users" DROP COLUMN IF EXISTS "mfa_secret"`);
    await queryRunner.query(`ALTER TABLE "rh_contrats" DROP COLUMN IF EXISTS "document_signe_url"`);
    await queryRunner.query(`ALTER TABLE "rh_contrats" DROP COLUMN IF EXISTS "signature_mode"`);
    await queryRunner.query(`ALTER TABLE "rh_contrats" DROP COLUMN IF EXISTS "signe_rh_user_id"`);
    await queryRunner.query(`ALTER TABLE "rh_contrats" DROP COLUMN IF EXISTS "signe_rh_at"`);
    await queryRunner.query(`ALTER TABLE "rh_contrats" DROP COLUMN IF EXISTS "signe_salarie_at"`);
    await queryRunner.query(`ALTER TABLE "rh_employes" DROP COLUMN IF EXISTS "situation_medicale_enc"`);
  }
}
