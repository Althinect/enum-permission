# Laravel Enum Permissions

A Laravel package to easily generate permission classes with enums using Models
*** This package uses Spatie/Permissions under the hood ***

## Requirements

- PHP 8.1 or higher
- Laravel 10.0 or higher

## Installation

```bash
composer require althinect/enum-permission
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag="enum-permission-config"
```

The configuration file will be published to `config/enum-permission.php`.

### Configuration Options

```php
return [
    'models_path' => 'Models', // Path to your models
    'user_model' => \App\Models\User::class, // Your User model
    'permissions' => [
        [
            'method' => 'viewAny',
            'arguments' => ['User $user'],
            'enum_case' => 'VIEW_ANY',
            'enum_value' => '{{modelName}}.viewAny'
        ],
        // ... other permissions
    ]
];
```

## Usage

### Generating Permission Enums

```bash
# Generate for a specific model
php artisan permission:make User

# Generate with policy
php artisan permission:make User --policy

# Interactive selection of models
php artisan permission:make
```

### Syncing Permissions to Database

```bash
# Sync all permissions
php artisan permission:sync

# Clean existing permissions before sync
php artisan permission:sync --clean
```

### Using Generated Permissions

```php
// In your policies
public function view(User $user, Post $post): bool
{
    return $user->hasPermissionTo(PostPermission::VIEW);
}
```

## Directory Structure

After generation, your files will be organized as follows:

```
app/
├── Models/
│   └── User.php
├── Permissions/
│   └── UserPermission.php
└── Policies/
    └── UserPolicy.php
```

## Available Commands

- `permission:make {model?} {--P|policy}` - Generate permission enums
- `permission:sync {--C|clean}` - Sync permissions to database

## Examples

### Generated Permission Enum

```php
namespace App\Permissions;

enum UserPermission: string
{
    case VIEW_ANY = 'User.viewAny';
    case VIEW = 'User.view';
    case CREATE = 'User.create';
    case UPDATE = 'User.update';
    case DELETE = 'User.delete';
    case RESTORE = 'User.restore';
    case FORCE_DELETE = 'User.forceDelete';
}
```

### Using with Policies

```php
use App\Permissions\UserPermission;

class UserPolicy
{
    public function view(User $user, User $model): bool
    {
        return $user->hasPermissionTo(UserPermission::VIEW);
    }
}
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This package is open-source software licensed under the MIT license.

