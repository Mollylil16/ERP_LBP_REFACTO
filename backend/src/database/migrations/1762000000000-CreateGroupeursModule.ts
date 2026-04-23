import { MigrationInterface, QueryRunner } from 'typeorm';

/**
 * Module Groupeurs / Grossistes
 * - Tables dédiées (UUID) reliées à lbp_users (id int)
 * - Pas de suppression physique : usage d’un champ `statut` + audit log
 */
export class CreateGroupeursModule1762000000000 implements MigrationInterface {
  name = 'CreateGroupeursModule1762000000000';

  public async up(queryRunner: QueryRunner): Promise<void> {
    // UUID via gen_random_uuid()
    await queryRunner.query(`CREATE EXTENSION IF NOT EXISTS pgcrypto;`);

    await queryRunner.query(`
      CREATE TABLE IF NOT EXISTS "lbp_groupeurs" (
        "id" uuid NOT NULL DEFAULT gen_random_uuid(),
        "user_id" integer,
        "code" character varying(20) NOT NULL,
        "raison_sociale" character varying(150) NOT NULL,
        "nom_commercial" character varying(150),
        "type" character varying(20) NOT NULL DEFAULT 'groupeur',
        "pays" character varying(80),
        "ville" character varying(80),
        "adresse" text,
        "telephone" character varying(30),
        "email_contact" character varying(120),
        "numero_registre" character varying(60),
        "corridors" text,
        "modes_transport" text,
        "statut" character varying(20) NOT NULL DEFAULT 'actif',
        "cree_par" integer,
        "created_at" TIMESTAMP NOT NULL DEFAULT now(),
        "updated_at" TIMESTAMP NOT NULL DEFAULT now(),
        CONSTRAINT "PK_lbp_groupeurs" PRIMARY KEY ("id"),
        CONSTRAINT "UQ_lbp_groupeurs_code" UNIQUE ("code")
      );
    `);
    await queryRunner.query(`
      ALTER TABLE "lbp_groupeurs"
        ADD CONSTRAINT "FK_lbp_groupeurs_user" FOREIGN KEY ("user_id") REFERENCES "lbp_users"("id") ON DELETE SET NULL;
    `);
    await queryRunner.query(`
      ALTER TABLE "lbp_groupeurs"
        ADD CONSTRAINT "FK_lbp_groupeurs_cree_par" FOREIGN KEY ("cree_par") REFERENCES "lbp_users"("id") ON DELETE SET NULL;
    `);

    await queryRunner.query(`
      CREATE TABLE IF NOT EXISTS "lbp_groupeur_devis" (
        "id" uuid NOT NULL DEFAULT gen_random_uuid(),
        "groupeur_id" uuid NOT NULL,
        "numero" character varying(30) NOT NULL,
        "client_nom" character varying(150) NOT NULL,
        "client_contact" character varying(120),
        "origine" character varying(100) NOT NULL,
        "destination" character varying(100) NOT NULL,
        "mode_transport" character varying(20),
        "type_chargement" character varying(10),
        "marchandise" text,
        "poids_kg" numeric(10,2),
        "volume_m3" numeric(10,2),
        "prix_propose" numeric(15,2),
        "devise" character varying(5) NOT NULL DEFAULT 'XOF',
        "validite_jours" integer NOT NULL DEFAULT 15,
        "statut" character varying(20) NOT NULL DEFAULT 'brouillon',
        "notes" text,
        "created_at" TIMESTAMP NOT NULL DEFAULT now(),
        "updated_at" TIMESTAMP NOT NULL DEFAULT now(),
        CONSTRAINT "PK_lbp_groupeur_devis" PRIMARY KEY ("id"),
        CONSTRAINT "UQ_lbp_groupeur_devis_numero" UNIQUE ("numero")
      );
    `);
    await queryRunner.query(`
      ALTER TABLE "lbp_groupeur_devis"
        ADD CONSTRAINT "FK_lbp_groupeur_devis_groupeur" FOREIGN KEY ("groupeur_id") REFERENCES "lbp_groupeurs"("id") ON DELETE CASCADE;
    `);

    await queryRunner.query(`
      CREATE TABLE IF NOT EXISTS "lbp_groupeur_expeditions" (
        "id" uuid NOT NULL DEFAULT gen_random_uuid(),
        "groupeur_id" uuid NOT NULL,
        "devis_id" uuid,
        "numero_expedition" character varying(30) NOT NULL,
        "client_nom" character varying(150) NOT NULL,
        "client_contact" character varying(120),
        "origine" character varying(100) NOT NULL,
        "destination" character varying(100) NOT NULL,
        "mode_transport" character varying(20),
        "type_chargement" character varying(10),
        "marchandise" text,
        "poids_kg" numeric(10,2),
        "volume_m3" numeric(10,2),
        "numero_conteneur" character varying(30),
        "taille_conteneur" character varying(10),
        "date_depart_prevu" date,
        "date_arrivee_prevu" date,
        "date_depart_reel" date,
        "date_arrivee_reelle" date,
        "armateur" character varying(100),
        "numero_bl_master" character varying(60),
        "numero_bl_house" character varying(60),
        "statut" character varying(30) NOT NULL DEFAULT 'en_preparation',
        "notes" text,
        "created_at" TIMESTAMP NOT NULL DEFAULT now(),
        "updated_at" TIMESTAMP NOT NULL DEFAULT now(),
        CONSTRAINT "PK_lbp_groupeur_expeditions" PRIMARY KEY ("id"),
        CONSTRAINT "UQ_lbp_groupeur_expeditions_numero" UNIQUE ("numero_expedition")
      );
    `);
    await queryRunner.query(`
      ALTER TABLE "lbp_groupeur_expeditions"
        ADD CONSTRAINT "FK_lbp_groupeur_expeditions_groupeur" FOREIGN KEY ("groupeur_id") REFERENCES "lbp_groupeurs"("id") ON DELETE CASCADE;
    `);
    await queryRunner.query(`
      ALTER TABLE "lbp_groupeur_expeditions"
        ADD CONSTRAINT "FK_lbp_groupeur_expeditions_devis" FOREIGN KEY ("devis_id") REFERENCES "lbp_groupeur_devis"("id") ON DELETE SET NULL;
    `);

    await queryRunner.query(`
      CREATE TABLE IF NOT EXISTS "lbp_groupeur_factures" (
        "id" uuid NOT NULL DEFAULT gen_random_uuid(),
        "groupeur_id" uuid NOT NULL,
        "expedition_id" uuid,
        "numero_facture" character varying(30) NOT NULL,
        "client_nom" character varying(150) NOT NULL,
        "client_contact" character varying(120),
        "date_emission" date NOT NULL DEFAULT CURRENT_DATE,
        "date_echeance" date,
        "lignes" jsonb NOT NULL DEFAULT '[]',
        "sous_total" numeric(15,2) NOT NULL,
        "tva_pct" numeric(5,2) NOT NULL DEFAULT 18.00,
        "tva_montant" numeric(15,2),
        "total_ttc" numeric(15,2) NOT NULL,
        "devise" character varying(5) NOT NULL DEFAULT 'XOF',
        "statut_paiement" character varying(20) NOT NULL DEFAULT 'en_attente',
        "montant_recu" numeric(15,2) NOT NULL DEFAULT 0,
        "date_paiement" date,
        "mode_paiement" character varying(30),
        "notes" text,
        "created_at" TIMESTAMP NOT NULL DEFAULT now(),
        "updated_at" TIMESTAMP NOT NULL DEFAULT now(),
        CONSTRAINT "PK_lbp_groupeur_factures" PRIMARY KEY ("id"),
        CONSTRAINT "UQ_lbp_groupeur_factures_numero" UNIQUE ("numero_facture")
      );
    `);
    await queryRunner.query(`
      ALTER TABLE "lbp_groupeur_factures"
        ADD CONSTRAINT "FK_lbp_groupeur_factures_groupeur" FOREIGN KEY ("groupeur_id") REFERENCES "lbp_groupeurs"("id") ON DELETE CASCADE;
    `);
    await queryRunner.query(`
      ALTER TABLE "lbp_groupeur_factures"
        ADD CONSTRAINT "FK_lbp_groupeur_factures_expedition" FOREIGN KEY ("expedition_id") REFERENCES "lbp_groupeur_expeditions"("id") ON DELETE SET NULL;
    `);

    await queryRunner.query(`
      CREATE TABLE IF NOT EXISTS "lbp_groupeur_documents" (
        "id" uuid NOT NULL DEFAULT gen_random_uuid(),
        "groupeur_id" uuid NOT NULL,
        "expedition_id" uuid,
        "type_document" character varying(50) NOT NULL,
        "nom_fichier" character varying(200) NOT NULL,
        "url_fichier" text NOT NULL,
        "taille_octets" integer,
        "statut" character varying(20) NOT NULL DEFAULT 'valide',
        "uploaded_par" integer,
        "created_at" TIMESTAMP NOT NULL DEFAULT now(),
        CONSTRAINT "PK_lbp_groupeur_documents" PRIMARY KEY ("id")
      );
    `);
    await queryRunner.query(`
      ALTER TABLE "lbp_groupeur_documents"
        ADD CONSTRAINT "FK_lbp_groupeur_documents_groupeur" FOREIGN KEY ("groupeur_id") REFERENCES "lbp_groupeurs"("id") ON DELETE CASCADE;
    `);
    await queryRunner.query(`
      ALTER TABLE "lbp_groupeur_documents"
        ADD CONSTRAINT "FK_lbp_groupeur_documents_expedition" FOREIGN KEY ("expedition_id") REFERENCES "lbp_groupeur_expeditions"("id") ON DELETE SET NULL;
    `);
    await queryRunner.query(`
      ALTER TABLE "lbp_groupeur_documents"
        ADD CONSTRAINT "FK_lbp_groupeur_documents_uploaded_par" FOREIGN KEY ("uploaded_par") REFERENCES "lbp_users"("id") ON DELETE SET NULL;
    `);

    await queryRunner.query(`
      CREATE TABLE IF NOT EXISTS "lbp_groupeur_rapports" (
        "id" uuid NOT NULL DEFAULT gen_random_uuid(),
        "auteur_id" integer,
        "type" character varying(50) NOT NULL,
        "periode" character varying(20),
        "date_debut" date,
        "date_fin" date,
        "groupeur_id" uuid,
        "commentaire" text,
        "statut_lecture" character varying(20) NOT NULL DEFAULT 'non_lu',
        "soumis_a" integer,
        "created_at" TIMESTAMP NOT NULL DEFAULT now(),
        CONSTRAINT "PK_lbp_groupeur_rapports" PRIMARY KEY ("id")
      );
    `);
    await queryRunner.query(`
      ALTER TABLE "lbp_groupeur_rapports"
        ADD CONSTRAINT "FK_lbp_groupeur_rapports_auteur" FOREIGN KEY ("auteur_id") REFERENCES "lbp_users"("id") ON DELETE SET NULL;
    `);
    await queryRunner.query(`
      ALTER TABLE "lbp_groupeur_rapports"
        ADD CONSTRAINT "FK_lbp_groupeur_rapports_soumis_a" FOREIGN KEY ("soumis_a") REFERENCES "lbp_users"("id") ON DELETE SET NULL;
    `);
    await queryRunner.query(`
      ALTER TABLE "lbp_groupeur_rapports"
        ADD CONSTRAINT "FK_lbp_groupeur_rapports_groupeur" FOREIGN KEY ("groupeur_id") REFERENCES "lbp_groupeurs"("id") ON DELETE SET NULL;
    `);

    await queryRunner.query(`
      CREATE TABLE IF NOT EXISTS "lbp_groupeur_audit_log" (
        "id" uuid NOT NULL DEFAULT gen_random_uuid(),
        "acteur_id" integer,
        "acteur_role" character varying(50),
        "action" character varying(80) NOT NULL,
        "entite" character varying(50),
        "entite_id" uuid,
        "detail" jsonb,
        "ip_address" character varying(45),
        "created_at" TIMESTAMP NOT NULL DEFAULT now(),
        CONSTRAINT "PK_lbp_groupeur_audit_log" PRIMARY KEY ("id")
      );
    `);
    await queryRunner.query(`
      ALTER TABLE "lbp_groupeur_audit_log"
        ADD CONSTRAINT "FK_lbp_groupeur_audit_acteur" FOREIGN KEY ("acteur_id") REFERENCES "lbp_users"("id") ON DELETE SET NULL;
    `);

    await queryRunner.query(
      `CREATE INDEX IF NOT EXISTS "IDX_lbp_groupeur_audit_created_at" ON "lbp_groupeur_audit_log" ("created_at");`,
    );
    await queryRunner.query(
      `CREATE INDEX IF NOT EXISTS "IDX_lbp_groupeur_rapports_created_at" ON "lbp_groupeur_rapports" ("created_at");`,
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(`DROP TABLE IF EXISTS "lbp_groupeur_audit_log";`);
    await queryRunner.query(`DROP TABLE IF EXISTS "lbp_groupeur_rapports";`);
    await queryRunner.query(`DROP TABLE IF EXISTS "lbp_groupeur_documents";`);
    await queryRunner.query(`DROP TABLE IF EXISTS "lbp_groupeur_factures";`);
    await queryRunner.query(`DROP TABLE IF EXISTS "lbp_groupeur_expeditions";`);
    await queryRunner.query(`DROP TABLE IF EXISTS "lbp_groupeur_devis";`);
    await queryRunner.query(`DROP TABLE IF EXISTS "lbp_groupeurs";`);
  }
}

