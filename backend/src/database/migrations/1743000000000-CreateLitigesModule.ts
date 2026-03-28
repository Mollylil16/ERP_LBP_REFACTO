import {
  MigrationInterface,
  QueryRunner,
  Table,
  TableForeignKey,
  TableIndex,
} from 'typeorm';

export class CreateLitigesModule1743000000000 implements MigrationInterface {
  name = 'CreateLitigesModule1743000000000';

  public async up(queryRunner: QueryRunner): Promise<void> {
    // Créer la table des litiges
    await queryRunner.createTable(
      new Table({
        name: 'lbp_litiges',
        columns: [
          {
            name: 'id',
            type: 'int',
            isPrimary: true,
            isGenerated: true,
            generationStrategy: 'increment',
          },
          {
            name: 'num_litige',
            type: 'varchar',
            length: '50',
            isUnique: true,
          },
          {
            name: 'type',
            type: 'enum',
            enum: [
              'COLIS_PERDU',
              'COLIS_ENDOMMAGE',
              'RETARD_LIVRAISON',
              'MONTANT_INCORRECT',
              'SERVICE_CLIENT',
              'AUTRE',
            ],
            default: "'AUTRE'",
          },
          {
            name: 'statut',
            type: 'enum',
            enum: ['OUVERT', 'EN_COURS', 'RESOLU', 'FERME', 'REJETE'],
            default: "'OUVERT'",
          },
          {
            name: 'priorite',
            type: 'enum',
            enum: ['BASSE', 'NORMALE', 'HAUTE', 'CRITIQUE'],
            default: "'NORMALE'",
          },
          {
            name: 'objet',
            type: 'varchar',
            length: '200',
          },
          {
            name: 'description',
            type: 'text',
          },
          {
            name: 'id_colis',
            type: 'int',
            isNullable: true,
          },
          {
            name: 'id_facture',
            type: 'int',
            isNullable: true,
          },
          {
            name: 'id_client',
            type: 'int',
          },
          {
            name: 'id_agence',
            type: 'int',
          },
          {
            name: 'id_createur',
            type: 'int',
          },
          {
            name: 'id_assigne',
            type: 'int',
            isNullable: true,
          },
          {
            name: 'date_premiere_reponse',
            type: 'timestamp',
            isNullable: true,
          },
          {
            name: 'date_resolution',
            type: 'timestamp',
            isNullable: true,
          },
          {
            name: 'date_fermeture',
            type: 'timestamp',
            isNullable: true,
          },
          {
            name: 'contact_nom',
            type: 'varchar',
            length: '100',
            isNullable: true,
          },
          {
            name: 'contact_email',
            type: 'varchar',
            length: '100',
            isNullable: true,
          },
          {
            name: 'contact_telephone',
            type: 'varchar',
            length: '20',
            isNullable: true,
          },
          {
            name: 'resolution',
            type: 'text',
            isNullable: true,
          },
          {
            name: 'montant_compensation',
            type: 'decimal',
            precision: 10,
            scale: 2,
            isNullable: true,
          },
          {
            name: 'metadata',
            type: 'jsonb',
            isNullable: true,
          },
          {
            name: 'escalade',
            type: 'boolean',
            default: false,
          },
          {
            name: 'nb_relances',
            type: 'int',
            default: 0,
          },
          {
            name: 'created_at',
            type: 'timestamp',
            default: 'now()',
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

    // Créer la table des messages de litige
    await queryRunner.createTable(
      new Table({
        name: 'lbp_litige_messages',
        columns: [
          {
            name: 'id',
            type: 'int',
            isPrimary: true,
            isGenerated: true,
            generationStrategy: 'increment',
          },
          {
            name: 'id_litige',
            type: 'int',
          },
          {
            name: 'id_auteur',
            type: 'int',
          },
          {
            name: 'type',
            type: 'enum',
            enum: [
              'MESSAGE',
              'CHANGEMENT_STATUT',
              'ASSIGNATION',
              'ESCALADE',
              'RESOLUTION',
            ],
            default: "'MESSAGE'",
          },
          {
            name: 'contenu',
            type: 'text',
          },
          {
            name: 'pieces_jointes',
            type: 'jsonb',
            isNullable: true,
          },
          {
            name: 'interne',
            type: 'boolean',
            default: false,
          },
          {
            name: 'metadata',
            type: 'jsonb',
            isNullable: true,
          },
          {
            name: 'created_at',
            type: 'timestamp',
            default: 'now()',
          },
        ],
      }),
      true,
    );

    // Créer les index pour les performances
    await queryRunner.createIndex(
      'lbp_litiges',
      new TableIndex({ name: 'IDX_litiges_statut', columnNames: ['statut'] }),
    );
    await queryRunner.createIndex(
      'lbp_litiges',
      new TableIndex({ name: 'IDX_litiges_type', columnNames: ['type'] }),
    );
    await queryRunner.createIndex(
      'lbp_litiges',
      new TableIndex({
        name: 'IDX_litiges_agence',
        columnNames: ['id_agence'],
      }),
    );
    await queryRunner.createIndex(
      'lbp_litiges',
      new TableIndex({
        name: 'IDX_litiges_created_at',
        columnNames: ['created_at'],
      }),
    );
    await queryRunner.createIndex(
      'lbp_litige_messages',
      new TableIndex({
        name: 'IDX_messages_litige',
        columnNames: ['id_litige'],
      }),
    );

    // Créer les clés étrangères
    await queryRunner.createForeignKey(
      'lbp_litiges',
      new TableForeignKey({
        columnNames: ['id_colis'],
        referencedTableName: 'lbp_colis',
        referencedColumnNames: ['id'],
        onDelete: 'SET NULL',
        name: 'FK_litige_colis',
      }),
    );

    await queryRunner.createForeignKey(
      'lbp_litiges',
      new TableForeignKey({
        columnNames: ['id_facture'],
        referencedTableName: 'lbp_factures',
        referencedColumnNames: ['id'],
        onDelete: 'SET NULL',
        name: 'FK_litige_facture',
      }),
    );

    await queryRunner.createForeignKey(
      'lbp_litiges',
      new TableForeignKey({
        columnNames: ['id_client'],
        referencedTableName: 'lbp_clients',
        referencedColumnNames: ['id'],
        onDelete: 'CASCADE',
        name: 'FK_litige_client',
      }),
    );

    await queryRunner.createForeignKey(
      'lbp_litiges',
      new TableForeignKey({
        columnNames: ['id_agence'],
        referencedTableName: 'lbp_agences',
        referencedColumnNames: ['id'],
        onDelete: 'CASCADE',
        name: 'FK_litige_agence',
      }),
    );

    await queryRunner.createForeignKey(
      'lbp_litiges',
      new TableForeignKey({
        columnNames: ['id_createur'],
        referencedTableName: 'lbp_users',
        referencedColumnNames: ['id'],
        onDelete: 'CASCADE',
        name: 'FK_litige_createur',
      }),
    );

    await queryRunner.createForeignKey(
      'lbp_litiges',
      new TableForeignKey({
        columnNames: ['id_assigne'],
        referencedTableName: 'lbp_users',
        referencedColumnNames: ['id'],
        onDelete: 'SET NULL',
        name: 'FK_litige_assigne',
      }),
    );

    await queryRunner.createForeignKey(
      'lbp_litige_messages',
      new TableForeignKey({
        columnNames: ['id_litige'],
        referencedTableName: 'lbp_litiges',
        referencedColumnNames: ['id'],
        onDelete: 'CASCADE',
        name: 'FK_message_litige',
      }),
    );

    await queryRunner.createForeignKey(
      'lbp_litige_messages',
      new TableForeignKey({
        columnNames: ['id_auteur'],
        referencedTableName: 'lbp_users',
        referencedColumnNames: ['id'],
        onDelete: 'CASCADE',
        name: 'FK_message_auteur',
      }),
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    // Supprimer les clés étrangères
    await queryRunner.dropForeignKey(
      'lbp_litige_messages',
      'FK_message_auteur',
    );
    await queryRunner.dropForeignKey(
      'lbp_litige_messages',
      'FK_message_litige',
    );
    await queryRunner.dropForeignKey('lbp_litiges', 'FK_litige_assigne');
    await queryRunner.dropForeignKey('lbp_litiges', 'FK_litige_createur');
    await queryRunner.dropForeignKey('lbp_litiges', 'FK_litige_agence');
    await queryRunner.dropForeignKey('lbp_litiges', 'FK_litige_client');
    await queryRunner.dropForeignKey('lbp_litiges', 'FK_litige_facture');
    await queryRunner.dropForeignKey('lbp_litiges', 'FK_litige_colis');

    // Supprimer les index
    await queryRunner.dropIndex('lbp_litige_messages', 'IDX_messages_litige');
    await queryRunner.dropIndex('lbp_litiges', 'IDX_litiges_created_at');
    await queryRunner.dropIndex('lbp_litiges', 'IDX_litiges_agence');
    await queryRunner.dropIndex('lbp_litiges', 'IDX_litiges_type');
    await queryRunner.dropIndex('lbp_litiges', 'IDX_litiges_statut');

    // Supprimer les tables
    await queryRunner.dropTable('lbp_litige_messages');
    await queryRunner.dropTable('lbp_litiges');
  }
}
