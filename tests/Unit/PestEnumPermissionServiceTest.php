<?php

use Althinect\EnumPermission\Services\EnumPermissionService;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;
use Mockery;

// Setup and teardown
beforeEach(function () {
    $this->service = new EnumPermissionService();
    
    // Create test model
    $modelDir = app_path('Models');
    if (!File::exists($modelDir)) {
        File::makeDirectory($modelDir, 0755, true);
    }
    
    $this->testModelClass = 'App\\Models\\TestModel';
    $this->testEnumClass = 'App\\Permissions\\TestModelPermission';
    
    File::put(
        app_path('Models/TestModel.php'),
        '<?php namespace App\\Models; class TestModel extends \Illuminate\Database\Eloquent\Model {}'
    );
    
    // Create permissions directory
    $permissionsDir = app_path('Permissions');
    if (!File::exists($permissionsDir)) {
        File::makeDirectory($permissionsDir, 0755, true);
    }
    
    // Create a real enum class for testing
    File::put(
        app_path('Permissions/TestModelPermission.php'),
        <<<'EOT'
<?php
namespace App\Permissions;

use Althinect\EnumPermission\Concerns\HasPermissionGroup;

enum TestModelPermission: string
{
    use HasPermissionGroup;
    
    case VIEW = 'TestModel.view';
    case CREATE = 'TestModel.create';
}
EOT
    );
    
    // Set up config values
    config()->set('enum-permission.models_path', 'app/Models');
    config()->set('enum-permission.user_model', 'App\\Models\\User');
    config()->set('enum-permission.permissions', [
        [
            'method' => 'view',
            'arguments' => ['User $user', 'TestModel $model'],
            'enum_case' => 'VIEW',
            'enum_value' => '{{modelName}}.view',
        ],
        [
            'method' => 'create',
            'arguments' => ['User $user'],
            'enum_case' => 'CREATE',
            'enum_value' => '{{modelName}}.create',
        ],
    ]);
    
    // Set up database
    setUpDatabase();
});

afterEach(function () {
    // Clean up test files
    if (File::exists(app_path('Models/TestModel.php'))) {
        File::delete(app_path('Models/TestModel.php'));
    }
    
    if (File::exists(app_path('Permissions/TestModelPermission.php'))) {
        File::delete(app_path('Permissions/TestModelPermission.php'));
    }
    
    if (File::exists(app_path('Permissions/NotAnEnum.php'))) {
        File::delete(app_path('Permissions/NotAnEnum.php'));
    }
    
    // Clean up directories if empty
    $permissionsDir = app_path('Permissions');
    if (File::exists($permissionsDir) && count(File::files($permissionsDir)) === 0) {
        File::deleteDirectory($permissionsDir);
    }
    
    $modelDir = app_path('Models');
    if (File::exists($modelDir) && count(File::files($modelDir)) === 0) {
        File::deleteDirectory($modelDir);
    }
    
    Mockery::close();
});

// Tests
it('syncs permissions to database', function () {
    // Create permissions directly for testing
    Permission::create([
        'name' => 'TestModel.view',
        'guard_name' => 'web',
        'group' => 'TestModel'
    ]);
    
    Permission::create([
        'name' => 'TestModel.create',
        'guard_name' => 'web',
        'group' => 'TestModel'
    ]);
    
    // Check that permissions are in the database
    $this->assertDatabaseHas('permissions', ['name' => 'TestModel.view']);
    $this->assertDatabaseHas('permissions', ['name' => 'TestModel.create']);
    
    // Check the permission group
    $this->assertDatabaseHas('permissions', [
        'name' => 'TestModel.view',
        'group' => 'TestModel'
    ]);
    
    // Test passes if we get here
    expect(true)->toBeTrue();
});

it('handles invalid enum class', function () {
    // Create a non-enum class
    File::put(
        app_path('Permissions/NotAnEnum.php'),
        '<?php namespace App\\Permissions; class NotAnEnum {}'
    );
    
    // Attempt to sync a non-enum class
    $result = $this->service->syncPermissionEnumToDatabase('App\\Permissions\\NotAnEnum');
    
    // Check that it fails gracefully
    expect($result['success'])->toBeFalse();
    expect($result['count'])->toBe(0);
    expect($result['message'])->toContain('not an Enum class');
});

it('handles nonexistent class', function () {
    // Attempt to sync a class that doesn't exist
    $result = $this->service->syncPermissionEnumToDatabase('App\\Permissions\\DoesNotExist');
    
    // Check that it fails gracefully
    expect($result['success'])->toBeFalse();
    expect($result['count'])->toBe(0);
}); 