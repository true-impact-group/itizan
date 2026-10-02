<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    public function index(): View
    {
        return view('admin.faqs.index', ['faqs' => Faq::query()->orderBy('sort_order')->get()]);
    }

    public function create(): View
    {
        return view('admin.faqs.form', ['faq' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        Faq::create($this->validated($request));

        return redirect()->route('admin.faqs.index')->with('status', __('تمت إضافة السؤال.'));
    }

    public function edit(Faq $faq): View
    {
        return view('admin.faqs.form', ['faq' => $faq]);
    }

    public function update(Request $request, Faq $faq): RedirectResponse
    {
        $faq->update($this->validated($request));

        return redirect()->route('admin.faqs.index')->with('status', __('تم تحديث السؤال.'));
    }

    public function destroy(Faq $faq): RedirectResponse
    {
        $faq->delete();

        return redirect()->route('admin.faqs.index')->with('status', __('تم حذف السؤال.'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return [
            ...$request->validate([
                'question_ar' => ['required', 'string', 'max:255'],
                'question_en' => ['required', 'string', 'max:255'],
                'answer_ar' => ['required', 'string', 'max:3000'],
                'answer_en' => ['required', 'string', 'max:3000'],
                'sort_order' => ['nullable', 'integer', 'between:0,1000'],
            ]),
            'sort_order' => $request->integer('sort_order'),
            'is_published' => $request->boolean('is_published'),
        ];
    }
}
