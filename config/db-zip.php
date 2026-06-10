<?php

return [
    'backup_path' => 'backup',

    'zip_path' => 'zip',

    'required_roles' => ['admin', 'super_admin'],

    'middleware_group' => ['web', 'auth'],
];
