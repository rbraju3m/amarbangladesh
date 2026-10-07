<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_results', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->unique();
            $table->uuid('visitor_id')->nullable()->index();
            $table->foreignId('location_id')->constrained();
            $table->unsignedTinyInteger('match_pct');
            $table->foreignId('second_location_id')->nullable()->constrained('locations');
            $table->unsignedTinyInteger('second_match_pct')->nullable();
            $table->json('trait_vector');
            $table->json('trait_scores');
            $table->json('answer_ids');
            $table->string('reason_bn', 255);
            $table->string('display_name', 20)->nullable();
            $table->char('owner_token_hash', 64);
            $table->foreignId('referrer_result_id')->nullable()->constrained('quiz_results')->nullOnDelete();
            $table->unsignedTinyInteger('friend_match_pct')->nullable();
            $table->string('scoring_version', 16);
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('visitor_id')->nullable()->index();
            $table->string('name', 40);
            $table->foreignId('quiz_result_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('referrer_result_id')->nullable()->constrained('quiz_results')->nullOnDelete();
            $table->string('device', 10)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['name', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
        Schema::dropIfExists('quiz_results');
    }
};
