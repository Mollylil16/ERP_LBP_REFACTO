import { MigrationInterface, QueryRunner } from 'typeorm';

export class CreateCallCenterInbox1743200000000 implements MigrationInterface {
  name = 'CreateCallCenterInbox1743200000000';

  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(`
      CREATE TABLE IF NOT EXISTS "callcenter_conversations" (
        "id" SERIAL NOT NULL,
        "channel" varchar(20) NOT NULL,
        "customer_phone" varchar(40) NOT NULL,
        "callcenter_phone" varchar(40),
        "client_id" integer,
        "last_facture_id" integer,
        "last_litige_id" integer,
        "unread_count" integer NOT NULL DEFAULT 0,
        "last_message_at" TIMESTAMP,
        "created_at" TIMESTAMP NOT NULL DEFAULT now(),
        "updated_at" TIMESTAMP NOT NULL DEFAULT now(),
        CONSTRAINT "PK_callcenter_conversations_id" PRIMARY KEY ("id")
      );
    `);

    await queryRunner.query(`
      CREATE UNIQUE INDEX IF NOT EXISTS "IDX_callcenter_conversation_channel_customer"
      ON "callcenter_conversations" ("channel", "customer_phone");
    `);

    await queryRunner.query(`
      CREATE TABLE IF NOT EXISTS "callcenter_messages" (
        "id" SERIAL NOT NULL,
        "conversation_id" integer NOT NULL,
        "channel" varchar(20) NOT NULL,
        "direction" varchar(5) NOT NULL,
        "from_phone" varchar(40) NOT NULL,
        "to_phone" varchar(40) NOT NULL,
        "message" text NOT NULL,
        "provider" varchar(50),
        "provider_message_id" varchar(100),
        "raw_payload" jsonb,
        "created_at" TIMESTAMP NOT NULL DEFAULT now(),
        CONSTRAINT "PK_callcenter_messages_id" PRIMARY KEY ("id")
      );
    `);

    await queryRunner.query(`
      CREATE INDEX IF NOT EXISTS "IDX_callcenter_messages_conversation_created"
      ON "callcenter_messages" ("conversation_id", "created_at");
    `);

    await queryRunner.query(`
      CREATE INDEX IF NOT EXISTS "IDX_callcenter_messages_provider_id"
      ON "callcenter_messages" ("provider", "provider_message_id");
    `);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(
      `DROP INDEX IF EXISTS "IDX_callcenter_messages_provider_id";`,
    );
    await queryRunner.query(
      `DROP INDEX IF EXISTS "IDX_callcenter_messages_conversation_created";`,
    );
    await queryRunner.query(`DROP TABLE IF EXISTS "callcenter_messages";`);
    await queryRunner.query(
      `DROP INDEX IF EXISTS "IDX_callcenter_conversation_channel_customer";`,
    );
    await queryRunner.query(`DROP TABLE IF EXISTS "callcenter_conversations";`);
  }
}
