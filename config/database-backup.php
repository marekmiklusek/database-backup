<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    |
    | Configure storage settings for database backup files:
    |
    | - disk: Choose between 'local' or 'google' storage driver. For Google Drive,
    |         you can use any custom disk name defined in config/filesystems.php
    | - use_both_disks: If true, saves backups to both local and Google Drive
    | - directory: Custom local directory name where backups will be stored
    | - filename: Customizable naming pattern for backup files
    |   - prefix: Usually database name (defaults to Laravel env value)
    |   - date_format: Timestamp format for unique filenames
    |
    | Example filename: laravel_2025-01-10_14-30-00.sql
    |
    */

    'storage' => [
        'disk' => 'local',

        'use_both_disks' => false,

        'directory' => 'database-backups',

        'filename' => [
            'prefix' => env('DB_DATABASE', 'laravel'),
            'date_format' => 'Y-m-d_H-i-s',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cleanup Configuration
    |--------------------------------------------------------------------------
    |
    | Configure cleanup of old backup files:
    |
    | - days_to_keep: Number of days to retain backup files
    |                 Set to 0 to keep backups indefinitely
    |                 Files older than this will be permanently deleted
    |
    | - automatic: Enable/disable automatic cleanup after backups
    |                 true: Run cleanup after each backup
    |                 false: Manual cleanup only via command
    |
    | Usage:
    | - Automatic: Runs with new backups if 'automatic' is true
    | - Manual: php artisan db-backup:cleanup
    |
    */

    'cleanup' => [
        'days_to_keep' => 14,
        'automatic' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications Configuration
    |--------------------------------------------------------------------------
    |
    | Configure email notifications for backup and cleanup operations:
    |
    | - mail: Email configuration settings
    |   - to: Recipient email address for notifications
    |   - from: Sender details pulled from environment variables
    |          Configure MAIL_FROM_ADDRESS and MAIL_FROM_NAME in .env
    |
    | - events: Toggle notifications for specific events
    |   - backup_successful: Notify when backup completes successfully
    |   - backup_failed: Notify when backup operation fails
    |   - cleanup_successful: Notify when cleanup completes successfully
    |   - cleanup_failed: Notify when cleanup operation fails
    |
    | Note: Ensure your mail configuration is properly set in .env file:
    |       MAIL_MAILER, MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD
    |
    */

    'notifications' => [
        'mail' => [
            'to' => 'your@email.com',
            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
                'name' => env('MAIL_FROM_NAME', 'Example'),
            ],
        ],

        'events' => [
            'backup_successful' => false,
            'backup_failed' => false,
            'cleanup_successful' => false,
            'cleanup_failed' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Events Information
    |--------------------------------------------------------------------------
    |
    | Available events that you can listen to in your application:
    |
    | 1. BackupCreated
    |    - Triggered after successful backup creation
    |
    | 2. BackupFailed
    |    - Triggered when backup operation encounters an error
    |
    */

    'events' => [
        MarekMiklusek\LaravelDatabaseBackup\Events\BackupCreated::class,
        MarekMiklusek\LaravelDatabaseBackup\Events\BackupFailed::class,
    ],
];
