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

### Publish all assets (config, migrations, views)

```bash
php artisan vendor:publish --provider="Blalmal10a\DbZip\DbZipServiceProvider"
```

### Publish individually

```bash
# Config
php artisan vendor:publish --tag="db-zip-config"

# Migrations
php artisan vendor:publish --tag="db-zip-migrations"
php artisan migrate

# Views (optional — customize the UI)
php artisan vendor:publish --tag="db-zip-views"
```

### Publish controllers (optional — customize logic)

```bash
php artisan vendor:publish --tag="db-zip-controllers"
```

> After publishing controllers, update your `routes/db-zip.php` to point to the copied controllers (namespace `App\Http\Controllers\DbZip\`).

## Configuration

Published config `config/db-zip.php`:

```php
return [
    'backup_path' => 'backup', // storage path for temporary CSV files
    'zip_path' => 'zip',       // storage path for final zip archives
    'required_roles' => ['admin', 'super_admin'], // Spatie roles allowed access
    'middleware_group' => ['web', 'auth'], // middleware applied to all routes
];
```

Override in `.env` if needed:

```dotenv
DBZIP_BACKUP_PATH=backup
DBZIP_ZIP_PATH=zip
```

## Routes

| Method | URI | Action |
|--------|-----|--------|
| GET | `/backup` | Backup page (view) |
| GET | `/backup/tables` | List tables with schemas |
| POST | `/backup/export` | Export a table to chunked CSVs |
| POST | `/backup/zip` | Zip exported CSVs into a backup file |
| GET | `/backup/download/{fileName}` | Download a backup zip |
| DELETE | `/delete-zip-file-by-name` | Delete a backup zip |
| GET | `/restore` | Restore page (view) |
| POST | `/backup/restore` | Upload and restore a zip |

All routes are wrapped in the middleware group from `config('db-zip.middleware_group')`.

## Role-based access

This package requires [spatie/laravel-permission](https://spatie.be/docs/laravel-permission). Users must have at least one role listed in `config('db-zip.required_roles')` (default: `admin` or `super_admin`).

### Quick setup

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
    // ...
}
```

Create roles and assign to a user (e.g. in a seeder or tinker):

```bash
php artisan tinker --execute '
    use Spatie\Permission\Models\Role;
    use App\Models\User;

    Role::create(["name" => "admin"]);
    $user = User::find(1);
    $user->assignRole("admin");
'
```

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

// Export table to chunked CSV
$dbZip->exportTableToCsv('users', now()->format('Y-m-d_Hi'));

// Zip the backup
$dbZip->zipBackup('2025-01-01_120000');

// List backups
$backups = $dbZip->listBackups();

// Download path
$path = $dbZip->downloadBackup('2025-01-01_120000');

// Delete a backup
$dbZip->deleteBackup('2025-01-01_120000');

// Restore with schema recreation
$dbZip->restoreTable('users', $csvContent, $tableSQL, append: false);
```

### Customizing views

After publishing views:

```bash
php artisan vendor:publish --tag="db-zip-views"
```

Edit the layout or override individual views. Your layout must extend `db-zip::layouts.app` or create your own at `resources/views/vendor/db-zip/layouts/app.blade.php`.

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
