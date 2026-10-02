<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Support\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Editable policy and information pages (Markdown). Slugs are fixed because other pages link to them. */
class PageController extends Controller
{
    public function index(): View
    {
        return view('admin.pages.index', ['pages' => Page::query()->orderBy('group')->orderBy('sort_order')->get()]);
    }

    public function edit(Page $page): View
    {
        return view('admin.pages.edit', ['page' => $page]);
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $data = $request->validate([
            'title_ar' => ['required', 'string', 'max:200'],
            'title_en' => ['required', 'string', 'max:200'],
            'body_ar' => ['required', 'string', 'max:60000'],
            'body_en' => ['required', 'string', 'max:60000'],
        ]);

        $page->update([...$data, 'is_published' => $request->boolean('is_published'), 'updated_by' => $request->user()->id]);
        Audit::log('page.updated', $page);

        return redirect()->route('admin.pages.index')->with('status', __('تم حفظ الصفحة.'));
    }
}
