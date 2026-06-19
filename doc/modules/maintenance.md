# Mode maintenance des modules

Le mode maintenance est piloté depuis `/admin/system-tests`.

## Données persistées

La table `module_maintenance` contient :

- le slug du module ;
- l’état actif ou non ;
- le motif affiché aux utilisateurs ;
- l’administrateur ayant effectué la modification ;
- la date de mise à jour.

## Effets

Lorsqu’un module est en maintenance :

- sa carte du portail est grisée et non cliquable ;
- le motif est affiché ;
- un accès direct par URL retourne une page HTTP 503 ;
- sa carte Santé & Tests reste identifiable en orange ;
- les tests du module restent exécutables ;
- une réponse HTTP 503 de maintenance est considérée comme une protection
  attendue, pas comme une panne du module.

Le module Administration ne peut pas être mis en maintenance, afin de toujours
conserver l’accès à la remise en service.

## Sécurité

La modification utilise une route POST administrateur et un jeton CSRF :

```text
/admin/system-tests/maintenance/{module}
```

Le blocage réel est appliqué dans le routeur par
`ModuleMaintenanceMiddleware`. Masquer la carte du portail ne constitue donc
pas la seule protection.
