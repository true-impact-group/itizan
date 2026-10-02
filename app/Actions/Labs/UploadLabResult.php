<?php

namespace App\Actions\Labs;

use App\Actions\Files\StoreAttachment;
use App\Enums\LabStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\LabRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class UploadLabResult
{
    public function __construct(private readonly StoreAttachment $attachments) {}

    /** @param  list<UploadedFile>  $files */
    public function handle(User $client, LabRequest $lab, array $files): LabRequest
    {
        if ($lab->status === LabStatus::Reviewed) {
            throw new BusinessRuleViolation(__('تمت مراجعة هذا التحليل بالفعل.'));
        }

        if ($files === []) {
            throw new BusinessRuleViolation(__('أرفق ملف النتيجة.'));
        }

        return DB::transaction(function () use ($client, $lab, $files) {
            foreach ($files as $file) {
                $this->attachments->handle($lab, $client, $file);
            }

            $lab->update(['status' => LabStatus::Uploaded, 'result_uploaded_at' => now()]);

            return $lab;
        });
    }
}
