<?php

namespace MarekMiklusek\DatabaseBackup\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class BackupSuccessNotification extends Notification
{
    use Queueable;

    public function __construct(private string $fromAddress, private string $fromName) 
    {
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
            ->subject('Database Backup Successful')
            ->line('Great news! Your database backup has completed successfully.')
            ->line('Thank you for using our backup package!');
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
