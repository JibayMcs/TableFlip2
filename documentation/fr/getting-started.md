---
title: Premiers pas
order: 2
---

# Premiers pas

TableFlip propose deux modes de connexion. Choisissez celui qui
correspond à la configuration du déploiement.

## Mode compte

C'est le mode par défaut. Un administrateur crée un compte,
l'utilisateur se connecte avec ce compte et bénéficie des
fonctionnalités qui demandent du stockage persistant :

- des connexions de base de données enregistrées, chiffrées au repos
  et attachées au compte
- des exports asynchrones qui continuent à s'exécuter après un
  rafraîchissement de page
- la même liste de connexions disponible d'une session à l'autre

C'est le mode recommandé pour un usage quotidien.

## Mode direct base de données

Un formulaire similaire à celui de phpMyAdmin demande **driver, hôte,
port, nom d'utilisateur et mot de passe**, et se connecte directement
au serveur sans créer de compte sur TableFlip. La session dure aussi
longtemps que l'onglet du navigateur reste ouvert.

C'est le bon mode pour un accès ponctuel ou lorsqu'aucune persistance
n'est nécessaire.

En mode direct, les exports asynchrones ne sont pas disponibles : le
worker n'a aucun moyen de rejouer la session. Les exports synchrones
ponctuels, l'explorateur, l'éditeur SQL et l'édition inline
fonctionnent à l'identique du mode compte.

## Restreindre le formulaire de connexion

Un administrateur peut pré-remplir et verrouiller les champs hôte,
driver et base via variables d'environnement. Quand c'est configuré,
le formulaire direct est réduit à **nom d'utilisateur** et **mot de
passe**. C'est pratique lorsque TableFlip remplace phpMyAdmin pour un
serveur unique. Voyez le
[Quickstart auto-hébergement](/docs/self-hosting/quickstart) pour la
configuration exacte.
