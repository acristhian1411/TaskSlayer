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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title_original');
            $table->string('title_rpg');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('difficulty_level');
            $table->unsignedInteger('reward_points');
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->index('user_id', 'idx_task_user');
            $table->index('status');
        });

        Schema::create('task_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->boolean('was_completed')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index('task_id', 'idx_exec_task');
            $table->index('user_id', 'idx_exec_user');
            $table->index('started_at', 'idx_exec_started');
            $table->index(['user_id', 'started_at']);
        });

        Schema::create('task_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('execution_id')->nullable()->constrained('task_executions')->nullOnDelete();
            $table->string('type', 20);
            $table->timestamp('timestamp');
            $table->jsonb('metadata')->nullable();

            $table->index('task_id');
            $table->index('execution_id');
            $table->index('type');
            $table->index('timestamp');
        });

        Schema::create('rewards', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('cost_points');
            $table->string('reward_type', 20)->default('time');
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('reward_type');
        });

        Schema::create('reward_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reward_id')->constrained('rewards')->restrictOnDelete();
            $table->unsignedInteger('points_spent');
            $table->timestamp('redeemed_at')->useCurrent();

            $table->index('user_id');
            $table->index('reward_id');
            $table->index('redeemed_at');
        });

        Schema::create('user_points_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('points');
            $table->string('source_type', 50);
            $table->unsignedBigInteger('source_id');
            $table->timestamp('created_at')->useCurrent();

            $table->index('user_id', 'idx_ledger_user');
            $table->index(['source_type', 'source_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_points_ledger');
        Schema::dropIfExists('reward_redemptions');
        Schema::dropIfExists('rewards');
        Schema::dropIfExists('task_events');
        Schema::dropIfExists('task_executions');
        Schema::dropIfExists('tasks');
    }
};
