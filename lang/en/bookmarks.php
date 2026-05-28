<?php

return [
    'panel_title' => 'Saved connections',
    'panel_subtitle' => 'Stored encrypted in this browser, unlocked with a master password.',

    'setup' => [
        'title' => 'Set up a master password',
        'intro' => 'Your master password protects every connection you save in this browser. It is never sent to the server.',
        'master_password' => 'Master password',
        'confirm' => 'Confirm master password',
        'submit' => 'Create',
        'minimum' => 'At least 8 characters.',
        'mismatch' => 'Passwords do not match.',
    ],

    'unlock' => [
        'title' => 'Unlock saved connections',
        'master_password' => 'Master password',
        'submit' => 'Unlock',
        'forgot' => 'Forgot it?',
        'wrong' => 'Wrong master password.',
        'locked_summary_suffix' => 'saved',
    ],

    'list' => [
        'empty' => 'No saved connections yet.',
        'unlocked_summary' => 'Unlocked. Pick one to fill the form.',
        'lock' => 'Lock',
        'fill_hint' => 'Click to fill the form below.',
        'remove' => 'Remove',
        'remove_confirm' => 'Remove this saved connection?',
    ],

    'save_current' => [
        'title' => 'Save current connection',
        'label' => 'Label',
        'label_placeholder' => 'Production database, Staging, etc.',
        'color' => 'Accent colour',
        'button' => 'Save',
        'updated' => 'Saved.',
        'missing_credentials' => 'Fill the username and password first.',
    ],

    'navbar' => [
        'switch' => 'Switch connection',
        'no_saved' => 'No saved connections',
        'unlock_required' => 'Unlock from the login page first.',
    ],

    'wipe' => [
        'title' => 'Reset saved connections',
        'intro' => 'This permanently deletes every saved connection from this browser. It cannot be undone.',
        'confirm' => 'Yes, delete everything',
        'cancel' => 'Cancel',
    ],
];
