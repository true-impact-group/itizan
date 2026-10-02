<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PractitionerType;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Practitioner;
use App\Models\Subscription;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Account administration for clients. Deliberately shows no clinical data:
 * admin and support staff manage accounts, subscriptions and support, not medical records.
 */
class ClientController extends Controller
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q'));
        $digits = preg_replace('/\D+/', '', $term) ?? '';

        $clients = User::query()
            ->where('role', Role::Client->value)
            ->when($term !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'ilike', '%'.addcslashes($term, '%_\\').'%')
                ->orWhere('email', 'ilike', '%'.addcslashes($term, '%_\\').'%')
                ->when(strlen($digits) >= 3, fn ($phone) => $phone->orWhere('phone', 'like', "%{$digits}%"))))
            ->withCount(['subscriptions as active_subscriptions_count' => fn ($query) => $query->current()])
            ->latest()
            ->paginate(20)->withQueryString();

        return view('admin.clients.index', ['clients' => $clients, 'term' => $term]);
    }

    public function show(User $client): View
    {
        abort_unless($client->isClient(), 404);
        $this->authorize('view', $client);

        return view('admin.clients.show', [
            'client' => $client->load('primaryPractitioner.user'),
            'doctors' => Practitioner::query()->verified()->ofType(PractitionerType::Doctor)->with('user')->get(),
            'subscriptions' => Subscription::query()->with('package')->where('user_id', $client->id)->latest('starts_at')->get(),
            'payments' => Payment::query()->where('user_id', $client->id)->latest()->limit(10)->get(),
            'appointments' => Appointment::query()->with('practitioner.user')->where('user_id', $client->id)->latest('scheduled_at')->limit(10)->get(),
            'tickets' => Ticket::query()->where('user_id', $client->id)->latest()->limit(10)->get(),
        ]);
    }

    public function assignDoctor(Request $request, User $client): RedirectResponse
    {
        abort_unless($client->isClient(), 404);
        $this->authorize('manage', $client);

        $data = $request->validate([
            'practitioner_id' => ['nullable', Rule::exists(Practitioner::class, 'id')->where('type', PractitionerType::Doctor->value)],
        ]);

        $doctor = isset($data['practitioner_id']) ? Practitioner::query()->verified()->find($data['practitioner_id']) : null;
        abort_if(isset($data['practitioner_id']) && $doctor === null, 422);

        $client->forceFill(['primary_practitioner_id' => $doctor?->id])->save();
        Audit::log('client.primary_doctor_changed', $client, ['practitioner_id' => $doctor?->id]);

        return back()->with('status', __('تم تحديث الطبيب المتابع.'));
    }

    public function toggleActive(User $client): RedirectResponse
    {
        abort_unless($client->isClient(), 404);
        $this->authorize('manage', $client);

        $client->forceFill(['is_active' => ! $client->is_active])->save();
        Audit::log($client->is_active ? 'client.activated' : 'client.deactivated', $client);

        return back()->with('status', $client->is_active ? __('تم تفعيل الحساب.') : __('تم إيقاف الحساب.'));
    }
}
