<?php

namespace Althinect\EnumPermission\Concerns;

trait HasPermissionGroup
{
    public static function getPermissionGroup(): string
    {
        return str_replace('Permission', '', class_basename(static::class));
    }
}
