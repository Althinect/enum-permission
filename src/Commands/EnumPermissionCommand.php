<?php

namespace Althinect\EnumPermission\Commands;

use Althinect\EnumPermission\Concerns\Helpers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ReflectionClass;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\search;
use function Laravel\Prompts\select;

class EnumPermissionCommand extends Command
{
    use Helpers;

    public $signature = 'permission:make {modelName?} {--P|policy}';

    public $description = 'Generate Permissions Enum';

    public function handle(): int
    {
        $model = $this->argument('modelName');

        if ($model !== null) {
            $allModels = $this->getAllModels();
            $modelClass = implode('\\', explode('/', $model));

            if (! in_array($modelClass, $allModels)) {
                $this->warn('Model not found');
                if ($this->createNewModel($model)) {
                    return self::SUCCESS;
                }

                return self::FAILURE;
            }

            return $this->generatePermissionEnums([$modelClass]);
        }

        return $this->promptForModels();
    }

    protected function createNewModel($model): bool
    {
        $createNewModel = select(
            required: true,
            label: 'Model not found. Do you want to create a new model & Generate Permission Enum?',
            options: ['yes', 'no'],
        );

        if ($createNewModel === 'yes') {
            $modelOptions = ['name' => $model];

            $modelOptions['--migration'] = select(
                required: true,
                label: 'Do you want to create a migration for the model?',
                options: ['yes', 'no'],
            ) === 'yes';

            $modelOptions['--factory'] = select(
                required: true,
                label: 'Do you want to create a factory for the model?',
                options: ['yes', 'no'],
            ) === 'yes';

            $modelOptions['--seed'] = select(
                required: true,
                label: 'Do you want to create a seeder for the model?',
                options: ['yes', 'no'],
            ) === 'yes';

            $this->call('make:model', $modelOptions);
            $this->info('Model created successfully');

            return $this->generatePermissionEnums([implode('\\', explode('/', $model))]) === self::SUCCESS;
        }

        $this->info('Permission enum generation skipped for '.$model);

        return false;
    }

    protected function promptForModels(): int
    {
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

    protected function generatePermissionEnums(array $models): int
    {
        foreach ($models as $model) {
            $permissionCases = $this->getPermissionsStringCasesForEnum($model);

            $modelName = class_basename($model);
            $namespace = str_replace('Models', 'Permissions', (new ReflectionClass($model))->getNamespaceName());
            $modelPath = str_replace('Models', 'Permissions', (new ReflectionClass($model))->getFileName());
            $permissionEnumPath = str_replace('.php', 'Permission.php', $modelPath);

            $permissionStub = File::get('vendor/althinect/enum-permission/src/stubs/permission.stub');
            $permissionEnum = str_replace(['{{cases}}', '{{enumName}}', '{{namespace}}'], [$permissionCases, $modelName.'Permission', $namespace], $permissionStub);

            File::ensureDirectoryExists(dirname($permissionEnumPath));

            if (File::exists($permissionEnumPath)) {
                if ($this->shouldOverwriteFile($permissionEnumPath)) {
                    File::put($permissionEnumPath, $permissionEnum);
                } else {
                    $this->info('Permission enum generation skipped for '.$modelName);

                    continue;
                }
            } else {
                File::put($permissionEnumPath, $permissionEnum);
            }

            $this->info('Permission enum generated successfully for '.$modelName);

            if ($this->option('policy')) {
                $this->info('Generating policy for '.$modelName);
                $this->generatePolicy($model, $namespace);
            }
        }

        return self::SUCCESS;
    }

    protected function shouldOverwriteFile(string $filePath): bool
    {
        $overwrite = select(
            required: true,
            label: 'File '.$filePath.' already exists. Do you want to overwrite it?',
            options: ['yes', 'no'],
        );

        return $overwrite === 'yes';
    }

    protected function generatePolicy($model, $permissionNamespace): void
    {
        $policyStub = File::get('vendor/althinect/enum-permission/src/stubs/policy.stub');
        $modelName = class_basename($model);
        $namespace = (new ReflectionClass(objectOrClass: $model))->getNamespaceName();
        $modelVariable = lcfirst($modelName);
        $policyName = $modelName.'Policy';
        $userModel = config('enum-permission.user_model');
        $permissionEnumName = $modelName.'Permission';
        $permissionEnum = $permissionNamespace.'\\'.$permissionEnumName;

        $permissions = config('enum-permission.permissions');
        $methods = '';

        $policy = str_replace(
            ['{{namespace}}', '{{modelName}}', '{{permissionEnum}}', '{{policyName}}', '{{model}}', '{{modelVariable}}'],
            [$namespace, $modelName, $permissionEnum, $policyName, $model, $modelVariable],
            $policyStub
        );

        foreach ($permissions as $permission) {
            $arguments = implode(', ', $permission['arguments']);
            $enumCase = $permission['enum_case'];
            $enumValue = $permission['enum_value'];

            $policyMethodStructure = $this->getPolicyMethodStructure();

            $methods .= str_replace(
                ['{{method}}', '{{arguments}}', '{{enumValue}}', '{{enumCase}}'],
                [$permission['method'], $arguments, $enumValue, $enumCase],
                $policyMethodStructure
            );
        }
        $policy = str_replace('{{methods}}', $methods, $policy);

        $userModelName = class_basename($userModel);

        $policy = str_replace(
            ['{{userModel}}', '{{userModelName}}', '{{modelName}}', '{{permissionEnumName}}'],
            [$userModel, $userModelName, $modelName, $permissionEnumName],
            $policy
        );

        $policyPath = app_path('Policies/'.$policyName.'.php');
        File::ensureDirectoryExists(dirname($policyPath));

        if (File::exists($policyPath)) {
            if (! $this->shouldOverwriteFile($policyPath)) {
                $this->info('Policy generation skipped for '.$modelName);

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

    protected function getAllModels(): array
    {
        $models = array_filter($this->getClassesInDirectory(base_path(config(key: 'enum-permission.models_path'))), function ($model) {
            return collect(config('enum-permission.model_super_classes'))->contains(fn ($superClass): mixed => $model->isSubclassOf($superClass));
        });

        return $models = array_map(function ($model) {
            return $model->getName();
        }, $models);
    }

    protected function getClassesInDirectory(string $path): array
    {
        $files = File::allFiles($path);
        $models = [];

        foreach ($files as $file) {
            $namespace = $this->extractNamespace($file);
            $class = $namespace.'\\'.$file->getFilenameWithoutExtension();
            $model = new ReflectionClass($class);
            if (! $model->isAbstract()) {
                $models[] = $model;
            }
        }

        return $models;
    }

    protected function extractNamespace($file): string
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

    protected function getPermissionsStringCasesForEnum($model): string
    {
        $permissions = config('enum-permission.permissions');
        $cases = '';

        $modelName = class_basename($model);

        foreach ($permissions as $permission) {
            $enumCase = $permission['enum_case'];
            $enumValue = $permission['enum_value'];
            $cases .= '    case '.$enumCase.' = \''.$enumValue.'\';'.PHP_EOL;

            $cases = str_replace('{{modelName}}', $modelName, $cases);
        }

        return $cases;
    }

    protected function getPolicyMethodStructure(): string
    {
        return
    '    public function {{method}}({{arguments}}): bool'.PHP_EOL.
        '    {'.PHP_EOL.
        '        return $user->hasPermissionTo({{permissionEnumName}}::{{enumCase}});'.PHP_EOL.
        '    }'.PHP_EOL.PHP_EOL;
    }
}
