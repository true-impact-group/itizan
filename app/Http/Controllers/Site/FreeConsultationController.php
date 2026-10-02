<?php

namespace App\Http\Controllers\Site;

use App\Enums\AppointmentType;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Entry point of the "try Itizan with a free consultation" call to action. */
class FreeConsultationController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $booking = route('account.appointments.create', ['type' => AppointmentType::FreeIntro->value]);

        if ($request->user() === null) {
            // New visitors register first (phone + a few details), then land on the booking step.
            $request->session()->put('url.intended', $booking);

            return redirect()->route('register');
        }

        return $request->user()->isClient()
            ? redirect($booking)
            : redirect()->route($request->user()->role->homeRoute());
    }
}
