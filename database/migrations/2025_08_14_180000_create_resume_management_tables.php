<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('resumes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->longText('content_markdown');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('job_descriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('source_url', 2048);
            $table->string('source_url_hash', 64);
            $table->longText('content_markdown')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'source_url_hash']);
            $table->index('source_url_hash');
        });

        Schema::create('resume_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resume_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_description_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->string('model')->nullable();
            $table->string('headline')->nullable();
            $table->longText('feedback_markdown')->nullable();
            $table->text('notes')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tailored_resumes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resume_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_description_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resume_evaluation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('model')->nullable();
            $table->string('title')->nullable();
            $table->longText('content_markdown');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tailored_resumes');
        Schema::dropIfExists('resume_evaluations');
        Schema::dropIfExists('job_descriptions');
        Schema::dropIfExists('resumes');
    }
};
