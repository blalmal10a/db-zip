<?php

namespace Blalmal10a\DbZip;

use Blalmal10a\DbZip\Commands\DbZipCommand;
use Blalmal10a\DbZip\Http\Middleware\CheckBackupRole;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class DbZipServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('db-zip')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_db_zip_table')
            ->hasRoute('db-zip')
            ->hasCommand(DbZipCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(DbZip::class, function () {
            return new DbZip;
        });
    }

    public function packageBooted(): void
    {
        $this->app['router']->aliasMiddleware('backup-role', CheckBackupRole::class);
    }
}
