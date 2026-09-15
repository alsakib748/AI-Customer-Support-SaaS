<?php

namespace App\Notifications;

use App\Models\Tenant\AnalyticsExport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AnalyticsExportCompleted extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public AnalyticsExport $export)
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
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'analytics_export',
            'export_id' => $this->export->id,
            'export_type' => $this->export->type,
            'status' => $this->export->status,
            'row_count' => $this->export->row_count,
            'download_url' => $this->export->download_url,
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject("Your {$this->export->type} export is ready")
            ->line("Your requested {$this->export->type} export has completed.")
            ->line("Rows exported: {$this->export->row_count}")
            ->action('Download', $this->export->download_url ?? '#');
    }

}