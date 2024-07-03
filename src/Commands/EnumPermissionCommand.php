<?php

namespace Althinect\EnumPermission\Commands;

use Illuminate\Auth\Authenticatable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use ReflectionClass;

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
            sleep(1);
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
                $this->generatePolicy($model);
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

    protected function generatePolicy($model): void
    {
        $policyStub = File::get('vendor/althinect/enum-permission/src/stubs/policy.stub');
        $modelName = class_basename($model);
        $namespace = (new ReflectionClass($model))->getNamespaceName();
        $variableName = lcfirst($modelName);
        $policyName = $modelName.'Policy';
        $userModel = config('enum-permission.user_model');

        $policy = str_replace(
            ['{{modelName}}', '{{model}}', '{{namespace}}', '{{modelVariable}}', '{{policyName}}', '{{userModel}}'],
            [$modelName, $model, $namespace, $variableName, $policyName, $userModel],
            $policyStub
        );

        $policyPath = app_path('App/Policies/'.$policyName.'.php');
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

    private function getAllModels(): array
    {
        return array_filter($this->getClassesInDirectory(app_path(config('enum-permission.models_path'))), function ($model) {
            return $model->isSubclassOf(Model::class) || $model->isSubclassOf(Authenticatable::class);
        });
    }

    private function getClassesInDirectory($path): array
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
            $cases .= '    case '.$permission.' = \''.str_replace('{{ ModelName }}', $modelName, $case).'\';'.PHP_EOL;
        }

        return $cases;
    }
}
