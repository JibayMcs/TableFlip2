---
title: Dépannage
order: 5
---

# Dépannage

## Échecs au démarrage

| Symptôme | Cause probable et résolution |
|---|---|
| `FATAL: APP_KEY is not set.` dans les logs | La clé applicative manque dans l'environnement. Générez-en une avec `php artisan key:generate --show` et ajoutez-la à l'environnement. |
| HTTP 500 avec *No application encryption key has been specified* | Même cause : l'environnement n'est pas pris en compte par le conteneur. Vérifiez que le fichier d'environnement est bien chargé. |
| `Class "Redis" not found` côté worker | L'extension PHP phpredis manque dans l'image. Reconstruisez l'image après avoir récupéré les derniers changements. |
| Le conteneur logue `Waiting for redis…` pendant trente secondes puis continue | Le service Redis n'est pas joignable sur le réseau Docker. Voyez *Topologie réseau* ci-dessous. |
| La migration échoue avec `permissions table already exists` | Le fichier SQLite de stockage est dans un état incohérent, en général après un démarrage précédent qui a échoué. Stoppez la stack, supprimez le volume de stockage, puis redéployez. |

## Problèmes en cours d'exécution

| Symptôme | Cause probable et résolution |
|---|---|
| Les jobs de la queue restent en `pending` | Le conteneur worker a planté. Consultez ses logs pour identifier la cause. |
| Les exports ne se terminent jamais | Soit le worker n'est pas démarré, soit le volume de stockage n'est pas inscriptible. Inspectez le contenu de `/var/www/html/storage/app/exports` depuis l'intérieur du conteneur d'application. |
| SQLite est lisible mais les écritures échouent | Le volume a été créé en propriétaire root. Le script de démarrage du conteneur corrige la propriété à chaque boot ; vérifiez la propriété du fichier `tableflip.sqlite` à l'intérieur du volume pour confirmer. |
| Avertissements de contenu mixte dans la console du navigateur | L'application génère des URLs d'assets en `http://` alors qu'elle est servie en HTTPS. Vérifiez qu'`APP_URL` commence par `https://` et que le reverse proxy est bien déclaré comme proxy de confiance. |

## Topologie réseau

Sur certaines plateformes (Dokploy en particulier), le reverse proxy
ne voit que les conteneurs qui portent ses labels de routage sur le
réseau partagé. Le service Redis ne porte pas ces labels, il n'est
donc pas ajouté automatiquement au réseau partagé. Résultat :
l'application n'arrive pas à joindre Redis.

Le fichier Compose livré fait pointer le réseau par défaut du projet
vers le réseau externe du reverse proxy, ce qui place chaque service
directement sur ce réseau. La déclaration concernée ressemble à ceci :

```yaml
services:
  redis:
    # …
    networks:
      - dokploy-network
      - default

networks:
  default:
    external: true
    name: dokploy-network
  dokploy-network:
    external: true
```

Si un fichier Compose personnalisé a supprimé cette section,
recréez-la.

## La connexion directe à la base échoue

| Symptôme | Cause probable et résolution |
|---|---|
| `connection refused` | L'hôte est incorrect. Depuis l'intérieur d'un conteneur Docker, le `localhost` de la machine hôte est joignable via `host.docker.internal`. |
| `getaddrinfo failed` | Un problème DNS. Assurez-vous que la base cible est sur un réseau que le conteneur d'application peut résoudre. |
| Erreur d'authentification | Les identifiants sont incorrects, ou les règles d'accès basées sur l'hôte du serveur de base rejettent l'IP source. |

Lorsque le test de connexion échoue, le formulaire affiche l'erreur
brute renvoyée par le driver de base. Une lecture attentive pointe en
général directement la cause.

## Inspecter les logs

Les commandes ci-dessous diffusent les logs de chaque service.
Remplacez les noms de conteneur par ceux du déploiement réel (les
outils Docker les exposent souvent via `docker compose ps`).

```bash
# Conteneur d'application
docker logs -f <conteneur-application>

# Worker (queue:work)
docker logs -f <conteneur-worker>

# Scheduler (schedule:work)
docker logs -f <conteneur-scheduler>

# Redis
docker logs -f <conteneur-redis>
```
