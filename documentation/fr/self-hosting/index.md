---
title: Auto-hébergement
order: 10
---

# Auto-hébergement

TableFlip est distribué sous forme d'une petite application Laravel
empaquetée dans une stack Docker Compose de quatre services.
L'application écoute sur le port 80 à l'intérieur du conteneur,
derrière un reverse proxy (Traefik, nginx ou Caddy) qui termine le TLS
en frontal.

## Vue d'ensemble de la stack

| Service | Rôle |
|---|---|
| Application | Sert l'interface web sur le port 80 |
| Worker | Traite les jobs asynchrones (exports) |
| Scheduler | Exécute les commandes de maintenance quotidiennes |
| Redis | Stocke la queue, le cache et les sessions |

La base de stockage est **SQLite** par défaut : un seul fichier
conservé sur un volume Docker, sans base externe à provisionner. La
queue, le cache et les sessions s'appuient sur Redis, ce qui évite la
contention sur le fichier SQLite même en usage régulier.

Pour les déploiements plus importants, la base de stockage peut être
basculée vers MariaDB ou PostgreSQL — voyez la page
[Mise à jour](/docs/self-hosting/upgrading).

## Ce que cette stack ne contient pas

- **Pas de reverse proxy intégré.** La terminaison TLS et le routage
  sont attendus en amont. Le conteneur applicatif sert simplement HTTP
  sur le port 80.
- **Pas de MariaDB ni PostgreSQL intégré.** TableFlip stocke ses
  propres données ; les bases que les utilisateurs explorent sont
  ajoutées au runtime via le formulaire Connexions ou le login direct.
- **Pas de serveur mail intégré.** La configuration par défaut écrit
  les mails dans les logs du conteneur. Des identifiants SMTP peuvent
  être fournis via les variables d'environnement quand nécessaire.

## Où déployer

La stack Compose tourne sur n'importe quel hôte Docker disposant d'un
reverse proxy en frontal. Le
[Quickstart](/docs/self-hosting/quickstart) détaille un test local
et un déploiement avec Dokploy.
