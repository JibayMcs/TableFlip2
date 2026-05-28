<?php

return [
    'panel_title' => 'Connexions enregistrées',
    'panel_subtitle' => 'Stockées chiffrées dans ce navigateur, déverrouillées avec un mot de passe maître.',

    'setup' => [
        'title' => 'Définir un mot de passe maître',
        'intro' => 'Votre mot de passe maître protège chaque connexion que vous enregistrez dans ce navigateur. Il n\'est jamais envoyé au serveur.',
        'master_password' => 'Mot de passe maître',
        'confirm' => 'Confirmer le mot de passe maître',
        'submit' => 'Créer',
        'minimum' => '8 caractères minimum.',
        'mismatch' => 'Les mots de passe ne correspondent pas.',
    ],

    'unlock' => [
        'title' => 'Déverrouiller les connexions enregistrées',
        'master_password' => 'Mot de passe maître',
        'submit' => 'Déverrouiller',
        'forgot' => 'Oublié ?',
        'wrong' => 'Mot de passe maître incorrect.',
        'locked_summary_suffix' => 'enregistrées',
    ],

    'list' => [
        'empty' => 'Aucune connexion enregistrée.',
        'unlocked_summary' => 'Déverrouillé. Cliquez sur une entrée pour remplir le formulaire.',
        'lock' => 'Verrouiller',
        'fill_hint' => 'Cliquez pour remplir le formulaire ci-dessous.',
        'remove' => 'Supprimer',
        'remove_confirm' => 'Supprimer cette connexion enregistrée ?',
    ],

    'save_current' => [
        'title' => 'Enregistrer la connexion courante',
        'label' => 'Libellé',
        'label_placeholder' => 'Base de production, Staging, etc.',
        'color' => 'Couleur d\'accent',
        'button' => 'Enregistrer',
        'updated' => 'Enregistré.',
        'missing_credentials' => 'Saisissez d\'abord l\'utilisateur et le mot de passe.',
    ],

    'navbar' => [
        'switch' => 'Changer de connexion',
        'no_saved' => 'Aucune connexion enregistrée',
        'unlock_required' => 'Déverrouillez d\'abord depuis la page de connexion.',
    ],

    'wipe' => [
        'title' => 'Réinitialiser les connexions enregistrées',
        'intro' => 'Cette action supprime définitivement toutes les connexions enregistrées dans ce navigateur. Irréversible.',
        'confirm' => 'Oui, tout supprimer',
        'cancel' => 'Annuler',
    ],
];
