<?php

return [
    'backup_path' => env('DBZIP_BACKUP_PATH', 'backup'),

    'zip_path' => env('DBZIP_ZIP_PATH', 'zip'),

    'required_roles' => ['admin', 'super_admin'],

    'middleware_group' => ['web', 'auth'],
];
