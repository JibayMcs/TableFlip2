---
title: Variables d'environnement
order: 2
---

# Variables d'environnement

Les variables ci-dessous sont lues au démarrage du conteneur. La
plupart correspondent à une clé de `config/tableflip.php` et peuvent
aussi y être définies ; dans un déploiement Docker, l'environnement
reste la source canonique.

## Variables obligatoires

| Variable | Rôle |
|---|---|
| `APP_KEY` | Clé base64 utilisée pour chiffrer les mots de passe de connexion stockés. Générée une fois via `php artisan key:generate --show`. **La perdre invalide toutes les connexions enregistrées.** |
| `APP_URL` | URL publique où TableFlip est servi. Utilisée dans les liens absolus et dans les URLs de téléchargement signées. |

## Application

| Variable | Défaut | Rôle |
|---|---|---|
| `APP_ENV` | `production` | Nom standard de l'environnement Laravel. |
| `APP_DEBUG` | `false` | Doit rester désactivé en production. Sinon les traces de pile et la valeur des variables d'environnement sont exposées sur les pages d'erreur. |
| `APP_TIMEZONE` | `UTC` | Fuseau horaire utilisé dans le journal d'audit et le scheduler. |
| `LOG_CHANNEL` | `stderr` | Les conteneurs écrivent les logs sur la sortie d'erreur standard pour que Docker les collecte. |

## Base de stockage

| Variable | Défaut | Rôle |
|---|---|---|
| `DB_CONNECTION` | `sqlite` | Driver utilisé pour les données propres à TableFlip : `sqlite`, `mysql`, `pgsql` ou `sqlsrv`. |
| `DB_DATABASE` | `/var/www/html/storage/app/tableflip.sqlite` | Chemin du fichier SQLite, ou nom de la base pour les autres drivers. |
| `DB_HOST` | — | Utilisée quand le driver n'est pas SQLite. |
| `DB_PORT` | — | Utilisée quand le driver n'est pas SQLite. |
| `DB_USERNAME` | — | Utilisée quand le driver n'est pas SQLite. |
| `DB_PASSWORD` | — | Utilisée quand le driver n'est pas SQLite. |

## Cache, queue et sessions

| Variable | Défaut | Rôle |
|---|---|---|
| `CACHE_STORE` | `redis` | Driver de cache. |
| `SESSION_DRIVER` | `redis` | Driver de session. La persistance Redis est activée, les sessions survivent à un redémarrage du conteneur. |
| `QUEUE_CONNECTION` | `redis` | Driver utilisé par les exports asynchrones. |
| `REDIS_HOST` | `redis` | Nom d'hôte du service Redis dans la stack Compose. |
| `REDIS_PORT` | `6379` | Port Redis. |
| `REDIS_PASSWORD` | — | Vide pour un Redis local non authentifié. |

## Authentification

| Variable | Défaut | Rôle |
|---|---|---|
| `AUTH_BREEZE_ENABLED` | `true` | Affiche l'onglet **Compte** sur la page de connexion. |
| `AUTH_DIRECT_DB_ENABLED` | `true` | Affiche l'onglet **Direct database** sur la page de connexion. |
| `AUTH_REGISTRATION_ENABLED` | `false` | Autorise l'inscription. La version actuelle n'embarque pas de formulaire d'inscription public ; le flag est conservé pour un usage futur. |
| `TABLEFLIP_REQUIRE_DB_NAME` | `false` | Force le formulaire direct à exiger un nom de base. |

## Restreindre le formulaire direct

Chaque liste avec **exactement une** valeur pré-remplit et désactive
le champ correspondant du formulaire de connexion. Des valeurs
séparées par des virgules laissent le champ éditable tout en
restreignant les valeurs acceptées.

| Variable | Rôle |
|---|---|
| `TABLEFLIP_ALLOWED_DB_HOSTS` | Liste blanche de noms d'hôte séparés par des virgules (wildcards supportés). |
| `TABLEFLIP_ALLOWED_DB_DRIVERS` | Sous-ensemble de `mysql`, `pgsql`, `sqlsrv`, `sqlite`. |
| `TABLEFLIP_ALLOWED_DB_NAMES` | Liste blanche de noms de bases séparés par des virgules. |

## Journal d'audit et édition

| Variable | Défaut | Rôle |
|---|---|---|
| `TABLEFLIP_AUDIT_LOG_ENABLED` | `true` | Quand désactivé, les opérations d'écriture ne sont pas inscrites dans la table d'audit. |
| `TABLEFLIP_BULK_OP_CONFIRM_THRESHOLD` | `10` | Une suppression en masse au-dessus de ce nombre de lignes exige une confirmation typée. |

## Exports

| Variable | Défaut | Rôle |
|---|---|---|
| `TABLEFLIP_EXPORTS_DISK` | `local` | Disque sur lequel les fichiers générés sont stockés. |
| `TABLEFLIP_EXPORTS_RETENTION_DAYS` | `7` | Jours avant que les exports expirés soient supprimés par la commande de nettoyage. |
| `TABLEFLIP_EXPORTS_DOWNLOAD_TTL` | `30` | Validité du lien de téléchargement signé, en minutes. |

## Mail (optionnel)

La valeur par défaut `MAIL_MAILER=log` écrit les mails sortants sur
la sortie standard. Pour envoyer de vrais messages, fournissez les
variables Laravel SMTP habituelles : `MAIL_HOST`, `MAIL_PORT`,
`MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`,
`MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`.

## Comportement du conteneur

| Variable | Défaut | Rôle |
|---|---|---|
| `MIGRATE_ON_BOOT` | `1` | Quand réglé sur `0`, le conteneur n'exécute pas les migrations au démarrage. Utile lorsque les migrations sont orchestrées par un job dédié. |
