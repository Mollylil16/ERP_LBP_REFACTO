import {
  MigrationInterface,
  QueryRunner,
  Table,
  TableForeignKey,
} from 'typeorm';

export class CreateCaisseAndMouvements1738708600000 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    // Table: caisse
    await queryRunner.createTable(
      new Table({
        name: 'caisse',
        columns: [
          {
            name: 'id',
            type: 'int',
            isPrimary: true,
            isGenerated: true,
            generationStrategy: 'increment',
          },
          {
            name: 'nom',
            type: 'varchar',
            length: '100',
          },
          {
            name: 'id_agence',
            type: 'int',
            isNullable: true,
          },
          {
            name: 'solde_initial',
            type: 'decimal',
            precision: 10,
            scale: 2,
            default: 0,
          },
          {
            name: 'solde_minimum',
            type: 'decimal',
            precision: 10,
            scale: 2,
            default: 0,
            comment: "Seuil d'alerte pour solde faible",
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

    // Table: mouvements_caisse
    await queryRunner.createTable(
      new Table({
        name: 'mouvements_caisse',
        columns: [
          {
            name: 'id',
            type: 'int',
            isPrimary: true,
            isGenerated: true,
            generationStrategy: 'increment',
          },
          {
            name: 'id_caisse',
            type: 'int',
          },
          {
            name: 'type',
            type: 'enum',
            enum: [
              'APPRO',
              'ENTREE_ESPECE',
              'ENTREE_CHEQUE',
              'ENTREE_VIREMENT',
              'DECAISSEMENT',
            ],
          },
          {
            name: 'date_mouvement',
            type: 'date',
          },
          {
            name: 'montant',
            type: 'decimal',
            precision: 10,
            scale: 2,
          },
          {
            name: 'libelle',
            type: 'varchar',
            length: '255',
          },
          {
            name: 'mode_reglement',
            type: 'varchar',
            length: '50',
            isNullable: true,
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
            name: 'num_dossier',
            type: 'varchar',
            length: '50',
            isNullable: true,
            comment: 'Référence colis ou facture',
          },
          {
            name: 'nom_client',
            type: 'varchar',
            length: '100',
            isNullable: true,
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

    // Foreign Keys
    await queryRunner.createForeignKey(
      'caisse',
      new TableForeignKey({
        columnNames: ['id_agence'],
        referencedColumnNames: ['id'],
        referencedTableName: 'agences',
        onDelete: 'SET NULL',
      }),
    );

    await queryRunner.createForeignKey(
      'mouvements_caisse',
      new TableForeignKey({
        columnNames: ['id_caisse'],
        referencedColumnNames: ['id'],
        referencedTableName: 'caisse',
        onDelete: 'RESTRICT',
      }),
    );

    // Indexes
    await queryRunner.query(
      `CREATE INDEX idx_caisse_agence ON caisse(id_agence)`,
    );
    await queryRunner.query(
      `CREATE INDEX idx_mouvements_caisse ON mouvements_caisse(id_caisse)`,
    );
    await queryRunner.query(
      `CREATE INDEX idx_mouvements_date ON mouvements_caisse(date_mouvement)`,
    );
    await queryRunner.query(
      `CREATE INDEX idx_mouvements_type ON mouvements_caisse(type)`,
    );
    await queryRunner.query(
      `CREATE INDEX idx_mouvements_dossier ON mouvements_caisse(num_dossier)`,
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropTable('mouvements_caisse');
    await queryRunner.dropTable('caisse');
  }
}
