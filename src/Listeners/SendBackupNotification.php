<?php

declare(strict_types=1);

namespace MarekMiklusek\DatabaseBackup\Listeners;

use Illuminate\Support\Facades\Notification;
use MarekMiklusek\DatabaseBackup\Events\BackupFailed;
use MarekMiklusek\DatabaseBackup\Events\BackupCreated;
use MarekMiklusek\DatabaseBackup\Services\ConfigService;
use MarekMiklusek\DatabaseBackup\Notifications\BackupFailedNotification;
use MarekMiklusek\DatabaseBackup\Notifications\BackupSuccessNotification;

final class SendBackupNotification
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
        $shouldNotify = match (true) {
            $event instanceof BackupCreated => $this->service->notifications('events.backup_successful'),
            $event instanceof BackupFailed => $this->service->notifications('events.backup_failed'),
            default => false
        };

        if (! $shouldNotify) {
            return;
        }

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
