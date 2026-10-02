<?php

namespace App\Http\Controllers\Account;

use App\Actions\Billing\ManageSubscription;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\PackageComparison;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubscriptionController extends Controller
{
    public function show(Request $request, PackageComparison $comparison): View
    {
        $user = $request->user();
        $packages = Package::query()->where('is_active', true)->orderBy('sort_order')->get();

        return view('account.subscription.show', [
            'subscription' => Subscription::query()->with('package')->where('user_id', $user->id)->current()->latest('ends_at')->first(),
            'upcomingSubscription' => Subscription::query()->with('package')->where('user_id', $user->id)
                ->where('status', 'active')->where('starts_at', '>', now())->first(),
            'payments' => Payment::query()->with('subscription.package')->where('user_id', $user->id)
                ->whereIn('status', [PaymentStatus::Paid->value, PaymentStatus::Refunded->value])->latest('paid_at')->paginate(10),
            'packages' => $packages,
            'savings' => $comparison->longestPackageSavings($packages),
            'methods' => PaymentMethod::enabled(),
        ]);
    }

    public function enableAutoRenew(Request $request, ManageSubscription $manage): RedirectResponse
    {
        $request->validate(['auto_renew_consent' => ['accepted']]);

        $manage->enableAutoRenew($this->currentSubscription($request), $request->user());

        return back()->with('status', __('تم تفعيل التجديد التلقائي.'));
    }

    public function disableAutoRenew(Request $request, ManageSubscription $manage): RedirectResponse
    {
        $manage->disableAutoRenew($this->currentSubscription($request), $request->user());

        return back()->with('status', __('تم إلغاء التجديد التلقائي. يستمر اشتراكك حتى نهاية مدته.'));
    }

    public function updatePaymentMethod(Request $request, ManageSubscription $manage): RedirectResponse
    {
        $data = $request->validate(['payment_method' => ['required', Rule::enum(PaymentMethod::class)]]);

        $manage->changePaymentMethod($this->currentSubscription($request), PaymentMethod::from($data['payment_method']));

        return back()->with('status', __('تم تحديث وسيلة الدفع.'));
    }

    private function currentSubscription(Request $request): Subscription
    {
        $subscription = Subscription::query()->where('user_id', $request->user()->id)->current()->latest('ends_at')->firstOrFail();

        $this->authorize('manage', $subscription);

        return $subscription;
    }
}
