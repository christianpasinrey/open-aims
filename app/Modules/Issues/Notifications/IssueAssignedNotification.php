<?php

declare(strict_types=1);

namespace App\Modules\Issues\Notifications;

use App\Modules\Issues\Models\Issue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class IssueAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Issue $issue,
        public ?string $actorName = null,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $identifier = $this->issue->identifier();
        $url = url('/issues/'.$identifier);
        $by = $this->actorName !== null ? " por {$this->actorName}" : '';

        return (new MailMessage)
            ->subject("Se te ha asignado {$identifier}: {$this->issue->title}")
            ->greeting('Hola')
            ->line("Se te ha asignado una incidencia{$by}: **{$identifier} — {$this->issue->title}**.")
            ->action('Ver incidencia', $url);
    }
}
