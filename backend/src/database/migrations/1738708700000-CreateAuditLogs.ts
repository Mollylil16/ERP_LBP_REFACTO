import { MigrationInterface, QueryRunner, Table } from 'typeorm';

export class CreateAuditLogs1738708700000 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createTable(
      new Table({
        name: 'audit_logs',
        columns: [
          {
            name: 'id',
            type: 'int',
            isPrimary: true,
            isGenerated: true,
            generationStrategy: 'increment',
          },
          {
            name: 'user_id',
            type: 'int',
            isNullable: true,
          },
          {
            name: 'action',
            type: 'varchar',
            length: '100',
            comment: 'CREATE, UPDATE, DELETE, etc.',
          },
          {
            name: 'entity_type',
            type: 'varchar',
            length: '50',
            comment: 'colis, facture, paiement, etc.',
          },
          {
            name: 'entity_id',
            type: 'int',
            isNullable: true,
          },
          {
            name: 'changes',
            type: 'text',
            isNullable: true,
            comment: 'JSON des modifications',
          },
          {
            name: 'ip_address',
            type: 'varchar',
            length: '45',
            isNullable: true,
          },
          {
            name: 'user_agent',
            type: 'varchar',
            length: '255',
            isNullable: true,
          },
          {
            name: 'created_at',
            type: 'timestamp',
            default: 'CURRENT_TIMESTAMP',
          },
        ],
      }),
      true,
    );

    // Indexes robustes, compatibles avec ancien schéma camelCase
    const hasUserIdSnake = await queryRunner.hasColumn('audit_logs', 'user_id');
    const hasUserIdCamel = await queryRunner.hasColumn('audit_logs', 'userId');
    if (hasUserIdSnake) {
      await queryRunner.query(
        `CREATE INDEX IF NOT EXISTS idx_audit_user ON audit_logs(user_id)`,
      );
    } else if (hasUserIdCamel) {
      await queryRunner.query(
        `CREATE INDEX IF NOT EXISTS idx_audit_user ON audit_logs("userId")`,
      );
    }

    const hasEntityTypeSnake = await queryRunner.hasColumn(
      'audit_logs',
      'entity_type',
    );
    const hasEntityIdSnake = await queryRunner.hasColumn(
      'audit_logs',
      'entity_id',
    );
    const hasEntityCamel = await queryRunner.hasColumn('audit_logs', 'entity');
    const hasEntityIdCamel = await queryRunner.hasColumn(
      'audit_logs',
      'entityId',
    );
    if (hasEntityTypeSnake && hasEntityIdSnake) {
      await queryRunner.query(
        `CREATE INDEX IF NOT EXISTS idx_audit_entity ON audit_logs(entity_type, entity_id)`,
      );
    } else if (hasEntityCamel && hasEntityIdCamel) {
      await queryRunner.query(
        `CREATE INDEX IF NOT EXISTS idx_audit_entity ON audit_logs(entity, "entityId")`,
      );
    }

    const hasCreatedAtSnake = await queryRunner.hasColumn(
      'audit_logs',
      'created_at',
    );
    const hasCreatedAtCamel = await queryRunner.hasColumn(
      'audit_logs',
      'createdAt',
    );
    if (hasCreatedAtSnake) {
      await queryRunner.query(
        `CREATE INDEX IF NOT EXISTS idx_audit_date ON audit_logs(created_at)`,
      );
    } else if (hasCreatedAtCamel) {
      await queryRunner.query(
        `CREATE INDEX IF NOT EXISTS idx_audit_date ON audit_logs("createdAt")`,
      );
    }

    await queryRunner.query(
      `CREATE INDEX IF NOT EXISTS idx_audit_action ON audit_logs(action)`,
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropTable('audit_logs');
  }
}
