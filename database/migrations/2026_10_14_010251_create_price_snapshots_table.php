<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('price_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('watch_id')->constrained()->cascadeOnDelete();
            $table->date('checked_on');
            $table->unsignedInteger('listing_count')->default(0);
            $table->decimal('lowest_price', 8, 2)->nullable();
            $table->decimal('median_price', 8, 2)->nullable();
            $table->decimal('highest_price', 8, 2)->nullable();
            $table->string('lowest_listing_url', 2048)->nullable();
            $table->string('lowest_listing_title')->nullable();
            $table->timestamps();

            $table->unique(['watch_id', 'checked_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_snapshots');
    }
};
