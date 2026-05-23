<?php

declare(strict_types=1);

namespace App\Modules\Projects\Notifications;

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
        $by = $this->actorName !== null ? " por {$this->actorName}" : '';

        return (new MailMessage)
            ->subject('Nuevo proyecto: '.$this->project->name)
            ->greeting('Hola')
            ->line("Se ha creado un nuevo proyecto{$by}: **{$this->project->name}**.")
            ->action('Ver proyecto', $url)
            ->line('Gracias por usar la plataforma.');
    }
}
