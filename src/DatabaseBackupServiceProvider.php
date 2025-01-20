<?php

namespace MarekMiklusek\LaravelDatabaseBackup;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use MarekMiklusek\LaravelDatabaseBackup\Events\BackupFailed;
use MarekMiklusek\LaravelDatabaseBackup\Events\BackupCreated;
use MarekMiklusek\LaravelDatabaseBackup\Commands\BackupRunCommand;
use MarekMiklusek\LaravelDatabaseBackup\Commands\BackupCleanupCommand;
use MarekMiklusek\LaravelDatabaseBackup\Listeners\SendBackupNotification;

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
