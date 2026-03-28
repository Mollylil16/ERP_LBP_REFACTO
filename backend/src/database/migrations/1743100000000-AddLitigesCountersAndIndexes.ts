import { MigrationInterface, QueryRunner, Table, TableIndex } from 'typeorm';

export class AddLitigesCountersAndIndexes1743100000000 implements MigrationInterface {
  name = 'AddLitigesCountersAndIndexes1743100000000';

  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createTable(
      new Table({
        name: 'lbp_litige_counters',
        columns: [
          {
            name: 'counter_key',
            type: 'varchar',
            length: '10',
            isPrimary: true,
          },
          {
            name: 'sequence_value',
            type: 'int',
            default: 0,
          },
          {
            name: 'updated_at',
            type: 'timestamp',
            default: 'now()',
          },
        ],
      }),
      true,
    );

    await queryRunner.createIndex(
      'lbp_litige_messages',
      new TableIndex({
        name: 'IDX_messages_litige_created_at',
        columnNames: ['id_litige', 'created_at'],
      }),
    );

    await queryRunner.createIndex(
      'lbp_litiges',
      new TableIndex({
        name: 'IDX_litiges_assigne',
        columnNames: ['id_assigne'],
      }),
    );

    await queryRunner.createIndex(
      'lbp_litiges',
      new TableIndex({
        name: 'IDX_litiges_priorite',
        columnNames: ['priorite'],
      }),
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropIndex('lbp_litiges', 'IDX_litiges_priorite');
    await queryRunner.dropIndex('lbp_litiges', 'IDX_litiges_assigne');
    await queryRunner.dropIndex(
      'lbp_litige_messages',
      'IDX_messages_litige_created_at',
    );
    await queryRunner.dropTable('lbp_litige_counters');
  }
}
