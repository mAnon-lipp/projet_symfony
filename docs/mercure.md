# Mercure avec FrankenPHP et Docker

Ce document décrit la configuration Mercure du projet, les problèmes rencontrés
et les corrections appliquées. Mercure n'est pas exécuté dans un conteneur
séparé : il est intégré comme module Caddy dans l'image FrankenPHP du service
`php`.

## 1. Architecture retenue

Le service `php` regroupe les composants suivants :

- FrankenPHP pour exécuter Symfony ;
- Caddy pour servir HTTP/HTTPS ;
- le module Mercure pour publier et distribuer les mises à jour en temps réel.

Le point d'entrée Mercure est :

```text
/.well-known/mercure
```

La configuration du hub se trouve dans
[`frankenphp/Caddyfile`](../frankenphp/Caddyfile). Le fichier est monté en
lecture seule dans le conteneur de développement par
[`compose.override.yaml`](../compose.override.yaml), ce qui permet de modifier
la configuration puis de redémarrer le service sans reconstruire toute
l'application.

## 2. Variables importantes

Les variables sont définies dans [`compose.yaml`](../compose.yaml) :

| Variable | Rôle | Valeur locale par défaut |
| --- | --- | --- |
| `MERCURE_URL` | URL utilisée par l'application pour publier vers le hub | `http://localhost/.well-known/mercure` |
| `MERCURE_PUBLIC_URL` | URL annoncée aux clients qui s'abonnent | `https://localhost:443/.well-known/mercure` |
| `MERCURE_JWT_SECRET` | Secret Symfony pour les JWT Mercure | valeur de développement à remplacer |
| `MERCURE_PUBLISHER_JWT_KEY` | Clé JWT acceptée pour publier | même secret que ci-dessus |
| `MERCURE_SUBSCRIBER_JWT_KEY` | Clé JWT acceptée pour s'abonner | même secret que ci-dessus |
| `SERVER_NAME` | Nom d'hôte déclaré par Caddy | `localhost` |

En production, le secret par défaut ne doit jamais être conservé. Il faut
définir `CADDY_MERCURE_JWT_SECRET` avec une valeur aléatoire longue, ainsi que
`SERVER_NAME` avec le domaine réel.

## 3. Problèmes rencontrés

### 3.1 URL interne `http://php/...`

L'ancienne valeur par défaut de `MERCURE_URL` était :

```text
http://php/.well-known/mercure
```

Cette URL utilise le nom DNS interne du service Docker. Elle peut sembler
logique depuis un conteneur, mais Caddy/FrankenPHP sert le site avec le
`SERVER_NAME` public et l'application utilise le même service pour joindre le
hub. Selon la résolution d'hôte, le schéma et le routage actifs, cela pouvait
provoquer des erreurs de publication ou un accès différent de celui des
clients.

La valeur par défaut a été remplacée par :

```text
http://localhost/.well-known/mercure
```

Le routage reste effectué par Caddy dans le conteneur `php`, sans créer un
service Mercure supplémentaire. Pour un environnement personnalisé, la valeur
peut toujours être remplacée par `CADDY_MERCURE_URL`.

### 3.2 `SERVER_NAME` contenait `php:80`

L'ancienne configuration déclarait deux hôtes :

```text
SERVER_NAME: ${SERVER_NAME:-localhost}, php:80
```

La déclaration de `php:80` n'était pas nécessaire pour l'accès normal à
Mercure et pouvait rendre le site Caddy ambigu, notamment entre l'hôte public,
le port HTTPS et le nom interne Docker. Elle a été retirée. La configuration
utilise désormais uniquement :

```text
SERVER_NAME: ${SERVER_NAME:-localhost}
```

Si plusieurs domaines sont réellement nécessaires, ils doivent être ajoutés
explicitement et validés dans le Caddyfile.

### 3.3 Directive `demo` dans Mercure

Le fichier de développement définissait auparavant :

```yaml
MERCURE_EXTRA_DIRECTIVES: demo
```

Cette directive a été retirée. Elle correspondait à un mode de démonstration et
ne doit pas être activée dans une configuration applicative normale. Elle
pouvait modifier le comportement du hub et masquer les problèmes de JWT ou de
publication.

Le bloc Mercure conserve les directives utiles :

- `publisher_jwt` pour authentifier les publications ;
- `subscriber_jwt` pour authentifier les abonnements ;
- `anonymous` pour autoriser les abonnements anonymes en développement ;
- `subscriptions` pour activer l'API d'abonnement.

`anonymous` doit être reconsidéré avant une mise en production.

### 3.4 Compatibilité du protocole Mercure

La directive suivante a été ajoutée dans le bloc `mercure` :

```text
protocol_version_compatibility 8
```

Elle force la compatibilité avec la version de protocole attendue par les
clients Mercure utilisés par le projet et évite les incompatibilités entre la
version du module Caddy et les clients SSE.

## 4. Ce qui a été retiré et ce qui est conservé

### Retiré

| Élément | Raison |
| --- | --- |
| `MERCURE_EXTRA_DIRECTIVES: demo` | Supprimer le mode démonstration inutile et potentiellement perturbateur |
| `php:80` dans `SERVER_NAME` | Éviter un hôte Caddy supplémentaire non requis |
| `http://php/.well-known/mercure` comme URL par défaut | Utiliser le point d'entrée cohérent avec le site servi par Caddy |

### Conservé ou ajouté

| Élément | Rôle |
| --- | --- |
| Module Mercure dans `frankenphp/Caddyfile` | Hub intégré à FrankenPHP, sans conteneur séparé |
| Clés JWT publisher/subscriber | Authentification des publications et abonnements |
| `MERCURE_URL` et `MERCURE_PUBLIC_URL` | Séparer l'URL utilisée par l'application de celle annoncée aux clients |
| `protocol_version_compatibility 8` | Compatibilité explicite du protocole |
| Redaction du paramètre `authorization` dans les logs | Éviter d'écrire un JWT dans les journaux |
| Volumes `caddy_data` et `caddy_config` | Conserver la configuration et les certificats Caddy |

Le projet actuel n'utilise pas de service Docker Mercure indépendant. Il ne
faut donc pas ajouter `mercure` dans `compose.yaml` en parallèle du module
Caddy, sous peine de dupliquer le hub et de compliquer le routage.

## 5. Commandes de diagnostic et de validation

Les commandes doivent être lancées depuis la racine du projet.

### Vérifier la configuration Compose fusionnée

```console
docker compose config
```

Cette commande permet notamment de vérifier les valeurs finales après fusion de
`compose.yaml` et `compose.override.yaml`, sans démarrer les services.

### Construire et démarrer l'environnement

```console
docker compose build --pull
docker compose up --wait
```

Pour repartir d'une image complètement reconstruite :

```console
docker compose build --pull --no-cache
docker compose up --wait
```

### Vérifier l'état des services

```console
docker compose ps
docker compose logs php
docker compose logs --follow php
```

### Valider le Caddyfile dans FrankenPHP

La commande importante utilisée pour contrôler la syntaxe et le chargement du
module Mercure est :

```console
docker compose exec php frankenphp validate --config /etc/frankenphp/Caddyfile
```

Une validation réussie doit se terminer sans erreur. Si le conteneur n'est pas
en cours d'exécution, lancer d'abord `docker compose up --wait`.

### Inspecter les variables réellement injectées

```console
docker compose exec php printenv MERCURE_URL
docker compose exec php printenv MERCURE_PUBLIC_URL
docker compose exec php printenv SERVER_NAME
```

Ne pas afficher le secret JWT dans un terminal partagé ou dans un rapport.

### Redémarrer après une modification

Le Caddyfile étant monté depuis l'hôte en développement :

```console
docker compose restart php
docker compose exec php frankenphp validate --config /etc/frankenphp/Caddyfile
```

### Tester le point d'entrée HTTP

En HTTP local :

```console
curl.exe -i http://localhost/.well-known/mercure
```

En HTTPS local :

```console
curl.exe -k -i https://localhost/.well-known/mercure
```

Une réponse `401` ou une réponse indiquant qu'un JWT est requis peut être
normale : elle prouve que la route Mercure est atteinte. Une réponse `404`
indique plutôt un problème de Caddyfile, de montage du fichier ou de routage.

### Arrêter proprement l'environnement

```console
docker compose down --remove-orphans
```

Ne pas utiliser `docker compose down --volumes` pendant un simple diagnostic :
cela supprimerait notamment les volumes Caddy et les données persistantes
associées aux services.

## 6. Diagnostic par symptôme

### `frankenphp validate` échoue

1. Vérifier l'indentation du bloc `mercure`.
2. Vérifier que les directives sont compatibles avec la version de FrankenPHP.
3. Vérifier la configuration fusionnée avec `docker compose config`.
4. Redémarrer le service après correction.

### Le navigateur reçoit `404` sur `/.well-known/mercure`

1. Vérifier que le bloc `mercure` existe dans
   [`frankenphp/Caddyfile`](../frankenphp/Caddyfile).
2. Vérifier que le fichier est bien monté dans `/etc/frankenphp/Caddyfile`.
3. Vérifier `docker compose logs php`.
4. Exécuter la validation FrankenPHP.

### La publication échoue côté Symfony

1. Vérifier `MERCURE_URL`.
2. Vérifier que `MERCURE_PUBLISHER_JWT_KEY` correspond à
   `CADDY_MERCURE_JWT_SECRET`.
3. Vérifier l'algorithme JWT si une variable `MERCURE_*_JWT_ALG` est définie.
4. Vérifier que le conteneur `php` est démarré.
5. Redémarrer le service après modification des variables.

### L'abonnement SSE échoue

1. Vérifier `MERCURE_PUBLIC_URL` dans le navigateur.
2. Vérifier le certificat HTTPS local ou utiliser HTTP uniquement en
   développement.
3. Vérifier les droits d'abonnement et le JWT subscriber.
4. Vérifier qu'aucun proxy ne supprime la connexion SSE.

### Le JWT apparaît dans les logs

Le Caddyfile contient un filtre qui remplace le paramètre de requête
`authorization` par `REDACTED`. Si un secret apparaît malgré tout, ne pas le
committer : remplacer immédiatement `CADDY_MERCURE_JWT_SECRET` et rechercher
la source du log avant de partager celui-ci.

## 7. Production

Avant un déploiement :

```console
SERVER_NAME=example.com \
CADDY_MERCURE_JWT_SECRET=<secret-aleatoire> \
docker compose -f compose.yaml -f compose.prod.yaml build --pull --no-cache

SERVER_NAME=example.com \
CADDY_MERCURE_JWT_SECRET=<secret-aleatoire> \
docker compose -f compose.yaml -f compose.prod.yaml up --wait
```

Le secret ne doit pas être écrit dans Git, dans le Caddyfile ou dans cette
documentation. Il doit être fourni par l'environnement de déploiement.

