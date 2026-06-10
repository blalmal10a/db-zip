<?php

return [
    'backup_path' => env('DBZIP_BACKUP_PATH', 'app/backup'),

    'zip_path' => env('DBZIP_ZIP_PATH', 'app/zip'),

    'required_roles' => ['super_admin'],

    'middleware_group' => ['web', 'auth'],

    'route' => [
        'backup_index' => '/backup',
        'backup_tables' => '/backup/tables',
        'backup_export' => '/backup/export',
        'backup_zip' => '/backup/zip',
        'backup_download' => '/backup/download/{fileName}',
        'backup_delete' => '/backup/{fileName}',
        'restore_index' => '/restore',
        'backup_restore' => '/backup/restore',
    ],

    'controllers' => [
        'backup' => Blalmal10a\DbZip\Http\Controllers\BackupController::class,
        'restore' => Blalmal10a\DbZip\Http\Controllers\RestoreController::class,
    ],
];
