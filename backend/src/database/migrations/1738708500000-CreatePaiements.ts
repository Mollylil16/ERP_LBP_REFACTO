import {
  MigrationInterface,
  QueryRunner,
  Table,
  TableForeignKey,
} from 'typeorm';

export class CreatePaiements1738708500000 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createTable(
      new Table({
        name: 'paiements',
        columns: [
          {
            name: 'id',
            type: 'int',
            isPrimary: true,
            isGenerated: true,
            generationStrategy: 'increment',
          },
          {
            name: 'id_facture',
            type: 'int',
          },
          {
            name: 'date_paiement',
            type: 'date',
          },
          {
            name: 'montant',
            type: 'decimal',
            precision: 10,
            scale: 2,
          },
          {
            name: 'mode_paiement',
            type: 'varchar',
            length: '50',
            comment: 'ESPECE, CHEQUE, VIREMENT, etc.',
          },
          {
            name: 'num_cheque',
            type: 'varchar',
            length: '50',
            isNullable: true,
          },
          {
            name: 'banque',
            type: 'varchar',
            length: '100',
            isNullable: true,
          },
          {
            name: 'etat_validation',
            type: 'int',
            default: 1,
            comment: '0=Annulé, 1=Validé',
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
      'paiements',
      new TableForeignKey({
        columnNames: ['id_facture'],
        referencedColumnNames: ['id'],
        referencedTableName: 'factures',
        onDelete: 'RESTRICT',
      }),
    );

    // Indexes
    await queryRunner.query(
      `CREATE INDEX idx_paiements_facture ON paiements(id_facture)`,
    );
    await queryRunner.query(
      `CREATE INDEX idx_paiements_date ON paiements(date_paiement)`,
    );
    await queryRunner.query(
      `CREATE INDEX idx_paiements_mode ON paiements(mode_paiement)`,
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropTable('paiements');
  }
}
