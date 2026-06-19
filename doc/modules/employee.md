# Architecture du module Espace employé

Le module utilise désormais la même convention que RH et Administration :

```text
EmployeeNavigation
  -> EmployeeBaseController
  -> Page Object
  -> vue avec $page
  -> composants
  -> layout module
```

## Fichiers principaux

- `app/Controllers/Employee/EmployeeBaseController.php`
- `app/Controllers/Employee/EmployeePortalController.php`
- `app/View/Navigation/EmployeeNavigation.php`
- `app/View/Pages/Employee/`
- `app/View/Components/Employee.php`
- `app/View/Components/EmployeeRequestForms.php`
- `views/employee/`

Les vues ne préparent plus les dates, les timelines, les formulaires
d’explication ou les documents. Elles composent `Ui`, `Dashboard`,
`EmployeeRequestList`, `EmployeeRequestForms` et `Employee`.

Il n’existe plus de `views/employee/_navigation.php`.

Chaque vue reçoit uniquement un objet `$page` :

- `DashboardPage`
- `RequestFormPage`
- `RequestShowPage`

Les formulaires générés dans les composants incluent leur jeton CSRF. Le
contrôle Santé & Tests reconnaît également `Form::hidden('_csrf_token', ...)`.
