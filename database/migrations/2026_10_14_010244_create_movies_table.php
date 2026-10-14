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
        Schema::create('movies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tmdb_id')->unique();
            $table->string('title');
            $table->string('original_title')->nullable();
            $table->text('overview')->nullable();
            $table->date('release_date')->nullable();
            $table->unsignedSmallInteger('release_year')->nullable()->index();
            $table->unsignedSmallInteger('runtime')->nullable();
            $table->string('poster_path')->nullable();
            $table->string('backdrop_path')->nullable();
            $table->string('original_language', 12)->nullable();
            $table->decimal('vote_average', 4, 2)->default(0);
            $table->unsignedInteger('vote_count')->default(0);
            $table->decimal('popularity', 10, 3)->default(0)->index();
            $table->timestamp('details_synced_at')->nullable()->index();
            $table->timestamps();

            $table->index('title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movies');
    }
};
