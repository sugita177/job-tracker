<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_histories', function (Blueprint $table) {
            $table->id();
            // 応募集約が削除されたら履歴も連動して一括削除
            $table->foreignId('job_application_id')->constrained('job_applications')->cascadeOnDelete();
            $table->string('from_status');
            $table->string('to_status');
            $table->string('type'); // TRANSITION, CORRECTION
            $table->text('reason')->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['job_application_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_histories');
    }
};
