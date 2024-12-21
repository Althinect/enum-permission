<?php

return [
    'models_path' => 'Domains',
    'enum_path_should_follow_models_path' => true,
    'user_model' => 'App\Models\User',

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

    'guards' => [
        'web',
        'api',
    ],
];
