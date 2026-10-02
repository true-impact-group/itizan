<?php

namespace App\Actions\Support;

use App\Actions\Notify\NotifyStaff;
use App\Enums\ContactRequestStatus;
use App\Enums\ContactTarget;
use App\Enums\Role;
use App\Models\ContactRequest;
use App\Models\User;

class RequestContact
{
    public function __construct(private readonly NotifyStaff $notifyStaff) {}

    public function handle(User $client, ContactTarget $target, string $reason, bool $urgent = false, string $source = 'client'): ContactRequest
    {
        $request = ContactRequest::create([
            'user_id' => $client->id,
            'target' => $target,
            'reason' => $reason,
            'priority' => $urgent ? 'urgent' : 'normal',
            'source' => $source,
            'status' => ContactRequestStatus::Open,
        ]);

        // The care team triages everything; urgent doctor requests also page the care team.
        if ($target === ContactTarget::CareTeam || $urgent) {
            $this->notifyStaff->handle(
                Role::CareTeam,
                $urgent ? 'طلب عاجل يحتاج متابعة' : 'طلب تواصل جديد',
                'يوجد طلب جديد من أحد العملاء بانتظار المتابعة.',
                route('care.contact-requests.index'),
            );
        }

        return $request;
    }
}
