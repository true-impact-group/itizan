<?php

namespace App\Http\Controllers\Account;

use App\Actions\Labs\UploadLabResult;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UploadLabResultRequest;
use App\Models\LabRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LabController extends Controller
{
    public function index(Request $request): View
    {
        return view('account.labs.index', [
            'labs' => LabRequest::query()->with(['practitioner.user', 'attachments'])
                ->where('user_id', $request->user()->id)->latest()->paginate(10),
        ]);
    }

    public function upload(UploadLabResultRequest $request, LabRequest $lab, UploadLabResult $upload): RedirectResponse
    {
        $upload->handle($request->user(), $lab, $request->file('files', []));

        return redirect()->route('account.labs.index')->with('status', __('تم رفع النتيجة. سيراجعها الطبيب.'));
    }
}
