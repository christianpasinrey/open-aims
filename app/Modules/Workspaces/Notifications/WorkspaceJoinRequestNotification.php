<?php

declare(strict_types=1);

namespace App\Modules\Workspaces\Notifications;

use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class WorkspaceJoinRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Workspace $workspace, public string $requesterName) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Solicitud para unirse a {$this->workspace->name}")
            ->view('emails.notification', [
                'heading' => 'Nueva solicitud de acceso',
                'intro' => "{$this->requesterName} ha solicitado unirse al workspace «{$this->workspace->name}».",
                'badge' => null,
                'headline' => $this->workspace->name,
                'meta' => [['label' => 'Solicitante', 'value' => $this->requesterName]],
                'statusFrom' => null,
                'statusTo' => null,
                'actionUrl' => route('workspace.members'),
                'actionText' => 'Revisar solicitudes',
                'footnote' => 'Recibes este correo porque administras este workspace.',
            ]);
    }
}
