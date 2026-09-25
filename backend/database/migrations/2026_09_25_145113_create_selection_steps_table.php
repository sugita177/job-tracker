<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('selection_steps', function (Blueprint $table) {
            $table->id();
            // 応募集約が削除されたら面談ステップも連動して一括削除
            $table->foreignId('job_application_id')->constrained('job_applications')->cascadeOnDelete();
            $table->string('type'); // CASUAL_INTERVIEW, FIRST_ROUND, etc.
            $table->timestamp('scheduled_at')->nullable();
            $table->string('location_or_url')->nullable();
            $table->string('interviewer_info')->nullable();
            $table->text('prep_memo')->nullable();
            $table->text('review_memo')->nullable();
            $table->string('result'); // PENDING, PASSED, FAILED
            $table->timestamps();

            $table->index(['job_application_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('selection_steps');
    }
};
