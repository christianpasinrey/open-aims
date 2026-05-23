<?php

declare(strict_types=1);

namespace App\Modules\Projects\Notifications;

use App\Modules\Projects\Enums\ProjectState;
use App\Modules\Projects\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ProjectStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Project $project,
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
        $url = url('/projects/'.$this->project->slug);

        $meta = [];
        if ($this->actorName !== null) {
            $meta[] = ['label' => 'Cambiado por', 'value' => $this->actorName];
        }

        return (new MailMessage)
            ->subject('Cambio de estado: '.$this->project->name)
            ->view('emails.notification', [
                'heading' => 'Cambio de estado del proyecto',
                'intro' => 'El estado de un proyecto del que eres responsable ha cambiado.',
                'badge' => null,
                'headline' => $this->project->name,
                'meta' => $meta,
                'statusFrom' => ProjectState::labelFor($this->fromState),
                'statusTo' => ProjectState::labelFor($this->toState),
                'actionUrl' => $url,
                'actionText' => 'Ver proyecto',
                'footnote' => null,
            ]);
    }
}
