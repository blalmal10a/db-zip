<?php

namespace Blalmal10a\DbZip;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Blalmal10a\DbZip\Commands\DbZipCommand;

class DbZipServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('db-zip')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_db_zip_table')
            ->hasCommand(DbZipCommand::class);
    }
}
