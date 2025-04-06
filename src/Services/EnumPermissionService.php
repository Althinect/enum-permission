<?php

namespace Althinect\EnumPermission\Services;

use Althinect\EnumPermission\Concerns\Helpers;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use Spatie\Permission\Models\Permission;

class EnumPermissionService
{
    use Helpers;

    /**
     * Generate permission enum content for a model.
     *
     * @param  string  $modelClass  Fully qualified model class name
     * @return array Array containing the enum content and path
     */
    public function generateEnumContent(string $modelClass): array
    {
        $permissionCases = $this->getPermissionsStringCasesForEnum($modelClass);
        $modelName = class_basename($modelClass);
        $namespace = str_replace('Models', 'Permissions', (new ReflectionClass($modelClass))->getNamespaceName());
        $modelPath = str_replace('Models', 'Permissions', (new ReflectionClass($modelClass))->getFileName());
        $permissionEnumPath = str_replace('.php', 'Permission.php', $modelPath);

        $permissionStub = File::get(__DIR__.'/../stubs/permission.stub');
        $permissionEnum = str_replace(
            ['{{cases}}', '{{enumName}}', '{{namespace}}'],
            [$permissionCases, $modelName.'Permission', $namespace],
            $permissionStub
        );

        return [
            'content' => $permissionEnum,
            'path' => $permissionEnumPath,
            'className' => $modelName.'Permission',
            'namespace' => $namespace,
        ];
    }

    /**
     * Generate policy content for a model.
     *
     * @param  string  $modelClass  Fully qualified model class name
     * @param  string  $permissionNamespace  Namespace for the permission enum
     * @return array Array containing the policy content and path
     */
    public function generatePolicyContent(string $modelClass, string $permissionNamespace): array
    {
        $policyStub = File::get(__DIR__.'/../stubs/policy.stub');
        $modelName = class_basename($modelClass);
        $namespace = (new ReflectionClass(objectOrClass: $modelClass))->getNamespaceName();
        $modelVariable = lcfirst($modelName);
        $policyName = $modelName.'Policy';
        $userModel = config('enum-permission.user_model');
        $permissionEnumName = $modelName.'Permission';
        $permissionEnum = $permissionNamespace.'\\'.$permissionEnumName;

        $permissions = config('enum-permission.permissions');
        $methods = '';

        $policy = str_replace(
            ['{{namespace}}', '{{modelName}}', '{{permissionEnum}}', '{{policyName}}', '{{model}}', '{{modelVariable}}'],
            [$namespace, $modelName, $permissionEnum, $policyName, $modelClass, $modelVariable],
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

        return [
            'content' => $policy,
            'path' => $policyPath,
            'className' => $policyName,
        ];
    }

    /**
     * Get permissions string cases for enum.
     *
     * @param  string  $model  Fully qualified model class name
     * @return string String containing enum cases
     */
    protected function getPermissionsStringCasesForEnum(string $model): string
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

    /**
     * Get policy method structure.
     *
     * @return string Policy method structure template
     */
    protected function getPolicyMethodStructure(): string
    {
        return
        '    public function {{method}}({{arguments}}): bool'.PHP_EOL.
        '    {'.PHP_EOL.
        '        return $user->hasPermissionTo({{permissionEnumName}}::{{enumCase}});'.PHP_EOL.
        '    }'.PHP_EOL.PHP_EOL;
    }

    /**
     * Sync permission enum to database.
     *
     * @param  string  $permissionClass  Fully qualified permission enum class name
     * @return array Array containing success status and count of synced permissions
     */
    public function syncPermissionEnumToDatabase(string $permissionClass): array
    {
        if (! $this->isEnumClass($permissionClass)) {
            return [
                'success' => false,
                'message' => 'Class is not an Enum class',
                'count' => 0,
            ];
        }

        try {
            $cases = $permissionClass::cases();
            $guards = array_keys(config('auth.guards'));
            $syncedCount = 0;

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
                    $syncedCount++;
                }
            }

            return [
                'success' => true,
                'message' => "Successfully synced {$syncedCount} permissions for {$permissionClass}",
                'count' => $syncedCount,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => "Failed to sync permissions: {$e->getMessage()}",
                'count' => 0,
            ];
        }
    }
}
