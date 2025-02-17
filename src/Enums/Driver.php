<?php

namespace MarekMiklusek\DatabaseBackup\Enums;

use MarekMiklusek\DatabaseBackup\Traits\EnumHelper;

enum Driver: string 
{
    use EnumHelper;

    case LOCAL = 'local';
    case GOOGLE = 'google';
}