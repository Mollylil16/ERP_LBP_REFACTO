---
title: "Guide — Tirer les états (PDF/Excel) & soumettre à la direction"
project: "LBP"
version: "1.0"
---

## Objectif

Ce guide explique **où aller** et **quoi cliquer** pour :

- tirer un **état journalier / hebdomadaire / mensuel / annuel** en **PDF** ou **Excel**
- tirer les **états caisse** (jour / consolidé / retraits)
- **soumettre un rapport** à la direction (DG / Assistant DG) depuis la supervision

> Important : certains menus n’apparaissent pas si votre rôle n’a pas la permission.  
> Si vous ne voyez pas un menu cité, c’est généralement un **problème de droits**.

---

## 1) État “complet” colis (Groupage + Autres envois) — PDF/Excel

### Où aller

- Menu : **Rapports & analyse → Rapports**
- Page : **Rapports Colis**
- URL : `/#/colis/rapports`

### Ce que contient l’état

- Colis **Groupage** + **Autres envois**
- **Clients** (expéditeur) + **destinataire**
- **Trafic**, **mode d’envoi**, **date**
- **Montant** estimé (à partir des marchandises : poids/prix/emballage/assurance)
- **Poids total** (Kg) et **nombre de colis** (physiques) si les marchandises sont renseignées
- (Selon configuration) informations facture/paiement si présentes

### Tirer l’état **journalier**

1. Ouvrir **Rapports Colis**
2. Dans **Période**, choisir **Aujourd’hui**
3. Cliquer **Générer le rapport**
4. Cliquer **Exporter PDF** ou **Exporter Excel**

### Tirer l’état **hebdomadaire**

1. Ouvrir **Rapports Colis**
2. Dans **Période**, choisir **les 7 derniers jours**
3. **Générer le rapport**
4. **Exporter**

### Tirer l’état **mensuel**

1. Ouvrir **Rapports Colis**
2. Choisir **Mois en cours** (ou “mois dernier”)
3. **Générer le rapport**
4. **Exporter**

### Tirer l’état **annuel**

1. Ouvrir **Rapports Colis**
2. Choisir **Année en cours**
3. **Générer le rapport**
4. **Exporter**

---

## 2) État caisse (journalier) — PDF

### Où aller

- Menu : **Facturation & trésorerie → Gestion Caisse → Suivi Caisse**
- URL : `/#/caisse/suivi`

### Tirer le PDF “État du jour”

1. Ouvrir **Suivi Caisse**
2. Sélectionner la **caisse** (si vous en voyez plusieurs)
3. Vérifier la date (c’est “aujourd’hui”)
4. Cliquer **Exporter état du jour (PDF)**

> Ce PDF est brandé LBP (logo + en-tête/pied) et liste les mouvements + résumé du jour.

---

## 3) Journée consolidée (caisses par agence) — PDF/Excel

### Où aller

- Menu : **Facturation & trésorerie → Gestion Caisse → Journée consolidée (par agence)**
- URL : `/#/caisse/consolidee`

### Exporter

1. Choisir la **date**
2. Cliquer **Exporter PDF** ou **Exporter Excel**

---

## 4) Suivi des retraits (décaissements) — PDF/Excel

### Où aller

- Menu : **Facturation & trésorerie → Gestion Caisse → Suivi des Retraits**
- URL : `/#/caisse/retraits`

### Exporter

1. Choisir la **période**
2. (Optionnel) filtrer par **caisse**
3. Cliquer **Exporter PDF** ou **Exporter Excel**

---

## 5) Points journaliers exploitation — PDF/Excel + soumission/validation

### Où aller (Chef d’agence / Exploitation)

- Menu : **Exploitation → Points journaliers**
- URL : `/#/exploitation/points-journaliers`

### Exporter

1. Filtrer par **statut** si besoin (BROUILLON / SOUMIS / VALIDE / REJETE)
2. Cliquer **Exporter PDF** ou **Exporter Excel**

### Soumettre un point (par agence)

- Menu : **Exploitation → Nouveau point journalier**
- URL : `/#/agence/point-journalier/nouveau`

1. Choisir la **date du point**
2. Renseigner le **total recettes** (et observations si besoin)
3. (Optionnel) sélectionner les **crédits du jour**
4. Cliquer **Soumettre à l’agent exploitation**

### Validation (Agent exploitation / Caissière principale)

1. Aller sur **Points journaliers**
2. Filtrer sur **SOUMIS**
3. Cliquer **Valider** ou **Rejeter** (motif obligatoire)

---

## 6) Soumettre un rapport à la direction (DG / Assistant DG)

### Où aller

- Menu : **Supervision réseau**
- Onglet : **Rapports direction**
- URL : `/#/supervision`

### Étapes

1. Aller à **Supervision réseau**
2. Ouvrir l’onglet **Rapports direction**
3. Dans “Soumettre un rapport”, choisir :
   - **Type** (caisse, activité, anomalies, performance…)
   - **Libellé période** (jour / semaine / mois / trimestre / année)
   - **Période couverte** (dates)
   - (Optionnel) **Agence**
4. Cliquer **Soumettre au directeur**

> Une notification est envoyée à la direction (DG / Assistant DG) selon la configuration.

---

## Raccourci recommandé (pour éviter “on ne sait pas où aller”)

- Depuis le **Dashboard**, utilisez le bouton **“État du jour (PDF/Excel)”** (quand il est visible).  
  Il ouvre directement `/#/colis/rapports?preset=jour`.

