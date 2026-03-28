import {
  MigrationInterface,
  QueryRunner,
  Table,
  TableForeignKey,
} from 'typeorm';

export class CreateFactures1738708400000 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createTable(
      new Table({
        name: 'factures',
        columns: [
          {
            name: 'id',
            type: 'int',
            isPrimary: true,
            isGenerated: true,
            generationStrategy: 'increment',
          },
          {
            name: 'num_facture',
            type: 'varchar',
            length: '50',
            isUnique: true,
          },
          {
            name: 'id_colis',
            type: 'int',
          },
          {
            name: 'date_facture',
            type: 'date',
          },
          {
            name: 'montant_ht',
            type: 'decimal',
            precision: 10,
            scale: 2,
            default: 0,
          },
          {
            name: 'montant_ttc',
            type: 'decimal',
            precision: 10,
            scale: 2,
            default: 0,
          },
          {
            name: 'montant_paye',
            type: 'decimal',
            precision: 10,
            scale: 2,
            default: 0,
          },
          {
            name: 'etat',
            type: 'int',
            default: 0,
            comment: '0=Proforma, 1=Définitive, 2=Annulée',
          },
          {
            name: 'code_user',
            type: 'varchar',
            length: '50',
          },
          {
            name: 'created_at',
            type: 'timestamp',
            default: 'CURRENT_TIMESTAMP',
          },
          {
            name: 'updated_at',
            type: 'timestamp',
            default: 'CURRENT_TIMESTAMP',
            onUpdate: 'CURRENT_TIMESTAMP',
          },
        ],
      }),
      true,
    );

    // Foreign Key
    await queryRunner.createForeignKey(
      'factures',
      new TableForeignKey({
        columnNames: ['id_colis'],
        referencedColumnNames: ['id'],
        referencedTableName: 'colis',
        onDelete: 'RESTRICT',
      }),
    );

    // Indexes
    await queryRunner.query(
      `CREATE INDEX idx_factures_num ON factures(num_facture)`,
    );
    await queryRunner.query(
      `CREATE INDEX idx_factures_colis ON factures(id_colis)`,
    );
    await queryRunner.query(
      `CREATE INDEX idx_factures_date ON factures(date_facture)`,
    );
    await queryRunner.query(`CREATE INDEX idx_factures_etat ON factures(etat)`);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropTable('factures');
  }
}
