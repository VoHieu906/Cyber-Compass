<?php

namespace App\Modules\Notifications\Notifications;

use App\Enums\NotificationType;
use App\Models\User;
use App\Modules\Notifications\Services\NotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Every learner notification (D34). Queued and sent after commit. The stored row holds the
 * type, parameters and a link, never text, so the app shows it in the learner's current
 * language; emails are written in the account's language (User::preferredLocale).
 *
 * Channels follow the learner's preferences at send time; a suspended account gets nothing.
 */
final class LearningNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, string|int|null>  $params  scalar values only; `kind` picks the milestone message
     * @param  string  $url  a path in the web app, such as /learn/osi-model
     */
    public function __construct(
        public readonly NotificationType $kind,
        public readonly array $params,
        public readonly string $url,
    ) {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return $notifiable->isSuspended() ? [] : app(NotificationPreferences::class)->channels($notifiable, $this->kind);
    }

    /** Stored in the `type` column instead of the class name. */
    public function databaseType(object $notifiable): string
    {
        return $this->kind->value;
    }

    /** @return array{params: array<string, string|int|null>, url: string} */
    public function toArray(object $notifiable): array
    {
        return ['params' => $this->params, 'url' => $this->url];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $key = 'notifications.'.$this->messageKey();
        $replace = array_map(fn ($v) => (string) $v, $this->params);

        return (new MailMessage)
            ->subject(__("{$key}.subject", $replace))
            ->greeting(__('notifications.greeting', ['name' => $notifiable->name]))
            ->line(__("{$key}.line", $replace))
            ->action(__("{$key}.action"), rtrim((string) config('app.frontend_url'), '/').$this->url)
            ->line(__('notifications.footer'));
    }

    /** `milestone.course`, `milestone.streak`, …, or the type itself. */
    public function messageKey(): string
    {
        return $this->kind === NotificationType::Milestone ? 'milestone.'.$this->params['kind'] : $this->kind->value;
    }
}
