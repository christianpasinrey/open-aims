<?php

declare(strict_types=1);

namespace App\Modules\Workspaces\Notifications;

use App\Modules\Workspaces\Models\WorkspaceInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class WorkspaceInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public WorkspaceInvitation $invitation,
        public string $workspaceName,
        public ?string $inviterName = null,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url('/invite/'.$this->invitation->token);
        $by = $this->inviterName !== null ? "{$this->inviterName} te ha invitado" : 'Te han invitado';

        return (new MailMessage)
            ->subject("Invitación a {$this->workspaceName} en ".config('app.name'))
            ->view('emails.notification', [
                'heading' => 'Te han invitado a un workspace',
                'intro' => "{$by} a unirte al workspace «{$this->workspaceName}» en ".config('app.name').'.',
                'badge' => null,
                'headline' => $this->workspaceName,
                'meta' => [
                    ['label' => 'Para', 'value' => $this->invitation->email],
                    ['label' => 'Caduca', 'value' => $this->invitation->expires_at?->format('d/m/Y H:i') ?? '—'],
                ],
                'statusFrom' => null,
                'statusTo' => null,
                'actionUrl' => $url,
                'actionText' => 'Aceptar invitación',
                'footnote' => 'Si no esperabas esta invitación, puedes ignorar este correo.',
            ]);
    }
}
