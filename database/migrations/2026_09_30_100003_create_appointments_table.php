<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('practitioner_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->string('mode', 10);
            $table->string('status', 20);
            $table->timestamp('scheduled_at');
            $table->boolean('is_instant')->default(false);
            $table->unsignedSmallInteger('duration_minutes')->default(20);
            $table->string('ftms_reference', 40)->nullable()->unique();
            $table->string('meeting_url')->nullable();
            $table->text('client_notes')->nullable();       // encrypted by the model
            $table->text('practitioner_notes')->nullable(); // encrypted by the model
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['practitioner_id', 'scheduled_at']);
            $table->index(['user_id', 'status']);
            $table->index(['status', 'scheduled_at']);
        });

        // One free consultation per client (a cancelled one does not count).
        DB::statement("CREATE UNIQUE INDEX appointments_one_free_intro_per_user ON appointments (user_id) WHERE type = 'free_intro' AND status <> 'cancelled' AND deleted_at IS NULL");
        // A practitioner cannot be booked twice at the same time.
        DB::statement("CREATE UNIQUE INDEX appointments_no_double_booking ON appointments (practitioner_id, scheduled_at) WHERE practitioner_id IS NOT NULL AND status IN ('requested', 'confirmed') AND deleted_at IS NULL");
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
