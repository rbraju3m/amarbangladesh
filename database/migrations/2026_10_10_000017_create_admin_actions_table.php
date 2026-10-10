<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only moderation log (feature plan chunk 3): every hide / restore / remove / keep / block /
 * unblock by an admin, and every automatic hide after enough reports (user_id null). `meta` keeps what
 * the action would otherwise erase (a "keep" deletes the reports) and when the first report came in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 16);
            $table->string('target_type', 16); // post | answer | member
            $table->unsignedBigInteger('target_id');
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['target_type', 'target_id']);
            $table->index(['action', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_actions');
    }
};
