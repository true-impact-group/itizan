<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Enums\AssessmentStatus;
use App\Enums\ContactRequestStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Models\Appointment;
use App\Models\ContactRequest;
use App\Models\MedicalAssessment;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Ticket;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Operational numbers for the admin dashboard and reports. Counts and money only: no clinical content.
 */
class ReportService
{
    /** Live snapshot shown on the dashboard. */
    public function snapshot(): array
    {
        return [
            'clients' => User::query()->where('role', Role::Client->value)->count(),
            'activeSubscriptions' => Subscription::query()->current()->count(),
            'autoRenewing' => Subscription::query()->current()->where('auto_renew', true)->count(),
            'assessmentsWaiting' => MedicalAssessment::query()->where('status', AssessmentStatus::Submitted->value)->count(),
            'appointmentsToday' => Appointment::query()->whereIn('status', AppointmentStatus::active())->whereBetween('scheduled_at', [now()->startOfDay(), now()->endOfDay()])->count(),
            'instantWaiting' => Appointment::query()->where('is_instant', true)->whereNull('practitioner_id')->where('status', AppointmentStatus::Requested->value)->count(),
            'openContactRequests' => ContactRequest::query()->where('status', '!=', ContactRequestStatus::Resolved->value)->count(),
            'openTickets' => Ticket::query()->whereIn('status', [TicketStatus::Open->value, TicketStatus::InProgress->value])->count(),
            'ordersInProgress' => Order::query()->whereNotIn('status', [OrderStatus::Delivered->value, OrderStatus::Cancelled->value, OrderStatus::Rejected->value])->count(),
        ];
    }

    /** Period report: subscriptions, renewal, cancellation, payments, consultations, delivery and complaints. */
    public function period(CarbonInterface $from, CarbonInterface $to): array
    {
        $range = [$from->copy()->startOfDay(), $to->copy()->endOfDay()];

        $paid = Payment::query()->where('status', PaymentStatus::Paid->value)->whereBetween('paid_at', $range);
        $refunded = Payment::query()->where('status', PaymentStatus::Refunded->value)->whereBetween('refunded_at', $range);

        // Columns are table-qualified because the per-package breakdown joins the packages table.
        $newSubscriptions = Subscription::query()
            ->whereIn('subscriptions.status', [SubscriptionStatus::Active->value, SubscriptionStatus::Expired->value])
            ->whereBetween('subscriptions.created_at', $range);

        return [
            'range' => ['from' => $range[0], 'to' => $range[1]],
            'newClients' => User::query()->where('role', Role::Client->value)->whereBetween('created_at', $range)->count(),
            'subscriptions' => [
                'new' => (clone $newSubscriptions)->count(),
                // A renewal is a subscription bought by a client who already had an earlier paid one.
                'renewals' => (clone $newSubscriptions)->whereExists(fn ($query) => $query->select(DB::raw(1))
                    ->from('subscriptions as earlier')
                    ->whereColumn('earlier.user_id', 'subscriptions.user_id')
                    ->whereColumn('earlier.created_at', '<', 'subscriptions.created_at')
                    ->whereIn('earlier.status', [SubscriptionStatus::Active->value, SubscriptionStatus::Expired->value]))->count(),
                'cancelled' => Subscription::query()->where('status', SubscriptionStatus::Cancelled->value)->whereNotNull('cancelled_at')
                    ->where('cancel_reason', '!=', 'abandoned')->whereBetween('cancelled_at', $range)->count(),
                'byPackage' => (clone $newSubscriptions)->join('packages', 'packages.id', '=', 'subscriptions.package_id')
                    ->select('packages.name_ar', DB::raw('count(*) as total'))->groupBy('packages.name_ar')->pluck('total', 'name_ar')->all(),
            ],
            'payments' => [
                'count' => (clone $paid)->count(),
                'revenue_halalas' => (int) (clone $paid)->sum('amount_halalas'),
                'vat_halalas' => (int) (clone $paid)->sum('vat_halalas'),
                'refund_count' => (clone $refunded)->count(),
                'refund_halalas' => (int) (clone $refunded)->sum('refunded_halalas'),
                'byMethod' => (clone $paid)->select('method', DB::raw('count(*) as total'))->groupBy('method')->pluck('total', 'method')->all(),
            ],
            'consultations' => [
                'free' => Appointment::query()->where('type', AppointmentType::FreeIntro->value)->whereBetween('scheduled_at', $range)->where('status', '!=', AppointmentStatus::Cancelled->value)->count(),
                'followUp' => Appointment::query()->where('type', AppointmentType::FollowUp->value)->whereBetween('scheduled_at', $range)->where('status', '!=', AppointmentStatus::Cancelled->value)->count(),
                'completed' => Appointment::query()->where('status', AppointmentStatus::Completed->value)->whereBetween('scheduled_at', $range)->count(),
                'noShow' => Appointment::query()->where('status', AppointmentStatus::NoShow->value)->whereBetween('scheduled_at', $range)->count(),
            ],
            'delivery' => [
                'delivered' => Order::query()->where('status', OrderStatus::Delivered->value)->whereBetween('delivered_at', $range)->count(),
                'rejected' => Order::query()->where('status', OrderStatus::Rejected->value)->whereBetween('updated_at', $range)->count(),
                'avgHours' => $this->averageDeliveryHours($range),
            ],
            'complaints' => [
                'opened' => Ticket::query()->where('type', TicketType::Complaint->value)->whereBetween('created_at', $range)->count(),
                'resolved' => Ticket::query()->where('type', TicketType::Complaint->value)->whereBetween('resolved_at', $range)->count(),
                'support' => Ticket::query()->where('type', TicketType::Support->value)->whereBetween('created_at', $range)->count(),
            ],
        ];
    }

    /** @param  array{0: CarbonInterface, 1: CarbonInterface}  $range */
    private function averageDeliveryHours(array $range): ?float
    {
        $seconds = Order::query()->where('status', OrderStatus::Delivered->value)
            ->whereBetween('delivered_at', $range)->whereNotNull('approved_at')
            ->avg(DB::raw('extract(epoch from (delivered_at - approved_at))'));

        return $seconds === null ? null : round($seconds / 3600, 1);
    }
}
