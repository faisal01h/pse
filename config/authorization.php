<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Permission catalog
    |--------------------------------------------------------------------------
    |
    | English ability keys with Indonesian labels for admin UI.
    |
    */
    'permissions' => [
        'checks.perform' => 'Melakukan pemeriksaan',
        'figures.add' => 'Menambah tokoh',
        'figures.edit' => 'Mengubah tokoh',
        'parties.add' => 'Menambah partai',
        'parties.edit' => 'Mengubah partai',
        'businesses.add' => 'Menambah bisnis',
        'businesses.edit' => 'Mengubah bisnis',
        'audits.view' => 'Melihat audit',
        'audits.rerun' => 'Menjalankan ulang inferensi',
        'pages.add' => 'Menambah halaman',
        'pages.edit' => 'Mengubah halaman',
        'roles.view' => 'Melihat peran',
        'roles.manage' => 'Mengelola peran',
        'users.view' => 'Melihat pengguna',
        'users.manage' => 'Mengelola akses pengguna',
    ],

    'protected_roles' => [
        'admin',
    ],

    'admin_role' => 'admin',
];
