<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $action = trim((string) $request->query('action'));

        return view('admin.audit-logs', [
            'action' => $action,
            'logs' => AuditLog::query()->with('user')
                ->when($action !== '', fn ($query) => $query->where('action', 'like', addcslashes($action, '%_\\').'%'))
                ->latest('id')->paginate(30)->withQueryString(),
        ]);
    }
}
