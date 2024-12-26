<?php

//write test for @see \App\Commands\SyncPermissionCommand

use Illuminate\Support\Facades\Artisan;

beforeEach(function() {
    \Spatie\Permission\Models\Permission::query()->delete();
});

test('it can sync permissions', function() {
    // Act
    Artisan::call('permission:sync');

    // Assert
    expect(\Spatie\Permission\Models\Permission::where('name', 'view-users')->where('guard_name', 'web')->exists())->toBeTrue();
    expect(\Spatie\Permission\Models\Permission::where('name', 'create-users')->where('guard_name', 'web')->exists())->toBeTrue();
});

test('it does not duplicate existing permissions', function() {
    // Arrange
    \Spatie\Permission\Models\Permission::create([
        'name' => 'view-users',
        'guard_name' => 'web'
    ]);

    $initialCount = \Spatie\Permission\Models\Permission::count();

    // Act
    Artisan::call('permission:sync');

    // Assert
    expect(\Spatie\Permission\Models\Permission::count())->toBe($initialCount);
});

test('it returns success message', function() {
    // Act
    $result = Artisan::call('permission:sync');

    // Assert
    expect($result)->toBe(0);
    expect(Artisan::output())->toContain('Permissions synced successfully');
});

test('it can handle empty permissions table', function() {
    // Act
    Artisan::call('permission:sync');
    
    // Assert
    expect(\Spatie\Permission\Models\Permission::count())->toBeGreaterThan(0);
});
