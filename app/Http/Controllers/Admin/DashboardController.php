<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(ReportService $reports): View
    {
        return view('admin.dashboard', [
            'snapshot' => $reports->snapshot(),
            'month' => $reports->period(now()->subDays(29), now()),
        ]);
    }
}
