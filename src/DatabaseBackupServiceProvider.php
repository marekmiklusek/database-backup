<?php

namespace MarekMiklusek\DatabaseBackup;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use MarekMiklusek\DatabaseBackup\Events\BackupFailed;
use MarekMiklusek\DatabaseBackup\Events\BackupCreated;
use MarekMiklusek\DatabaseBackup\Commands\BackupRunCommand;
use MarekMiklusek\DatabaseBackup\Commands\BackupCleanupCommand;
use MarekMiklusek\DatabaseBackup\Listeners\SendBackupNotification;

class DatabaseBackupServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $configName = 'database-backup';

        // Call the config name anywhere in the package
        $this->app->singleton('configName', fn() => $configName);

        if ($this->app->runningInConsole()) {
            // Publish config
            $this->publishes([
                __DIR__ . "/../config/{$configName}.php" => config_path("{$configName}.php"),
            ], "{$configName}-config");

            // Register commands
            $this->commands([
                BackupRunCommand::class,
                BackupCleanupCommand::class,
            ]);
        }

        // Register events and their corresponding listeners
        Event::listen(
            [
                BackupCreated::class,
                BackupFailed::class,
            ],
            SendBackupNotification::class,
        );
    }
}
