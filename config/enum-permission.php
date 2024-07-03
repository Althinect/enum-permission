<?php

return [
    'models_path' => 'Domains',
    'enum_path_should_follow_models_path' => true,
    'user_model' => 'App\Models\User',

    'permissions_cases' => [
        'VIEW_ANY' => '{{ ModelName }}.viewAny',
        'VIEW' => '{{ ModelName }}.view',
        'CREATE' => '{{ ModelName }}.create',
        'UPDATE' => '{{ ModelName }}.update',
        'DELETE' => '{{ ModelName }}.delete',
        'RESTORE' => '{{ ModelName }}.restore',
        'FORCE_DELETE' => '{{ ModelName }}.forceDelete',
    ],
];
