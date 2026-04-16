import { MigrationInterface, QueryRunner } from 'typeorm';

export class CreatePrestatairesModule1749000000000
  implements MigrationInterface
{
  name = 'CreatePrestatairesModule1749000000000';

  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(`
      CREATE TABLE IF NOT EXISTS "lbp_prestataires" (
        "id" SERIAL PRIMARY KEY,
        "nom" varchar(200) NOT NULL,
        "pays" varchar(100),
        "actif" boolean NOT NULL DEFAULT true,
        "contact_nom" varchar(150),
        "contact_tel" varchar(50),
        "contact_email" varchar(200),
        "created_at" timestamptz NOT NULL DEFAULT now(),
        "updated_at" timestamptz NOT NULL DEFAULT now()
      )
    `);

    await queryRunner.query(`
      CREATE TABLE IF NOT EXISTS "lbp_prestataires_factures" (
        "id" SERIAL PRIMARY KEY,
        "id_agence" int NOT NULL REFERENCES "lbp_agences"("id") ON DELETE RESTRICT,
        "pays" varchar(100),
        "prestataire_id" int NOT NULL REFERENCES "lbp_prestataires"("id") ON DELETE RESTRICT,
        "date_reception" date NOT NULL,
        "numero_lta" varchar(100),
        "numero_envoi" varchar(100),
        "numero_facture" varchar(120) NOT NULL,
        "montant_total" numeric(12,2) NOT NULL DEFAULT 0,
        "devise" varchar(10) NOT NULL DEFAULT 'XOF',
        "delai_reglement_jours" int,
        "date_echeance" date NOT NULL,
        "statut" varchar(20) NOT NULL DEFAULT 'A_PAYER',
        "montant_regle" numeric(12,2) NOT NULL DEFAULT 0,
        "reliquat" numeric(12,2) NOT NULL DEFAULT 0,
        "note" text,
        "created_by" varchar(100),
        "created_at" timestamptz NOT NULL DEFAULT now(),
        "updated_at" timestamptz NOT NULL DEFAULT now()
      )
    `);

    await queryRunner.query(`
      CREATE INDEX IF NOT EXISTS "idx_prest_fact_agence" ON "lbp_prestataires_factures"("id_agence")
    `);
    await queryRunner.query(`
      CREATE INDEX IF NOT EXISTS "idx_prest_fact_echeance" ON "lbp_prestataires_factures"("date_echeance")
    `);
    await queryRunner.query(`
      CREATE UNIQUE INDEX IF NOT EXISTS "uq_prest_fact_prestataire_num" ON "lbp_prestataires_factures"("prestataire_id","numero_facture")
    `);

    await queryRunner.query(`
      CREATE TABLE IF NOT EXISTS "lbp_prestataires_reglements" (
        "id" SERIAL PRIMARY KEY,
        "facture_id" int NOT NULL REFERENCES "lbp_prestataires_factures"("id") ON DELETE CASCADE,
        "date_reglement" date NOT NULL,
        "mode_reglement" varchar(30) NOT NULL,
        "montant" numeric(12,2) NOT NULL DEFAULT 0,
        "reference" varchar(150),
        "note" text,
        "origine_fonds" varchar(30) NOT NULL DEFAULT 'CAISSE_PRINCIPALE',
        "hub_retrait_status" varchar(20) NOT NULL DEFAULT 'NA',
        "hub_retrait_marked_at" timestamptz,
        "hub_retrait_marked_by" varchar(100),
        "hub_retrait_approval_status" varchar(20) NOT NULL DEFAULT 'NA',
        "hub_retrait_approval_requested_at" timestamptz,
        "hub_retrait_approval_requested_by" varchar(100),
        "hub_retrait_approval_decided_at" timestamptz,
        "hub_retrait_approval_decided_by" varchar(100),
        "created_by" varchar(100),
        "created_at" timestamptz NOT NULL DEFAULT now(),
        "updated_at" timestamptz NOT NULL DEFAULT now()
      )
    `);

    await queryRunner.query(`
      CREATE INDEX IF NOT EXISTS "idx_prest_reg_facture" ON "lbp_prestataires_reglements"("facture_id")
    `);
    await queryRunner.query(`
      CREATE INDEX IF NOT EXISTS "idx_prest_reg_hub_status" ON "lbp_prestataires_reglements"("hub_retrait_status")
    `);
  }

  public async down(): Promise<void> {
    /* down non destructif */
  }
}

