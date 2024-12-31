<?php

return [
    // ---------------------------------------------------------------------------
    // The path where the Models can be discovered within the "app" directory
    // ---------------------------------------------------------------------------

    'models_app_path' => 'Models',

    // ---------------------------------------------------------------------------
    // Enums will be generated in a similar path to the models
    //
    // Example:
    // App\Models\User -> App\Permissions\UserPermission
    // App\Domain\Shared\Models\User -> App\Domain\Shared\Permissions\UserPermission
    // ---------------------------------------------------------------------------

    'enum_path_should_follow_models_path' => true,

    // ---------------------------------------------------------------------------
    // The path where the Enum classes will be generated
    // ---------------------------------------------------------------------------

    'user_model' => 'App\Models\User',

    // ---------------------------------------------------------------------------
    // The classes that the models should extend. This helps in model discovery
    // ---------------------------------------------------------------------------

    'model_super_classes' => [
        'Illuminate\Database\Eloquent\Model',
        'Illuminate\Foundation\Auth\User',
    ],

    // ---------------------------------------------------------------------------
    // This is a template for the Policy classes that will be
    // generated. Each permission will be a method in the policy
    //
    // method: The method name in the policy
    // arguments: The arguments that the method will take
    // enum_case: The case of the enum value
    // enum_value: The value of the enum
    // ---------------------------------------------------------------------------
    // WARNING: Do not change the {{modelName}} and {{userModelName}} placeholders
    // as they will be replaced by the actual model and user model names. You can
    // however change the {{modelName}}.{{method}} placeholders to match your
    // Refer: Althinect\EnumPermission\EnumPermissionCommand.php
    // ---------------------------------------------------------------------------

    'permissions' => [
        [
            'method' => 'viewAny',
            'arguments' => ['{{userModelName}} $user'],
            'enum_case' => 'VIEW_ANY',
            'enum_value' => '{{modelName}}.viewAny',
        ],
        [
            'method' => 'view',
            'arguments' => ['{{userModelName}} $user', '{{modelName}} $model'],
            'enum_case' => 'VIEW',
            'enum_value' => '{{modelName}}.view',
        ],
        [
            'method' => 'create',
            'arguments' => ['{{userModelName}} $user'],
            'enum_case' => 'CREATE',
            'enum_value' => '{{modelName}}.create',
        ],
        [
            'method' => 'update',
            'arguments' => ['{{userModelName}} $user', '{{modelName}} $model'],
            'enum_case' => 'UPDATE',
            'enum_value' => '{{modelName}}.update',
        ],
        [
            'method' => 'delete',
            'arguments' => ['{{userModelName}} $user', '{{modelName}} $model'],
            'enum_case' => 'DELETE',
            'enum_value' => '{{modelName}}.delete',
        ],
        [
            'method' => 'restore',
            'arguments' => ['{{userModelName}} $user', '{{modelName}} $model'],
            'enum_case' => 'RESTORE',
            'enum_value' => '{{modelName}}.restore',
        ],
        [
            'method' => 'forceDelete',
            'arguments' => ['{{userModelName}} $user', '{{modelName}} $model'],
            'enum_case' => 'FORCE_DELETE',
            'enum_value' => '{{modelName}}.forceDelete',
        ],
    ],

    // ---------------------------------------------------------------------------
    // The guards that the permissions will be created for
    // ---------------------------------------------------------------------------

    'guards' => [
        'web',
        'api',
    ],
];
