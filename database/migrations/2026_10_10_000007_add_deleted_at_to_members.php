<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A member who deleted their account. The row stays (emptied) only so posts and answers they chose
 * to keep still have an author, shown as "মুছে ফেলা অ্যাকাউন্ট". See `Accounts::delete()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', fn (Blueprint $table) => $table->timestamp('deleted_at')->nullable()->after('blocked_at'));
    }

    public function down(): void
    {
        Schema::table('members', fn (Blueprint $table) => $table->dropColumn('deleted_at'));
    }
};
