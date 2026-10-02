<?php

namespace App\Actions\Orders;

use App\Actions\Notify\NotifyStaff;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Order;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RequestOrder
{
    public function __construct(private readonly NotifyStaff $notifyStaff) {}

    /**
     * @param  array{items: list<array{product_id: int, quantity: int}>, delivery_city: string, delivery_address: string, prescription_id?: ?int, notes?: ?string}  $data
     */
    public function handle(User $client, array $data): Order
    {
        $lines = collect($data['items'])->filter(fn ($line) => ! empty($line['product_id']) && (int) ($line['quantity'] ?? 0) > 0);

        $products = Product::query()->orderable()->whereIn('id', $lines->pluck('product_id'))->get()->keyBy('id');

        if ($lines->isEmpty() || $products->count() !== $lines->pluck('product_id')->unique()->count()) {
            throw new BusinessRuleViolation(__('اختر منتجات متاحة للطلب.'));
        }

        // Supplements are open to everyone; ordering medication is for subscribers only.
        $containsMedication = $products->where('requires_prescription', true)->isNotEmpty();
        if ($containsMedication && ! $client->hasActiveSubscription()) {
            throw new BusinessRuleViolation(__('طلب الأدوية متاح للمشتركين فقط. جدّد اشتراكك لمتابعة طلب دوائك.'));
        }

        $prescription = $this->validatedPrescription($client, $products->where('requires_prescription', true)->isNotEmpty(), $data['prescription_id'] ?? null);

        if ($prescription?->product_id) {
            $wrong = $products->where('requires_prescription', true)->contains(fn (Product $p) => $p->id !== $prescription->product_id);
            if ($wrong) {
                throw new BusinessRuleViolation(__('المنتج المختار لا يطابق الوصفة.'));
            }
        }

        $free = in_array($data['delivery_city'], config('itizan.delivery.free_cities'), true);

        $order = DB::transaction(function () use ($client, $data, $lines, $products, $prescription, $free) {
            $subtotal = $lines->sum(fn ($line) => $products[$line['product_id']]->price_halalas * $line['quantity']);
            $fee = $free ? 0 : (int) config('itizan.delivery.fee_halalas');

            $order = Order::create([
                'user_id' => $client->id,
                'prescription_id' => $prescription?->id,
                'status' => OrderStatus::Requested,
                'subtotal_halalas' => $subtotal,
                'delivery_fee_halalas' => $fee,
                'total_halalas' => $subtotal + $fee,
                'free_delivery' => $free,
                'delivery_city' => $data['delivery_city'],
                'delivery_address' => $data['delivery_address'],
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($lines as $line) {
                $product = $products[$line['product_id']];
                $order->items()->create([
                    'product_id' => $product->id,
                    'name' => $product->name_ar,
                    'quantity' => $line['quantity'],
                    'unit_price_halalas' => $product->price_halalas,
                    'is_available' => $product->in_stock,
                ]);
            }

            $order->recordEvent(OrderStatus::Requested, 'تم إرسال طلبك إلى الصيدلية', $client);

            return $order;
        });

        $this->notifyStaff->handle(Role::Pharmacy, 'طلب جديد بانتظار المراجعة', 'يوجد طلب جديد يحتاج إلى مراجعة التوفر والسعر.', route('pharmacy.orders.index'));

        return $order;
    }

    /** Prescription-only products need one of the client's own active prescriptions. */
    private function validatedPrescription(User $client, bool $required, ?int $prescriptionId): ?Prescription
    {
        if ($prescriptionId === null) {
            if ($required) {
                throw new BusinessRuleViolation(__('هذا المنتج يُصرف بوصفة طبية. اختر الوصفة المرتبطة بالطلب.'));
            }

            return null;
        }

        $prescription = Prescription::query()->active()->where('user_id', $client->id)->find($prescriptionId);

        if ($prescription === null) {
            throw new BusinessRuleViolation(__('الوصفة المختارة غير صالحة أو غير فعّالة.'));
        }

        return $prescription;
    }
}
