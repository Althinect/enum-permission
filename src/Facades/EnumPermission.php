<?php

namespace Althinect\EnumPermission\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Althinect\EnumPermission\EnumPermission
 */
class EnumPermission extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Althinect\EnumPermission\EnumPermission::class;
    }
}
