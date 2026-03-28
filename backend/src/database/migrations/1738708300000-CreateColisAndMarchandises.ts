import {
  MigrationInterface,
  QueryRunner,
  Table,
  TableForeignKey,
} from 'typeorm';

export class CreateColisAndMarchandises1738708300000 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    // Table: colis
    await queryRunner.createTable(
      new Table({
        name: 'colis',
        columns: [
          {
            name: 'id',
            type: 'int',
            isPrimary: true,
            isGenerated: true,
            generationStrategy: 'increment',
          },
          {
            name: 'ref_colis',
            type: 'varchar',
            length: '50',
            isUnique: true,
          },
          {
            name: 'trafic_envoi',
            type: 'enum',
            enum: ['NATIONAL', 'INTERNATIONAL'],
          },
          {
            name: 'forme_envoi',
            type: 'enum',
            enum: ['GROUPAGE', 'AUTRES_ENVOIS'],
          },
          {
            name: 'mode_envoi',
            type: 'enum',
            enum: ['MARITIME', 'AERIEN', 'ROUTIER'],
            isNullable: true,
          },
          {
            name: 'date_envoi',
            type: 'date',
          },
          {
            name: 'id_client',
            type: 'int',
          },
          {
            name: 'nom_dest',
            type: 'varchar',
            length: '100',
          },
          {
            name: 'lieu_dest',
            type: 'varchar',
            length: '100',
            isNullable: true,
          },
          {
            name: 'tel_dest',
            type: 'varchar',
            length: '20',
            isNullable: true,
          },
          {
            name: 'email_dest',
            type: 'varchar',
            length: '100',
            isNullable: true,
          },
          {
            name: 'nom_recup',
            type: 'varchar',
            length: '100',
            isNullable: true,
          },
          {
            name: 'adresse_recup',
            type: 'varchar',
            length: '255',
            isNullable: true,
          },
          {
            name: 'tel_recup',
            type: 'varchar',
            length: '20',
            isNullable: true,
          },
          {
            name: 'email_recup',
            type: 'varchar',
            length: '100',
            isNullable: true,
          },
          {
            name: 'etat_validation',
            type: 'int',
            default: 0,
            comment: '0=Brouillon, 1=Validé',
          },
          {
            name: 'code_user',
            type: 'varchar',
            length: '50',
          },
          {
            name: 'id_agence',
            type: 'int',
            isNullable: true,
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

    // Table: marchandises
    await queryRunner.createTable(
      new Table({
        name: 'marchandises',
        columns: [
          {
            name: 'id',
            type: 'int',
            isPrimary: true,
            isGenerated: true,
            generationStrategy: 'increment',
          },
          {
            name: 'id_colis',
            type: 'int',
          },
          {
            name: 'nom_marchandise',
            type: 'varchar',
            length: '100',
          },
          {
            name: 'nbre_colis',
            type: 'int',
            default: 1,
          },
          {
            name: 'nbre_articles',
            type: 'int',
            default: 1,
          },
          {
            name: 'prix_unit',
            type: 'decimal',
            precision: 10,
            scale: 2,
            default: 0,
          },
          {
            name: 'prix_emballage',
            type: 'decimal',
            precision: 10,
            scale: 2,
            default: 0,
          },
          {
            name: 'prix_assurance',
            type: 'decimal',
            precision: 10,
            scale: 2,
            default: 0,
          },
          {
            name: 'prix_agence',
            type: 'decimal',
            precision: 10,
            scale: 2,
            default: 0,
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

    // Foreign Keys
    await queryRunner.createForeignKey(
      'colis',
      new TableForeignKey({
        columnNames: ['id_client'],
        referencedColumnNames: ['id'],
        referencedTableName: 'clients',
        onDelete: 'RESTRICT',
      }),
    );

    await queryRunner.createForeignKey(
      'colis',
      new TableForeignKey({
        columnNames: ['id_agence'],
        referencedColumnNames: ['id'],
        referencedTableName: 'agences',
        onDelete: 'SET NULL',
      }),
    );

    await queryRunner.createForeignKey(
      'marchandises',
      new TableForeignKey({
        columnNames: ['id_colis'],
        referencedColumnNames: ['id'],
        referencedTableName: 'colis',
        onDelete: 'CASCADE',
      }),
    );

    // Indexes
    await queryRunner.query(`CREATE INDEX idx_colis_ref ON colis(ref_colis)`);
    await queryRunner.query(
      `CREATE INDEX idx_colis_client ON colis(id_client)`,
    );
    await queryRunner.query(`CREATE INDEX idx_colis_date ON colis(date_envoi)`);
    await queryRunner.query(
      `CREATE INDEX idx_colis_agence ON colis(id_agence)`,
    );
    await queryRunner.query(
      `CREATE INDEX idx_marchandises_colis ON marchandises(id_colis)`,
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropTable('marchandises');
    await queryRunner.dropTable('colis');
  }
}
