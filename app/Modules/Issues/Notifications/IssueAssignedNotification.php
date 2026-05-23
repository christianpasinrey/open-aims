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

        $meta = [];
        if ($this->actorName !== null) {
            $meta[] = ['label' => 'Asignada por', 'value' => $this->actorName];
        }
        $stateName = $this->issue->workflowState?->name;
        if ($stateName !== null) {
            $meta[] = ['label' => 'Estado', 'value' => $stateName];
        }

        return (new MailMessage)
            ->subject("Se te ha asignado {$identifier}: {$this->issue->title}")
            ->view('emails.notification', [
                'heading' => 'Se te ha asignado una incidencia',
                'intro' => $this->actorName !== null
                    ? "{$this->actorName} te ha asignado esta incidencia."
                    : 'Se te ha asignado esta incidencia.',
                'badge' => $identifier,
                'headline' => $this->issue->title,
                'meta' => $meta,
                'statusFrom' => null,
                'statusTo' => null,
                'actionUrl' => $url,
                'actionText' => 'Ver incidencia',
                'footnote' => null,
            ]);
    }
}
