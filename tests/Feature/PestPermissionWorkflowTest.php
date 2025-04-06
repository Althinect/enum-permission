<?php

use Althinect\EnumPermission\Commands\EnumPermissionCommand;
use Althinect\EnumPermission\Commands\SyncPermissionCommand;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;

// Setup and teardown
beforeEach(function () {
    // Create a test model
    $modelDir = app_path('Models');
    if (!File::exists($modelDir)) {
        File::makeDirectory($modelDir, 0755, true);
    }
    
    $this->testModelPath = app_path('Models/TestWorkflowModel.php');
    $testModelContent = <<<'EOT'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestWorkflowModel extends Model
{
    protected $table = 'test_workflow_models';
    
    protected $fillable = [
        'name',
        'description',
    ];
}
EOT;
    
    File::put($this->testModelPath, $testModelContent);
    
    // Set up paths for generated files
    $this->testPermissionEnumPath = app_path('Permissions/TestWorkflowModelPermission.php');
    $this->testPolicyPath = app_path('Policies/TestWorkflowModelPolicy.php');
    
    // Configure for testing
    config()->set('enum-permission.models_path', 'app/Models');
    config()->set('enum-permission.user_model', 'App\\Models\\User');
    config()->set('enum-permission.model_super_classes', [
        'Illuminate\\Database\\Eloquent\\Model',
    ]);
    
    // Set up permissions configuration
    config()->set('enum-permission.permissions', [
        [
            'method' => 'view',
            'arguments' => ['User $user', 'TestWorkflowModel $model'],
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
    
    // Set up database for permission syncing
    setUpDatabase();
});

afterEach(function () {
    // Clean up files
    foreach ([$this->testModelPath, $this->testPermissionEnumPath, $this->testPolicyPath] as $path) {
        if (File::exists($path)) {
            File::delete($path);
        }
    }
    
    // Clean up directories
    foreach ([app_path('Models'), app_path('Permissions'), app_path('Policies')] as $dir) {
        if (File::exists($dir) && count(File::files($dir)) === 0) {
            File::deleteDirectory($dir);
        }
    }
});
