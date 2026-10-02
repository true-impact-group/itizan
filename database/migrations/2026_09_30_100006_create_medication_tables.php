<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('practitioner_id')->constrained()->restrictOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->text('medication_name');   // encrypted by the model
            $table->text('dose');              // encrypted by the model
            $table->text('instructions')->nullable(); // encrypted by the model
            $table->unsignedSmallInteger('interval_days')->default(1);
            $table->time('dose_time')->default('09:00:00');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->timestamp('next_dose_at')->nullable()->index();
            $table->timestamp('reminder_sent_for')->nullable();
            $table->string('status', 20)->index();
            $table->json('pre_dose_questions')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->string('stop_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
        });

        Schema::create('dose_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('scheduled_for');
            $table->string('status', 20);
            $table->timestamp('logged_at');
            $table->text('pre_dose_answers')->nullable(); // encrypted by the model
            $table->string('note', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            // A scheduled dose can be logged only once.
            $table->unique(['prescription_id', 'scheduled_for']);
        });

        Schema::create('side_effect_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('prescription_id')->nullable()->constrained()->nullOnDelete();
            $table->text('symptom');            // encrypted by the model
            $table->string('severity', 20);
            $table->timestamp('occurred_at');
            $table->string('status', 20)->index();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->text('response_note')->nullable(); // encrypted by the model
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'occurred_at']);
        });

        Schema::create('contact_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('target', 20);
            $table->text('reason');             // encrypted by the model
            $table->string('priority', 10)->default('normal');
            $table->string('source', 20)->default('client');
            $table->string('status', 20)->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('response_note')->nullable(); // encrypted by the model
            $table->timestamps();
            $table->softDeletes();

            $table->index(['target', 'status']);
        });

        Schema::create('lab_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('practitioner_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('instructions', 500)->nullable();
            $table->string('status', 20)->index();
            $table->date('due_on')->nullable();
            $table->timestamp('result_uploaded_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('reviewer_notes')->nullable(); // encrypted by the model
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable');
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('disk', 30);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('lab_requests');
        Schema::dropIfExists('contact_requests');
        Schema::dropIfExists('side_effect_reports');
        Schema::dropIfExists('dose_logs');
        Schema::dropIfExists('prescriptions');
    }
};
