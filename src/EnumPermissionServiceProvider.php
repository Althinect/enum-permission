<?php

namespace Althinect\EnumPermission;

use Althinect\EnumPermission\Commands\EnumPermissionCommand;
use Althinect\EnumPermission\Commands\SyncPermissionCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class EnumPermissionServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('enum-permission')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigrations([
                'add_group_column_to_permissions_table',
            ])
            ->hasCommand(EnumPermissionCommand::class)
            ->hasCommand(SyncPermissionCommand::class);
    }
}
