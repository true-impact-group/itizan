<?php

namespace App\Actions\Support;

use App\Enums\ContactRequestStatus;
use App\Models\ContactRequest;
use App\Models\User;
use App\Notifications\GenericNotification;
use App\Support\Audit;

class ResolveContactRequest
{
    public function handle(ContactRequest $request, User $handler, ContactRequestStatus $status, ?string $note = null): ContactRequest
    {
        $request->update([
            'status' => $status,
            'assigned_to' => $request->assigned_to ?? $handler->id,
            'response_note' => $note ?? $request->response_note,
            'resolved_at' => $status === ContactRequestStatus::Resolved ? now() : null,
        ]);

        Audit::log('contact_request.'.$status->value, $request);

        if ($status === ContactRequestStatus::Resolved) {
            $request->user->notify(new GenericNotification(
                'تمت متابعة طلبك',
                'اطّلع على رد الفريق من حسابك.',
                route('account.contact-requests.index'),
            ));
        }

        return $request;
    }
}
