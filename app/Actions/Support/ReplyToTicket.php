<?php

namespace App\Actions\Support;

use App\Enums\TicketStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use App\Notifications\GenericNotification;

class ReplyToTicket
{
    public function handle(Ticket $ticket, User $author, string $body, bool $internal = false): TicketReply
    {
        if ($ticket->status === TicketStatus::Closed) {
            throw new BusinessRuleViolation(__('هذه التذكرة مغلقة. افتح طلبًا جديدًا.'));
        }

        if ($internal && $author->isClient()) {
            $internal = false;
        }

        $reply = $ticket->replies()->create([
            'user_id' => $author->id,
            'body' => $body,
            'is_internal' => $internal,
        ]);

        if ($author->isClient()) {
            // A reply from the client reopens a resolved ticket.
            if ($ticket->status === TicketStatus::Resolved) {
                $ticket->update(['status' => TicketStatus::Open, 'resolved_at' => null]);
            }
        } else {
            if ($ticket->status === TicketStatus::Open) {
                $ticket->update(['status' => TicketStatus::InProgress, 'assigned_to' => $ticket->assigned_to ?? $author->id]);
            }

            if (! $internal) {
                $ticket->user->notify(new GenericNotification(
                    'رد جديد على طلبك',
                    'أضاف فريق الدعم ردًا على طلبك. اطّلع عليه من حسابك.',
                    route('account.tickets.show', $ticket),
                ));
            }
        }

        return $reply;
    }
}
