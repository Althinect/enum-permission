<?php

namespace Althinect\EnumPermission\Commands;

use Althinect\EnumPermission\Concerns\Helpers;
use Althinect\EnumPermission\Services\EnumPermissionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

use function Laravel\Prompts\select;

class SyncPermissionCommand extends Command
{
    use Helpers;

    public $signature = 'permission:sync {--C|clean} {--path=} {--force}';

    public $description = 'Sync Permissions to the DB';

    /**
     * @var \Althinect\EnumPermission\Services\EnumPermissionService
     */
    protected $service;

    public function __construct(EnumPermissionService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    public function handle(): int
    {
        $this->info('Syncing Permissions...');

        $customPath = $this->option('path');
        $permissionFiles = $this->getPermissionFiles($customPath);

        if (empty($permissionFiles)) {
            $this->warn('No permission enum files found'.($customPath ? ' in path: '.$customPath : ''));

            return self::FAILURE;
        }

        if ($this->option('clean')) {
            if (! $this->option('force')) {
                // confirm if the user wants to clean the permissions
                $confirm = select(
                    required: true,
                    label: 'Do you want to clean the permissions? This will delete all the permissions in the database',
                    options: ['yes', 'no'],
                    default: 'no'
                );

                if ($confirm !== 'yes') {
                    $this->info('Clean operation cancelled');

                    return self::SUCCESS;
                }
            }

            try {
                DB::table('permissions')->delete();

                // Use a database-agnostic approach to reset IDs
                $driver = DB::connection()->getDriverName();
                if ($driver === 'pgsql') {
                    DB::statement('ALTER SEQUENCE permissions_id_seq RESTART WITH 1;');
                } elseif ($driver === 'mysql' || $driver === 'mariadb') {
                    DB::statement('ALTER TABLE permissions AUTO_INCREMENT = 1;');
                }

                $this->info('All permissions have been removed from the database');
            } catch (\Exception $e) {
                $this->error('Failed to clean permissions: '.$e->getMessage());

                return self::FAILURE;
            }
        }

        $syncedCount = 0;
        $failedCount = 0;

        foreach ($permissionFiles as $permissionFile) {
            $permissionClass = $this->extractNamespace(file: $permissionFile->getPathname()).'\\'.$permissionFile->getBasename(suffix: '.php');

            $this->info("Processing {$permissionClass}");

            $result = $this->service->syncPermissionEnumToDatabase($permissionClass);

            if ($result['success']) {
                $this->info($result['message']);
                $syncedCount += $result['count'];
            } else {
                $this->warn($result['message']);
                $failedCount++;
            }
        }

        $this->info("Permissions sync complete. Synced: {$syncedCount}, Failed: {$failedCount}");

        return self::SUCCESS;
    }
}
