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
        Schema::table('tasks', function (Blueprint $table): void {
            $table->boolean('has_checkpoints')->default(false)->after('status');
        });

        Schema::create('task_checkpoints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->string('title');
            $table->boolean('is_completed')->default(false);
            $table->unsignedInteger('order_index')->default(0);
            $table->unsignedInteger('reward_points_small')->default(0);
            $table->timestamps();

            $table->index(['task_id', 'order_index']);
            $table->index(['task_id', 'is_completed']);
        });

        Schema::table('task_executions', function (Blueprint $table): void {
            $table->foreignId('checkpoint_id')
                ->nullable()
                ->after('task_id')
                ->constrained('task_checkpoints')
                ->nullOnDelete();

            $table->index('checkpoint_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_executions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('checkpoint_id');
        });

        Schema::dropIfExists('task_checkpoints');

        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropColumn('has_checkpoints');
        });
    }
};