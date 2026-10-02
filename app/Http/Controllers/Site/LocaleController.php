<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        abort_unless(in_array($locale, config('app.supported_locales'), true), 404);

        $request->session()->put('locale', $locale);
        $request->user()?->update(['locale' => $locale]);

        return redirect()->back(fallback: route('home'));
    }
}
