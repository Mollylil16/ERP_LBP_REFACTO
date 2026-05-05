Tu es un expert en développement de systèmes d'information RH (SIRH).
Ta mission est de développer un SIRH complet, moderne et conforme au
Code du Travail ivoirien (Loi n° 2015-532 du 20 juillet 2015 +
Décrets 2024-898, 2024-901, 2024-902).

════════════════════════════════════════════════


════════════════════════════════════════════════
ARCHITECTURE GLOBALE
════════════════════════════════════════════════

Génère une architecture modulaire avec les dossiers suivants :

/backend
  /modules
    /admin-personnel
    /contrats
    /paie
    /temps-presences
    /evaluation
    /recrutement
    /formation
    /reporting
    /auth
    /parametres
  /middlewares
  /utils
  /config
  /db (migrations + seeds)

/frontend
  /pages ou /views
    /dashboard
    /employes
    /contrats
    /paie
    /conges
    /evaluations
    /recrutement
    /formations
    /rapports
    /parametres
    /admin
  /components
  /stores ou /context
  /services (appels API)
  /utils

════════════════════════════════════════════════
MODULE 1 — GESTION ADMINISTRATIVE DU PERSONNEL
════════════════════════════════════════════════

Développe le CRUD complet de la fiche employé avec les champs :
- Identité : nom, prénoms, date/lieu naissance, nationalité, sexe,
  situation familiale, nb enfants, CNI, CNPS
- Contact : adresse, téléphone, email pro, email perso, photo
- Pro : matricule (auto-généré), date embauche, ancienneté (calculée),
  poste, catégorie/grade, département, service, site/agence,
  type contrat, statut (actif/suspendu/sorti), responsable N+1
- Historique des postes (journal horodaté)
- Conformité Art. 4 CDT : champs VIH/handicap chiffrés (accès restreint)
- Registre d'employeur numérique — Décret 2024-902 Art.9 (3 fascicules)
- Import en masse CSV/Excel avec validation
- Export fiche PDF

Schéma DB à créer :
Tables : employees, employee_positions_history, departments,
         positions, sites, job_categories

════════════════════════════════════════════════
MODULE 2 — GESTION DES CONTRATS ET DOCUMENTS RH
════════════════════════════════════════════════

- Modèles de contrats paramétrables : CDI, CDD, Stage, Intérim
- Génération PDF automatique depuis la fiche employé
- Durée maximale CDD : 2 ans (alerte système — Art. 14-20 CDT)
- Périodes d'essai selon catégorie :
    Ouvriers     → 8 jours renouvelables 1 fois
    Employés/AM  → 1 mois renouvelable 1 fois
    Cadres       → 3 mois renouvelables 1 fois
- Alertes automatiques : fin période d'essai, fin CDD (J-30, J-15, J-7)
- Gestion des avenants avec versioning
- Coffre-fort numérique par employé (upload PDF, DOCX, JPG)
- Génération automatique d'attestations : travail, salaire, présence,
  solde de tout compte
- Conservation 10 ans minimum

Tables : contracts, contract_templates, contract_amendments,
         employee_documents, document_types

════════════════════════════════════════════════
MODULE 3 — PAIE ET RÉMUNÉRATION
════════════════════════════════════════════════

Développe un moteur de paie complet avec :

RUBRIQUES DE GAINS :
- Salaire de base (grille catégorielle)
- Prime d'ancienneté (calculée automatiquement par tranche)
- Prime de transport, de rendement, de responsabilité
- 13e mois, heures supplémentaires majorées

HEURES SUPPLÉMENTAIRES (Décret 2024-898) :
- 41e–48e heure : +15%
- Au-delà 48h    : +50%
- Travail nuit (21h–5h) : +25%
- Dimanche       : +50%
- Jours fériés   : +100%

DÉDUCTIONS LÉGALES :
- CNPS Retraite    : salarial 3,2% / patronal 7,7% / plafond 1 647 315 FCFA
- CNPS AT          : patronal 2% à 5%
- CNPS Famille     : patronal 5,75% / plafond 70 000 FCFA
- CMU              : salarial 2% / patronal 2%
- ITS              : barème progressif DGI (tranches à paramétrer)
- Contribution Nationale : 1,5% salaire brut
- Avances sur salaire, absences injustifiées

WORKFLOW PAIE :
1. Import présences/absences
2. Calcul automatique brut + déductions
3. Vérification SMIG (alerte si salaire < SMIG en vigueur)
4. Validation RH → DAF
5. Génération bulletins PDF (mentions obligatoires légales)
6. Fichier virement bancaire
7. États CNPS et DGI auto-générés
8. Archivage 10 ans

Tables : payroll_runs, payroll_lines, pay_slips, pay_components,
         pay_rules, salary_advances, payroll_declarations

════════════════════════════════════════════════
MODULE 4 — GESTION DU TEMPS ET DES PRÉSENCES
════════════════════════════════════════════════

- Durée légale : 40h/semaine ou 2 352h/an (Décret 2024-898)
- Modes de pointage :
    Badgeuse (API webhook)
    Mobile (géolocalisation GPS)
    Biométrie (intégration SDK)
    Saisie manuelle manager (avec justificatif)
- Calcul auto : heures travaillées, HS, retards, absences
- Gestion des anomalies avec workflow de régularisation
- Planning et horaires par équipe / shift
- Calendrier jours fériés CI (liste complète à intégrer + fêtes islamiques)

CONGÉS PAYÉS (Art. 25 CDT) :
- Calcul : 2,5 jours ouvrables/mois travaillé
- Majoration ancienneté :
    < 5 ans  → 30 jours
    5–10 ans → 42 jours
    10–15 ans→ 54 jours
    > 15 ans → 66 jours
- Workflow demande : Employé → Manager → RH
- Types : congé annuel, maladie, maternité (14 semaines),
          paternité (10 jours), événement familial, formation,
          absence injustifiée, suspension
- Compteurs de soldes en temps réel
- Notification email/SMS à chaque étape

Tables : attendance_records, leave_requests, leave_types,
         leave_balances, work_schedules, public_holidays,
         attendance_anomalies

════════════════════════════════════════════════
MODULE 5 — ÉVALUATION ET PERFORMANCE
════════════════════════════════════════════════

- Campagnes d'évaluation : annuelle, semestrielle, trimestrielle,
  fin de période d'essai
- Types : auto-évaluation, N+1, évaluation 360°
- Grilles par catégorie (pondérations configurables) :
    Résultats/objectifs        : 40%
    Compétences métier         : 25%
    Compétences comportementales: 20%
    Conformité/discipline      : 10%
    Développement personnel    :  5%
- Objectifs SMART avec suivi de progression
- Workflow signature : Évalué → Évaluateur → RH → Direction
- Plan de Développement Individuel (PDI) auto-généré
- Lien avec module paie (primes de performance)
- Lien avec module formation (besoins détectés)
- Tableaux de bord : distribution notes, top performers, alertes

Tables : evaluation_campaigns, evaluation_forms, evaluations,
         evaluation_criteria, objectives, development_plans

════════════════════════════════════════════════
MODULE 6 — RECRUTEMENT ET INTÉGRATION (ATS)
════════════════════════════════════════════════

- Fiches de poste avec compétences requises
- Publication offres (portail interne + export job boards)
- Formulaire candidature : upload CV + lettre de motivation
- Présélection automatique par critères
- Pipeline candidats : Nouveau → Présélectionné → Entretien →
                       Refusé / Retenu → Embauché
- CVthèque consultable et filtrée
- Emails automatiques (accusé, convocation, réponse)
- Grilles d'évaluation des entretiens
- Conformité Art. 4 CDT : aucun champ discriminatoire
- Onboarding : checklist personnalisable, suivi période d'essai
- Visite médicale d'embauche (obligatoire — à tracker)
- Budget et KPI recrutement (délai moyen, coût par recrutement)

Tables : job_postings, applications, candidates, interviews,
         interview_evaluations, onboarding_checklists,
         onboarding_tasks

════════════════════════════════════════════════
MODULE 7 — FORMATION ET DÉVELOPPEMENT
════════════════════════════════════════════════

- Plan de formation annuel (consolidation besoins, budget par dept)
- Catalogue formations internes et externes
- Sessions : présentiel / distanciel / e-learning / mixte
- Inscriptions avec validation manager
- Évaluation satisfaction et impact post-formation
- Référentiel compétences par famille de postes
- Cartographie compétences employé (radar)
- Détection écarts compétences requises vs actuelles
- Historique certifications et diplômes

Tables : training_plans, training_sessions, training_enrollments,
         competencies, employee_competencies, certifications

════════════════════════════════════════════════
MODULE 8 — TABLEAUX DE BORD ET REPORTING
════════════════════════════════════════════════

KPIs temps réel :
- Effectif total / par service / par type contrat
- Taux d'absentéisme = (Jours absents / Jours théoriques) × 100
- Taux de turnover = (Départs / Effectif moyen) × 100
- Masse salariale et coût moyen par employé
- Délai moyen de recrutement
- Taux de réalisation du plan de formation
- Note moyenne d'évaluation
- CDD expirant dans 30 jours

RAPPORTS LÉGAUX OBLIGATOIRES :
- Bilan social annuel
- Déclaration main-d'œuvre (avant 31 janvier — Décret 2024-902 Art.6)
- États CNPS mensuel et annuel
- Registre employeur (3 fascicules — Décret 2024-902 Art.9)
- Rapport heures supplémentaires

Exports : PDF, Excel, CSV
Graphiques : courbes, histogrammes, camemberts, jauges

════════════════════════════════════════════════
MODULE 9 — PARAMÈTRES DE CONFIGURATION
════════════════════════════════════════════════

- Organigramme dynamique (directions, départements, services, sites)
- Grilles salariales par catégorie professionnelle
- SMIG configurable (valeur mise à jour annuellement)
- Rubriques de paie paramétrables (formules configurables)
- Taux de cotisations CNPS, ITS, CMU, CN (mis à jour réglementairement)
- Modèles de contrats et attestations personnalisables
- Workflows de validation configurables par processus
- Calendrier entreprise (jours fériés CI + jours de fermeture)
- Gestion multi-entités / multi-sites

Tables : company_settings, pay_rules, salary_grids,
         workflow_configs, contract_templates, notification_templates

════════════════════════════════════════════════
MODULE 10 — SÉCURITÉ ET ACCÈS
════════════════════════════════════════════════

RÔLES :
- super_admin  → accès total
- rh_admin     → tous modules RH
- payroll      → paie, absences, pointage
- manager      → équipe directe uniquement
- employee     → profil personnel uniquement
- direction    → dashboards agrégés
- auditor      → lecture limitée, accès temporaire tracé

SÉCURITÉ :
- JWT + refresh token (expiration 15 min / 7 jours)
- MFA obligatoire pour super_admin, rh_admin, payroll
- SSO OAuth2 / LDAP (optionnel)
- Mot de passe : min 10 caractères, renouvellement 90 jours
- Verrouillage après 5 tentatives
- Session timeout configurable
- Chiffrement données sensibles (AES-256 au repos, TLS 1.3 en transit)
- Journal d'audit complet (qui, quoi, quand, depuis quelle IP)
- Alerte sur actions suspectes (export massif, accès inhabituel)
- Données VIH/handicap : chiffrement supplémentaire + accès restreint
  (conformité Art. 4 CDT)

Tables : users, roles, permissions, role_permissions,
         audit_logs, sessions

════════════════════════════════════════════════
CONTRAINTES LÉGALES TRANSVERSALES À INTÉGRER
════════════════════════════════════════════════

Implémenter dans le code les garde-fous suivants :

1. Art. 3 CDT  → Bloquer tout contrat sans consentement libre
2. Art. 4 CDT  → Aucun formulaire ne peut avoir de champ
                 race/religion/opinion politique obligatoire
3. Art. 8 CDT  → Paramètre qui violerait le CDT = rejeté avec message
4. Art. 25 CDT → Solde congé ne peut être négatif sans autorisation RH
5. Art. 31 CDT → Alerte si salaire < SMIG avant validation bulletin
6. Décret 2024-898 → Calcul HS automatique selon barème légal exact
7. Décret 2024-902 → Déclaration main-d'œuvre bloquée si > 31 janvier
                     sans validation forcée de la direction

════════════════════════════════════════════════
ORDRE DE DÉVELOPPEMENT RECOMMANDÉ
════════════════════════════════════════════════

Phase 1 : Auth + Paramètres + Organigramme
Phase 2 : Gestion administrative du personnel (CRUD employés)
Phase 3 : Contrats + Documents
Phase 4 : Paie (moteur de calcul + bulletins)
Phase 5 : Temps + Présences + Congés
Phase 6 : Recrutement + Onboarding
Phase 7 : Évaluation + Formation
Phase 8 : Dashboards + Reporting + Rapports légaux
Phase 9 : Sécurité avancée + Audit + Tests
Phase 10 : Optimisation + Documentation API

════════════════════════════════════════════════
LIVRABLES ATTENDUS PAR MODULE
════════════════════════════════════════════════

Pour chaque module, génère :
✅ Migrations de base de données
✅ Modèles / entités avec relations
✅ Services métier (logique de calcul)
✅ Controllers / routes API documentés
✅ Middlewares de validation et d'autorisation
✅ Tests unitaires (fonctions de calcul paie/congés)
✅ Composants frontend avec formulaires et tableaux
✅ Gestion des erreurs avec messages en français
✅ Seed data (données de test réalistes CI)

════════════════════════════════════════════════
COMMENCE PAR :
════════════════════════════════════════════════

1. Génère la structure complète des dossiers du projet
2. Configure la base de données et crée TOUTES les migrations
3. Développe le module Auth complet (inscription, login, MFA, rôles)
4. Développe le module Administration du Personnel (CRUD employé)
5. Demande-moi confirmation avant de passer au module suivant

Pose-moi des questions si tu as besoin de précisions sur
le stack technique ou les spécificités métier ivoiriennes.