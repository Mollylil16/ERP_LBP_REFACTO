# 🚀 Rapport de Refactoring Backend LBP (Node.js/NestJS vers PHP MVC)

## 📌 Contexte
Le projet LBP a nécessité une refonte majeure de son système d'information (Backend). L'architecture initiale, basée sur NestJS, Prisma et React, présentait des limitations de flexibilité, de scalabilité ou d'alignement avec les compétences internes. 
L'objectif du refactoring était de reconstruire intégralement le cœur du système en **PHP Natif MVC**, de nettoyer la base de données PostgreSQL, et d'implémenter les nouvelles exigences métier.

---

## 🛠️ Phase 1 : Nettoyage et Restructuration
1. **Suppression du Frontend React** : Le code source React/TypeScript (`src/`, `package.json`, etc.) a été purgé du dépôt pour laisser un dossier propre uniquement dédié au code backend en attendant la recréation du Frontend.
2. **Architecture MVC** :
   - Mise en place d'un socle PHP robuste.
   - Routage dynamique avec protection JWT et Middlewares de Permissions.
   - Connexion PDO sécurisée.
3. **Nettoyage de la Base de Données** :
   - Suppression des tables inutiles héritées de l'ancien ORM.
   - Refonte totale du schéma via des fichiers de migrations propres (`001_colissage.sql`, `002_finance.sql`, etc.).
   - Standardisation du nommage des tables avec le préfixe `lbp_`.

---

## 📦 Phase 2 : Module Colissage & Logistique
1. **Gestion des Colis** :
   - Création de la table `lbp_colis` avec son système de `statut` dynamique (RECEPTIONNE, EN_TRANSIT, etc.).
   - Mise en place de la table `lbp_marchandises` avec `ON DELETE CASCADE`.
2. **Retrait des Colis (Sécurité renforcée)** :
   - L'API de retrait oblige désormais la saisie des informations du récupérateur (`nom_recuperateur`, `cni_recuperateur`, `telephone_recuperateur`).
3. **Inventaires des Agences** :
   - Création du système d'inventaire (`lbp_inventaires`, `lbp_inventaire_lignes`) permettant aux chefs d'agences de vérifier si chaque colis est `PRESENT`, `MANQUANT` ou `ENDOMMAGE`.
4. **Cartographie et Tracking "Style Yango"** :
   - Création de la table `lbp_expeditions` pour regrouper des colis par vol/bateau.
   - Création de la table `lbp_tracking_gps` permettant aux Agents d'Exploitation d'envoyer les coordonnées GPS (Latitude/Longitude) d'une expédition.

---

## 💶 Phase 3 : Module Finance et Caisse
1. **Gestion Multi-Devise (Double Devise)** :
   - Les factures (`lbp_factures`) gèrent désormais le paiement mixte : le système prend le `montant_xof` ou `montant_eur` et calcule la contre-valeur avec un `taux_change_eur_xof` gravé dans le marbre au moment de la transaction.
2. **Clôtures et Points Journaliers** :
   - Les caissières génèrent leur clôture de caisse quotidienne (`lbp_points_caisse`) en statut `SOUMIS`.
   - Seule la **Caissière Principale** peut la passer en `VALIDE` ou `REJETE`.
3. **La Caisse Enregistreuse Détaillée** :
   - **Générateur Automatique Sécurisé** : Le système génère automatiquement et sans collision les numéros de Fiches de Recette (`FR`), Bordereaux de Versement Internes (`BVI`) et Ordres de Décaissement (`DEC`).
   - La table `lbp_caisses` suit l'évolution stricte du Solde.
   - Les mouvements physiques d'argent (Approvisionnements, Décaissements, Entrées d'espèces/chèques/virements) sont enregistrés dans `lbp_mouvements_caisse`.

---

## 👮 Phase 4 : Sécurité, Rôles et Supervision
1. **Scoping et Permissions Globales/Locales** :
   - Les contrôleurs détectent l'agence de l'utilisateur (`id_agence`). Une caissière ne voit **que** les données de son agence.
   - Les utilisateurs ayant la permission de type `.global` (ex: La Caissière Principale, les Superviseurs) peuvent interroger l'API pour récupérer les données consolidées de toutes les agences.
2. **Comptes Multi-Utilisateurs Partagés (Ex: Agence Paris)** :
   - Implémentation du système "d'Opérateurs de Caisse". Un compte informatique générique peut être utilisé, mais chaque opération critique nécessite de taper le "Code Opérateur" de l'employé physique (`lbp_operateurs`).
3. **Outil d'Audit pour les Superviseurs** :
   - Ajout des rôles **Superviseur Général** (Tout le réseau) et **Superviseur Régional** (Son pays uniquement).
   - Module de signalement d'anomalies, demandes de justification (gelées en attente de réponse des agents), annotations discrètes (visibles que par la direction) et rapports (`lbp_signalements`, `lbp_demandes_justification`, `lbp_annotations_supervision`).

---

## 🎯 Conclusion du Backend
Le Backend LBP est désormais entièrement réécrit. L'API REST est en ligne, opérationnelle, et 100% capable de gérer la logistique internationale, les conversions financières automatiques, et la traçabilité des espèces de manière totalement autonome et sécurisée.

**Prochaine étape : Construction du Frontend PHP pour afficher ces données.**
