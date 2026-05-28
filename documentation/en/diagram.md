---
title: Diagram
order: 7
---

# Diagram

The Diagram page renders an interactive entity-relationship view of
the selected database. Tables are shown as nodes, foreign keys as
edges between them. The view supports panning, zooming, dragging
nodes, and searching by name.

## Layouts

Three layout engines are available, switchable from the toolbar
without regenerating the graph :

- **Hierarchical** : top-down, the most familiar layout for an ER
  diagram. Default choice.
- **Force-directed** : positions are computed by simulated forces.
  Fast on large schemas (two hundred tables and beyond).
- **Organic** : a softer, more readable layout for smaller schemas.

## Compact mode

The Compact toggle hides every column that is not a primary or foreign
key. Recommended for schemas with more than about one hundred and
fifty tables, where the full layout becomes noticeably slower.

## Interactions

- Click a node to highlight it together with its direct neighbours
  and the edges between them. Other nodes dim. A panel on the right
  lists the columns of the selected table, with primary-key,
  foreign-key and nullability markers.
- Press **Esc** or click the background to clear the selection.
- The search field in the toolbar highlights nodes whose name
  matches, as the text is typed.
- The **Fit** button centres the diagram on the viewport.
- The **Download PNG** button exports the entire graph as a
  high-resolution image with a white background.

## Bookmarkable views

The URL captures the current database, the compact toggle and the
selected layout. Bookmarking the page recreates the same view on the
next visit.

## Large schemas

For very large schemas (about five hundred tables and beyond), the
combination of Compact mode and the Force-directed layout produces
the most responsive result. The layout engine still has to place
every node, so a render time of several seconds is expected ; there
is no streaming render.
