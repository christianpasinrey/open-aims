<?php

declare(strict_types=1);

namespace App\Modules\Projects\Notifications;

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
        $by = $this->actorName !== null ? " ({$this->actorName})" : '';

        return (new MailMessage)
            ->subject('Cambio de estado: '.$this->project->name)
            ->greeting('Hola')
            ->line("El estado de tu proyecto **{$this->project->name}** ha cambiado{$by}.")
            ->line('De: '.($this->fromState ?? '—').' → A: '.($this->toState ?? '—'))
            ->action('Ver proyecto', $url);
    }
}
