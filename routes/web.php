<?php

use App\Http\Controllers\Account;
use App\Http\Controllers\Admin;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Care;
use App\Http\Controllers\Delivery;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Pharmacy;
use App\Http\Controllers\Practitioner;
use App\Http\Controllers\Site\FreeConsultationController;
use App\Http\Controllers\Site\HealthController;
use App\Http\Controllers\Site\LocaleController;
use App\Http\Controllers\Site\PageController;
use App\Http\Controllers\Webhooks\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public site
|--------------------------------------------------------------------------
*/
Route::controller(PageController::class)->group(function () {
    Route::get('/', 'home')->name('home');
    Route::get('/how-it-works', 'howItWorks')->name('how-it-works');
    Route::get('/services', 'services')->name('services');
    Route::get('/team', 'team')->name('team');
    Route::get('/pricing', 'pricing')->name('pricing');
    Route::get('/faq', 'faq')->name('faq');
    Route::get('/prescription-journey', 'prescriptionJourney')->name('prescription-journey');
    Route::get('/about', 'about')->name('about');
    Route::get('/contact', 'contact')->name('contact');
    Route::get('/policies', 'policies')->name('policies.index');
    Route::get('/policies/{page}', 'policy')->name('policies.show');
});

Route::get('/free-consultation', FreeConsultationController::class)->name('free-consultation');
Route::get('/locale/{locale}', LocaleController::class)->name('locale');
Route::get('/health', HealthController::class)->name('health');

// Payment provider callback: authenticated by signature, exempt from CSRF (see bootstrap/app.php).
Route::post('/webhooks/payments', PaymentWebhookController::class)->middleware('throttle:webhooks')->name('webhooks.payments');

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:register');

    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');

    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:password-reset')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:password-reset')->name('password.update');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Shared by every signed-in role
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', fn () => redirect()->route(auth()->user()->role->homeRoute()))->name('dashboard');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read', [NotificationController::class, 'markAllRead'])->name('notifications.read');

    // Authorised through AttachmentPolicy on every request.
    Route::get('/attachments/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');

    // Sandbox payment page (only reachable while the sandbox gateway driver is active, never in production).
    Route::get('/checkout/sandbox/{payment}', [Account\SandboxCheckoutController::class, 'show'])->name('checkout.sandbox.show');
    Route::post('/checkout/sandbox/{payment}', [Account\SandboxCheckoutController::class, 'complete'])->middleware('throttle:sensitive')->name('checkout.sandbox.complete');
});

/*
|--------------------------------------------------------------------------
| Client account
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:client'])->prefix('account')->name('account.')->group(function () {
    Route::get('/', Account\DashboardController::class)->name('dashboard');

    Route::get('/appointments', [Account\AppointmentController::class, 'index'])->name('appointments.index');
    Route::get('/appointments/book', [Account\AppointmentController::class, 'create'])->name('appointments.create');
    Route::post('/appointments', [Account\AppointmentController::class, 'store'])->middleware('throttle:sensitive')->name('appointments.store');
    Route::get('/appointments/{appointment}', [Account\AppointmentController::class, 'show'])->name('appointments.show');
    Route::post('/appointments/{appointment}/cancel', [Account\AppointmentController::class, 'cancel'])->name('appointments.cancel');

    Route::get('/assessment', [Account\AssessmentController::class, 'show'])->name('assessment.show');
    Route::put('/assessment', [Account\AssessmentController::class, 'update'])->middleware('throttle:sensitive')->name('assessment.update');

    Route::get('/plan', [Account\PlanController::class, 'show'])->name('plan.show');

    Route::get('/progress', [Account\ProgressController::class, 'index'])->name('progress.index');
    Route::post('/progress', [Account\ProgressController::class, 'store'])->name('progress.store');
    Route::delete('/progress/{measurement}', [Account\ProgressController::class, 'destroy'])->name('progress.destroy');

    Route::get('/medications', [Account\MedicationController::class, 'index'])->name('medications.index');
    Route::get('/medications/{prescription}', [Account\MedicationController::class, 'show'])->name('medications.show');
    Route::post('/medications/{prescription}/doses', [Account\MedicationController::class, 'logDose'])->middleware('throttle:sensitive')->name('medications.doses.store');

    Route::get('/side-effects', [Account\SideEffectController::class, 'index'])->name('side-effects.index');
    Route::post('/side-effects', [Account\SideEffectController::class, 'store'])->middleware('throttle:uploads')->name('side-effects.store');

    Route::get('/contact-requests', [Account\ContactRequestController::class, 'index'])->name('contact-requests.index');
    Route::post('/contact-requests', [Account\ContactRequestController::class, 'store'])->middleware('throttle:sensitive')->name('contact-requests.store');

    Route::get('/labs', [Account\LabController::class, 'index'])->name('labs.index');
    Route::post('/labs/{lab}/results', [Account\LabController::class, 'upload'])->middleware('throttle:uploads')->name('labs.upload');

    Route::get('/orders', [Account\OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/create', [Account\OrderController::class, 'create'])->name('orders.create');
    Route::post('/orders', [Account\OrderController::class, 'store'])->middleware('throttle:sensitive')->name('orders.store');
    Route::get('/orders/{order}', [Account\OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/approve', [Account\OrderController::class, 'approve'])->middleware('throttle:sensitive')->name('orders.approve');
    Route::post('/orders/{order}/cancel', [Account\OrderController::class, 'cancel'])->name('orders.cancel');

    Route::get('/subscription', [Account\SubscriptionController::class, 'show'])->name('subscription.show');
    Route::post('/subscription/auto-renew', [Account\SubscriptionController::class, 'enableAutoRenew'])->name('subscription.auto-renew.enable');
    Route::delete('/subscription/auto-renew', [Account\SubscriptionController::class, 'disableAutoRenew'])->name('subscription.auto-renew.disable');
    Route::put('/subscription/payment-method', [Account\SubscriptionController::class, 'updatePaymentMethod'])->name('subscription.payment-method');

    Route::get('/checkout/{package}', [Account\CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/checkout/{package}', [Account\CheckoutController::class, 'store'])->middleware('throttle:sensitive')->name('checkout.store');
    Route::get('/invoices/{payment}', [Account\InvoiceController::class, 'show'])->name('invoices.show');

    Route::get('/tickets', [Account\TicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/create', [Account\TicketController::class, 'create'])->name('tickets.create');
    Route::post('/tickets', [Account\TicketController::class, 'store'])->middleware('throttle:sensitive')->name('tickets.store');
    Route::get('/tickets/{ticket}', [Account\TicketController::class, 'show'])->name('tickets.show');
    Route::post('/tickets/{ticket}/replies', [Account\TicketController::class, 'reply'])->middleware('throttle:sensitive')->name('tickets.reply');

    Route::get('/profile', [Account\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [Account\ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [Account\ProfileController::class, 'updatePassword'])->middleware('throttle:sensitive')->name('profile.password');
});

/*
|--------------------------------------------------------------------------
| Doctors and dietitians
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:doctor,dietitian'])->prefix('practitioner')->name('practitioner.')->group(function () {
    Route::get('/', Practitioner\DashboardController::class)->name('dashboard');

    Route::get('/clients', [Practitioner\ClientController::class, 'index'])->name('clients.index');
    Route::get('/clients/{client}', [Practitioner\ClientController::class, 'show'])->name('clients.show');

    Route::get('/clients/{client}/plan/create', [Practitioner\PlanController::class, 'create'])->name('plans.create');
    Route::post('/clients/{client}/plan', [Practitioner\PlanController::class, 'store'])->name('plans.store');
    Route::get('/plans/{plan}/edit', [Practitioner\PlanController::class, 'edit'])->name('plans.edit');
    Route::put('/plans/{plan}', [Practitioner\PlanController::class, 'update'])->name('plans.update');
});

Route::middleware(['auth', 'role:doctor'])->prefix('practitioner')->name('practitioner.')->group(function () {
    Route::get('/appointments', [Practitioner\AppointmentController::class, 'index'])->name('appointments.index');
    Route::get('/appointments/{appointment}', [Practitioner\AppointmentController::class, 'show'])->name('appointments.show');
    Route::post('/appointments/{appointment}/complete', [Practitioner\AppointmentController::class, 'complete'])->name('appointments.complete');
    Route::post('/appointments/{appointment}/claim', [Practitioner\AppointmentController::class, 'claim'])->name('appointments.claim');

    Route::get('/assessments', [Practitioner\AssessmentController::class, 'index'])->name('assessments.index');
    Route::get('/assessments/{assessment}', [Practitioner\AssessmentController::class, 'show'])->name('assessments.show');
    Route::post('/assessments/{assessment}/review', [Practitioner\AssessmentController::class, 'review'])->name('assessments.review');

    Route::post('/clients/{client}/prescriptions', [Practitioner\PrescriptionController::class, 'store'])->name('prescriptions.store');
    Route::post('/prescriptions/{prescription}/stop', [Practitioner\PrescriptionController::class, 'stop'])->name('prescriptions.stop');

    Route::get('/labs', [Practitioner\LabController::class, 'index'])->name('labs.index');
    Route::post('/clients/{client}/labs', [Practitioner\LabController::class, 'store'])->name('labs.store');
    Route::post('/labs/{lab}/review', [Practitioner\LabController::class, 'review'])->name('labs.review');

    Route::get('/availability', [Practitioner\AvailabilityController::class, 'edit'])->name('availability.edit');
    Route::put('/availability', [Practitioner\AvailabilityController::class, 'update'])->name('availability.update');
});

/*
|--------------------------------------------------------------------------
| Care team (and doctors, for requests addressed to them)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:care_team'])->prefix('care')->name('care.')->group(function () {
    Route::get('/', Care\DashboardController::class)->name('dashboard');
    Route::get('/side-effects', [Care\SideEffectController::class, 'index'])->name('side-effects.index');
    Route::put('/side-effects/{report}', [Care\SideEffectController::class, 'update'])->name('side-effects.update');
});

Route::middleware(['auth', 'role:care_team,doctor'])->prefix('care')->name('care.')->group(function () {
    Route::get('/contact-requests', [Care\ContactRequestController::class, 'index'])->name('contact-requests.index');
    Route::put('/contact-requests/{contactRequest}', [Care\ContactRequestController::class, 'update'])->name('contact-requests.update');
});

/*
|--------------------------------------------------------------------------
| Pharmacy and delivery partner
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:pharmacy'])->prefix('pharmacy')->name('pharmacy.')->group(function () {
    Route::get('/orders', [Pharmacy\OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [Pharmacy\OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/quote', [Pharmacy\OrderController::class, 'quote'])->name('orders.quote');
    Route::post('/orders/{order}/reject', [Pharmacy\OrderController::class, 'reject'])->name('orders.reject');
    Route::post('/orders/{order}/advance', [Pharmacy\OrderController::class, 'advance'])->name('orders.advance');
});

Route::middleware(['auth', 'role:delivery'])->prefix('delivery')->name('delivery.')->group(function () {
    Route::get('/orders', [Delivery\OrderController::class, 'index'])->name('orders.index');
    Route::post('/orders/{order}/advance', [Delivery\OrderController::class, 'advance'])->name('orders.advance');
});

/*
|--------------------------------------------------------------------------
| Administration and support
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:admin,support'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');

    Route::get('/clients', [Admin\ClientController::class, 'index'])->name('clients.index');
    Route::get('/clients/{client}', [Admin\ClientController::class, 'show'])->name('clients.show');
    Route::get('/subscriptions', [Admin\SubscriptionController::class, 'index'])->name('subscriptions.index');
    Route::get('/appointments', [Admin\AppointmentController::class, 'index'])->name('appointments.index');
    Route::post('/appointments/{appointment}/cancel', [Admin\AppointmentController::class, 'cancel'])->name('appointments.cancel');
    Route::get('/operations', Admin\OperationsController::class)->name('operations');

    Route::get('/tickets', [Admin\TicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/{ticket}', [Admin\TicketController::class, 'show'])->name('tickets.show');
    Route::post('/tickets/{ticket}/replies', [Admin\TicketController::class, 'reply'])->name('tickets.reply');
    Route::put('/tickets/{ticket}', [Admin\TicketController::class, 'update'])->name('tickets.update');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::put('/clients/{client}/primary-doctor', [Admin\ClientController::class, 'assignDoctor'])->name('clients.primary-doctor');
    Route::post('/clients/{client}/toggle-active', [Admin\ClientController::class, 'toggleActive'])->name('clients.toggle-active');

    Route::get('/payments', [Admin\PaymentController::class, 'index'])->name('payments.index');
    Route::post('/payments/{payment}/refund', [Admin\PaymentController::class, 'refund'])->middleware('throttle:sensitive')->name('payments.refund');

    Route::get('/packages', [Admin\PackageController::class, 'index'])->name('packages.index');
    Route::get('/packages/{package}/edit', [Admin\PackageController::class, 'edit'])->name('packages.edit');
    Route::put('/packages/{package}', [Admin\PackageController::class, 'update'])->name('packages.update');

    Route::get('/practitioners', [Admin\PractitionerController::class, 'index'])->name('practitioners.index');
    Route::get('/practitioners/{practitioner}/edit', [Admin\PractitionerController::class, 'edit'])->name('practitioners.edit');
    Route::put('/practitioners/{practitioner}', [Admin\PractitionerController::class, 'update'])->name('practitioners.update');
    Route::post('/practitioners/{practitioner}/verify', [Admin\PractitionerController::class, 'verify'])->name('practitioners.verify');
    Route::post('/practitioners/{practitioner}/revoke', [Admin\PractitionerController::class, 'revoke'])->name('practitioners.revoke');

    Route::get('/staff', [Admin\StaffController::class, 'index'])->name('staff.index');
    Route::get('/staff/create', [Admin\StaffController::class, 'create'])->name('staff.create');
    Route::post('/staff', [Admin\StaffController::class, 'store'])->name('staff.store');
    Route::get('/staff/{member}/edit', [Admin\StaffController::class, 'edit'])->name('staff.edit');
    Route::put('/staff/{member}', [Admin\StaffController::class, 'update'])->name('staff.update');

    Route::get('/pages', [Admin\PageController::class, 'index'])->name('pages.index');
    Route::get('/pages/{page}/edit', [Admin\PageController::class, 'edit'])->name('pages.edit');
    Route::put('/pages/{page}', [Admin\PageController::class, 'update'])->name('pages.update');

    Route::resource('faqs', Admin\FaqController::class)->except('show');
    Route::resource('products', Admin\ProductController::class)->except('show');
    Route::resource('pharmacies', Admin\PharmacyController::class)->except(['show', 'destroy'])->parameters(['pharmacies' => 'pharmacy']);

    Route::get('/reports', Admin\ReportController::class)->name('reports');
    Route::get('/audit-logs', Admin\AuditLogController::class)->name('audit-logs');
});
