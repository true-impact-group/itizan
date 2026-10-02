<?php

namespace App\Http\Controllers\Practitioner;

use App\Actions\Appointments\SaveAvailability;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Practitioner\AvailabilityRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    public function edit(Request $request): View
    {
        abort_unless($request->user()->hasRole(Role::Doctor) && $request->user()->practitioner, 403);

        $windows = $request->user()->practitioner->availabilities()->orderBy('start_time')->get()->groupBy('weekday');

        return view('practitioner.availability', [
            'windows' => $windows,
            'slotMinutes' => $windows->flatten()->first()?->slot_minutes ?? config('itizan.booking.default_slot_minutes'),
        ]);
    }

    public function update(AvailabilityRequest $request, SaveAvailability $save): RedirectResponse
    {
        abort_unless($request->user()->practitioner, 403);

        $save->handle($request->user()->practitioner, $request->input('days', []), (int) $request->input('slot_minutes'));

        return redirect()->route('practitioner.availability.edit')->with('status', __('تم حفظ أوقات التوفر.'));
    }
}
