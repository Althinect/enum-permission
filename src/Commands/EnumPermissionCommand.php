<?php

namespace Althinect\EnumPermission\Commands;

use Illuminate\Auth\Authenticatable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Reflection;
use ReflectionClass;

use function Laravel\Prompts\multisearch;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\search;
use function Laravel\Prompts\select;

class EnumPermissionCommand extends Command
{
    public $signature = 'model-permission {name?} {--P|policy}';

    public $description = 'Generate Permissions Enum';

    public function handle(): int
    {
        $model = $this->argument('name');

        if ($model !== null) {

            $allModels = $this->getAllModels();
            $modelClass = implode('\\', explode('/', $model));

            // dd($modelClass, $allModels);

            if(!in_array($modelClass, $allModels)){
                $this->warn('Model not found');
                // ask to create a new model
                $createNewModel = select(
                    required: true,
                    label: 'Model not found. Do you want to create a new model & Generate Permission Enum?',
                    options: ['yes', 'no'],
                );

                $modelOptions = ['name' => $model];

                $migrations = select(
                    required: true,
                    label: 'Do you want to create a migration for the model?',
                    options: ['yes', 'no'],
                );

                $migrations === 'yes' ? $modelOptions['--migration'] = true : null;

                $factories = select(
                    required: true,
                    label: 'Do you want to create a factory for the model?',
                    options: ['yes', 'no'],
                );

                $factories === 'yes' ? $modelOptions['--factory'] = true : null;

                $seeders = select(
                    required: true,
                    label: 'Do you want to create a seeder for the model?',
                    options: ['yes', 'no'],
                );

                $seeders === 'yes' ? $modelOptions['--seed'] = true : null;

                // if yes, create a new model with artisan make:model
                if($createNewModel === 'yes'){
                    $this->call('make:model', $modelOptions);
                    sleep(1); // wait for model to be created
                    $this->info('Model created successfully');

                    $models = [$modelClass];

                    return $this->generatePermissionEnums($models);
                } else {
                    $this->info('Permission enum generation skipped for ' . $model);

                    return self::FAILURE;
                }
            } else {
                $models = [$modelClass];
                return $this->generatePermissionEnums($models);
            }
        }

        if ($model === null) {
            $this->warn('No name provided. Please select a model to generate permissions for.');

            $allModels = $this->getAllModels();
            $models = multiselect(
                required: true,
                label: 'Select a model:',
                options: ['all', ...$allModels],
            );

            if (in_array('all', $models)) {
                $models = $allModels;
            }

            return $this->generatePermissionEnums($models);

        }

        return self::FAILURE;
    }

    protected function generatePermissionEnums($models): int
    {
        $permissionCases = '';
            foreach ($models as $model) {
                $permissionCases = $this->getPermissionsStringCasesForEnum($model);

                $modelName = class_basename($model);
                $namespace = (new ReflectionClass($model))->getNamespaceName();
                $namespace = str_replace('Models', 'Permissions', $namespace);
                $modelPath = (new ReflectionClass($model))->getFileName();
                $permissionEnumPath = str_replace('Models', 'Permissions', $modelPath);
                $permissionEnumPath = str_replace('.php', 'Permission.php', $permissionEnumPath);

                $permissionStub = File::get('vendor/althinect/enum-permission/src/stubs/permission.stub');
                $permissionEnum = str_replace('{{cases}}', $permissionCases, $permissionStub);
                $enumName = $modelName . 'Permission';
                $permissionEnum = str_replace('{{enumName}}', $enumName, $permissionEnum);
                $permissionEnum = str_replace('{{namespace}}', $namespace, $permissionEnum);

                File::ensureDirectoryExists(dirname($permissionEnumPath));

                //ask to overwrite if file exists
                if (File::exists($permissionEnumPath)) {
                    $overwrite = select(
                        required: true,
                        label: 'File' . $permissionEnumPath . ' already exists. Do you want to overwrite it?',
                        options: ['yes', 'no'],
                    );

                    if ($overwrite === 'no') {
                        $this->info('Permission enum generation skipped for ' . $modelName);
                        continue;
                    }
                }

                File::put($permissionEnumPath, $permissionEnum);

                $this->info('Permission enum generated successfully for ' . $modelName);

                if ($this->option('policy')) {
                    // dd($model, $modelName);
                    $this->info('Generating policy for ' . $modelName);
                    $this->generatePolicy($model);
                }
            }

            return self::SUCCESS;
    }

    protected function generatePolicy($model): void
    {
        $policyStub = File::get('vendor/althinect/enum-permission/src/stubs/policy.stub');
        $modelName = class_basename($model);
        $policy = str_replace('{{modelName}}', $modelName, $policyStub);

        $policy = str_replace('{{model}}', $model, $policy);
        $policy = str_replace('{{namespace}}', (new ReflectionClass($model))->getNamespaceName(), $policy);

        $variableName = lcfirst($modelName);
        $policy = str_replace('{{modelVariable}}', $variableName, $policy);

        $policyName = $modelName . 'Policy';
        $policy = str_replace('{{policyName}}', $policyName, $policy);

        $userModel = config('enum-permission.user_model');
        $policy = str_replace('{{userModel}}', $userModel, $policy);

        $policyPath = app_path('App/Policies/' . $modelName . 'Policy.php');

        File::ensureDirectoryExists(dirname($policyPath));

        //ask to overwrite if file exists
        if (File::exists($policyPath)) {
            $overwrite = select(
                required: true,
                label: 'File' . $policyPath . ' already exists. Do you want to overwrite it?',
                options: ['yes', 'no'],
            );

            if ($overwrite === 'no') {
                $this->info('Policy generation skipped for ' . $modelName);
                return;
            }
        }

        File::put($policyPath, $policy);
    }

    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'model' => fn () => search(
                label: 'Search for a model:',
                placeholder: 'User',
                options: fn ($value) => $this->getAllModels(),
            ),
        ];
    }

    private function getAllModels(): array
    {
        $models = $this->getClassesInDirectory(app_path(config('enum-permission.models_path')));

        $modelNames = [];
        foreach ($models as $model) {
            if (
                $model->isSubclassOf(Model::class) ||
                $model->isSubclassOf(Authenticatable::class) &&
                $model->getName() !== null
            ) {
                $modelNames[] = $model->getName();
            }
        }

        return $modelNames;
    }

    private function getClassesInDirectory($path): array
    {
        $files = File::allFiles($path);
        $models = [];

        foreach ($files as $file) {
            $namespace = $this->extractNamespace($file);
            $class = $namespace . '\\' . $file->getFilenameWithoutExtension();
            $model = new ReflectionClass($class);
            if (!$model->isAbstract()) {
                $models[] = $model;
            }
        }

        return $models;
    }

    private function extractNamespace($file): string
    {
        $ns = '';
        $handle = fopen($file, 'r');
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                if (preg_match('/namespace\s+([a-zA-Z0-9_\\\\]+);/', $line, $matches)) {
                    $ns = $matches[1];
                    break;
                }
            }
            fclose($handle);
        }

        return $ns;
    }

    private function getPermissionsStringCasesForEnum($model): string
    {
        $permissions = config('enum-permission.permissions_cases');
        $cases = '';

        $modelName = class_basename($model);

        foreach ($permissions as $permission => $case) {
            $cases .= '    case ' . $permission . ' =  \'';
            $cases .= str_replace('{{ ModelName }}', $modelName, $case) . '\';' . PHP_EOL;
        }

        return $cases;
    }
}
