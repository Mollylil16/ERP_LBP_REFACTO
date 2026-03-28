import { MigrationInterface, QueryRunner } from 'typeorm';

/**
 * Aligne la table audit_logs avec l'entité TypeORM (colonnes snake_case, types, jsonb).
 * Idempotent : gère l'ancien schéma (entity_type, changes, user_id int) et d'éventuelles colonnes camelCase (sync).
 */
export class AlignAuditLogsSchema1743310000000 implements MigrationInterface {
  name = 'AlignAuditLogsSchema1743310000000';

  public async up(queryRunner: QueryRunner): Promise<void> {
    const hasTable = await queryRunner.hasTable('audit_logs');
    if (!hasTable) {
      return;
    }

    await queryRunner.query(`DROP INDEX IF EXISTS idx_audit_entity`);
    await queryRunner.query(`DROP INDEX IF EXISTS idx_audit_user`);
    await queryRunner.query(`DROP INDEX IF EXISTS idx_audit_date`);
    await queryRunner.query(`DROP INDEX IF EXISTS idx_audit_action`);

    // Sync TypeORM (camelCase) → snake_case
    if (
      (await queryRunner.hasColumn('audit_logs', 'userId')) &&
      !(await queryRunner.hasColumn('audit_logs', 'user_id'))
    ) {
      await queryRunner.query(
        `ALTER TABLE "audit_logs" RENAME COLUMN "userId" TO "user_id"`,
      );
    }
    if (
      (await queryRunner.hasColumn('audit_logs', 'entityId')) &&
      !(await queryRunner.hasColumn('audit_logs', 'entity_id'))
    ) {
      await queryRunner.query(
        `ALTER TABLE "audit_logs" RENAME COLUMN "entityId" TO "entity_id"`,
      );
    }
    if (
      (await queryRunner.hasColumn('audit_logs', 'ipAddress')) &&
      !(await queryRunner.hasColumn('audit_logs', 'ip_address'))
    ) {
      await queryRunner.query(
        `ALTER TABLE "audit_logs" RENAME COLUMN "ipAddress" TO "ip_address"`,
      );
    }
    if (
      (await queryRunner.hasColumn('audit_logs', 'userAgent')) &&
      !(await queryRunner.hasColumn('audit_logs', 'user_agent'))
    ) {
      await queryRunner.query(
        `ALTER TABLE "audit_logs" RENAME COLUMN "userAgent" TO "user_agent"`,
      );
    }
    if (
      (await queryRunner.hasColumn('audit_logs', 'createdAt')) &&
      !(await queryRunner.hasColumn('audit_logs', 'created_at'))
    ) {
      await queryRunner.query(
        `ALTER TABLE "audit_logs" RENAME COLUMN "createdAt" TO "created_at"`,
      );
    }

    // Ancienne migration : entity_type → entity
    if (
      (await queryRunner.hasColumn('audit_logs', 'entity_type')) &&
      !(await queryRunner.hasColumn('audit_logs', 'entity'))
    ) {
      await queryRunner.query(
        `ALTER TABLE "audit_logs" RENAME COLUMN "entity_type" TO "entity"`,
      );
    }

    // changes (text) → details (jsonb)
    const hasChanges = await queryRunner.hasColumn('audit_logs', 'changes');
    const hasDetails = await queryRunner.hasColumn('audit_logs', 'details');
    if (hasChanges && !hasDetails) {
      await queryRunner.query(
        `ALTER TABLE "audit_logs" ADD COLUMN "details" jsonb`,
      );
      await queryRunner.query(`
        UPDATE "audit_logs"
        SET "details" = jsonb_build_object('legacy', "changes")
        WHERE "changes" IS NOT NULL
      `);
      await queryRunner.query(`ALTER TABLE "audit_logs" DROP COLUMN "changes"`);
    } else if (!hasDetails) {
      await queryRunner.query(
        `ALTER TABLE "audit_logs" ADD COLUMN IF NOT EXISTS "details" jsonb`,
      );
    }

    const userCol: { data_type: string }[] = await queryRunner.query(`
      SELECT data_type FROM information_schema.columns
      WHERE table_schema = 'public' AND table_name = 'audit_logs' AND column_name = 'user_id'
    `);
    if (userCol.length && userCol[0].data_type === 'integer') {
      await queryRunner.query(`
        ALTER TABLE "audit_logs" ALTER COLUMN "user_id" TYPE varchar(64) USING "user_id"::text
      `);
    }

    const entCol: { data_type: string }[] = await queryRunner.query(`
      SELECT data_type FROM information_schema.columns
      WHERE table_schema = 'public' AND table_name = 'audit_logs' AND column_name = 'entity_id'
    `);
    if (entCol.length && entCol[0].data_type === 'integer') {
      await queryRunner.query(`
        ALTER TABLE "audit_logs" ALTER COLUMN "entity_id" TYPE varchar(64) USING "entity_id"::text
      `);
    }

    if (!(await queryRunner.hasColumn('audit_logs', 'duration'))) {
      await queryRunner.query(
        `ALTER TABLE "audit_logs" ADD COLUMN "duration" integer`,
      );
    }

    if (!(await queryRunner.hasColumn('audit_logs', 'status'))) {
      await queryRunner.query(
        `ALTER TABLE "audit_logs" ADD COLUMN "status" varchar(32)`,
      );
    }
    await queryRunner.query(
      `UPDATE "audit_logs" SET "status" = 'success' WHERE "status" IS NULL`,
    );
    await queryRunner.query(
      `ALTER TABLE "audit_logs" ALTER COLUMN "status" SET NOT NULL`,
    );

    await queryRunner.query(
      `CREATE INDEX IF NOT EXISTS "idx_audit_user" ON "audit_logs" ("user_id")`,
    );
    await queryRunner.query(
      `CREATE INDEX IF NOT EXISTS "idx_audit_entity" ON "audit_logs" ("entity", "entity_id")`,
    );
    await queryRunner.query(
      `CREATE INDEX IF NOT EXISTS "idx_audit_date" ON "audit_logs" ("created_at")`,
    );
    await queryRunner.query(
      `CREATE INDEX IF NOT EXISTS "idx_audit_action" ON "audit_logs" ("action")`,
    );
  }

  public async down(): Promise<void> {
    // Non réversible sans perte (renommages + types + migration changes)
  }
}
