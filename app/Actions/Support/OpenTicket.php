<?php

namespace App\Actions\Support;

use App\Actions\Notify\NotifyStaff;
use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Models\Ticket;
use App\Models\User;

class OpenTicket
{
    public function __construct(private readonly NotifyStaff $notifyStaff) {}

    public function handle(User $client, TicketType $type, string $subject, string $body): Ticket
    {
        $ticket = Ticket::create([
            'user_id' => $client->id,
            'type' => $type,
            'subject' => $subject,
            'body' => $body,
            'status' => TicketStatus::Open,
        ]);

        $this->notifyStaff->handle(
            Role::Support,
            $type === TicketType::Complaint ? 'شكوى جديدة' : 'طلب دعم جديد',
            'يوجد طلب جديد من أحد العملاء بانتظار المتابعة.',
            route('admin.tickets.show', $ticket),
        );

        return $ticket;
    }
}
