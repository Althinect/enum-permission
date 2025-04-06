<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

use Althinect\EnumPermission\Tests\TestCase;
use Illuminate\Support\Facades\Schema;

uses(TestCase::class)
    ->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Set up the database tables needed for permission testing
 */
function setUpDatabase()
{
    // Create required tables directly
    $schema = Schema::connection('testing');

    if (! $schema->hasTable('permissions')) {
        $schema->create('permissions', function ($table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->string('group')->nullable();
            $table->timestamps();
        });
    }

    if (! $schema->hasTable('roles')) {
        $schema->create('roles', function ($table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });
    }

    if (! $schema->hasTable('model_has_permissions')) {
        $schema->create('model_has_permissions', function ($table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type']);
            $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_perms_prim');
        });
    }

    if (! $schema->hasTable('model_has_roles')) {
        $schema->create('model_has_roles', function ($table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type']);
            $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_prim');
        });
    }

    if (! $schema->hasTable('role_has_permissions')) {
        $schema->create('role_has_permissions', function ($table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id'], 'role_has_perms_prim');
        });
    }
}
