import { MigrationInterface, QueryRunner } from 'typeorm';

export class CreateRhModuleV31762500000000 implements MigrationInterface {
  name = 'CreateRhModuleV31762500000000';

  public async up(queryRunner: QueryRunner): Promise<void> {
    // 1. Enum type document RH
    await queryRunner.query(`
      DO $migration$
      BEGIN
        IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'rh_documents_type_enum') THEN
          CREATE TYPE "public"."rh_documents_type_enum" AS ENUM (
            'contrat','avenant','cni','diplome','certificat','attestation',
            'fiche_paie','visite_medicale','photo','autre'
          );
        END IF;
      END
      $migration$;
    `);

    // 2. rh_documents (coffre-fort numérique)
    await queryRunner.query(`
      CREATE TABLE IF NOT EXISTS "rh_documents" (
        "id"              SERIAL PRIMARY KEY,
        "id_employe"      INTEGER       NOT NULL REFERENCES "rh_employes"("id") ON DELETE CASCADE,
        "type"            "public"."rh_documents_type_enum" NOT NULL DEFAULT 'autre',
        "nom_fichier"     VARCHAR(200)  NOT NULL,
        "url_fichier"     TEXT          NOT NULL,
        "taille_octets"   BIGINT,
        "mime_type"       VARCHAR(20),
        "description"     TEXT,
        "date_expiration" DATE,
        "id_uploader"     INTEGER REFERENCES "lbp_users"("id") ON DELETE SET NULL,
        "created_at"      TIMESTAMPTZ   NOT NULL DEFAULT now()
      );
    `);

    // 3. rh_historique_postes
    await queryRunner.query(`
      CREATE TABLE IF NOT EXISTS "rh_historique_postes" (
        "id"                  SERIAL PRIMARY KEY,
        "id_employe"          INTEGER       NOT NULL REFERENCES "rh_employes"("id") ON DELETE CASCADE,
        "ancien_poste"        VARCHAR(100),
        "nouveau_poste"       VARCHAR(100),
        "ancien_departement"  VARCHAR(100),
        "nouveau_departement" VARCHAR(100),
        "ancienne_categorie"  VARCHAR(60),
        "nouvelle_categorie"  VARCHAR(60),
        "ancien_salaire"      NUMERIC(15,2),
        "nouveau_salaire"     NUMERIC(15,2),
        "date_effet"          DATE          NOT NULL,
        "motif"               TEXT,
        "id_auteur"           INTEGER REFERENCES "lbp_users"("id") ON DELETE SET NULL,
        "created_at"          TIMESTAMPTZ   NOT NULL DEFAULT now()
      );
    `);

    // 4. rh_onboarding_checklists
    await queryRunner.query(`
      CREATE TABLE IF NOT EXISTS "rh_onboarding_checklists" (
        "id"                    SERIAL PRIMARY KEY,
        "id_employe"            INTEGER       NOT NULL REFERENCES "rh_employes"("id") ON DELETE CASCADE,
        "date_prise_poste"      DATE,
        "date_visite_medicale"  DATE,
        "visite_medicale_faite" BOOLEAN       NOT NULL DEFAULT false,
        "materiel_fourni"       BOOLEAN       NOT NULL DEFAULT false,
        "acces_systemes"        BOOLEAN       NOT NULL DEFAULT false,
        "badge_remis"           BOOLEAN       NOT NULL DEFAULT false,
        "livret_accueil"        BOOLEAN       NOT NULL DEFAULT false,
        "formation_securite"    BOOLEAN       NOT NULL DEFAULT false,
        "taches_custom"         JSONB,
        "notes"                 TEXT,
        "id_referent"           INTEGER REFERENCES "lbp_users"("id") ON DELETE SET NULL,
        "created_at"            TIMESTAMPTZ   NOT NULL DEFAULT now(),
        "updated_at"            TIMESTAMPTZ   NOT NULL DEFAULT now()
      );
    `);

    // 5. Ajouter colonne salaire_base à rh_employes (pour export paie)
    await queryRunner.query(`
      DO $migration$
      BEGIN
        IF NOT EXISTS (
          SELECT 1 FROM information_schema.columns
          WHERE table_name='rh_employes' AND column_name='salaire_base'
        ) THEN
          ALTER TABLE "rh_employes" ADD COLUMN "salaire_base" NUMERIC(15,2);
        END IF;
      END
      $migration$;
    `);

    // 6. Seed jours fériés légaux CI (2025 + 2026) — Décret 2024-898
    await queryRunner.query(`
      INSERT INTO "rh_jours_feries" ("date","libelle","est_islamique","annee") VALUES
        ('2025-01-01','Jour de l''An',false,2025),
        ('2025-01-03','Anniversaire de la Côte d''Ivoire',false,2025),
        ('2025-04-18','Vendredi Saint',false,2025),
        ('2025-04-21','Lundi de Pâques',false,2025),
        ('2025-05-01','Fête du Travail',false,2025),
        ('2025-05-29','Ascension',false,2025),
        ('2025-06-09','Lundi de Pentecôte',false,2025),
        ('2025-08-07','Fête Nationale — Indépendance',false,2025),
        ('2025-08-15','Assomption',false,2025),
        ('2025-11-01','Toussaint',false,2025),
        ('2025-11-15','Paix Nationale',false,2025),
        ('2025-12-25','Noël',false,2025),
        ('2026-01-01','Jour de l''An',false,2026),
        ('2026-01-03','Anniversaire de la Côte d''Ivoire',false,2026),
        ('2026-04-03','Vendredi Saint',false,2026),
        ('2026-04-06','Lundi de Pâques',false,2026),
        ('2026-05-01','Fête du Travail',false,2026),
        ('2026-05-14','Ascension',false,2026),
        ('2026-05-25','Lundi de Pentecôte',false,2026),
        ('2026-08-07','Fête Nationale — Indépendance',false,2026),
        ('2026-08-15','Assomption',false,2026),
        ('2026-11-01','Toussaint',false,2026),
        ('2026-11-15','Paix Nationale',false,2026),
        ('2026-12-25','Noël',false,2026)
      ON CONFLICT ("date") DO NOTHING;
    `);

    // 7. Répertoire uploads (créé au runtime par NestJS)
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(`DELETE FROM "rh_jours_feries" WHERE annee IN (2025, 2026) AND est_islamique = false;`);
    await queryRunner.query(`DROP TABLE IF EXISTS "rh_onboarding_checklists" CASCADE;`);
    await queryRunner.query(`DROP TABLE IF EXISTS "rh_historique_postes" CASCADE;`);
    await queryRunner.query(`DROP TABLE IF EXISTS "rh_documents" CASCADE;`);
    await queryRunner.query(`DROP TYPE IF EXISTS "public"."rh_documents_type_enum";`);
    await queryRunner.query(`ALTER TABLE "rh_employes" DROP COLUMN IF EXISTS "salaire_base";`);
  }
}
