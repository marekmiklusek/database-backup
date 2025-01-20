<?php

namespace MarekMiklusek\LaravelDatabaseBackup\Traits;

trait EnumHelper
{
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}