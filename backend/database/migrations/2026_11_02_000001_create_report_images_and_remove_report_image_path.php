<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->string('image_path');
            $table->unsignedTinyInteger('sort_order');
            $table->unique(['report_id', 'sort_order']);
        });

        // Preserve any paths added before this migration, without a legacy runtime field.
        DB::table('reports')->whereNotNull('image_path')->orderBy('id')->each(function ($report) {
            DB::table('report_images')->insert([
                'report_id' => $report->id,
                'image_path' => $report->image_path,
                'sort_order' => 0,
            ]);
        });

        Schema::table('reports', fn (Blueprint $table) => $table->dropColumn('image_path'));
    }

    public function down(): void
    {
        // A rollback to one photo cannot represent multiple photos; never silently discard them.
        if (DB::table('report_images')->select('report_id')->groupBy('report_id')->havingRaw('count(*) > 1')->exists()) {
            throw new RuntimeException('Cannot roll back while reports have multiple photos.');
        }

        Schema::table('reports', fn (Blueprint $table) => $table->string('image_path')->nullable());
        DB::table('report_images')->orderBy('id')->each(function ($image) {
            DB::table('reports')->where('id', $image->report_id)->update(['image_path' => $image->image_path]);
        });
        Schema::dropIfExists('report_images');
    }
};
