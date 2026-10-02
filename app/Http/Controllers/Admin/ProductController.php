<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(): View
    {
        return view('admin.products.index', ['products' => Product::query()->orderBy('type')->orderBy('name_ar')->paginate(25)]);
    }

    public function create(): View
    {
        return view('admin.products.form', ['product' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        Product::create($this->validated($request, null));

        return redirect()->route('admin.products.index')->with('status', __('تمت إضافة المنتج.'));
    }

    public function edit(Product $product): View
    {
        return view('admin.products.form', ['product' => $product]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $product->update($this->validated($request, $product));

        return redirect()->route('admin.products.index')->with('status', __('تم تحديث المنتج.'));
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return redirect()->route('admin.products.index')->with('status', __('تم حذف المنتج.'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Product $product): array
    {
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:40', Rule::unique('products', 'sku')->ignore($product?->id)],
            'name_ar' => ['required', 'string', 'max:150'],
            'name_en' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::enum(ProductType::class)],
            'price' => ['required', 'numeric', 'min:0', 'max:100000'],
            'description_ar' => ['nullable', 'string', 'max:1000'],
            'description_en' => ['nullable', 'string', 'max:1000'],
        ]);

        return [
            'sku' => $data['sku'],
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'],
            'type' => $data['type'],
            'description_ar' => $data['description_ar'] ?? null,
            'description_en' => $data['description_en'] ?? null,
            'price_halalas' => (int) round($data['price'] * 100),
            // Medication is always dispensed against a prescription.
            'requires_prescription' => $data['type'] === ProductType::Medication->value,
            'in_stock' => $request->boolean('in_stock'),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
