<?php

namespace App\Http\Requests\Account;

use App\Enums\AppointmentMode;
use App\Enums\AppointmentType;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isClient();
    }

    public function rules(): array
    {
        $instant = $this->boolean('instant');

        return [
            'type' => ['required', Rule::enum(AppointmentType::class)],
            'mode' => ['required', Rule::enum(AppointmentMode::class)],
            'instant' => ['nullable', 'boolean'],
            'starts_at' => [$instant ? 'nullable' : 'required', 'date_format:Y-m-d H:i'],
            'practitioner_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:500'],
            // The client confirms they understood the scope and how the session is held before booking.
            'acknowledge' => ['accepted'],
        ];
    }

    /** @return array{type: AppointmentType, mode: AppointmentMode, starts_at: ?Carbon, practitioner_id: ?int, instant: bool, notes: ?string} */
    public function bookingData(): array
    {
        $startsAt = $this->filled('starts_at') && ! $this->boolean('instant')
            ? Carbon::createFromFormat('Y-m-d H:i', $this->string('starts_at')->toString())
            : null;

        return [
            'type' => AppointmentType::from($this->string('type')->toString()),
            'mode' => AppointmentMode::from($this->string('mode')->toString()),
            'starts_at' => $startsAt,
            'practitioner_id' => $this->filled('practitioner_id') ? $this->integer('practitioner_id') : null,
            'instant' => $this->boolean('instant'),
            'notes' => $this->filled('notes') ? $this->string('notes')->toString() : null,
        ];
    }
}
