import { MigrationInterface, QueryRunner, Table } from 'typeorm';

export class CreateAgences1738708100000 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createTable(
      new Table({
        name: 'agences',
        columns: [
          {
            name: 'id',
            type: 'int',
            isPrimary: true,
            isGenerated: true,
            generationStrategy: 'increment',
          },
          {
            name: 'code',
            type: 'varchar',
            length: '20',
            isUnique: true,
          },
          {
            name: 'nom',
            type: 'varchar',
            length: '100',
          },
          {
            name: 'pays',
            type: 'varchar',
            length: '50',
            default: "'Côte d'Ivoire'",
          },
          {
            name: 'ville',
            type: 'varchar',
            length: '50',
          },
          {
            name: 'adresse',
            type: 'varchar',
            length: '255',
            isNullable: true,
          },
          {
            name: 'telephone',
            type: 'varchar',
            length: '20',
            isNullable: true,
          },
          {
            name: 'email',
            type: 'varchar',
            length: '100',
            isNullable: true,
          },
          {
            name: 'nom_responsable',
            type: 'varchar',
            length: '100',
            isNullable: true,
          },
          {
            name: 'tel_responsable',
            type: 'varchar',
            length: '20',
            isNullable: true,
          },
          {
            name: 'actif',
            type: 'boolean',
            default: true,
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

    // Add foreign key to users table
    await queryRunner.query(`
            ALTER TABLE users 
            ADD CONSTRAINT fk_users_agence 
            FOREIGN KEY (id_agence) 
            REFERENCES agences(id) 
            ON DELETE SET NULL
        `);

    // Index
    await queryRunner.query(`CREATE INDEX idx_agences_code ON agences(code)`);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(
      `ALTER TABLE users DROP FOREIGN KEY fk_users_agence`,
    );
    await queryRunner.dropTable('agences');
  }
}
