<?php

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
        Schema::table('resumes', function (Blueprint $table) {
            $table->string('ingestion_status')->default('completed')->after('content_markdown');
            $table->text('ingestion_error')->nullable()->after('ingestion_status');
            $table->timestamp('ingested_at')->nullable()->after('ingestion_error');
        });

        DB::table('resumes')->update([
            'ingestion_status' => 'completed',
            'ingested_at' => DB::raw('created_at'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('resumes', function (Blueprint $table) {
            $table->dropColumn([
                'ingestion_status',
                'ingestion_error',
                'ingested_at',
            ]);
        });
    }
};
