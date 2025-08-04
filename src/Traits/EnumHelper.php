<?php

declare(strict_types=1);

namespace MarekMiklusek\DatabaseBackup\Traits;

trait EnumHelper
{
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
