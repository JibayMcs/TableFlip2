---
title: Connexions
order: 3
---

# Connexions

Une *connexion* est un ensemble d'identifiants utilisés par TableFlip
pour atteindre un serveur de base de données : driver, hôte, port, nom
d'utilisateur, mot de passe, et éventuellement un nom de base. Les
utilisateurs en mode compte les enregistrent et les réutilisent ; les
utilisateurs en mode direct travaillent avec une seule connexion
valide le temps de la session.

## Ajouter une connexion (mode compte)

Ouvrez le sélecteur de connexion dans la barre supérieure et
sélectionnez **Nouvelle connexion**. Remplissez le formulaire :

- **Driver** : MySQL, MariaDB, PostgreSQL, SQL Server ou SQLite
- **Hôte, port, nom d'utilisateur, mot de passe**
- **Base de données** (optionnel) : laissez vide pour lister toutes
  les bases du serveur lors de la connexion ; obligatoire pour SQLite
  (chemin du fichier)
- **Libellé** : un nom convivial affiché dans le sélecteur

Le bouton **Tester la connexion** exécute une requête
`SELECT version()` et affiche le message d'erreur réel renvoyé par la
base si elle échoue (DNS, authentification, connexion refusée, SSL,
règles d'accès basées sur l'hôte, etc.). Utilisez-le avant
d'enregistrer.

Les mots de passe sont chiffrés au repos avec la clé applicative
(`APP_KEY`). La perte ou la rotation de cette clé rend tous les mots
de passe enregistrés illisibles et oblige à les ressaisir.

## Basculer entre connexions

Le sélecteur de la barre supérieure liste toutes les connexions
enregistrées, l'active mise en évidence. Le basculement est instantané :
ni reconnexion, ni rechargement de page.

## SSL et options avancées

La version actuelle prend en charge `sslmode=require` pour PostgreSQL
via l'URL de connexion. Le certificat CA complet, le certificat et la
clé client, le tunneling SSH et le pooling au niveau connexion sont
prévus pour une version ultérieure.

## Suppression

La suppression d'une connexion la retire définitivement de la base de
stockage, mot de passe chiffré inclus. Il n'y a pas de corbeille ni de
trace d'audit pour les suppressions.
