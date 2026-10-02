<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class UploadLabResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('upload', $this->route('lab'));
    }

    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1', 'max:5'],
            'files.*' => ['file', 'mimes:'.implode(',', config('itizan.uploads.mimes')), 'max:'.config('itizan.uploads.max_kb')],
        ];
    }
}
