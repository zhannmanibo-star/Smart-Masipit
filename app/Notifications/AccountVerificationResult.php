<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountVerificationResult extends Notification implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public function __construct(
        public string $decision,
        public string $reviewedAt
    ) {
        $this->onConnection('database');
        $this->onQueue('mail');
        $this->afterCommit();
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $approved = $this->decision === 'approved';

        $message = (new MailMessage())
            ->subject('SMART MASIPIT — Account Verification Result')
            ->greeting('Hello!')
            ->line(
                'Your resident account application was reviewed on '
                . $this->reviewedAt
                . ' (Philippine time).'
            );

        if ($approved) {
            $message
                ->line('Your resident account application was approved.')
                ->line(
                    'You may sign in to SMART MASIPIT to access '
                    . 'the resident services available to your account.'
                );
        } else {
            $message
                ->line('Your resident account application was rejected.')
                ->line(
                    'Sign in to SMART MASIPIT to view the recorded reason. '
                    . 'For clarification, contact the barangay office.'
                );
        }

        return $message
            ->line(
                'This email reports the decision recorded at that time. '
                . 'Your account page shows your current status.'
            )
            ->salutation('SMART MASIPIT | Barangay Masipit');
    }
}