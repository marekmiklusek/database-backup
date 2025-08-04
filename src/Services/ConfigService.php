<?php

declare(strict_types=1);

namespace MarekMiklusek\DatabaseBackup\Services;

use Exception;
use MarekMiklusek\DatabaseBackup\Enums\Driver;

final class ConfigService
{
    private const MYSQL = 'mysql';

    public function mysqlDB(string $key): string|bool
    {
        return $this->getConfigValue($key, self::MYSQL);
    }

    public function googleDisk(string $key): string|bool
    {
        return $this->getConfigValue($key, Driver::GOOGLE->value);
    }

    public function storage(string $key): string|bool
    {
        return $this->getConfigValue($key, 'storage');
    }

    public function cleanup(string $key): string|int|bool
    {
        return $this->getConfigValue($key, 'cleanup');
    }

    public function notifications(string $key): string|bool
    {
        return $this->getConfigValue($key, 'notifications');
    }

    public function localDirectory(): string
    {
        $localRoot = config('filesystems.disks.local.root');
        $localDirectory = $this->storage('directory');

        return "{$localRoot}/{$localDirectory}";
    }

    public function getDriver(): string
    {
        $disk = $this->storage('disk');

        if (! array_key_exists($this->storage('disk'), config('filesystems.disks'))) {
            throw new Exception("Disk: [{$disk}] not found in configuration file: filesystems/disks");
        }

        $driver = config("filesystems.disks.{$disk}.driver");
        if (! in_array($driver, Driver::values())) {
            throw new Exception("Disk: [{$disk}] is not supported by this package");
        }

        return $driver;
    }

    /*
    |--------------------------------------------------------------------------
    | Private functions
    |--------------------------------------------------------------------------
    */

    private function getConfigValue(string $key, string $configKey): string|int|bool
    {
        $config = match ($configKey) {
            self::MYSQL => config('database.connections.mysql'),
            Driver::GOOGLE->value => config('filesystems.disks.'.$this->storage('disk')),
            default => config(app('configName').'.'.$configKey)
        };

        $configObject = json_decode(json_encode($config));
        if (is_null($configObject)) {
            $this->throwConfigKeyNotFoundException($configKey);
        }

        $value = data_get($configObject, $key);
        if (is_null($value)) {
            $this->throwConfigKeyNotFoundException($key);
        }

        return $value;
    }

    private function throwConfigKeyNotFoundException(string $configKey): void
    {
        throw new Exception("Config key [{$configKey}] not found in the configuration file");
    }
}
