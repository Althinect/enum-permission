<?php

namespace Althinect\EnumPermission\Commands;

use Althinect\EnumPermission\Concerns\Helpers;
use Althinect\EnumPermission\Services\EnumPermissionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ReflectionClass;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\search;
use function Laravel\Prompts\select;

class EnumPermissionCommand extends Command
{
    use Helpers;

    public $signature = 'permission:make {modelName?} {--P|policy} {--force}';

    public $description = 'Generate Permissions Enum';

    /**
     * @var \Althinect\EnumPermission\Services\EnumPermissionService
     */
    protected $service;

    public function __construct(EnumPermissionService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

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
            try {
                $enumData = $this->service->generateEnumContent($model);

                File::ensureDirectoryExists(dirname($enumData['path']));

                if (File::exists($enumData['path'])) {
                    if ($this->option('force') || $this->shouldOverwriteFile($enumData['path'])) {
                        File::put($enumData['path'], $enumData['content']);
                    } else {
                        $this->info('Permission enum generation skipped for '.class_basename($model));

                        continue;
                    }
                } else {
                    File::put($enumData['path'], $enumData['content']);
                }

                $this->info('Permission enum generated successfully for '.class_basename($model));

                if ($this->option('policy')) {
                    $this->info('Generating policy for '.class_basename($model));
                    $this->generatePolicy($model, $enumData['namespace']);
                }
            } catch (\Exception $e) {
                $this->error('Failed to generate permission enum for '.$model.': '.$e->getMessage());

                continue;
            }
        }

        return self::SUCCESS;
    }

    protected function generatePolicy($model, $permissionNamespace): void
    {
        try {
            $policyData = $this->service->generatePolicyContent($model, $permissionNamespace);

            File::ensureDirectoryExists(dirname($policyData['path']));

            if (File::exists($policyData['path'])) {
                if ($this->option('force') || $this->shouldOverwriteFile($policyData['path'])) {
                    File::put($policyData['path'], $policyData['content']);
                } else {
                    $this->info('Policy generation skipped for '.class_basename($model));

                    return;
                }
            } else {
                File::put($policyData['path'], $policyData['content']);
            }

            $this->info('Policy generated successfully for '.class_basename($model));
        } catch (\Exception $e) {
            $this->error('Failed to generate policy for '.$model.': '.$e->getMessage());
        }
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

        return array_map(function ($model) {
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
