<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * Base for every notification. Content is intentionally generic: titles and bodies
 * never carry medication names, doses, symptoms or results (privacy requirement).
 * Sensitive details are only visible after signing in to the account.
 */
abstract class PlatformNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct()
    {
        $this->afterCommit();
    }

    abstract protected function title(): string;

    abstract protected function body(): string;

    protected function url(): ?string
    {
        return null;
    }

    /** In-app always; email only when the user provided one. */
    public function via(object $notifiable): array
    {
        return filled($notifiable->email ?? null) ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title())
            ->greeting(__('أهلًا :name', ['name' => $notifiable->name]))
            ->line($this->body());

        if ($this->url()) {
            $mail->action(__('فتح حسابي'), $this->url());
        }

        return $mail->line(__('هذه رسالة عامة ولا تتضمن معلومات صحية. التفاصيل متاحة داخل حسابك.'));
    }

    /** @return array{title: string, body: string, url: ?string} */
    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title(), 'body' => $this->body(), 'url' => $this->url()];
    }
}
