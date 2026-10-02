<?php

namespace App\Actions\Support;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Support\Audit;

class UpdateTicketStatus
{
    public function handle(Ticket $ticket, TicketStatus $status, ?int $assigneeId = null): Ticket
    {
        $isDone = in_array($status, [TicketStatus::Resolved, TicketStatus::Closed], true);

        $ticket->update([
            'status' => $status,
            'assigned_to' => $assigneeId ?? $ticket->assigned_to,
            'resolved_at' => $isDone ? ($ticket->resolved_at ?? now()) : null,
        ]);

        Audit::log('ticket.'.$status->value, $ticket);

        return $ticket;
    }
}
