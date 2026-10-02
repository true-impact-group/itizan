<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique();
            $table->string('name_ar');
            $table->string('name_en');
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->unsignedSmallInteger('duration_months');
            $table->unsignedInteger('price_halalas');
            $table->boolean('vat_inclusive')->default(true);
            $table->unsignedSmallInteger('included_followups')->default(0);
            $table->json('features_ar')->nullable();
            $table->json('features_en')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('package_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->index();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->boolean('auto_renew')->default(false);
            $table->timestamp('auto_renew_consented_at')->nullable();
            $table->string('payment_method', 20)->nullable();
            $table->unsignedInteger('price_halalas');
            $table->unsignedSmallInteger('followups_included')->default(0);
            $table->unsignedSmallInteger('followups_used')->default(0);
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamp('renewal_reminded_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status', 'ends_at']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('subscription_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('amount_halalas');
            $table->unsignedInteger('vat_halalas')->default(0);
            $table->string('currency', 3)->default('SAR');
            $table->string('method', 20);
            $table->string('status', 20)->index();
            $table->string('provider', 30);
            $table->string('provider_reference')->nullable()->unique();
            $table->string('invoice_number', 30)->nullable()->unique();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->unsignedInteger('refunded_halalas')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
        });

        DB::statement('ALTER TABLE packages ADD CONSTRAINT packages_price_positive CHECK (price_halalas > 0)');
        DB::statement('ALTER TABLE subscriptions ADD CONSTRAINT subscriptions_period_valid CHECK (ends_at > starts_at)');
        DB::statement('ALTER TABLE subscriptions ADD CONSTRAINT subscriptions_followups_valid CHECK (followups_used <= followups_included)');
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_refund_valid CHECK (refunded_halalas <= amount_halalas)');
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('packages');
    }
};
