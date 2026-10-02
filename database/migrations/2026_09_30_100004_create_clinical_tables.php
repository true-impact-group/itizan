<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->index();
            $table->decimal('height_cm', 5, 1)->nullable();
            $table->decimal('weight_kg', 5, 1)->nullable();
            $table->decimal('waist_cm', 5, 1)->nullable();
            $table->decimal('bmi', 4, 1)->nullable();
            $table->decimal('target_weight_kg', 5, 1)->nullable();
            $table->string('pregnancy_status', 20)->nullable();
            $table->boolean('is_breastfeeding')->nullable();
            // Free-text clinical fields are encrypted at rest by the model.
            $table->text('medical_history')->nullable();
            $table->text('chronic_conditions')->nullable();
            $table->text('current_medications')->nullable();
            $table->text('allergies')->nullable();
            $table->text('past_surgeries')->nullable();
            $table->text('previous_treatments')->nullable();
            $table->text('current_symptoms')->nullable();
            $table->text('available_labs')->nullable();
            $table->text('goals')->nullable();
            $table->text('safety_answers')->nullable();
            $table->boolean('has_red_flags')->default(false)->index();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('reviewer_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // A client has at most one open assessment at a time.
        DB::statement("CREATE UNIQUE INDEX medical_assessments_one_open_per_user ON medical_assessments (user_id) WHERE status IN ('draft', 'submitted', 'needs_info') AND deleted_at IS NULL");

        Schema::create('weight_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('assessment_id')->nullable()->constrained('medical_assessments')->nullOnDelete();
            $table->foreignId('practitioner_id')->constrained()->restrictOnDelete();
            $table->foreignId('dietitian_id')->nullable()->constrained('practitioners')->nullOnDelete();
            $table->string('status', 20)->index();
            $table->decimal('start_weight_kg', 5, 1);
            $table->decimal('target_weight_kg', 5, 1);
            $table->decimal('start_waist_cm', 5, 1)->nullable();
            $table->decimal('target_waist_cm', 5, 1)->nullable();
            $table->text('doctor_notes')->nullable();
            $table->json('next_steps')->nullable();
            $table->text('nutrition_guidance')->nullable();
            $table->date('starts_on');
            $table->date('review_on')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement("CREATE UNIQUE INDEX weight_plans_one_active_per_user ON weight_plans (user_id) WHERE status = 'active' AND deleted_at IS NULL");

        Schema::create('measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('measured_on');
            $table->decimal('weight_kg', 5, 1);
            $table->decimal('waist_cm', 5, 1)->nullable();
            $table->string('note', 255)->nullable();
            $table->string('source', 20)->default('client');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'measured_on']);
        });

        DB::statement('ALTER TABLE measurements ADD CONSTRAINT measurements_weight_sane CHECK (weight_kg BETWEEN 20 AND 500)');
        DB::statement('ALTER TABLE medical_assessments ADD CONSTRAINT assessments_weight_sane CHECK (weight_kg IS NULL OR weight_kg BETWEEN 20 AND 500)');
    }

    public function down(): void
    {
        Schema::dropIfExists('measurements');
        Schema::dropIfExists('weight_plans');
        Schema::dropIfExists('medical_assessments');
    }
};
