---
title: Éditeur SQL
order: 5
---

# Éditeur SQL

L'éditeur SQL est une surface CodeMirror multi-onglets, avec
coloration syntaxique adaptée au dialecte, autocomplétion basée sur le
schéma et un panneau latéral d'historique des requêtes.

## Exécuter des requêtes

Trois raccourcis clavier couvrent les interactions courantes :

- **Ctrl + Entrée** (ou Cmd + Entrée sur macOS) exécute la requête
  courante
- **Ctrl + /** (ou Cmd + /) commente ou décommente la sélection
- **Ctrl + Espace** ouvre l'autocomplétion manuellement ; elle apparaît
  également pendant la frappe

Un petit badge sous l'éditeur indique le dialecte SQL avec lequel
TableFlip discute (MySQL, MariaDB, PostgreSQL, SQL Server ou SQLite),
en fonction de la base sélectionnée au-dessus de l'éditeur.

## Confirmation des requêtes destructives

Une fenêtre de confirmation s'affiche lorsque l'éditeur détecte une
requête susceptible de modifier des données sans périmètre clair :

- `DELETE FROM …` sans clause `WHERE`
- `UPDATE …` sans clause `WHERE`
- `TRUNCATE`, `DROP`, `ALTER`, `RENAME`

La fenêtre affiche le motif qui a déclenché la détection ainsi que la
requête complète. L'utilisateur doit saisir `CONFIRM` pour confirmer.
Le même mécanisme protège la suppression en masse dans l'Explorateur.

## Historique des requêtes

Un panneau latéral liste les requêtes précédentes pour l'utilisateur
courant, les plus récentes en haut. Les requêtes identiques exécutées
consécutivement sont fusionnées (le timestamp est mis à jour, aucune
nouvelle ligne n'est ajoutée). Cliquez sur une entrée pour la charger
dans l'onglet actif. Une icône de fermeture sur chaque ligne supprime
cette entrée seule ; une action « tout vider » dans l'en-tête du
panneau supprime l'ensemble.

## Éditeur SQL dans l'Explorateur

Le même éditeur est intégré à la vue Données de l'Explorateur.
Lorsqu'une requête SQL pilote la table, les contrôles de tri, de
filtre et de pagination sont masqués, et un bandeau permet en un clic
de revenir à la vue filtrée.
