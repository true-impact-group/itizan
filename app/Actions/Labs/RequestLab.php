<?php

namespace App\Actions\Labs;

use App\Enums\LabStatus;
use App\Enums\PractitionerType;
use App\Exceptions\BusinessRuleViolation;
use App\Models\LabRequest;
use App\Models\Practitioner;
use App\Models\User;
use App\Notifications\GenericNotification;
use App\Support\Audit;

class RequestLab
{
    public function handle(Practitioner $doctor, User $client, string $name, ?string $instructions = null, mixed $dueOn = null): LabRequest
    {
        if ($doctor->type !== PractitionerType::Doctor || ! $doctor->isVerified()) {
            throw new BusinessRuleViolation(__('فقط الطبيب الموثّق يمكنه طلب التحاليل.'));
        }

        $lab = LabRequest::create([
            'user_id' => $client->id,
            'practitioner_id' => $doctor->id,
            'name' => $name,
            'instructions' => $instructions,
            'due_on' => $dueOn,
            'status' => LabStatus::Requested,
        ]);

        Audit::log('lab.requested', $lab);
        $client->notify(new GenericNotification(
            'طلب الطبيب تحليلًا جديدًا',
            'اطّلع على التحليل المطلوب وارفع النتيجة من حسابك.',
            route('account.labs.index'),
        ));

        return $lab;
    }
}
