<?php

namespace Althinect\EnumPermission\Commands;

use Althinect\EnumPermission\Concerns\Helpers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use ReflectionException;
use Spatie\Permission\Models\Permission;

use function Laravel\Prompts\search;
use function Laravel\Prompts\select;

class SyncPermissionCommand extends Command
{
    use Helpers;

    public $signature = 'permission:sync {--C|clean}';

    public $description = 'Sync Permissions to the DB';

    public function handle(): int
    {
        $this->info('Syncing Permissions...');

        $permissionFiles = $this->getPermissionFiles();

        if ($this->option('clean')) {

            // confirm if the user wants to clean the permissions
            select(
                required: true,
                label: 'Do you want to clean the permissions? This will delete all the permissions in the database',
                options: ['yes', 'no'],
                default: 'no'
            );

            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            DB::table('permissions')->truncate();
        }

        foreach ($permissionFiles as $permissionFile) {

            $permissionClass = $this->extractNamespace($permissionFile->getPathname()).'\\'.$permissionFile->getBasename('.php');

            $this->info("Processing {$permissionClass}");

            if (! $this->isEnumClass($permissionClass)) {
                $this->warn('Class is not an Enum class');

                continue;
            }

            $cases = $permissionClass::cases();

            $guards = array_keys(config('auth.guards'));

            foreach ($guards as $guard) {
                foreach ($cases as $case) {
                    $permission = [
                        'name' => $case->value,
                        'guard_name' => $guard,
                    ];

                    if (config('enum-permission.syncPermissionGroup')) {
                        $permission['group'] = $permissionClass::getPermissionGroup();
                    }

                    Permission::firstOrCreate($permission);
                }
            }
        }

        return self::SUCCESS;
    }

    private function isEnumClass(string $classPath): bool
    {
        try {
            $reflection = new ReflectionClass($classPath);

            return $reflection->isEnum();
        } catch (ReflectionException $e) {
            return false;
        }
    }

    public function getPermissionFiles()
    {
        $permissionClasses = [];
        $files = File::allFiles(app_path());

        // Search all the Enum files that are Suffixed with Permission
        foreach ($files as $file) {
            $fileName = $file->getFilename();
            $fileExtension = $file->getExtension();

            if ($fileExtension === 'php' && strpos($fileName, 'Permission') !== false) {
                $permissionClasses[] = $file;
            }
        }

        return $permissionClasses;
    }
}
