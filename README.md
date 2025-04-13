# Laravel Enum Permissions

[![Latest Version on Packagist](https://img.shields.io/packagist/v/althinect/enum-permission.svg?style=flat-square)](https://packagist.org/packages/althinect/enum-permission)
[![Total Downloads](https://img.shields.io/packagist/dt/althinect/enum-permission.svg?style=flat-square)](https://packagist.org/packages/althinect/enum-permission)
![GitHub Actions](https://github.com/Althinect/enum-permission/actions)

A Laravel package to easily manage Permissions with Enums and sync these permissions to your database. This package is built on top of Spatie's Laravel-Permission package, providing an enum-based approach to permission management. It's fully configured via the config file located at `config/enum-permission.php`.

## Requirements

- PHP 8.1 or higher (required for Enums support)
- Laravel 10.0 or higher
- Spatie/Laravel-Permission package

## Installation

```bash
composer require althinect/enum-permission
```

This package automatically installs Spatie's Laravel-Permission package as a dependency, so you don't need to require it separately.

### Spatie configs
You will need to run the migrations and add the necessary configs according to the Spatie Permissions documentation

After installation, publish the configuration file:

```bash
php artisan vendor:publish --tag="enum-permission-config"
```

Then run the migrations to set up all required tables including the permissions tables from Spatie and the group column added by this package:

```bash
php artisan migrate
```

## Configuration

The configuration file will be published to `config/enum-permission.php`. Customize your permissions, models path, and other options there.

### Configuration Options

```php
return [
    // Path to your models
    'models_path' => 'app/Models',
    
    // Your User model
    'user_model' => \App\Models\User::class,
    
    // Classes that models should extend for discovery
    'model_super_classes' => [
        'Illuminate\Database\Eloquent\Model',
        'Illuminate\Foundation\Auth\User',
    ],
    
    // Permission definitions for policy methods
    'permissions' => [
        [
            'method' => 'viewAny',
            'arguments' => ['{{userModelName}} $user'],
            'enum_case' => 'VIEW_ANY',
            'enum_value' => '{{modelName}}.view-any'
        ],
        // ... other permissions
    ],
    
    // Auth guards to create permissions for
    'guards' => [
        'web',
        'api',
    ],
    
    // Whether to sync permission groups
    'sync_permission_group' => false,
];
```

## Migrations

This package relies on the database tables created by the Spatie Laravel-Permission package and automatically adds a 'group' column to the permissions table for better organization.

When you install this package and run migrations, two things happen:

1. The Spatie migrations create the core permission tables:
   - `permissions` - Stores all permissions
   - `roles` - Stores all roles
   - `model_has_permissions` - Maps permissions to users or other models
   - `model_has_roles` - Maps roles to users or other models
   - `role_has_permissions` - Maps permissions to roles

2. This package adds its own migration to enhance the permissions table:
   - Adds a `group` column to the `permissions` table
   - Creates an index on the `group` column for faster queries

The `group` column is used when `sync_permission_group` is enabled in the config, allowing permissions to be organized by model name, which is especially useful for UI-based permission management systems.

## Usage

### Generating Permission Enums

The `permission:make` command generates permission enums (and policies if requested) for your models.

```bash
# Generate for a specific model
php artisan permission:make User

# Generate with policy
php artisan permission:make User --policy

# Skip confirmation prompts (useful for CI/CD)
php artisan permission:make User --force

# Interactive selection of models
php artisan permission:make
```

### Syncing Permissions to Database

The `permission:sync` command scans for permission enums and syncs them to the database.

```bash
# Sync all permissions
php artisan permission:sync

# Clean existing permissions before sync
php artisan permission:sync --clean

# Skip confirmation prompts (useful for CI/CD)
php artisan permission:sync --force

# Specify a custom path to scan for permissions
php artisan permission:sync --path=app/Domain/Auth/Permissions
```

### Using Generated Permissions

```php
// In your controllers or services
if ($user->hasPermissionTo(PostPermission::CREATE)) {
    // User can create posts
}

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
├── Permissions/  (mirrors your Models directory structure)
│   └── UserPermission.php
└── Policies/
    └── UserPolicy.php
```

If your models use domain-driven structure, permission enums will follow the same structure:

```
app/
├── Domain/
│   └── Blog/
│       ├── Models/
│       │   └── Post.php
│       └── Permissions/
│           └── PostPermission.php
└── Policies/
    └── PostPolicy.php
```

## Available Commands

- `permission:make {modelName?} {--P|policy} {--force}` - Generate permission enums and optional policies
- `permission:sync {--C|clean} {--path=} {--force}` - Sync permissions to database

## Examples

### Generated Permission Enum

```php
namespace App\Permissions;

use Althinect\EnumPermission\Concerns\HasPermissionGroup;

enum UserPermission: string
{
    use HasPermissionGroup;

    case VIEW_ANY = 'User.view-any';
    case VIEW = 'User.view';
    case CREATE = 'User.create';
    case UPDATE = 'User.update';
    case DELETE = 'User.delete';
    case RESTORE = 'User.restore';
    case FORCE_DELETE = 'User.force-delete';
}
```

### Generated Policy

```php
namespace App\Policies;

use App\Models\User;
use App\Permissions\UserPermission;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(UserPermission::VIEW_ANY);
    }
    
    public function view(User $user, User $model): bool
    {
        return $user->hasPermissionTo(UserPermission::VIEW);
    }
    
    // Additional methods for create, update, delete, etc.
}
```

### Permission Groups

When `sync_permission_group` is enabled in the config, permissions will be grouped by model name, which is helpful for UI-based permission management:

```php
// In UserPermission.php
public static function getPermissionGroup(): string
{
    return str_replace('Permission', '', class_basename(static::class)); // Returns "User"
}
```

This feature uses the `group` column added to the `permissions` table by this package's migration. The permissions are grouped automatically during the sync process:

```php
// Example of how permissions are stored with groups
[
    'name' => 'User.view',
    'guard_name' => 'web',
    'group' => 'User' // <-- This groups all User permissions together
]
```

This grouping makes it easy to:
- Create model-based permission management UIs
- Filter permissions by model in your admin panels
- Apply batch operations to all permissions of a specific model

## Error Handling

The package includes comprehensive error handling:

- Database compatibility across PostgreSQL, MySQL, and other supported systems
- Graceful failure when encountering invalid classes
- File operation safeguards
- Exception reporting for debugging

## Extending the Package

You can extend the package's functionality by:

1. Customizing the permission stubs in the config file
2. Adding custom permission groups
3. Creating middleware that uses the permission enums
4. Building custom UI components for managing permissions

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This package is open-source software licensed under the MIT license.

