<?php

namespace App\Http\Controllers\Account;

use App\Actions\Support\OpenTicket;
use App\Actions\Support\ReplyToTicket;
use App\Enums\TicketType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreTicketRequest;
use App\Models\Ticket;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function index(Request $request): View
    {
        return view('account.tickets.index', [
            'tickets' => Ticket::query()->where('user_id', $request->user()->id)->latest()->paginate(10),
        ]);
    }

    public function create(Request $request): View
    {
        return view('account.tickets.create', ['type' => TicketType::tryFrom((string) $request->query('type')) ?? TicketType::Support]);
    }

    public function store(StoreTicketRequest $request, OpenTicket $open): RedirectResponse
    {
        $ticket = $open->handle(
            $request->user(),
            TicketType::from($request->string('type')->toString()),
            $request->string('subject')->toString(),
            $request->string('body')->toString(),
        );

        return redirect()->route('account.tickets.show', $ticket)->with('status', __('تم استلام طلبك وسنرد عليك في أقرب وقت.'));
    }

    public function show(Ticket $ticket): View
    {
        $this->authorize('view', $ticket);

        $ticket->load(['replies' => fn ($query) => $query->where('is_internal', false), 'replies.author']);

        return view('account.tickets.show', ['ticket' => $ticket]);
    }

    public function reply(Request $request, Ticket $ticket, ReplyToTicket $reply): RedirectResponse
    {
        $this->authorize('reply', $ticket);

        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:3000']]);

        $reply->handle($ticket, $request->user(), $data['body']);

        return redirect()->route('account.tickets.show', $ticket)->with('status', __('تمت إضافة ردك.'));
    }
}
