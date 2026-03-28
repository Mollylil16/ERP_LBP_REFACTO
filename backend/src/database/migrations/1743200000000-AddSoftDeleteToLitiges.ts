import {
  MigrationInterface,
  QueryRunner,
  TableColumn,
  TableIndex,
} from 'typeorm';

export class AddSoftDeleteToLitiges1743200000000 implements MigrationInterface {
  name = 'AddSoftDeleteToLitiges1743200000000';

  public async up(queryRunner: QueryRunner): Promise<void> {
    const hasDeletedAt = await queryRunner.hasColumn(
      'lbp_litiges',
      'deleted_at',
    );
    if (!hasDeletedAt) {
      await queryRunner.addColumn(
        'lbp_litiges',
        new TableColumn({
          name: 'deleted_at',
          type: 'timestamp',
          isNullable: true,
        }),
      );
    }

    await queryRunner.createIndex(
      'lbp_litiges',
      new TableIndex({
        name: 'IDX_litiges_deleted_at',
        columnNames: ['deleted_at'],
      }),
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropIndex('lbp_litiges', 'IDX_litiges_deleted_at');

    const hasDeletedAt = await queryRunner.hasColumn(
      'lbp_litiges',
      'deleted_at',
    );
    if (hasDeletedAt) {
      await queryRunner.dropColumn('lbp_litiges', 'deleted_at');
    }
  }
}
