<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __invoke(Request $request, ReportService $reports): View
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $from = isset($data['from']) ? Carbon::parse($data['from']) : now()->subDays(29);
        $to = isset($data['to']) ? Carbon::parse($data['to']) : now();

        return view('admin.reports', [
            'from' => $from,
            'to' => $to,
            'report' => $reports->period($from, $to),
        ]);
    }
}
