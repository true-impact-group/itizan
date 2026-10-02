<?php

namespace App\Http\Controllers\Care;

use App\Actions\Support\ResolveContactRequest;
use App\Enums\ContactRequestStatus;
use App\Enums\ContactTarget;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\ContactRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Shared by the care team (all requests) and doctors (only the requests addressed to a doctor). */
class ContactRequestController extends Controller
{
    public function index(Request $request): View
    {
        $status = ContactRequestStatus::tryFrom((string) $request->query('status'));

        return view('care.contact-requests.index', [
            'status' => $status,
            'requests' => ContactRequest::query()->with(['user', 'assignee'])
                ->when($request->user()->hasRole(Role::Doctor), fn ($query) => $query->where('target', ContactTarget::Doctor->value))
                ->when($status, fn ($query) => $query->where('status', $status->value), fn ($query) => $query->where('status', '!=', ContactRequestStatus::Resolved->value))
                ->orderByRaw("case when priority = 'urgent' then 0 else 1 end")->oldest()
                ->paginate(15)->withQueryString(),
        ]);
    }

    public function update(Request $request, ContactRequest $contactRequest, ResolveContactRequest $resolve): RedirectResponse
    {
        $this->authorize('handle', $contactRequest);

        $data = $request->validate([
            'status' => ['required', Rule::enum(ContactRequestStatus::class)],
            'response_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $resolve->handle($contactRequest, $request->user(), ContactRequestStatus::from($data['status']), $data['response_note'] ?? null);

        return back()->with('status', __('تم تحديث الطلب.'));
    }
}
