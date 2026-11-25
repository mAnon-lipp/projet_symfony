# Comprendre tout le dossier `src/`

Ce guide décrit chaque fichier suivi par Git dans `src/` et explique comment les pièces travaillent ensemble. `src/` contient le code PHP de l'application ; les pages HTML sont dans `templates/`, les réglages Symfony dans `config/` et la structure SQL évolue dans `migrations/`.

## 1. Comment lire une requête Symfony

```text
Navigateur
   │ requête HTTP
   ▼
Route #[Route(...)] sur un contrôleur
   ├── Formulaire Symfony : lire, convertir et valider les champs
   ├── Service : appliquer une opération métier / appeler une API externe
   ├── Repository : lire des entités dans MySQL avec Doctrine
   └── EntityManager : enregistrer les entités modifiées
          │
          ├── render(...)       → template Twig / réponse HTML
          ├── JsonResponse(...) → réponse JSON
          └── redirectToRoute(...) → redirection après succès
```

Symfony trouve automatiquement les contrôleurs et services de `src/` grâce à l'autowiring configuré dans `config/services.yaml`. Lorsqu'un contrôleur demande un `AlbumRepository`, par exemple, Symfony construit le service et injecte ses dépendances.

Les classes `Entity` représentent les lignes de la base sous forme d'objets. Les `Repository` savent comment les rechercher. Les formulaires vérifient les données fournies par le navigateur. Les contrôleurs relient ces éléments sans contenir normalement toute la logique d'accès externe ou de requête SQL.

## 2. Démarrage de l'application et commande

### [Kernel.php](../src/Kernel.php)

Le Kernel est le noyau Symfony de l'application. `MicroKernelTrait` lui permet de charger la configuration standard à partir du dossier `config/`. `getAllowedEnvs()` limite les environnements acceptés à `prod`, `dev` et `test`.

### [SeedMusicCatalogCommand.php](../src/Command/SeedMusicCatalogCommand.php)

Commande console Symfony nommée `app:seed-musicbrainz`.

- `SEED_ALBUMS` contient une sélection prédéfinie d'artistes, d'albums et de genres de repli.
- `configure()` déclare l'option `--limit`, pour limiter le nombre d'albums tentés.
- `execute()` parcourt cette sélection, appelle `MusicCatalogService` et affiche les succès ou les erreurs avec `SymfonyStyle`.
- `parseLimit()` rejette une valeur non entière ou inférieure à 1, puis la plafonne à la taille de la sélection.

L'import n'est pas lancé automatiquement par cette classe ; il faut exécuter la commande explicitement. Les requêtes réseau sont faites par le client MusicBrainz partagé avec le reste de l'application.

## 3. Contrôleurs du site

Un contrôleur reçoit les paramètres d'une route, appelle les composants nécessaires et choisit la réponse. Les routes sont déclarées par les attributs `#[Route(...)]`.

### [HomeController.php](../src/Controller/HomeController.php)

Route `/`. Les visiteurs non connectés voient `home/landing.html.twig` et quelques avis publics. Les personnes connectées voient `home/index.html.twig` avec un album mis en avant, sa moyenne et son nombre d'avis, les avis récents et les albums populaires. Le calcul de moyenne est délégué à `ReviewRepository`.

### [CatalogController.php](../src/Controller/CatalogController.php)

Expose quatre routes autour du catalogue :

- `search()` (`GET /catalog`) lit le texte, le genre et l'année, garde les valeurs utilisables et demande les albums au `AlbumRepository`. La page contient également la liste des genres.
- `detail()` (`GET /catalog/album/{id}`) charge un album ou renvoie une 404, puis affiche ses pistes triées par numéro.
- `autocomplete()` (`GET /api/catalog/autocomplete`) renvoie du JSON pour les albums et/ou artistes. La recherche ne part qu'à partir de deux caractères et le paramètre `type` est limité à `all`, `album` ou `artist`.
- `resolveAlbum()` (`POST /api/catalog/resolve-album`) exige `ROLE_USER`, lit le JSON `{artist, album}`, demande au service de catalogue de trouver ou créer l'album, puis renvoie ses principales informations en JSON.

Les réponses HTML vont dans `templates/catalog/`; les routes `/api/...` renvoient des données et ne rendent pas de page Twig.

### [DiaryController.php](../src/Controller/DiaryController.php)

Toutes les routes du journal exigent `ROLE_USER`. Le contrôleur vérifie également que l'utilisateur courant est bien une entité `User`.

- `index()` (`GET|POST /diary`) prépare une nouvelle entité `Review` et son formulaire `DiaryEntryType`. À la soumission valide, les champs artiste/album servent à `MusicCatalogService`; les autres champs remplissent l'écoute. Après sauvegarde, une redirection évite de soumettre deux fois le formulaire si la page est rechargée.
- La liste est paginée à dix écoutes. Le numéro de page est borné, et une page au-delà de la dernière redirige vers la dernière page valide.
- `edit()` (`GET|POST /diary/{id}/edit`) retrouve l'écoute par son identifiant **et** l'utilisateur propriétaire. Elle utilise le même type de formulaire en mode édition, met à jour la date de modification puis sauvegarde.

L'ajout demande artiste, album et date d'écoute. L'édition ne présente que la note, le commentaire et le favori. Les vues correspondantes sont `templates/diary/index.html.twig` et `templates/diary/edit.html.twig`.

### [RegistrationController.php](../src/Controller/RegistrationController.php)

Route `/register`. Construit `RegistrationFormType`, attend un formulaire soumis et valide, hache le mot de passe avec `UserPasswordHasherInterface`, initialise les dates de création et d'acceptation des conditions, sauvegarde le nouvel utilisateur, puis le connecte automatiquement avec l'authentificateur du site.

### [SecurityController.php](../src/Controller/SecurityController.php)

Affiche la page `/login`, en transmettant au template l'erreur de connexion précédente et le dernier identifiant saisi. La méthode `logout()` ne réalise pas elle-même la déconnexion : le firewall Symfony intercepte cette route.

### [SecurityAdminController.php](../src/Controller/SecurityAdminController.php)

Affiche `/admin/login`. Elle transmet au template EasyAdmin les noms des champs, le jeton CSRF, la route de soumission et la destination souhaitée après connexion. L'accès à la zone `/admin` reste contrôlé par les règles de sécurité, pas seulement par cette page.

### [UserController.php](../src/Controller/UserController.php)

Route `/profile`, réservée aux utilisateurs connectés. Elle lie `UserProfileFormType` à l'utilisateur courant. Si un nouvel avatar valide a été envoyé, elle crée un nom de fichier aléatoire, déplace l'image dans `public/uploads/avatars` et enregistre son nom dans le profil. Ensuite, elle demande au repository les favoris distincts et les écoutes récentes pour alimenter la page du profil.

## 4. Contrôleurs d'administration EasyAdmin

Ces classes construisent l'interface d'administration et les champs affichés dans les formulaires CRUD. L'autorisation globale de `/admin` est définie dans `config/packages/security.yaml`.

### [DashboardController.php](../src/Controller/Admin/DashboardController.php)

Déclare le tableau de bord `/admin`, ses compteurs (utilisateurs, albums, artistes, genres et avis), quelques utilisateurs et avis récents, ainsi que les entrées de menu vers les CRUD.

### [AlbumCrudController.php](../src/Controller/Admin/AlbumCrudController.php)

Décrit les champs d'un album : titre, artiste, genre, label, pochette, date de sortie et date de création. Les champs artiste et genre ont une recherche autocomplétée. La date de création est définie lors de la création et masquée dans le formulaire.

### [ArtistCrudController.php](../src/Controller/Admin/ArtistCrudController.php)

Gère le nom, l'image et la date de création d'un artiste. Le formulaire de création initialise cette dernière.

### [GenreCrudController.php](../src/Controller/Admin/GenreCrudController.php)

CRUD simple pour le nom d'un genre.

### [ReviewCrudController.php](../src/Controller/Admin/ReviewCrudController.php)

Permet aux administrateurs de modifier une écoute. La création manuelle d'un avis est désactivée ; les associations utilisateur et album sont désactivées en édition. Les dates de suivi sont affichées mais masquées dans le formulaire. `updateEntity()` actualise `updated_at` avant de confier la sauvegarde à EasyAdmin.

### [UserCrudController.php](../src/Controller/Admin/UserCrudController.php)

Gère le nom, l'adresse e-mail, les rôles et certains champs de dates d'un compte. Le mot de passe est un champ de formulaire de type password et n'est jamais affiché dans les listes. À la modification, un champ vide conserve le hash existant ; une nouvelle valeur est hachée. À la création, le champ est actuellement facultatif alors que la méthode de sauvegarde hache également une valeur vide : voir le point correspondant dans le [rapport d'analyse](rapport-analyse.md).

## 5. Entités : les objets enregistrés en base

Les attributs `#[ORM\Column]`, `#[ORM\ManyToOne]` et `#[ORM\OneToMany]` indiquent à Doctrine comment faire correspondre les propriétés PHP et les colonnes/relations SQL. Les méthodes `add...()` et `remove...()` synchronisent souvent les deux côtés d'une relation.

### [User.php](../src/Entity/User.php)

Compte de l'application et implémentation des contrats d'authentification Symfony. Il stocke e-mail, rôles, hash du mot de passe, nom, avatar, biographie et dates de statut. `getUserIdentifier()` utilise l'e-mail ; `getRoles()` garantit toujours `ROLE_USER`. `__serialize()` remplace le hash complet par une empreinte avant de placer l'utilisateur dans la session. `getAvatarUrl()` transforme un nom de fichier stocké en URL publique. Un utilisateur possède plusieurs `Review`.

### [Artist.php](../src/Entity/Artist.php)

Artiste du catalogue : nom, image, date de création et collection d'albums. `__toString()` fournit son nom aux champs de sélection EasyAdmin.

### [Genre.php](../src/Entity/Genre.php)

Genre musical : nom et collection d'albums associés. `__toString()` permet de l'afficher simplement dans l'administration.

### [Album.php](../src/Entity/Album.php)

Album du catalogue : titre, date de sortie éventuellement inconnue, pochette, label, artiste, genre et date d'ajout. Il possède des collections de pistes et d'écoutes. `getTotalDuration()` additionne les durées des pistes chargées.

### [Track.php](../src/Entity/Track.php)

Piste d'un album avec titre, numéro, durée en secondes et indicateur explicite. Une piste pointe vers un seul album. Le modèle ne stocke pas de numéro de disque distinct.

### [Review.php](../src/Entity/Review.php)

Représente **une écoute**, pas un avis unique par album : utilisateur, album, note, commentaire, date écoutée, favori et dates de suivi. `deleted_at` et `deleted_by` prévoient une suppression logique, sans que cela filtre automatiquement toutes les requêtes. Les propriétés `user_id` et `album_id` sont des relations vers des objets (`User` et `Album`), malgré le suffixe `Id` dans leurs noms/getters.

### Relations en résumé

```text
Artist 1 ─── plusieurs Album plusieurs ─── 1 Genre
                         │
                         ├── plusieurs Track
                         └── plusieurs Review plusieurs ─── 1 User
```

Un utilisateur peut donc créer plusieurs écoutes du même album à des dates différentes. L'album et le catalogue ne sont pas dupliqués pour chaque écoute.

**Attention aux méthodes de retrait :** les relations principales sont configurées comme non nulles en base, alors que certaines méthodes `remove...()` mettent le lien à `null`. Elles ne sont pas utilisées par les contrôleurs actuels ; il faudrait décider du comportement (suppression de l'enfant, relation facultative ou interdiction) avant de les appeler puis de sauvegarder.

## 6. Formulaires Symfony

Un formulaire Symfony transforme des champs HTTP en données PHP, vérifie les contraintes et produit les erreurs à afficher. `mapped: false` signifie que le champ ne correspond pas à une propriété de l'entité : le contrôleur doit le lire explicitement.

### [DiaryEntryType.php](../src/Form/DiaryEntryType.php)

Formulaire de création et d'édition d'une écoute, lié à `Review`. L'option booléenne `is_edit` change les champs présentés :

- en création : `artist` et `album` sont des champs texte non mappés ; `listenedAt` est une date convertie en `DateTimeImmutable`;
- dans les deux modes : `rating` est un choix de 1 à 5, `comment` est facultatif et `isFavorite` est une case facultative;
- en édition, artiste, album et date ne sont pas modifiables depuis ce formulaire.

Le contrôleur utilise les valeurs non mappées pour appeler MusicBrainz, tandis que Symfony place la note, le commentaire et le favori directement dans l'objet `Review`.

### [RegistrationFormType.php](../src/Form/RegistrationFormType.php)

Formulaire lié à `User`. Il valide l'e-mail, le nom d'utilisateur, l'acceptation des conditions (`agreeTerms`, non mappé) et le mot de passe (`plainPassword`, non mappé parce que le contrôleur doit d'abord le hacher).

### [UserProfileFormType.php](../src/Form/UserProfileFormType.php)

Formulaire lié à `User` pour le nom, la biographie et l'avatar. L'avatar est non mappé : un fichier reçu ne doit pas être écrit dans une colonne par Doctrine. Il est validé par taille maximale et types MIME, puis traité par `UserController`.

## 7. Repositories : les requêtes à la base

Doctrine fournit déjà des opérations génériques comme `find()`, `findBy()` et `count()`. Les repositories ci-dessous ajoutent les recherches propres à l'application.

### [AlbumRepository.php](../src/Repository/AlbumRepository.php)

- `findOneByTitleAndArtist()` réutilise un album d'après le titre sans tenir compte de la casse/espaces et de son artiste.
- `search()` applique les filtres catalogue et plafonne les résultats.
- `findForAutocomplete()` cherche soit dans les titres, soit chez les artistes ; `findAlbumsForAutocomplete()` cherche uniquement les titres.
- `findLatest()` sert à trouver les albums récemment ajoutés.
- `findMostReviewed()` classe les albums selon le nombre d'avis non supprimés.

### [ArtistRepository.php](../src/Repository/ArtistRepository.php)

`findOneByNormalizedName()` cherche un artiste après normalisation simple casse/espaces ; `findForAutocomplete()` renvoie des correspondances limitées et triées par nom.

### [GenreRepository.php](../src/Repository/GenreRepository.php)

`findOneByNormalizedName()` permet au service de réutiliser un genre plutôt que d'en créer un doublon pour chaque import.

### [ReviewRepository.php](../src/Repository/ReviewRepository.php)

- `findRecentPublic()` sélectionne les avis publics récents avec album, artiste et utilisateur déjà chargés.
- `getRatingSummaryForAlbum()` calcule moyenne et nombre de notes dans MySQL.
- `paginateForUser()` renvoie les écoutes d'un utilisateur sous forme de pages Doctrine.
- `findRecentForUser()` limite les écoutes récentes du profil.
- `findFavoriteAlbumsForUser()` renvoie un favori récent par album, en supprimant les doublons par une sous-requête `NOT EXISTS`.
- `createUserEntriesQueryBuilder()` partage la requête de base et charge album, artiste et genre pour éviter des accès répétés à ces relations dans Twig.

Les requêtes personnelles ne filtrent actuellement pas toutes `deleted_at`; cette différence est expliquée dans le rapport.

### [TrackRepository.php](../src/Repository/TrackRepository.php)

Repository Doctrine standard pour `Track`, sans méthode métier personnalisée pour l'instant. Il contient encore les exemples générés et commentés par MakerBundle.

### [UserRepository.php](../src/Repository/UserRepository.php)

Repository des comptes et implémentation de `PasswordUpgraderInterface`. Symfony peut lui demander d'enregistrer un nouveau hash lorsqu'un mot de passe doit être rehaché avec une meilleure stratégie.

## 8. Authentification et sécurité

### [AbstractAppLoginFormAuthenticator.php](../src/Security/AbstractAppLoginFormAuthenticator.php)

Classe de base commune aux deux connexions. Elle lit e-mail et mot de passe de la requête, mémorise le dernier e-mail, construit le `Passport` et ses éléments de sécurité (vérification du mot de passe, jeton CSRF, remember-me). Après connexion, elle décide si une destination mémorisée est permise ou s'il faut rediriger vers la route de succès de l'application. Elle laisse aux classes enfants les noms de routes et leur politique de destination.

### [AppAuthentificationAuthenticator.php](../src/Security/AppAuthentificationAuthenticator.php)

Authentificateur du formulaire standard. Il écoute la route `app_login`, revient normalement à `app_home` et refuse de rediriger vers une destination de l'espace `/admin`.

### [AppAdminAuthenticator.php](../src/Security/AppAdminAuthenticator.php)

Authentificateur du formulaire admin. Il écoute `admin_login`, revient normalement au tableau `admin` et ne suit que les destinations internes à `/admin`.

### [AppAuthenticationEntryPoint.php](../src/Security/AppAuthenticationEntryPoint.php)

Choisit où envoyer un visiteur anonyme qui demande une page protégée : `/admin/login` pour l'espace admin, `/login` pour les autres pages.

Ces classes réalisent l'authentification ; les autorisations sont distinctes et configurées dans `config/packages/security.yaml` (règles `ROLE_ADMIN` pour `/admin`, `ROLE_USER` pour `/diary` et les routes explicitement protégées).

## 9. Services : appels et logique partagée

### [MusicBrainzClient.php](../src/Service/MusicBrainzClient.php)

Client HTTP de l'API MusicBrainz et de Cover Art Archive. Il cherche un artiste, un album, récupère les informations détaillées de la sortie et tente d'obtenir une pochette. `request()` ajoute l'en-tête `User-Agent`, convertit la réponse JSON, espace les requêtes et retente certaines erreurs réseau/serveur. Une pochette indisponible est considérée facultative.

Le client utilise les premiers résultats de recherche quand aucun nom d'artiste exactement égal n'est trouvé et limite la recherche de sortie à un résultat. Pour des homonymes ou plusieurs éditions, il peut donc choisir une correspondance imparfaite.

### [MusicCatalogService.php](../src/Service/MusicCatalogService.php)

Orchestre l'import et la persistance du catalogue :

1. cherche ou crée l'artiste;
2. si l'artiste existe déjà en base, cherche l'album pour ce couple artiste/titre;
3. complète la pochette, le genre ou le label d'un album existant si ces informations manquent;
4. sinon, demande les métadonnées à MusicBrainz, crée album, genre et pistes;
5. enregistre les nouvelles entités ensemble au `flush()` final.

Les genres viennent des genres/tags de MusicBrainz, du genre de repli fourni par la commande, ou de `Unknown`. Une date absente est admise (`null`); les formats non vides qui ne sont pas reconnus provoquent une erreur. La durée reçue en millisecondes est convertie en secondes.

## 10. Les trois fichiers `.gitignore` dans `src`

[`Controller/.gitignore`](../src/Controller/.gitignore), [`Entity/.gitignore`](../src/Entity/.gitignore) et [`Repository/.gitignore`](../src/Repository/.gitignore) sont vides dans l'état actuel. Ils n'exécutent aucun code ; ils permettent simplement à Git de conserver ces dossiers même lorsqu'ils n'ont pas encore de fichier dans un clone ou une branche.

## 11. Ce que `src/` ne contient pas

- Les pages et leurs balises HTML sont dans `templates/`, pas dans les contrôleurs.
- Les routes de sécurité, rôles et connexions à la base sont configurés dans `config/`.
- L'historique des changements de tables est dans `migrations/`.
- Le dépôt n'a pas de dossier `tests/` visible ; les tests fonctionnels et unitaires ne sont donc pas encore présents dans l'arborescence.

Pour la liste des améliorations restantes et des points à vérifier, voir le [rapport d'analyse](rapport-analyse.md). Pour comprendre les conteneurs et démarrer le projet, voir le [README](../README.md).
