<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // 応募が紐づいている企業は誤削除できないよう restrict
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('title');
            $table->string('priority'); // HIGH, MEDIUM, LOW
            $table->string('channel_type')->nullable(); // DIRECT, AGENT, MEDIA, REFERRAL, OTHER
            $table->string('channel_detail_name')->nullable();
            $table->string('current_status'); // INTERESTED, CASUAL_INTERVIEW, etc.
            $table->timestamp('applied_at')->nullable();
            $table->string('job_url')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // ユーザーごとのステータス一覧、応募日順ソート用複合インデックス
            $table->index(['user_id', 'current_status']);
            $table->index(['user_id', 'applied_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_applications');
    }
};
