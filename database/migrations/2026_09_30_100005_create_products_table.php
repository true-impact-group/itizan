<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 40)->unique();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('type', 20)->index();
            $table->boolean('requires_prescription')->default(false);
            $table->unsignedInteger('price_halalas');
            $table->boolean('in_stock')->default(true);
            $table->boolean('is_active')->default(true);
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('ALTER TABLE products ADD CONSTRAINT products_price_non_negative CHECK (price_halalas >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
