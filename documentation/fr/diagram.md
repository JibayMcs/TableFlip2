---
title: Diagramme
order: 7
---

# Diagramme

La page Diagramme affiche une vue entité-relation interactive de la
base sélectionnée. Les tables apparaissent sous forme de nœuds, les
clés étrangères sous forme d'arêtes entre eux. La vue prend en charge
le panoramique, le zoom, le déplacement des nœuds et la recherche par
nom.

## Layouts

Trois moteurs de mise en page sont disponibles, basculables depuis la
barre d'outils sans régénérer le graphe :

- **Hiérarchique** : disposition descendante, la mise en page la plus
  familière pour un diagramme ER. Choix par défaut.
- **Force dirigée** : les positions sont calculées par simulation de
  forces. Rapide sur les schémas volumineux (au-delà de deux cents
  tables).
- **Organique** : une mise en page plus douce et plus lisible pour les
  schémas de petite taille.

## Mode compact

Le bouton Compact masque toutes les colonnes qui ne sont ni clés
primaires ni clés étrangères. Recommandé pour les schémas comportant
plus de cent cinquante tables environ, où la mise en page complète
devient sensiblement plus lente.

## Interactions

- Cliquez sur un nœud pour le mettre en évidence avec ses voisins
  directs et les arêtes qui les relient. Les autres nœuds s'estompent.
  Un panneau à droite liste les colonnes de la table sélectionnée,
  avec les marqueurs de clé primaire, de clé étrangère et de
  nullabilité.
- Appuyez sur **Échap** ou cliquez sur le fond pour effacer la
  sélection.
- Le champ de recherche de la barre d'outils met en évidence les
  nœuds dont le nom correspond, pendant la frappe.
- Le bouton **Ajuster** recentre le diagramme sur la zone visible.
- Le bouton **Télécharger PNG** exporte la totalité du graphe en
  image haute résolution avec un fond blanc.

## Vues bookmarkables

L'URL conserve la base courante, l'état du mode compact et le layout
sélectionné. Ajouter la page aux favoris recrée la même vue à la
prochaine visite.

## Schémas volumineux

Pour les schémas très volumineux (au-delà de cinq cents tables), la
combinaison du mode compact et du layout Force dirigée donne le
résultat le plus réactif. Le moteur de mise en page doit malgré tout
placer chaque nœud, un rendu de plusieurs secondes est donc attendu ;
il n'y a pas de rendu en streaming.
