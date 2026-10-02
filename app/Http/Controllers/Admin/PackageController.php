<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Support\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Only two paid packages exist (six-month and annual), so packages are edited, not created or deleted.
 * Changing a price affects new purchases only: each subscription keeps the price it was bought at.
 */
class PackageController extends Controller
{
    public function index(): View
    {
        return view('admin.packages.index', ['packages' => Package::query()->orderBy('sort_order')->get()]);
    }

    public function edit(Package $package): View
    {
        return view('admin.packages.edit', ['package' => $package]);
    }

    public function update(Request $request, Package $package): RedirectResponse
    {
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:150'],
            'name_en' => ['required', 'string', 'max:150'],
            'description_ar' => ['nullable', 'string', 'max:1000'],
            'description_en' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'min:1', 'max:1000000'],
            'vat_inclusive' => ['nullable', 'boolean'],
            'included_followups' => ['required', 'integer', 'between:0,60'],
            'features_ar' => ['nullable', 'string', 'max:2000'],
            'features_en' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'between:0,100'],
        ]);

        $package->update([
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'],
            'description_ar' => $data['description_ar'] ?? null,
            'description_en' => $data['description_en'] ?? null,
            'price_halalas' => (int) round($data['price'] * 100),
            'vat_inclusive' => $request->boolean('vat_inclusive'),
            'included_followups' => $data['included_followups'],
            'features_ar' => $this->lines($data['features_ar'] ?? ''),
            'features_en' => $this->lines($data['features_en'] ?? ''),
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $data['sort_order'] ?? $package->sort_order,
        ]);

        Audit::log('package.updated', $package, ['price_halalas' => $package->price_halalas]);

        return redirect()->route('admin.packages.index')->with('status', __('تم تحديث الباقة.'));
    }

    /** @return list<string> */
    private function lines(string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/u', $text) ?: [])));
    }
}
