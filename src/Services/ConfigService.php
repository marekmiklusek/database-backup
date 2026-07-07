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

    /**
     * Build extra [client] lines for the mysqldump defaults-extra-file
     * based on the package's SSL config, falling back to the Laravel
     * `mysql` connection's own SSL settings.
     *
     * IMPORTANT: `ssl-mode` is a MySQL-only option. MariaDB's dump client
     * (mariadb-dump / mysqldump shim) does not understand it and fails with
     * "unknown variable 'ssl-mode=...'". For MariaDB use `ssl` +
     * `ssl-verify-server-cert`. The `database.dump_client` config selects
     * which dialect of options to emit ('mysql' | 'mariadb').
     *
     * Precedence for the CA path and enabling SSL:
     *  1. package config: database.ssl_ca / database.ssl_mode
     *  2. mysql connection: ssl_ca / options[PDO::MYSQL_ATTR_SSL_CA]
     *  3. mysql connection: MYSQL_ATTR_SSL_VERIFY_SERVER_CERT === false
     *
     * Returns '' when no SSL settings are present (backwards compatible).
     */
    public function mysqlSslOptions(): string
    {
        $configName = app('configName');
        $connection = config('database.connections.mysql', []);
        $options = $connection['options'] ?? [];

        $client = strtolower((string) (config("{$configName}.database.dump_client") ?: 'mysql'));

        $sslCa = config("{$configName}.database.ssl_ca")
            ?: ($connection['ssl_ca']
                ?? $connection['sslca']
                ?? ($options[\PDO::MYSQL_ATTR_SSL_CA] ?? null));

        $sslMode = strtoupper((string) (config("{$configName}.database.ssl_mode")
            ?: ($connection['ssl_mode'] ?? $connection['sslmode'] ?? '')));

        // Did the app explicitly opt out of certificate verification?
        $skipVerify = array_key_exists(\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT, $options)
            && $options[\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] === false;

        // Nothing configured -> emit nothing (backwards compatible).
        if (empty($sslCa) && $sslMode === '' && ! $skipVerify) {
            return '';
        }

        $verify = ! empty($sslCa) && in_array($sslMode, ['VERIFY_CA', 'VERIFY_IDENTITY'], true);

        $lines = [];

        if ($client === 'mariadb') {
            // MariaDB dialect
            $lines[] = 'ssl';
            if (! empty($sslCa)) {
                $lines[] = "ssl-ca={$sslCa}";
            }
            $lines[] = 'ssl-verify-server-cert='.($verify ? '1' : '0');
        } else {
            // MySQL dialect
            if (! empty($sslCa)) {
                $lines[] = "ssl-ca={$sslCa}";
            }
            $lines[] = 'ssl-mode='.($verify ? ($sslMode ?: 'VERIFY_CA') : 'REQUIRED');
        }

        return PHP_EOL.implode(PHP_EOL, $lines).PHP_EOL;
    }

    /**
     * The dump binary to invoke.
     *
     * Explicit config wins; otherwise derived from dump_client
     * ('mariadb' -> mariadb-dump, else mysqldump). Using mariadb-dump on
     * MariaDB avoids the "Deprecated program name" warning.
     */
    public function dumpBinary(): string
    {
        $configName = app('configName');

        $binary = config("{$configName}.database.dump_binary");
        if (! empty($binary)) {
            return (string) $binary;
        }

        $client = strtolower((string) (config("{$configName}.database.dump_client") ?: 'mysql'));

        return $client === 'mariadb' ? 'mariadb-dump' : 'mysqldump';
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
