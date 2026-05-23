<?php

declare(strict_types=1);

namespace App\Modules\Projects\Notifications;

use App\Modules\Projects\Enums\ProjectState;
use App\Modules\Projects\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class NewProjectNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Project $project,
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
            $meta[] = ['label' => 'Creado por', 'value' => $this->actorName];
        }
        $stateLabel = ProjectState::labelFor($this->project->state?->value);
        if ($stateLabel !== null) {
            $meta[] = ['label' => 'Estado', 'value' => $stateLabel];
        }

        return (new MailMessage)
            ->subject('Nuevo proyecto: '.$this->project->name)
            ->view('emails.notification', [
                'heading' => 'Nuevo proyecto',
                'intro' => $this->actorName !== null
                    ? "{$this->actorName} ha creado un proyecto nuevo."
                    : 'Se ha creado un proyecto nuevo.',
                'badge' => null,
                'headline' => $this->project->name,
                'meta' => $meta,
                'statusFrom' => null,
                'statusTo' => null,
                'actionUrl' => $url,
                'actionText' => 'Ver proyecto',
                'footnote' => null,
            ]);
    }
}
