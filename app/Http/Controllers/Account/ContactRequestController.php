<?php

namespace App\Http\Controllers\Account;

use App\Actions\Support\RequestContact;
use App\Enums\ContactTarget;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreContactRequestRequest;
use App\Models\ContactRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContactRequestController extends Controller
{
    public function index(Request $request): View
    {
        return view('account.contact-requests.index', [
            'requests' => ContactRequest::query()->where('user_id', $request->user()->id)->latest()->paginate(10),
        ]);
    }

    public function store(StoreContactRequestRequest $request, RequestContact $contact): RedirectResponse
    {
        $contact->handle(
            $request->user(),
            ContactTarget::from($request->string('target')->toString()),
            $request->string('reason')->toString(),
        );

        return redirect()->route('account.contact-requests.index')->with('status', __('تم إرسال طلبك. سيتواصل معك الفريق قريبًا.'));
    }
}
