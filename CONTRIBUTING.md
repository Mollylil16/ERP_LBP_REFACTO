# Guide de contribution

Ce document explique les règles de développement du projet et les bonnes pratiques à respecter pour garder l’application propre, claire et facile à maintenir.

## 1. Objectif

Tous les développeurs doivent suivre les mêmes conventions afin de :

- garder une architecture cohérente ;
- faciliter la lecture du code ;
- réduire les erreurs ;
- rendre le projet plus simple à faire évoluer.

---

## 2. Architecture générale

Le projet suit une structure PHP native simple et organisée.

### Flux principal

```text
Route
→ Controller
→ Service
→ Repository
→ Base de données
```

### Structure des dossiers

```text
app/
├── Controllers/
├── Database/
├── Helpers/
├── Middleware/
├── Models/
├── Repositories/
└── Services/

bootstrap/
config/
routes/
views/
public/
```

---

## 3. Rôles des dossiers

### Contrôleurs

Les contrôleurs :

- reçoivent les requêtes HTTP ;
- valident les entrées simples ;
- appellent les services ;
- renvoient les vues ou les réponses.

Ils ne doivent pas :

- contenir de requêtes SQL ;
- contenir une logique métier complexe ;
- générer des blocs HTML trop volumineux.

### Services

Les services contiennent la logique métier importante, par exemple :

- connexion d’un utilisateur ;
- création d’un compte ;
- activation/désactivation d’un utilisateur.

### Repositories

Les repositories sont responsables de l’accès aux données.

Toutes les requêtes SQL doivent être centralisées ici.

### Models

Les models représentent les entités métier principales, par exemple :

- User
- UserGroup

Ils doivent rester concentrés sur la structure des données.

### Views

Les vues doivent contenir uniquement :

- HTML ;
- affichage ;
- petites conditions d’affichage.

Elles ne doivent pas contenir :

- SQL ;
- logique métier ;
- traitements complexes.

---

## 4. Frontend et styles

### CSS

Le CSS doit rester séparé du HTML.

Tous les styles doivent être placés dans :

- public/assets/css/

Éviter :

- CSS inline ;
- balises <style> dans les vues.

### JavaScript

Le JavaScript doit rester séparé du HTML.

Tous les scripts doivent être placés dans :

- public/assets/js/

Éviter :

- scripts inline volumineux ;
- logique JavaScript directement dans les vues.

---

## 5. Règles de sécurité

Les règles suivantes sont obligatoires :

- utiliser PDO avec des requêtes préparées ;
- protéger les formulaires sensibles avec CSRF ;
- échapper les données affichées ;
- journaliser les actions sensibles ;
- vérifier les permissions avant chaque accès critique.

---

## 6. Bonnes pratiques de code

- garder les fonctions courtes et lisibles ;
- nommer les variables et méthodes de façon explicite ;
- éviter les duplications ;
- faire une seule responsabilité par couche ;
- documenter les parties complexes.

---

## 7. Git et branches

### Branches recommandées

- main : version stable de production
- develop : version stable de développement
- feature/* : nouvelles fonctionnalités
- fix/* : correctifs
- refactor/* : améliorations de structure

### Exemples

```bash
feature/authentication
feature/auth-api
fix/router-normalization
refactor/database-layer
```

---

## 8. Convention de commits

Les messages de commit doivent être clairs et explicites.

### Exemples recommandés

- Add authentication controllers
- Create device repository layer
- Implement CSRF protection
- Refactor routing system
- Fix asset path generation

### À éviter

- update
- test
- fix
- modification

---

## 9. Documentation

Chaque fonctionnalité importante doit être documentée.

Documentation attendue :

```text
doc/
├── backend/
├── frontend/
└── architecture/
```

Les documents doivent expliquer :

- le rôle de la fonctionnalité ;
- la façon de la tester ;
- les points d’entrée et les dépendances.

---

## 10. Commentaires de code

Les commentaires doivent :

- être utiles ;
- expliquer le pourquoi ;
- rester lisibles ;
- éviter les commentaires inutiles.

Exemple :

```php
/**
 * Vérifie qu’un utilisateur possède bien les informations demandées.
 *
 * Cette méthode est utilisée avant tout accès aux données
 * afin d’éviter les accès non autorisés.
 */
```

---

## 11. Objectif qualité

Le projet doit rester :

- maintenable ;
- sécurisé ;
- lisible ;
- modulaire ;
- facile à faire évoluer.

---

## 12. Exemple de workflow

```bash
git add .
git commit -m "Add project contribution and architecture standards"
git push -u origin feature/project-standards
```

Si vous débutez, commencez par lire la documentation du projet, puis modifiez une petite partie avant de toucher à la logique principale.


