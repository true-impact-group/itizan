<?php

namespace App\Http\Controllers\Practitioner;

use App\Actions\Medication\PrescribeMedication;
use App\Actions\Medication\StopPrescription;
use App\Http\Controllers\Controller;
use App\Http\Requests\Practitioner\PrescribeRequest;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PrescriptionController extends Controller
{
    public function store(PrescribeRequest $request, User $client, PrescribeMedication $prescribe): RedirectResponse
    {
        $this->authorize('viewClinical', $client);
        $this->authorize('create', Prescription::class);

        $prescribe->handle($request->user()->practitioner, $client, $request->prescriptionData());

        return redirect()->route('practitioner.clients.show', $client)->with('status', __('تم حفظ الوصفة وإبلاغ العميل.'));
    }

    public function stop(Request $request, Prescription $prescription, StopPrescription $stop): RedirectResponse
    {
        $this->authorize('update', $prescription);

        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']]);

        $stop->handle($prescription, $data['reason']);

        return redirect()->route('practitioner.clients.show', $prescription->user_id)->with('status', __('تم إيقاف الوصفة.'));
    }
}
