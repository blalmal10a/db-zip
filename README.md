# Easily backup and restore Laravel database tables by saving them into zip files.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/blalmal10-a/db-zip.svg?style=flat-square)](https://packagist.org/packages/blalmal10-a/db-zip)
[![Fix PHP code style issues](https://github.com/blalmal10a/db-zip/actions/workflows/fix-php-code-style-issues.yml/badge.svg)](https://github.com/blalmal10a/db-zip/actions/workflows/fix-php-code-style-issues.yml)
[![run-tests](https://github.com/blalmal10a/db-zip/actions/workflows/run-tests.yml/badge.svg)](https://github.com/blalmal10a/db-zip/actions/workflows/run-tests.yml)
[![PHPStan](https://github.com/blalmal10a/db-zip/actions/workflows/phpstan.yml/badge.svg)](https://github.com/blalmal10a/db-zip/actions/workflows/phpstan.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/blalmal10a/db-zip.svg?style=flat-square)](https://packagist.org/packages/blalmal10a/db-zip)

**db-zip** exports database tables into chunked CSV files (400 rows per chunk), packages them into a zip with schema JSON, and can restore tables from those zip files — all through a web UI or API.

## Installation

```bash
composer require blalmal10a/db-zip
```

The package works immediately after installation — no migrations, no setup required.\
- You can simply go to /backup to backup your current database
- And /restore to restore the exported .zip files

### Optional — Publish config (to customise behaviour)

```bash
php artisan vendor:publish --tag="db-zip-config"
```

### Optional — Publish views (to customise the UI)

```bash
php artisan vendor:publish --tag="db-zip-views"
```

Views are published to `resources/views/vendor/db-zip/`. Your layout can extend or replace `db-zip::layouts.app`.

## Default behaviour

| Behaviour | Default |
|-----------|---------|
| Auth required | Yes — routes use `web` + `auth` middleware |
| Role required | `super_admin` (via Spatie Laravel Permission) |
| Storage paths | `storage/backup/` (CSV temp) and `storage/zip/` (zip archives) |
| Route prefix | None — routes sit at `/backup`, `/restore` |

## Authentication & role access

Out of the box, all routes require an **authenticated user** with the **`super_admin` role** (via `spatie/laravel-permission`).

### Quick role setup

```bash
composer require spatie/laravel-permission
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
```

Add the `HasRoles` trait to your `User` model:

```php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
}
```

Create the role and assign to a user:

```bash
php artisan tinker --execute '
    Spatie\Permission\Models\Role::create(["name" => "super_admin"]);
    $user = App\Models\User::find(1);
    $user->assignRole("super_admin");
'
```

### Bypassing or customising auth / roles

You have full control via the published config. Run:

```bash
php artisan vendor:publish --tag="db-zip-config"
```

Then edit `config/db-zip.php`:

```php
// Disable role checks entirely
'required_roles' => [],

// Change the required roles to any set you want
'required_roles' => ['admin', 'backup-operator'],

// Change the middleware applied to all routes
'middleware_group' => ['web'], // no auth middleware — accessible to all

// Or use your own custom middleware group
'middleware_group' => ['web', 'auth', 'custom-backup-guard'],
```

If you use a completely different authorisation system, you can also point the routes to your own controller classes:

```php
'controllers' => [
    'backup' => App\Http\Controllers\MyBackupController::class,
    'restore' => App\Http\Controllers\MyRestoreController::class,
],
```

> Your custom controllers must implement the same public methods as the originals. Extend the package controllers and override only what you need:

```php
namespace App\Http\Controllers;

use Blalmal10a\DbZip\Http\Controllers\BackupController as BaseBackupController;

class MyBackupController extends BaseBackupController
{
    // override any method
}
```

## Configuration reference

Published config `config/db-zip.php`:

```php
return [
    'backup_path' => env('DBZIP_BACKUP_PATH', 'backup'),
    'zip_path' => env('DBZIP_ZIP_PATH', 'zip'),
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
```

Override paths via `.env`:

```dotenv
DBZIP_BACKUP_PATH=backup
DBZIP_ZIP_PATH=zip
```

## Routes

| Method | URI | Name |
|--------|-----|------|
| GET | `/backup` | `backup.index` |
| GET | `/backup/tables` | `backup.tables` |
| POST | `/backup/export` | `backup.export` |
| POST | `/backup/zip` | `backup.zip` |
| GET | `/backup/download/{fileName}` | `backup.download` |
| DELETE | `/backup/{fileName}` | `backup.delete` |
| GET | `/restore` | `restore.index` |
| POST | `/backup/restore` | `backup.restore` |

All routes are wrapped in the middleware group from `config('db-zip.middleware_group')` plus a `backup-role` check.

Change any route path in the published config without touching the route file.

## Usage

### Via browser

Visit `/backup` to see available tables, export them, and manage zip files.
Visit `/restore` to upload a zip, view chunked CSVs grouped by table, and restore data.

### Via API

```bash
# List tables
curl http://your-app.test/backup/tables

# Export a table
curl -X POST http://your-app.test/backup/export \
  -H "Content-Type: application/json" \
  -d '{"table":"users","connection":"mysql"}'

# Zip the exported CSVs
curl -X POST http://your-app.test/backup/zip \
  -H "Content-Type: application/json" \
  -d '{"timestamp":"2025-01-01_120000"}'

# Download a backup
curl -O http://your-app.test/backup/download/2025-01-01_120000

# Upload and restore
curl -X POST http://your-app.test/backup/restore \
  -F "zip=@2025-01-01_120000.zip" \
  -F "append=false"
```

### Using PHP

```php
use Blalmal10a\DbZip\DbZip;

$dbZip = app(DbZip::class);

$dbZip->exportTableToCsv('users', now()->format('Y-m-d_Hi'));
$dbZip->zipBackup('2025-01-01_120000');

$backups = $dbZip->listBackups();
$path = $dbZip->downloadBackup('2025-01-01_120000');
$dbZip->deleteBackup('2025-01-01_120000');
$dbZip->restoreTable('users', $csvContent, $tableSQL, append: false);
```

## Storage

Backup CSVs and zip files are stored in `storage_path(config('db-zip.backup_path'))` and `storage_path(config('db-zip.zip_path'))` respectively — outside `public/` by default. Downloads are served through Laravel, not directly by the web server.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [blalmal10a](https://github.com/blalmal10a)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
