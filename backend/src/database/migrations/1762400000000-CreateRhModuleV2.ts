import { MigrationInterface, QueryRunner } from 'typeorm';

export class CreateRhModuleV21762400000000 implements MigrationInterface {
  name = 'CreateRhModuleV21762400000000';

  public async up(queryRunner: QueryRunner): Promise<void> {
    // 1. Create enum types
    await queryRunner.query(`
      DO $migration$
      BEGIN
        IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'rh_paie_runs_statut_enum') THEN
          CREATE TYPE "public"."rh_paie_runs_statut_enum" AS ENUM ('brouillon','calcule','valide_rh','valide_daf','cloture');
        END IF;
        IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'rh_avances_salaire_statut_enum') THEN
          CREATE TYPE "public"."rh_avances_salaire_statut_enum" AS ENUM ('en_attente','approuve','refuse','remboursee');
        END IF;
        IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'rh_presences_statut_enum') THEN
          CREATE TYPE "public"."rh_presences_statut_enum" AS ENUM ('present','absent','retard','mission','conge','ferie');
        END IF;
        IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'rh_presences_type_pointage_enum') THEN
          CREATE TYPE "public"."rh_presences_type_pointage_enum" AS ENUM ('badgeuse','mobile','manuel','biometrie');
        END IF;
        IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'rh_evaluations_type_enum') THEN
          CREATE TYPE "public"."rh_evaluations_type_enum" AS ENUM ('annuelle','semestrielle','trimestrielle','fin_essai');
        END IF;
        IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'rh_evaluations_statut_enum') THEN
          CREATE TYPE "public"."rh_evaluations_statut_enum" AS ENUM ('brouillon','en_cours','signe_evalue','signe_evaluateur','valide_rh','cloture');
        END IF;
        IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'rh_postes_statut_enum') THEN
          CREATE TYPE "public"."rh_postes_statut_enum" AS ENUM ('ouvert','en_cours','pourvu','annule');
        END IF;
        IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'rh_candidatures_statut_enum') THEN
          CREATE TYPE "public"."rh_candidatures_statut_enum" AS ENUM ('nouveau','preselectionne','entretien','retenu','refuse','embauche');
        END IF;
        IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'rh_formations_type_enum') THEN
          CREATE TYPE "public"."rh_formations_type_enum" AS ENUM ('presentiel','distanciel','elearning','mixte');
        END IF;
        IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'rh_inscriptions_formation_statut_enum') THEN
          CREATE TYPE "public"."rh_inscriptions_formation_statut_enum" AS ENUM ('en_attente','confirme','termine','annule');
        END IF;
      END
      $migration$;
    `);

    // 2. rh_config_paie
    await queryRunner.query(`
      CREATE TABLE IF NOT EXISTS "rh_config_paie" (
        "id"                           SERIAL PRIMARY KEY,
        "annee_mois"                   VARCHAR(10)    NOT NULL UNIQUE,
        "smig_mensuel"                 NUMERIC(15,2)  NOT NULL DEFAULT 75000,
        "cnps_retraite_salarial"       NUMERIC(5,4)   NOT NULL DEFAULT 0.032,
        "cnps_retraite_patronal"       NUMERIC(5,4)   NOT NULL DEFAULT 0.077,
        "cnps_retraite_plafond_annuel" NUMERIC(15,2)  NOT NULL DEFAULT 1647315,
        "cnps_at_patronal"             NUMERIC(5,4)   NOT NULL DEFAULT 0.02,
        "cnps_famille_patronal"        NUMERIC(5,4)   NOT NULL DEFAULT 0.0575,
        "cnps_famille_plafond_mensuel" NUMERIC(15,2)  NOT NULL DEFAULT 70000,
        "cmu_salarial"                 NUMERIC(5,4)   NOT NULL DEFAULT 0.02,
        "cmu_patronal"                 NUMERIC(5,4)   NOT NULL DEFAULT 0.02,
        "cn_taux"                      NUMERIC(5,4)   NOT NULL DEFAULT 0.015,
        "its_tranches"                 JSONB,
        "updated_at"                   TIMESTAMPTZ    NOT NULL DEFAULT now()
      );
    `);

    // Insert default config
    await queryRunner.query(`
      INSERT INTO "rh_config_paie" ("annee_mois","its_tranches") VALUES (
        'DEFAULT',
        '[
          {"min":0,"max":75000,"taux":0},
          {"min":75001,"max":240000,"taux":0.16},
          {"min":240001,"max":800000,"taux":0.21},
          {"min":800001,"max":2000000,"taux":0.24},
          {"min":2000001,"max":null,"taux":0.28}
        ]'::jsonb
      ) ON CONFLICT (annee_mois) DO NOTHING;
    `);

    // 3. rh_paie_runs
    await queryRunner.query(`
      CREATE TABLE IF NOT EXISTS "rh_paie_runs" (
        "id"                       SERIAL PRIMARY KEY,
        "periode"                  VARCHAR(7)    NOT NULL,
        "statut"                   "public"."rh_paie_runs_statut_enum" NOT NULL DEFAULT 'brouillon',
        "total_brut"               NUMERIC(15,2) NOT NULL DEFAULT 0,
        "total_net"                NUMERIC(15,2) NOT NULL DEFAULT 0,
        "total_charges_salariales" NUMERIC(15,2) NOT NULL DEFAULT 0,
        "total_charges_patronales" NUMERIC(15,2) NOT NULL DEFAULT 0,
        "nb_employes"              INTEGER       NOT NULL DEFAULT 0,
        "notes"                    TEXT,
        "id_valideur_rh"           INTEGER REFERENCES "lbp_users"("id") ON DELETE SET NULL,
        "date_validation_rh"       TIMESTAMPTZ,
        "id_valideur_daf"          INTEGER REFERENCES "lbp_users"("id") ON DELETE SET NULL,
        "date_validation_daf"      TIMESTAMPTZ,
        "created_at"               TIMESTAMPTZ   NOT NULL DEFAULT now(),
        "updated_at"               TIMESTAMPTZ   NOT NULL DEFAULT now()
      );
    `);

    // 4. rh_paie_lignes
    await queryRunner.query(`
      CREATE TABLE IF NOT EXISTS "rh_paie_lignes" (
        "id"                         SERIAL PRIMARY KEY,
        "id_run"                     INTEGER       NOT NULL REFERENCES "rh_paie_runs"("id") ON DELETE CASCADE,
        "id_employe"                 INTEGER       NOT NULL REFERENCES "rh_employes"("id") ON DELETE RESTRICT,
        "salaire_base"               NUMERIC(15,2) NOT NULL DEFAULT 0,
        "prime_anciennete"           NUMERIC(15,2) NOT NULL DEFAULT 0,
        "prime_transport"            NUMERIC(15,2) NOT NULL DEFAULT 0,
        "heures_sup_montant"         NUMERIC(15,2) NOT NULL DEFAULT 0,
        "autres_primes"              NUMERIC(15,2) NOT NULL DEFAULT 0,
        "salaire_brut"               NUMERIC(15,2) NOT NULL DEFAULT 0,
        "cnps_retraite_salarial"     NUMERIC(15,2) NOT NULL DEFAULT 0,
        "cmu_salarial"               NUMERIC(15,2) NOT NULL DEFAULT 0,
        "its"                        NUMERIC(15,2) NOT NULL DEFAULT 0,
        "cn"                         NUMERIC(15,2) NOT NULL DEFAULT 0,
        "avances_deduites"           NUMERIC(15,2) NOT NULL DEFAULT 0,
        "absences_deduites"          NUMERIC(15,2) NOT NULL DEFAULT 0,
        "total_deductions_salariales" NUMERIC(15,2) NOT NULL DEFAULT 0,
        "salaire_net"                NUMERIC(15,2) NOT NULL DEFAULT 0,
        "cnps_retraite_patronal"     NUMERIC(15,2) NOT NULL DEFAULT 0,
        "cnps_at_patronal"           NUMERIC(15,2) NOT NULL DEFAULT 0,
        "cnps_famille_patronal"      NUMERIC(15,2) NOT NULL DEFAULT 0,
        "cmu_patronal"               NUMERIC(15,2) NOT NULL DEFAULT 0,
        "total_charges_patronales"   NUMERIC(15,2) NOT NULL DEFAULT 0,
        "cout_total_employeur"       NUMERIC(15,2) NOT NULL DEFAULT 0,
        "alerte_smig"                BOOLEAN       NOT NULL DEFAULT false,
        "detail_calcul"              JSONB,
        "created_at"                 TIMESTAMPTZ   NOT NULL DEFAULT now(),
        "updated_at"                 TIMESTAMPTZ   NOT NULL DEFAULT now()
      );
    `);

    // 5. rh_avances_salaire
    await queryRunner.query(`
      CREATE TABLE IF NOT EXISTS "rh_avances_salaire" (
        "id"              SERIAL PRIMARY KEY,
        "id_employe"      INTEGER       NOT NULL REFERENCES "rh_employes"("id") ON DELETE CASCADE,
        "montant"         NUMERIC(15,2) NOT NULL,
        "mois_deduction"  VARCHAR(7)    NOT NULL,
        "statut"          "public"."rh_avances_salaire_statut_enum" NOT NULL DEFAULT 'en_attente',
        "motif"           TEXT,
        "id_approbateur"  INTEGER REFERENCES "lbp_users"("id") ON DELETE SET NULL,
        "created_at"      TIMESTAMPTZ   NOT NULL DEFAULT now(),
        "updated_at"      TIMESTAMPTZ   NOT NULL DEFAULT now()
      );
    `);

    // 6. rh_presences
    await queryRunner.query(`
      CREATE TABLE IF NOT EXISTS "rh_presences" (
        "id"               SERIAL PRIMARY KEY,
        "id_employe"       INTEGER       NOT NULL REFERENCES "rh_employes"("id") ON DELETE CASCADE,
        "date_presence"    DATE          NOT NULL,
        "heure_entree"     TIME,
        "heure_sortie"     TIME,
        "heures_travaillees" NUMERIC(5,2) NOT NULL DEFAULT 0,
        "heures_sup"       NUMERIC(5,2)  NOT NULL DEFAULT 0,
        "retard_minutes"   NUMERIC(5,2)  NOT NULL DEFAULT 0,
        "statut"           "public"."rh_presences_statut_enum" NOT NULL DEFAULT 'present',
        "type_pointage"    "public"."rh_presences_type_pointage_enum" NOT NULL DEFAULT 'manuel',
        "justificatif"     TEXT,
        "est_valide"       BOOLEAN       NOT NULL DEFAULT false,
        "id_validateur"    INTEGER REFERENCES "lbp_users"("id") ON DELETE SET NULL,
        "created_at"       TIMESTAMPTZ   NOT NULL DEFAULT now(),
        "updated_at"       TIMESTAMPTZ   NOT NULL DEFAULT now()
      );
    `);

    // 7. rh_jours_feries
    await queryRunner.query(`
      CREATE TABLE IF NOT EXISTS "rh_jours_feries" (
        "id"           SERIAL PRIMARY KEY,
        "date"         DATE         NOT NULL UNIQUE,
        "libelle"      VARCHAR(150) NOT NULL,
        "est_islamique" BOOLEAN     NOT NULL DEFAULT false,
        "annee"        INTEGER      NOT NULL,
        "created_at"   TIMESTAMPTZ  NOT NULL DEFAULT now()
      );
    `);

    // 8. rh_evaluations
    await queryRunner.query(`
      CREATE TABLE IF NOT EXISTS "rh_evaluations" (
        "id"                      SERIAL PRIMARY KEY,
        "id_employe"              INTEGER       NOT NULL REFERENCES "rh_employes"("id") ON DELETE CASCADE,
        "id_evaluateur"           INTEGER REFERENCES "lbp_users"("id") ON DELETE SET NULL,
        "type"                    "public"."rh_evaluations_type_enum" NOT NULL,
        "periode"                 VARCHAR(7)    NOT NULL,
        "statut"                  "public"."rh_evaluations_statut_enum" NOT NULL DEFAULT 'brouillon',
        "score_resultats"         NUMERIC(5,2),
        "score_competences_metier" NUMERIC(5,2),
        "score_comportement"      NUMERIC(5,2),
        "score_conformite"        NUMERIC(5,2),
        "score_developpement"     NUMERIC(5,2),
        "note_globale"            NUMERIC(5,2),
        "commentaire_evaluateur"  TEXT,
        "commentaire_employe"     TEXT,
        "plan_developpement"      TEXT,
        "objectifs"               JSONB,
        "created_at"              TIMESTAMPTZ   NOT NULL DEFAULT now(),
        "updated_at"              TIMESTAMPTZ   NOT NULL DEFAULT now()
      );
    `);

    // 9. rh_postes
    await queryRunner.query(`
      CREATE TABLE IF NOT EXISTS "rh_postes" (
        "id"                   SERIAL PRIMARY KEY,
        "intitule"             VARCHAR(150) NOT NULL,
        "departement"          VARCHAR(100),
        "description"          TEXT,
        "competences_requises" TEXT,
        "nb_postes"            INTEGER      NOT NULL DEFAULT 1,
        "statut"               "public"."rh_postes_statut_enum" NOT NULL DEFAULT 'ouvert',
        "id_agence"            INTEGER REFERENCES "agences"("id") ON DELETE SET NULL,
        "date_limite"          DATE,
        "publication_interne"  BOOLEAN      NOT NULL DEFAULT true,
        "created_at"           TIMESTAMPTZ  NOT NULL DEFAULT now(),
        "updated_at"           TIMESTAMPTZ  NOT NULL DEFAULT now()
      );
    `);

    // 10. rh_candidatures
    await queryRunner.query(`
      CREATE TABLE IF NOT EXISTS "rh_candidatures" (
        "id"                    SERIAL PRIMARY KEY,
        "id_poste"              INTEGER      NOT NULL REFERENCES "rh_postes"("id") ON DELETE CASCADE,
        "nom"                   VARCHAR(100) NOT NULL,
        "prenoms"               VARCHAR(200) NOT NULL,
        "email"                 VARCHAR(100),
        "telephone"             VARCHAR(20),
        "cv_url"                TEXT,
        "lettre_motivation_url" TEXT,
        "statut"                "public"."rh_candidatures_statut_enum" NOT NULL DEFAULT 'nouveau',
        "notes_recruteur"       TEXT,
        "note_entretien"        NUMERIC(5,2),
        "id_recruteur"          INTEGER REFERENCES "lbp_users"("id") ON DELETE SET NULL,
        "date_entretien"        DATE,
        "created_at"            TIMESTAMPTZ  NOT NULL DEFAULT now(),
        "updated_at"            TIMESTAMPTZ  NOT NULL DEFAULT now()
      );
    `);

    // 11. rh_formations
    await queryRunner.query(`
      CREATE TABLE IF NOT EXISTS "rh_formations" (
        "id"           SERIAL PRIMARY KEY,
        "titre"        VARCHAR(200) NOT NULL,
        "description"  TEXT,
        "type"         "public"."rh_formations_type_enum" NOT NULL DEFAULT 'presentiel',
        "organisme"    VARCHAR(150),
        "date_debut"   DATE,
        "date_fin"     DATE,
        "duree_heures" INTEGER,
        "cout"         NUMERIC(15,2),
        "places_max"   INTEGER      NOT NULL DEFAULT 0,
        "est_actif"    BOOLEAN      NOT NULL DEFAULT true,
        "annee_plan"   INTEGER,
        "created_at"   TIMESTAMPTZ  NOT NULL DEFAULT now(),
        "updated_at"   TIMESTAMPTZ  NOT NULL DEFAULT now()
      );
    `);

    // 12. rh_inscriptions_formation
    await queryRunner.query(`
      CREATE TABLE IF NOT EXISTS "rh_inscriptions_formation" (
        "id"                    SERIAL PRIMARY KEY,
        "id_formation"          INTEGER      NOT NULL REFERENCES "rh_formations"("id") ON DELETE CASCADE,
        "id_employe"            INTEGER      NOT NULL REFERENCES "rh_employes"("id") ON DELETE CASCADE,
        "statut"                "public"."rh_inscriptions_formation_statut_enum" NOT NULL DEFAULT 'en_attente',
        "note_satisfaction"     NUMERIC(5,2),
        "commentaire"           TEXT,
        "id_validateur_manager" INTEGER REFERENCES "lbp_users"("id") ON DELETE SET NULL,
        "created_at"            TIMESTAMPTZ  NOT NULL DEFAULT now(),
        "updated_at"            TIMESTAMPTZ  NOT NULL DEFAULT now()
      );
    `);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(`DROP TABLE IF EXISTS "rh_inscriptions_formation" CASCADE;`);
    await queryRunner.query(`DROP TABLE IF EXISTS "rh_formations" CASCADE;`);
    await queryRunner.query(`DROP TABLE IF EXISTS "rh_candidatures" CASCADE;`);
    await queryRunner.query(`DROP TABLE IF EXISTS "rh_postes" CASCADE;`);
    await queryRunner.query(`DROP TABLE IF EXISTS "rh_evaluations" CASCADE;`);
    await queryRunner.query(`DROP TABLE IF EXISTS "rh_jours_feries" CASCADE;`);
    await queryRunner.query(`DROP TABLE IF EXISTS "rh_presences" CASCADE;`);
    await queryRunner.query(`DROP TABLE IF EXISTS "rh_avances_salaire" CASCADE;`);
    await queryRunner.query(`DROP TABLE IF EXISTS "rh_paie_lignes" CASCADE;`);
    await queryRunner.query(`DROP TABLE IF EXISTS "rh_paie_runs" CASCADE;`);
    await queryRunner.query(`DROP TABLE IF EXISTS "rh_config_paie" CASCADE;`);
    await queryRunner.query(`DROP TYPE IF EXISTS "public"."rh_inscriptions_formation_statut_enum";`);
    await queryRunner.query(`DROP TYPE IF EXISTS "public"."rh_formations_type_enum";`);
    await queryRunner.query(`DROP TYPE IF EXISTS "public"."rh_candidatures_statut_enum";`);
    await queryRunner.query(`DROP TYPE IF EXISTS "public"."rh_postes_statut_enum";`);
    await queryRunner.query(`DROP TYPE IF EXISTS "public"."rh_evaluations_statut_enum";`);
    await queryRunner.query(`DROP TYPE IF EXISTS "public"."rh_evaluations_type_enum";`);
    await queryRunner.query(`DROP TYPE IF EXISTS "public"."rh_presences_type_pointage_enum";`);
    await queryRunner.query(`DROP TYPE IF EXISTS "public"."rh_presences_statut_enum";`);
    await queryRunner.query(`DROP TYPE IF EXISTS "public"."rh_avances_salaire_statut_enum";`);
    await queryRunner.query(`DROP TYPE IF EXISTS "public"."rh_paie_runs_statut_enum";`);
  }
}
