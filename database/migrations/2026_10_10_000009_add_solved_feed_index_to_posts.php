<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The feed's "solved" tab: keyset by id within published posts that have an accepted answer. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', fn (Blueprint $table) => $table->index(['status', 'accepted_answer_id', 'id']));
    }

    public function down(): void
    {
        Schema::table('posts', fn (Blueprint $table) => $table->dropIndex(['status', 'accepted_answer_id', 'id']));
    }
};
