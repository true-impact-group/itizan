<?php

namespace App\Http\Controllers\Account;

use App\Actions\Progress\RecordMeasurement;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreMeasurementRequest;
use App\Models\Measurement;
use App\Models\WeightPlan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProgressController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('account.progress.index', [
            'plan' => WeightPlan::query()->where('user_id', $user->id)->where('status', 'active')->first(),
            'measurements' => Measurement::query()->where('user_id', $user->id)->latest('measured_on')->latest('id')->paginate(15),
            // Chart data: oldest to newest, capped so the chart stays readable.
            'series' => Measurement::query()->where('user_id', $user->id)->latest('measured_on')->latest('id')->limit(60)->get()->reverse()->values(),
        ]);
    }

    public function store(StoreMeasurementRequest $request, RecordMeasurement $record): RedirectResponse
    {
        $record->handle($request->user(), $request->validated());

        return redirect()->route('account.progress.index')->with('status', __('تم تسجيل قياسك. كل خطوة في المتابعة تساعد فريقك على دعمك.'));
    }

    public function destroy(Measurement $measurement): RedirectResponse
    {
        $this->authorize('delete', $measurement);

        $measurement->delete();

        return redirect()->route('account.progress.index')->with('status', __('تم حذف القياس.'));
    }
}
