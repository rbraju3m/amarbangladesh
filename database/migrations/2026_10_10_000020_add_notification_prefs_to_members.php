<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-type notification choices (feature plan chunk 6): {"site": {"reply": false}, "email": {"answer": false}},
 * only what was turned off; null = everything on (the behaviour before). See Member::wantsNotice().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', fn (Blueprint $table) => $table->json('notification_prefs')->nullable()->after('email_notifications'));
    }

    public function down(): void
    {
        Schema::table('members', fn (Blueprint $table) => $table->dropColumn('notification_prefs'));
    }
};
