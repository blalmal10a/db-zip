# db-zip-config

Activate this skill when working with package configuration, publishable assets, or route registration.

## Configuration

The `config/db-zip.php` file defines:

```php
return [
    'backup_path' => env('DBZIP_BACKUP_PATH', 'backup'),       // CSV storage relative to public disk
    'zip_path'    => env('DBZIP_ZIP_PATH', 'zip'),              // ZIP storage relative to public disk
    'required_roles' => ['admin', 'super_admin'],                // Spatie roles; empty = all auth
    'middleware_group' => ['web', 'auth'],                       // Route middleware stack
];
```

## Publishable assets

ServiceProvider uses `Spatie\LaravelPackageTools`:
- `->hasConfigFile()` — publishes `config/db-zip.php`
- `->hasViews()` — publishes `resources/views/`
- `->hasMigration('create_db_zip_table')` — publishes migration stub
- `->hasCommand(...)` — registers artisan commands

## Route registration

Routes should be loaded in the ServiceProvider via `Package::routing()` or a dedicated route file (`routes/db-zip.php`) registered in `boot()`. Routes use config-driven middleware:

```php
Route::middleware(config('db-zip.middleware_group'))->group(function () { ... });
```

## View extendability

Views use `@extends('db-zip::layouts.app')` so consuming apps can override the layout. Users publish views with `php artisan vendor:publish --tag="db-zip-views"`.
