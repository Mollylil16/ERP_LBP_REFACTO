import { MigrationInterface, QueryRunner } from 'typeorm';

export class AddPaymentStatusToFactures1741700000000 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    // 1. Créer le type enum si nécessaire
    await queryRunner.query(`
            DO $$ BEGIN
                CREATE TYPE payment_status_enum AS ENUM ('unpaid', 'partial', 'paid');
            EXCEPTION
                WHEN duplicate_object THEN null;
            END $$;
        `);

    // 2. Ajouter les colonnes manquantes à lbp_factures
    await queryRunner.query(`
            ALTER TABLE "lbp_factures"
            ADD COLUMN IF NOT EXISTS "payment_status" payment_status_enum NOT NULL DEFAULT 'unpaid',
            ADD COLUMN IF NOT EXISTS "devise" varchar(10) NOT NULL DEFAULT 'XOF',
            ADD COLUMN IF NOT EXISTS "taux_change" decimal(12,4) NOT NULL DEFAULT 1;
        `);

    // 3. Mettre à jour payment_status selon les montants existants
    await queryRunner.query(`
            UPDATE "lbp_factures"
            SET "payment_status" = CASE
                WHEN "montant_paye" >= "montant_ttc" AND "montant_ttc" > 0 THEN 'paid'::payment_status_enum
                WHEN "montant_paye" > 0 THEN 'partial'::payment_status_enum
                ELSE 'unpaid'::payment_status_enum
            END;
        `);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(`
            ALTER TABLE "lbp_factures"
            DROP COLUMN IF EXISTS "payment_status",
            DROP COLUMN IF EXISTS "devise",
            DROP COLUMN IF EXISTS "taux_change";
        `);
  }
}
