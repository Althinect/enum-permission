<?php

use Althinect\EnumPermission\Commands\EnumPermissionCommand;
use Althinect\EnumPermission\Commands\SyncPermissionCommand;
use Althinect\EnumPermission\Services\EnumPermissionService;
use Illuminate\Support\Facades\File;
use Mockery\MockInterface;
use Spatie\Permission\Models\Permission;

// Setup for EnumPermissionCommand tests
beforeEach(function () {
    // Mock model directory and create a test model
    $modelDir = app_path('Models');
    if (! File::exists($modelDir)) {
        File::makeDirectory($modelDir, 0755, true);
    }

    // Create test model file
    $testModelPath = app_path('Models/TestModel.php');
    if (! File::exists($testModelPath)) {
        File::put($testModelPath, '<?php namespace App\\Models; class TestModel extends \Illuminate\Database\Eloquent\Model {}');
    }

    // Set up paths
    $this->testModelClass = 'App\\Models\\TestModel';
    $this->testPermissionEnumPath = app_path('Permissions/TestModelPermission.php');
    $this->testPolicyPath = app_path('Policies/TestModelPolicy.php');

    // Configure for testing
    config()->set('enum-permission.models_path', 'app/Models');
    config()->set('enum-permission.user_model', 'App\\Models\\User');
    config()->set('enum-permission.model_super_classes', [
        'Illuminate\\Database\\Eloquent\\Model',
    ]);

    // Create database tables
    setUpDatabase();
});

afterEach(function () {
    // Clean up test files
    foreach ([
        app_path('Models/TestModel.php'),
        app_path('Permissions/TestModelPermission.php'),
        app_path('Policies/TestModelPolicy.php'),
    ] as $file) {
        if (File::exists($file)) {
            File::delete($file);
        }
    }

    // Clean up directories
    foreach ([app_path('Models'), app_path('Permissions'), app_path('Policies')] as $dir) {
        if (File::exists($dir) && count(File::files($dir)) === 0) {
            File::deleteDirectory($dir);
        }
    }

    // Custom paths cleanup
    $customPath = base_path('custom/permissions');
    if (File::exists($customPath)) {
        File::deleteDirectory($customPath);
    }

    Mockery::close();
});

// EnumPermissionCommand Tests

// SyncPermissionCommand Tests
it('syncs permissions from enum to database', function () {
    // Create a test permission file
    $permissionsDir = app_path('Permissions');
    if (! File::exists($permissionsDir)) {
        File::makeDirectory($permissionsDir, 0755, true);
    }

    File::put(app_path('Permissions/TestModelPermission.php'), '<?php
namespace App\Permissions;
use Althinect\EnumPermission\Concerns\HasPermissionGroup;
enum TestModelPermission: string {
    use HasPermissionGroup;
    case VIEW = "TestModel.view";
    case CREATE = "TestModel.create";
}');

    // Mock the service
    $this->mock(EnumPermissionService::class, function (MockInterface $mock) {
        $mock->shouldReceive('syncPermissionEnumToDatabase')
            ->once()
            ->with('App\\Permissions\\TestModelPermission')
            ->andReturn([
                'success' => true,
                'message' => 'Successfully synced 8 permissions for App\\Permissions\\TestModelPermission',
                'count' => 8,
            ]);
    });

    // Run the sync command
    $this->artisan(SyncPermissionCommand::class, ['--force' => true])
        ->expectsOutput('Syncing Permissions...')
        ->expectsOutput('Processing App\\Permissions\\TestModelPermission')
        ->expectsOutput('Successfully synced 8 permissions for App\\Permissions\\TestModelPermission')
        ->assertExitCode(0);
});

it('handles clean option', function () {
    // Create test permission file
    $permissionsDir = app_path('Permissions');
    if (! File::exists($permissionsDir)) {
        File::makeDirectory($permissionsDir, 0755, true);
    }

    File::put(app_path('Permissions/TestModelPermission.php'), '<?php
namespace App\Permissions;
use Althinect\EnumPermission\Concerns\HasPermissionGroup;
enum TestModelPermission: string {
    use HasPermissionGroup;
    case VIEW = "TestModel.view";
    case CREATE = "TestModel.create";
}');

    // Create some test permissions first
    Permission::create(['name' => 'test.permission', 'guard_name' => 'web']);

    // Verify permission exists
    $this->assertDatabaseHas('permissions', ['name' => 'test.permission']);

    // Mock the service
    $this->mock(EnumPermissionService::class, function (MockInterface $mock) {
        $mock->shouldReceive('syncPermissionEnumToDatabase')
            ->once()
            ->with('App\\Permissions\\TestModelPermission')
            ->andReturn([
                'success' => true,
                'message' => 'Successfully synced 8 permissions for App\\Permissions\\TestModelPermission',
                'count' => 8,
            ]);
    });

    // Run the sync command with clean option
    $this->artisan(SyncPermissionCommand::class, ['--clean' => true, '--force' => true])
        ->expectsOutput('Syncing Permissions...')
        ->expectsOutput('All permissions have been removed from the database')
        ->assertExitCode(0);

    // Verify permissions were cleaned
    $this->assertDatabaseMissing('permissions', ['name' => 'test.permission']);
});

it('handles custom path option', function () {
    // Create a custom path for testing
    $customPath = base_path('custom/permissions');
    if (! File::exists($customPath)) {
        File::makeDirectory($customPath, 0755, true);
    }

    // Create a test permission file in the custom path
    $customPermissionPath = $customPath.'/CustomPermission.php';
    File::put($customPermissionPath, '<?php namespace Custom\\Permissions; enum CustomPermission: string { case VIEW = "custom.view"; }');

    // Mock the service
    $this->mock(EnumPermissionService::class, function (MockInterface $mock) {
        $mock->shouldReceive('syncPermissionEnumToDatabase')
            ->once()
            ->andReturn([
                'success' => true,
                'message' => 'Successfully synced permissions',
                'count' => 1,
            ]);
    });

    // Run the command with custom path
    $this->artisan(SyncPermissionCommand::class, [
        '--path' => $customPath,
        '--force' => true,
    ])
        ->expectsOutput('Syncing Permissions...')
        ->assertExitCode(0);
});
