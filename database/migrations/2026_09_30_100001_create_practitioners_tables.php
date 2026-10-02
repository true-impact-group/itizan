<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practitioners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->string('name_en')->nullable();
            $table->string('specialty_ar');
            $table->string('specialty_en')->nullable();
            $table->string('license_number', 60)->nullable()->unique();
            $table->string('license_authority', 60)->default('SCFHS');
            $table->timestamp('license_verified_at')->nullable();
            $table->foreignId('license_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('license_expires_on')->nullable();
            $table->text('bio_ar')->nullable();
            $table->text('bio_en')->nullable();
            $table->unsignedSmallInteger('years_experience')->nullable();
            $table->boolean('accepts_instant')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'is_active']);
        });

        Schema::create('practitioner_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practitioner_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday'); // 0 = Sunday ... 6 = Saturday (Carbon dayOfWeek)
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('slot_minutes')->default(20);
            $table->timestamps();

            $table->unique(['practitioner_id', 'weekday', 'start_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practitioner_availabilities');
        Schema::dropIfExists('practitioners');
    }
};
