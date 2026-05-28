<?php

return [
    'navbar' => [
        'audit' => 'Audit',
        'history' => 'Historique',
    ],

    'common' => [
        'no_results' => 'Aucune entrée correspondante.',
        'search_placeholder' => 'Rechercher…',
        'expand_sql' => 'Voir le SQL',
        'collapse_sql' => 'Masquer le SQL',
        'filter_all' => 'Tous',
        'filter_by_op' => 'Opération',
        'filter_by_status' => 'Statut',
        'filter_by_kind' => 'Type d\'utilisateur',
        'kind_web' => 'Compte',
        'kind_direct_db' => 'Direct DB',
    ],

    'table_operations' => [
        'title' => 'Journal des écritures',
        'subtitle' => 'Chaque insertion / mise à jour / suppression effectuée depuis l\'explorateur.',
        'search_placeholder' => 'Rechercher par table, base, utilisateur ou SQL…',
        'col_when' => 'Quand',
        'col_user' => 'Utilisateur',
        'col_target' => 'Cible',
        'col_operation' => 'Op',
        'col_rows' => 'Lignes',
        'col_sql' => 'SQL',
        'op_insert' => 'INSERT',
        'op_update' => 'UPDATE',
        'op_delete' => 'DELETE',
        'op_truncate' => 'TRUNCATE',
        'op_drop' => 'DROP',
        'bindings' => 'Bindings',
        'no_bindings' => 'Aucun binding.',
    ],

    'query_history' => [
        'title' => 'Historique des requêtes',
        'subtitle' => 'Chaque requête SQL exécutée depuis l\'éditeur ou le scratch pad.',
        'search_placeholder' => 'Rechercher par SQL, base ou utilisateur…',
        'col_when' => 'Quand',
        'col_user' => 'Utilisateur',
        'col_db' => 'Base',
        'col_status' => 'Statut',
        'col_duration' => 'Durée',
        'col_rows' => 'Lignes',
        'col_sql' => 'SQL',
        'status_success' => 'OK',
        'status_error' => 'Erreur',
        'duration_ms' => ':ms ms',
        'error_message' => 'Erreur',
    ],
];
