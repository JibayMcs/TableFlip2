---
title: Configuration
order: 3
---

# Configuration

La plupart des réglages propres à TableFlip vivent dans
`config/tableflip.php` et lisent leur valeur par défaut depuis une
variable d'environnement. Le fichier peut être surchargé dans un build
personnalisé ; dans un déploiement Docker, l'environnement reste la
source canonique.

## Authentification

```php
'breeze_enabled' => env('AUTH_BREEZE_ENABLED', true),
'direct_db_enabled' => env('AUTH_DIRECT_DB_ENABLED', true),
'registration_enabled' => env('AUTH_REGISTRATION_ENABLED', false),
'require_db_name' => env('TABLEFLIP_REQUIRE_DB_NAME', false),
```

Lorsque `breeze_enabled` et `direct_db_enabled` sont tous deux
activés, la page de connexion affiche deux onglets. Désactiver l'un
masque son onglet. Désactiver les deux rend le formulaire de connexion
inaccessible depuis un navigateur ; ce mode est utile uniquement
lorsque l'accès est accordé via une API.

## Restreindre le périmètre direct

```php
'hosts' => explode(',', env('TABLEFLIP_ALLOWED_DB_HOSTS', '')),
'drivers' => explode(',', env('TABLEFLIP_ALLOWED_DB_DRIVERS', '')),
'databases' => explode(',', env('TABLEFLIP_ALLOWED_DB_NAMES', '')),
```

Chaque liste contient des patterns insensibles à la casse (wildcards
supportés). Une liste qui contient **exactement une** valeur
pré-remplit et désactive le champ correspondant du formulaire. C'est
le mécanisme utilisé pour transformer TableFlip en alternative
mono-serveur à phpMyAdmin : tous les champs autres que le nom
d'utilisateur et le mot de passe sont verrouillés.

## Journal d'audit

```php
'enabled' => env('TABLEFLIP_AUDIT_LOG_ENABLED', true),
```

Quand activé, chaque écriture effectuée via l'Explorateur (insertion,
mise à jour, suppression, truncate, drop) est inscrite dans le journal
d'audit. Désactiver l'option permet de supprimer l'inscription sur les
déploiements à très forte écriture. Le browser administrateur
`/admin/audit` n'a alors plus d'entrées à afficher.

## Édition

```php
'bulk_confirm_threshold' => env('TABLEFLIP_BULK_OP_CONFIRM_THRESHOLD', 10),
```

Une suppression en masse affectant plus de lignes que le seuil exige
une confirmation typée. Une valeur de `0` impose une confirmation à
chaque suppression en masse, indépendamment de la taille.

## Exports

```php
'disk' => env('TABLEFLIP_EXPORTS_DISK', 'local'),
'retention_days' => env('TABLEFLIP_EXPORTS_RETENTION_DAYS', 7),
'download_url_ttl_minutes' => env('TABLEFLIP_EXPORTS_DOWNLOAD_TTL', 30),
```

Le disque sélectionné doit exister dans `config/filesystems.php`. Le
disque `local` par défaut écrit dans `storage/app/exports/` à
l'intérieur du conteneur. Avec la stack Compose livrée, ce dossier vit
sur le volume de stockage et persiste à travers les redémarrages.

## Réglages qui ne vivent pas dans `config/tableflip.php`

- La clé applicative (`APP_KEY`) fait partie de `config/app.php`.
- Les détails de connexion à la base sont dans `config/database.php`.
- Les drivers de cache, queue et session sont dans `config/cache.php`,
  `config/queue.php` et `config/session.php`.

Tous ces réglages suivent les conventions Laravel standard.
