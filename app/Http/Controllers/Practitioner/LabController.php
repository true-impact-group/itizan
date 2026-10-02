<?php

namespace App\Http\Controllers\Practitioner;

use App\Actions\Labs\RequestLab;
use App\Actions\Labs\ReviewLabResult;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\LabRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LabController extends Controller
{
    public function index(): View
    {
        return view('practitioner.labs.index', [
            'labs' => LabRequest::query()->with('user')->where('status', 'uploaded')->oldest('result_uploaded_at')->paginate(15),
        ]);
    }

    public function store(Request $request, User $client, RequestLab $requestLab): RedirectResponse
    {
        $this->authorize('viewClinical', $client);
        abort_unless($request->user()->hasRole(Role::Doctor), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'instructions' => ['nullable', 'string', 'max:500'],
            'due_on' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $requestLab->handle($request->user()->practitioner, $client, $data['name'], $data['instructions'] ?? null, $data['due_on'] ?? null);

        return redirect()->route('practitioner.clients.show', $client)->with('status', __('تم طلب التحليل من العميل.'));
    }

    public function review(Request $request, LabRequest $lab, ReviewLabResult $review): RedirectResponse
    {
        $this->authorize('review', $lab);

        $data = $request->validate(['notes' => ['nullable', 'string', 'max:3000']]);

        $review->handle($lab, $data['notes'] ?? null);

        return redirect()->route('practitioner.labs.index')->with('status', __('تمت مراجعة النتيجة وإبلاغ العميل.'));
    }
}
