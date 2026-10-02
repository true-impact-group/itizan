<?php

namespace App\Actions\Medication;

use App\Actions\Files\StoreAttachment;
use App\Actions\Support\RequestContact;
use App\Enums\ContactTarget;
use App\Enums\Severity;
use App\Enums\SideEffectStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Prescription;
use App\Models\SideEffectReport;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ReportSideEffect
{
    public function __construct(
        private readonly StoreAttachment $attachments,
        private readonly RequestContact $contacts,
    ) {}

    /**
     * @param  array{symptom: string, severity: Severity, occurred_at: mixed, prescription_id?: ?int}  $data
     * @param  list<UploadedFile>  $files
     */
    public function handle(User $client, array $data, array $files = []): SideEffectReport
    {
        if (! empty($data['prescription_id'])
            && ! Prescription::query()->whereKey($data['prescription_id'])->where('user_id', $client->id)->exists()) {
            throw new BusinessRuleViolation(__('الوصفة المختارة غير صحيحة.'));
        }

        $severe = $data['severity'] === Severity::Severe;

        $report = DB::transaction(function () use ($client, $data, $files, $severe) {
            $report = SideEffectReport::create([
                'user_id' => $client->id,
                'prescription_id' => $data['prescription_id'] ?? null,
                'symptom' => $data['symptom'],
                'severity' => $data['severity'],
                'occurred_at' => $data['occurred_at'],
                'status' => $severe ? SideEffectStatus::Escalated : SideEffectStatus::New,
            ]);

            foreach ($files as $file) {
                $this->attachments->handle($report, $client, $file);
            }

            return $report;
        });

        // Severe symptoms go straight to the doctor as an urgent request; the client is also told to seek emergency care.
        if ($severe) {
            $this->contacts->handle(
                $client,
                ContactTarget::Doctor,
                __('تم تسجيل عَرَض جانبي شديد ويحتاج إلى مراجعة عاجلة.'),
                urgent: true,
                source: 'side_effect',
            );
        }

        return $report;
    }
}
