import { MigrationInterface, QueryRunner, Table } from 'typeorm';

export class CreateClients1738708200000 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createTable(
      new Table({
        name: 'clients',
        columns: [
          {
            name: 'id',
            type: 'int',
            isPrimary: true,
            isGenerated: true,
            generationStrategy: 'increment',
          },
          {
            name: 'nom_exp',
            type: 'varchar',
            length: '100',
          },
          {
            name: 'type_piece_exp',
            type: 'varchar',
            length: '50',
          },
          {
            name: 'num_piece_exp',
            type: 'varchar',
            length: '50',
          },
          {
            name: 'tel_exp',
            type: 'varchar',
            length: '20',
          },
          {
            name: 'email_exp',
            type: 'varchar',
            length: '100',
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

    // Indexes pour recherche rapide
    await queryRunner.query(`CREATE INDEX idx_clients_nom ON clients(nom_exp)`);
    await queryRunner.query(`CREATE INDEX idx_clients_tel ON clients(tel_exp)`);
    await queryRunner.query(
      `CREATE INDEX idx_clients_num_piece ON clients(num_piece_exp)`,
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropTable('clients');
  }
}
