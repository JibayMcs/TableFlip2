---
title: Quickstart
order: 1
---

# Quickstart

## Lancer un test local

Le dépôt embarque un fichier Compose et un modèle d'environnement. La
séquence ci-dessous démarre une stack TableFlip complète sur la
machine locale.

```bash
# 1. Générez une clé applicative. Conservez-la pendant toute la durée
#    de vie du déploiement ; la perdre invalide tous les mots de passe
#    de connexion enregistrés.
php artisan key:generate --show

# 2. Copiez le modèle d'environnement et remplissez les placeholders.
cp .docker/.env.docker.example .env.docker
$EDITOR .env.docker

# 3. Buildez et démarrez la stack.
docker compose -f .docker/docker-compose.yml --env-file .env.docker up -d --build

# 4. Ouvrez l'application.
#    Le conteneur écoute sur le port 80 à l'intérieur du réseau Docker.
```

Le conteneur d'application effectue les étapes suivantes au premier
démarrage :

- crée le fichier SQLite de stockage s'il n'existe pas
- attend que Redis soit joignable (jusqu'à trente secondes)
- exécute les migrations de base de données
- met en cache la configuration, les routes, les vues et les listeners
  d'événements
- démarre Apache sur le port 80

## Déployer avec Dokploy

1. Créez une nouvelle application **Compose** dans Dokploy et faites-la
   pointer sur le fichier `.docker/docker-compose.yml` du dépôt.
2. Collez le contenu de `.env.docker.example` dans l'onglet
   **Environment** et remplissez les placeholders. `APP_KEY` et
   `APP_URL` sont obligatoires.
3. Ajoutez un domaine à l'application :
   - choisissez le nom d'hôte public
   - réglez le **Container Port** sur **80** (le défaut Dokploy de
     3000 ne correspond pas à l'image TableFlip)
   - activez HTTPS avec Let's Encrypt ou tout autre fournisseur de
     certificat supporté par l'installation
4. Lancez le déploiement. Dokploy build l'image, démarre Redis et
   lance les quatre services.

> **Problème fréquent au premier déploiement.** Lorsque la stack
> Compose utilise un réseau par défaut spécifique au projet, le
> reverse proxy ne voit pas le service Redis et l'application n'arrive
> pas à s'y connecter. Le fichier Compose livré déclare le réseau par
> défaut du projet comme un réseau externe `dokploy-network`, ce qui
> place chaque service directement sur le réseau partagé. Conservez
> cette section telle quelle.

## Utiliser TableFlip comme remplaçant phpMyAdmin

Lorsque TableFlip est dédié à un seul serveur de base, le formulaire
de connexion peut être réduit à un nom d'utilisateur et un mot de
passe en verrouillant les autres champs :

```env
AUTH_BREEZE_ENABLED=false
AUTH_DIRECT_DB_ENABLED=true

TABLEFLIP_ALLOWED_DB_HOSTS=database.example.com
TABLEFLIP_ALLOWED_DB_DRIVERS=mysql
TABLEFLIP_ALLOWED_DB_NAMES=production
TABLEFLIP_REQUIRE_DB_NAME=true
```

La page de connexion affiche uniquement les champs **nom
d'utilisateur** et **mot de passe**. Les champs hôte, driver et base
sont pré-remplis et désactivés.
