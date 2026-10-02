<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderStatus;
use App\Enums\PrescriptionStatus;
use App\Enums\Role;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Pharmacy;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\User;
use App\Notifications\GenericNotification;
use App\Notifications\OrderUpdatedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrderFlowTest extends TestCase
{
    private User $client;

    private Pharmacy $pharmacy;

    private User $pharmacist;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = $this->client(['city' => 'الرياض']);
        $this->subscribe($this->client); // ordering medication is for subscribers
        $this->pharmacy = Pharmacy::factory()->create();
        $this->pharmacist = $this->pharmacist($this->pharmacy);
    }

    private function place(array $items, array $extra = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->client)->post(route('account.orders.store'), [
            'items' => $items,
            'delivery_city' => 'الرياض',
            'delivery_address' => 'حي الملقا، شارع الأمير محمد بن سلمان',
            ...$extra,
        ]);
    }

    private function quote(Order $order, ?User $as = null, array $overrides = [])
    {
        $payload = [
            'lines' => $order->items->mapWithKeys(fn (OrderItem $i) => [$i->id => ['available' => '1', 'unit_price' => '50.00']])->all(),
            'eta_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'delivery_fee' => '20',
            ...$overrides,
        ];

        return $this->actingAs($as ?? $this->pharmacist)->post(route('pharmacy.orders.quote', $order), $payload);
    }

    private function quotedOrder(array $state = []): Order
    {
        $order = Order::factory()->for($this->client, 'user')->create(['status' => OrderStatus::Quoted, 'pharmacy_id' => $this->pharmacy->id, 'subtotal_halalas' => 10000, 'total_halalas' => 10000, 'eta_at' => now()->addDay(), ...$state]);
        $order->items()->create(['name' => 'منتج', 'quantity' => 2, 'unit_price_halalas' => 5000, 'is_available' => true]);

        return $order;
    }

    // ---- requesting ------------------------------------------------------------------------------

    public function test_a_client_orders_a_supplement_and_delivery_is_free_in_a_covered_city(): void
    {
        Notification::fake();
        $supplement = Product::factory()->create(['price_halalas' => 4500]);

        $this->place([['product_id' => $supplement->id, 'quantity' => 2]])->assertRedirect();

        $order = Order::with('items', 'events')->firstOrFail();
        $this->assertSame(OrderStatus::Requested, $order->status);
        $this->assertSame(9000, $order->subtotal_halalas);
        $this->assertSame(0, $order->delivery_fee_halalas);
        $this->assertTrue($order->free_delivery);
        $this->assertSame(9000, $order->total_halalas);
        $this->assertCount(1, $order->items);
        $this->assertSame(2, $order->items->first()->quantity);
        $this->assertCount(1, $order->events);
        $this->assertNull($order->pharmacy_id);
        Notification::assertSentTo($this->pharmacist, GenericNotification::class);
    }

    public function test_delivery_has_a_fee_outside_the_free_delivery_cities(): void
    {
        $supplement = Product::factory()->create(['price_halalas' => 4500]);

        $this->place([['product_id' => $supplement->id, 'quantity' => 1]], ['delivery_city' => 'تبوك']);

        $order = Order::firstOrFail();
        $this->assertFalse($order->free_delivery);
        $this->assertSame(2500, $order->delivery_fee_halalas);
        $this->assertSame(7000, $order->total_halalas);
    }

    public function test_the_delivery_address_is_encrypted_at_rest(): void
    {
        $this->place([['product_id' => Product::factory()->create()->id, 'quantity' => 1]], ['delivery_address' => 'عنوان سري جدًا 123']);

        $this->assertStringNotContainsString('سري', (string) DB::table('orders')->value('delivery_address'));
        $this->assertSame('عنوان سري جدًا 123', Order::firstOrFail()->delivery_address);
    }

    public function test_a_medication_needs_one_of_the_clients_active_prescriptions(): void
    {
        $medication = Product::factory()->medication()->create();

        $this->place([['product_id' => $medication->id, 'quantity' => 1]])
            ->assertSessionHas('error', 'هذا المنتج يُصرف بوصفة طبية. اختر الوصفة المرتبطة بالطلب.');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_a_medication_order_with_a_valid_prescription_is_accepted_and_linked(): void
    {
        $medication = Product::factory()->medication()->create();
        $rx = Prescription::factory()->create(['user_id' => $this->client->id, 'product_id' => $medication->id]);

        $this->place([['product_id' => $medication->id, 'quantity' => 1]], ['prescription_id' => $rx->id])->assertRedirect();

        $this->assertSame($rx->id, Order::firstOrFail()->prescription_id);
    }

    public function test_someone_elses_or_an_inactive_prescription_cannot_be_used(): void
    {
        $medication = Product::factory()->medication()->create();
        $foreign = Prescription::factory()->create(['product_id' => $medication->id]);
        $stopped = Prescription::factory()->create(['user_id' => $this->client->id, 'product_id' => $medication->id, 'status' => PrescriptionStatus::Stopped]);

        $this->place([['product_id' => $medication->id, 'quantity' => 1]], ['prescription_id' => $foreign->id])->assertSessionHas('error');
        $this->place([['product_id' => $medication->id, 'quantity' => 1]], ['prescription_id' => $stopped->id])->assertSessionHas('error');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_the_ordered_medication_must_match_the_prescribed_one(): void
    {
        $prescribed = Product::factory()->medication()->create();
        $other = Product::factory()->medication()->create();
        $rx = Prescription::factory()->create(['user_id' => $this->client->id, 'product_id' => $prescribed->id]);

        $this->place([['product_id' => $other->id, 'quantity' => 1]], ['prescription_id' => $rx->id])
            ->assertSessionHas('error', 'المنتج المختار لا يطابق الوصفة.');
    }

    public function test_quantity_zero_rows_are_ignored_and_an_empty_order_is_refused(): void
    {
        $a = Product::factory()->create();
        $b = Product::factory()->create();

        $this->place([['product_id' => $a->id, 'quantity' => 0], ['product_id' => $b->id, 'quantity' => 0]])
            ->assertSessionHas('error', 'اختر منتجات متاحة للطلب.');

        $this->place([['product_id' => $a->id, 'quantity' => 0], ['product_id' => $b->id, 'quantity' => 3]])->assertRedirect();
        $this->assertSame(1, Order::firstOrFail()->items()->count());
    }

    public function test_inactive_unknown_products_and_bad_input_are_refused(): void
    {
        $inactive = Product::factory()->create(['is_active' => false]);

        $this->place([['product_id' => $inactive->id, 'quantity' => 1]])->assertSessionHas('error');
        $this->place([['product_id' => 999999, 'quantity' => 1]])->assertSessionHas('error');
        $this->place([['product_id' => Product::factory()->create()->id, 'quantity' => 11]])->assertSessionHasErrors('items.0.quantity');
        $this->place([['product_id' => Product::factory()->create()->id, 'quantity' => 1]], ['delivery_city' => 'مدينة وهمية'])->assertSessionHasErrors('delivery_city');
        $this->place([['product_id' => Product::factory()->create()->id, 'quantity' => 1]], ['delivery_address' => 'قصير'])->assertSessionHasErrors('delivery_address');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_the_order_form_lists_products_and_explains_free_delivery(): void
    {
        Product::factory()->create(['name_ar' => 'منتج ظاهر']);
        Product::factory()->create(['name_ar' => 'منتج مخفي', 'is_active' => false]);

        $this->actingAs($this->client)->get(route('account.orders.create'))
            ->assertOk()->assertSee('منتج ظاهر')->assertDontSee('منتج مخفي')->assertSee('التوصيل مجاني في')->assertSee('الرياض');
    }

    // ---- pharmacy quote ------------------------------------------------------------------------------

    public function test_the_pharmacy_confirms_availability_price_and_delivery_time(): void
    {
        Notification::fake();
        $order = Order::factory()->for($this->client, 'user')->create(['free_delivery' => false]);
        $order->items()->create(['name' => 'منتج أ', 'quantity' => 2, 'unit_price_halalas' => 4000, 'is_available' => true]);

        $this->quote($order->load('items'))->assertRedirect(route('pharmacy.orders.show', $order));

        $order->refresh();
        $this->assertSame(OrderStatus::Quoted, $order->status);
        $this->assertSame($this->pharmacy->id, $order->pharmacy_id);
        $this->assertSame(10000, $order->subtotal_halalas, '2 x 50.00');
        $this->assertSame(2000, $order->delivery_fee_halalas);
        $this->assertSame(12000, $order->total_halalas);
        $this->assertNotNull($order->eta_at);
        $this->assertNotNull($order->quoted_at);
        Notification::assertSentTo($this->client, OrderUpdatedNotification::class);
    }

    public function test_free_delivery_stays_free_whatever_fee_the_pharmacy_types(): void
    {
        $order = Order::factory()->for($this->client, 'user')->create(['free_delivery' => true]);
        $order->items()->create(['name' => 'منتج', 'quantity' => 1, 'unit_price_halalas' => 4000, 'is_available' => true]);

        $this->quote($order->load('items'), null, ['delivery_fee' => '99']);

        $this->assertSame(0, $order->fresh()->delivery_fee_halalas);
    }

    public function test_unavailable_items_are_excluded_and_a_fully_unavailable_order_is_rejected(): void
    {
        $order = Order::factory()->for($this->client, 'user')->create();
        $a = $order->items()->create(['name' => 'متوفر', 'quantity' => 1, 'unit_price_halalas' => 4000, 'is_available' => true]);
        $b = $order->items()->create(['name' => 'غير متوفر', 'quantity' => 1, 'unit_price_halalas' => 4000, 'is_available' => true]);

        $this->actingAs($this->pharmacist)->post(route('pharmacy.orders.quote', $order), [
            'lines' => [$a->id => ['available' => '1', 'unit_price' => '30'], $b->id => ['available' => '0', 'unit_price' => '30']],
            'eta_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ]);
        $this->assertSame(3000, $order->fresh()->subtotal_halalas);

        $second = Order::factory()->for($this->client, 'user')->create();
        $x = $second->items()->create(['name' => 'س', 'quantity' => 1, 'unit_price_halalas' => 4000, 'is_available' => true]);
        $this->actingAs($this->pharmacist)->post(route('pharmacy.orders.quote', $second), [
            'lines' => [$x->id => ['available' => '0']], 'eta_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ]);
        $this->assertSame(OrderStatus::Rejected, $second->fresh()->status);
        $this->assertSame(0, $second->fresh()->total_halalas);
    }

    public function test_quote_input_is_validated(): void
    {
        $order = Order::factory()->for($this->client, 'user')->create();
        $order->items()->create(['name' => 'منتج', 'quantity' => 1, 'unit_price_halalas' => 4000, 'is_available' => true]);
        $order->load('items');

        $this->quote($order, null, ['eta_at' => now()->subDay()->format('Y-m-d\TH:i')])->assertSessionHasErrors('eta_at');
        $this->quote($order, null, ['eta_at' => 'soon'])->assertSessionHasErrors('eta_at');
        $this->quote($order, null, ['lines' => [$order->items->first()->id => ['available' => '1', 'unit_price' => '-5']]])->assertSessionHasErrors('lines.*.unit_price');

        $this->assertSame(OrderStatus::Requested, $order->fresh()->status);
    }

    public function test_the_first_pharmacy_to_quote_owns_the_order_and_others_are_locked_out(): void
    {
        $order = Order::factory()->for($this->client, 'user')->create();
        $order->items()->create(['name' => 'منتج', 'quantity' => 1, 'unit_price_halalas' => 4000, 'is_available' => true]);
        $other = $this->pharmacist();

        $this->quote($order->load('items'))->assertRedirect();

        $this->actingAs($other)->get(route('pharmacy.orders.show', $order))->assertForbidden();
        $this->quote($order, $other)->assertForbidden();
        $this->assertSame($this->pharmacy->id, $order->fresh()->pharmacy_id);
    }

    public function test_the_pharmacy_can_requote_before_the_client_approves(): void
    {
        $order = $this->quotedOrder();
        $order->load('items');

        $this->quote($order, null, ['delivery_fee' => '0'])->assertRedirect();

        $this->assertSame(OrderStatus::Quoted, $order->fresh()->status);
        $this->assertSame(10000, $order->fresh()->subtotal_halalas);
    }

    public function test_pharmacy_staff_without_a_pharmacy_or_with_an_inactive_one_cannot_quote(): void
    {
        $order = Order::factory()->for($this->client, 'user')->create();
        $order->items()->create(['name' => 'منتج', 'quantity' => 1, 'unit_price_halalas' => 4000, 'is_available' => true]);
        $order->load('items');

        $this->pharmacy->update(['is_active' => false]);
        $this->quote($order)->assertSessionHas('error', 'هذا الإجراء متاح لموظفي الصيدليات المرخّصة فقط.');

        $orphan = User::factory()->role(Role::Pharmacy)->create(['pharmacy_id' => null]);
        $this->quote($order, $orphan)->assertForbidden();
    }

    public function test_the_pharmacy_queue_shows_its_own_orders_and_unassigned_new_ones_only(): void
    {
        $mine = Order::factory()->create(['pharmacy_id' => $this->pharmacy->id, 'status' => OrderStatus::Quoted]);
        $open = Order::factory()->create(['status' => OrderStatus::Requested]);
        $theirs = Order::factory()->create(['pharmacy_id' => Pharmacy::factory()->create()->id, 'status' => OrderStatus::Quoted]);

        $response = $this->actingAs($this->pharmacist)->get(route('pharmacy.orders.index'))->assertOk();

        $ids = $response->viewData('orders')->pluck('id');
        $this->assertTrue($ids->contains($mine->id));
        $this->assertTrue($ids->contains($open->id));
        $this->assertFalse($ids->contains($theirs->id));
    }

    // ---- client approval ------------------------------------------------------------------------------

    public function test_the_client_approves_the_exact_quoted_price(): void
    {
        $order = $this->quotedOrder();

        $this->actingAs($this->client)->post(route('account.orders.approve', $order), ['total_halalas' => 10000, 'agree' => '1'])
            ->assertRedirect(route('account.orders.show', $order));

        $order->refresh();
        $this->assertSame(OrderStatus::Approved, $order->status);
        $this->assertNotNull($order->approved_at);
        $this->assertSame(OrderStatus::Approved, $order->events()->latest('id')->first()->status);
    }

    public function test_approval_requires_the_agreement_checkbox(): void
    {
        $order = $this->quotedOrder();

        $this->actingAs($this->client)->post(route('account.orders.approve', $order), ['total_halalas' => 10000, 'agree' => '0'])->assertSessionHasErrors('agree');

        $this->assertSame(OrderStatus::Quoted, $order->fresh()->status);
    }

    public function test_approval_is_refused_if_the_price_changed_after_the_client_saw_it(): void
    {
        $order = $this->quotedOrder(['total_halalas' => 12000]);

        $this->actingAs($this->client)->post(route('account.orders.approve', $order), ['total_halalas' => 10000, 'agree' => '1'])
            ->assertSessionHas('error', 'تغيّر السعر. راجع العرض المحدّث قبل الموافقة.');

        $this->assertSame(OrderStatus::Quoted, $order->fresh()->status);
    }

    public function test_an_order_that_was_not_quoted_cannot_be_approved(): void
    {
        $order = Order::factory()->for($this->client, 'user')->create(['status' => OrderStatus::Requested]);

        $this->actingAs($this->client)->post(route('account.orders.approve', $order), ['total_halalas' => 4500, 'agree' => '1'])->assertForbidden();
        $this->assertSame(OrderStatus::Requested, $order->fresh()->status);
    }

    public function test_the_quote_page_asks_for_approval_with_the_total_in_the_consent_text(): void
    {
        $order = $this->quotedOrder();

        $this->actingAs($this->client)->get(route('account.orders.show', $order))
            ->assertOk()->assertSee('راجع السعر ووافق عليه')->assertSee('100 ريال')->assertSee('موافقة وتأكيد الطلب');
    }

    // ---- fulfilment state machine ------------------------------------------------------------------------------

    public function test_an_approved_order_moves_through_preparing_delivery_and_delivered(): void
    {
        Notification::fake();
        $order = $this->quotedOrder(['status' => OrderStatus::Approved]);
        $driver = $this->staff(Role::Delivery);

        $this->actingAs($this->pharmacist)->post(route('pharmacy.orders.advance', $order), ['to' => 'preparing'])->assertRedirect();
        $this->assertSame(OrderStatus::Preparing, $order->fresh()->status);

        $this->actingAs($driver)->post(route('delivery.orders.advance', $order), ['to' => 'out_for_delivery'])->assertRedirect();
        $this->assertSame(OrderStatus::OutForDelivery, $order->fresh()->status);

        $this->actingAs($driver)->post(route('delivery.orders.advance', $order), ['to' => 'delivered'])->assertRedirect();

        $order->refresh();
        $this->assertSame(OrderStatus::Delivered, $order->status);
        $this->assertNotNull($order->delivered_at);
        $this->assertSame(3, $order->events()->whereIn('status', ['preparing', 'out_for_delivery', 'delivered'])->count());
        Notification::assertSentToTimes($this->client, OrderUpdatedNotification::class, 3);
    }

    public function test_steps_cannot_be_skipped_or_reversed(): void
    {
        $order = $this->quotedOrder(['status' => OrderStatus::Approved]);

        $this->actingAs($this->pharmacist)->post(route('pharmacy.orders.advance', $order), ['to' => 'delivered'])->assertSessionHas('error', 'لا يمكن نقل الطلب إلى هذه الحالة.');
        $this->assertSame(OrderStatus::Approved, $order->fresh()->status);

        $order->update(['status' => OrderStatus::Delivered]);
        $this->actingAs($this->pharmacist)->post(route('pharmacy.orders.advance', $order), ['to' => 'preparing'])->assertSessionHas('error');
    }

    public function test_preparation_cannot_start_before_the_client_approved_the_price(): void
    {
        $order = $this->quotedOrder(['status' => OrderStatus::Quoted]);

        $this->actingAs($this->pharmacist)->post(route('pharmacy.orders.advance', $order), ['to' => 'preparing'])->assertSessionHas('error');
        $this->assertSame(OrderStatus::Quoted, $order->fresh()->status);
    }

    public function test_the_delivery_partner_cannot_start_preparation_and_a_pharmacy_cannot_move_another_pharmacys_order(): void
    {
        $order = $this->quotedOrder(['status' => OrderStatus::Approved]);
        $driver = $this->staff(Role::Delivery);

        $this->actingAs($driver)->post(route('delivery.orders.advance', $order), ['to' => 'preparing'])->assertStatus(302)->assertSessionHasErrors('to');

        $this->actingAs($this->pharmacist())->post(route('pharmacy.orders.advance', $order), ['to' => 'preparing'])->assertForbidden();
        $this->assertSame(OrderStatus::Approved, $order->fresh()->status);
    }

    public function test_the_delivery_partner_sees_only_delivery_details_not_medical_ones(): void
    {
        $rx = Prescription::factory()->create(['medication_name' => 'اسم دواء سري']);
        $order = Order::factory()->create(['pharmacy_id' => $this->pharmacy->id, 'status' => OrderStatus::Preparing, 'prescription_id' => $rx->id, 'delivery_address' => 'عنوان التسليم الظاهر']);
        $order->items()->create(['name' => 'منتج دوائي سري', 'quantity' => 1, 'unit_price_halalas' => 4000, 'is_available' => true]);
        $hidden = Order::factory()->create(['status' => OrderStatus::Requested]);

        $this->actingAs($this->staff(Role::Delivery))->get(route('delivery.orders.index'))
            ->assertOk()
            ->assertSee('عنوان التسليم الظاهر')
            ->assertDontSee('اسم دواء سري')
            ->assertDontSee('منتج دوائي سري')
            ->assertDontSee('#'.$hidden->id);
    }

    public function test_the_delivery_partner_cannot_open_the_quote_or_a_non_dispatchable_order(): void
    {
        $driver = $this->staff(Role::Delivery);
        $requested = Order::factory()->create(['status' => OrderStatus::Requested]);

        $this->actingAs($driver)->get(route('pharmacy.orders.show', $requested))->assertForbidden();
        $this->actingAs($driver)->post(route('delivery.orders.advance', $requested), ['to' => 'out_for_delivery'])->assertForbidden();
    }

    // ---- cancelling / rejecting ------------------------------------------------------------------------------

    public function test_a_client_can_cancel_until_preparation_starts(): void
    {
        foreach ([OrderStatus::Requested, OrderStatus::Quoted, OrderStatus::Approved] as $status) {
            $order = Order::factory()->for($this->client, 'user')->create(['status' => $status]);

            $this->actingAs($this->client)->post(route('account.orders.cancel', $order), ['reason' => 'غيّرت رأيي'])->assertRedirect();

            $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
            $this->assertNotNull($order->fresh()->cancelled_at);
        }
    }

    public function test_a_client_cannot_cancel_once_the_order_is_being_prepared_or_on_its_way(): void
    {
        foreach ([OrderStatus::Preparing, OrderStatus::OutForDelivery, OrderStatus::Delivered] as $status) {
            $order = Order::factory()->for($this->client, 'user')->create(['status' => $status]);

            $this->actingAs($this->client)->post(route('account.orders.cancel', $order))->assertForbidden();
            $this->assertSame($status, $order->fresh()->status);
        }
    }

    public function test_the_pharmacy_can_reject_a_new_or_quoted_order_with_a_reason(): void
    {
        Notification::fake();
        $order = Order::factory()->for($this->client, 'user')->create(['status' => OrderStatus::Requested]);

        $this->actingAs($this->pharmacist)->post(route('pharmacy.orders.reject', $order), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($this->pharmacist)->post(route('pharmacy.orders.reject', $order), ['reason' => 'المنتج غير متوفر'])->assertRedirect(route('pharmacy.orders.index'));

        $this->assertSame(OrderStatus::Rejected, $order->fresh()->status);
        Notification::assertSentTo($this->client, OrderUpdatedNotification::class);
    }

    public function test_a_pharmacy_cannot_reject_an_order_it_is_already_fulfilling(): void
    {
        $order = $this->quotedOrder(['status' => OrderStatus::Preparing]);

        $this->actingAs($this->pharmacist)->post(route('pharmacy.orders.reject', $order), ['reason' => 'سبب'])->assertSessionHas('error');
        $this->assertSame(OrderStatus::Preparing, $order->fresh()->status);
    }

    // ---- visibility ------------------------------------------------------------------------------

    public function test_the_client_sees_a_timeline_of_updates(): void
    {
        $order = $this->quotedOrder();
        $order->recordEvent(OrderStatus::Requested, 'تم إرسال طلبك إلى الصيدلية');
        $order->recordEvent(OrderStatus::Quoted, 'تم تأكيد التوفر والسعر وموعد التوصيل المتوقع');

        $this->actingAs($this->client)->get(route('account.orders.show', $order))
            ->assertSee('تحديثات الطلب')->assertSee('تم إرسال طلبك إلى الصيدلية')->assertSee('تم تأكيد التوفر والسعر');
    }

    public function test_the_pharmacy_sees_the_prescription_only_for_orders_it_can_access(): void
    {
        $rx = Prescription::factory()->create(['medication_name' => 'دواء ظاهر للصيدلية']);
        $own = Order::factory()->create(['pharmacy_id' => $this->pharmacy->id, 'prescription_id' => $rx->id, 'status' => OrderStatus::Approved]);
        $other = $this->pharmacist();

        $this->actingAs($this->pharmacist)->get(route('pharmacy.orders.show', $own))->assertOk()->assertSee('دواء ظاهر للصيدلية');

        $this->assertTrue($this->pharmacist->can('view', $rx));
        $this->assertFalse($other->can('view', $rx));
    }

    public function test_unassigned_new_orders_expose_their_prescription_to_any_pharmacy_so_it_can_quote(): void
    {
        $rx = Prescription::factory()->create();
        Order::factory()->create(['prescription_id' => $rx->id, 'status' => OrderStatus::Requested]);

        $this->assertTrue($this->pharmacist->can('view', $rx));
        $this->assertFalse($this->staff(Role::Delivery)->can('view', $rx));
        $this->assertFalse($this->admin()->can('view', $rx));
        $this->assertFalse($this->staff(Role::Support)->can('view', $rx));
    }

    public function test_admin_monitors_orders_by_status_without_seeing_items(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Preparing, 'pharmacy_id' => $this->pharmacy->id]);
        $order->items()->create(['name' => 'منتج حساس الاسم', 'quantity' => 1, 'unit_price_halalas' => 100, 'is_available' => true]);

        $this->actingAs($this->admin())->get(route('admin.operations'))->assertOk()->assertSee('#'.$order->id)->assertDontSee('منتج حساس الاسم');
    }

    public function test_orders_list_uses_a_constant_number_of_queries(): void
    {
        Order::factory()->count(8)->for($this->client, 'user')->create()->each(fn ($o) => $o->items()->create(['name' => 'م', 'quantity' => 1, 'unit_price_halalas' => 1, 'is_available' => true]));

        DB::enableQueryLog();
        $this->actingAs($this->client)->get(route('account.orders.index'))->assertOk();

        $this->assertLessThan(14, count(DB::getQueryLog()));
    }
}
