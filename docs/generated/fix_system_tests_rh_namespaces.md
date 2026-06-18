# Correctif tests système et namespaces RH

## Problèmes corrigés

- `App\Services\Rh\RhPersonnelService` référençait `DataVisibilityService` sans import complet.
- `App\Services\Admin\SystemTestService` référençait `AssetIntegrityService` sans import complet.
- L’interface `/admin/system-tests` pouvait recevoir une erreur HTML PHP au lieu d’un JSON valide lorsqu’une exception/fatal throwable survenait pendant un test module.

## Correctifs appliqués

- Ajout de `use App\Services\Support\DataVisibilityService;` dans `app/Services/Rh/RhPersonnelService.php`.
- Ajout de `use App\Services\Support\AssetIntegrityService;` dans `app/Services/Admin/SystemTestService.php`.
- Ajout d’une méthode `jsonSafe()` dans `app/Controllers/Admin/AdminSystemTestController.php` pour retourner un JSON d’échec propre en cas d’exception serveur.

## Vérifications

- `php -l` OK sur `app/`, `routes/` et `tests/`.
- Vérification statique des imports `use App\...` : aucun import App manquant détecté.
