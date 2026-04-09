import { MigrationInterface, QueryRunner } from 'typeorm';

export class CreateAuthRefreshTokens1744000000000
  implements MigrationInterface
{
  name = 'CreateAuthRefreshTokens1744000000000';

  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(`
      CREATE TABLE IF NOT EXISTS auth_refresh_tokens (
        id SERIAL PRIMARY KEY,
        user_id INTEGER NOT NULL REFERENCES lbp_users(id) ON DELETE CASCADE,
        token_id VARCHAR(64) NOT NULL UNIQUE,
        token_hash TEXT NOT NULL,
        expires_at TIMESTAMPTZ NOT NULL,
        revoked_at TIMESTAMPTZ NULL,
        created_ip VARCHAR(45) NULL,
        created_user_agent VARCHAR(512) NULL,
        rotated_from_ip VARCHAR(45) NULL,
        rotated_from_user_agent VARCHAR(512) NULL,
        created_at TIMESTAMPTZ NOT NULL DEFAULT now()
      );
    `);

    await queryRunner.query(
      `CREATE INDEX IF NOT EXISTS idx_auth_refresh_tokens_user_id ON auth_refresh_tokens(user_id);`,
    );
    await queryRunner.query(
      `CREATE INDEX IF NOT EXISTS idx_auth_refresh_tokens_expires_at ON auth_refresh_tokens(expires_at);`,
    );
    await queryRunner.query(
      `CREATE INDEX IF NOT EXISTS idx_auth_refresh_tokens_revoked_at ON auth_refresh_tokens(revoked_at);`,
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(`DROP TABLE IF EXISTS auth_refresh_tokens;`);
  }
}

