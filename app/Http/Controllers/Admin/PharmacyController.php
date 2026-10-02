<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pharmacy;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PharmacyController extends Controller
{
    public function index(): View
    {
        return view('admin.pharmacies.index', ['pharmacies' => Pharmacy::query()->withCount('staff')->orderBy('name')->paginate(25)]);
    }

    public function create(): View
    {
        return view('admin.pharmacies.form', ['pharmacy' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        Pharmacy::create($this->validated($request, null));

        return redirect()->route('admin.pharmacies.index')->with('status', __('تمت إضافة الصيدلية.'));
    }

    public function edit(Pharmacy $pharmacy): View
    {
        return view('admin.pharmacies.form', ['pharmacy' => $pharmacy]);
    }

    public function update(Request $request, Pharmacy $pharmacy): RedirectResponse
    {
        $pharmacy->update($this->validated($request, $pharmacy));

        return redirect()->route('admin.pharmacies.index')->with('status', __('تم تحديث الصيدلية.'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Pharmacy $pharmacy): array
    {
        return [
            ...$request->validate([
                'name' => ['required', 'string', 'max:150'],
                'license_number' => ['required', 'string', 'max:60', Rule::unique('pharmacies', 'license_number')->ignore($pharmacy?->id)],
                'city' => ['required', 'string', Rule::in(config('itizan.cities'))],
                'phone' => ['nullable', 'string', 'max:20'],
            ]),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
