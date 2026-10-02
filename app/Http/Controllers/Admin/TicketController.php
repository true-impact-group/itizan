<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Support\ReplyToTicket;
use App\Actions\Support\UpdateTicketStatus;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    public function index(Request $request): View
    {
        $status = TicketStatus::tryFrom((string) $request->query('status'));
        $type = TicketType::tryFrom((string) $request->query('type'));

        return view('admin.tickets.index', [
            'status' => $status,
            'type' => $type,
            'tickets' => Ticket::query()->with(['user', 'assignee'])
                ->when($status, fn ($query) => $query->where('status', $status->value), fn ($query) => $query->whereIn('status', [TicketStatus::Open->value, TicketStatus::InProgress->value]))
                ->when($type, fn ($query) => $query->where('type', $type->value))
                ->oldest()->paginate(20)->withQueryString(),
        ]);
    }

    public function show(Ticket $ticket): View
    {
        $this->authorize('manage', $ticket);

        return view('admin.tickets.show', ['ticket' => $ticket->load(['user', 'replies.author', 'assignee'])]);
    }

    public function reply(Request $request, Ticket $ticket, ReplyToTicket $reply): RedirectResponse
    {
        $this->authorize('manage', $ticket);

        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:3000']]);

        $reply->handle($ticket, $request->user(), $data['body'], $request->boolean('internal'));

        return back()->with('status', __('تمت إضافة الرد.'));
    }

    public function update(Request $request, Ticket $ticket, UpdateTicketStatus $update): RedirectResponse
    {
        $this->authorize('manage', $ticket);

        $data = $request->validate(['status' => ['required', Rule::enum(TicketStatus::class)]]);

        $update->handle($ticket, TicketStatus::from($data['status']), $request->user()->id);

        return back()->with('status', __('تم تحديث حالة التذكرة.'));
    }
}
