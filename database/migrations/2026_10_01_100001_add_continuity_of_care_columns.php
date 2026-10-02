<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The doctor who approved the client's assessment becomes their primary doctor (admins can change it).
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('primary_practitioner_id')->nullable()->after('pharmacy_id')->constrained('practitioners')->nullOnDelete();
        });

        // Once-only nudges: a doctor who left a finished session open, and a prescription whose subscription lapsed.
        Schema::table('appointments', function (Blueprint $table) {
            $table->timestamp('closure_reminded_at')->nullable()->after('reminder_sent_at');
        });

        Schema::table('prescriptions', function (Blueprint $table) {
            $table->timestamp('lapse_notified_at')->nullable()->after('reminder_sent_for');
        });
    }

    public function down(): void
    {
        Schema::table('prescriptions', fn (Blueprint $table) => $table->dropColumn('lapse_notified_at'));
        Schema::table('appointments', fn (Blueprint $table) => $table->dropColumn('closure_reminded_at'));
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('primary_practitioner_id');
        });
    }
};
