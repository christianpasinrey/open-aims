<?php

declare(strict_types=1);

namespace App\Modules\Workspaces\Notifications;

use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class WorkspaceJoinDecisionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Workspace $workspace, public bool $approved) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if ($this->approved) {
            return (new MailMessage)
                ->subject("Aceptado en {$this->workspace->name}")
                ->view('emails.notification', [
                    'heading' => 'Solicitud aprobada',
                    'intro' => "Ya formas parte del workspace «{$this->workspace->name}».",
                    'badge' => null,
                    'headline' => $this->workspace->name,
                    'meta' => [],
                    'statusFrom' => null,
                    'statusTo' => null,
                    'actionUrl' => url('/workspace/switch?workspace='.$this->workspace->slug),
                    'actionText' => 'Ir al workspace',
                    'footnote' => null,
                ]);
        }

        return (new MailMessage)
            ->subject("Solicitud no aceptada — {$this->workspace->name}")
            ->view('emails.notification', [
                'heading' => 'Solicitud no aceptada',
                'intro' => "Tu solicitud para unirte a «{$this->workspace->name}» no ha sido aceptada.",
                'badge' => null,
                'headline' => $this->workspace->name,
                'meta' => [],
                'statusFrom' => null,
                'statusTo' => null,
                'actionUrl' => url('/onboarding'),
                'actionText' => 'Explorar workspaces',
                'footnote' => null,
            ]);
    }
}
