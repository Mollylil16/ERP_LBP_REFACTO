# Correction RH + tests système + variables de vues

## Correctifs appliqués

- `SystemTestService` ne considère plus une redirection `302` vers `/login` comme une erreur HTTP pour les pages protégées.
- L'analyse CSRF reconnaît maintenant `Csrf::input()` et le champ `_csrf_token`.
- L'analyse des formulaires accepte les actions PHP dynamiques du type `action="<?= View::url(...) ?>"`.
- `AdminSystemTestController` encapsule les sorties parasites dans un buffer afin que l'interface `/admin/system-tests` reçoive toujours du JSON valide.
- `BaseController` injecte désormais des valeurs par défaut via `ViewBag::defaults()` avant `extract()`.
- Toutes les vues PHP reçoivent la ligne standard :
  `/** @var \App\Support\ViewBag $viewData */ $viewData ??= \App\Support\ViewBag::from(get_defined_vars());`

## Vérifications effectuées

- `php -l` OK sur tous les fichiers PHP du projet.
- Imports `use App\...` contrôlés : aucun fichier cible manquant.
- Routes contrôlées : classes et méthodes de controllers présentes.
- Contrôles RH par réflexion :
  - vues : OK
  - includes / partials : OK
  - assets : OK
  - formulaires & CSRF : OK
  - architecture composants : OK

## Note

Les pages RH protégées peuvent répondre `302` vers login quand le test HTTP ne transporte pas la session navigateur.
Ce comportement est normal et ne doit pas faire échouer le module.
