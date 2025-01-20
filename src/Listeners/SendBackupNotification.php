<?php

namespace MarekMiklusek\LaravelDatabaseBackup\Listeners;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
use MarekMiklusek\LaravelDatabaseBackup\Events\BackupFailed;
use MarekMiklusek\LaravelDatabaseBackup\Events\BackupCreated;
use MarekMiklusek\LaravelDatabaseBackup\Services\ConfigService;
use MarekMiklusek\LaravelDatabaseBackup\Notifications\BackupFailedNotification;
use MarekMiklusek\LaravelDatabaseBackup\Notifications\BackupSuccessNotification;

class SendBackupNotification
{
    /**
     * Create the event listener.
     */
    public function __construct(private ConfigService $service)
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(BackupCreated|BackupFailed $event): void
    {          
        Notification::route('mail', $this->service->notifications('mail.to'))
            ->notify($this->createNotification($event));
    }

    /*
    |--------------------------------------------------------------------------
    | Private functions
    |--------------------------------------------------------------------------
    */

    private function createNotification(BackupCreated|BackupFailed $event): BackupSuccessNotification|BackupFailedNotification
    {
        $from = [
            'address' => $this->service->notifications('mail.from.address'),
            'name' => $this->service->notifications('mail.from.name'),
        ];

        return $event instanceof BackupCreated
            ? new BackupSuccessNotification($from['address'], $from['name'])
            : new BackupFailedNotification($event->errorMessage, $from['address'], $from['name']);
    }
}
