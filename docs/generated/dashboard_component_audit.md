# Audit dashboards et composants

Date : 2026-06-18

## Dashboards corrigés

- `views/admin/dashboard.php`
  - Variables normalisées pour Intelephense : `$statistics`, `$grantedPermissions`, `$entities`.
  - Cartes KPI via `Dashboard::kpis()`.
  - Liste des entités via `Dashboard::entityList()`.
  - Carte bonnes pratiques via `Dashboard::infoCard()`.

- `views/employee/dashboard.php`
  - Variables normalisées pour Intelephense : `$employee`, `$stats`, `$attendance`, `$requests`, `$explanations`, `$documents`.
  - Cartes KPI via `Dashboard::kpis()`.
  - Sections via `Ui::section()`.
  - Pointages via `Dashboard::attendanceList()`.
  - Demandes d'explications via `Dashboard::explanationList()`.
  - Documents via `Dashboard::documentGrid()`.

- `views/modules/dashboard.php`
  - Données module normalisées avec valeurs par défaut.
  - Workflow via `Dashboard::workflow()`.

- `views/rh/dashboard.php`
  - Mode de vue normalisé.
  - KPI et alertes déjà componentisés conservés.
  - Répartition services via `Dashboard::bars()`.
  - Métriques statistiques via `Dashboard::metricPanels()`.
  - Classements via `Dashboard::ranking()`.
  - Rapports via `Dashboard::reportCards()`.

## Composants renforcés

- `app/View/Components/Dashboard.php`
  - Ajout : `entityList`, `infoCard`, `attendanceList`, `documentGrid`, `workflow`, `ranking`, `bars`, `explanationList`, `metricPanels`, `reportCards`.
- `app/View/Components/Ui.php`
  - `Ui::section()` accepte désormais l'attribut `id`.

## Vérification globale des vues

Il reste des pages non-dashboard contenant encore du HTML métier direct (`<form>`, `<article>`, cartes/listings) à migrer progressivement vers les composants :

- Formulaires directs : `views/auth/login.php`, `views/employee/request-show.php`, `views/site/contact.php`, `views/site/devis.php`, `views/site/index.php`, `views/site/tracking.php`, `views/rh/lifecycle/index.php`, `views/rh/personnel/exit.php`, `views/rh/personnel/form.php`, `views/rh/personnel/index.php`, `views/rh/personnel/mutation.php`, `views/rh/personnel/show.php`, `views/rh/settings/index.php`, `views/admin/permissions/edit.php`, `views/admin/users/form.php`, `views/admin/users/index.php`, `views/admin/users/show.php`.
- Cartes/articles directs : `views/rh/module-page.php`, `views/selection_portail/index.php`, `views/site/agences.php`, `views/site/contact.php`, `views/site/index.php`, `views/site/tracking.php`, `views/rh/lifecycle/index.php`, `views/rh/personnel/show.php`, `views/rh/settings/index.php`, `views/admin/system_tests/index.php`.
- Sections `finea-section-card` directes hors dashboards : `views/employee/request-show.php`, `views/rh/module-page.php`, `views/site/index.php`, `views/rh/lifecycle/index.php`, `views/rh/personnel/exit.php`, `views/rh/personnel/form.php`, `views/rh/personnel/index.php`, `views/rh/personnel/movements-index.php`, `views/rh/personnel/mutation.php`, `views/rh/personnel/mutations-index.php`, `views/rh/personnel/show.php`, `views/rh/settings/index.php`, `views/admin/permissions/edit.php`, `views/admin/permissions/matrix.php`, `views/admin/users/form.php`, `views/admin/users/index.php`, `views/admin/users/show.php`.

Conclusion : tous les dashboards sont corrigés et componentisés. Les autres pages ont été identifiées pour une vague de standardisation globale dédiée, sans refactor massif risqué dans ce patch.
