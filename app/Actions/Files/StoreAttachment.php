<?php

namespace App\Actions\Files;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

/** Stores an upload on the private disk under a random name and records it against a model. */
class StoreAttachment
{
    public function handle(Model $attachable, User $owner, UploadedFile $file): Attachment
    {
        $disk = (string) config('itizan.uploads.disk');
        $path = $file->store("attachments/{$owner->id}", $disk);

        return Attachment::create([
            'attachable_type' => $attachable->getMorphClass(),
            'attachable_id' => $attachable->getKey(),
            'user_id' => $owner->id,
            'disk' => $disk,
            'path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 200),
            'mime_type' => (string) $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
    }
}
