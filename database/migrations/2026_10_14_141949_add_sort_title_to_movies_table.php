<?php

use App\Models\Movie;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            $table->string('sort_title')->nullable()->after('title')->index();
        });

        DB::table('movies')->select(['id', 'title'])->orderBy('id')->chunkById(500, function ($movies): void {
            foreach ($movies as $movie) {
                DB::table('movies')->where('id', $movie->id)->update(['sort_title' => Movie::sortableTitle($movie->title)]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            $table->dropColumn('sort_title');
        });
    }
};
