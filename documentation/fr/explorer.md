---
title: Explorateur
order: 4
---

# Explorateur

L'Explorateur est la page principale pour naviguer dans le contenu
d'une base et l'éditer. La barre latérale à gauche liste les bases,
les tables et les vues ; la zone principale affiche le schéma et les
données de la table sélectionnée.

## Barre latérale

Cliquez sur le nom d'une base pour la déplier. Les tables et les vues
sont chargées à la demande. Le champ de recherche en haut correspond à
un nom de base *ou* à n'importe quelle table ou vue à l'intérieur
d'une base dépliée. Les bases repliées ne sont pas incluses dans la
recherche tant qu'elles ne sont pas dépliées ; un indice discret le
rappelle lorsque la recherche est active.

## Onglet Schéma

La vue Schéma liste chaque colonne avec les informations suivantes :
marqueur de clé primaire, marqueur de clé étrangère, type de données,
indication de nullabilité et valeur par défaut. La colonne d'actions à
droite (truncate, drop) reste visible lors du défilement horizontal,
ce qui garde les tables larges utilisables.

## Onglet Données

La vue Données est en lecture seule par défaut. Cliquez sur l'en-tête
d'une colonne pour trier, utilisez le bouton **Filtrer** pour composer
des conditions localement, et paginez en bas. Pour les tables très
volumineuses, le nombre total de lignes est affiché en estimation
(par exemple « ≈ 281 125 lignes »). Un bouton à côté du compteur
déclenche un `COUNT(*)` exact à la demande.

### Éditer une cellule

Cliquez sur une cellule pour basculer en mode édition. Le champ de
saisie s'adapte au type de la colonne : sélecteurs de date et d'heure
pour les types temporels, champs numériques pour les types numériques,
raccourci NULL pour les colonnes nullables. **Enregistrer** exécute
une requête `UPDATE` restreinte à la clé primaire de la ligne et
inscrit la modification dans le journal d'audit.

### Insérer et supprimer des lignes

- **Ajouter une ligne** ouvre un formulaire avec un champ par colonne.
  Les marqueurs « requis » suivent les colonnes `NOT NULL`.
- Un bouton de suppression sur chaque ligne la retire après
  confirmation.
- Les cases à cocher à gauche activent la sélection multiple. Une
  fenêtre de confirmation typée s'affiche lorsque la sélection dépasse
  un seuil configurable (10 lignes par défaut).

Toutes les opérations d'écriture sont inscrites dans le journal
d'audit, accessible aux administrateurs depuis `/admin/audit`.

## Éditeur SQL intégré

Le bouton **SQL** au-dessus de la table ouvre un éditeur qui pilote la
table courante. Exécutez une requête `SELECT` avec des clauses
personnalisées et la table affiche le résultat. Un bandeau rappelle
que la table est en mode personnalisé, et un clic suffit pour revenir
à la vue filtrée.

## Tables larges

Pour les schémas comportant des centaines de colonnes, deux mesures
maintiennent la page réactive :

- les colonnes vides sont masquées automatiquement sur chaque page
- les cellules de type texte sont tronquées à 240 caractères et
  marquées comme telles ; cliquez sur une cellule tronquée pour
  récupérer la valeur complète

Un sélecteur de colonnes au-dessus de la table permet de basculer la
visibilité de chaque colonne individuellement. Un champ de recherche
intégré au sélecteur aide à retrouver rapidement une colonne dans une
longue liste.
