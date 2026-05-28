---
title: Exports
order: 6
---

# Exports

TableFlip peut produire trois types d'exports :

- **Par table** : CSV, JSON (tableau ou un document par ligne) ou un
  fichier SQL compact. Lancé depuis la vue Données avec les filtres
  et le tri courants, ou piloté par une requête SQL personnalisée.
- **Dump de base** : un fichier SQL complet avec structure et
  données, multi-tables, dans l'esprit d'un export phpMyAdmin.
- **Depuis l'éditeur SQL** : exécutez une requête et exportez son
  jeu de résultats.

Les exports s'exécutent en arrière-plan dans un worker. L'utilisateur
peut quitter la page ; le résultat apparaît dans la liste **Exports**,
avec un statut (en attente, en cours, terminé, échoué). Un lien de
téléchargement signé devient disponible une fois l'export terminé, et
reste valide trente minutes par défaut.

## Export par table

Ouvrez la vue Données d'une table et sélectionnez **Exporter**, puis
choisissez :

- **Format** : CSV (délimiteur et en-tête), JSON (tableau ou un
  document par ligne) ou SQL (instruction DROP, CREATE minimal,
  INSERT multi-lignes)
- **Compression** : aucune, gzip ou zip
- **Template de nom** : des marqueurs comme `@DATABASE@`, `@TABLE@`,
  `@DATETIME@` et `@USER@` sont substitués au moment de la génération

L'export reprend les filtres et le tri courants. Si la vue Données est
pilotée par une requête SQL personnalisée, c'est cette requête qui est
utilisée.

## Dump de base

La page de dump de base propose deux modes :

- **Rapide** : un clic avec des défauts raisonnables. La sortie
  contient la structure et les données de chaque table, avec quelques
  options de sécurité activées (transaction, désactivation des clés
  étrangères pendant l'import).
- **Personnalisé** : une grille par table avec deux cases distinctes
  Structure et Données, des actions groupées pour basculer toute une
  colonne d'un coup, et six options SQL qui ajustent la sortie :
  instructions DROP, garde `IF NOT EXISTS`, transaction, désactivation
  des clés étrangères, commentaire d'en-tête, taille du lot
  d'insertion.

Le dump s'adapte à la base cible :

- MySQL et MariaDB utilisent `AUTO_INCREMENT`, les literals binaires
  hexadécimaux, et `SET FOREIGN_KEY_CHECKS`
- PostgreSQL utilise les colonnes `SERIAL`, les literals `bytea`
  binaires, et `session_replication_role` pour contourner les clés
  étrangères
- SQL Server utilise `IDENTITY(1,1)`, le préfixe `N'…'` pour les
  chaînes Unicode, et les gardes `IF NOT EXISTS` via `sys.tables`
- SQLite utilise la déclaration inline `INTEGER PRIMARY KEY
  AUTOINCREMENT` et la directive `PRAGMA foreign_keys`

## Rétention

Les fichiers générés sont supprimés après la durée de rétention
configurée (sept jours par défaut). Une commande planifiée s'exécute
chaque nuit pour supprimer les entrées expirées et leurs fichiers.
Elle accepte un mode de prévisualisation qui affiche ce qui serait
supprimé sans toucher à rien.

## Remarque sur le mode direct

Les exports asynchrones sont réservés aux **utilisateurs en mode
compte**. Le worker n'a aucun moyen de rejouer une session direct.
Les exports synchrones ponctuels lancés depuis la vue Données
fonctionnent pour tous les utilisateurs, quel que soit le mode de
connexion.
