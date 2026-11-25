# Guide de lecture du code

Ce guide décrit l'application telle qu'elle est organisée dans le dépôt. Elle suit le modèle Symfony classique : une requête arrive dans un contrôleur, qui délègue le stockage aux repositories/à Doctrine et les règles d'intégration aux services, puis rend une page Twig ou une réponse JSON.

## Vue d'ensemble

```text
Navigateur
   │ HTTP(S)
   ▼
Caddy + FrankenPHP (Docker)
   │ public/index.php
   ▼
Symfony : route → contrôleur
   ├── formulaires / sécurité
   ├── services ───────────────→ MusicBrainz / Cover Art Archive
   ├── repositories → Doctrine ORM → MySQL
   └── Twig → HTML
```

Pour les routes, consulte `docker compose exec php php bin/console debug:router`.

## Parcours principaux

### Explorer le catalogue

`CatalogController` reçoit les filtres de recherche, valide les paramètres simples et délègue les requêtes à `AlbumRepository`, `ArtistRepository` et `GenreRepository`. Les pages sont rendues par `templates/catalog/`. La route d'autocomplétion renvoie du JSON. La route de résolution d'album exige `ROLE_USER` et appelle le service de catalogue.

### Ajouter un album et une écoute

`DiaryController::index` vérifie la session, valide le jeton CSRF et les champs postés, puis demande à `MusicCatalogService` de retrouver ou créer les métadonnées musicales. Le contrôleur crée ensuite une entité `Review` associant l'utilisateur à l'album : c'est une écoute datée, avec une note, un commentaire et un favori. Chaque écoute reste distincte, y compris si le même utilisateur a déjà écouté le même album.

`DiaryController::edit` ne permet de modifier que la note, le commentaire et l'état favori. Le fait de chercher l'entrée par son identifiant **et** par l'utilisateur courant évite de laisser un compte modifier l'écoute d'un autre compte.

Les vues de ces pages sont dans `templates/diary/`. Le formulaire est actuellement écrit à la main en HTML et validé dans le contrôleur, contrairement aux formulaires d'inscription et de profil qui utilisent le composant Symfony Form.

### Enrichir les données musicales

`MusicCatalogService` orchestre la création et la réutilisation des artistes, genres, albums et pistes. `MusicBrainzClient` encapsule les requêtes HTTP, leur espacement, les nouvelles tentatives et la lecture des réponses. Les données d'un tiers ne sont pas garanties : une date ou une pochette peut manquer.

La commande `app:seed-musicbrainz` importe une sélection prédéfinie et accepte `--limit`. Elle est distincte du démarrage normal de l'application.

### Créer un compte et se connecter

`RegistrationFormType` vérifie les champs et l'acceptation des conditions. `RegistrationController` hache le mot de passe avec le service Symfony avant de sauvegarder l'utilisateur.

La configuration de `config/packages/security.yaml` définit le fournisseur de comptes (adresse e-mail), les firewalls et les règles d'accès. Les deux authentificateurs distinguent les formulaires de connexion du site et de l'administration ; `AppAuthenticationEntryPoint` choisit la bonne page de connexion. Les mots de passe persistés sont des hachages, jamais des mots de passe en clair.

### Profil et administration

`UserController` traite le formulaire de profil et l'avatar. `DashboardController` et les contrôleurs CRUD de `src/Controller/Admin/` construisent l'interface EasyAdmin. L'accès à `/admin` est limité à `ROLE_ADMIN` par la configuration de sécurité.

## Modèle de données

| Entité | Rôle et associations principales |
|---|---|
| `User` | Compte, informations de profil, rôles et plusieurs écoutes (`Review`) |
| `Artist` | Artiste ayant plusieurs albums |
| `Genre` | Genre partagé par plusieurs albums |
| `Album` | Appartient à un artiste et à un genre ; possède des pistes et des avis |
| `Track` | Piste d'un album, avec numéro et durée |
| `Review` | Écoute d'un album par un utilisateur, avec note, date, commentaire et favori |

Les attributs Doctrine des entités définissent les relations et les colonnes. Les migrations versionnées dans `migrations/` traduisent l'évolution de ce modèle en changements SQL. Une fois une migration utilisée dans un environnement partagé, on en ajoute une nouvelle plutôt que de réécrire l'historique déjà appliqué.

## Repères dans les dossiers

- `src/Controller/` : routes et orchestration HTTP.
- `src/Controller/Admin/` : tableau de bord et CRUD EasyAdmin.
- `src/Entity/` : objets persistés et relations Doctrine.
- `src/Repository/` : requêtes de lecture et filtres.
- `src/Service/` : logique d'accès au catalogue musical externe.
- `src/Form/` : formulaires Symfony pour inscription et profil.
- `src/Security/` : authentification et redirection vers les formulaires de connexion.
- `templates/` : vues Twig.
- `config/` : services, routes et configuration des bundles.
- `migrations/` : historique de schéma SQL.
- `Dockerfile`, `compose*.yaml`, `frankenphp/` : construction et exécution des conteneurs.

## Démarrage et commandes

En local, `docker compose up --build --wait` construit l'image et démarre les services. Le fichier `compose.override.yaml` ajoute le mode développement. L'entrypoint installe les dépendances si nécessaire, attend la base et exécute les migrations au démarrage.

```powershell
docker compose exec php php bin/console debug:router
docker compose exec php php bin/console doctrine:migrations:status
docker compose exec php php bin/console app:seed-musicbrainz --limit=5
```

## Notes de lecture

- `check.md` contient des notes de travail, pas une spécification complète. Il mentionne notamment la difficulté d'obtenir certaines dates de sortie. Le code rend `release_date` nullable et lève une erreur si la réponse reçue a un format de date non pris en charge.
- Les fichiers `docs/*.md` d'origine documentent principalement le modèle Symfony Docker (TLS, production, MySQL, dépannage, etc.). Ils restent utiles pour l'infrastructure, mais ne décrivent pas le métier de trackd.
- La liste et la priorité des simplifications possibles sont dans le [rapport d'analyse](rapport-analyse.md).
