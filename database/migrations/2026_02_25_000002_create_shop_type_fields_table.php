<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_type_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_type_id')->constrained()->cascadeOnDelete();
            $table->string('label'); // Display name e.g. "Size"
            $table->string('key'); // Attribute key for variant JSON e.g. "size"
            $table->string('field_type')->default('text'); // text, number, select
            $table->json('options')->nullable(); // For select: ["S","M","L"] or [{"value":"s","label":"Small"}]
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['shop_type_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_type_fields');
    }
};
