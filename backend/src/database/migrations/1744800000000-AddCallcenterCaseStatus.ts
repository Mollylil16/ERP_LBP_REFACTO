import { MigrationInterface, QueryRunner } from 'typeorm';

export class AddCallcenterCaseStatus1744800000000 implements MigrationInterface {
  name = 'AddCallcenterCaseStatus1744800000000';

  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(`
      ALTER TABLE "callcenter_conversations"
      ADD COLUMN IF NOT EXISTS "case_status" varchar(20) NOT NULL DEFAULT 'open';
    `);
    await queryRunner.query(`
      CREATE INDEX IF NOT EXISTS "IDX_callcenter_conversations_case_status"
      ON "callcenter_conversations" ("case_status");
    `);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(
      `DROP INDEX IF EXISTS "IDX_callcenter_conversations_case_status";`,
    );
    await queryRunner.query(`
      ALTER TABLE "callcenter_conversations" DROP COLUMN IF EXISTS "case_status";
    `);
  }
}
