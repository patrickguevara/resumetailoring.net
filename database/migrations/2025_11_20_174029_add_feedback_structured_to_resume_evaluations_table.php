<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resume_evaluations', function (Blueprint $table) {
            $table->json('feedback_structured')->nullable()->after('feedback_markdown');
        });
    }

    public function down(): void
    {
        Schema::table('resume_evaluations', function (Blueprint $table) {
            $table->dropColumn('feedback_structured');
        });
    }
};
