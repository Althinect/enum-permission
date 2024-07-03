
# About

The **Althinect Enum Permission** package helps in generating Permission Enums for models in your application. It also provides an option to generate policy files for models as well as models that do not exist.

## Installation

1. Require the package using Composer:
    ```sh
    composer require althinect/enum-permission
    ```

2. Publish the configuration file:
    ```sh
    php artisan vendor:publish --provider="Althinect\EnumPermission\EnumPermissionServiceProvider"
    ```

## Usage

The command can be used to generate permission enums for a specific model or multiple models. Additionally, it can create new models if they don't exist and generate associated migration, factory, and seeder files.

### Generate Permission Enum for a Specific Model

To generate a permission enum for a specific model, run the following command:

```sh
php artisan model-permission {ModelName}
```

Replace `{ModelName}` with the name of your model. For example:

```sh
php artisan model-permission User
```

### Generate Permission Enum for Multiple Models

If you don't specify a model name, the command will prompt you to select from a list of available models:

```sh
php artisan model-permission
```

You can select multiple models from the list or choose `all` to generate permission enums for all models.

### Create a New Model

If the specified model does not exist, the command will prompt you to create a new model and generate the permission enum for it. You will also be prompted to create migration, factory, and seeder files for the new model.

### Generate Policy Files

You can use the `--policy` option to generate policy files for the models:

```sh
php artisan model-permission {ModelName} --policy
```

### Options

- `name`: The name of the model for which you want to generate the permission enum.
- `--policy` or `-P`: Generate a policy file for the specified model.

### Prompts

The command will prompt you for the following:

- Whether to create a new model if the specified model does not exist.
- Whether to create migration, factory, and seeder files for the new model.
- Whether to overwrite existing permission enum and policy files if they already exist.

## Configuration

The configuration file `config/enum-permission.php` allows you to define the following:

- `models_path`: The directory path where your models are stored.
- `permissions_cases`: An array defining the permission cases for the enums.
- `user_model`: The fully qualified class name of your User model.

Example configuration:

```php
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
```

## Stubs

The command uses stub files to generate the permission enums and policy files. These stubs are located in the `vendor/althinect/enum-permission/src/stubs` directory.

- `permission.stub`: The stub file for generating permission enums.
- `policy.stub`: The stub file for generating policy files.

You can customize these stubs according to your needs.

## Examples

### Example: Generating Permission Enum for User Model

```sh
php artisan model-permission User
```

### Example: Generating Permission Enum and Policy for Post Model

```sh
php artisan model-permission Post --policy
```

### Example: Creating a New Model and Generating Permission Enum

```sh
php artisan model-permission NewModel
```

You will be prompted to create the model, migration, factory, and seeder files.

## License

This package is open-source software licensed under the [MIT license](LICENSE).

