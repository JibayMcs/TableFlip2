---
title: Mise à jour
order: 4
---

# Mise à jour

## Mettre à jour l'application

```bash
git pull
docker compose -f .docker/docker-compose.yml --env-file .env.docker build --pull
docker compose -f .docker/docker-compose.yml --env-file .env.docker up -d
```

Les migrations de base de données sont exécutées automatiquement au
démarrage du conteneur. Réglez `MIGRATE_ON_BOOT=0` dans
l'environnement si les migrations sont orchestrées par un job dédié.

> La clé applicative (`APP_KEY`) doit rester la même d'une version à
> l'autre. La changer invalide tous les mots de passe de connexion
> enregistrés et oblige les utilisateurs à les ressaisir.

## Passer de SQLite à MariaDB ou PostgreSQL

SQLite convient à un déploiement modeste (quelques utilisateurs
simultanés, un nombre modéré de connexions enregistrées). Pour des
déploiements plus importants, la base de stockage peut être basculée
vers MariaDB ou PostgreSQL.

```env
DB_CONNECTION=mysql
DB_HOST=database.example.com
DB_PORT=3306
DB_DATABASE=tableflip
DB_USERNAME=tableflip
DB_PASSWORD=…
```

1. Créez une base vide sur le serveur cible (`CREATE DATABASE tableflip`).
2. Mettez à jour l'environnement et redéployez.
3. Le conteneur exécute les migrations au premier démarrage.

La clé applicative peut rester la même. Les connexions enregistrées
continuent de fonctionner ; seul l'emplacement de stockage change.

> Les données existantes ne sont **pas** migrées automatiquement. Pour
> conserver le journal d'audit et les connexions enregistrées d'un
> déploiement SQLite précédent, exportez le fichier SQLite avec
> `php artisan db:dump`, puis importez le résultat dans la nouvelle
> base.

## Sécurité des volumes

Le volume de stockage contient le fichier SQLite, ou le dossier des
uploads si la base de stockage a été basculée vers un moteur distant.
Traitez-le comme n'importe quelle donnée critique : sauvegarde,
snapshot ou réplication avant toute opération risquée.

Le volume Redis contient le fichier append-only utilisé par les
sessions, la queue et le cache. Le perdre déconnecte tous les
utilisateurs et abandonne les jobs en cours, sans conséquence sur les
données persistantes. Il peut être supprimé sans autre précaution.

## Lorsqu'une mise à jour échoue

La page [Dépannage](/docs/self-hosting/troubleshooting) documente les
signatures les plus courantes observées au premier démarrage ou après
une mise à jour, et la manière de s'en sortir.
