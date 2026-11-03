<?php

namespace App\Notifications;

use App\Models\DocumentRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentReadyForPickup extends Notification implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public function __construct(
        public int $documentRequestId
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

    public function shouldSend(
        object $notifiable,
        string $channel
    ): bool {
        $user = User::query()->find($notifiable->id);

        $item = DocumentRequest::query()
            ->with('resident')
            ->find($this->documentRequestId);

        if (
            ! $user
            || $user->role !== 'resident'
            || ! $user->canAccessServices()
            || ! $item
            || $item->status !== 'ready_for_release'
            || ! $item->resident
            || $item->resident->trashed()
            || $item->resident->isDeceased()
            || (int) $user->resident_id !== (int) $item->resident_id
        ) {
            return false;
        }

        return $item->source === 'walk_in'
            || (
                $item->source === 'online'
                && (int) $item->requested_by === (int) $user->id
            );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $item = DocumentRequest::query()
            ->findOrFail($this->documentRequestId);

        $documentName = DocumentRequest::TYPES[$item->document_type]
            ?? 'Requested document';

        return (new MailMessage())
            ->subject('SMART MASIPIT — Document Ready for Pickup')
            ->greeting('Hello!')
            ->line(
                'Your requested document has been marked ready '
                . 'for pickup at the Barangay Masipit office.'
            )
            ->line('Request reference: ' . $item->reference_number)
            ->line('Document: ' . $documentName)
            ->line('Pickup instructions:')
            ->line(
                $item->pickup_instructions
                ?: 'Please contact the barangay office for instructions.'
            )
            ->line(
                'Please check your SMART MASIPIT account for '
                . 'the latest request status before visiting.'
            )
            ->line(
                'This email is a pickup notification. '
                . 'The printed document will be released at the barangay office.'
            )
            ->salutation('SMART MASIPIT | Barangay Masipit');
    }
}