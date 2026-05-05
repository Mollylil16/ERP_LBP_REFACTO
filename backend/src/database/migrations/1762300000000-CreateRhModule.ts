import { MigrationInterface, QueryRunner } from 'typeorm';

export class CreateRhModule1762300000000 implements MigrationInterface {
  name = 'CreateRhModule1762300000000';

  public async up(queryRunner: QueryRunner): Promise<void> {
    // 1. Extend lbp_permissions_module_enum with 'RH'
    await queryRunner.query(`
      DO $migration$
      BEGIN
        IF EXISTS (
          SELECT 1 FROM pg_catalog.pg_type WHERE typname = 'lbp_permissions_module_enum'
        ) AND NOT EXISTS (
          SELECT 1 FROM pg_catalog.pg_enum e
          INNER JOIN pg_catalog.pg_type t ON e.enumtypid = t.oid
          WHERE t.typname = 'lbp_permissions_module_enum' AND e.enumlabel = 'RH'
        ) THEN
          ALTER TYPE "public"."lbp_permissions_module_enum" ADD VALUE 'RH';
        END IF;
      END
      $migration$;
    `);

    // 2. Extend lbp_users_role_enum with 'RESPONSABLE_RH'
    await queryRunner.query(`
      DO $migration$
      BEGIN
        IF EXISTS (
          SELECT 1 FROM pg_catalog.pg_type WHERE typname = 'lbp_users_role_enum'
        ) AND NOT EXISTS (
          SELECT 1 FROM pg_catalog.pg_enum e
          INNER JOIN pg_catalog.pg_type t ON e.enumtypid = t.oid
          WHERE t.typname = 'lbp_users_role_enum' AND e.enumlabel = 'RESPONSABLE_RH'
        ) THEN
          ALTER TYPE "public"."lbp_users_role_enum" ADD VALUE 'RESPONSABLE_RH';
        END IF;
      END
      $migration$;
    `);

    // 3. Create enum types for RH
    await queryRunner.query(`
      DO $migration$
      BEGIN
        IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'rh_employes_type_contrat_actuel_enum') THEN
          CREATE TYPE "public"."rh_employes_type_contrat_actuel_enum" AS ENUM ('CDI','CDD','STAGE','INTERIM');
        END IF;
        IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'rh_employes_statut_enum') THEN
          CREATE TYPE "public"."rh_employes_statut_enum" AS ENUM ('actif','suspendu','sorti');
        END IF;
        IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'rh_employes_sexe_enum') THEN
          CREATE TYPE "public"."rh_employes_sexe_enum" AS ENUM ('M','F');
        END IF;
        IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'rh_employes_situation_familiale_enum') THEN
          CREATE TYPE "public"."rh_employes_situation_familiale_enum" AS ENUM ('celibataire','marie','divorce','veuf');
        END IF;
        IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'rh_contrats_type_contrat_enum') THEN
          CREATE TYPE "public"."rh_contrats_type_contrat_enum" AS ENUM ('CDI','CDD','STAGE','INTERIM');
        END IF;
        IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'rh_contrats_statut_enum') THEN
          CREATE TYPE "public"."rh_contrats_statut_enum" AS ENUM ('actif','termine','resilie','essai');
        END IF;
        IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'rh_conge_requests_statut_enum') THEN
          CREATE TYPE "public"."rh_conge_requests_statut_enum" AS ENUM ('en_attente','approuve_manager','approuve','refuse','annule');
        END IF;
      END
      $migration$;
    `);

    // 4. Create rh_employes
    await queryRunner.query(`
      CREATE TABLE IF NOT EXISTS "rh_employes" (
        "id"                      SERIAL PRIMARY KEY,
        "matricule"               VARCHAR(20)  NOT NULL UNIQUE,
        "nom"                     VARCHAR(100) NOT NULL,
        "prenoms"                 VARCHAR(200) NOT NULL,
        "date_naissance"          DATE,
        "lieu_naissance"          VARCHAR(100),
        "nationalite"             VARCHAR(80),
        "sexe"                    "public"."rh_employes_sexe_enum",
        "situation_familiale"     "public"."rh_employes_situation_familiale_enum",
        "nb_enfants"              INTEGER      NOT NULL DEFAULT 0,
        "numero_cni"              VARCHAR(30),
        "numero_cnps"             VARCHAR(30),
        "adresse"                 TEXT,
        "telephone"               VARCHAR(20),
        "email_pro"               VARCHAR(100),
        "email_perso"             VARCHAR(100),
        "date_embauche"           DATE         NOT NULL,
        "date_sortie"             DATE,
        "intitule_poste"          VARCHAR(100),
        "categorie"               VARCHAR(60),
        "grade"                   VARCHAR(40),
        "departement"             VARCHAR(100),
        "service"                 VARCHAR(100),
        "type_contrat_actuel"     "public"."rh_employes_type_contrat_actuel_enum" NOT NULL DEFAULT 'CDI',
        "statut"                  "public"."rh_employes_statut_enum"              NOT NULL DEFAULT 'actif',
        "id_agence"               INTEGER REFERENCES "agences"("id") ON DELETE SET NULL,
        "id_responsable"          INTEGER REFERENCES "rh_employes"("id") ON DELETE SET NULL,
        "id_user"                 INTEGER REFERENCES "lbp_users"("id") ON DELETE SET NULL,
        "created_at"              TIMESTAMPTZ NOT NULL DEFAULT now(),
        "updated_at"              TIMESTAMPTZ NOT NULL DEFAULT now()
      );
    `);

    // 5. Create rh_contrats
    await queryRunner.query(`
      CREATE TABLE IF NOT EXISTS "rh_contrats" (
        "id"                      SERIAL PRIMARY KEY,
        "id_employe"              INTEGER      NOT NULL REFERENCES "rh_employes"("id") ON DELETE CASCADE,
        "type_contrat"            "public"."rh_contrats_type_contrat_enum" NOT NULL,
        "date_debut"              DATE         NOT NULL,
        "date_fin"                DATE,
        "periode_essai_debut"     DATE,
        "periode_essai_fin"       DATE,
        "intitule_poste"          VARCHAR(100),
        "salaire_base"            NUMERIC(15,2),
        "statut"                  "public"."rh_contrats_statut_enum" NOT NULL DEFAULT 'actif',
        "motif_fin"               TEXT,
        "notes"                   TEXT,
        "alerte_envoyee_jours"    INTEGER      NOT NULL DEFAULT 0,
        "created_at"              TIMESTAMPTZ NOT NULL DEFAULT now(),
        "updated_at"              TIMESTAMPTZ NOT NULL DEFAULT now()
      );
    `);

    // 6. Create rh_conge_types
    await queryRunner.query(`
      CREATE TABLE IF NOT EXISTS "rh_conge_types" (
        "id"                      SERIAL PRIMARY KEY,
        "code"                    VARCHAR(40)  NOT NULL UNIQUE,
        "libelle"                 VARCHAR(100) NOT NULL,
        "jours_par_an"            INTEGER      NOT NULL DEFAULT 0,
        "est_paye"                BOOLEAN      NOT NULL DEFAULT true,
        "necessite_justificatif"  BOOLEAN      NOT NULL DEFAULT false,
        "description"             TEXT,
        "est_actif"               BOOLEAN      NOT NULL DEFAULT true,
        "created_at"              TIMESTAMPTZ NOT NULL DEFAULT now(),
        "updated_at"              TIMESTAMPTZ NOT NULL DEFAULT now()
      );
    `);

    // 7. Create rh_conge_requests
    await queryRunner.query(`
      CREATE TABLE IF NOT EXISTS "rh_conge_requests" (
        "id"                      SERIAL PRIMARY KEY,
        "id_employe"              INTEGER      NOT NULL REFERENCES "rh_employes"("id") ON DELETE CASCADE,
        "id_conge_type"           INTEGER      NOT NULL REFERENCES "rh_conge_types"("id") ON DELETE RESTRICT,
        "date_debut"              DATE         NOT NULL,
        "date_fin"                DATE         NOT NULL,
        "nb_jours"                INTEGER      NOT NULL,
        "motif"                   TEXT,
        "statut"                  "public"."rh_conge_requests_statut_enum" NOT NULL DEFAULT 'en_attente',
        "id_valideur_manager"     INTEGER REFERENCES "lbp_users"("id") ON DELETE SET NULL,
        "date_validation_manager" TIMESTAMPTZ,
        "commentaire_manager"     TEXT,
        "id_valideur_rh"          INTEGER REFERENCES "lbp_users"("id") ON DELETE SET NULL,
        "date_validation_rh"      TIMESTAMPTZ,
        "commentaire_rh"          TEXT,
        "created_at"              TIMESTAMPTZ NOT NULL DEFAULT now(),
        "updated_at"              TIMESTAMPTZ NOT NULL DEFAULT now()
      );
    `);

    // 8. Create rh_conge_balances
    await queryRunner.query(`
      CREATE TABLE IF NOT EXISTS "rh_conge_balances" (
        "id"                      SERIAL PRIMARY KEY,
        "id_employe"              INTEGER      NOT NULL REFERENCES "rh_employes"("id") ON DELETE CASCADE,
        "id_conge_type"           INTEGER      NOT NULL REFERENCES "rh_conge_types"("id") ON DELETE RESTRICT,
        "annee"                   INTEGER      NOT NULL,
        "jours_acquis"            NUMERIC(8,2) NOT NULL DEFAULT 0,
        "jours_pris"              NUMERIC(8,2) NOT NULL DEFAULT 0,
        "jours_restants"          NUMERIC(8,2) NOT NULL DEFAULT 0,
        "created_at"              TIMESTAMPTZ NOT NULL DEFAULT now(),
        "updated_at"              TIMESTAMPTZ NOT NULL DEFAULT now(),
        UNIQUE ("id_employe", "id_conge_type", "annee")
      );
    `);

    // 9. Seed default conge types (Art. 25 CDT ivoirien)
    await queryRunner.query(`
      INSERT INTO "rh_conge_types" ("code","libelle","jours_par_an","est_paye","necessite_justificatif","description") VALUES
        ('CONGE_ANNUEL',      'Congé annuel légal',        30,  true,  false, 'Art. 25 CDT — 30 j/an base (<5 ans ancienneté)'),
        ('MALADIE',           'Congé maladie',              0,  true,  true,  'Sur certificat médical — durée selon arrêt'),
        ('MATERNITE',         'Congé maternité',           98,  true,  true,  'Art. 23 CDT — 14 semaines (98 jours)'),
        ('PATERNITE',         'Congé paternité',            3,  true,  false, 'Art. 23-2 CDT — 3 jours ouvrables'),
        ('EVENEMENT_FAMILLE', 'Evénement familial',         5,  true,  true,  'Mariage, décès, naissance — sur justificatif'),
        ('SANS_SOLDE',        'Congé sans solde',           0,  false, false, 'Sur accord de la direction')
      ON CONFLICT (code) DO NOTHING;
    `);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(`DROP TABLE IF EXISTS "rh_conge_balances" CASCADE;`);
    await queryRunner.query(`DROP TABLE IF EXISTS "rh_conge_requests" CASCADE;`);
    await queryRunner.query(`DROP TABLE IF EXISTS "rh_conge_types" CASCADE;`);
    await queryRunner.query(`DROP TABLE IF EXISTS "rh_contrats" CASCADE;`);
    await queryRunner.query(`DROP TABLE IF EXISTS "rh_employes" CASCADE;`);
    await queryRunner.query(`DROP TYPE IF EXISTS "public"."rh_conge_requests_statut_enum";`);
    await queryRunner.query(`DROP TYPE IF EXISTS "public"."rh_contrats_statut_enum";`);
    await queryRunner.query(`DROP TYPE IF EXISTS "public"."rh_contrats_type_contrat_enum";`);
    await queryRunner.query(`DROP TYPE IF EXISTS "public"."rh_employes_situation_familiale_enum";`);
    await queryRunner.query(`DROP TYPE IF EXISTS "public"."rh_employes_sexe_enum";`);
    await queryRunner.query(`DROP TYPE IF EXISTS "public"."rh_employes_statut_enum";`);
    await queryRunner.query(`DROP TYPE IF EXISTS "public"."rh_employes_type_contrat_actuel_enum";`);
  }
}
