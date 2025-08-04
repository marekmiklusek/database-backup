<?php

declare(strict_types=1);

namespace MarekMiklusek\DatabaseBackup\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

final class BackupFailedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        private string $errorMessage,
        private string $fromAddress,
        private string $fromName,
    ) {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->from($this->fromAddress, $this->fromName)
            ->subject('Database Backup Failed')
            ->error()
            ->line('Unfortunately, your database backup has failed.')
            ->line("Error: {$this->errorMessage}")
            ->line('Please check your system or logs for details.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
