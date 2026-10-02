<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\SaveStaffUser;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Pharmacy;
use App\Models\User;
use App\Rules\PhoneNumber;
use App\Support\Phone;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Staff accounts and roles. Only an administrator can grant access. */
class StaffController extends Controller
{
    public function index(Request $request): View
    {
        $role = Role::tryFrom((string) $request->query('role'));

        return view('admin.staff.index', [
            'role' => $role,
            'staff' => User::query()->where('role', '!=', Role::Client->value)
                ->when($role, fn ($query) => $query->where('role', $role->value))
                ->with('pharmacy')->orderBy('role')->orderBy('name')->paginate(20)->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('admin.staff.form', ['member' => null, 'pharmacies' => Pharmacy::query()->where('is_active', true)->orderBy('name')->get()]);
    }

    public function store(Request $request, SaveStaffUser $save): RedirectResponse
    {
        $save->handle(null, $this->validated($request, null));

        return redirect()->route('admin.staff.index')->with('status', __('تم إنشاء حساب الموظف.'));
    }

    public function edit(User $member): View
    {
        abort_if($member->isClient(), 404);

        return view('admin.staff.form', [
            'member' => $member->load('practitioner'),
            'pharmacies' => Pharmacy::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $member, SaveStaffUser $save): RedirectResponse
    {
        abort_if($member->isClient(), 404);
        $this->authorize('manage', $member);

        $save->handle($member, $this->validated($request, $member));

        return redirect()->route('admin.staff.index')->with('status', __('تم تحديث حساب الموظف.'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?User $member): array
    {
        $request->merge(['phone' => Phone::normalize($request->input('phone')) ?? $request->input('phone')]);

        $roles = collect(Role::cases())->reject(fn (Role $role) => $role === Role::Client)->map->value->all();

        return [
            ...$request->validate([
                'name' => ['required', 'string', 'min:3', 'max:100'],
                'phone' => ['required', new PhoneNumber, Rule::unique('users', 'phone')->ignore($member?->id)],
                'email' => ['nullable', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($member?->id)],
                'password' => [$member ? 'nullable' : 'required', Password::defaults()],
                'role' => ['required', Rule::in($roles)],
                'pharmacy_id' => ['nullable', 'required_if:role,pharmacy', 'exists:pharmacies,id'],
                'name_en' => ['nullable', 'string', 'max:150'],
                'specialty_ar' => ['nullable', 'string', 'max:150'],
                'specialty_en' => ['nullable', 'string', 'max:150'],
                'license_number' => ['nullable', 'string', 'max:60', Rule::unique('practitioners', 'license_number')->ignore($member?->practitioner?->id)],
                'bio_ar' => ['nullable', 'string', 'max:2000'],
                'bio_en' => ['nullable', 'string', 'max:2000'],
            ]),
            'is_active' => $request->boolean('is_active'),
            'accepts_instant' => $request->boolean('accepts_instant'),
        ];
    }
}
