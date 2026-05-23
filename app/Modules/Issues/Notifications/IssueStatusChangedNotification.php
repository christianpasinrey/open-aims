<?php

declare(strict_types=1);

namespace App\Modules\Issues\Notifications;

use App\Modules\Issues\Models\Issue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class IssueStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Issue $issue,
        public ?string $fromState,
        public ?string $toState,
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

        $meta = [];
        if ($this->actorName !== null) {
            $meta[] = ['label' => 'Cambiado por', 'value' => $this->actorName];
        }

        return (new MailMessage)
            ->subject("Cambio de estado en {$identifier}: {$this->issue->title}")
            ->view('emails.notification', [
                'heading' => 'Cambio de estado',
                'intro' => 'El estado de una incidencia que creaste ha cambiado.',
                'badge' => $identifier,
                'headline' => $this->issue->title,
                'meta' => $meta,
                'statusFrom' => $this->fromState,
                'statusTo' => $this->toState,
                'actionUrl' => $url,
                'actionText' => 'Ver incidencia',
                'footnote' => null,
            ]);
    }
}
