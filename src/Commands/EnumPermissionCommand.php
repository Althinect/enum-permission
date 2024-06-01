<?php

namespace Althinect\EnumPermission\Commands;

use Illuminate\Console\Command;

class EnumPermissionCommand extends Command
{
    public $signature = 'enum-permission';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
