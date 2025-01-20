<?php

namespace MarekMiklusek\LaravelDatabaseBackup\Enums;

use MarekMiklusek\LaravelDatabaseBackup\Traits\EnumHelper;

enum Driver: string 
{
    use EnumHelper;

    case LOCAL = 'local';
    case GOOGLE = 'google';
}