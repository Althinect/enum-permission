<?php

namespace Althinect\EnumPermission\Tests;

use Althinect\EnumPermission\EnumPermissionServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Permission\PermissionServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Althinect\\EnumPermission\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            EnumPermissionServiceProvider::class,
            PermissionServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Set up enum-permission config
        config()->set('enum-permission.syncPermissionGroup', true);
        config()->set('enum-permission.models_path', 'app/Models');
        config()->set('enum-permission.user_model', 'App\\Models\\User');
        config()->set('enum-permission.model_super_classes', [
            'Illuminate\\Database\\Eloquent\\Model',
        ]);

        // Create a basic user model for testing
        $usersTable = include __DIR__.'/../vendor/orchestra/testbench-core/laravel/migrations/2014_10_12_000000_testbench_create_users_table.php';
        $usersTable->up();
    }
}
